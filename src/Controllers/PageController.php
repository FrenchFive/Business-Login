<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\PasswordResetBroker;
use App\Http\Request;
use App\Http\Response;
use App\Security\Session;
use App\View\View;

/**
 * Server-rendered HTML pages. Each page is a shell: forms submit over AJAX
 * and the dashboard loads its data from the JSON API.
 */
final class PageController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Session $session,
        private readonly PasswordResetBroker $resets,
    ) {
    }

    public function home(Request $request): Response
    {
        return Response::redirect($this->auth->user($request) === null ? '/login' : '/dashboard');
    }

    public function login(Request $request): Response
    {
        return $this->guestPage($request, 'auth/login', 'Sign in', [
            'status' => $request->queryString('status'),
        ]);
    }

    public function register(Request $request): Response
    {
        return $this->guestPage($request, 'auth/register', 'Create your workspace');
    }

    public function forgotPassword(Request $request): Response
    {
        return $this->guestPage($request, 'auth/forgot-password', 'Reset your password');
    }

    public function resetPassword(Request $request): Response
    {
        $token = $request->queryString('token');

        return $this->guestPage($request, 'auth/reset-password', 'Choose a new password', [
            'token' => $token,
            'valid' => $this->resets->userFor($token) !== null,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $user = $this->auth->user($request);

        if ($user === null) {
            return Response::redirect($this->session->expired() ? '/login?status=expired' : '/login');
        }

        return Response::html($this->view->render('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
        ], 'app'));
    }

    /** @param array<string, mixed> $data */
    private function guestPage(Request $request, string $template, string $title, array $data = []): Response
    {
        if ($this->auth->user($request) !== null) {
            return Response::redirect('/dashboard');
        }

        return Response::html($this->view->render($template, ['title' => $title, 'page' => basename($template)] + $data, 'auth'));
    }
}
