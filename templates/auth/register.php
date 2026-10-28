<?php
/**
 * @var App\View\View $view
 * @var string $appName
 */
?>
<section class="auth-card">
    <header class="auth-card__header">
        <h1>Create your workspace</h1>
        <p>Set up <?= e($appName) ?> for your team in under a minute.</p>
    </header>

    <form class="form" data-form="register" action="/api/auth/register" method="post" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <?= $view->partial('field', [
            'name' => 'company', 'label' => 'Company name', 'icon' => 'building-2',
            'placeholder' => 'Acme Inc.', 'autocomplete' => 'organization', 'required' => true, 'autofocus' => true, 'maxlength' => 80,
        ]) ?>

        <?= $view->partial('field', [
            'name' => 'name', 'label' => 'Your full name', 'icon' => 'user-round',
            'placeholder' => 'Alex Morgan', 'autocomplete' => 'name', 'required' => true, 'maxlength' => 80,
        ]) ?>

        <?= $view->partial('field', [
            'name' => 'email', 'label' => 'Work e-mail', 'type' => 'email', 'icon' => 'mail',
            'placeholder' => 'name@company.com', 'autocomplete' => 'email', 'required' => true,
        ]) ?>

        <?= $view->partial('field', [
            'name' => 'password', 'label' => 'Password', 'type' => 'password', 'icon' => 'lock-keyhole',
            'placeholder' => 'Create a strong password', 'autocomplete' => 'new-password', 'required' => true, 'strength' => true,
        ]) ?>

        <button class="button button--primary button--lg button--block" type="submit">
            <span class="button__label">Create workspace</span>
            <?= icon('arrow-right') ?>
        </button>

        <p class="form__fineprint">You'll be the owner of the new workspace. This is a demo: data stays on this server.</p>
    </form>

    <p class="auth-card__switch">Already have an account? <a class="link" href="/login">Sign in</a></p>
</section>
