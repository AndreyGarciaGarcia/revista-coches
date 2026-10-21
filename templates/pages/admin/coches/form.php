<?php
/**
 * Ficha técnica completa desde el panel.
 * Recibe: $coche (null = alta), $marcas, $imagenes,
 *         $categorias, $combustibles, $transmisiones, $tracciones, $eficiencias
 */

$c = $coche;
$esNuevo = $c === null;

$valores = static fn (string $campo, mixed $defecto = ''): string => e((string) (($c[$campo] ?? $defecto) ?? $defecto));

$lista = static function (?array $c, string $campo): string {
    if (empty($c[$campo])) {
        return '';
    }

    $v = $c[$campo];

    if (is_string($v)) {
        $decoded = json_decode($v, true);
        $v = is_array($decoded) ? $decoded : [$v];
    }

    if (!is_array($v)) {
        return '';
    }

    return e(implode("\n", array_map('strval', $v)));
};

$opciones = static function (array $valores, ?string $actual, array $rotulos = []): void {
    foreach ($valores as $v) {
        $etiqueta = $rotulos[$v] ?? $v;
        printf(
            '<option value="%s"%s>%s</option>',
            e($v),
            $actual === $v ? ' selected' : '',
            e($etiqueta)
        );
    }
};
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg"><?= $esNuevo ? 'Nuevo vehículo' : 'Editar ficha técnica' ?></h2>
        <p class="panel-sub">
            <?php if ($esNuevo): ?>
                Los campos marcados como obligatorios son los mínimos para poder publicar la ficha.
            <?php else: ?>
                <?= e($c['marca'] . ' ' . $c['modelo']) ?>
                · <?= (int) $c['vistas'] ?> vistas ·
                <span class="state <?= $c['estado'] === 'publicado' ? 'is-ok' : 'is-warn' ?>">
                    <?= e((string) $c['estado']) ?>
                </span>
            <?php endif; ?>
        </p>
    </div>

    <div class="head-actions">
        <?php if (!$esNuevo && !empty($c['slug'])): ?>
            <a class="btn btn-ghost" href="<?= url('admin/coches/' . (int) $c['id'] . '/galeria') ?>">
                Galería y vídeo
            </a>

            <a class="btn btn-ghost" href="<?= url('coches/' . $c['slug']) ?>" target="_blank" rel="noopener">
                Ver ficha ↗
            </a>
        <?php endif; ?>

        <a class="btn btn-ghost" href="<?= url('admin/coches') ?>">Volver al listado</a>
    </div>
</header>

<form class="form form-panel"
      action="<?= $esNuevo ? url('admin/coches/nuevo') : url('admin/coches/' . $c['id'] . '/editar') ?>"
      method="post" data-validate novalidate>
    <?= csrf_field() ?>

    <div class="editor-grid">

        <div class="editor-main">

            <section class="box">
                <h3 class="box-title">Identificación</h3>

                <div class="form-row">
                    <div class="field">
                        <label for="marca_id">Marca <span class="req">obligatorio</span></label>
                        <select id="marca_id" name="marca_id" required>
                            <option value="">Elige una marca…</option>
                            <?php foreach (($marcas ?? []) as $m): ?>
                                <option value="<?= (int) $m['id'] ?>"
                                    <?= (int) ($c['marca_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                                    <?= e($m['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error" hidden></p>
                    </div>

                    <div class="field">
                        <label for="modelo">Modelo <span class="req">obligatorio</span></label>
                        <input id="modelo" name="modelo" type="text" required minlength="2" maxlength="120"
                               value="<?= $valores('modelo') ?>" placeholder="Ej. Golf">
                        <p class="field-error" hidden></p>
                    </div>
                </div>

                <div class="field">
                    <label for="version">Versión</label>
                    <input id="version" name="version" type="text" maxlength="140"
                           value="<?= $valores('version') ?>"
                           placeholder="Ej. 1.5 TSI 150 cv DSG Style">
                    <p class="field-help">Motorización, potencia y acabado concretos.</p>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="categoria">Categoría <span class="req">obligatorio</span></label>
                        <select id="categoria" name="categoria" required>
                            <?php $opciones($categorias ?? [], (string) ($c['categoria'] ?? ''), array_map(
                                static fn (string $v): string => etiquetaCategoria($v),
                                $categorias ?? []
                            )); ?>
                        </select>
                        <p class="field-error" hidden></p>
                    </div>

                    <div class="field">
                        <label for="estado">Estado <span class="req">obligatorio</span></label>
                        <select id="estado" name="estado" required>
                            <?php foreach (['publicado', 'borrador', 'archivado'] as $e): ?>
                                <option value="<?= e($e) ?>"
                                    <?= (string) ($c['estado'] ?? 'borrador') === $e ? 'selected' : '' ?>>
                                    <?= e(ucfirst($e)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="check">
                    <input type="checkbox" name="destacado" value="1"
                        <?= !empty($c['destacado']) ? 'checked' : '' ?>>
                    <span>Destacado en la portada del catálogo</span>
                </label>
            </section>

            <section class="box">
                <h3 class="box-title">Motor y transmisión</h3>

                <div class="form-row form-row-3">
                    <div class="field">
                        <label for="combustible">Combustible <span class="req">obligatorio</span></label>
                        <select id="combustible" name="combustible" required>
                            <?php $opciones($combustibles ?? [], (string) ($c['combustible'] ?? 'gasolina'), [
                                'gasolina'           => 'Gasolina',
                                'diesel'             => 'Diésel',
                                'hibrido'            => 'Híbrido',
                                'hibrido_enchufable' => 'Híbrido enchufable',
                                'electrico'          => 'Eléctrico',
                                'gas_natural'        => 'Gas natural (GNL/CNG)',
                            ]); ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="transmision">Caja <span class="req">obligatorio</span></label>
                        <select id="transmision" name="transmision" required>
                            <?php $opciones($transmisiones ?? [], (string) ($c['transmision'] ?? 'manual'), [
                                'manual'    => 'Manual',
                                'automatica' => 'Automática',
                                'dct'       => 'Doble embrague (DCT)',
                                'cvt'       => 'Variador continuo (CVT)',
                            ]); ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="traccion">Tracción <span class="req">obligatorio</span></label>
                        <select id="traccion" name="traccion" required>
                            <?php $opciones($tracciones ?? [], (string) ($c['traccion'] ?? 'delantera'), [
                                'delantera'    => 'Delantera',
                                'trasera'      => 'Trasera',
                                'cuatro_ruedas' => 'Integral',
                            ]); ?>
                        </select>
                    </div>
                </div>

                <div class="form-row form-row-4">
                    <div class="field">
                        <label for="potencia_cv">Potencia (cv)</label>
                        <input id="potencia_cv" name="potencia_cv" type="number" min="1" max="3000"
                               value="<?= $valores('potencia_cv') ?>">
                    </div>

                    <div class="field">
                        <label for="par_nm">Par motor (Nm)</label>
                        <input id="par_nm" name="par_nm" type="number" min="1" max="3000"
                               value="<?= $valores('par_nm') ?>">
                    </div>

                    <div class="field">
                        <label for="cilindrada_cc">Cilindrada (cm³)</label>
                        <input id="cilindrada_cc" name="cilindrada_cc" type="number" min="0" max="10000"
                               value="<?= $valores('cilindrada_cc') ?>">
                    </div>

                    <div class="field">
                        <label for="cilindros">Cilindros</label>
                        <input id="cilindros" name="cilindros" type="number" min="0" max="16"
                               value="<?= $valores('cilindros') ?>">
                    </div>
                </div>

                <div class="form-row form-row-4">
                    <div class="field">
                        <label for="marchas">Marchas</label>
                        <input id="marchas" name="marchas" type="number" min="0" max="12"
                               value="<?= $valores('marchas') ?>">
                    </div>

                    <div class="field">
                        <label for="bateria_kwh">Batería (kWh)</label>
                        <input id="bateria_kwh" name="bateria_kwh" type="number" step="0.1" min="0" max="300"
                               value="<?= $valores('bateria_kwh') ?>">
                    </div>

                    <div class="field">
                        <label for="anio_desde">Año desde <span class="req">obligatorio</span></label>
                        <input id="anio_desde" name="anio_desde" type="number" required min="1900" max="2100"
                               value="<?= $valores('anio_desde', '2024') ?>">
                        <p class="field-error" hidden></p>
                    </div>

                    <div class="field">
                        <label for="anio_hasta">Año hasta</label>
                        <input id="anio_hasta" name="anio_hasta" type="number" min="1900" max="2100"
                               value="<?= $valores('anio_hasta') ?>">
                    </div>
                </div>
            </section>

            <section class="box">
                <h3 class="box-title">Prestaciones y consumo</h3>

                <div class="form-row form-row-4">
                    <div class="field">
                        <label for="aceleracion_0_100">0 a 100 km/h (s)</label>
                        <input id="aceleracion_0_100" name="aceleracion_0_100" type="number" step="0.1"
                               min="0.5" max="60" value="<?= $valores('aceleracion_0_100') ?>">
                    </div>

                    <div class="field">
                        <label for="vel_maxima_kmh">Vel. máxima (km/h)</label>
                        <input id="vel_maxima_kmh" name="vel_maxima_kmh" type="number" min="20" max="600"
                               value="<?= $valores('vel_maxima_kmh') ?>">
                    </div>

                    <div class="field">
                        <label for="autonomia_km">Autonomía (km)</label>
                        <input id="autonomia_km" name="autonomia_km" type="number" min="0" max="2000"
                               value="<?= $valores('autonomia_km') ?>">
                    </div>

                    <div class="field">
                        <label for="eficiencia">Etiqueta de eficiencia</label>
                        <select id="eficiencia" name="eficiencia">
                            <option value="">Sin etiqueta</option>
                            <?php $opciones($eficiencias ?? [], (string) ($c['eficiencia'] ?? '')); ?>
                        </select>
                    </div>
                </div>

                <div class="form-row form-row-4">
                    <div class="field">
                        <label for="consumo_l100">Consumo térmico (l/100 km)</label>
                        <input id="consumo_l100" name="consumo_l100" type="number" step="0.1" min="0" max="60"
                               value="<?= $valores('consumo_l100') ?>">
                    </div>

                    <div class="field">
                        <label for="consumo_kwh100">Consumo eléctrico (kWh/100 km)</label>
                        <input id="consumo_kwh100" name="consumo_kwh100" type="number" step="0.1" min="0" max="100"
                               value="<?= $valores('consumo_kwh100') ?>">
                    </div>

                    <div class="field">
                        <label for="emisiones_gkm">Emisiones CO₂ (g/km)</label>
                        <input id="emisiones_gkm" name="emisiones_gkm" type="number" min="0" max="800"
                               value="<?= $valores('emisiones_gkm') ?>">
                    </div>

                    <div class="field">
                        <label for="valoracion">Valoración (0–5)</label>
                        <input id="valoracion" name="valoracion" type="number" step="0.1" min="0" max="5"
                               value="<?= $valores('valoracion', '0') ?>">
                    </div>
                </div>
            </section>

            <section class="box">
                <h3 class="box-title">Dimensiones</h3>

                <div class="form-row form-row-4">
                    <div class="field">
                        <label for="largo_mm">Largo (mm)</label>
                        <input id="largo_mm" name="largo_mm" type="number" min="0" max="8000"
                               value="<?= $valores('largo_mm') ?>">
                    </div>

                    <div class="field">
                        <label for="ancho_mm">Ancho (mm)</label>
                        <input id="ancho_mm" name="ancho_mm" type="number" min="0" max="5000"
                               value="<?= $valores('ancho_mm') ?>">
                    </div>

                    <div class="field">
                        <label for="alto_mm">Alto (mm)</label>
                        <input id="alto_mm" name="alto_mm" type="number" min="0" max="5000"
                               value="<?= $valores('alto_mm') ?>">
                    </div>

                    <div class="field">
                        <label for="distancia_ejes_mm">Batalla (mm)</label>
                        <input id="distancia_ejes_mm" name="distancia_ejes_mm" type="number" min="0" max="6000"
                               value="<?= $valores('distancia_ejes_mm') ?>">
                    </div>
                </div>

                <div class="form-row form-row-4">
                    <div class="field">
                        <label for="peso_kg">Peso (kg)</label>
                        <input id="peso_kg" name="peso_kg" type="number" min="0" max="10000"
                               value="<?= $valores('peso_kg') ?>">
                    </div>

                    <div class="field">
                        <label for="maletero_litros">Maletero (l)</label>
                        <input id="maletero_litros" name="maletero_litros" type="number" min="0" max="3000"
                               value="<?= $valores('maletero_litros') ?>">
                    </div>

                    <div class="field">
                        <label for="plazas">Plazas</label>
                        <input id="plazas" name="plazas" type="number" min="1" max="9"
                               value="<?= $valores('plazas', '5') ?>">
                    </div>

                    <div class="field">
                        <label for="puertas">Puertas</label>
                        <input id="puertas" name="puertas" type="number" min="2" max="7"
                               value="<?= $valores('puertas', '5') ?>">
                    </div>
                </div>
            </section>

            <section class="box">
                <h3 class="box-title">Precio y contenido</h3>

                <div class="form-row form-row-3">
                    <div class="field">
                        <label for="precio_nuevo">Precio nuevo (€)</label>
                        <input id="precio_nuevo" name="precio_nuevo" type="number" min="0" max="1000000"
                               value="<?= $valores('precio_nuevo') ?>">
                    </div>

                    <div class="field">
                        <label for="precio_ocasion">Precio de ocasión (€)</label>
                        <input id="precio_ocasion" name="precio_ocasion" type="number" min="0" max="1000000"
                               value="<?= $valores('precio_ocasion') ?>">
                    </div>

                    <div class="field">
                        <label for="imagen_id">Fotografía principal</label>
                        <select id="imagen_id" name="imagen_id" data-preview-select
                                data-preview-target="img-preview">
                            <option value="">Sin imagen</option>
                            <?php foreach (($imagenes ?? []) as $img): ?>
                                <option value="<?= (int) $img['id'] ?>"
                                    data-src="<?= e(asset_url((string) $img['ruta'])) ?>"
                                    <?= (int) ($imagenActual ?? 0) === (int) $img['id'] ? 'selected' : '' ?>>
                                    <?= e($img['titulo'] ?: basename((string) $img['ruta'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="img-preview" id="img-preview" hidden>
                            <img alt="Vista previa de la imagen seleccionada">
                            <p class="img-preview-credit"></p>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="resumen">Resumen</label>
                    <textarea id="resumen" name="resumen" rows="3" maxlength="400"
                              data-counter data-count-target="resumen"
                              placeholder="Qué es este coche en dos frases."><?= $valores('resumen') ?></textarea>
                    <p class="field-help">
                        <span data-count="resumen"><?= mb_strlen((string) ($c['resumen'] ?? '')) ?></span> / 400
                    </p>
                </div>

                <div class="field">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="8" maxlength="60000"
                              placeholder="Contexto del modelo, público al que va dirigido, versiones…"><?= $valores('descripcion') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="puntos_fuertes">Puntos fuertes</label>
                        <textarea id="puntos_fuertes" name="puntos_fuertes" rows="5" maxlength="4000"
                                  placeholder="Uno por línea"><?= $lista($c, 'puntos_fuertes') ?></textarea>
                        <p class="field-help">Un punto por línea.</p>
                    </div>

                    <div class="field">
                        <label for="puntos_debiles">Puntos débiles</label>
                        <textarea id="puntos_debiles" name="puntos_debiles" rows="5" maxlength="4000"
                                  placeholder="Uno por línea"><?= $lista($c, 'puntos_debiles') ?></textarea>
                        <p class="field-help">Un punto por línea.</p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="editor-side">
            <div class="form-actions is-stacked">
                <button class="btn btn-primary btn-lg btn-block" type="submit">
                    <?= $esNuevo ? 'Añadir al catálogo' : 'Guardar ficha' ?>
                </button>

                <a class="btn btn-ghost btn-block" href="<?= url('admin/coches') ?>">Volver</a>
            </div>

            <section class="box box-help">
                <h3 class="box-title">Ficha mínima</h3>
                <ul class="checklist">
                    <li>Marca, modelo y versión exactos.</li>
                    <li>Potencia y par en unidades homologadas.</li>
                    <li>Consumo y emisiones del ciclo WLTP.</li>
                    <li>PVP recomendado con impuestos.</li>
                    <li>Al menos una fotografía propia del modelo.</li>
                </ul>
            </section>

            <section class="box">
                <h3 class="box-title">Guía de categorías</h3>
                <dl class="hint-list">
                    <?php foreach (($categorias ?? []) as $cat): ?>
                        <dt><?= e($cat) ?></dt>
                        <dd><?= e(etiquetaCategoria($cat)) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </section>
        </aside>
    </div>
</form>
