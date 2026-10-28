<?php
/**
 * @var string $title
 * @var string $appName
 * @var string $csrfToken
 * @var string $stylesheet page-specific stylesheet (relative to public/assets/css)
 * @var string $script     page-specific ES module (relative to public/assets/js)
 */
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/' . $stylesheet)) ?>">
    <script src="<?= e(asset('js/theme.js')) ?>"></script>
    <script type="module" src="<?= e(asset('js/' . $script)) ?>"></script>
