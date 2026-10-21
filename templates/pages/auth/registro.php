<?php /** Alta de cuenta. */ ?>

<section class="auth">
    <div class="auth-panel">
        <header class="auth-head">
            <p class="section-kicker">Únete</p>
            <h1 class="auth-title">Crear cuenta</h1>
            <p class="auth-lede">
                Comenta artículos, sigue a tus marcas favoritas y guarda el comparador.
            </p>
        </header>

        <form class="form" action="<?= url('registro') ?>" method="post" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="field">
                    <label for="nombre">Nombre <span class="req">obligatorio</span></label>
                    <input id="nombre" name="nombre" type="text" required minlength="2" maxlength="100"
                           autocomplete="given-name" autofocus value="<?= e(old('nombre')) ?>"
                           data-error="Escribe tu nombre.">
                    <p class="field-error" hidden></p>
                </div>

                <div class="field">
                    <label for="apellidos">Apellidos</label>
                    <input id="apellidos" name="apellidos" type="text" maxlength="120"
                           autocomplete="family-name" value="<?= e(old('apellidos')) ?>">
                </div>
            </div>

            <div class="field">
                <label for="email">Correo electrónico <span class="req">obligatorio</span></label>
                <input id="email" name="email" type="email" required maxlength="190"
                       autocomplete="email" value="<?= e(old('email')) ?>"
                       data-error="Introduce un correo electrónico válido.">
                <p class="field-error" hidden></p>
            </div>

            <div class="field">
                <label for="password">Contraseña <span class="req">obligatorio</span></label>
                <input id="password" name="password" type="password" required minlength="10" maxlength="200"
                       autocomplete="new-password" data-mirror="password-meter"
                       data-error="Mínimo 10 caracteres.">
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

            <label class="check check-lg">
                <input type="checkbox" name="acepta" required value="1"
                       data-error="Debes aceptar los términos y la política de privacidad.">
                <span>
                    Acepto las <a href="<?= url('aviso-legal') ?>">condiciones de uso</a> y la
                    <a href="<?= url('privacidad') ?>">política de privacidad</a>.
                </span>
            </label>
            <p class="field-error" hidden></p>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg btn-block" type="submit">Crear mi cuenta</button>
            </div>

            <p class="form-note">
                ¿Ya estás registrado? <a href="<?= url('acceso') ?>">Inicia sesión</a>.
            </p>
        </form>
    </div>
</section>
