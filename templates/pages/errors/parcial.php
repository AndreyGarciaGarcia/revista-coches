<?php
/**
 * Base común de las páginas de error.
 * Recibe: $codigo, $titulo, $texto, $mensaje, $acciones (array de [etiqueta, enlace])
 */

$codigo = $codigo ?? 500;
$titulo = $titulo ?? 'Se ha producido un error';
$texto = $texto ?? 'Inténtalo de nuevo en unos instantes.';
$acciones = $acciones ?? [];
$mensaje = trim((string) ($mensaje ?? ''));
?>
<section class="error-page">
    <div class="shell shell-narrow">
        <p class="error-code" aria-hidden="true"><?= (int) $codigo ?></p>

        <h1 class="error-title"><?= e($titulo) ?></h1>

        <p class="error-text"><?= e($texto) ?></p>

        <?php if ($mensaje !== ''): ?>
            <p class="error-detail"><code><?= e($mensaje) ?></code></p>
        <?php endif; ?>

        <?php if ($acciones !== []): ?>
            <div class="cta-row">
                <?php foreach ($acciones as $i => [$etiqueta, $enlace]): ?>
                    <a class="btn <?= $i === 0 ? 'btn-primary' : 'btn-ghost' ?> btn-lg"
                       href="<?= e($enlace) ?>"><?= e($etiqueta) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="error-help">
            ¿Crees que es un error nuestro? Escríbenos en
            <a href="<?= url('contacto') ?>">la página de contacto</a> y lo revisamos.
        </p>
    </div>
</section>
