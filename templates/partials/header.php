<?php
/**
 * Cabecera del sitio: navegación principal, buscador y acceso.
 * El estado activo se calcula contra $currentPath.
 */

$usuario = $authUser ?? null;
$ruta = (string) ($currentPath ?? '');

$activo = static function (string $prefijo) use ($ruta): string {
    if ($prefijo === '') {
        return $ruta === '' ? ' is-active' : '';
    }

    return ($ruta === $prefijo || str_starts_with($ruta, $prefijo . '/')) ? ' is-active' : '';
};

$nivel = (int) ($usuario['rol_nivel'] ?? 0);
?>
<header class="site-header" data-header>
    <div class="shell header-inner">
        <a class="brand" href="<?= url('') ?>" aria-label="M MotorPress · inicio">
            <span class="brand-mark" aria-hidden="true">M</span>
            <span class="brand-name">Motor<em>Press</em></span>
        </a>

        <button class="nav-toggle" type="button" data-nav-toggle
                aria-expanded="false" aria-controls="nav-principal">
            <span class="nav-toggle-bar" aria-hidden="true"></span>
            <span class="sr-only">Abrir menú</span>
        </button>

        <nav class="main-nav" id="nav-principal" data-nav>
            <ul class="nav-list">
                <li><a class="nav-link<?= $activo('') ?>" href="<?= url('') ?>">Inicio</a></li>
                <li><a class="nav-link<?= $activo('noticias') ?>" href="<?= url('noticias') ?>">Noticias</a></li>
                <li><a class="nav-link<?= $activo('coches') || $activo('marcas') ? ' is-active' : '' ?>"
                       href="<?= url('coches') ?>">Catálogo</a></li>
                <li><a class="nav-link<?= $activo('comparador') ?>" href="<?= url('comparador') ?>">Comparador</a></li>
                <li><a class="nav-link<?= $activo('sobre-nosotros') ?>" href="<?= url('sobre-nosotros') ?>">Quiénes somos</a></li>
            </ul>

            <form class="nav-search" action="<?= url('buscar') ?>" method="get" role="search">
                <label class="sr-only" for="q-cabecera">Buscar en MotorPress</label>
                <input id="q-cabecera" type="search" name="q" maxlength="120"
                       value="<?= e($query['q'] ?? '') ?>" placeholder="Buscar coche, noticia…"
                       autocomplete="off">
                <button type="submit" aria-label="Buscar">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                        <path fill="currentColor" d="M10 2a8 8 0 105.29 14.03l4.84 4.84 1.41-1.41-4.84-4.84A8 8 0 0010 2zm0 2a6 6 0 110 12 6 6 0 010-12z"/>
                    </svg>
                </button>
            </form>

            <div class="nav-account">
                <?php if ($usuario !== null): ?>
                    <a class="nav-chip" href="<?= url('cuenta') ?>">
                        <span class="avatar" aria-hidden="true">
                            <?= e(mb_strtoupper(mb_substr((string) $usuario['nombre'], 0, 1))) ?>
                        </span>
                        <span class="nav-chip-text"><?= e($usuario['nombre']) ?></span>
                    </a>

                    <?php if ($nivel >= 30): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= url('admin') ?>">Panel</a>
                    <?php endif; ?>

                    <form class="inline-form" action="<?= url('salir') ?>" method="post" data-confirm="¿Cerrar sesión?">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit">Salir</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="<?= url('acceso') ?>">Entrar</a>
                    <?php if (registro_abierto()): ?>
                        <a class="btn btn-primary btn-sm" href="<?= url('registro') ?>">Crear cuenta</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
