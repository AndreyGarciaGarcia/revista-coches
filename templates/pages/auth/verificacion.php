<?php /** Verificación en dos pasos antes de entrar al panel. */ ?>

<section class="auth">
    <div class="auth-panel">
        <header class="auth-head">
            <p class="section-kicker">Seguridad</p>
            <h1 class="auth-title">Verificación en dos pasos</h1>
            <p class="auth-lede">
                La contraseña es correcta, pero falta un paso. Estás entrando como
                <strong><?= e($email) ?></strong>.
            </p>
        </header>

        <form class="form" action="<?= url('acceso/verificacion') ?>" method="post" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="clave">Clave maestra <span class="req">obligatorio</span></label>
                <input id="clave" name="clave" type="password" required maxlength="200"
                       autocomplete="current-password" autofocus
                       data-error="Introduce la clave maestra.">
                <p class="field-error" hidden></p>
                <p class="field-help">
                    La frase larga que sólo tú tienes (en el <code>.env</code> sólo se guarda su
                    hash: <code>PANEL_CLAVE_HASH</code>).
                </p>
            </div>

            <?php if ($totp): ?>
                <div class="field">
                    <label for="codigo">Código de la app <span class="req">obligatorio</span></label>
                    <input id="codigo" name="codigo" type="text" inputmode="numeric"
                           autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                           placeholder="000000" required
                           data-error="Introduce el código de 6 dígitos.">
                    <p class="field-error" hidden></p>
                    <p class="field-help">El código que muestra tu aplicación de autenticación.</p>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg btn-block" type="submit">Verificar y entrar</button>
            </div>

            <p class="form-note">
                ¿No has sido tú? Vuelve <a href="<?= url('acceso') ?>">al inicio de sesión</a>:
                cada intento fallido queda registrado con su dirección IP.
            </p>
        </form>

        <section class="auth-extra">
            <h2 class="side-title">Qué se comprueba</h2>
            <p class="field-help">
                1 · Contraseña · 2 · Clave maestra
                <?php if ($totp): ?>· 3 · Código del móvil<?php endif; ?>
            </p>
            <p class="field-help">
                Tienes 5 minutos y 5 intentos para completar la verificación; después hay que
                empezar de nuevo con la contraseña.
            </p>
        </section>
    </div>
</section>
