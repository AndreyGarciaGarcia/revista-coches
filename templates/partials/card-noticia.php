<?php
/**
 * Tarjeta de artículo.
 * Recibe: $n (fila de v_noticias_publicadas) y opcional $talla
 * ('grande' para la destacada de portada).
 */

$talla = $talla ?? 'normal';
$alt = (string) ($n['imagen_alt'] ?? $n['titulo'] ?? '');
$destacada = !empty($n['destacada']);
?>
<article class="card card-news<?= $talla === 'grande' ? ' card-news-lg' : '' ?><?= $destacada ? ' is-featured' : '' ?>">
    <a class="card-media" href="<?= url('noticias/' . $n['slug']) ?>" tabindex="-1" aria-hidden="true">
        <?= imagen($n['imagen'] ?? null, $alt, ['width' => 800, 'height' => 500, 'loading' => 'lazy']) ?>
        <?= categoria_chip($n['categoria'] ?? null, $n['categoria_color'] ?? null, $n['categoria_slug'] ?? null) ?>
    </a>

    <div class="card-body">
        <div class="card-meta">
            <?php if (!empty($n['publicado_at'])): ?>
                <time datetime="<?= e(substr((string) $n['publicado_at'], 0, 10)) ?>">
                    <?= e(readable_time($n['publicado_at'])) ?>
                </time>
            <?php endif; ?>
            <?php if (!empty($n['tiempo_lectura'])): ?>
                <span class="dot" aria-hidden="true">·</span>
                <span><?= (int) $n['tiempo_lectura'] ?> min de lectura</span>
            <?php endif; ?>
        </div>

        <h2 class="card-title">
            <a href="<?= url('noticias/' . $n['slug']) ?>"><?= e($n['titulo']) ?></a>
        </h2>

        <?php if (!empty($n['resumen'])): ?>
            <p class="card-excerpt"><?= e(excerpt((string) $n['resumen'], $talla === 'grande' ? 240 : 160)) ?></p>
        <?php endif; ?>

        <div class="card-foot">
            <?php if (!empty($n['autor'])): ?>
                <span class="byline">
                    <?= letra_marca((string) $n['autor'], 'is-xs') ?>
                    <?= e($n['autor']) ?>
                </span>
            <?php endif; ?>

            <span class="card-stats">
                <?php if (isset($n['num_comentarios'])): ?>
                    <span title="Comentarios"><?= (int) $n['num_comentarios'] ?> ✎</span>
                <?php endif; ?>
                <?php if (isset($n['vistas'])): ?>
                    <span title="Vistas"><?= number_format((int) $n['vistas'], 0, ',', '.') ?> ▸</span>
                <?php endif; ?>
            </span>
        </div>
    </div>
</article>
