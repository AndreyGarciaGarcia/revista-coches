<?php
/**
 * Listado de noticias con barra lateral de categorías, etiquetas y
 * control de ordenación.
 *
 * Recibe: $resultado, $categorias, $actual, $etiqueta, $texto, $orden,
 *         $etiquetas, $masLeidas
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$categorias = $categorias ?? [];
$etiquetas = $etiquetas ?? [];
$masLeidas = $masLeidas ?? [];
$texto = (string) ($texto ?? '');
$orden = (string) ($orden ?? 'recientes');

$baseUrl = !empty($etiqueta) ? 'etiqueta/' . $etiqueta['slug'] : 'noticias';
$queryBase = [];
if ($texto !== '') {
    $queryBase['q'] = $texto;
}
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Redacción</p>
        <h1 class="page-title">
            <?php if (!empty($actual)): ?>
                <?= e($actual['nombre']) ?>
            <?php elseif (!empty($etiqueta)): ?>
                Etiqueta «<?= e($etiqueta['nombre']) ?>»
            <?php elseif ($texto !== ''): ?>
                Resultados para «<?= e($texto) ?>»
            <?php else: ?>
                Noticias de coches
            <?php endif; ?>
        </h1>

        <?php if (!empty($actual['descripcion'])): ?>
            <p class="page-lede"><?= e($actual['descripcion']) ?></p>
        <?php endif; ?>

        <div class="filter-bar filter-bar-top">
            <form class="inline-form inline-form-grow" action="<?= url('noticias') ?>" method="get" role="search">
                <label class="sr-only" for="buscar-noticias">Buscar noticias</label>
                <input id="buscar-noticias" type="search" name="q" value="<?= e($texto) ?>"
                       maxlength="120" placeholder="Buscar en los artículos…">
                <button class="btn btn-ghost" type="submit">Buscar</button>
            </form>

            <div class="segmented" role="group" aria-label="Ordenar por">
                <?php foreach ([
                    'recientes'   => 'Más recientes',
                    'vistas'      => 'Más leídas',
                    'comentarios' => 'Comentados',
                ] as $clave => $rotulo): ?>
                    <a class="segmented-item<?= $orden === $clave ? ' is-active' : '' ?>"
                       href="<?= url($baseUrl) ?>?<?= e(http_build_query($queryBase + ['orden' => $clave])) ?>">
                        <?= e($rotulo) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="result-count">
            <?= number_format((int) $resultado['total'], 0, ',', '.') ?>
            <?= (int) $resultado['total'] === 1 ? 'artículo' : 'artículos' ?>
        </p>
    </div>
</section>

<section class="section section-tight">
    <div class="shell split split-wide">
        <div class="split-main">
            <?php if ($resultado['items'] === []): ?>
                <div class="empty-state">
                    <p class="empty-title">No hay artículos que mostrar</p>
                    <p>Prueba con otra búsqueda o explora las secciones de la derecha.</p>
                    <a class="btn btn-primary" href="<?= url('noticias') ?>">Ver todo</a>
                </div>
            <?php else: ?>
                <div class="grid grid-news">
                    <?php foreach ($resultado['items'] as $n): ?>
                        <?= \App\Core\View::partial('partials/card-noticia', ['n' => $n]) ?>
                    <?php endforeach; ?>
                </div>

                <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], $baseUrl,
                    $queryBase + (isset($actual) && $actual !== null ? ['categoria' => $actual['slug']] : [])) ?>
            <?php endif; ?>
        </div>

        <aside class="split-side">
            <h2 class="side-title">Secciones</h2>
            <ul class="side-list">
                <li>
                    <a class="<?= !isset($actual) ? 'is-current' : '' ?>" href="<?= url('noticias') ?>">
                        <span>Todas</span>
                        <span class="side-count"><?= (int) $resultado['total'] ?></span>
                    </a>
                </li>
                <?php foreach ($categorias as $cat): ?>
                    <li>
                        <a class="<?= isset($actual) && $actual['id'] === $cat['id'] ? 'is-current' : '' ?>"
                           href="<?= url('categoria/' . $cat['slug']) ?>">
                            <span><?= e($cat['nombre']) ?></span>
                            <span class="side-count"><?= (int) $cat['num_noticias'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($masLeidas !== []): ?>
                <h2 class="side-title">Populares</h2>
                <ol class="side-ranked">
                    <?php foreach (array_slice($masLeidas, 0, 5) as $n): ?>
                        <li><a href="<?= url('noticias/' . $n['slug']) ?>"><?= e(excerpt((string) $n['titulo'], 90)) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if ($etiquetas !== []): ?>
                <h2 class="side-title">Etiquetas</h2>
                <div class="tag-cloud">
                    <?php foreach ($etiquetas as $t): ?>
                        <a class="tag" href="<?= url('etiqueta/' . $t['slug']) ?>"><?= e($t['nombre']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>
