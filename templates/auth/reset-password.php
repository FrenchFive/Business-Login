<?php
/**
 * @var App\View\View $view
 * @var string $token
 * @var bool $valid
 */
?>
<section class="auth-card">
    <div class="success-state success-state--warning" data-step="invalid" <?= $valid ? 'hidden' : '' ?>>
        <span class="success-state__icon"><?= icon('triangle-alert') ?></span>
        <h1>This link has expired</h1>
        <p>Password reset links work once and expire after 60 minutes. Request a fresh one to continue.</p>
        <a class="button button--primary button--lg button--block" href="/forgot-password">Request a new link</a>
        <a class="back-link back-link--center" href="/login"><?= icon('arrow-left') ?> Back to sign in</a>
    </div>

    <?php if ($valid): ?>
        <div data-step="reset">
            <header class="auth-card__header">
                <span class="auth-card__icon"><?= icon('lock-keyhole') ?></span>
                <h1>Choose a new password</h1>
                <p>Make it something you haven't used before. All remembered devices will be signed out.</p>
            </header>

            <form class="form" data-form="reset-password" action="/api/auth/reset-password" method="post" novalidate>
                <div class="form-alert" data-form-alert hidden></div>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <?= $view->partial('field', [
                    'name' => 'password', 'label' => 'New password', 'type' => 'password', 'icon' => 'lock-keyhole',
                    'placeholder' => 'Create a strong password', 'autocomplete' => 'new-password', 'required' => true,
                    'autofocus' => true, 'strength' => true,
                ]) ?>

                <?= $view->partial('field', [
                    'name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password',
                    'icon' => 'lock-keyhole', 'placeholder' => 'Type it again', 'autocomplete' => 'new-password', 'required' => true,
                ]) ?>

                <button class="button button--primary button--lg button--block" type="submit">
                    <span class="button__label">Update password</span>
                </button>
            </form>
        </div>
    <?php endif; ?>
</section>
