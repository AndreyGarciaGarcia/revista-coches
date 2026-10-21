<?php
/**
 * Portada.
 *
 * Composición de prensa de motor:
 *   · Apertura a dos columnas: titular a la izquierda, fotografía a la derecha.
 *   · Tira de tres titulares secundarios cerrando la apertura.
 *   · Rejilla de actualidad, banda de cifras, vitrina del catálogo,
 *     directorio de marcas, banda del comparador y cierre con lo más leído.
 */

$principal = $principal ?? null;
$secundarias = $secundarias ?? [];
$destacados = $destacados ?? [];
$novedades = $novedades ?? [];
$marcas = $marcas ?? [];
$masLeidas = $masLeidas ?? [];
$categorias = $categorias ?? [];
$metricas = $metricas ?? [];

/* Con cuatro o más secundarias, tres cierran la apertura y el resto
   forma la rejilla de actualidad. Con menos, todo va a la rejilla. */
$tira = count($secundarias) >= 4 ? array_slice($secundarias, 0, 3) : [];
$actualidad = $tira !== [] ? array_slice($secundarias, 3) : $secundarias;
?>

<?php if ($principal !== null): ?>
    <section class="hero<?= $tira !== [] ? ' hero-con-tira' : '' ?>">
        <div class="shell hero-grid">
            <div class="hero-copy">
                <p class="hero-kicker">
                    <?= categoria_chip($principal['categoria'] ?? null, $principal['categoria_color'] ?? null,
                        $principal['categoria_slug'] ?? null) ?>
                    <?php if (!empty($principal['fijada'])): ?>
                        <span class="chip chip-solid">Portada</span>
                    <?php endif; ?>
                </p>

                <h1 class="hero-title">
                    <a href="<?= url('noticias/' . $principal['slug']) ?>"><?= e($principal['titulo']) ?></a>
                </h1>

                <?php if (!empty($principal['resumen'])): ?>
                    <p class="hero-lede"><?= e($principal['resumen']) ?></p>
                <?php endif; ?>

                <a class="leer-enlace" href="<?= url('noticias/' . $principal['slug']) ?>">
                    Leer el análisis <span aria-hidden="true">→</span>
                </a>

                <p class="hero-meta">
                    <?php if (!empty($principal['autor'])): ?>
                        <span>Por <strong><?= e($principal['autor']) ?></strong></span>
                        <span class="dot" aria-hidden="true">·</span>
                    <?php endif; ?>
                    <time datetime="<?= e(substr((string) ($principal['publicado_at'] ?? ''), 0, 10)) ?>">
                        <?= e(readable_time($principal['publicado_at'] ?? null)) ?>
                    </time>
                    <?php if (!empty($principal['tiempo_lectura'])): ?>
                        <span class="dot" aria-hidden="true">·</span>
                        <span><?= (int) $principal['tiempo_lectura'] ?> min</span>
                    <?php endif; ?>
                </p>
            </div>

            <figure class="hero-media">
                <?= imagen($principal['imagen'] ?? null,
                    (string) ($principal['imagen_alt'] ?? $principal['titulo']),
                    ['width' => 1600, 'height' => 1200, 'loading' => 'eager', 'fetchpriority' => 'high']) ?>
                <figcaption>Foto · Wikimedia Commons</figcaption>
            </figure>
        </div>

        <?php if ($tira !== []): ?>
            <div class="shell">
                <ol class="hero-secundarias">
                    <?php foreach ($tira as $i => $n): ?>
                        <li>
                            <span class="hs-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <a class="hs-title" href="<?= url('noticias/' . $n['slug']) ?>">
                                <?= e($n['titulo']) ?>
                            </a>
                            <span class="hs-meta">
                                <?= e($n['categoria'] ?? '') ?>
                                <?php if (!empty($n['publicado_at'])): ?>
                                    <span class="dot" aria-hidden="true">·</span>
                                    <?= e(time_ago($n['publicado_at'])) ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($actualidad !== []): ?>
    <section class="section" aria-labelledby="tit-actualidad">
        <div class="shell">
            <header class="section-head">
                <div>
                    <p class="section-kicker">Actualidad</p>
                    <h2 class="section-title" id="tit-actualidad">Lo último en la redacción</h2>
                </div>
                <a class="section-more" href="<?= url('noticias') ?>">Todas las noticias →</a>
            </header>

            <div class="grid grid-news">
                <?php foreach ($actualidad as $n): ?>
                    <?= \App\Core\View::partial('partials/card-noticia', ['n' => $n]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($metricas !== []): ?>
    <section class="section section-tight" aria-label="Cifras de MotorPress">
        <div class="shell">
            <ul class="stats">
                <?php foreach ([
                    'modelos'      => ['Modelos con ficha', ''],
                    'marcas'       => ['Marcas cubiertas', ''],
                    'articulos'    => ['Artículos publicados', ''],
                    'suscriptores' => ['Suscriptores', ''],
                ] as $clave => [$rotulo, $_]): ?>
                    <?php if (isset($metricas[$clave])): ?>
                        <li class="stat">
                            <span class="stat-value"><?= number_format((int) $metricas[$clave], 0, ',', '.') ?></span>
                            <span class="stat-label"><?= e($rotulo) ?></span>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if ($destacados !== []): ?>
    <section class="section section-alt" aria-labelledby="tit-destacados">
        <div class="shell">
            <header class="section-head">
                <div>
                    <p class="section-kicker">Catálogo</p>
                    <h2 class="section-title" id="tit-destacados">Coches que merecen un viaje</h2>
                    <p class="section-lede">
                        Fichas completas con precios reales, consumos homologados y valoraciones
                        de nuestro equipo.
                    </p>
                </div>
                <a class="section-more" href="<?= url('coches') ?>">Explorar catálogo →</a>
            </header>

            <div class="grid grid-cars">
                <?php foreach ($destacados as $c): ?>
                    <?= \App\Core\View::partial('partials/card-coche', ['c' => $c]) ?>
                <?php endforeach; ?>
            </div>

            <?php if ($novedades !== []): ?>
                <div class="novedades">
                    <h3 class="novedades-title">Novedades recientes</h3>
                    <ul class="novedades-list">
                        <?php foreach ($novedades as $c): ?>
                            <li>
                                <a href="<?= url('coches/' . $c['slug']) ?>">
                                    <?= letra_marca((string) $c['marca'], 'is-xs') ?>
                                    <span class="novedades-name">
                                        <strong><?= e($c['marca']) ?> <?= e($c['modelo']) ?></strong>
                                        <em><?= (int) $c['anio_desde'] ?></em>
                                    </span>
                                    <span class="novedades-price">
                                        <?= $c['precio_nuevo'] !== null ? e(euros((int) $c['precio_nuevo'])) : '—' ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($marcas !== []): ?>
    <section class="section" aria-labelledby="tit-marcas">
        <div class="shell">
            <header class="section-head">
                <div>
                    <p class="section-kicker">Directorio</p>
                    <h2 class="section-title" id="tit-marcas">Todas las marcas</h2>
                </div>
                <a class="section-more" href="<?= url('marcas') ?>">Ver todas →</a>
            </header>

            <ul class="brand-grid">
                <?php foreach (array_slice($marcas, 0, 18) as $m): ?>
                    <li>
                        <a class="brand-tile" href="<?= url('marcas/' . $m['slug']) ?>">
                            <?= letra_marca($m['nombre']) ?>
                            <span class="brand-tile-name"><?= e($m['nombre']) ?></span>
                            <span class="brand-tile-count">
                                <?= (int) $m['num_modelos'] ?> <?= (int) $m['num_modelos'] === 1 ? 'modelo' : 'modelos' ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<section class="band band-compare" aria-labelledby="tit-compara">
    <div class="shell band-inner">
        <div>
            <p class="section-kicker is-light">Herramienta</p>
            <h2 class="band-title" id="tit-compara">Compara hasta cuatro coches, ficha a ficha</h2>
            <p class="band-lede">
                Motor, consumos, dimensiones, precio y valoraciones en una sola tabla.
                Sin registros, sin molestias.
            </p>
        </div>
        <div class="band-actions">
            <a class="btn btn-primary btn-lg" href="<?= url('comparador') ?>">Abrir comparador</a>
            <a class="btn btn-ghost btn-lg is-light" href="<?= url('buscar') ?>">Buscar un modelo</a>
        </div>
    </div>
</section>

<?php if ($masLeidas !== [] || $categorias !== []): ?>
    <section class="section" aria-labelledby="tit-leido">
        <div class="shell split">
            <div class="split-main">
                <header class="section-head is-plain">
                    <div>
                        <p class="section-kicker">Tendencias</p>
                        <h2 class="section-title" id="tit-leido">Lo más leído esta semana</h2>
                    </div>
                </header>

                <ol class="ranked">
                    <?php foreach ($masLeidas as $i => $n): ?>
                        <li>
                            <span class="ranked-num"><?= $i + 1 ?></span>
                            <div>
                                <a class="ranked-title" href="<?= url('noticias/' . $n['slug']) ?>">
                                    <?= e($n['titulo']) ?>
                                </a>
                                <p class="ranked-meta">
                                    <?= e($n['categoria'] ?? '') ?>
                                    <?php if (!empty($n['publicado_at'])): ?>
                                        <span class="dot" aria-hidden="true">·</span>
                                        <?= e(time_ago($n['publicado_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="ranked-views"><?= number_format((int) $n['vistas'], 0, ',', '.') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <aside class="split-side">
                <h2 class="side-title">Secciones</h2>
                <ul class="side-list">
                    <?php foreach ($categorias as $cat): ?>
                        <li>
                            <a href="<?= url('categoria/' . $cat['slug']) ?>">
                                <span><?= e($cat['nombre']) ?></span>
                                <span class="side-count"><?= (int) $cat['num_noticias'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="side-cta">
                    <h2 class="side-title">Recíbelo todo</h2>
                    <p>Un correo a la semana con lo mejor de la redacción y del catálogo.</p>
                    <a class="btn btn-primary btn-block" href="<?= url('contacto') ?>">Hablar con la redacción</a>
                </div>
            </aside>
        </div>
    </section>
<?php endif; ?>
