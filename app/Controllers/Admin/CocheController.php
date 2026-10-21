<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\AdminRepository;
use App\Repositories\CocheRepository;

/**
 * Gestión del catálogo desde el panel: alta, edición de la ficha
 * técnica, precios y borrado lógico.
 */
final class CocheController extends Controller
{
    private const CATEGORIAS = [
        'turismo', 'berlina', 'suv', 'utilitario', 'deportivo',
        'monovolumen', 'pickup', 'furgoneta', 'coupé', 'descapotable',
    ];

    private const COMBUSTIBLES = [
        'gasolina', 'diesel', 'hibrido', 'hibrido_enchufable', 'electrico', 'gas_natural',
    ];

    private const TRANSMISIONES = ['manual', 'automatica', 'dct', 'cvt'];
    private const TRACCIONES = ['delantera', 'trasera', 'cuatro_ruedas'];
    private const EFICIENCIAS = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

    public function index(Request $request): Response
    {
        $estado = (string) $request->get('estado', '');
        $texto = trim((string) $request->get('q', ''));
        $repo = new CocheRepository();

        return $this->view('pages/admin/coches/index', [
            'title'     => 'Catálogo — Panel',
            'resultado' => $repo->listadoAdmin($this->page(), 15, $estado, $texto),
            'estados'   => $repo->contarPorEstado(),
            'estado'    => $estado,
            'texto'     => $texto,
        ], 'layouts/admin');
    }

    public function crearForm(Request $request): Response
    {
        return $this->formulario(null);
    }

    public function crear(Request $request): Response
    {
        $repo = new CocheRepository();
        $datos = $this->validar($request);

        $id = $repo->crear($datos);
        $this->imagen($request, $id);

        (new AdminRepository())->auditar('coche.crear', 'coches', $id, [
            'modelo' => $datos['modelo'] ?? '',
        ]);

        $this->flash('success', 'Vehículo añadido al catálogo.');

        return $this->redirect(url('admin/coches/' . $id . '/editar'));
    }

    public function editarForm(Request $request): Response
    {
        $id = $request->intParam('id');
        $coche = (new CocheRepository())->porIdAdmin($id);

        if ($coche === null) {
            throw HttpException::notFound('El vehículo no existe.');
        }

        return $this->formulario($coche);
    }

    public function actualizar(Request $request): Response
    {
        $id = $request->intParam('id');
        $repo = new CocheRepository();

        if ($repo->porIdAdmin($id) === null) {
            throw HttpException::notFound('El vehículo no existe.');
        }

        $datos = $this->validar($request);
        $repo->actualizar($id, $datos);
        $this->imagen($request, $id);

        (new AdminRepository())->auditar('coche.editar', 'coches', $id, [
            'modelo' => $datos['modelo'] ?? '',
        ]);

        $this->flash('success', 'Ficha actualizada.');

        return $this->redirect(url('admin/coches/' . $id . '/editar'));
    }

    public function borrar(Request $request): Response
    {
        $id = $request->intParam('id');

        if ((new CocheRepository())->borrar($id)) {
            (new AdminRepository())->auditar('coche.borrar', 'coches', $id);
            $this->flash('success', 'Vehículo eliminado del catálogo (borrado lógico).');
        } else {
            $this->flash('error', 'No se pudo eliminar el vehículo.');
        }

        return $this->redirect(url('admin/coches'));
    }

    // -----------------------------------------------------------------
    //  Formulario
    // -----------------------------------------------------------------

    private function formulario(?array $coche): Response
    {
        $repo = new CocheRepository();
        $imagenActual = 0;

        if ($coche !== null) {
            try {
                $imagenActual = (int) Database::get()->scalar(
                    'SELECT imagen_id FROM coches_imagenes
                      WHERE coche_id = :c AND es_principal = 1
                      ORDER BY orden LIMIT 1',
                    ['c' => $coche['id']]
                );
            } catch (\Throwable) {
                $imagenActual = 0;
            }
        }

        return $this->view('pages/admin/coches/form', [
            'title'      => $coche === null ? 'Nuevo vehículo — Panel' : 'Editar vehículo — Panel',
            'coche'      => $coche,
            'imagenActual' => $imagenActual,
            'marcas'     => $repo->marcas(),
            'imagenes'   => (new AdminRepository())->imagenes('cars'),
            'categorias' => self::CATEGORIAS,
            'combustibles' => self::COMBUSTIBLES,
            'transmisiones' => self::TRANSMISIONES,
            'tracciones' => self::TRACCIONES,
            'eficiencias' => self::EFICIENCIAS,
        ], 'layouts/admin');
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        $reglas = [
            'marca_id'        => 'required|integer',
            'modelo'          => 'required|string|min:2|max:120',
            'version'         => 'sometimes|nullable|string|max:140',
            'categoria'       => 'required|in:' . implode(',', self::CATEGORIAS),
            'combustible'     => 'required|in:' . implode(',', self::COMBUSTIBLES),
            'transmision'     => 'required|in:' . implode(',', self::TRANSMISIONES),
            'traccion'        => 'required|in:' . implode(',', self::TRACCIONES),
            'estado'          => 'required|in:borrador,publicado,archivado',
            'anio_desde'      => 'required|integer|min:1900|max:2100',
            'anio_hasta'      => 'sometimes|nullable|integer|min:1900|max:2100',
            'marchas'         => 'sometimes|nullable|integer|min:0|max:12',
            'plazas'          => 'sometimes|nullable|integer|min:1|max:9',
            'puertas'         => 'sometimes|nullable|integer|min:2|max:7',
            'potencia_cv'     => 'sometimes|nullable|integer|min:1|max:3000',
            'par_nm'          => 'sometimes|nullable|integer|min:1|max:3000',
            'cilindrada_cc'   => 'sometimes|nullable|integer|min:0|max:10000',
            'cilindros'       => 'sometimes|nullable|integer|min:0|max:16',
            'aceleracion_0_100' => 'sometimes|nullable|numeric|min:0.5|max:60',
            'vel_maxima_kmh'  => 'sometimes|nullable|integer|min:20|max:600',
            'consumo_l100'    => 'sometimes|nullable|numeric|min:0|max:60',
            'consumo_kwh100'  => 'sometimes|nullable|numeric|min:0|max:100',
            'autonomia_km'    => 'sometimes|nullable|integer|min:0|max:2000',
            'emisiones_gkm'   => 'sometimes|nullable|integer|min:0|max:800',
            'bateria_kwh'     => 'sometimes|nullable|numeric|min:0|max:300',
            'eficiencia'      => 'sometimes|nullable|in:' . implode(',', self::EFICIENCIAS),
            'maletero_litros' => 'sometimes|nullable|integer|min:0|max:3000',
            'largo_mm'        => 'sometimes|nullable|integer|min:0|max:8000',
            'ancho_mm'        => 'sometimes|nullable|integer|min:0|max:5000',
            'alto_mm'         => 'sometimes|nullable|integer|min:0|max:5000',
            'distancia_ejes_mm' => 'sometimes|nullable|integer|min:0|max:6000',
            'peso_kg'         => 'sometimes|nullable|integer|min:0|max:10000',
            'precio_nuevo'    => 'sometimes|nullable|integer|min:0|max:1000000',
            'precio_ocasion'  => 'sometimes|nullable|integer|min:0|max:1000000',
            'valoracion'      => 'sometimes|nullable|numeric|min:0|max:5',
            'resumen'         => 'sometimes|nullable|string|max:400',
            'descripcion'     => 'sometimes|nullable|string|max:60000',
            'puntos_fuertes'  => 'sometimes|nullable|string|max:4000',
            'puntos_debiles'  => 'sometimes|nullable|string|max:4000',
            'imagen_id'       => 'sometimes|nullable|integer',
        ];

        $datos = Validator::make($request->payload(), $reglas, [
            'marca_id'   => 'La marca',
            'modelo'     => 'El modelo',
            'categoria'  => 'La categoría',
            'anio_desde' => 'El año de inicio',
            'estado'     => 'El estado',
        ])->validated();

        $datos['destacado'] = $request->input('destacado') ? 1 : 0;

        // Columnas NOT NULL con valor por defecto: nunca se envía NULL
        $datos['valoracion'] = $datos['valoracion'] ?? 0;
        $datos['plazas'] = $datos['plazas'] ?? 5;
        $datos['puertas'] = $datos['puertas'] ?? 5;

        // Fortes y débiles llegan como texto (una línea por punto)
        foreach (['puntos_fuertes', 'puntos_debiles'] as $campo) {
            if (!empty($datos[$campo]) && is_string($datos[$campo])) {
                $lineas = array_values(array_filter(array_map('trim', explode("\n", $datos[$campo]))));
                $datos[$campo] = $lineas;
            }
        }

        return $datos;
    }

    /** Asigna la fotografía principal si se ha elegido en el formulario. */
    private function imagen(Request $request, int $cocheId): void
    {
        $imagenId = (int) $request->input('imagen_id', 0);

        if ($imagenId > 0) {
            (new AdminRepository())->asignarImagenCoche($cocheId, $imagenId);
        }
    }
}
