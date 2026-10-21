<?php
/**
 * Configuración editable del sitio.
 * Recibe: $valores, $entorno, $metricas
 */

$valores = $valores ?? [];
$entorno = $entorno ?? [];
$metricas = $metricas ?? [];

$agrupados = [];
$iconos = [
    'catalogo'     => '⛬',
    'comentarios'  => '✍',
    'mantenimiento' => '⚙',
    'seguridad'    => '⛨',
    'contacto'     => '✉',
    'noticias'     => '✎',
    'seo'          => '⌕',
];

foreach ($valores as $clave => $fila) {
    $prefijo = strstr((string) $clave, '.', true);
    $agrupados[$prefijo ?: 'general'][] = ['clave' => (string) $clave, 'fila' => $fila];
}

ksort($agrupados);
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Configuración</h2>
        <p class="panel-sub">
            Ajustes persistentes de la publicación. Los valores del sistema y las credenciales
            no son editables desde la interfaz.
        </p>
    </div>
</header>

<form class="form form-panel" action="<?= url('admin/configuracion') ?>" method="post">
    <?= csrf_field() ?>

    <div class="editor-grid">

        <div class="editor-main">
            <?php foreach ($agrupados as $grupo => $filas): ?>
                <section class="box">
                    <h3 class="box-title">
                        <span class="box-ico" aria-hidden="true">
                            <?= e($iconos[$grupo] ?? '⚙') ?>
                        </span>
                        <?= e(ucfirst(str_replace(['_', '-'], ' ', (string) $grupo))) ?>
                    </h3>

                    <div class="settings-list">
                        <?php foreach ($filas as $el): ?>
                            <?php $f = $el['fila']; $tipo = (string) $f['tipo']; ?>
                            <div class="setting">
                                <div class="setting-text">
                                    <label for="cfg_<?= e($el['clave']) ?>">
                                        <code><?= e($el['clave']) ?></code>
                                    </label>
                                    <p><?= e((string) ($f['descripcion'] ?? '')) ?></p>
                                </div>

                                <div class="setting-control">
                                    <?php if ($tipo === 'booleano'): ?>
                                        <?php $activo = in_array((string) $f['valor'], ['1', 'true', 'on'], true); ?>
                                        <select id="cfg_<?= e($el['clave']) ?>" name="cfg_<?= e($el['clave']) ?>">
                                            <option value="1" <?= $activo ? 'selected' : '' ?>>Activado</option>
                                            <option value="0" <?= $activo ? '' : 'selected' ?>>Desactivado</option>
                                        </select>

                                    <?php elseif ($tipo === 'numero'): ?>
                                        <input id="cfg_<?= e($el['clave']) ?>" type="number"
                                               name="cfg_<?= e($el['clave']) ?>"
                                               value="<?= e((string) $f['valor']) ?>">

                                    <?php elseif ($tipo === 'json'): ?>
                                        <textarea id="cfg_<?= e($el['clave']) ?>" rows="4"
                                                  name="cfg_<?= e($el['clave']) ?>"><?= e((string) $f['valor']) ?></textarea>

                                    <?php else: ?>
                                        <input id="cfg_<?= e($el['clave']) ?>" type="text"
                                               name="cfg_<?= e($el['clave']) ?>" maxlength="4000"
                                               value="<?= e((string) $f['valor']) ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php if ($agrupados === []): ?>
                <div class="empty-state">
                    <p class="empty-title">No hay claves de configuración</p>
                </div>
            <?php endif; ?>
        </div>

        <aside class="editor-side">
            <div class="form-actions is-stacked">
                <button class="btn btn-primary btn-lg btn-block" type="submit">Guardar configuración</button>
                <a class="btn btn-ghost btn-block" href="<?= url('admin/configuracion') ?>">Descartar cambios</a>
            </div>

            <section class="box">
                <h3 class="box-title">Entorno</h3>
                <dl class="hint-list">
                    <?php foreach ($entorno as $clave => $valor): ?>
                        <dt><?= e(ucfirst(str_replace('_', ' ', (string) $clave))) ?></dt>
                        <dd><code><?= e((string) $valor) ?></code></dd>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="box">
                <h3 class="box-title">Cifras del sitio</h3>
                <dl class="hint-list">
                    <?php foreach ($metricas as $clave => $valor): ?>
                        <dt><?= e(ucfirst(str_replace('_', ' ', (string) $clave))) ?></dt>
                        <dd><?= number_format((int) $valor, 0, ',', '.') ?></dd>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="box box-help">
                <h3 class="box-title">Seguridad</h3>
                <ul class="checklist">
                    <li>Usuario MySQL con privilegios mínimos.</li>
                    <li>Contraseñas con Argon2id.</li>
                    <li>Consultas preparadas en todas las rutas.</li>
                    <li>Límite de peticiones por IP en formularios y API.</li>
                    <li>Auditoría de toda operación sensible.</li>
                </ul>
            </section>
        </aside>
    </div>
</form>
