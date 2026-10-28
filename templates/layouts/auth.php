<?php
/**
 * Split-screen layout for the sign-in / sign-up / recovery pages.
 *
 * @var App\View\View $view
 * @var string $content
 * @var string $title
 * @var string $page
 * @var string $appName
 */
?>
<!doctype html>
<html lang="en">
<head>
<?= $view->partial('head', ['title' => $title, 'stylesheet' => 'auth.css', 'script' => 'auth.js']) ?>
</head>
<body class="auth-page" data-page="<?= e($page) ?>">
<?= App\View\Icons::sprite() ?>
<div class="auth">
    <aside class="auth__brand">
        <div class="auth__brand-inner">
            <a class="brand brand--on-dark" href="/login">
                <img src="/assets/img/logo.svg" alt="" width="34" height="34">
                <span><?= e($appName) ?></span>
            </a>

            <div class="pitch">
                <span class="pitch__eyebrow"><?= icon('sparkles') ?> Business portal</span>
                <h2 class="pitch__title">The secure front door to <span class="pitch__highlight">your business.</span></h2>
                <p class="pitch__lead">One sign-in for your whole team, with brute-force protection, remembered devices and a complete audit trail built in.</p>

                <ul class="pitch__features">
                    <li>
                        <span class="pitch__feature-icon"><?= icon('shield-check') ?></span>
                        <span><strong>Hardened by default</strong>CSRF tokens, Argon2id hashing and rate-limited sign-ins.</span>
                    </li>
                    <li>
                        <span class="pitch__feature-icon"><?= icon('fingerprint') ?></span>
                        <span><strong>Remembered devices</strong>Rotating single-use tokens you can revoke at any time.</span>
                    </li>
                    <li>
                        <span class="pitch__feature-icon"><?= icon('history') ?></span>
                        <span><strong>Full audit trail</strong>Every sign-in, failure and password change is recorded.</span>
                    </li>
                </ul>
            </div>

            <figure class="preview" aria-hidden="true">
                <figcaption class="preview__header">
                    <span class="preview__pulse"></span> Sign-in activity <span class="preview__tag">Example</span>
                </figcaption>
                <ul class="preview__list">
                    <li>
                        <span class="avatar avatar--sm" data-hue="1">PS</span>
                        <span class="preview__text"><strong>Priya Shah</strong>Signed in · Chrome on macOS</span>
                        <span class="preview__time">now</span>
                    </li>
                    <li>
                        <span class="avatar avatar--sm" data-hue="4">JL</span>
                        <span class="preview__text"><strong>Jordan Lee</strong>Remembered device · Safari on iOS</span>
                        <span class="preview__time">4 min</span>
                    </li>
                    <li class="is-blocked">
                        <span class="preview__blocked-icon"><?= icon('shield-alert') ?></span>
                        <span class="preview__text"><strong>Unknown</strong>5 failed attempts · sign-in paused</span>
                        <span class="preview__time">12 min</span>
                    </li>
                </ul>
            </figure>
        </div>
    </aside>

    <main class="auth__main">
        <header class="auth__top">
            <a class="brand auth__mobile-brand" href="/login">
                <img src="/assets/img/logo.svg" alt="" width="30" height="30">
                <span><?= e($appName) ?></span>
            </a>
            <?= $view->partial('theme-toggle') ?>
        </header>

        <div class="auth__content">
            <?= $content ?>
        </div>

        <footer class="auth__footer">
            <span>&copy; <?= date('Y') ?> <?= e($appName) ?> demo</span>
            <span class="auth__stack">PHP · AJAX · Vanilla JS</span>
        </footer>
    </main>
</div>
<div class="toasts" data-toasts aria-live="polite"></div>
</body>
</html>
