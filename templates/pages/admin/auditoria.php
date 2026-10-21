<?php
/**
 * Traza de auditoría.
 * Recibe: $resultado, $acciones, $accion, $texto
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$acciones = $acciones ?? [];
$accion = (string) ($accion ?? '');
$texto = (string) ($texto ?? '');
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Auditoría</h2>
        <p class="panel-sub">
            <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
            registros · quién, qué, cuándo y desde qué dirección
        </p>
    </div>

    <a class="btn btn-ghost" href="<?= url('admin/auditoria') ?>">Limpiar filtros</a>
</header>

<form class="toolbar" action="<?= url('admin/auditoria') ?>" method="get">
    <label class="sr-only" for="accion">Filtrar por acción</label>
    <select id="accion" name="accion">
        <option value="">Todas las acciones</option>
        <?php foreach (array_keys($acciones) as $a): ?>
            <option value="<?= e($a) ?>" <?= $accion === $a ? 'selected' : '' ?>>
                <?= e($a) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="sr-only" for="q">Buscar en la auditoría</label>
    <input id="q" type="search" name="q" maxlength="120" value="<?= e($texto) ?>"
           placeholder="Buscar por autor, entidad o IP…">

    <button class="btn btn-ghost" type="submit">Filtrar</button>
</form>

<?php if ($resultado['items'] === []): ?>
    <div class="empty-state">
        <p class="empty-title">No hay registros con este filtro</p>
    </div>
<?php else: ?>
    <div class="table-scroll">
        <table class="table table-panel table-audit">
            <thead>
            <tr>
                <th scope="col">Fecha y hora</th>
                <th scope="col">Autor</th>
                <th scope="col">Acción</th>
                <th scope="col">Entidad</th>
                <th scope="col">IP</th>
                <th scope="col">Detalle</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($resultado['items'] as $a): ?>
                <?php
                $datos = (string) ($a['datos'] ?? '');
                $json = $datos !== '' ? json_decode($datos, true) : null;
                ?>
                <tr>
                    <td class="cell-sub">
                        <time datetime="<?= e((string) $a['created_at']) ?>">
                            <?= e(readable_time($a['created_at'], true)) ?>
                        </time>
                    </td>

                    <td class="cell-main">
                        <span class="cell-title"><?= e((string) ($a['actor_email'] ?? 'sistema')) ?></span>
                        <span class="cell-sub"><?= e(time_ago($a['created_at'])) ?></span>
                    </td>

                    <td><code class="audit-action"><?= e((string) $a['accion']) ?></code></td>

                    <td class="cell-sub">
                        <?php if (!empty($a['entidad'])): ?>
                            <?= e((string) $a['entidad']) ?>
                            <?php if (!empty($a['entidad_id'])): ?>
                                <span class="tag">#<?= (int) $a['entidad_id'] ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>

                    <td class="cell-sub"><code><?= e((string) ($a['ip'] ?? '—')) ?></code></td>

                    <td class="cell-detail">
                        <?php if (is_array($json) && $json !== []): ?>
                            <details>
                                <summary>Ver datos</summary>
                                <pre><?= e(json_encode($json,
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                            </details>
                        <?php elseif ($datos !== ''): ?>
                            <details>
                                <summary>Ver datos</summary>
                                <pre><?= e($datos) ?></pre>
                            </details>
                        <?php else: ?>
                            <span class="cell-sub">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], 'admin/auditoria',
        array_filter(['accion' => $accion, 'q' => $texto])) ?>
<?php endif; ?>
