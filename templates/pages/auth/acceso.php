<?php /** Inicio de sesión. */ ?>

<section class="auth">
    <div class="auth-panel">
        <header class="auth-head">
            <p class="section-kicker">Bienvenido de nuevo</p>
            <h1 class="auth-title">Iniciar sesión</h1>
            <p class="auth-lede">Accede para comentar artículos y guardar tus comparativas.</p>
        </header>

        <form class="form" action="<?= url('acceso') ?>" method="post" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Correo electrónico <span class="req">obligatorio</span></label>
                <input id="email" name="email" type="email" required maxlength="190"
                       autocomplete="username" autofocus value="<?= e(old('email')) ?>"
                       data-error="Introduce tu correo electrónico.">
                <p class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="password">Contraseña <span class="req">obligatorio</span></label>
                <input id="password" name="password" type="password" required maxlength="200"
                       autocomplete="current-password"
                       data-error="Introduce tu contraseña.">
                <p class="field-error" hidden></p>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg btn-block" type="submit">Entrar</button>
            </div>

            <p class="form-note">
                <?php if (!empty($registro)): ?>
                    ¿Todavía no tienes cuenta?
                    <a href="<?= url('registro') ?>">Créala en un minuto</a>.
                <?php else: ?>
                    El registro de cuentas está cerrado.
                <?php endif; ?>
            </p>
        </form>

        <?php if (empty($registro)): ?>
            <section class="auth-extra">
                <h2 class="side-title">Acceso restringido</h2>
                <p class="field-help">
                    Sólo las cuentas habilitadas por el administrador pueden entrar. Si necesitas
                    acceso, solicítalo desde la página de contacto.
                </p>
                <p class="field-help">
                    Las contraseñas fallidas se limitan a 10 intentos cada 5 minutos por dirección IP
                    y 8 por cuenta; el panel añade además clave maestra y código de 6 dígitos.
                </p>
            </section>
        <?php else: ?>
            <section class="auth-extra">
                <h2 class="side-title">Cuentas de demostración</h2>
                <p class="field-help">
                    Administrador: <code>admin@motorpress.dev</code> · <code>Admin2026!</code>
                </p>
                <p class="field-help">
                    Las contraseñas fallidas se limitan a 10 intentos cada 5 minutos por dirección IP
                    y 8 por cuenta.
                </p>
            </section>
        <?php endif; ?>
    </div>
</section>
