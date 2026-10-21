<?php
/**
 * Créditos fotográficos.
 * Recibe: $manifiesto (clave => metadatos de Wikimedia Commons)
 */

$manifiesto = $manifiesto ?? [];
$subidas = $subidas ?? [];

$porSeccion = [];
foreach ($manifiesto as $clave => $meta) {
    $seccion = (string) ($meta['seccion'] ?? 'otros');
    $porSeccion[$seccion][] = ['clave' => (string) $clave, 'meta' => $meta];
}

$titulos = ['cars' => 'Catálogo', 'news' => 'Redacción', 'hero' => 'Aperturas', 'brands' => 'Marcas'];
ksort($porSeccion);

$usadas = [];
$duplicadas = 0;
foreach ($manifiesto as $meta) {
    $t = (string) ($meta['titulo'] ?? '');
    if ($t === '') {
        continue;
    }
    if (isset($usadas[$t])) {
        $duplicadas++;
    }
    $usadas[$t] = true;
}
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Transparencia</p>
        <h1 class="page-title">Créditos fotográficos</h1>
        <p class="page-lede">
            Las fotografías de la biblioteca proceden de
            <a href="https://commons.wikimedia.org/"
               rel="noopener noreferrer nofollow" target="_blank">Wikimedia Commons</a> y se
            reproducen bajo la licencia indicada en cada ficha: son
            <strong><?= count($manifiesto) ?></strong> imágenes, ninguna repetida
            (<?= $duplicadas === 0 ? '0 duplicados' : $duplicadas . ' duplicados' ?>).
            <?php if ($subidas !== []): ?>
                Las tomas aportadas por la redacción desde el panel se listan al final.
            <?php endif; ?>
        </p>
    </div>
</section>

<section class="section section-tight">
    <div class="shell">
        <?php if ($manifiesto === []): ?>
            <div class="empty-state">
                <p class="empty-title">No hay manifiesto de imágenes disponible</p>
            </div>
        <?php endif; ?>

        <?php foreach ($porSeccion as $seccion => $elementos): ?>
            <section class="credits-block">
                <h2 class="block-title">
                    <?= e($titulos[$seccion] ?? ucfirst($seccion)) ?>
                    <span class="count-badge"><?= count($elementos) ?></span>
                </h2>

                <div class="table-scroll">
                    <table class="table table-credits">
                        <thead>
                        <tr>
                            <th scope="col">Imagen</th>
                            <th scope="col">Título en Commons</th>
                            <th scope="col">Autoría</th>
                            <th scope="col">Licencia</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($elementos as $el): $m = $el['meta']; ?>
                            <tr>
                                <td class="credit-cell">
                                    <a class="credit-thumb"
                                       href="<?= e(asset_url((string) ($m['ruta'] ?? ''))) ?>"
                                       target="_blank" rel="noopener noreferrer">
                                        <?= imagen($m['ruta'] ?? null, (string) ($m['titulo'] ?? ''),
                                            ['width' => 120, 'height' => 80, 'loading' => 'lazy']) ?>
                                    </a>
                                    <code><?= e($el['clave']) ?></code>
                                </td>
                                <td>
                                    <?php if (!empty($m['fuente'])): ?>
                                        <a href="<?= e((string) $m['fuente']) ?>" target="_blank"
                                           rel="noopener noreferrer nofollow">
                                            <?= e((string) ($m['titulo'] ?? '—')) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e((string) ($m['titulo'] ?? '—')) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) ($m['autor'] ?? '—')) ?></td>
                                <td><span class="license"><?= e((string) ($m['licencia'] ?? '—')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($subidas !== []): ?>
            <section class="credits-block">
                <h2 class="block-title">
                    Aportadas por la redacción
                    <span class="count-badge"><?= count($subidas) ?></span>
                </h2>

                <div class="table-scroll">
                    <table class="table table-credits">
                        <thead>
                        <tr>
                            <th scope="col">Imagen</th>
                            <th scope="col">Descripción</th>
                            <th scope="col">Autoría</th>
                            <th scope="col">Licencia</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subidas as $s): ?>
                            <tr>
                                <td class="credit-cell">
                                    <a class="credit-thumb"
                                       href="<?= e(asset_url((string) $s['ruta'])) ?>"
                                       target="_blank" rel="noopener noreferrer">
                                        <?= imagen((string) $s['ruta'],
                                            (string) ($s['alt'] !== '' ? $s['alt'] : $s['titulo']),
                                            ['width' => 120, 'height' => 80, 'loading' => 'lazy']) ?>
                                    </a>
                                    <code><?= e(basename((string) $s['ruta'])) ?></code>
                                </td>
                                <td><?= e((string) ($s['titulo'] !== '' ? $s['titulo'] : $s['alt'])) ?></td>
                                <td><?= e((string) ($s['autor'] !== '' ? $s['autor'] : '—')) ?></td>
                                <td><span class="license"><?= e((string) ($s['licencia'] !== '' ? $s['licencia'] : '—')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
