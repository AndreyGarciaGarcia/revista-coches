<?php
/**
 * Panel · Seguridad.
 * Recibe: totpActivo, totpDesde, uri, secreto, claveMaestra, listaBlanca,
 *         registro, inactividad, cuentas
 */

$totpActivo   = (bool) ($totpActivo ?? false);
$totpDesde    = $totpDesde ?? null;
$uri          = (string) ($uri ?? '');
$secreto      = (string) ($secreto ?? '');
$claveOk      = (bool) ($claveMaestra ?? false);
$lista        = (string) ($listaBlanca ?? '');
$registro     = (bool) ($registro ?? false);
$inactividad  = (int) ($inactividad ?? 15);
$cuentas      = $cuentas ?? [];

$activas = array_values(array_filter($cuentas, static fn (array $c): bool => ($c['estado'] ?? '') === 'activo'));
$editan  = array_values(array_filter($activas, static fn (array $c): int => (int) ($c['nivel'] ?? 0) >= 90));
?>

<header class="panel-head">
    <div>
        <h2 class="panel-title is-lg">Seguridad</h2>
        <p class="panel-sub">
            El panel sólo se abre con tres cosas a la vez: la contraseña, la clave maestra
            del <code>.env</code> y el código de 6 dígitos de tu móvil.
        </p>
    </div>
</header>

<div class="editor-grid">

    <div class="editor-main">

        <!-- ---------------------------------------------------------
             Segundo factor
             --------------------------------------------------------- -->
        <section class="box">
            <h3 class="box-title">
                <span class="box-ico" aria-hidden="true">⛨</span>
                Verificación en dos pasos
                <?php if ($totpActivo): ?>
                    <span class="badge-soft" style="margin-left:auto">Activa</span>
                <?php else: ?>
                    <span class="badge-soft is-warn" style="margin-left:auto">Sin configurar</span>
                <?php endif; ?>
            </h3>

            <?php if ($totpActivo): ?>
                <p class="field-help">
                    Activada el <?= e(date('d/m/Y H:i', strtotime((string) $totpDesde))) ?>.
                    Cada vez que entres al panel te pedirá el código que marca tu aplicación
                    de autenticación, además de la clave maestra.
                </p>

                <form class="form" action="<?= url('admin/seguridad/totp/borrar') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="field">
                        <label for="codigo-off">Código actual de la app <span class="req">obligatorio</span></label>
                        <input id="codigo-off" name="codigo" type="text" inputmode="numeric"
                               autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                               placeholder="000000" required data-codigo>
                        <p class="field-help">
                            Se pide el código para demostrar que eres quien tiene el móvil:
                            desactivarlo sin más dejaría el panel con dos factores en vez de tres.
                        </p>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-ghost" type="submit">Desactivar el segundo factor</button>
                    </div>
                </form>

            <?php else: ?>
                <ol class="totp-steps">
                    <li>Abre la app de autenticación de tu móvil (Google Authenticator, Aegis, 1Password…).</li>
                    <li>Escanea este código QR o introduce la clave manualmente.</li>
                    <li>Escribe aquí el código de 6 dígitos que aparece para confirmar el emparejamiento.</li>
                </ol>

                <div class="totp-grid">
                    <div class="totp-qr" data-qr="<?= e($uri) ?>" role="img"
                         aria-label="Código QR con la clave del segundo factor"></div>

                    <div>
                        <p class="field-help">
                            ¿No puedes escanear? Introduce esta clave manualmente en la app:
                        </p>
                        <p class="totp-secret"><?= e($secreto) ?></p>

                        <form class="form" action="<?= url('admin/seguridad/totp') ?>" method="post">
                            <?= csrf_field() ?>

                            <div class="field">
                                <label for="codigo-on">Código de confirmación <span class="req">obligatorio</span></label>
                                <input id="codigo-on" name="codigo" type="text" inputmode="numeric"
                                       autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                                       placeholder="000000" required data-codigo autofocus>
                            </div>

                            <div class="form-actions">
                                <button class="btn btn-primary" type="submit">Activar verificación en dos pasos</button>
                            </div>
                        </form>
                    </div>
                </div>

                <p class="field-help">
                    El secreto se guarda cifrado con AES-256-GCM: quien vuelque la base de datos
                    sin tener también el <code>.env</code> no podrá generar tus códigos.
                </p>
            <?php endif; ?>
        </section>

        <!-- ---------------------------------------------------------
             Cuentas
             --------------------------------------------------------- -->
        <section class="box">
            <h3 class="box-title">
                <span class="box-ico" aria-hidden="true">☺</span>
                Cuentas de la plataforma
            </h3>

            <p class="field-help">
                <?= count($cuentas) ?> cuenta<?= count($cuentas) === 1 ? '' : 's' ?> en total ·
                <?= count($activas) ?> activa<?= count($activas) === 1 ? '' : 's' ?> ·
                <?= count($editan) ?> con capacidad de edición.
                <?= $editan <= 1 ? 'Sólo tú puedes cambiar nada en el sitio.' : '' ?>
            </p>

            <div class="table-scroll">
                <table class="table table-panel">
                    <thead>
                    <tr>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                        <th scope="col">2FA</th>
                        <th scope="col">Último acceso</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cuentas as $c): ?>
                        <?php
                        $activo = ($c['estado'] ?? '') === 'activo';
                        $edita  = (int) ($c['nivel'] ?? 0) >= 90;
                        ?>
                        <tr>
                            <td>
                                <code><?= e($c['email']) ?></code>
                                <?php if ($lista !== '' && strcasecmp($lista, (string) $c['email']) === 0): ?>
                                    <span class="badge-soft">Lista blanca</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($c['rol'] ?? '—') ?></td>
                            <td>
                                <span class="badge-soft <?= $activo ? '' : 'is-off' ?>">
                                    <?= $activo ? 'Activa' : 'Suspendida' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$activo): ?>
                                    —
                                <?php elseif (!empty($c['totp_activado_en'])): ?>
                                    <span class="badge-soft">Activado</span>
                                <?php else: ?>
                                    <span class="badge-soft is-warn">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $c['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime((string) $c['ultimo_acceso']))) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($cuentas === []): ?>
                        <tr><td colspan="5">No se pudieron cargar las cuentas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- -------------------------------------------------------------
         Lateral
         ------------------------------------------------------------- -->
    <aside class="editor-side">
        <div class="form-actions is-stacked">
            <a class="btn btn-ghost btn-block" href="<?= url('admin/auditoria') ?>">
                Revisar la auditoría
            </a>
        </div>

        <section class="box">
            <h3 class="box-title">Barrera de entrada</h3>
            <ul class="checklist">
                <li>Contraseña con Argon2id (64 MB, 4 iteraciones).</li>
                <li>Clave maestra en el <code>.env</code>: <?= $claveOk ? 'configurada' : 'FALTA CONFIGURAR' ?>.</li>
                <li>Segundo factor TOTP: <?= $totpActivo ? 'activo' : 'pendiente de emparejar' ?>.</li>
                <li>Lista blanca de cuentas:
                    <?= $lista !== '' ? '<code>' . e($lista) . '</code>' : 'desactivada' ?>.
                </li>
                <li>Cierre automático tras <?= $inactividad ?> min de inactividad.</li>
                <li>Límite de peticiones al panel por IP y minuto.</li>
                <li>Registro público de cuentas: <?= $registro ? 'ABIERTO' : 'cerrado' ?>.</li>
                <li>Cabeceras: sin iframes ajenos, sin caché, sin referencias.</li>
            </ul>
        </section>

        <section class="box box-help">
            <h3 class="box-title">Cómo se entra al panel</h3>
            <ol class="totp-steps">
                <li>Contraseña en <code>/acceso</code>.</li>
                <li>Clave maestra (+ código TOTP) en la verificación.</li>
                <li>Sesión del panel, que se cierra sola al dejar de usarla.</li>
            </ol>
            <p class="field-help">
                Si pierdes el móvil, la clave maestra sigue abriendo la puerta; si pierdes la
                clave maestra, se regenera desde consola con
                <code>php scripts/panel_clave.php</code>.
            </p>
        </section>

        <?php if (!$claveOk): ?>
            <section class="box box-help">
                <h3 class="box-title">Acción pendiente</h3>
                <p class="field-help">
                    Falta la clave maestra en el <code>.env</code>. Ejecuta
                    <code>php scripts/panel_clave.php</code> para crearla: sin ella, nadie
                    puede completar la verificación.
                </p>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?php if (!$totpActivo && $uri !== ''): ?>
    <script src="<?= asset('js/qrcode.min.js') ?>" defer></script>
    <script src="<?= asset('js/panel-totp.js') ?>" defer></script>
<?php endif; ?>
