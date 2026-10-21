<?php
/** Formulario de contacto + información institucional. */
?>
<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Contacto</p>
        <h1 class="page-title">Hablemos</h1>
        <p class="page-lede">
            Redacción, publicidad, colaboraciones o correcciones: escríbenos y te responderemos
            normalmente en menos de 48 horas laborables.
        </p>
    </div>
</section>

<section class="section section-tight">
    <div class="shell contact-grid">

        <div class="contact-form">
            <form class="form" action="<?= url('contacto') ?>" method="post" data-validate novalidate>
                <?= csrf_field() ?>
                <!-- Honeypot invisible: los bots lo rellenan -->
                <input class="sr-only" type="text" name="empresa" tabindex="-1" autocomplete="off" aria-hidden="true">

                <div class="form-row">
                    <div class="field">
                        <label for="nombre">Nombre <span class="req">obligatorio</span></label>
                        <input id="nombre" name="nombre" type="text" required minlength="2" maxlength="120"
                               autocomplete="name" value="<?= e(old('nombre')) ?>"
                               data-error="Escribe tu nombre (mínimo 2 caracteres).">
                        <p class="field-error" hidden></p>
                    </div>

                    <div class="field">
                        <label for="email">Correo electrónico <span class="req">obligatorio</span></label>
                        <input id="email" name="email" type="email" required maxlength="190"
                               autocomplete="email" value="<?= e(old('email')) ?>"
                               data-error="Introduce un correo electrónico válido.">
                        <p class="field-error" hidden></p>
                    </div>
                </div>

                <div class="field">
                    <label for="asunto">Asunto <span class="req">obligatorio</span></label>
                    <select id="asunto" name="asunto" required
                            data-error="Elige el motivo de tu mensaje.">
                        <option value="">Selecciona una opción…</option>
                        <?php foreach ([
                            'Redacción y noticias'   => 'Redacción y noticias',
                            'Corrección de un dato'  => 'Corregir un dato publicado',
                            'Prueba de vehículo'     => 'Propuesta de prueba',
                            'Publicidad y partnerships' => 'Publicidad y colaboraciones',
                            'Soporte técnico'        => 'Problemas con la web',
                            'Otro'                   => 'Otro asunto',
                        ] as $valor => $rotulo): ?>
                            <option value="<?= e($valor) ?>" <?= old('asunto') === $valor ? 'selected' : '' ?>>
                                <?= e($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-error" hidden></p>
                </div>

                <div class="field">
                    <label for="mensaje">Mensaje <span class="req">obligatorio</span></label>
                    <textarea id="mensaje" name="mensaje" rows="7" required minlength="20" maxlength="4000"
                              placeholder="Cuéntanos con detalle…"
                              data-error="El mensaje debe tener entre 20 y 4.000 caracteres."><?= e(old('mensaje')) ?></textarea>
                    <p class="field-help">Mínimo 20 caracteres.</p>
                    <p class="field-error" hidden></p>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary btn-lg" type="submit">Enviar mensaje</button>
                    <p class="form-note">
                        Al enviar aceptas nuestra <a href="<?= url('privacidad') ?>">política de privacidad</a>.
                    </p>
                </div>
            </form>
        </div>

        <aside class="contact-side">
            <section class="side-widget">
                <h2 class="side-title">Otras vías</h2>
                <ul class="contact-list">
                    <li>
                        <span class="contact-label">Redacción</span>
                        <a href="mailto:redaccion@motorpress.dev">redaccion@motorpress.dev</a>
                    </li>
                    <li>
                        <span class="contact-label">Publicidad</span>
                        <a href="mailto:publicidad@motorpress.dev">publicidad@motorpress.dev</a>
                    </li>
                    <li>
                        <span class="contact-label">Soporte</span>
                        <a href="mailto:soporte@motorpress.dev">soporte@motorpress.dev</a>
                    </li>
                </ul>
            </section>

            <section class="side-widget" id="publicidad">
                <h2 class="side-title">Publicidad</h2>
                <p>
                    Trabajamos con formatos nativos, análisis patrocinados y campañas de catálogo.
                    Envíanos tu briefing con objetivos y calendario.
                </p>
            </section>

            <section class="side-widget side-widget-accent">
                <h2 class="side-title">Boletín semanal</h2>
                <p>
                    <strong><?= number_format((int) ($suscriptores ?? 0), 0, ',', '.') ?></strong>
                    personas ya lo reciben. Una vez por semana, sin ruido.
                </p>
                <form class="newsletter is-compact" action="<?= url('newsletter') ?>" method="post" data-newsletter>
                    <?= csrf_field() ?>
                    <label class="sr-only" for="contacto-email">Tu correo</label>
                    <input id="contacto-email" type="email" name="email" required maxlength="190"
                           placeholder="tu@correo.com" autocomplete="email">
                    <input class="sr-only" type="text" name="web" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <button class="btn btn-primary btn-block" type="submit">Suscribirme</button>
                </form>
            </section>
        </aside>
    </div>
</section>
