<?php
/**
 * Editor de artículo.
 * Recibe: $noticia (null = alta), $categorias, $imagenes
 */

$n = $noticia;
$esNuevo = $n === null;
$categorias = $categorias ?? [];
$imagenes = $imagenes ?? [];

$valor = static fn (string $campo, mixed $defecto = ''): string => e((string) (($n[$campo] ?? $defecto) ?? $defecto));

$acciones = [];
if (!$esNuevo && !empty($n['slug'])) {
    $acciones = [
        ['Ver en la web', url('noticias/' . $n['slug']), 'btn-ghost'],
    ];
}
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg"><?= $esNuevo ? 'Nuevo artículo' : 'Editar artículo' ?></h2>
        <p class="panel-sub">
            <?php if ($esNuevo): ?>
                Se guardará como borrador hasta que lo publiques.
            <?php else: ?>
                Última modificación: <?= e(readable_time($n['actualizado_en'] ?: $n['creado_en'], true)) ?>
                · <?= (int) $n['vistas'] ?> vistas
            <?php endif; ?>
        </p>
    </div>

    <div class="head-actions">
        <?php foreach ($acciones as [$etiqueta, $enlace, $clase]): ?>
            <a class="btn <?= $clase ?>" href="<?= e($enlace) ?>" target="_blank" rel="noopener">
                <?= e($etiqueta) ?> ↗
            </a>
        <?php endforeach; ?>

        <a class="btn btn-ghost" href="<?= url('admin/noticias') ?>">Volver al listado</a>
    </div>
</header>

<form class="form form-panel"
      action="<?= $esNuevo ? url('admin/noticias/nueva') : url('admin/noticias/' . $n['id'] . '/editar') ?>"
      method="post" data-validate novalidate>
    <?= csrf_field() ?>

    <div class="editor-grid">

        <div class="editor-main">
            <div class="field">
                <label for="titulo">Título <span class="req">obligatorio</span></label>
                <input id="titulo" name="titulo" type="text" required minlength="10" maxlength="200"
                       autofocus data-counter data-count-target="titular"
                       value="<?= $valor('titulo') ?>"
                       data-error="El título debe tener entre 10 y 200 caracteres.">
                <p class="field-help">
                    <span data-count="titular"><?= mb_strlen((string) ($n['titulo'] ?? '')) ?></span> / 200
                    caracteres
                </p>
                <p class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="resumen">Resumen</label>
                <textarea id="resumen" name="resumen" rows="3" maxlength="400"
                          data-counter data-count-target="resumen"
                          placeholder="Una o dos frases que enganchen; se usa en las tarjetas y en el SEO."><?= $valor('resumen') ?></textarea>
                <p class="field-help">
                    <span data-count="resumen"><?= mb_strlen((string) ($n['resumen'] ?? '')) ?></span> / 400
                    caracteres · recomendado entre 120 y 160
                </p>
            </div>

            <div class="field">
                <label for="cuerpo">Cuerpo del artículo <span class="req">obligatorio</span></label>

                <div class="editor-toolbar" data-editor-toolbar role="toolbar" aria-label="Formato">
                    <button type="button" data-ins="<h2>Título</h2>" title="Subtítulo">H2</button>
                    <button type="button" data-ins="<h3>Subtítulo</h3>" title="Subtítulo menor">H3</button>
                    <button type="button" data-wrap="<strong>|</strong>" title="Negrita"><b>N</b></button>
                    <button type="button" data-wrap="<em>|</em>" title="Cursiva"><i>I</i></button>
                    <button type="button" data-ins="<p>Escribe aquí el párrafo…</p>" title="Párrafo">P</button>
                    <button type="button" data-ins="<ul>&#10;&lt;li&gt;Elemento&lt;/li&gt;&#10;</ul>" title="Lista">•</button>
                    <button type="button" data-ins="<blockquote>Cita destacada</blockquote>" title="Cita">❝</button>
                    <button type="button" data-ins="<figure>&#10;&lt;img src=&quot;&quot; alt=&quot;&quot;&gt;&#10;&lt;figcaption&gt;Pie de foto&lt;/figcaption&gt;&#10;</figure>"
                            title="Figura con pie">🖼</button>
                </div>

                <textarea id="cuerpo" name="cuerpo" rows="24" required minlength="50" maxlength="200000"
                          data-counter data-count-target="cuerpo"
                          placeholder="&lt;p&gt;Introduce aquí el artículo…&lt;/p&gt;"
                          data-error="El cuerpo debe tener al menos 50 caracteres."><?= $valor('cuerpo') ?></textarea>

                <p class="field-help">
                    Se admite HTML limitado: p, h2, h3, strong, em, ul, ol, li, blockquote, figure,
                    figcaption, img, a, br.
                    <span data-count="cuerpo"><?= mb_strlen((string) ($n['cuerpo'] ?? '')) ?></span> caracteres
                </p>
                <p class="field-error" hidden></p>
            </div>
        </div>

        <aside class="editor-side">

            <section class="box">
                <h3 class="box-title">Publicación</h3>

                <div class="field">
                    <label for="estado">Estado <span class="req">obligatorio</span></label>
                    <select id="estado" name="estado" required>
                        <?php foreach ([
                            'borrador'  => 'Borrador — sólo lo ves tú',
                            'revisado'  => 'Revisado — listo para publicar',
                            'publicado' => 'Publicado — visible para todos',
                            'archivado' => 'Archivado — fuera del circuito',
                        ] as $valorEstado => $rotulo): ?>
                            <option value="<?= e($valorEstado) ?>"
                                <?= (string) ($n['estado'] ?? 'borrador') === $valorEstado ? 'selected' : '' ?>>
                                <?= e($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="check">
                    <input type="checkbox" name="destacada" value="1"
                        <?= !empty($n['destacada']) ? 'checked' : '' ?>>
                    <span>Destacada en portada</span>
                </label>

                <label class="check">
                    <input type="checkbox" name="fijada" value="1"
                        <?= !empty($n['fijada']) ? 'checked' : '' ?>>
                    <span>Fijada al inicio de la lista</span>
                </label>

                <label class="check">
                    <input type="checkbox" name="permitir_comentarios" value="1"
                        <?= ($n['permitir_comentarios'] ?? 1) ? 'checked' : '' ?>>
                    <span>Permitir comentarios</span>
                </label>
            </section>

            <section class="box">
                <h3 class="box-title">Clasificación</h3>

                <div class="field">
                    <label for="categoria_id">Categoría</label>
                    <select id="categoria_id" name="categoria_id">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) ($n['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="imagen_id">Imagen principal</label>

                    <select id="imagen_id" name="imagen_id" data-preview-select
                            data-preview-target="img-preview">
                        <option value="">Sin imagen (se usará un marcador)</option>
                        <?php foreach ($imagenes as $img): ?>
                            <option value="<?= (int) $img['id'] ?>"
                                data-src="<?= e(asset_url((string) $img['ruta'])) ?>"
                                <?= (int) ($n['imagen_id'] ?? 0) === (int) $img['id'] ? 'selected' : '' ?>>
                                <?= e(($img['titulo'] ?: basename((string) $img['ruta']))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="img-preview" id="img-preview" hidden>
                        <img alt="Vista previa de la imagen seleccionada">
                        <p class="img-preview-credit"></p>
                    </div>
                </div>

                <div class="field">
                    <label for="pie_foto">Pie de la fotografía</label>
                    <input id="pie_foto" name="pie_foto" type="text" maxlength="255"
                           value="<?= $valor('pie_foto') ?>"
                           placeholder="Modelo fotografiado · Autor / Licencia">
                </div>
            </section>

            <section class="box box-help">
                <h3 class="box-title">Antes de publicar</h3>
                <ul class="checklist">
                    <li>Título claro y sin clicbait.</li>
                    <li>Resumen entre 120 y 160 caracteres.</li>
                    <li>Cuerpo con al menos 500 caracteres y subtítulos.</li>
                    <li>Imagen con atribución de autoría y licencia.</li>
                    <li>Sin datos técnicos sin fuente.</li>
                </ul>
            </section>

            <div class="form-actions is-stacked">
                <button class="btn btn-primary btn-lg btn-block" type="submit">
                    <?= $esNuevo ? 'Crear artículo' : 'Guardar cambios' ?>
                </button>

                <?php if (!$esNuevo): ?>
                    <a class="btn btn-ghost btn-block" href="<?= url('admin/noticias') ?>">Descartar y volver</a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</form>
