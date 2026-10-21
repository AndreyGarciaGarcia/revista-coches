<?php
/**
 * Tarjeta de vehículo del catálogo.
 * Recibe: $c (fila de v_coches_catalogo)
 */

$precio = $c['precio_nuevo'] ?? $c['precio_ocasion'] ?? null;
$enlace = url('coches/' . $c['slug']);
?>
<article class="card card-car">
    <a class="card-media" href="<?= $enlace ?>" tabindex="-1" aria-hidden="true">
        <?= imagen($c['imagen_principal'] ?? null, ($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? ''),
            ['width' => 640, 'height' => 400, 'loading' => 'lazy']) ?>
        <?php if (!empty($c['destacado'])): ?>
            <span class="badge badge-hot">Destacado</span>
        <?php endif; ?>
        <?php if (!empty($c['num_imagenes'])): ?>
            <span class="badge-count"><?= (int) $c['num_imagenes'] ?> fotos</span>
        <?php endif; ?>
    </a>

    <div class="card-body">
        <div class="card-brand">
            <?= letra_marca((string) ($c['marca'] ?? '?'), 'is-sm') ?>
            <span class="card-brand-name"><?= e($c['marca'] ?? '') ?></span>
        </div>

        <h2 class="card-title">
            <a href="<?= $enlace ?>"><?= e($c['modelo']) ?></a>
        </h2>

        <?php if (!empty($c['version'])): ?>
            <p class="card-version"><?= e($c['version']) ?></p>
        <?php endif; ?>

        <ul class="spec-row">
            <?php if (!empty($c['potencia_cv'])): ?>
                <li><strong><?= (int) $c['potencia_cv'] ?></strong> cv</li>
            <?php endif; ?>
            <?php if (!empty($c['combustible'])): ?>
                <li><?= e(etiquetaCombustible((string) $c['combustible'])) ?></li>
            <?php endif; ?>
            <?php if (!empty($c['transmision'])): ?>
                <li><?= e(etiquetaTransmision((string) $c['transmision'])) ?></li>
            <?php endif; ?>
            <?php if (!empty($c['autonomia_km'])): ?>
                <li><strong><?= (int) $c['autonomia_km'] ?></strong> km</li>
            <?php endif; ?>
        </ul>

        <div class="card-foot card-foot-split">
            <div class="price">
                <?php if ($precio !== null): ?>
                    <span class="price-value"><?= e(euros((int) $precio)) ?></span>
                    <span class="price-label"><?= !empty($c['precio_nuevo']) ? 'precio nuevo' : 'de ocasión' ?></span>
                <?php else: ?>
                    <span class="price-value is-muted">Consultar</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($c['valoracion'])): ?>
                <div class="rating" title="<?= num((float) $c['valoracion'], 1) ?> / 5">
                    <?= stars((float) $c['valoracion']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>
