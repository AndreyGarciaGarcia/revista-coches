<?= \App\Core\View::partial('pages/errors/parcial', [
    'codigo'  => 429,
    'titulo'  => 'Demasiadas peticiones',
    'texto'   => 'Has superado el número de acciones permitidas en los últimos minutos. '
        . 'Espera un poco antes de volver a intentarlo: así protegemos el sitio para todos.',
    'mensaje' => $message ?? '',
    'acciones' => [
        ['Volver al inicio', url('')],
        ['Ver el catálogo', url('coches')],
    ],
]) ?>
