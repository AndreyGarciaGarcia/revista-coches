<?php
/**
 * Moderación de comentarios.
 * Recibe: $resultado, $estado, $estados
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$estado = (string) ($estado ?? '');
$estados = $estados ?? ['pendiente', 'aprobado', 'spam', 'rechazado'];

$pestanas = ['' => 'Todos'] + array_combine($estados, array_map('ucfirst', $estados));

$clase = static fn (string $e): string => match ($e) {
    'aprobado' => 'is-ok',
    'pendiente' => 'is-warn',
    'spam'     => 'is-danger',
    default    => 'is-muted',
};
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Comentarios</h2>
        <p class="panel-sub">
            <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
            comentarios con este filtro · toda moderación queda auditada
        </p>
    </div>

    <a class="btn btn-ghost" href="<?= url('admin/comentarios?estado=pendiente') ?>">
        Ver pendientes
    </a>
</header>

<nav class="tabs" aria-label="Filtrar por estado">
    <?php foreach ($pestanas as $clave => $rotulo): ?>
        <?php
        $href = url('admin/comentarios') . ($clave !== '' ? '?estado=' . $clave : '');
        $activo = $estado === $clave;
        ?>
        <a class="tab<?= $activo ? ' is-active' : '' ?>" href="<?= e($href) ?>"
           <?= $activo ? 'aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($resultado['items'] === []): ?>
    <div class="empty-state">
        <p class="empty-title">No hay comentarios en esta cola</p>
        <p>Cuando llegue alguno aparecerá aquí para su revisión.</p>
        <a class="btn btn-ghost" href="<?= url('noticias') ?>">Ver la web</a>
    </div>
<?php else: ?>
    <ul class="moderation-list">
        <?php foreach ($resultado['items'] as $c): ?>
            <li class="moderation-card">
                <header class="moderation-head">
                    <div class="moderation-who">
                        <?= letra_marca((string) ($c['usuario'] ?? 'Anónimo'), 'is-sm') ?>
                        <div>
                            <strong><?= e((string) ($c['usuario'] ?? 'Comentario anónimo')) ?></strong>
                            <span class="cell-sub">
                                <?= e(readable_time($c['creado_en'], true)) ?>
                                <?php if (!empty($c['moderado_en'])): ?>
                                    · moderado <?= e(time_ago($c['moderado_en'])) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <span class="state <?= $clase((string) $c['estado']) ?>">
                        <?= e((string) $c['estado']) ?>
                    </span>
                </header>

                <blockquote class="moderation-text">
                    <?= nl2br(e((string) $c['contenido'])) ?>
                </blockquote>

                <p class="moderation-source">
                    en
                    <a href="<?= url('noticias/' . $c['noticia_slug']) ?>#comentarios" target="_blank" rel="noopener">
                        <?= e($c['noticia']) ?>
                    </a>
                    <?php if (!empty($c['email'])): ?>
                        · <a href="mailto:<?= e((string) $c['email']) ?>"><?= e((string) $c['email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($c['ip'])): ?>
                        · IP <code><?= e((string) $c['ip']) ?></code>
                    <?php endif; ?>
                </p>

                <div class="moderation-actions">
                    <?php foreach (['aprobado', 'pendiente', 'spam', 'rechazado'] as $nuevo): ?>
                        <?php if ($nuevo === $c['estado']) {
                            continue;
                        } ?>

                        <form class="inline-form"
                              action="<?= url('admin/comentarios/' . $c['id'] . '/moderar') ?>"
                              method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="estado" value="<?= e($nuevo) ?>">

                            <?php if ($nuevo === 'spam' || $nuevo === 'rechazado'): ?>
                                <label class="sr-only" for="motivo-<?= (int) $c['id'] ?>">Motivo</label>
                                <input id="motivo-<?= (int) $c['id'] ?>" type="text" name="motivo"
                                       maxlength="200" placeholder="Motivo (opcional)">
                            <?php endif; ?>

                            <button class="btn btn-sm <?= $nuevo === 'aprobado' ? 'btn-primary' : 'btn-ghost' ?>"
                                    type="submit"
                                    data-confirm="Marcar este comentario como «<?= e($nuevo) ?>»?">
                                <?= e(ucfirst($nuevo)) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], 'admin/comentarios',
        array_filter(['estado' => $estado])) ?>
<?php endif; ?>
