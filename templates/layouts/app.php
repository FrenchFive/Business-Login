<?php
/**
 * Signed-in application shell: sidebar navigation + content area.
 *
 * @var App\View\View $view
 * @var App\Auth\User $user
 * @var string $content
 * @var string $title
 * @var string $appName
 * @var bool $demo
 */
$organizationInitials = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $user->organizationName) ?: 'W', 0, 2));
$nav = [
    'overview' => ['layout-dashboard', 'Overview'],
    'security' => ['shield-check', 'Security'],
    'team' => ['users', 'Team'],
    'profile' => ['user-pen', 'Profile'],
];
?>
<!doctype html>
<html lang="en">
<head>
<?= $view->partial('head', ['title' => $title, 'stylesheet' => 'dashboard.css', 'script' => 'dashboard.js']) ?>
</head>
<body class="app-page" data-page="dashboard">
<?= App\View\Icons::sprite() ?>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="/dashboard">
            <img src="/assets/img/logo.svg" alt="" width="30" height="30">
            <span><?= e($appName) ?></span>
        </a>

        <div class="workspace">
            <span class="workspace__logo"><?= e($organizationInitials) ?></span>
            <span class="workspace__text">
                <strong data-org-name><?= e($user->organizationName) ?></strong>
                <span>Business workspace</span>
            </span>
        </div>

        <nav class="nav" aria-label="Dashboard sections">
            <span class="nav__label">Menu</span>
            <?php foreach ($nav as $id => [$iconName, $label]): ?>
                <a class="nav__item" href="#<?= e($id) ?>" data-tab-link="<?= e($id) ?>">
                    <?= icon($iconName) ?>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__footer">
            <div class="user-chip">
                <span class="avatar" data-hue="<?= $user->avatarHue() ?>" data-user-initials><?= e($user->initials()) ?></span>
                <span class="user-chip__text">
                    <strong data-user-name><?= e($user->name) ?></strong>
                    <span><?= e($user->email) ?></span>
                </span>
            </div>
            <button class="button button--secondary button--block" type="button" data-logout>
                <?= icon('log-out') ?>
                <span class="button__label">Sign out</span>
            </button>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="topbar__heading">
                <p class="topbar__eyebrow" data-greeting>Welcome</p>
                <h1 class="topbar__title" data-tab-title>Overview</h1>
            </div>
            <div class="topbar__actions">
                <?php if ($demo): ?>
                    <span class="pill pill--demo" title="Demo data — safe to explore"><?= icon('sparkles') ?> Demo mode</span>
                <?php endif; ?>
                <?= $view->partial('theme-toggle') ?>
            </div>
        </header>

        <main class="content" id="content">
            <?= $content ?>
        </main>
    </div>
</div>
<div class="toasts" data-toasts aria-live="polite"></div>
</body>
</html>
