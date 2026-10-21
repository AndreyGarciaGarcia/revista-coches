<?php
/**
 * Búsqueda global.
 * Recibe: $texto, $coches, $noticias, $marcas, $total
 */

$texto = (string) ($texto ?? '');
$coches = $coches ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$noticias = $noticias ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$marcas = $marcas ?? [];
$total = (int) ($total ?? 0);

$sugerencias = ['SUV familiar', 'eléctrico', 'híbrido enchufable', 'berlina alemana', 'deportivo'];
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Búsqueda</p>
        <h1 class="page-title">
            <?= $texto !== '' ? 'Resultados para «' . e($texto) . '»' : 'Buscar en MotorPress' ?>
        </h1>

        <form class="search-hero" action="<?= url('buscar') ?>" method="get" role="search">
            <label class="sr-only" for="q-global">Buscar coches, noticias y marcas</label>
            <input id="q-global" type="search" name="q" maxlength="120" required
                   value="<?= e($texto) ?>" placeholder="¿Qué estás buscando?"
                   data-search-suggest>
            <button class="btn btn-primary" type="submit">Buscar</button>
        </form>

        <?php if ($texto !== ''): ?>
            <p class="result-count">
                <strong><?= number_format($total, 0, ',', '.') ?></strong>
                <?= $total === 1 ? 'resultado' : 'resultados' ?> en coches, artículos y marcas
            </p>
        <?php else: ?>
            <ul class="chip-cloud">
                <?php foreach ($sugerencias as $s): ?>
                    <li><a class="tag" href="<?= url('buscar') ?>?q=<?= e(urlencode($s)) ?>"><?= e($s) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="section section-tight">
    <div class="shell">
        <?php if ($texto !== '' && $total === 0): ?>
            <div class="empty-state">
                <p class="empty-title">No hemos encontrado nada para «<?= e($texto) ?>»</p>
                <p>Comprueba la ortografía o prueba con un término más genérico.</p>
                <a class="btn btn-primary" href="<?= url('coches') ?>">Explorar el catálogo</a>
            </div>
        <?php endif; ?>

        <?php if ($marcas !== []): ?>
            <section class="result-group">
                <header class="section-head is-plain">
                    <div>
                        <p class="section-kicker">Marcas</p>
                        <h2 class="section-title"><?= count($marcas) ?> <?= count($marcas) === 1 ? 'marca' : 'marcas' ?></h2>
                    </div>
                </header>

                <ul class="brand-grid">
                    <?php foreach ($marcas as $m): ?>
                        <li>
                            <a class="brand-tile" href="<?= url('marcas/' . $m['slug']) ?>">
                                <?= letra_marca((string) $m['nombre']) ?>
                                <span class="brand-tile-name"><?= e($m['nombre']) ?></span>
                                <span class="brand-tile-count"><?= (int) $m['num_modelos'] ?> modelos</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (($coches['items'] ?? []) !== []): ?>
            <section class="result-group">
                <header class="section-head">
                    <div>
                        <p class="section-kicker">Catálogo</p>
                        <h2 class="section-title">
                            <?= number_format((int) $coches['total'], 0, ',', '.') ?>
                            <?= (int) $coches['total'] === 1 ? 'vehículo' : 'vehículos' ?>
                        </h2>
                    </div>
                    <a class="section-more" href="<?= url('coches') ?>?q=<?= e(urlencode($texto)) ?>">
                        Ver todos con filtros →
                    </a>
                </header>

                <div class="grid grid-cars">
                    <?php foreach ($coches['items'] as $c): ?>
                        <?= \App\Core\View::partial('partials/card-coche', ['c' => $c]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (($noticias['items'] ?? []) !== []): ?>
            <section class="result-group">
                <header class="section-head">
                    <div>
                        <p class="section-kicker">Redacción</p>
                        <h2 class="section-title">
                            <?= number_format((int) $noticias['total'], 0, ',', '.') ?>
                            <?= (int) $noticias['total'] === 1 ? 'artículo' : 'artículos' ?>
                        </h2>
                    </div>
                    <a class="section-more" href="<?= url('noticias') ?>?q=<?= e(urlencode($texto)) ?>">
                        Ver todas las noticias →
                    </a>
                </header>

                <div class="grid grid-news">
                    <?php foreach ($noticias['items'] as $n): ?>
                        <?= \App\Core\View::partial('partials/card-noticia', ['n' => $n]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
