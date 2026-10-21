<?php
/**
 * Ficha de marca con su listado de modelos.
 * Recibe: $marca, $resultado, $relacionadas
 */

$marca = $marca ?? null;
$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$relacionadas = $relacionadas ?? [];

$campos = [
    'pais'              => 'País',
    'fundacion'         => 'Fundación',
    'sede'              => 'Sede',
    'propietario'       => 'Grupo',
    'segmento'          => 'Segmento',
    'num_modelos'       => 'Modelos en catálogo',
];
?>
<section class="page-head page-head-brand">
    <div class="shell">
        <nav class="breadcrumb" aria-label="Ruta de navegación">
            <a href="<?= url('') ?>">Inicio</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('marcas') ?>">Marcas</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e($marca['nombre']) ?></span>
        </nav>

        <div class="brand-hero">
            <div class="brand-hero-mark">
                <?= letra_marca((string) $marca['nombre'], 'is-xl') ?>
            </div>

            <div class="brand-hero-copy">
                <p class="section-kicker is-light">Fabricante</p>
                <h1 class="page-title"><?= e($marca['nombre']) ?></h1>

                <?php if (!empty($marca['descripcion'])): ?>
                    <p class="page-lede"><?= e($marca['descripcion']) ?></p>
                <?php endif; ?>

                <dl class="brand-facts">
                    <?php foreach ($campos as $campo => $rotulo): ?>
                        <?php if (!empty($marca[$campo])): ?>
                            <div>
                                <dt><?= e($rotulo) ?></dt>
                                <dd><?= $campo === 'num_modelos' ? (int) $marca[$campo] : e((string) $marca[$campo]) ?></dd>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>
</section>

<section class="section section-tight">
    <div class="shell">
        <header class="results-bar">
            <p class="result-count">
                <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
                <?= (int) $resultado['total'] === 1 ? 'modelo' : 'modelos' ?> de <?= e($marca['nombre']) ?>
            </p>

            <a class="btn btn-ghost btn-sm" href="<?= url('coches') ?>?marcas=<?= (int) $marca['id'] ?>">
                Ver con filtros avanzados
            </a>
        </header>

        <?php if ($resultado['items'] === []): ?>
            <div class="empty-state">
                <p class="empty-title">Aún no hay modelos de esta marca</p>
                <p>Estamos ampliando el catálogo cada semana.</p>
                <a class="btn btn-primary" href="<?= url('coches') ?>">Explorar todo el catálogo</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cars">
                <?php foreach ($resultado['items'] as $c): ?>
                    <?= \App\Core\View::partial('partials/card-coche', ['c' => $c]) ?>
                <?php endforeach; ?>
            </div>

            <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'],
                'marcas/' . $marca['slug']) ?>
        <?php endif; ?>

        <?php if ($relacionadas !== []): ?>
            <div class="brand-related">
                <h2 class="side-title">Destacados del catálogo</h2>
                <div class="grid grid-cars">
                    <?php foreach ($relacionadas as $c): ?>
                        <?= \App\Core\View::partial('partials/card-coche', ['c' => $c]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
