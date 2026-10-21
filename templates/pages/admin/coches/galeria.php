<?php
/**
 * Galería fotográfica y material en vídeo de un vehículo.
 * Recibe: $coche, $fotos, $videos, $categorias
 */

$c = $coche ?? null;
$fotos = $fotos ?? [];
$videos = $videos ?? [];
$categorias = $categorias ?? [];

$porCategoria = [];
foreach ($fotos as $f) {
    $clave = (string) ($f['categoria'] ?? 'exterior');
    $porCategoria[$clave] = ($porCategoria[$clave] ?? 0) + 1;
}

$principal = array_values(array_filter($fotos, static fn (array $f): bool => !empty($f['es_principal'])));
$principalId = $principal !== [] ? (int) $principal[0]['id'] : 0;
$rotulo = static fn (string $k): string => $categorias[$k] ?? $k;

$pesoTotal = 0;
foreach ($fotos as $f) {
    $pesoTotal += (int) ($f['peso_kb'] ?? 0);
}
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Galería y vídeo</h2>
        <p class="panel-sub">
            <a href="<?= url('admin/coches/' . (int) $c['id'] . '/editar') ?>"><?= e($c['etiqueta']) ?></a>
            · <strong><?= count($fotos) ?></strong>
            <?= count($fotos) === 1 ? 'fotografía' : 'fotografías' ?>
            (<?= number_format($pesoTotal, 0, ',', '.') ?> KB)
            · <strong><?= count($videos) ?></strong>
            <?= count($videos) === 1 ? 'vídeo' : 'vídeos' ?>
        </p>
    </div>

    <div class="head-actions">
        <a class="btn btn-ghost" href="<?= url('coches/' . $c['slug']) ?>" target="_blank" rel="noopener">
            Ver ficha ↗
        </a>
        <a class="btn btn-ghost" href="<?= url('admin/coches/' . (int) $c['id'] . '/editar') ?>">Editar ficha</a>
        <a class="btn btn-ghost" href="<?= url('admin/coches') ?>">Listado</a>
    </div>
</header>

<?php if ($fotos !== []): ?>
    <nav class="tabs" aria-label="Reparto de la galería por categoría">
        <span class="tab is-active">
            Todas <span class="tab-count"><?= count($fotos) ?></span>
        </span>
        <?php foreach ($categorias as $clave => $etiqueta): ?>
            <span class="tab">
                <?= e($etiqueta) ?>
                <span class="tab-count"><?= (int) ($porCategoria[$clave] ?? 0) ?></span>
            </span>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<section class="box">
    <h3 class="box-title">Añadir fotografías</h3>

    <form class="ag-form" method="post" enctype="multipart/form-data"
          action="<?= url('admin/coches/' . (int) $c['id'] . '/galeria/fotos') ?>">
        <?= csrf_field() ?>

        <div class="form-row form-row-3">
            <div class="field">
                <label for="fotos">Archivos JPG <span class="req">obligatorio</span></label>
                <input id="fotos" name="fotos[]" type="file" accept="image/jpeg,.jpg,.jpeg"
                       multiple required>
                <p class="field-help">Hasta 10 por envío · 6 MB máximo cada uno · 400 px de ancho mínimo.</p>
            </div>

            <div class="field">
                <label for="categoria">Categoría</label>
                <select id="categoria" name="categoria">
                    <?php foreach ($categorias as $clave => $etiqueta): ?>
                        <option value="<?= e($clave) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field-help">Es la que usan los filtros de la ficha pública.</p>
            </div>

            <div class="field">
                <label for="alt">Texto alternativo</label>
                <input id="alt" name="alt" type="text" maxlength="200"
                       placeholder="Automático si se deja vacío">
                <p class="field-help">Descripción para buscadores y lectores de pantalla.</p>
            </div>
        </div>

        <div class="ag-form-actions">
            <button class="btn btn-primary" type="submit">Subir a la galería</button>
            <span class="field-help">
                <?= count($fotos) ?> de 20 fotografías usadas · se guardan en
                <code>public/assets/img/<?= e($c['carpeta']) ?>/</code>
            </span>
        </div>
    </form>
</section>

<section class="box">
    <h3 class="box-title">
        Fotografías
        <span class="count-badge"><?= count($fotos) ?></span>
    </h3>

    <?php if ($fotos === []): ?>
        <div class="empty-state">
            <p class="empty-title">Este vehículo todavía no tiene galería</p>
            <p>Sube alguna toma arriba o ejecuta <code>php scripts/descargar-galeria.php</code> para material de Commons.</p>
        </div>
    <?php else: ?>
        <div class="admin-gallery">
            <?php foreach ($fotos as $i => $f): ?>
                <?php
                $fotoId = (int) $f['id'];
                $esPrincipal = (int) $f['id'] === $principalId;
                $base = 'admin/coches/' . (int) $c['id'] . '/galeria/fotos/' . $fotoId;
                ?>

                <article class="ag-item<?= $esPrincipal ? ' is-main' : '' ?>">
                    <div class="ag-thumb">
                        <?= imagen((string) $f['ruta'], (string) $f['alt'],
                            ['width' => 640, 'height' => 400, 'loading' => 'lazy']) ?>

                        <?php if ($esPrincipal): ?>
                            <span class="ag-badge">Principal</span>
                        <?php endif; ?>

                        <span class="ag-num" aria-hidden="true"><?= $i + 1 ?></span>
                    </div>

                    <div class="ag-body">
                        <p class="ag-title"><?= e($f['alt']) ?></p>

                        <p class="ag-meta">
                            <?= e($f['autor'] ?: 'Sin autor') ?>
                            <?php if (!empty($f['licencia'])): ?>
                                <span aria-hidden="true">·</span> <?= e($f['licencia']) ?>
                            <?php endif; ?>
                            <?php if (!empty($f['ancho'])): ?>
                                <span aria-hidden="true">·</span>
                                <?= (int) $f['ancho'] ?>×<?= (int) $f['alto'] ?> px
                            <?php endif; ?>
                            <?php if (!empty($f['peso_kb'])): ?>
                                <span aria-hidden="true">·</span> <?= (int) $f['peso_kb'] ?> KB
                            <?php endif; ?>
                        </p>

                        <div class="ag-actions">
                            <form class="inline-form" method="post"
                                  action="<?= url($base . '/categoria') ?>">
                                <?= csrf_field() ?>
                                <label class="sr-only" for="cat-<?= $fotoId ?>">Categoría</label>
                                <select id="cat-<?= $fotoId ?>" name="categoria">
                                    <?php foreach ($categorias as $clave => $etiqueta): ?>
                                        <option value="<?= e($clave) ?>"
                                            <?= (string) ($f['categoria'] ?? '') === $clave ? 'selected' : '' ?>>
                                            <?= e($etiqueta) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-ghost btn-sm" type="submit">Guardar</button>
                            </form>

                            <?php if ($esPrincipal): ?>
                                <span class="state is-info">es la principal</span>
                            <?php else: ?>
                                <form class="inline-form" method="post"
                                      action="<?= url($base . '/principal') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm" type="submit">Hacer principal</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$esPrincipal): ?>
                                <div class="ag-move">
                                    <form method="post" action="<?= url($base . '/orden') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delta" value="arriba">
                                        <button class="btn btn-ghost btn-sm" type="submit"
                                                aria-label="Subir en el orden">↑</button>
                                    </form>
                                    <form method="post" action="<?= url($base . '/orden') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delta" value="abajo">
                                        <button class="btn btn-ghost btn-sm" type="submit"
                                                aria-label="Bajar en el orden">↓</button>
                                    </form>

                                    <form method="post" action="<?= url($base . '/borrar') ?>"
                                          data-confirm="¿Eliminar esta fotografía de la galería?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm is-danger" type="submit">Borrar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="box">
    <h3 class="box-title">
        Vídeos
        <span class="count-badge"><?= count($videos) ?></span>
    </h3>

    <form class="ag-form" method="post"
          action="<?= url('admin/coches/' . (int) $c['id'] . '/galeria/videos') ?>">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="field">
                <label for="video">Enlace de YouTube <span class="req">obligatorio</span></label>
                <input id="video" name="video" type="text" maxlength="300" required
                       placeholder="https://www.youtube.com/watch?v=… o youtu.be/…">
                <p class="field-help">
                    Se valida con YouTube y su miniatura se descarga al sitio: la ficha pública
                    no pide nada a terceros hasta que el visitante pulsa reproducir. Máximo 6.
                </p>
            </div>

            <div class="field ag-form-cta">
                <span class="sr-only">Añadir</span>
                <button class="btn btn-primary" type="submit">Añadir vídeo</button>
            </div>
        </div>
    </form>

    <?php if ($videos === []): ?>
        <div class="empty-state">
            <p class="empty-title">Sin vídeos en esta ficha</p>
            <p>Añade una prueba de dirección, una presentación o un análisis del modelo.</p>
        </div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table table-panel">
                <thead>
                <tr>
                    <th scope="col">Miniatura</th>
                    <th scope="col">Vídeo</th>
                    <th scope="col">Canal</th>
                    <th scope="col">Duración</th>
                    <th scope="col" class="actions-col">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($videos as $v): ?>
                    <?php $baseV = 'admin/coches/' . (int) $c['id'] . '/galeria/videos/' . (int) $v['id']; ?>
                    <tr>
                        <td class="ag-video-thumb">
                            <?php if (!empty($v['miniatura'])): ?>
                                <?= imagen((string) $v['miniatura'], 'Miniatura: ' . (string) $v['titulo'],
                                    ['width' => 240, 'height' => 135, 'loading' => 'lazy']) ?>
                            <?php else: ?>
                                <span class="state is-muted">sin miniatura</span>
                            <?php endif; ?>
                        </td>

                        <td class="cell-main">
                            <span class="cell-title"><?= e($v['titulo']) ?></span>
                            <span class="cell-sub">
                                youtube.com/watch?v=<?= e($v['codigo']) ?>
                            </span>
                        </td>

                        <td><?= e($v['canal'] ?: '—') ?></td>
                        <td><?= !empty($v['duracion']) ? e($v['duracion']) : '—' ?></td>

                        <td class="actions-col">
                            <div class="row-actions">
                                <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
                                   href="https://www.youtube.com/watch?v=<?= e($v['codigo']) ?>">Abrir ↗</a>

                                <form method="post" action="<?= url($baseV . '/orden') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delta" value="arriba">
                                    <button class="btn btn-ghost btn-sm" type="submit"
                                            aria-label="Subir el vídeo">↑</button>
                                </form>

                                <form method="post" action="<?= url($baseV . '/orden') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delta" value="abajo">
                                    <button class="btn btn-ghost btn-sm" type="submit"
                                            aria-label="Bajar el vídeo">↓</button>
                                </form>

                                <form class="inline-form" method="post"
                                      action="<?= url($baseV . '/borrar') ?>"
                                      data-confirm="¿Quitar este vídeo de la ficha?">
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
    <?php endif; ?>
</section>
