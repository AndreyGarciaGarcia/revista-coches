<?php
/**
 * Catálogo con filtros combinables (columna izquierda) y resultados.
 *
 * Recibe: $resultado, $filtros, $facetas, $marcas, $rango,
 *         $categorias, $combustibles, $total
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$facetas = $facetas ?? ['categorias' => [], 'combustibles' => [], 'marcas' => []];
$marcas = $marcas ?? [];
$f = $filtros ?? [];
$rango = $rango ?? ['min' => 0, 'max' => 100000];
$categorias = $categorias ?? [];
$combustibles = $combustibles ?? [];

/** Marca los filtros activos en el formulario de resultados. */
$seleccion = static function (string $clave, string $valor) use ($f): bool {
    return in_array($valor, array_filter(explode(',', (string) ($f[$clave] ?? ''))), true);
};

$activos = 0;
foreach (['marcas', 'categorias', 'combustibles'] as $clave) {
    $activos += count(array_filter(explode(',', (string) ($f[$clave] ?? ''))));
}
foreach (['precio_min', 'precio_max', 'potencia_min'] as $clave) {
    if (!empty($f[$clave])) {
        $activos++;
    }
}
if ((string) ($f['texto'] ?? '') !== '') {
    $activos++;
}
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Catálogo</p>
        <h1 class="page-title">Encuentra tu próximo coche</h1>
        <p class="page-lede">
            <?= number_format((int) ($total ?? 0), 0, ',', '.') ?> vehículos con ficha técnica,
            precio de nuevo y de ocasión.
        </p>

        <form class="search-hero" action="<?= url('coches') ?>" method="get" role="search">
            <label class="sr-only" for="q-catalogo">Buscar por marca, modelo o versión</label>
            <input id="q-catalogo" type="search" name="q" maxlength="120"
                   value="<?= e($f['texto'] ?? '') ?>"
                   placeholder="Prueba con «SUV familiar», «A4» o «eléctrico 500 km»…">
            <button class="btn btn-primary" type="submit">Buscar</button>
        </form>

        <?php if ($activos > 0): ?>
            <p class="result-count">
                <?= number_format((int) $resultado['total'], 0, ',', '.') ?>
                <?= (int) $resultado['total'] === 1 ? 'resultado' : 'resultados' ?>
                con <?= (int) $activos ?> <?= (int) $activos === 1 ? 'filtro' : 'filtros' ?> activos
                · <a href="<?= url('coches') ?>">limpiar todo</a>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-tight">
    <div class="shell catalog">
        <aside class="filters" id="panel-filtros" data-filters>
            <form action="<?= url('coches') ?>" method="get" id="form-filtros">
                <?php if ((string) ($f['texto'] ?? '') !== ''): ?>
                    <input type="hidden" name="q" value="<?= e($f['texto']) ?>">
                <?php endif; ?>
                <?php if (!empty($f['orden'])): ?>
                    <input type="hidden" name="orden" value="<?= e($f['orden']) ?>">
                <?php endif; ?>

                <div class="filters-head">
                    <h2>Filtros</h2>
                    <?php if ($activos > 0): ?>
                        <a class="filters-clear" href="<?= url('coches') ?>">Limpiar</a>
                    <?php endif; ?>
                </div>

                <fieldset class="filter-group">
                    <legend>Precio (€)</legend>
                    <div class="range-pair">
                        <input type="number" name="precio_min" inputmode="numeric" min="0" max="500000" step="500"
                               value="<?= e((string) ($f['precio_min'] ?? '')) ?>"
                               placeholder="<?= (int) $rango['min'] ?>" aria-label="Precio mínimo">
                        <span aria-hidden="true">–</span>
                        <input type="number" name="precio_max" inputmode="numeric" min="0" max="500000" step="500"
                               value="<?= e((string) ($f['precio_max'] ?? '')) ?>"
                               placeholder="<?= (int) $rango['max'] ?>" aria-label="Precio máximo">
                    </div>
                    <p class="field-help">
                        Entre <?= e(euros((int) $rango['min'])) ?> y <?= e(euros((int) $rango['max'])) ?>
                    </p>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Potencia mínima (cv)</legend>
                    <input type="number" name="potencia_min" inputmode="numeric" min="0" max="2000" step="10"
                           value="<?= e((string) ($f['potencia_min'] ?? '')) ?>" placeholder="Sin límite"
                           aria-label="Potencia mínima en caballos">
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Categoría</legend>
                    <ul class="check-list">
                        <?php foreach ($categorias as $cat): ?>
                            <li>
                                <label class="check">
                                    <input type="checkbox" name="categorias[]" value="<?= e($cat) ?>"
                                        data-filter-key="categorias"
                                        <?= $seleccion('categorias', $cat) ? 'checked' : '' ?>>
                                    <span><?= e(etiquetaCategoria($cat)) ?></span>
                                    <em><?= (int) ($facetas['categorias'][$cat] ?? 0) ?></em>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Combustible</legend>
                    <ul class="check-list">
                        <?php foreach ($combustibles as $tipo): ?>
                            <li>
                                <label class="check">
                                    <input type="checkbox" name="combustibles[]" value="<?= e($tipo) ?>"
                                        data-filter-key="combustibles"
                                        <?= $seleccion('combustibles', $tipo) ? 'checked' : '' ?>>
                                    <span><?= e(etiquetaCombustible($tipo)) ?></span>
                                    <em><?= (int) ($facetas['combustibles'][$tipo] ?? 0) ?></em>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Marca</legend>
                    <input class="filter-search" type="search" data-brand-filter
                           placeholder="Filtrar marcas…" aria-label="Filtrar la lista de marcas">
                    <ul class="check-list check-list-scroll">
                        <?php foreach ($marcas as $m): ?>
                            <li data-brand-name="<?= e(mb_strtolower($m['nombre'])) ?>">
                                <label class="check">
                                    <input type="checkbox" name="marcas[]" value="<?= (int) $m['id'] ?>"
                                        data-filter-key="marcas"
                                        <?= $seleccion('marcas', (string) $m['id']) ? 'checked' : '' ?>>
                                    <span><?= e($m['nombre']) ?></span>
                                    <em><?= (int) $m['num_modelos'] ?></em>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>

                <div class="filters-actions">
                    <button class="btn btn-primary btn-block" type="submit">Aplicar filtros</button>
                </div>
            </form>
        </aside>

        <div class="results">
            <div class="results-bar">
                <p class="result-count">
                    <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
                    <?= (int) $resultado['total'] === 1 ? 'coche' : 'coches' ?>
                </p>

                <div class="results-bar-side">
                    <button class="btn btn-ghost btn-sm filters-toggle" type="button"
                            data-filters-toggle aria-expanded="false" aria-controls="panel-filtros">
                        Filtros
                        <?php if ($activos > 0): ?>
                            <span class="count-badge"><?= (int) $activos ?></span>
                        <?php endif; ?>
                    </button>

                <form class="sort-form" action="<?= url('coches') ?>" method="get">
                    <?php foreach (['q', 'categorias', 'combustibles', 'marcas', 'precio_min', 'precio_max', 'potencia_min'] as $oculto): ?>
                        <?php $valor = $f[$oculto] ?? ''; if ($valor !== '' && $valor !== null): ?>
                            <input type="hidden" name="<?= e($oculto) ?>" value="<?= e(is_array($valor) ? implode(',', $valor) : (string) $valor) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <label for="orden">Ordenar por</label>
                    <select id="orden" name="orden" data-auto-submit>
                        <?php foreach ([
                            'relevancia' => 'Relevancia',
                            'precio_asc'  => 'Precio: de menor a mayor',
                            'precio_desc' => 'Precio: de mayor a menor',
                            'potencia'    => 'Mayor potencia',
                            'novedades'   => 'Novedades',
                            'vistas'      => 'Más consultados',
                        ] as $clave => $rotulo): ?>
                            <option value="<?= e($clave) ?>"
                                <?= ($f['orden'] ?? 'relevancia') === $clave ? 'selected' : '' ?>>
                                <?= e($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                </div>
            </div>

            <?php if ($resultado['items'] === []): ?>
                <div class="empty-state">
                    <p class="empty-title">Ningún coche coincide con esos filtros</p>
                    <p>Prueba a ampliar el rango de precio o a quitar alguna marca.</p>
                    <a class="btn btn-primary" href="<?= url('coches') ?>">Ver todo el catálogo</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cars">
                    <?php foreach ($resultado['items'] as $c): ?>
                        <?= \App\Core\View::partial('partials/card-coche', ['c' => $c]) ?>
                    <?php endforeach; ?>
                </div>

                <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], 'coches',
                    array_filter([
                        'q' => $f['texto'] ?? '',
                        'categorias' => $f['categorias'] ?? '',
                        'combustibles' => $f['combustibles'] ?? '',
                        'marcas' => $f['marcas'] ?? '',
                        'precio_min' => $f['precio_min'] ?? '',
                        'precio_max' => $f['precio_max'] ?? '',
                        'potencia_min' => $f['potencia_min'] ?? '',
                        'orden' => $f['orden'] ?? '',
                    ], static fn ($v): bool => $v !== '' && $v !== null)) ?>
            <?php endif; ?>
        </div>
    </div>
</section>
