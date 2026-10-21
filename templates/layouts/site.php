<?php
/**
 * Envoltorio del sitio público.
 *
 * Variables que recibe:
 *   $title, $description, $canonical, $content, $authUser, $flash,
 *   $currentPath, $appName, $query
 *
 * Las cabeceras de seguridad (CSP con nonce) las emite App; por eso los
 * pocos scripts inline llevan el atributo nonce.
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title><?= e($title ?? 'MotorPress') ?> · MotorPress</title>
    <meta name="description" content="<?= e($description ?? '') ?>">
    <?php if (!empty($canonical)): ?>
        <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= e($robots ?? 'index, follow') ?>, max-image-preview:large">
    <meta name="theme-color" content="#ffffff">
    <meta name="generator" content="MotorPress 1.0">

    <meta property="og:site_name" content="MotorPress">
    <meta property="og:type" content="<?= str_contains((string) ($currentPath ?? ''), 'noticias/') ? 'article' : 'website' ?>">
    <meta property="og:title" content="<?= e($title ?? 'MotorPress') ?>">
    <meta property="og:description" content="<?= e($description ?? '') ?>">
    <meta property="og:locale" content="es_ES">
    <?php if (!empty($canonical)): ?>
        <meta property="og:url" content="<?= e($canonical) ?>">
    <?php endif; ?>

    <?= csrf_meta() ?>

    <link rel="icon" href="<?= asset('img/favicon.svg', false) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= asset('img/favicon.svg', false) ?>">
    <link rel="preload" href="<?= asset('fonts/oswald-latin.woff2', false) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= asset('fonts/plex-sans-latin.woff2', false) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('css/fuentes.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <script type="application/ld+json" nonce="<?= e(\App\Core\Security::nonce()) ?>">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'MotorPress',
        'url' => $appUrl ?? '',
        'description' => 'Noticias, pruebas y catálogo de coches.',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => ($appUrl ?? '') . '/buscar?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>
    </script>
</head>
<body data-base="<?= e(url('')) ?>">
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<?= \App\Core\View::partial('partials/header') ?>
<?= \App\Core\View::partial('partials/flash') ?>

<main id="contenido">
    <?= $content ?>
</main>

<?= \App\Core\View::partial('partials/footer') ?>

<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
