<?php
/**
 * Mensajes flash (una sola lectura, ya consumidos por el controlador).
 * Se pintan justo después de la cabecera para que siempre sean visibles.
 */

$mensajes = $flash ?? [];
if ($mensajes === []) {
    return;
}

$iconos = [
    'success' => '✓',
    'error'   => '!',
    'info'    => 'i',
];
?>
<div class="shell flash-stack" role="status" aria-live="polite">
    <?php foreach ($mensajes as $mensaje): ?>
        <div class="flash flash-<?= e($mensaje['type']) ?>">
            <span class="flash-icon" aria-hidden="true">
                <?= e($iconos[$mensaje['type']] ?? 'i') ?>
            </span>
            <p><?= e($mensaje['message']) ?></p>
            <button class="flash-close" type="button" data-dismiss aria-label="Cerrar aviso">×</button>
        </div>
    <?php endforeach; ?>
</div>
