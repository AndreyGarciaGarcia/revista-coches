<?php
/** Pie del sitio: navegación secundaria, boletín y créditos. */

$year = (int) date('Y');
?>
<footer class="site-footer">
    <div class="shell footer-grid">

        <div class="footer-brand">
            <a class="brand" href="<?= url('') ?>">
                <span class="brand-mark" aria-hidden="true">M</span>
                <span class="brand-name">Motor<em>Press</em></span>
            </a>
            <p class="footer-lede">
                Periodismo de automoción sin humo: pruebas largas, datos verificados
                y un catálogo con ficha técnica de cada modelo.
            </p>

            <form class="newsletter" action="<?= url('newsletter') ?>" method="post" data-newsletter>
                <?= csrf_field() ?>
                <label class="sr-only" for="footer-email">Tu correo electrónico</label>
                <input id="footer-email" type="email" name="email" required maxlength="190"
                       placeholder="tu@correo.com" autocomplete="email">
                <!-- Honeypot: los bots rellenan este campo -->
                <input class="sr-only" type="text" name="web" tabindex="-1" autocomplete="off" aria-hidden="true">
                <button class="btn btn-primary" type="submit">Suscribirme</button>
                <p class="newsletter-note">Boletín semanal. Baja en un clic, sin letra pequeña.</p>
            </form>
        </div>

        <nav class="footer-col" aria-label="Explorar">
            <h2>Explorar</h2>
            <ul>
                <li><a href="<?= url('noticias') ?>">Noticias</a></li>
                <li><a href="<?= url('coches') ?>">Catálogo de coches</a></li>
                <li><a href="<?= url('marcas') ?>">Todas las marcas</a></li>
                <li><a href="<?= url('comparador') ?>">Comparador</a></li>
                <li><a href="<?= url('buscar') ?>">Búsqueda</a></li>
            </ul>
        </nav>

        <nav class="footer-col" aria-label="Servicios">
            <h2>Servicios</h2>
            <ul>
                <li><a href="<?= url('contacto') ?>">Contacto</a></li>
                <li><a href="<?= url('contacto') ?>#publicidad">Publicidad</a></li>
                <?php if (registro_abierto()): ?>
                    <li><a href="<?= url('registro') ?>">Crear cuenta</a></li>
                <?php endif; ?>
                <li><a href="<?= url('acceso') ?>">Iniciar sesión</a></li>
                <li><a href="<?= url('api/v1/salud') ?>">API pública</a></li>
            </ul>
        </nav>

        <nav class="footer-col" aria-label="Legal">
            <h2>Legal</h2>
            <ul>
                <li><a href="<?= url('sobre-nosotros') ?>">Quiénes somos</a></li>
                <li><a href="<?= url('privacidad') ?>">Privacidad</a></li>
                <li><a href="<?= url('aviso-legal') ?>">Aviso legal</a></li>
                <li><a href="<?= url('cookies') ?>">Cookies</a></li>
                <li><a href="<?= url('creditos') ?>">Créditos fotográficos</a></li>
            </ul>
        </nav>
    </div>

    <div class="shell footer-bottom">
        <p>© <?= $year ?> MotorPress. Todos los derechos reservados.</p>
        <p class="footer-meta">
            Fotografías de <a href="<?= url('creditos') ?>">Wikimedia Commons</a>
            bajo licencia libre · PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?> · MySQL 8
        </p>
    </div>
</footer>
