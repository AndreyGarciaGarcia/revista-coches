<?php /** Quiénes somos y cómo trabajamos. */ ?>

<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Quiénes somos</p>
        <h1 class="page-title">Periodismo de automoción, sin humo</h1>
        <p class="page-lede">
            MotorPress nace de una idea sencilla: publicar datos verificables y pruebas largas
            en lugar de notas de prensa reescritas.
        </p>
    </div>
</section>

<section class="section section-tight">
    <div class="shell shell-narrow prose">
        <h2>Cómo trabajamos</h2>

        <p>
            Cada ficha de vehículo se construye a partir de tres fuentes: la documentación técnica
            del fabricante, los valores homologados de consumo y emisiones, y nuestras propias
            mediciones cuando disponemos del coche durante una prueba.
        </p>

        <p>
            Cuando un dato no está confirmado, no se publica. Preferimos marcar una especificación
            como «por confirmar» a rellenar huecos con estimaciones.
        </p>

        <h2>Independencia editorial</h2>

        <p>
            Ninguna marca puede comprar una puntuación. Las colaboraciones comerciales se
            identifican expresamente como tal y no aparecen en la sección de valoraciones.
        </p>

        <h2>El catálogo</h2>

        <p>
            Mantenemos un catálogo con ficha técnica, precios de nuevo y de ocasión, evolución
            histórica de precios y valoraciones. Está pensado para comparar, no para vender:
            por eso mostramos también los puntos débiles de cada modelo.
        </p>

        <h2>Créditos fotográficos</h2>

        <p>
            Las fotografías proceden de Wikimedia Commons y se publican bajo licencias libres.
            Autoría, licencia y enlace de origen de cada imagen están documentados en la
            <a href="<?= url('creditos') ?>">página de créditos</a>.
        </p>

        <h2>Tecnología</h2>

        <p>
            El sitio se construye con PHP 8 y MySQL 8, sin dependencias de terceros en tiempo de
            ejecución: consultas preparadas en todo el recorrido, usuario de base de datos con
            privilegios mínimos, trazas de auditoría y límite de peticiones por IP.
        </p>
    </div>
</section>

<?php if (!empty($metricas)): ?>
    <section class="section section-alt" aria-label="Cifras">
        <div class="shell">
            <ul class="stats">
                <?php foreach ([
                    'modelos'      => 'Modelos documentados',
                    'marcas'       => 'Marcas',
                    'articulos'    => 'Artículos',
                    'suscriptores' => 'Suscriptores',
                ] as $clave => $rotulo): ?>
                    <?php if (isset($metricas[$clave])): ?>
                        <li class="stat">
                            <span class="stat-value"><?= number_format((int) $metricas[$clave], 0, ',', '.') ?></span>
                            <span class="stat-label"><?= e($rotulo) ?></span>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="shell">
        <div class="cta-row">
            <a class="btn btn-primary btn-lg" href="<?= url('contacto') ?>">Contactar con la redacción</a>
            <a class="btn btn-ghost btn-lg" href="<?= url('coches') ?>">Explorar el catálogo</a>
        </div>
    </div>
</section>
