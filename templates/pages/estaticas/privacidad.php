<?php /** Política de privacidad (RGPD). */ ?>

<section class="page-head">
    <div class="shell">
        <p class="section-kicker">Legal</p>
        <h1 class="page-title">Política de privacidad</h1>
        <p class="page-lede">Última actualización: <?= e(date('d/m/Y')) ?></p>
    </div>
</section>

<section class="section section-tight">
    <div class="shell shell-narrow prose">
        <h2>1. Responsable del tratamiento</h2>
        <p>
            MotorPress es el responsable del tratamiento de los datos personales que facilitas a
            través de este sitio. Puedes contactar con nosotros en
            <a href="mailto:privacidad@motorpress.dev">privacidad@motorpress.dev</a>.
        </p>

        <h2>2. Qué datos tratamos</h2>
        <ul>
            <li><strong>Cuenta de usuario:</strong> nombre, apellidos, correo electrónico y contraseña
                (almacenada con hash Argon2id; jamás en claro).</li>
            <li><strong>Comentarios:</strong> el texto que escribes y, si lo indicas, tu correo.</li>
            <li><strong>Formulario de contacto:</strong> nombre, correo, asunto y mensaje.</li>
            <li><strong>Suscripción al boletín:</strong> correo y, opcionalmente, nombre.</li>
            <li><strong>Datos técnicos:</strong> dirección IP, agente de usuario y registros de
                auditoría de seguridad.</li>
        </ul>

        <h2>3. Para qué los usamos</h2>
        <ul>
            <li>Gestionar tu cuenta y permitirte comentar.</li>
            <li>Responder a los mensajes que nos envías.</li>
            <li>Enviar el boletín que has solicitado.</li>
            <li>Proteger el sitio frente a usos abusivos (límite de intentos de acceso, prevención
                de spam).</li>
            <li>Cumplir obligaciones legales y atender requerimientos de autoridad.</li>
        </ul>

        <h2>4. Base legal</h2>
        <p>
            El tratamiento se basa en tu consentimiento (registro, boletín), en la ejecución del
            contrato (prestación del servicio de cuenta) y en nuestro interés legítimo en mantener
            la seguridad del sitio.
        </p>

        <h2>5. Conservación</h2>
        <p>
            Los datos de la cuenta se conservan mientras esta exista. Los mensajes de contacto se
            mantienen durante un máximo de doce meses. Los registros de auditoría se conservan por
            el tiempo exigible legalmente.
        </p>

        <h2>6. Destinatarios</h2>
        <p>
            No vendemos ni cedemos tus datos a terceros. Únicamente se accede a ellos desde el
            personal autorizado de la publicación, y el sitio no incorpora analíticas de terceros
            ni píxeles de seguimiento publicitario.
        </p>

        <h2>7. Tus derechos</h2>
        <p>
            Puedes ejercer los derechos de acceso, rectificación, supresión, oposición, limitación
            y portabilidad escribiendo a la dirección indicada. Si consideras que el tratamiento no
            se ajusta a la normativa, puedes reclamar ante la Agencia Española de Protección de
            Datos.
        </p>

        <h2>8. Cookies</h2>
        <p>
            Consulta la <a href="<?= url('cookies') ?>">política de cookies</a> para conocer las
            cookies que utiliza este sitio.
        </p>
    </div>
</section>
