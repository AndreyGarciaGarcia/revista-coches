<?php
/**
 * Directorio completo de fabricantes.
 * Recibe: $marcas, $total
 */

$marcas = $marcas ?? [];
$letras = [];

foreach ($marcas as $m) {
    $inicial = mb_strtoupper(mb_substr((string) $m['nombre'], 0, 1));
    $letras[$inicial] ??= [];
    $letras[$inicial][] = $m;
}

ksort($letras, SORT_NATURAL | SORT_FLAG_CASE);
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Directorio</p>
        <h1 class="page-title">Marcas de coches</h1>
        <p class="page-lede">
            <?= count($marcas) ?> fabricantes con <?= number_format((int) ($total ?? 0), 0, ',', '.') ?>
            modelos documentados: origen, número de versiones y precio de entrada.
        </p>

        <?php if (count($letras) > 1): ?>
            <nav class="alpha-nav" aria-label="Filtrar por inicial">
                <?php foreach (array_keys($letras) as $letra): ?>
                    <a href="#letra-<?= e(mb_strtolower($letra)) ?>"><?= e($letra) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

<section class="section section-tight">
    <div class="shell">
        <?php if ($marcas === []): ?>
            <div class="empty-state">
                <p class="empty-title">Todavía no hay marcas publicadas</p>
                <a class="btn btn-primary" href="<?= url('coches') ?>">Ver el catálogo</a>
            </div>
        <?php endif; ?>

        <?php foreach ($letras as $letra => $grupo): ?>
            <div class="alpha-block" id="letra-<?= e(mb_strtolower($letra)) ?>">
                <h2 class="alpha-title"><?= e($letra) ?></h2>

                <ul class="brand-grid">
                    <?php foreach ($grupo as $m): ?>
                        <li>
                            <a class="brand-tile" href="<?= url('marcas/' . $m['slug']) ?>">
                                <?= letra_marca((string) $m['nombre']) ?>
                                <span class="brand-tile-name"><?= e($m['nombre']) ?></span>
                                <span class="brand-tile-count">
                                    <?php if (!empty($m['pais'])): ?>
                                        <?= e($m['pais']) ?> ·
                                    <?php endif; ?>
                                    <?= (int) $m['num_modelos'] ?> <?= (int) $m['num_modelos'] === 1 ? 'modelo' : 'modelos' ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</section>
