<?php
/**
 * @var string $title
 * @var int $status
 * @var string $message
 * @var string $appName
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/theme.js')) ?>"></script>
</head>
<body class="error-page">
<main class="error">
    <img src="/assets/img/logo.svg" alt="<?= e($appName) ?>" width="44" height="44">
    <p class="error__code"><?= (int) $status ?></p>
    <h1 class="error__title"><?= e($title) ?></h1>
    <p class="error__message"><?= e($message) ?></p>
    <a class="button button--primary" href="/">Go to the homepage</a>
</main>
</body>
</html>
