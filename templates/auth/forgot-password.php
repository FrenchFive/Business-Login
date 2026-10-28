<?php
/**
 * @var App\View\View $view
 */
?>
<section class="auth-card">
    <div data-step="request">
        <a class="back-link" href="/login"><?= icon('arrow-left') ?> Back to sign in</a>
        <header class="auth-card__header">
            <span class="auth-card__icon"><?= icon('key-round') ?></span>
            <h1>Forgot your password?</h1>
            <p>Enter your work e-mail and we'll send you a secure link to choose a new one.</p>
        </header>

        <form class="form" data-form="forgot-password" action="/api/auth/forgot-password" method="post" novalidate>
            <div class="form-alert" data-form-alert hidden></div>

            <?= $view->partial('field', [
                'name' => 'email', 'label' => 'Work e-mail', 'type' => 'email', 'icon' => 'mail',
                'placeholder' => 'name@company.com', 'autocomplete' => 'email', 'required' => true, 'autofocus' => true,
            ]) ?>

            <button class="button button--primary button--lg button--block" type="submit">
                <span class="button__label">Send reset link</span>
            </button>
        </form>
    </div>

    <div class="success-state" data-step="sent" hidden>
        <span class="success-state__icon"><?= icon('mail-check') ?></span>
        <h1>Check your inbox</h1>
        <p data-sent-message></p>

        <div class="demo-mail" data-demo-mail hidden>
            <div class="demo-mail__header"><?= icon('sparkles') ?> Demo mode — no real e-mail was sent</div>
            <p>The message was saved to <code data-demo-mail-file></code>. Open the link it contains:</p>
            <a class="button button--secondary button--block" data-demo-mail-link href="#">
                <?= icon('external-link') ?> Open reset link
            </a>
        </div>

        <button class="button button--ghost button--block" type="button" data-restart>Use a different e-mail</button>
        <a class="back-link back-link--center" href="/login"><?= icon('arrow-left') ?> Back to sign in</a>
    </div>
</section>
