<?= \App\Core\View::partial('pages/errors/parcial', [
    'codigo'  => 404,
    'titulo'  => 'Esta página no existe',
    'texto'   => 'La dirección que has solicitado no corresponde a ningún contenido publicado, '
        . 'o el artículo ha dejado de estar disponible.',
    'mensaje' => $message ?? '',
    'acciones' => [
        ['Ir al inicio', url('')],
        ['Buscar en el sitio', url('buscar')],
    ],
]) ?>
