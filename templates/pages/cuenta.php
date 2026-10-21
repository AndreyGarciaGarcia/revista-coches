<?php
/**
 * Área personal del usuario.
 * Recibe: $usuario, $comentarios, $favoritos
 */

$usuario = $usuario ?? [];
$comentarios = $comentarios ?? [];
$favoritos = $favoritos ?? [];
$nivel = (int) ($usuario['rol_nivel'] ?? 0);
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Mi cuenta</p>
        <h1 class="page-title">Hola, <?= e($usuario['nombre'] ?? '') ?></h1>
        <p class="page-lede">
            <?= e(($usuario['email'] ?? '')) ?> ·
            Rol: <strong><?= e($usuario['rol_nombre'] ?? 'Lector') ?></strong>
            <?php if (!empty($usuario['ultimo_acceso'])): ?>
                · Último acceso: <?= e(readable_time($usuario['ultimo_acceso'], true)) ?>
            <?php endif; ?>
        </p>

        <div class="filter-bar filter-bar-top">
            <?php if ($nivel >= 30): ?>
                <a class="btn btn-primary" href="<?= url('admin') ?>">Abrir el panel</a>
            <?php endif; ?>
            <form class="inline-form" action="<?= url('salir') ?>" method="post" data-confirm="¿Cerrar sesión?">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </div>
</section>

<section class="section section-tight">
    <div class="shell account-grid">

        <section class="account-block">
            <h2 class="block-title">Perfil</h2>

            <form class="form" action="<?= url('cuenta') ?>" method="post" data-validate novalidate>
                <?= csrf_field() ?>

                <div class="form-row">
                    <div class="field">
                        <label for="nombre">Nombre <span class="req">obligatorio</span></label>
                        <input id="nombre" name="nombre" type="text" required minlength="2" maxlength="100"
                               value="<?= e($usuario['nombre'] ?? '') ?>">
                        <p class="field-error" hidden></p>
                    </div>

                    <div class="field">
                        <label for="apellidos">Apellidos</label>
                        <input id="apellidos" name="apellidos" type="text" maxlength="120"
                               value="<?= e($usuario['apellidos'] ?? '') ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="bio">Presentación</label>
                    <textarea id="bio" name="bio" rows="3" maxlength="400"
                              placeholder="Un par de líneas sobre ti y tu coche"><?= e($usuario['bio'] ?? '') ?></textarea>
                    <p class="field-help">Máximo 400 caracteres. Se muestra junto a tus comentarios.</p>
                </div>

                <div class="field">
                    <label for="web">Sitio web</label>
                    <input id="web" name="web" type="url" maxlength="255"
                           value="<?= e($usuario['web'] ?? '') ?>" placeholder="https://…">
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Guardar perfil</button>
                </div>
            </form>
        </section>

        <section class="account-block">
            <h2 class="block-title">Cambiar contraseña</h2>

            <form class="form" action="<?= url('cuenta/password') ?>" method="post" data-validate novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="actual">Contraseña actual <span class="req">obligatorio</span></label>
                    <input id="actual" name="actual" type="password" required maxlength="200"
                           autocomplete="current-password">
                    <p class="field-error" hidden></p>
                </div>

                <div class="field">
                    <label for="nueva">Contraseña nueva <span class="req">obligatorio</span></label>
                    <input id="nueva" name="nueva" type="password" required minlength="10" maxlength="200"
                           autocomplete="new-password" data-mirror="password-meter">
                    <div class="password-meter" data-meter hidden>
                        <span class="password-meter-bar"></span>
                        <span class="password-meter-text">—</span>
                    </div>
                    <ul class="password-rules">
                        <li data-rule="length">10 caracteres o más</li>
                        <li data-rule="case">Mayúscula y minúscula</li>
                        <li data-rule="digit">Al menos un número</li>
                        <li data-rule="symbol">Al menos un símbolo</li>
                        <li data-rule="common">No es una contraseña común</li>
                    </ul>
                    <p class="field-error" hidden></p>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Actualizar contraseña</button>
                </div>
            </form>
        </section>

        <section class="account-block account-block-wide">
            <h2 class="block-title">
                Mis comentarios
                <span class="count-badge"><?= count($comentarios) ?></span>
            </h2>

            <?php if ($comentarios === []): ?>
                <p class="empty-note">Todavía no has comentado ningún artículo.</p>
                <a class="btn btn-ghost btn-sm" href="<?= url('noticias') ?>">Ver las noticias</a>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($comentarios as $c): ?>
                        <li class="activity">
                            <div class="activity-main">
                                <a href="<?= url('noticias/' . $c['slug']) ?>#comentarios">
                                    <?= e(excerpt((string) $c['contenido'], 140)) ?>
                                </a>
                                <p class="activity-meta">
                                    en <strong><?= e($c['titulo']) ?></strong> ·
                                    <?= e(time_ago($c['creado_en'])) ?>
                                </p>
                            </div>

                            <span class="state state-<?= e((string) $c['estado']) ?>">
                                <?= e($c['estado']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($favoritos !== []): ?>
            <section class="account-block account-block-wide">
                <h2 class="block-title">Mis coches guardados</h2>
                <div class="grid grid-cars">
                    <?php foreach ($favoritos as $c): ?>
                        <?= \App\Core\View::partial('partials/card-coche', ['c' => $c]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
