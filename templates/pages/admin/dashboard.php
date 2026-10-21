<?php
/**
 * Resumen del panel: cifras, pendientes y actividad.
 * Recibe: $metricas, $actividad, $borradores, $ultimosCoches, $acciones
 */

$metricas = $metricas ?? [];
$actividad = $actividad ?? [];
$borradores = $borradores ?? ['items' => [], 'total' => 0];
$ultimosCoches = $ultimosCoches ?? ['items' => [], 'total' => 0];
$acciones = $acciones ?? [];

$tiles = [
    ['articulos', 'Artículos', 'admin/noticias', 'bg-blue'],
    ['borradores', 'Borradores', 'admin/noticias?estado=borrador', 'bg-amber'],
    ['coches', 'Vehículos', 'admin/coches', 'bg-green'],
    ['comentarios', 'Comentarios', 'admin/comentarios', 'bg-violet'],
    ['pendientes', 'Por moderar', 'admin/comentarios?estado=pendiente', 'bg-red'],
    ['mensajes', 'Mensajes nuevos', 'admin/mensajes?estado=nuevo', 'bg-cyan'],
    ['usuarios', 'Usuarios', 'admin/usuarios', 'bg-slate'],
    ['suscriptores', 'Suscriptores', '', 'bg-teal'],
];
?>

<section class="panel-grid panel-tiles">
    <?php foreach ($tiles as [$clave, $rotulo, $enlace, $color]): ?>
        <?php $valor = (int) ($metricas[$clave] ?? 0); ?>

        <?php if ($enlace !== ''): ?>
            <a class="tile <?= $color ?>" href="<?= url($enlace) ?>">
        <?php else: ?>
            <div class="tile <?= $color ?>">
        <?php endif; ?>

                <span class="tile-label"><?= e($rotulo) ?></span>
                <strong class="tile-value"><?= number_format($valor, 0, ',', '.') ?></strong>

                <?php if ($enlace !== ''): ?>
                    <span class="tile-arrow" aria-hidden="true">→</span>
                <?php endif; ?>

            <?php if ($enlace !== ''): ?>
            </a>
        <?php else: ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<section class="panel-actions">
    <h2 class="panel-title">Acciones rápidas</h2>

    <div class="action-row">
        <a class="action-card" href="<?= url('admin/noticias/nueva') ?>">
            <span class="action-ico" aria-hidden="true">✎</span>
            <span class="action-text">
                <strong>Nuevo artículo</strong>
                <small>Borrador en blanco</small>
            </span>
        </a>

        <a class="action-card" href="<?= url('admin/coches/nuevo') ?>">
            <span class="action-ico" aria-hidden="true">⛬</span>
            <span class="action-text">
                <strong>Añadir vehículo</strong>
                <small>Ficha técnica completa</small>
            </span>
        </a>

        <a class="action-card" href="<?= url('admin/comentarios?estado=pendiente') ?>">
            <span class="action-ico" aria-hidden="true">✍</span>
            <span class="action-text">
                <strong>Moderar</strong>
                <small><?= (int) ($metricas['pendientes'] ?? 0) ?> comentarios esperando</small>
            </span>
        </a>

        <a class="action-card" href="<?= url('admin/mensajes?estado=nuevo') ?>">
            <span class="action-ico" aria-hidden="true">✉</span>
            <span class="action-text">
                <strong>Bandeja</strong>
                <small><?= (int) ($metricas['mensajes'] ?? 0) ?> mensajes sin abrir</small>
            </span>
        </a>
    </div>
</section>

<section class="panel-cols">

    <div class="panel-col">
        <article class="panel-box">
            <header class="panel-box-head">
                <h2 class="panel-title">Borradores pendientes</h2>
                <a class="panel-link" href="<?= url('admin/noticias?estado=borrador') ?>">Ver todos →</a>
            </header>

            <?php if ($borradores['items'] === []): ?>
                <p class="empty-note">No hay borradores abiertos. Buen trabajo.</p>
            <?php else: ?>
                <ul class="mini-list">
                    <?php foreach ($borradores['items'] as $n): ?>
                        <li>
                            <a href="<?= url('admin/noticias/' . $n['id'] . '/editar') ?>">
                                <?= e(excerpt((string) $n['titulo'], 70)) ?>
                            </a>
                            <span class="mini-meta">
                                <?= e(readable_time($n['creado_en'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="panel-box">
            <header class="panel-box-head">
                <h2 class="panel-title">Últimos vehículos añadidos</h2>
                <a class="panel-link" href="<?= url('admin/coches') ?>">Ver catálogo →</a>
            </header>

            <?php if ($ultimosCoches['items'] === []): ?>
                <p class="empty-note">El catálogo está vacío.</p>
            <?php else: ?>
                <ul class="mini-list">
                    <?php foreach ($ultimosCoches['items'] as $c): ?>
                        <li>
                            <a href="<?= url('admin/coches/' . $c['id'] . '/editar') ?>">
                                <?= e($c['marca'] . ' ' . $c['modelo']) ?>
                            </a>
                            <span class="mini-meta">
                                <?= e($c['estado']) ?> ·
                                <?= !empty($c['precio_nuevo']) ? e(euros((int) $c['precio_nuevo'])) : '—' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </div>

    <div class="panel-col">
        <article class="panel-box">
            <header class="panel-box-head">
                <h2 class="panel-title">Actividad reciente</h2>
                <a class="panel-link" href="<?= url('admin/auditoria') ?>">Auditoría completa →</a>
            </header>

            <?php if ($actividad === []): ?>
                <p class="empty-note">Todavía no hay acciones registradas.</p>
            <?php else: ?>
                <ul class="feed">
                    <?php foreach ($actividad as $a): ?>
                        <li>
                            <span class="feed-dot" aria-hidden="true"></span>
                            <div class="feed-body">
                                <p class="feed-line">
                                    <strong><?= e((string) $a['actor_email']) ?></strong>
                                    <code><?= e((string) $a['accion']) ?></code>
                                    <?php if (!empty($a['entidad'])): ?>
                                        <span class="feed-entity">sobre <?= e((string) $a['entidad']) ?></span>
                                    <?php endif; ?>
                                </p>
                                <time datetime="<?= e((string) $a['created_at']) ?>">
                                    <?= e(time_ago($a['created_at'])) ?>
                                </time>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="panel-box">
            <header class="panel-box-head">
                <h2 class="panel-title">Operaciones más frecuentes</h2>
            </header>

            <?php if ($acciones === []): ?>
                <p class="empty-note">Sin datos de auditoría todavía.</p>
            <?php else: ?>
                <?php $max = max(1, max($acciones)); ?>

                <ul class="bar-list">
                    <?php foreach ($acciones as $accion => $total): ?>
                        <li>
                            <span class="bar-label"><code><?= e($accion) ?></code></span>
                            <span class="bar-track">
                                <span class="bar-fill" style="width: <?= (int) round($total / $max * 100) ?>%"></span>
                            </span>
                            <span class="bar-num"><?= (int) $total ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </div>
</section>
