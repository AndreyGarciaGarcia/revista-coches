<?= \App\Core\View::partial('pages/errors/parcial', [
    'codigo'  => 500,
    'titulo'  => 'Algo ha fallado por nuestra parte',
    'texto'   => 'El error se ha registrado en los registros del servidor y lo revisaremos. '
        . 'Vuelve a intentarlo en unos instantes.',
    'mensaje' => $message ?? '',
    'acciones' => [
        ['Reintentar', (string) ($_SERVER['REQUEST_URI'] ?? '/')],
        ['Ir al inicio', url('')],
    ],
]) ?>
