<?php /** Error genérico (cualquier otro código HTTP). */

$codigo = (int) ($status ?? 500);

\App\Core\View::partial('pages/errors/parcial', [
    'codigo'  => $codigo,
    'titulo'  => $titulo ?? 'Se ha producido un error',
    'texto'   => 'No hemos podido completar la petición. Si el problema persiste, '
        . 'avísanos desde la página de contacto.',
    'mensaje' => $message ?? '',
    'acciones' => [
        ['Ir al inicio', url('')],
        ['Contactar', url('contacto')],
    ],
]);
