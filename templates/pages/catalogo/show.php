<?php
/**
 * Ficha técnica completa de un vehículo.
 *
 * Recibe: $coche, $galeria, $videos, $historico, $relacionados,
 *         $noticias, $valoraciones
 *
 * La tabla de especificaciones se agrupa por bloques para que se pueda
 * escanear en segundos, como en cualquier comparador serio.
 *
 * La galería admite filtro por tipo de toma (exterior, interior, motor y
 * detalle) y, si el vehículo tiene material en vídeo, se muestra debajo
 * con reproductor en diferido: la página no pide nada a YouTube hasta
 * que el visitante lo reproduce.
 */

$c = $coche;
$galeria = $galeria ?? [];
$videos = $videos ?? [];
$historico = $historico ?? [];
$relacionados = $relacionados ?? [];
$noticias = $noticias ?? [];
$valoraciones = $valoraciones ?? [];

$principal = $galeria[0] ?? null;

/** Rótulos de los tipos de toma que reconoce la galería. */
$rotulosToma = [
    'exterior' => 'Exterior',
    'interior' => 'Interior',
    'motor'    => 'Motor',
    'detalle'  => 'Detalles',
];

/** @var array<string,int> $conteosToma número de fotos por tipo */
$conteosToma = [];
foreach ($galeria as $toma) {
    $tipo = (string) ($toma['categoria'] ?? 'exterior');
    $conteosToma[$tipo] = ($conteosToma[$tipo] ?? 0) + 1;
}

/** Pie de foto con la atribución exigida por las licencias libres. */
$pieDe = static function (array $img): string {
    $pie = trim((string) ($img['pie'] ?? ''));
    if ($pie !== '') {
        return $pie;
    }
    $autor = trim((string) ($img['autor'] ?? ''));
    if ($autor === '') {
        return '';
    }
    $licencia = trim((string) ($img['licencia'] ?? ''));
    return 'Fotografía: ' . $autor . ($licencia !== '' ? ' · ' . $licencia : '');
};

$fuertes = json_decode((string) ($c['puntos_fuertes'] ?? '[]'), true) ?: [];
$debiles = json_decode((string) ($c['puntos_debiles'] ?? '[]'), true) ?: [];

/** @param array<int,mixed> $grupos */
$filas = static function (array $grupos): void {
    foreach ($grupos as $titulo => $filas) {
        if ($filas === []) {
            continue;
        }
        echo '<section class="spec-block">';
        echo '<h3 class="spec-title">' . e((string) $titulo) . '</h3>';
        echo '<dl class="spec-grid">';
        foreach ($filas as $etiqueta => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }
            echo '<div class="spec-item">';
            echo '<dt>' . e((string) $etiqueta) . '</dt>';
            echo '<dd>' . (is_scalar($valor) ? e((string) $valor) : '—') . '</dd>';
            echo '</div>';
        }
        echo '</dl></section>';
    }
};

$esElectrico = in_array((string) $c['combustible'], ['electrico', 'hibrido_enchufable'], true);
$precio = $c['precio_nuevo'] ?? $c['precio_ocasion'] ?? null;
?>
<section class="car-head">
    <div class="shell">
        <nav class="breadcrumb" aria-label="Ruta de navegación">
            <a href="<?= url('') ?>">Inicio</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('coches') ?>">Catálogo</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('marcas/' . $c['marca_slug']) ?>"><?= e($c['marca']) ?></a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e($c['modelo']) ?></span>
        </nav>

        <div class="car-head-grid">
            <div class="car-head-copy">
                <p class="car-brand">
                    <?= letra_marca((string) $c['marca'], 'is-sm') ?>
                    <span><?= e($c['marca']) ?></span>
                </p>

                <h1 class="car-title"><?= e($c['modelo']) ?></h1>
                <?php if (!empty($c['version'])): ?>
                    <p class="car-version"><?= e($c['version']) ?></p>
                <?php endif; ?>

                <ul class="car-badges">
                    <li class="chip"><?= e(etiquetaCategoria((string) $c['categoria'])) ?></li>
                    <li class="chip"><?= e(etiquetaCombustible((string) $c['combustible'])) ?></li>
                    <?php if (!empty($c['anio_desde'])): ?>
                        <li class="chip">Desde <?= (int) $c['anio_desde'] ?></li>
                    <?php endif; ?>
                    <?php if (!empty($c['eficiencia'])): ?>
                        <li class="chip chip-energy" title="Etiqueta de eficiencia energética">
                            <?= e((string) $c['eficiencia']) ?>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php if (!empty($c['resumen'])): ?>
                    <p class="car-lede"><?= e($c['resumen']) ?></p>
                <?php endif; ?>

                <?php if (!empty($c['valoracion'])): ?>
                    <p class="car-rating">
                        <?= stars((float) $c['valoracion']) ?>
                        <strong><?= num((float) $c['valoracion'], 1) ?></strong>
                        <span>· <?= number_format((int) $c['vistas'], 0, ',', '.') ?> consultas</span>
                    </p>
                <?php endif; ?>
            </div>

            <aside class="car-price-card">
                <p class="car-price-label">Precio desde</p>
                <p class="car-price-value">
                    <?= $precio !== null ? e(euros((int) $precio)) : 'Consultar' ?>
                </p>
                <p class="car-price-note">
                    <?php if (!empty($c['precio_nuevo'])): ?>
                        PVP recomendado con impuestos
                        <?php if (!empty($c['precio_ocasion'])): ?>
                            · Ocasión desde <strong><?= e(euros((int) $c['precio_ocasion'])) ?></strong>
                        <?php endif; ?>
                    <?php elseif (!empty($c['precio_ocasion'])): ?>
                        Precio de ocasión
                    <?php else: ?>
                        Consulta con tu concesionario
                    <?php endif; ?>
                </p>

                <dl class="car-price-specs">
                    <?php if (!empty($c['potencia_cv'])): ?>
                        <div>
                            <dt>Potencia</dt>
                            <dd><?= (int) $c['potencia_cv'] ?> cv
                                <?php if (!empty($c['potencia_kw'])): ?>
                                    <span><?= (int) $c['potencia_kw'] ?> kW</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($c['aceleracion_0_100'])): ?>
                        <div>
                            <dt>0 a 100 km/h</dt>
                            <dd><?= num((float) $c['aceleracion_0_100'], 1) ?> s</dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($c['vel_maxima_kmh'])): ?>
                        <div>
                            <dt>Velocidad máx.</dt>
                            <dd><?= (int) $c['vel_maxima_kmh'] ?> km/h</dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <div class="car-price-actions">
                    <button class="btn btn-primary btn-block" type="button" data-compare-add="<?= (int) $c['id'] ?>">
                        Comparar este coche
                    </button>
                    <a class="btn btn-ghost btn-block" href="<?= url('marcas/' . $c['marca_slug']) ?>">
                        Ver toda la marca
                    </a>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php if ($galeria !== []): ?>
    <section class="car-gallery" aria-label="Galería fotográfica">
        <div class="shell">
            <header class="gallery-head">
                <p class="gallery-count">
                    <strong><?= count($galeria) ?></strong>
                    <span><?= count($galeria) === 1 ? 'fotografía' : 'fotografías' ?></span>
                </p>

                <?php if (count($conteosToma) > 1): ?>
                    <ul class="gallery-filters" data-gallery-filters>
                        <li>
                            <button type="button" class="gfilter is-active" data-gfilter="all">
                                Todas <span><?= count($galeria) ?></span>
                            </button>
                        </li>
                        <?php foreach ($rotulosToma as $tipo => $rotulo): ?>
                            <?php if (!empty($conteosToma[$tipo])): ?>
                                <li>
                                    <button type="button" class="gfilter" data-gfilter="<?= e($tipo) ?>">
                                        <?= e($rotulo) ?> <span><?= (int) $conteosToma[$tipo] ?></span>
                                    </button>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </header>

            <figure class="gallery-main" data-lightbox-root>
                <?php
                $altPrincipal = trim((string) ($principal['alt'] ?? ''));
                if ($altPrincipal === '') {
                    $altPrincipal = trim((string) $c['marca'] . ' ' . (string) $c['modelo']);
                }
                ?>
                <button class="gallery-open" type="button" data-lightbox-open<?php if ($altPrincipal !== ''): ?> aria-label="Ampliar imagen: <?= e($altPrincipal) ?>"<?php endif; ?>>
                    <?= imagen($principal['ruta'] ?? null,
                        $altPrincipal,
                        ['width' => 1600, 'height' => 1000, 'loading' => 'eager']) ?>
                </button>

                <?php $piePrincipal = $pieDe($principal ?? []); ?>
                <?php if ($piePrincipal !== ''): ?>
                    <figcaption class="figure-credit" data-gallery-credit><?= e($piePrincipal) ?></figcaption>
                <?php endif; ?>
            </figure>

            <?php if (count($galeria) > 1): ?>
                <ul class="gallery-thumbs" data-gallery-thumbs>
                    <?php foreach ($galeria as $i => $img): ?>
                        <?php $tipo = (string) ($img['categoria'] ?? 'exterior'); ?>
                        <li data-cat="<?= e($tipo) ?>">
                            <button type="button" class="gallery-thumb<?= $i === 0 ? ' is-active' : '' ?>"
                                    data-lightbox-src="<?= e(asset_url((string) $img['ruta'])) ?>"
                                    data-lightbox-alt="<?= e((string) ($img['alt'] ?? '')) ?>"
                                    data-credit="<?= e($pieDe($img)) ?>">
                                <?= imagen($img['ruta'] ?? null, '', ['width' => 220, 'height' => 154, 'loading' => 'lazy']) ?>
                                <span class="gallery-tag"><?= e($rotulosToma[$tipo] ?? 'Foto') ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($videos !== []): ?>
    <section class="section section-tight car-videos">
        <div class="shell">
            <header class="section-head">
                <div>
                    <p class="section-kicker">En vídeo</p>
                    <h2 class="section-title">
                        <?= e($c['marca']) ?> <?= e($c['modelo']) ?> en marcha
                    </h2>
                </div>
                <p class="section-lede">
                    Pruebas de dirección y presentaciones. El reproductor sólo se
                    carga al pulsar el vídeo.
                </p>
            </header>

            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <?php
                    $esYoutube = ($video['plataforma'] ?? 'youtube') === 'youtube';
                    $miniatura = (string) ($video['miniatura'] ?? '');
                    ?>
                    <article class="video-card">
                        <button type="button"
                                class="video-facade"
                                data-video="<?= e($esYoutube ? (string) $video['codigo'] : asset_url((string) $video['codigo'])) ?>"
                                data-video-youtube="<?= $esYoutube ? '1' : '0' ?>"
                                data-video-titulo="<?= e((string) $video['titulo']) ?>">
                            <?php if ($miniatura !== ''): ?>
                                <?= imagen($miniatura, 'Miniatura: ' . (string) $video['titulo'],
                                    ['width' => 480, 'height' => 270, 'loading' => 'lazy']) ?>
                            <?php endif; ?>
                            <span class="video-play" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="26" height="26" focusable="false">
                                    <path d="M8 5.5v13l11-6.5z" fill="currentColor" />
                                </svg>
                            </span>
                            <?php if (!empty($video['duracion'])): ?>
                                <span class="video-time"><?= e((string) $video['duracion']) ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="video-meta">
                            <h3 class="video-title"><?= e((string) $video['titulo']) ?></h3>
                            <p class="video-by">
                                <?php if (!empty($video['canal'])): ?>
                                    <?= e((string) $video['canal']) ?>
                                <?php else: ?>
                                    YouTube
                                <?php endif; ?>
                                <?php if (!empty($video['duracion'])): ?>
                                    <span aria-hidden="true">·</span>
                                    <time><?= e((string) $video['duracion']) ?></time>
                                <?php endif; ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section section-tight">
    <div class="shell car-layout">
        <div class="car-main">
            <?php if ($fuertes !== [] || $debiles !== []): ?>
                <section class="verdict">
                    <h2 class="block-title">Nuestro veredicto</h2>

                    <?php if ($fuertes !== []): ?>
                        <div class="verdict-col is-plus">
                            <h3>Puntos fuertes</h3>
                            <ul>
                                <?php foreach ($fuertes as $punto): ?>
                                    <li><?= e((string) $punto) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($debiles !== []): ?>
                        <div class="verdict-col is-minus">
                            <h3>Puntos débiles</h3>
                            <ul>
                                <?php foreach ($debiles as $punto): ?>
                                    <li><?= e((string) $punto) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <h2 class="block-title" id="ficha-tecnica">Ficha técnica</h2>

            <?php $filas([
                'Motor y transmisión' => [
                    'Combustible'      => etiquetaCombustible((string) $c['combustible']),
                    'Cilindrada'       => !empty($c['cilindrada_cc']) ? (int) $c['cilindrada_cc'] . ' cm³' : null,
                    'Cilindros'        => !empty($c['cilindros']) ? (int) $c['cilindros'] : null,
                    'Potencia'         => !empty($c['potencia_cv'])
                        ? (int) $c['potencia_cv'] . ' cv' . (!empty($c['potencia_kw']) ? ' · ' . (int) $c['potencia_kw'] . ' kW' : '')
                        : null,
                    'Par motor'        => !empty($c['par_nm']) ? (int) $c['par_nm'] . ' Nm' : null,
                    'Caja'             => !empty($c['transmision']) ? etiquetaTransmision((string) $c['transmision']) : null,
                    'Marchas'          => !empty($c['marchas']) ? (int) $c['marchas'] : null,
                    'Tracción'         => !empty($c['traccion']) ? etiquetaTraccion((string) $c['traccion']) : null,
                    'Batería'          => !empty($c['bateria_kwh']) ? num((float) $c['bateria_kwh'], 1) . ' kWh' : null,
                ],
                'Prestaciones' => [
                    '0 a 100 km/h'      => !empty($c['aceleracion_0_100']) ? num((float) $c['aceleracion_0_100'], 1) . ' s' : null,
                    'Velocidad máxima'  => !empty($c['vel_maxima_kmh']) ? (int) $c['vel_maxima_kmh'] . ' km/h' : null,
                    'Autonomía (WLTP)'  => !empty($c['autonomia_km']) ? (int) $c['autonomia_km'] . ' km' : null,
                    'Año de lanzamiento' => !empty($c['anio_desde']) ? (int) $c['anio_desde'] : null,
                    'Fin de producción' => !empty($c['anio_hasta']) ? (int) $c['anio_hasta'] : null,
                ],
                'Consumo y emisiones' => [
                    'Consumo combinado' => !empty($c['consumo_l100'])
                        ? num((float) $c['consumo_l100'], 1) . ' l/100 km'
                        : (!empty($c['consumo_kwh100']) ? num((float) $c['consumo_kwh100'], 1) . ' kWh/100 km' : null),
                    'Emisiones CO₂'     => !empty($c['emisiones_gkm']) ? (int) $c['emisiones_gkm'] . ' g/km' : null,
                    'Eficiencia'        => !empty($c['eficiencia']) ? 'Etiqueta ' . (string) $c['eficiencia'] : null,
                ],
                'Dimensiones y habitabilidad' => [
                    'Largo · Ancho · Alto' => (!empty($c['largo_mm']) && !empty($c['ancho_mm']) && !empty($c['alto_mm']))
                        ? (int) $c['largo_mm'] . ' · ' . (int) $c['ancho_mm'] . ' · ' . (int) $c['alto_mm'] . ' mm'
                        : null,
                    'Batalla'           => !empty($c['distancia_ejes_mm']) ? (int) $c['distancia_ejes_mm'] . ' mm' : null,
                    'Peso en orden de marcha' => !empty($c['peso_kg']) ? (int) $c['peso_kg'] . ' kg' : null,
                    'Maletero'          => !empty($c['maletero_litros']) ? (int) $c['maletero_litros'] . ' litros' : null,
                    'Plazas'            => !empty($c['plazas']) ? (int) $c['plazas'] : null,
                    'Puertas'           => !empty($c['puertas']) ? (int) $c['puertas'] : null,
                ],
            ]); ?>

            <?php if (!empty($c['descripcion'])): ?>
                <section class="prose car-description">
                    <h2 class="block-title">Sobre este modelo</h2>
                    <?= nl2br(e($c['descripcion'])) ?>
                </section>
            <?php endif; ?>

            <?php if ($historico !== []): ?>
                <section class="price-history">
                    <h2 class="block-title">Evolución del precio</h2>
                    <p class="block-lede">Evolución trimestral del PVP recomendado y de la media de ocasión.</p>

                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col">Periodo</th>
                                <th scope="col" class="num">Precio nuevo</th>
                                <th scope="col" class="num">Ocasión</th>
                                <th scope="col">Demanda</th>
                                <th scope="col" class="num">Unidades</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historico as $fila): ?>
                                <tr>
                                    <th scope="row"><?= e((string) $fila['periodo']) ?></th>
                                    <td class="num">
                                        <?= $fila['precio_nuevo'] !== null ? e(euros((int) $fila['precio_nuevo'])) : '—' ?>
                                    </td>
                                    <td class="num">
                                        <?= $fila['precio_ocasion'] !== null ? e(euros((int) $fila['precio_ocasion'])) : '—' ?>
                                    </td>
                                    <td><?= e((string) ($fila['demanda'] ?? '—')) ?></td>
                                    <td class="num">
                                        <?= $fila['unidades'] !== null ? number_format((int) $fila['unidades'], 0, ',', '.') : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($valoraciones !== []): ?>
                <section class="reviews">
                    <h2 class="block-title">Opiniones de conductores</h2>
                    <ol class="review-list">
                        <?php foreach ($valoraciones as $v): ?>
                            <li class="review">
                                <div class="review-head">
                                    <?= letra_marca((string) ($v['nombre'] ?? 'Anónimo'), 'is-sm') ?>
                                    <div>
                                        <strong><?= e((string) ($v['nombre'] ?? 'Usuario de MotorPress')) ?></strong>
                                        <?= stars((float) $v['puntuacion']) ?>
                                        <time datetime="<?= e((string) $v['creado_en']) ?>">
                                            <?= e(time_ago($v['creado_en'])) ?>
                                        </time>
                                    </div>
                                </div>
                                <?php if (!empty($v['comentario'])): ?>
                                    <p class="review-text"><?= nl2br(e((string) $v['comentario'])) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>
        </div>

        <aside class="car-side">
            <?php if ($noticias !== []): ?>
                <section class="side-widget">
                    <h2 class="side-title">Noticias de este modelo</h2>
                    <ul class="side-list">
                        <?php foreach ($noticias as $n): ?>
                            <li>
                                <a href="<?= url('noticias/' . $n['slug']) ?>">
                                    <span><?= e(excerpt((string) $n['titulo'], 78)) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($esElectrico): ?>
                <section class="side-widget side-widget-accent">
                    <h2 class="side-title">¿Cuánto consumo?</h2>
                    <p>
                        Con
                        <strong><?= !empty($c['consumo_kwh100']) ? num((float) $c['consumo_kwh100'], 1) . ' kWh/100 km' : 'el consumo homologado' ?></strong>
                        y una autonomía de <strong><?= (int) $c['autonomia_km'] ?> km</strong>, un viaje de
                        300 km te costará alrededor de
                        <strong><?= e(euros((int) round(((int) $c['autonomia_km'] / 100) * (float) ($c['consumo_kwh100'] ?? 17) * 0.22))) ?></strong>
                        en electricidad doméstica.
                    </p>
                </section>
            <?php endif; ?>

            <section class="side-widget">
                <h2 class="side-title">Enlace de la ficha</h2>
                <div class="share is-stacked">
                    <button class="btn btn-ghost btn-sm btn-block" type="button"
                            data-copy="<?= e(url('coches/' . $c['slug'])) ?>">Copiar enlace</button>
                </div>
            </section>
        </aside>
    </div>
</section>

<?php if ($relacionados !== []): ?>
    <section class="section section-alt" aria-labelledby="tit-relacionados">
        <div class="shell">
            <header class="section-head">
                <div>
                    <p class="section-kicker">Alternativas</p>
                    <h2 class="section-title" id="tit-relacionados">
                        Otros <?= e(etiquetaCategoria((string) $c['categoria'])) ?> que encajan contigo
                    </h2>
                </div>
                <a class="section-more" href="<?= url('coches') ?>?categorias[]=<?= e((string) $c['categoria']) ?>">
                    Ver la categoría →
                </a>
            </header>

            <div class="grid grid-cars">
                <?php foreach ($relacionados as $r): ?>
                    <?= \App\Core\View::partial('partials/card-coche', ['c' => $r]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<script type="application/ld+json" nonce="<?= e(\App\Core\Security::nonce()) ?>">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Car',
    'name' => trim(($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? '') . ' ' . ($c['version'] ?? '')),
    'brand' => ['@type' => 'Brand', 'name' => $c['marca'] ?? ''],
    'category' => $c['categoria'] ?? '',
    'url' => url('coches/' . $c['slug']),
    'image' => !empty($principal['ruta']) ? asset_url((string) $principal['ruta']) : '',
    'description' => $c['resumen'] ?? '',
    'vehicleEngine' => !empty($c['potencia_cv'])
        ? ['@type' => 'EngineSpecification', 'enginePower' => ['@type' => 'QuantitativeValue', 'value' => (int) $c['potencia_cv'], 'unitCode' => 'N12']]
        : null,
    'itemCondition' => 'https://schema.org/NewCondition',
    'offers' => $precio !== null
        ? [
            '@type' => 'Offer',
            'price' => (int) $precio,
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
        ]
        : null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>
</script>
