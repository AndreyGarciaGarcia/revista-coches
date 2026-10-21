<?php
/**
 * Bandeja de mensajes de contacto.
 * Recibe: $resultado, $estado, $estados
 */

$resultado = $resultado ?? ['items' => [], 'total' => 0, 'paginas' => 1, 'pagina' => 1];
$estado = (string) ($estado ?? '');
$estados = $estados ?? ['nuevo', 'en_curso', 'resuelto', 'spam'];

$pestanas = ['' => 'Todos'] + array_combine($estados, [
    'nuevo'     => 'Nuevos',
    'en_curso'  => 'En curso',
    'resuelto'  => 'Resueltos',
    'spam'      => 'Spam',
]);

$clase = static fn (string $e): string => match ($e) {
    'nuevo'    => 'is-danger',
    'en_curso' => 'is-warn',
    'resuelto' => 'is-ok',
    default    => 'is-muted',
};
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Mensajes de contacto</h2>
        <p class="panel-sub">
            <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong>
            mensajes con este filtro
        </p>
    </div>

    <a class="btn btn-ghost" href="<?= url('contacto') ?>" target="_blank" rel="noopener">
        Ver el formulario ↗
    </a>
</header>

<nav class="tabs" aria-label="Filtrar por estado">
    <?php foreach ($pestanas as $clave => $rotulo): ?>
        <?php
        $href = url('admin/mensajes') . ($clave !== '' ? '?estado=' . $clave : '');
        $activo = $estado === $clave;
        ?>
        <a class="tab<?= $activo ? ' is-active' : '' ?>" href="<?= e($href) ?>"
           <?= $activo ? 'aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($resultado['items'] === []): ?>
    <div class="empty-state">
        <p class="empty-title">La bandeja está vacía</p>
        <p>No hay mensajes con este filtro.</p>
    </div>
<?php else: ?>
    <ul class="moderation-list">
        <?php foreach ($resultado['items'] as $m): ?>
            <li class="moderation-card">
                <header class="moderation-head">
                    <div class="moderation-who">
                        <?= letra_marca((string) ($m['nombre'] ?? 'X'), 'is-sm') ?>
                        <div>
                            <strong><?= e((string) ($m['nombre'] ?? 'Sin nombre')) ?></strong>
                            <span class="cell-sub">
                                <a href="mailto:<?= e((string) $m['email']) ?>"><?= e((string) $m['email']) ?></a>
                                · <?= e(readable_time($m['creado_en'], true)) ?>
                            </span>
                        </div>
                    </div>

                    <span class="state <?= $clase((string) $m['estado']) ?>">
                        <?= e((string) $m['estado']) ?>
                    </span>
                </header>

                <p class="moderation-subject">
                    <strong><?= e((string) ($m['asunto'] ?? 'Sin asunto')) ?></strong>
                    <?php if (!empty($m['usuario'])): ?>
                        <span class="tag">Cuenta: <?= e((string) $m['usuario']) ?></span>
                    <?php endif; ?>
                </p>

                <blockquote class="moderation-text">
                    <?= nl2br(e((string) ($m['mensaje'] ?? ''))) ?>
                </blockquote>

                <div class="moderation-actions">
                    <?php foreach (['nuevo', 'en_curso', 'resuelto', 'spam'] as $nuevo): ?>
                        <?php if ($nuevo === $m['estado']) {
                            continue;
                        } ?>

                        <form class="inline-form"
                              action="<?= url('admin/mensajes/' . $m['id'] . '/estado') ?>"
                              method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="estado" value="<?= e($nuevo) ?>">
                            <button class="btn btn-sm <?= $nuevo === 'resuelto' ? 'btn-primary' : 'btn-ghost' ?>"
                                    type="submit">
                                <?= e(str_replace('_', ' ', $nuevo)) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>

                    <a class="btn btn-sm btn-ghost"
                       href="mailto:<?= e((string) $m['email']) ?>?subject=Re:%20<?= e(rawurlencode((string) ($m['asunto'] ?? ''))) ?>">
                        Responder por correo
                    </a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?= pagination((int) $resultado['pagina'], (int) $resultado['paginas'], 'admin/mensajes',
        array_filter(['estado' => $estado])) ?>
<?php endif; ?>
