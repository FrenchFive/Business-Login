<?php
/**
 * @var App\View\View $view
 * @var string $appName
 * @var bool $demo
 * @var int $rememberDays
 * @var string $status
 */
$notices = [
    'signed-out' => ['success', 'circle-check', 'You have been signed out. See you soon!'],
    'expired' => ['warning', 'clock', 'Your session expired after a period of inactivity. Please sign in again.'],
    'password-reset' => ['success', 'shield-check', 'Your password has been reset. Sign in with your new password.'],
];
$notice = $notices[$status] ?? null;
?>
<section class="auth-card">
    <header class="auth-card__header">
        <h1>Welcome back</h1>
        <p>Sign in to your <?= e($appName) ?> workspace.</p>
    </header>

    <?php if ($notice !== null): ?>
        <div class="alert alert--<?= e($notice[0]) ?>" role="status">
            <?= icon($notice[1]) ?>
            <span><?= e($notice[2]) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($demo): ?>
        <div class="demo-callout">
            <span class="demo-callout__icon"><?= icon('sparkles') ?></span>
            <span class="demo-callout__body">
                <strong>Try the demo account</strong>
                <span><code><?= e(App\Database\DemoSeeder::EMAIL) ?></code> · <code><?= e(App\Database\DemoSeeder::PASSWORD) ?></code></span>
            </span>
            <button class="button button--secondary button--sm" type="button"
                    data-fill-demo
                    data-email="<?= e(App\Database\DemoSeeder::EMAIL) ?>"
                    data-password="<?= e(App\Database\DemoSeeder::PASSWORD) ?>">Fill in</button>
        </div>
    <?php endif; ?>

    <form class="form" data-form="login" action="/api/auth/login" method="post" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <?= $view->partial('field', [
            'name' => 'email', 'label' => 'Work e-mail', 'type' => 'email', 'icon' => 'mail',
            'placeholder' => 'name@company.com', 'autocomplete' => 'username', 'required' => true, 'autofocus' => true,
        ]) ?>

        <?= $view->partial('field', [
            'name' => 'password', 'label' => 'Password', 'type' => 'password', 'icon' => 'lock-keyhole',
            'placeholder' => 'Your password', 'autocomplete' => 'current-password', 'required' => true,
            'aside' => '<a class="link link--sm" href="/forgot-password">Forgot password?</a>',
        ]) ?>

        <label class="checkbox">
            <input type="checkbox" name="remember" value="1">
            <span class="checkbox__box"><?= icon('check') ?></span>
            <span>Keep me signed in for <?= (int) $rememberDays ?> days</span>
        </label>

        <button class="button button--primary button--lg button--block" type="submit">
            <span class="button__label">Sign in</span>
            <?= icon('arrow-right') ?>
        </button>
    </form>

    <p class="auth-card__switch">New to <?= e($appName) ?>? <a class="link" href="/register">Create a workspace</a></p>
</section>
