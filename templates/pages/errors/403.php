<?= \App\Core\View::partial('pages/errors/parcial', [
    'codigo'  => 403,
    'titulo'  => 'No tienes acceso a esta zona',
    'texto'   => 'Tu cuenta no tiene los permisos necesarios para ver esta página. '
        . 'Si necesitas acceso, pídeselo al administrador de la publicación.',
    'mensaje' => $message ?? '',
    'acciones' => [
        ['Volver al inicio', url('')],
        ['Ir a mi cuenta', url('cuenta')],
    ],
]) ?>
