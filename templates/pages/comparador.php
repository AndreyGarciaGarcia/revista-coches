<?php
/**
 * Comparador de hasta cuatro vehículos.
 * Recibe: $seleccion, $sugerencias, $maximo, $catalogo
 */

$seleccion = $seleccion ?? [];
$sugerencias = $sugerencias ?? [];
$catalogo = $catalogo ?? [];
$maximo = (int) ($maximo ?? 4);

/** Filas de la tabla comparativa: [rotulo, criterio de mejor valor] */
$filas = [
    'Precio desde'        => ['precio', 'min', static fn (array $c) => $c['precio_nuevo'] ?? $c['precio_ocasion'] ?? PHP_INT_MAX],
    'Categoría'           => [null, null, static fn (array $c) => etiquetaCategoria((string) $c['categoria'])],
    'Combustible'         => [null, null, static fn (array $c) => etiquetaCombustible((string) $c['combustible'])],
    'Potencia'            => ['cv', 'max', static fn (array $c) => (int) ($c['potencia_cv'] ?? 0)],
    'Par motor'           => ['nm', 'max', static fn (array $c) => (int) ($c['par_nm'] ?? 0)],
    '0 a 100 km/h'        => ['s', 'min', static fn (array $c) => (float) ($c['aceleracion_0_100'] ?? PHP_FLOAT_MAX)],
    'Velocidad máxima'    => ['kmh', 'max', static fn (array $c) => (int) ($c['vel_maxima_kmh'] ?? 0)],
    'Consumo'             => [null, null, static function (array $c) {
        if (!empty($c['consumo_l100'])) {
            return num((float) $c['consumo_l100'], 1) . ' l/100 km';
        }

        return !empty($c['consumo_kwh100']) ? num((float) $c['consumo_kwh100'], 1) . ' kWh/100 km' : '—';
    }],
    'Autonomía'           => ['km', 'max', static fn (array $c) => (int) ($c['autonomia_km'] ?? 0)],
    'Emisiones CO₂'       => ['g', 'min', static fn (array $c) => (int) ($c['emisiones_gkm'] ?? PHP_INT_MAX)],
    'Caja'                => [null, null, static fn (array $c) => etiquetaTransmision((string) ($c['transmision'] ?? ''))],
    'Tracción'            => [null, null, static fn (array $c) => etiquetaTraccion((string) ($c['traccion'] ?? ''))],
    'Maletero'            => ['l', 'max', static fn (array $c) => (int) ($c['maletero_litros'] ?? 0)],
    'Largo'               => ['mm', 'min', static fn (array $c) => (int) ($c['largo_mm'] ?? PHP_INT_MAX)],
    'Peso'                => ['kg', 'min', static fn (array $c) => (int) ($c['peso_kg'] ?? PHP_INT_MAX)],
    'Plazas'              => [null, null, static fn (array $c) => (int) ($c['plazas'] ?? 0)],
    'Año desde'           => ['anio', 'max', static fn (array $c) => (int) ($c['anio_desde'] ?? 0)],
    'Valoración'          => ['val', 'max', static fn (array $c) => (float) ($c['valoracion'] ?? 0)],
];

/** @param array<int,array<string,mixed>> $seleccion */
$ids = array_map(static fn (array $c): int => (int) $c['id'], $seleccion);

/** @param array<int,array<string,mixed>> $seleccion */
$sinIds = static fn (int $excluir): string => implode(',', array_filter($ids, static fn (int $i): bool => $i !== $excluir));
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Herramienta</p>
        <h1 class="page-title">Comparador de coches</h1>
        <p class="page-lede">
            <?php if ($seleccion !== []): ?>
                <?= count($seleccion) ?> de <?= $maximo ?> vehículos en comparación.
                La columna mejor valorada en cada fila aparece resaltada.
            <?php else: ?>
                Elige hasta <?= $maximo ?> modelos y compáralos ficha a ficha: motor, prestaciones,
                consumos, dimensiones y precio.
            <?php endif; ?>
        </p>

        <div class="filter-bar filter-bar-top">
            <form class="inline-form" action="<?= url('comparador') ?>" method="get">
                <label class="sr-only" for="ids">Identificadores separados por coma</label>
                <input id="ids" type="text" name="ids" value="<?= e(implode(',', $ids)) ?>"
                       pattern="[0-9,]*" maxlength="40"
                       placeholder="<?= e(implode(',', array_map(static fn (array $c): int => (int) $c['id'], $sugerencias))) ?>">
                <button class="btn btn-primary" type="submit">Comparar</button>
            </form>

            <a class="btn btn-ghost" href="<?= url('coches') ?>">Buscar más modelos</a>
        </div>
    </div>
</section>

<section class="section section-tight">
    <div class="shell">
        <?php if ($seleccion === []): ?>
            <div class="empty-state">
                <p class="empty-title">Ningún coche seleccionado</p>
                <p>Añade vehículos desde el catálogo o compara directamente uno de estos destacados:</p>
            </div>

            <div class="grid grid-cars">
                <?php foreach ($sugerencias as $c): ?>
                    <?= \App\Core\View::partial('partials/card-coche', ['c' => $c]) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="table-scroll compare-scroll">
                <table class="table table-compare">
                    <caption class="sr-only">
                        Comparativa de <?= count($seleccion) ?> vehículos
                    </caption>

                    <thead>
                    <tr>
                        <th scope="col" class="compare-rowhead">Característica</th>

                        <?php foreach ($seleccion as $c): ?>
                            <th scope="col" class="compare-col">
                                <a class="compare-col-media" href="<?= url('coches/' . $c['slug']) ?>">
                                    <?= imagen($c['imagen_principal'] ?? null,
                                        ($c['marca'] ?? '') . ' ' . ($c['modelo'] ?? ''),
                                        ['width' => 320, 'height' => 200, 'loading' => 'lazy']) ?>
                                </a>

                                <p class="compare-brand">
                                    <?= letra_marca((string) $c['marca'], 'is-xs') ?>
                                    <?= e($c['marca']) ?>
                                </p>
                                <h2 class="compare-name">
                                    <a href="<?= url('coches/' . $c['slug']) ?>"><?= e($c['modelo']) ?></a>
                                </h2>
                                <?php if (!empty($c['version'])): ?>
                                    <p class="compare-version"><?= e(excerpt((string) $c['version'], 60)) ?></p>
                                <?php endif; ?>

                                <div class="compare-col-actions">
                                    <?php if (count($seleccion) > 1): ?>
                                        <a class="btn btn-ghost btn-sm"
                                           href="<?= url('comparador') ?>?ids=<?= e($sinIds((int) $c['id'])) ?>">
                                            Quitar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($filas as $rotulo => [$unidad, $sentido, $obtener]): ?>
                        <?php
                        $valores = [];
                        $numericos = [];

                        foreach ($seleccion as $col => $c) {
                            $valor = $obtener($c);
                            $valores[$col] = $valor;

                            if ($sentido !== null && is_numeric($valor)) {
                                $numericos[$col] = (float) $valor;
                            }
                        }

                        $mejor = null;
                        if ($sentido !== null && count($numericos) > 1) {
                            $orden = $numericos;
                            $sentido === 'min' ? asort($orden, SORT_ASC) : arsort($orden, SORT_ASC);
                            $primero = array_key_first($orden);
                            $ultimo = array_key_last($orden);

                            if ($primero !== $ultimo && $orden[$primero] !== $orden[$ultimo]) {
                                $mejor = $primero;
                            }
                        }
                        ?>

                        <tr>
                            <th scope="row" class="compare-rowhead"><?= e($rotulo) ?></th>

                            <?php foreach ($valores as $col => $valor): ?>
                                <?php
                                $esMejor = $mejor !== null && $mejor === $col;
                                $texto = match (true) {
                                    $sentido === 'min' && is_numeric($valor) && (float) $valor < PHP_INT_MAX / 2
                                        && in_array($unidad, ['precio', 's', 'g', 'mm', 'kg'], true)
                                        => $unidad === 'precio' ? euros((int) $valor) : num((float) $valor, $unidad === 's' ? 1 : 0),
                                    $sentido === 'max' && is_numeric($valor) && (float) $valor > 0
                                        && in_array($unidad, ['cv', 'nm', 'kmh', 'km', 'l', 'anio', 'val'], true)
                                        => match ($unidad) {
                                            'val'  => num((float) $valor, 1) . ' / 5',
                                            'cv'   => (int) $valor . ' cv',
                                            'nm'   => (int) $valor . ' Nm',
                                            'kmh'  => (int) $valor . ' km/h',
                                            'km'   => (int) $valor . ' km',
                                            'l'    => (int) $valor . ' l',
                                            default => (string) (int) $valor,
                                        },
                                    is_numeric($valor) && (float) $valor === (int) $valor && (int) $valor === 0
                                        => '—',
                                    default => is_scalar($valor) ? (string) $valor : '—',
                                };
                                ?>

                                <td class="compare-cell<?= $esMejor ? ' is-best' : '' ?>">
                                    <span class="compare-value"><?= e($texto) ?></span>
                                    <?php if ($esMejor): ?>
                                        <span class="compare-tag">Mejor</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <tr>
                        <th scope="row" class="compare-rowhead">Acciones</th>
                        <?php foreach ($seleccion as $c): ?>
                            <td class="compare-cell">
                                <a class="btn btn-primary btn-sm btn-block" href="<?= url('coches/' . $c['slug']) ?>">
                                    Ver ficha
                                </a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    </tbody>
                </table>
            </div>

            <?php if (count($seleccion) < $maximo): ?>
                <section class="compare-add">
                    <h2 class="block-title">Añadir otro vehículo</h2>
                    <form class="inline-form" action="<?= url('comparador') ?>" method="get">
                        <input type="hidden" name="ids" value="<?= e(implode(',', $ids)) ?>">
                        <label class="sr-only" for="add-car">Modelo a añadir</label>
                        <select id="add-car" name="add">
                            <option value="">Elige un modelo…</option>
                            <?php foreach ($catalogo as $c): ?>
                                <?php if (in_array((int) $c['id'], $ids, true)) {
                                    continue;
                                } ?>
                                <option value="<?= (int) $c['id'] ?>">
                                    <?= e($c['marca'] . ' ' . $c['modelo']) ?>
                                    <?= !empty($c['version']) ? ' · ' . e($c['version']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" type="submit" data-compare-submit>Añadir</button>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
