<?php
/**
 * Gestión de usuarios y roles.
 * Recibe: $resultado, $roles, $texto
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$roles = $roles ?? [];
$texto = (string) ($texto ?? '');
$yo = auth_user();

$estadoClase = static fn (string $e): string => match ($e) {
    'activo'    => 'is-ok',
    'suspendido' => 'is-danger',
    default     => 'is-muted',
};
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Usuarios</h2>
        <p class="panel-sub">
            <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
            cuentas activas · los cambios de rol se auditan con autor e IP
        </p>
    </div>
</header>

<form class="toolbar" action="<?= url('admin/usuarios') ?>" method="get">
    <label class="sr-only" for="q">Buscar usuarios</label>
    <input id="q" type="search" name="q" maxlength="120" value="<?= e($texto) ?>"
           placeholder="Buscar por correo, nombre o apellidos…">

    <button class="btn btn-ghost" type="submit">Buscar</button>

    <?php if ($texto !== ''): ?>
        <a class="btn btn-ghost" href="<?= url('admin/usuarios') ?>">Limpiar</a>
    <?php endif; ?>
</form>

<?php if ($resultado['items'] === []): ?>
    <div class="empty-state">
        <p class="empty-title">No hay usuarios con esta búsqueda</p>
    </div>
<?php else: ?>
    <div class="table-scroll">
        <table class="table table-panel">
            <thead>
            <tr>
                <th scope="col">Usuario</th>
                <th scope="col">Rol</th>
                <th scope="col">Estado</th>
                <th scope="col">Último acceso</th>
                <th scope="col">Alta</th>
                <th scope="col">Última IP</th>
                <th scope="col" class="actions-col">Acciones</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($resultado['items'] as $u): ?>
                <?php $esYo = (int) $u['id'] === (int) ($yo['id'] ?? 0); ?>

                <tr<?= $esYo ? ' class="is-me"' : '' ?>>
                    <td class="cell-main">
                        <span class="cell-title">
                            <?= letra_marca((string) $u['nombre'], 'is-xs') ?>
                            <?= e($u['nombre'] . ' ' . ($u['apellidos'] ?? '')) ?>
                            <?php if ($esYo): ?>
                                <span class="tag">Tú</span>
                            <?php endif; ?>
                        </span>
                        <span class="cell-sub">
                            <a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a>
                            · <code><?= e((string) $u['uuid']) ?></code>
                        </span>
                    </td>

                    <td>
                        <span class="role role-n<?= (int) $u['nivel'] ?>"><?= e($u['rol']) ?></span>
                    </td>

                    <td>
                        <span class="state <?= $estadoClase((string) $u['estado']) ?>">
                            <?= e((string) $u['estado']) ?>
                        </span>
                    </td>

                    <td class="cell-sub">
                        <?= $u['ultimo_acceso'] ? e(time_ago($u['ultimo_acceso'])) : 'Nunca' ?>
                    </td>
                    <td class="cell-sub"><?= e(readable_time($u['creado_en'])) ?></td>
                    <td class="cell-sub"><code><?= e((string) ($u['ultima_ip'] ?? '—')) ?></code></td>

                    <td class="actions-col">
                        <?php if ($esYo): ?>
                            <span class="cell-sub">No editable</span>
                        <?php else: ?>
                            <form class="row-actions" data-inline
                                  action="<?= url('admin/usuarios/' . $u['id'] . '/editar') ?>"
                                  method="post">
                                <?= csrf_field() ?>

                                <label class="sr-only" for="rol-<?= (int) $u['id'] ?>">Rol</label>
                                <select id="rol-<?= (int) $u['id'] ?>" name="rol_id">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= (int) $r['id'] ?>"
                                            <?= (int) $u['rol_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                                            <?= e($r['nombre']) ?> (<?= (int) $r['nivel'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label class="sr-only" for="estado-<?= (int) $u['id'] ?>">Estado</label>
                                <select id="estado-<?= (int) $u['id'] ?>" name="estado">
                                    <?php foreach (['activo', 'inactivo', 'suspendido'] as $e): ?>
                                        <option value="<?= e($e) ?>"
                                            <?= (string) $u['estado'] === $e ? 'selected' : '' ?>>
                                            <?= e(ucfirst($e)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button class="btn btn-primary btn-sm" type="submit"
                                        data-confirm="Guardar los cambios de rol/estado de este usuario?">
                                    Guardar
                                </button>
                            </form>

                            <form class="inline-form"
                                  action="<?= url('admin/usuarios/' . $u['id'] . '/borrar') ?>"
                                  method="post"
                                  data-confirm="¿Desactivar esta cuenta? Se conserva el registro para auditoría.">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm is-danger" type="submit">Baja</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], 'admin/usuarios',
        array_filter(['q' => $texto])) ?>
<?php endif; ?>

<section class="panel-box panel-box-wide">
    <header class="panel-box-head">
        <h2 class="panel-title">Niveles de acceso</h2>
    </header>

    <div class="table-scroll">
        <table class="table">
            <thead>
            <tr>
                <th scope="col">Nivel</th>
                <th scope="col">Rol</th>
                <th scope="col">Puede</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $descripciones = [
                100 => 'Todo: seguridad, base de datos, usuarios y configuración',
                90  => 'Usuarios, auditoría y configuración del sistema',
                50  => 'Publicar artículos y moderar comentarios',
                30  => 'Gestionar el panel: noticias, catálogo y mensajes',
                0   => 'Leer, comentar y guardar comparativas',
            ];

            $porNivel = [];
            foreach ($roles as $r) {
                $porNivel[(int) $r['nivel']] = (string) $r['nombre'];
            }

            krsort($descripciones);
            foreach ($descripciones as $nivel => $textoNivel): ?>
                <tr>
                    <td><span class="role role-n<?= (int) $nivel ?>"><?= (int) $nivel ?></span></td>
                    <td><?= e($porNivel[(int) $nivel] ?? 'Sin rol asignado') ?></td>
                    <td><?= e($textoNivel) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
