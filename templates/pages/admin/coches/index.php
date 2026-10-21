<?php
/**
 * Listado del catálogo desde el panel.
 * Recibe: $resultado, $estados, $estado, $texto
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$estados = $estados ?? [];
$estado = (string) ($estado ?? '');
$texto = (string) ($texto ?? '');

$pestanas = [
    ''          => ['Todos', (int) $resultado['total']],
    'publicado' => ['Publicados', (int) ($estados['publicado'] ?? 0)],
    'borrador'  => ['Borradores', (int) ($estados['borrador'] ?? 0)],
    'archivado' => ['Archivados', (int) ($estados['archivado'] ?? 0)],
];

$estadoClase = static fn (string $e): string => match ($e) {
    'publicado' => 'is-ok',
    'archivado' => 'is-muted',
    default     => 'is-warn',
};
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Catálogo de vehículos</h2>
        <p class="panel-sub">
            <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
            fichas en el listado
        </p>
    </div>

    <a class="btn btn-primary" href="<?= url('admin/coches/nuevo') ?>">+ Nuevo vehículo</a>
</header>

<nav class="tabs" aria-label="Filtrar por estado">
    <?php foreach ($pestanas as $clave => [$rotulo, $n]): ?>
        <?php
        $href = url('admin/coches') . ($clave !== '' ? '?estado=' . $clave : '');
        $activo = $estado === $clave;
        ?>
        <a class="tab<?= $activo ? ' is-active' : '' ?>" href="<?= e($href) ?>"
           <?= $activo ? 'aria-current="page"' : '' ?>>
            <?= e($rotulo) ?>
            <span class="tab-count"><?= number_format($n, 0, ',', '.') ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<form class="toolbar" action="<?= url('admin/coches') ?>" method="get">
    <?php if ($estado !== ''): ?>
        <input type="hidden" name="estado" value="<?= e($estado) ?>">
    <?php endif; ?>

    <label class="sr-only" for="q">Buscar vehículos</label>
    <input id="q" type="search" name="q" maxlength="120" value="<?= e($texto) ?>"
           placeholder="Buscar por marca, modelo o versión…">

    <button class="btn btn-ghost" type="submit">Buscar</button>

    <?php if ($texto !== ''): ?>
        <a class="btn btn-ghost" href="<?= url('admin/coches') . ($estado !== '' ? '?estado=' . $estado : '') ?>">
            Limpiar
        </a>
    <?php endif; ?>
</form>

<?php if ($resultado['items'] === []): ?>
    <div class="empty-state">
        <p class="empty-title">No hay vehículos con este filtro</p>
        <p>Añade la primera ficha técnica del catálogo.</p>
        <a class="btn btn-primary" href="<?= url('admin/coches/nuevo') ?>">Añadir vehículo</a>
    </div>
<?php else: ?>
    <div class="table-scroll">
        <table class="table table-panel">
            <thead>
            <tr>
                <th scope="col">Vehículo</th>
                <th scope="col">Categoría</th>
                <th scope="col">Combustible</th>
                <th scope="col" class="num">cv</th>
                <th scope="col" class="num">Precio nuevo</th>
                <th scope="col" class="num">Fotos</th>
                <th scope="col" class="num">Vistas</th>
                <th scope="col">Estado</th>
                <th scope="col" class="actions-col">Acciones</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($resultado['items'] as $c): ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title">
                            <?php if (!empty($c['destacado'])): ?>
                                <span class="flag is-star" title="Destacado">★</span>
                            <?php endif; ?>

                            <a href="<?= url('admin/coches/' . $c['id'] . '/editar') ?>">
                                <?= e($c['marca'] . ' ' . $c['modelo']) ?>
                            </a>
                        </span>

                        <span class="cell-sub">
                            <?php if (!empty($c['version'])): ?>
                                <?= e(excerpt((string) $c['version'], 50)) ?>
                            <?php else: ?>
                                Sin versión concreta
                            <?php endif; ?>
                            · /coches/<?= e($c['slug']) ?>
                        </span>
                    </td>

                    <td><?= e(etiquetaCategoria((string) $c['categoria'])) ?></td>
                    <td><?= e(etiquetaCombustible((string) $c['combustible'])) ?></td>
                    <td class="num"><?= !empty($c['potencia_cv']) ? (int) $c['potencia_cv'] : '—' ?></td>
                    <td class="num">
                        <?= !empty($c['precio_nuevo']) ? e(euros((int) $c['precio_nuevo'])) : '—' ?>
                    </td>
                    <td class="num"><?= (int) $c['num_imagenes'] ?></td>
                    <td class="num"><?= number_format((int) $c['vistas'], 0, ',', '.') ?></td>

                    <td>
                        <span class="state <?= $estadoClase((string) $c['estado']) ?>">
                            <?= e((string) $c['estado']) ?>
                        </span>
                    </td>

                    <td class="actions-col">
                        <div class="row-actions">
                            <a class="btn btn-ghost btn-sm"
                               href="<?= url('admin/coches/' . $c['id'] . '/editar') ?>">Editar</a>

                            <a class="btn btn-ghost btn-sm"
                               href="<?= url('admin/coches/' . $c['id'] . '/galeria') ?>">Galería</a>

                            <form class="inline-form"
                                  action="<?= url('admin/coches/' . $c['id'] . '/borrar') ?>"
                                  method="post" data-confirm="¿Eliminar este vehículo del catálogo?">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm is-danger" type="submit">Borrar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], 'admin/coches',
        array_filter(['estado' => $estado, 'q' => $texto])) ?>
<?php endif; ?>
