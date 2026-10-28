<?php

declare(strict_types=1);

namespace App;

use App\Auth\Auth;
use App\Auth\OrganizationRepository;
use App\Auth\PasswordHasher;
use App\Auth\PasswordPolicy;
use App\Auth\PasswordResetBroker;
use App\Auth\RememberMe;
use App\Auth\UserRepository;
use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Controllers\TeamController;
use App\Database\Database;
use App\Database\DemoSeeder;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Mail\FileMailer;
use App\Security\Csrf;
use App\Security\RateLimiter;
use App\Security\SecurityHeaders;
use App\Security\SecurityLog;
use App\Security\Session;
use App\Support\Clock;
use App\View\View;
use Throwable;

/**
 * The application kernel: wires the services for one request, applies the
 * cross-cutting checks (session, CSRF, JSON-only API) and dispatches the route.
 */
final class App
{
    private readonly View $view;

    public function __construct(
        private readonly Config $config,
        private readonly Clock $clock = new Clock(),
    ) {
        $this->view = new View($config->rootPath . '/templates', [
            'appName' => $config->appName,
            'demo' => $config->demo,
            'rememberDays' => $config->rememberDays,
            'csrfToken' => '',
        ]);
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->process($request);
        } catch (HttpException $e) {
            $response = $this->renderError($request, $e);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
            $message = $this->config->debug ? $e->getMessage() : 'Something went wrong on our side. Please try again in a moment.';
            $response = $this->renderError($request, new HttpException(500, 'server_error', $message));
        }

        return SecurityHeaders::apply($response, $request);
    }

    private function process(Request $request): Response
    {
        $config = $this->config;
        $clock = $this->clock;

        $db = Database::connect($config);
        $db->migrate(
            $config->rootPath . '/database/schema',
            $config->demo ? static fn (Database $db) => (new DemoSeeder($db, $clock))->run() : null,
        );

        $session = new Session($config->path($config->sessionPath), $config->sessionIdleMinutes * 60, $request->secure, $clock);
        $csrf = new Csrf($session);
        $users = new UserRepository($db, $clock);
        $organizations = new OrganizationRepository($db, $clock);
        $hasher = new PasswordHasher();
        $policy = new PasswordPolicy();
        $rememberMe = new RememberMe($db, $clock, $config->rememberDays, $request->secure);
        $log = new SecurityLog($db, $clock);
        $resets = new PasswordResetBroker($db, $users, $clock);
        $auth = new Auth($session, $csrf, $users, $hasher, $rememberMe, $log, $clock);

        $session->start();

        if ($request->isUnsafe()) {
            $this->guardStateChange($request, $csrf);
        }

        $auth->user($request); // resolve the session or "remember me" cookie up-front
        $this->view->share('csrfToken', $csrf->token());

        if (!$request->isUnsafe()) {
            // Reads never write the session, so unlock it for parallel AJAX requests.
            $session->release();
        }

        $pages = new PageController($this->view, $auth, $session, $resets);
        $authApi = new AuthController(
            $config, $db, $auth, $users, $organizations, $hasher, $policy, $resets, $rememberMe,
            new RateLimiter($db, $clock), $log,
            new FileMailer($config->path($config->mailPath), "{$config->appName} <no-reply@keystone.test>", $clock),
        );
        $account = new AccountController($config, $auth, $users, $organizations, $hasher, $policy, $rememberMe, $log, $clock);
        $team = new TeamController($auth, $users);

        return (new Router())
            // Pages (HTML)
            ->get('/', $pages->home(...))
            ->get('/login', $pages->login(...))
            ->get('/register', $pages->register(...))
            ->get('/forgot-password', $pages->forgotPassword(...))
            ->get('/reset-password', $pages->resetPassword(...))
            ->get('/dashboard', $pages->dashboard(...))
            // Auth API (JSON)
            ->post('/api/auth/login', $authApi->login(...))
            ->post('/api/auth/register', $authApi->register(...))
            ->post('/api/auth/logout', $authApi->logout(...))
            ->post('/api/auth/forgot-password', $authApi->forgotPassword(...))
            ->post('/api/auth/reset-password', $authApi->resetPassword(...))
            // Account API (JSON, signed-in only)
            ->get('/api/me', $account->me(...))
            ->post('/api/account/profile', $account->updateProfile(...))
            ->post('/api/account/password', $account->changePassword(...))
            ->get('/api/account/security', $account->security(...))
            ->post('/api/account/devices/forget', $account->forgetDevices(...))
            ->get('/api/team', $team->index(...))
            ->dispatch($request);
    }

    /**
     * Every state-changing request must be JSON and carry the CSRF token.
     * Requiring `application/json` also means a plain cross-site <form> can never
     * reach the API (browsers cannot send that content type without CORS).
     */
    private function guardStateChange(Request $request, Csrf $csrf): void
    {
        if (!$request->isApi()) {
            throw new HttpException(405, 'method_not_allowed', 'This page only accepts GET requests.');
        }

        if (!$request->isJson()) {
            throw new HttpException(415, 'unsupported_media_type', 'Send the request body as JSON (Content-Type: application/json).');
        }

        if (!$csrf->validate($request->header('x-csrf-token'))) {
            throw new HttpException(403, 'csrf_token_mismatch', 'Your page has expired. Refresh it and try again.');
        }
    }

    private function renderError(Request $request, HttpException $e): Response
    {
        if ($request->isApi() || str_contains($request->header('accept') ?? '', 'application/json')) {
            $response = Response::json($e->toArray(), $e->status);
        } else {
            $response = Response::html($this->view->render('errors/error', [
                'title' => $e->status === 404 ? 'Page not found' : 'Something went wrong',
                'status' => $e->status,
                'message' => $e->getMessage(),
            ]), $e->status);
        }

        foreach ($e->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
