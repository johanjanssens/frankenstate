<?php
/**
 * Shared header template for FrankenState demo pages.
 *
 * Expected variables:
 *   $_title      string         Page title
 *   $_prev       array|null     ['url' => '...', 'label' => '...'] for prev nav link
 *   $_next       array|null     ['url' => '...', 'label' => '...'] for next nav link
 *   $_styleExtra string|null    Page-specific CSS
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>(function(){var t=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme:dark)').matches;if(t==='dark'||(!t&&d))document.documentElement.classList.add('dark')})()</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_title) ?> - FrankenState</title>
    <link rel="stylesheet" href="/style.php">
<?php if (!empty($_styleExtra)): ?>
    <style>
<?= $_styleExtra ?>
    </style>
<?php endif; ?>
</head>
<body>
    <div class="nav">
        <a href="/">&larr; Back to demos</a>
        <button class="theme-toggle" onclick="document.documentElement.classList.toggle('dark');localStorage.setItem('theme',document.documentElement.classList.contains('dark')?'dark':'light')" aria-label="Toggle theme">&#x25D1;</button>
        <span class="nav-links">
<?php if (!empty($_prev)): ?>
            <a href="<?= htmlspecialchars($_prev['url']) ?>">&larr; <?= htmlspecialchars($_prev['label']) ?></a>
<?php endif; ?>
<?php if (!empty($_next)): ?>
            <a href="<?= htmlspecialchars($_next['url']) ?>"><?= htmlspecialchars($_next['label']) ?> &rarr;</a>
<?php endif; ?>
        </span>
    </div>
