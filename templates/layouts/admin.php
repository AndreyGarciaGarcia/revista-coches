<?php
/**
 * Envoltorio del panel de administración.
 * Recibe: $content, $title, $authUser, $flash, $currentPath
 */

$usuario = $authUser ?? null;
$ruta = (string) ($currentPath ?? '');
$nivel = (int) ($usuario['rol_nivel'] ?? 0);

$activo = static function (string $prefijo) use ($ruta): string {
    return $ruta === 'admin/' . $prefijo || str_starts_with($ruta, 'admin/' . $prefijo . '/')
        ? ' is-active'
        : '';
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title ?? 'Panel') ?> · MotorPress</title>
    <meta name="description" content="<?= e($description ?? 'Panel de administración de MotorPress.') ?>">

    <?= csrf_meta() ?>

    <link rel="icon" href="<?= asset('img/favicon.svg', false) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= asset('fonts/oswald-latin.woff2', false) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('css/fuentes.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>

<body class="admin-body" data-base="<?= e(url('')) ?>">
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<div class="admin-shell">

    <aside class="admin-side" data-admin-side>
        <div class="admin-brand">
            <a class="brand" href="<?= url('') ?>">
                <span class="brand-mark" aria-hidden="true">M</span>
                <span class="brand-name">Motor<em>Press</em></span>
            </a>
            <span class="admin-brand-tag">Panel</span>
        </div>

        <nav class="admin-nav" aria-label="Navegación del panel">
            <p class="admin-nav-title">Contenido</p>
            <ul>
                <li>
                    <a class="<?= $ruta === 'admin' ? 'is-active' : '' ?>" href="<?= url('admin') ?>">
                        <span class="nav-ico" aria-hidden="true">▦</span> Resumen
                    </a>
                </li>
                <li>
                    <a class="<?= $activo('noticias') ?>" href="<?= url('admin/noticias') ?>">
                        <span class="nav-ico" aria-hidden="true">✎</span> Noticias
                    </a>
                </li>
                <li>
                    <a class="<?= $activo('coches') ?>" href="<?= url('admin/coches') ?>">
                        <span class="nav-ico" aria-hidden="true">⛬</span> Catálogo
                    </a>
                </li>
            </ul>

            <p class="admin-nav-title">Comunidad</p>
            <ul>
                <li>
                    <a class="<?= $activo('comentarios') ?>" href="<?= url('admin/comentarios') ?>">
                        <span class="nav-ico" aria-hidden="true">✍</span> Comentarios
                    </a>
                </li>
                <li>
                    <a class="<?= $activo('mensajes') ?>" href="<?= url('admin/mensajes') ?>">
                        <span class="nav-ico" aria-hidden="true">✉</span> Mensajes
                    </a>
                </li>
            </ul>

            <?php if ($nivel >= 90): ?>
                <p class="admin-nav-title">Sistema</p>
                <ul>
                    <li>
                        <a class="<?= $activo('usuarios') ?>" href="<?= url('admin/usuarios') ?>">
                            <span class="nav-ico" aria-hidden="true">☺</span> Usuarios
                        </a>
                    </li>
                    <li>
                        <a class="<?= $activo('auditoria') ?>" href="<?= url('admin/auditoria') ?>">
                            <span class="nav-ico" aria-hidden="true">◔</span> Auditoría
                        </a>
                    </li>
                    <li>
                        <a class="<?= $activo('configuracion') ?>" href="<?= url('admin/configuracion') ?>">
                            <span class="nav-ico" aria-hidden="true">⚙</span> Configuración
                        </a>
                    </li>
                    <li>
                        <a class="<?= $activo('seguridad') ?>" href="<?= url('admin/seguridad') ?>">
                            <span class="nav-ico" aria-hidden="true">⛨</span> Seguridad
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </nav>

        <div class="admin-side-foot">
            <a class="btn btn-ghost btn-sm btn-block" href="<?= url('') ?>" target="_blank" rel="noopener">
                Ver la web ↗
            </a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-top">
            <button class="nav-toggle" type="button" data-admin-toggle aria-expanded="false"
                    aria-controls="admin-side">
                <span class="nav-toggle-bar" aria-hidden="true"></span>
                <span class="sr-only">Abrir menú del panel</span>
            </button>

            <h1 class="admin-top-title"><?= e($title ?? 'Panel') ?></h1>

            <div class="admin-top-actions">
                <?php if ($nivel >= 30): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= url('admin/noticias/nueva') ?>">+ Nuevo artículo</a>
                <?php endif; ?>

                <span class="nav-chip">
                    <span class="avatar" aria-hidden="true">
                        <?= e(mb_strtoupper(mb_substr((string) ($usuario['nombre'] ?? '?'), 0, 1))) ?>
                    </span>
                    <span class="nav-chip-text"><?= e($usuario['nombre'] ?? '') ?></span>
                </span>

                <form class="inline-form" action="<?= url('salir') ?>" method="post" data-confirm="¿Cerrar sesión?">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit">Salir</button>
                </form>
            </div>
        </header>

        <?= \App\Core\View::partial('partials/flash') ?>

        <main id="contenido" class="admin-content">
            <?= $content ?>
        </main>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
