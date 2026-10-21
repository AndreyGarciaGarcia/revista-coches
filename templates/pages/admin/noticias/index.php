<?php
/**
 * Listado de artículos del panel.
 * Recibe: $resultado, $estados, $estado, $texto
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$estados = $estados ?? [];
$estado = (string) ($estado ?? '');
$texto = (string) ($texto ?? '');

$pestanas = [
    ''          => ['Todos', (int) $resultado['total']],
    'borrador'  => ['Borradores', (int) ($estados['borrador'] ?? 0)],
    'revisado'  => ['Revisados', (int) ($estados['revisado'] ?? 0)],
    'publicado' => ['Publicados', (int) ($estados['publicado'] ?? 0)],
    'archivado' => ['Archivados', (int) ($estados['archivado'] ?? 0)],
];

$estadoClase = static fn (string $e): string => match ($e) {
    'publicado' => 'is-ok',
    'revisado'  => 'is-info',
    'archivado' => 'is-muted',
    default     => 'is-warn',
};
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Noticias</h2>
        <p class="panel-sub">
            <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
            artículos en el listado
        </p>
    </div>

    <a class="btn btn-primary" href="<?= url('admin/noticias/nueva') ?>">+ Nuevo artículo</a>
</header>

<nav class="tabs" aria-label="Filtrar por estado">
    <?php foreach ($pestanas as $clave => [$rotulo, $n]): ?>
        <?php
        $href = url('admin/noticias') . ($clave !== '' ? '?estado=' . $clave : '');
        $activo = $estado === $clave;
        ?>
        <a class="tab<?= $activo ? ' is-active' : '' ?>" href="<?= e($href) ?>"
           <?= $activo ? 'aria-current="page"' : '' ?>>
            <?= e($rotulo) ?>
            <span class="tab-count"><?= number_format($n, 0, ',', '.') ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<form class="toolbar" action="<?= url('admin/noticias') ?>" method="get">
    <?php if ($estado !== ''): ?>
        <input type="hidden" name="estado" value="<?= e($estado) ?>">
    <?php endif; ?>

    <label class="sr-only" for="q">Buscar artículos</label>
    <input id="q" type="search" name="q" maxlength="120" value="<?= e($texto) ?>"
           placeholder="Buscar por título o resumen…">

    <button class="btn btn-ghost" type="submit">Buscar</button>

    <?php if ($texto !== ''): ?>
        <a class="btn btn-ghost" href="<?= url('admin/noticias') . ($estado !== '' ? '?estado=' . $estado : '') ?>">
            Limpiar
        </a>
    <?php endif; ?>
</form>

<?php if ($resultado['items'] === []): ?>
    <div class="empty-state">
        <p class="empty-title">No hay artículos con este filtro</p>
        <p>Prueba con otra pestaña o crea uno nuevo.</p>
        <a class="btn btn-primary" href="<?= url('admin/noticias/nueva') ?>">Crear artículo</a>
    </div>
<?php else: ?>
    <div class="table-scroll">
        <table class="table table-panel">
            <thead>
            <tr>
                <th scope="col">Artículo</th>
                <th scope="col">Categoría</th>
                <th scope="col">Autor</th>
                <th scope="col" class="num">Vistas</th>
                <th scope="col" class="num">Com.</th>
                <th scope="col">Estado</th>
                <th scope="col">Actualizado</th>
                <th scope="col" class="actions-col">Acciones</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($resultado['items'] as $n): ?>
                <tr>
                    <td class="cell-main">
                        <span class="cell-title">
                            <?php if (!empty($n['destacada'])): ?>
                                <span class="flag is-star" title="Destacada">★</span>
                            <?php endif; ?>
                            <?php if (!empty($n['fijada'])): ?>
                                <span class="flag is-pin" title="Fijada">📌</span>
                            <?php endif; ?>

                            <a href="<?= url('admin/noticias/' . $n['id'] . '/editar') ?>">
                                <?= e(excerpt((string) $n['titulo'], 80)) ?>
                            </a>
                        </span>

                        <span class="cell-sub">
                            /noticias/<?= e($n['slug']) ?>
                            · <?= (int) $n['tiempo_lectura'] ?> min de lectura
                        </span>
                    </td>

                    <td><?= e((string) ($n['categoria'] ?? 'Sin categoría')) ?></td>
                    <td><?= e((string) ($n['autor'] ?? '—')) ?></td>
                    <td class="num"><?= number_format((int) $n['vistas'], 0, ',', '.') ?></td>
                    <td class="num"><?= (int) $n['num_comentarios'] ?></td>

                    <td>
                        <span class="state <?= $estadoClase((string) $n['estado']) ?>">
                            <?= e((string) $n['estado']) ?>
                        </span>
                    </td>

                    <td class="cell-sub">
                        <?= e(readable_time($n['actualizado_en'] ?: $n['creado_en'])) ?>
                    </td>

                    <td class="actions-col">
                        <div class="row-actions">
                            <a class="btn btn-ghost btn-sm"
                               href="<?= url('admin/noticias/' . $n['id'] . '/editar') ?>">Editar</a>

                            <?php if ($n['estado'] !== 'publicado'): ?>
                                <form class="inline-form" action="<?= url('admin/noticias/' . $n['id'] . '/publicar') ?>"
                                      method="post">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-primary btn-sm" type="submit">Publicar</button>
                                </form>
                            <?php endif; ?>

                            <form class="inline-form" action="<?= url('admin/noticias/' . $n['id'] . '/borrar') ?>"
                                  method="post" data-confirm="¿Eliminar este artículo? El borrado es lógico.">
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

    <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], 'admin/noticias',
        array_filter(['estado' => $estado, 'q' => $texto])) ?>
<?php endif; ?>
