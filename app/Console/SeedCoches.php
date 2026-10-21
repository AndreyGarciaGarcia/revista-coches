<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Database;
use App\Core\Str;

/**
 * Datos del catálogo: 30 vehículos con ficha técnica completa.
 *
 * La escritura en BD deja que los disparadores (tr_coches_bi) se
 * encarguen de generar slug, uuid y potencia en kW, de modo que aquí
 * sólo se aportan los datos de negocio.
 */
final class SeedCoches
{
    /** @var callable(string):void|null */
    private $out;

    public function __construct(?callable $out = null)
    {
        $this->out = $out;
    }

    /**
     * @param array<string,int> $marcas slug de marca => id
     */
    public function run(array $marcas): int
    {
        $db = Database::get();
        $creados = 0;

        foreach ($this->datos() as $coche) {
            $marcaSlug = $coche['marca'];
            $marcaId = $marcas[$marcaSlug] ?? 0;
            if ($marcaId === 0) {
                continue;
            }

            $existe = $db->first(
                'SELECT id FROM coches WHERE marca_id = :m AND modelo = :mo AND version = :v',
                ['m' => $marcaId, 'mo' => $coche['modelo'], 'v' => $coche['version']]
            );

            if ($existe !== null) {
                continue;
            }

            $descripcion = $this->describir($coche);

            $db->insert('coches', [
                'uuid'              => '',
                'marca_id'          => $marcaId,
                'modelo'            => $coche['modelo'],
                'version'           => $coche['version'],
                'slug'              => '',
                'categoria'         => $coche['categoria'],
                'anio_desde'        => $coche['anio_desde'],
                'anio_hasta'        => $coche['anio_hasta'] ?? null,
                'estado'            => 'publicado',
                'destacado'         => $coche['destacado'] ?? 0,
                'combustible'       => $coche['combustible'],
                'transmision'       => $coche['transmision'],
                'marchas'           => $coche['marchas'] ?? null,
                'traccion'          => $coche['traccion'],
                'plazas'            => $coche['plazas'] ?? 5,
                'puertas'           => $coche['puertas'] ?? 5,
                'potencia_cv'       => $coche['potencia_cv'],
                'potencia_kw'       => null,
                'par_nm'            => $coche['par_nm'] ?? null,
                'cilindrada_cc'     => $coche['cilindrada_cc'] ?? null,
                'cilindros'         => $coche['cilindros'] ?? null,
                'cilindraje_text'   => $coche['cilindraje_text'] ?? null,
                'aceleracion_0_100' => $coche['aceleracion'] ?? null,
                'vel_maxima_kmh'    => $coche['vel_max'] ?? null,
                'consumo_l100'      => $coche['consumo'] ?? null,
                'consumo_kwh100'    => $coche['kwh100'] ?? null,
                'autonomia_km'      => $coche['autonomia'] ?? null,
                'emisiones_gkm'     => $coche['emisiones'] ?? null,
                'bateria_kwh'       => $coche['bateria'] ?? null,
                'eficiencia'        => $coche['eficiencia'] ?? null,
                'maletero_litros'   => $coche['maletero'] ?? null,
                'largo_mm'          => $coche['largo'] ?? null,
                'ancho_mm'          => $coche['ancho'] ?? null,
                'alto_mm'           => $coche['alto'] ?? null,
                'distancia_ejes_mm' => $coche['ejes'] ?? null,
                'peso_kg'           => $coche['peso'] ?? null,
                'carga_util_kg'     => $coche['carga'] ?? null,
                'precio_nuevo'      => $coche['precio_nuevo'],
                'precio_ocasion'    => $coche['precio_ocasion'],
                'resumen'           => $coche['resumen'],
                'descripcion'       => $descripcion,
                'puntos_fuertes'    => json_encode($coche['fuertes'], JSON_UNESCAPED_UNICODE),
                'puntos_debiles'    => json_encode($coche['debiles'], JSON_UNESCAPED_UNICODE),
                'valoracion'        => $coche['valoracion'],
            ]);

            $cocheId = (int) $db->scalar('SELECT id FROM coches WHERE marca_id = :m AND version = :v', [
                'm' => $marcaId, 'v' => $coche['version'],
            ]);

            if ($cocheId > 0) {
                $this->imagenes($db, $cocheId, (string) $coche['clave']);
                $this->precios($db, $cocheId, (int) $coche['precio_nuevo'], (int) $coche['precio_ocasion']);
                $creados++;
            }
        }

        if ($this->out) {
            ($this->out)("   {$creados} vehículos nuevos");
        }

        return $creados;
    }

    // -----------------------------------------------------------------

    private function imagenes(Database $db, int $cocheId, string $clave): void
    {
        $imagen = $db->first('SELECT id FROM imagenes WHERE ruta LIKE :r', [
            'r' => '%/' . $clave . '.jpg',
        ]);

        if ($imagen === null) {
            return;
        }

        $db->execute(
            'INSERT IGNORE INTO coches_imagenes (coche_id, imagen_id, orden, es_principal)
             VALUES (:c, :i, 0, 1)',
            ['c' => $cocheId, 'i' => (int) $imagen['id']]
        );
    }

    /**
     * Vincula las fotografías de la biblioteca con los vehículos ya
     * existentes. Idempotente: sólo toca los que no tienen imagen.
     *
     * Es necesario porque las fotos se descargan después de crear el
     * catálogo y un sembrador idempotente no vuelve a insertar nada.
     */
    public function vincularImagenes(): int
    {
        $db = Database::get();
        $vinculadas = 0;

        foreach ($this->datos() as $coche) {
            $existe = $db->first(
                'SELECT c.id
                   FROM coches c
                   JOIN marcas m ON m.id = c.marca_id
                  WHERE m.slug = :marca AND c.modelo = :mo AND c.version = :v
                  LIMIT 1',
                ['marca' => $coche['marca'], 'mo' => $coche['modelo'], 'v' => $coche['version']]
            );

            if ($existe === null) {
                continue;
            }

            $cocheId = (int) $existe['id'];
            $antes = $db->count(
                'SELECT COUNT(*) FROM coches_imagenes WHERE coche_id = :c',
                ['c' => $cocheId]
            );

            if ($antes > 0) {
                continue;
            }

            $this->imagenes($db, $cocheId, (string) $coche['clave']);

            $vinculadas += $db->count(
                'SELECT COUNT(*) FROM coches_imagenes WHERE coche_id = :c',
                ['c' => $cocheId]
            ) > $antes ? 1 : 0;
        }

        return $vinculadas;
    }

    /** Seis meses de histórico de precios (base de las evoluciones). */
    private function precios(Database $db, int $cocheId, int $nuevo, int $ocasion): void
    {
        for ($i = 5; $i >= 0; $i--) {
            $fecha = date('Y-m-01', strtotime("-{$i} month"));

            // Ligera pendiente: el mercado de ocasión sube, el 0 km ajusta
            $factorNuevo = 1 + ($i * 0.0025);
            $factorOcasion = 1 - ($i * 0.008);

            $db->execute(
                'INSERT IGNORE INTO precios_historicos (coche_id, periodo, precio_nuevo, precio_ocasion, demanda, unidades)
                 VALUES (:c, :p, :n, :o, :d, :u)',
                [
                    'c' => $cocheId,
                    'p' => $fecha,
                    'n' => (int) round($nuevo * $factorNuevo / 100) * 100,
                    'o' => (int) round($ocasion * $factorOcasion / 100) * 100,
                    'd' => match ($i % 5) {
                        0 => 'media', 1 => 'alta', 2 => 'muy_alta', 3 => 'media', default => 'baja',
                    },
                    'u' => 40 + (($cocheId * 7 + $i * 13) % 120),
                ]
            );
        }
    }

    private function describir(array $c): string
    {
        $ficha = sprintf(
            '%s %s: %d cv, %s de consumo, 0-100 en %s s, %s km/h y maletero de %s litros. '
            . 'Medidas: %s mm de largo, %s mm de anchura y %s mm de distancia entre ejes, con un peso en orden de marcha de %s kg.',
            $c['modelo'],
            $c['version'],
            $c['potencia_cv'],
            isset($c['consumo'])
                ? num((float) $c['consumo'], 1) . ' l/100 km'
                : (isset($c['kwh100']) ? num((float) $c['kwh100'], 1) . ' kWh/100 km' : 'sin datos'),
            isset($c['aceleracion']) ? num((float) $c['aceleracion'], 1) : '—',
            $c['vel_max'] ?? '—',
            $c['maletero'] ?? '—',
            $c['largo'] ?? '—',
            $c['ancho'] ?? '—',
            $c['ejes'] ?? '—',
            $c['peso'] ?? '—'
        );

        return implode("\n\n", array_filter([
            $c['resumen'],
            $c['texto'][0] ?? '',
            $c['texto'][1] ?? '',
            $ficha,
        ]));
    }

    // =================================================================
    //  DATOS
    // =================================================================

    /** @return array<int,array<string,mixed>> */
    private function datos(): array
    {
        return [
            [
                'clave' => 'bmw-serie-3', 'marca' => 'bmw',
                'modelo' => 'Serie 3', 'version' => '320d xDrive 190 cv',
                'categoria' => 'berlina', 'anio_desde' => 2019,
                'combustible' => 'diesel', 'transmision' => 'automatica', 'marchas' => 8,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 190, 'par_nm' => 400, 'cilindrada_cc' => 1995, 'cilindros' => 4,
                'cilindraje_text' => '2.0 Turbo diésel', 'aceleracion' => 7.4, 'vel_max' => 240,
                'consumo' => 5.2, 'emisiones' => 137, 'eficiencia' => 'C',
                'maletero' => 480, 'largo' => 4713, 'ancho' => 1827, 'alto' => 1440,
                'ejes' => 2851, 'peso' => 1610, 'carga' => 530,
                'precio_nuevo' => 52900, 'precio_ocasion' => 33500,
                'valoracion' => 4.6, 'destacado' => 1,
                'resumen' => 'La berlina de referencia en conducción sigue marcando el listón: chasis magistral, habitabilidad amplia y un diésel eficiente que ronda los 5 litros en uso real.',
                'texto' => [
                    'La cuarta generación de la Serie 3 mantiene lo que siempre la ha distinguido: una dirección con el peso justo, un eje trasero comunicativo y un reparto de pesos cercano al 50/50. Sobre el papel puede parecer una frialdad alemana; sobre una carretera de montaña se convierte en el argumento de venta definitivo.',
                    'La versión 320d xDrive sigue siendo la opción racional para quien recorre muchos kilómetros: el bloque de dos litros entrega sus 400 Nm desde apenas 1.750 rpm y admite rodar por encima de los 140 km/h sin exigir más de cinco litros. El cambio automático de ocho relaciones trabaja con fluidez y casi no se hace notar.',
                ],
                'fuertes' => ['Dinámica de referencia en la categoría', 'Consumo real muy contenido', 'Calidad de acabado y materiales', 'Buena conservación de valor'],
                'debiles' => ['Precio de equipamiento opcional elevado', 'Suspensión firme en adoquines', 'Sistema multimedia lento al arrancar'],
            ],
            [
                'clave' => 'bmw-i4', 'marca' => 'bmw',
                'modelo' => 'i4', 'version' => 'eDrive40 340 cv',
                'categoria' => 'berlina', 'anio_desde' => 2021,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'trasera', 'puertas' => 5,
                'potencia_cv' => 340, 'par_nm' => 430, 'aceleracion' => 5.5, 'vel_max' => 190,
                'kwh100' => 16.4, 'autonomia' => 521, 'bateria' => 83.9, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 470, 'largo' => 4783, 'ancho' => 1852, 'alto' => 1448,
                'ejes' => 2856, 'peso' => 2050, 'carga' => 440,
                'precio_nuevo' => 56900, 'precio_ocasion' => 38900,
                'valoracion' => 4.7, 'destacado' => 1,
                'resumen' => 'El cupé de cuatro puertas eléctrico de BMW combina 521 km de autonomía homologada con una suspensión trasera multilink que recuerda a la Serie 4.',
                'texto' => [
                    'La i4 es probablemente el coche eléctrico que mejor conduce de su segmento. BMW no ha renunciado al equilibrio del chasis: el peso a batería está bajo el suelo, el centro de gravedad es bajísimo y la distribución casi perfecta se nota en cada curva.',
                    'El bloque eDrive40 entrega 340 cv al eje trasero y admite recargas de hasta 205 kW: de 10 % a 80 % en unos 30 minutos. En autopista el consumo ronda los 18 kWh/100 km, cifra que sostiene una autonomía real superior a los 400 kilómetros.',
                ],
                'fuertes' => ['Autonomía real alta', 'Recarga rápida de 205 kW', 'Calidad de rodadura y aislamiento', 'Maletero de gran volumen'],
                'debiles' => ['Peso en orden de marcha superior a 2 t', 'Pantalla curva con curva de aprendizaje', 'Prestaciones del equipamiento opcional'],
            ],
            [
                'clave' => 'mercedes-clase-c', 'marca' => 'mercedes-benz',
                'modelo' => 'Clase C', 'version' => 'C 220 d 4MATIC',
                'categoria' => 'berlina', 'anio_desde' => 2021,
                'combustible' => 'diesel', 'transmision' => 'automatica', 'marchas' => 9,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 194, 'par_nm' => 440, 'cilindrada_cc' => 1993, 'cilindros' => 4,
                'cilindraje_text' => '2.0 OM654', 'aceleracion' => 7.3, 'vel_max' => 238,
                'consumo' => 5.0, 'emisiones' => 132, 'eficiencia' => 'C',
                'maletero' => 455, 'largo' => 4751, 'ancho' => 1820, 'alto' => 1438,
                'ejes' => 2865, 'peso' => 1685, 'carga' => 490,
                'precio_nuevo' => 54400, 'precio_ocasion' => 35900,
                'valoracion' => 4.5, 'destacado' => 0,
                'resumen' => 'La Clase C W206 apuesta por el confort y la tecnología: suspensión adaptativa, salpicadero de 11,9 pulgadas y un aislamiento acústico de referencia.',
                'texto' => [
                    'Mercedes ha construido la C 220 d pensando en quien pasa cuatro horas diarias dentro. El nivel de silencio a 120 km/h es comparable al de berlinas de segmento superior y la suspensión adaptativa filtra casi cualquier junta de expansión sin perder definición.',
                    'El cambio 9G-Tronic de nueve marchas mantiene el régimen bajo en carretera y el bloque OM654 cumple con las normas Euro 6d sin adblue excesivo. El consumo en trayectos largos se queda en torno a cinco litros por cada cien kilómetros.',
                ],
                'fuertes' => ['Confort de marcha excelente', 'Aislamiento acústico', 'Calidad percibida en el habitáculo', 'Equipamiento de asistencia completo'],
                'debiles' => ['Precio final con opciones', 'Volante con poca información', 'Reparto trasero de maletero irregular'],
            ],
            [
                'clave' => 'mercedes-eqe', 'marca' => 'mercedes-benz',
                'modelo' => 'EQE', 'version' => '350+ 292 cv',
                'categoria' => 'berlina', 'anio_desde' => 2022,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'trasera', 'puertas' => 5,
                'potencia_cv' => 292, 'par_nm' => 565, 'aceleracion' => 6.4, 'vel_max' => 180,
                'kwh100' => 15.9, 'autonomia' => 639, 'bateria' => 96.0, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 430, 'largo' => 4946, 'ancho' => 1884, 'alto' => 1503,
                'ejes' => 3120, 'peso' => 2355, 'carga' => 460,
                'precio_nuevo' => 81700, 'precio_ocasion' => 57500,
                'valoracion' => 4.4, 'destacado' => 1,
                'resumen' => 'Con 639 km de autonomía homologada, el EQE 350+ es la berlina eléctrica de mayor alcance del catálogo Mercedes y un vagón sobre ruedas en marcha.',
                'texto' => [
                    'La arquitectura eléctrica permite un habitáculo de proporciones imposibles en un coche de combustión: el interejes supera los tres metros y la planta baja totalmente plana ofrece una espacialidad de lo más generosa.',
                    'El paquete de batería de 96 kWh admite picos de recarga de 170 kW. La cifra homologada de 639 km se queda en torno a los 500 km reales en autovía con clima templado, lo que sigue siendo sobrado para la mayoría de trayectos.',
                ],
                'fuertes' => ['Autonomía de 639 km homologada', 'Confort y silencio', 'Espacio interior', 'Calidad de materiales'],
                'debiles' => ['Peso superior a 2,3 t', 'Precio de compra elevado', 'Largo que penaliza en aparcamientos'],
            ],
            [
                'clave' => 'audi-a4', 'marca' => 'audi',
                'modelo' => 'A4', 'version' => '40 TDI 204 cv quattro',
                'categoria' => 'berlina', 'anio_desde' => 2019,
                'combustible' => 'diesel', 'transmision' => 'automatica', 'marchas' => 7,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 204, 'par_nm' => 400, 'cilindrada_cc' => 1968, 'cilindros' => 4,
                'cilindraje_text' => '2.0 TDI', 'aceleracion' => 7.2, 'vel_max' => 246,
                'consumo' => 5.1, 'emisiones' => 134, 'eficiencia' => 'C',
                'maletero' => 460, 'largo' => 4762, 'ancho' => 1847, 'alto' => 1428,
                'ejes' => 2820, 'peso' => 1645, 'carga' => 500,
                'precio_nuevo' => 49800, 'precio_ocasion' => 31200,
                'valoracion' => 4.4, 'destacado' => 0,
                'resumen' => 'La A4 B9 sigue siendo el caballo de batalla del ejecutivo español: tracción quattro, virtual cockpit y un consumo de autopista imbatible.',
                'texto' => [
                    'El 40 TDI combina 204 cv con el cambio S tronic de siete relaciones y la tracción total. El conjunto funciona con una precisión casi mecánica: nada de tirones ni pausas, sólo progresión constante desde el primer giro.',
                    'El habitáculo es sobrio y bien resuelto. El cuadro virtual de 12,3 pulgadas sigue siendo de los mejores de la categoría y el sistema de información resulta claro desde el primer minuto, algo que no siempre ocurre con las pantallas actuales.',
                ],
                'fuertes' => ['Tracción quattro fiable', 'Cuadro virtual muy claro', 'Consumo en autovía', 'Ergonomía'],
                'debiles' => ['Diseño ya en el final de su ciclo', 'Extras de serie escasos', 'Maletero algo menor que el rival'],
            ],
            [
                'clave' => 'audi-q8-etron', 'marca' => 'audi',
                'modelo' => 'Q8 e-tron', 'version' => '55 quattro 408 cv',
                'categoria' => 'suv', 'anio_desde' => 2023,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 408, 'par_nm' => 664, 'aceleracion' => 5.6, 'vel_max' => 200,
                'kwh100' => 21.0, 'autonomia' => 494, 'bateria' => 114.0, 'emisiones' => 0,
                'eficiencia' => 'B',
                'maletero' => 569, 'largo' => 4915, 'ancho' => 1940, 'alto' => 1659,
                'ejes' => 2928, 'peso' => 2585, 'carga' => 610,
                'precio_nuevo' => 78900, 'precio_ocasion' => 56500,
                'valoracion' => 4.3, 'destacado' => 0,
                'resumen' => 'El SUV eléctrico grande de Audi destaca por su capacidad de carga de 114 kWh, su maletero de 569 litros y la tracción quattro permanente.',
                'texto' => [
                    'El Q8 e-tron renombra y mejora al e-tron original con una batería de 114 kWh y una eficiencia revisada. La suspensión neumática de serie en la versión 55 permite elevar la carrocería para caminos de tierra o bajarla para ganar estabilidad en autopista.',
                    'Los 569 litros de maletero y un techo de remolque de hasta 1.800 kg lo convierten en un eléctrico realmente polivalente: admite familias numerosas, viajes largos y carga útil sin renunciar a la tracción total.',
                ],
                'fuertes' => ['Batería de 114 kWh', 'Tracción quattro', 'Espacio y polivalencia', 'Suspensión neumática'],
                'debiles' => ['Consumo elevado en autopista', 'Peso de 2,5 t', 'Precio de reposición alto'],
            ],
            [
                'clave' => 'vw-golf', 'marca' => 'volkswagen',
                'modelo' => 'Golf', 'version' => '1.5 eTSI 150 cv DSG',
                'categoria' => 'turismo', 'anio_desde' => 2020,
                'combustible' => 'gasolina', 'transmision' => 'dct', 'marchas' => 7,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 150, 'par_nm' => 250, 'cilindrada_cc' => 1498, 'cilindros' => 4,
                'cilindraje_text' => '1.5 TSI ACT', 'aceleracion' => 8.4, 'vel_max' => 220,
                'consumo' => 5.5, 'emisiones' => 126, 'eficiencia' => 'C',
                'maletero' => 381, 'largo' => 4284, 'ancho' => 1789, 'alto' => 1456,
                'ejes' => 2636, 'peso' => 1335, 'carga' => 540,
                'precio_nuevo' => 30700, 'precio_ocasion' => 19400,
                'valoracion' => 4.5, 'destacado' => 1,
                'resumen' => 'El Golf sigue siendo el compacts de referencia: habitáculo bien resuelto, motores eficientes y una reventa que apenas devalúa.',
                'texto' => [
                    'La octava generación apuesta por la digitalización, pero mantiene lo esencial. El 1.5 eTSI combina cilindros desactivables con un sistema suave de 48 voltios que hace el arranque casi imperceptible y ayuda en consumo en ciudad.',
                    'El DSG de siete relaciones es rápido y preciso, y la dirección —aunque algo ligera— se vuelve progresiva con la velocidad. Es un coche difícil de criticar porque cumple en todo lo importante sin excesos.',
                ],
                'fuertes' => ['Versatilidad total', 'Motor 1.5 eTSI eficiente', 'Buena conservación de valor', 'Ergonomía demostrada'],
                'debiles' => ['Acabado de plásticos algo rígido', 'Tacto del volante frío', 'Equipamiento base corto'],
            ],
            [
                'clave' => 'vw-id4', 'marca' => 'volkswagen',
                'modelo' => 'ID.4', 'version' => 'Pro 286 cv',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'trasera', 'puertas' => 5,
                'potencia_cv' => 286, 'par_nm' => 545, 'aceleracion' => 6.7, 'vel_max' => 180,
                'kwh100' => 16.5, 'autonomia' => 527, 'bateria' => 77.0, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 543, 'largo' => 4584, 'ancho' => 1852, 'alto' => 1640,
                'ejes' => 2771, 'peso' => 2124, 'carga' => 560,
                'precio_nuevo' => 44900, 'precio_ocasion' => 27900,
                'valoracion' => 4.4, 'destacado' => 0,
                'resumen' => 'El ID.4 es el SUV eléctrico familiar por excelencia: 527 km de autonomía, 543 litros de maletero y un precio de entrada competitivo.',
                'texto' => [
                    'Volkswagen planteó el ID.4 como el coche eléctrico para todo el mundo, y se acerca. La plataforma MEB deja el suelo plano, la entrada es cómoda y la visibilidad trasera es excelente para el tamaño del vehículo.',
                    'La recarga admite 175 kW en corriente continua, lo que permite pasar del 10 al 80 % en media hora. En ciudad es donde más brilla: el par disponible desde el primer giro y el freno regenerativo hacen la conducción muy cómoda.',
                ],
                'fuertes' => ['Autonomía de 527 km', 'Maletero de 543 litros', 'Entrada y salida cómodas', 'Recarga de 175 kW'],
                'debiles' => ['Sistema de información poco intuitivo', 'Plásticos del salpicadero', 'Luz de marcadores difíciles de ver'],
            ],
            [
                'clave' => 'toyota-corolla', 'marca' => 'toyota',
                'modelo' => 'Corolla', 'version' => '1.8 Hybrid 140 cv',
                'categoria' => 'turismo', 'anio_desde' => 2019,
                'combustible' => 'hibrido', 'transmision' => 'cvt', 'marchas' => 1,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 140, 'par_nm' => 163, 'cilindrada_cc' => 1798, 'cilindros' => 4,
                'cilindraje_text' => '1.8 THS-II', 'aceleracion' => 10.9, 'vel_max' => 175,
                'consumo' => 4.0, 'emisiones' => 92, 'eficiencia' => 'B',
                'maletero' => 343, 'largo' => 4630, 'ancho' => 1790, 'alto' => 1435,
                'ejes' => 2700, 'peso' => 1335, 'carga' => 500,
                'precio_nuevo' => 30650, 'precio_ocasion' => 20900,
                'valoracion' => 4.6, 'destacado' => 1,
                'resumen' => 'Híbrido sin enchufar con un consumo real de cuatro litros, siete años de garantía y una fiabilidad que ha hecho escuela en el segmento.',
                'texto' => [
                    'El sistema híbrido de Toyota funciona sin pensar: el motor eléctrico asiste en ciudad, el térmico se apaga en paradas y la transmisión sin marchas elimina cualquier sacudida. No es el más prestacional, pero es de los más sensatos.',
                    'El consumo real en uso mixto ronda los 4,0 litros sin necesidad de cargar nada. A esto se suma la garantía de siete años o 250.000 kilómetros, que explica por qué la Corolla sigue liderando las listas de fiabilidad europeas.',
                ],
                'fuertes' => ['Consumo real de 4 l/100 km', 'Garantía de 7 años', 'Fiabilidad probada', 'Reventa sólida'],
                'debiles' => ['Aceleración perezosa', 'Aislamiento acústico justo', 'Maletero menor que el de los rivales'],
            ],
            [
                'clave' => 'toyota-rav4', 'marca' => 'toyota',
                'modelo' => 'RAV4', 'version' => '2.5 Hybrid 222 cv AWD-i',
                'categoria' => 'suv', 'anio_desde' => 2019,
                'combustible' => 'hibrido', 'transmision' => 'cvt', 'marchas' => 1,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 222, 'par_nm' => 221, 'cilindrada_cc' => 2487, 'cilindros' => 4,
                'cilindraje_text' => '2.5 A25A-FXS', 'aceleracion' => 8.4, 'vel_max' => 180,
                'consumo' => 5.6, 'emisiones' => 128, 'eficiencia' => 'C',
                'maletero' => 580, 'largo' => 4600, 'ancho' => 1855, 'alto' => 1685,
                'ejes' => 2690, 'peso' => 1655, 'carga' => 600,
                'precio_nuevo' => 40400, 'precio_ocasion' => 26800,
                'valoracion' => 4.5, 'destacado' => 1,
                'resumen' => 'El SUV híbrido más vendido de Europa: 222 cv, tracción total eléctrica y 580 litros de maletero con un consumo contenido para su tamaño.',
                'texto' => [
                    'La quinta generación abandonó la plataforma anterior por la TNGA-K, más rígida y con un centro de gravedad más bajo. El resultado se nota en curva, donde el RAV4 se muestra estable y previsible pese a su altura.',
                    'La versión AWD-i añade un segundo motor eléctrico en el eje trasero que trabaja sin cardán: transmite hasta 300 Nm al eje posterior cuando el sistema detecta falta de agarre. En lodazal y nieve funciona mejor de lo que cabría esperar.',
                ],
                'fuertes' => ['Maletero de 580 litros', 'Tracción total eléctrica', 'Consumo contenido para el tamaño', 'Garantía larga'],
                'debiles' => ['Ruido del motor en aceleración', 'Calidad de sonido del equipo', 'Pintura blanda'],
            ],
            [
                'clave' => 'tesla-model-3', 'marca' => 'tesla',
                'modelo' => 'Model 3', 'version' => 'Long Range AWD 498 cv',
                'categoria' => 'berlina', 'anio_desde' => 2019,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 498, 'par_nm' => 660, 'aceleracion' => 4.4, 'vel_max' => 201,
                'kwh100' => 14.7, 'autonomia' => 629, 'bateria' => 75.0, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 594, 'largo' => 4720, 'ancho' => 1850, 'alto' => 1441,
                'ejes' => 2875, 'peso' => 1828, 'carga' => 540,
                'precio_nuevo' => 47990, 'precio_ocasion' => 28500,
                'valoracion' => 4.7, 'destacado' => 1,
                'resumen' => '629 km de autonomía, 4,4 segundos de 0 a 100 y una red de recarga propia: el Model 3 sigue siendo el baremo contra el que se mide todo lo eléctrico.',
                'texto' => [
                    'La versión High Performance y el restyling han afinado lo que ya era un producto sólido: mayor autonomía, mejor aislamiento y un interior más cuidado. La tracción total con dos motores ofrece una salida limpia y sin dramas.',
                    'El argumento va más allá del coche: la aplicación permite programar la climatización, revisar el estado de carga y localizar el vehículo, y la red Supercharger elimina la planificación de rutas que tanto asusta a los noveles.',
                ],
                'fuertes' => ['Autonomía de 629 km', 'Red de Superchargers', 'Aceleración de 4,4 s', 'Software sobresaliente'],
                'debiles' => ['Acabado interior irregular', 'Todo se controla desde la pantalla', 'Seguros más caros'],
            ],
            [
                'clave' => 'tesla-model-y', 'marca' => 'tesla',
                'modelo' => 'Model Y', 'version' => 'Long Range AWD 450 cv',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 450, 'par_nm' => 493, 'aceleracion' => 5.0, 'vel_max' => 217,
                'kwh100' => 15.7, 'autonomia' => 533, 'bateria' => 75.0, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 854, 'largo' => 4751, 'ancho' => 1921, 'alto' => 1624,
                'ejes' => 2890, 'peso' => 1979, 'carga' => 570,
                'precio_nuevo' => 48990, 'precio_ocasion' => 31900,
                'valoracion' => 4.6, 'destacado' => 1,
                'resumen' => 'El vehículo más vendido del mundo combina 533 km de autonomía con 854 litros de maletero y tecnología de carga de vanguardia.',
                'texto' => [
                    'El Model Y toma la plataforma del Model 3 y la estira hacia arriba. El resultado es un SUV de dimensiones contenidas —4,75 metros— con un habitáculo sorprendente gracias al techo panorámico y a la ausencia de túnel de transmisión.',
                    'La estructura de una sola pieza fundida reduce piezas y mejora la rigidez. En cuanto a recarga, admite hasta 250 kW y aprovecha al máximo la red propia de Superchargers, hoy la más extendida de Europa.',
                ],
                'fuertes' => ['Maletero de 854 litros', 'Recarga de 250 kW', 'Eficiencia por kilómetro', 'Software con actualizaciones'],
                'debiles' => ['Suspensión firme', 'Aislamiento mejorable', 'Dependencia total de la pantalla'],
            ],
            [
                'clave' => 'seat-leon', 'marca' => 'seat',
                'modelo' => 'León', 'version' => '1.5 TSI 150 cv DSG',
                'categoria' => 'turismo', 'anio_desde' => 2020,
                'combustible' => 'gasolina', 'transmision' => 'dct', 'marchas' => 7,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 150, 'par_nm' => 250, 'cilindrada_cc' => 1498, 'cilindros' => 4,
                'cilindraje_text' => '1.5 TSI EVO2', 'aceleracion' => 8.4, 'vel_max' => 218,
                'consumo' => 5.4, 'emisiones' => 123, 'eficiencia' => 'C',
                'maletero' => 380, 'largo' => 4368, 'ancho' => 1800, 'alto' => 1442,
                'ejes' => 2686, 'peso' => 1300, 'carga' => 530,
                'precio_nuevo' => 26900, 'precio_ocasion' => 17200,
                'valoracion' => 4.3, 'destacado' => 0,
                'resumen' => 'El compacto español más equilibrado: misma base que el Golf, precio más contenido y un diseño que se ha animado con la generación actual.',
                'texto' => [
                    'El León comparte plataforma, motores y tecnología con el Golf, pero adopta un carácter más directo. La puesta a punto del chasis es ligeramente más firme y la dirección comunica algo más.',
                    'El 1.5 TSI de 150 cv con cambio DSG es el conjunto más recomendable: mueve el coche con holgura, se muestra elástico en carretera y se queda por debajo de los seis litros en uso mixto real.',
                ],
                'fuertes' => ['Relación precio-equipamiento', 'Motor 1.5 TSI', 'Espacio interior', 'Fácil de mantener'],
                'debiles' => ['Acabado inferior al del Golf', 'Ruido de rodadura', 'Iluminación básica en versiones de entrada'],
            ],
            [
                'clave' => 'cupra-formentor', 'marca' => 'cupra',
                'modelo' => 'Formentor', 'version' => 'VZ 390 cv 4Drive',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'gasolina', 'transmision' => 'dct', 'marchas' => 7,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 390, 'par_nm' => 420, 'cilindrada_cc' => 2497, 'cilindros' => 5,
                'cilindraje_text' => '2.5 TFSI EA855', 'aceleracion' => 4.9, 'vel_max' => 250,
                'consumo' => 8.7, 'emisiones' => 197, 'eficiencia' => 'E',
                'maletero' => 450, 'largo' => 4450, 'ancho' => 1839, 'alto' => 1572,
                'ejes' => 2630, 'peso' => 1695, 'carga' => 470,
                'precio_nuevo' => 55900, 'precio_ocasion' => 37400,
                'valoracion' => 4.8, 'destacado' => 1,
                'resumen' => 'Cinco cilindros turbo, 390 cv y tracción total: el Formentor VZ es el cupé-SUV más divertido de la gama española y un auténtico coche de circuito.',
                'texto' => [
                    'La llegada del cinco cilindros de 2,5 litros convirtió al Formentor VZ en un objeto de deseo. El sonido en vacío y las veintiuna marchas que suena en el tercer tiempo son motivo suficiente para encenderlo.',
                    'Con 4,9 segundos de 0 a 100 km/h y tracción total, el VZ se apoya en el DSG de siete relaciones y un diferencial autobloqueante trasero. En curva es más honesto de lo que su aspecto agresivo sugiere.',
                ],
                'fuertes' => ['Motor de cinco cilindros', 'Prestaciones de 4,9 s', 'Sonido y carácter', 'Diseño llamativo'],
                'debiles' => ['Consumo elevado', 'Frenada muy exigente', 'Poco práctico con cuatro adultos'],
            ],
            [
                'clave' => 'cupra-born', 'marca' => 'cupra',
                'modelo' => 'Born', 'version' => 'e-Boost 231 cv 58 kWh',
                'categoria' => 'turismo', 'anio_desde' => 2022,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'trasera', 'puertas' => 5,
                'potencia_cv' => 231, 'par_nm' => 310, 'aceleracion' => 6.6, 'vel_max' => 160,
                'kwh100' => 14.9, 'autonomia' => 420, 'bateria' => 58.0, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 385, 'largo' => 4322, 'ancho' => 1809, 'alto' => 1540,
                'ejes' => 2767, 'peso' => 1805, 'carga' => 470,
                'precio_nuevo' => 36500, 'precio_ocasion' => 23900,
                'valoracion' => 4.4, 'destacado' => 0,
                'resumen' => 'El eléctrico deportivo de Cupra ofrece tracción trasera, un chasis ágil y una autonomía de 420 km con la batería de 58 kWh.',
                'texto' => [
                    'El Born comparte plataforma con el ID.3, pero Cupra ha trabajado la puesta a punto: dirección más directa, suspensión más firme y un reparto de pesos que se agradece en carreteras reviradas.',
                    'La batería de 58 kWh admite recargas de hasta 120 kW y la versión e-Boost libera los 231 cv de forma sostenida. Es de los pocos eléctricos compactos que se divierten de verdad en subida.',
                ],
                'fuertes' => ['Tracción trasera', 'Chasis bien resuelto', 'Precio competitivo', 'Diseño interior'],
                'debiles' => ['Autonomía modesta en autopista', 'Espacio trasero ajustado', 'Pantalla con menús profundos'],
            ],
            [
                'clave' => 'renault-clio', 'marca' => 'renault',
                'modelo' => 'Clio', 'version' => 'E-Tech Full Hybrid 145',
                'categoria' => 'utilitario', 'anio_desde' => 2020,
                'combustible' => 'hibrido', 'transmision' => 'automatica', 'marchas' => 4,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 145, 'par_nm' => 148, 'cilindrada_cc' => 1398, 'cilindros' => 4,
                'cilindraje_text' => '1.6 THS 145', 'aceleracion' => 9.8, 'vel_max' => 175,
                'consumo' => 4.2, 'emisiones' => 96, 'eficiencia' => 'B',
                'maletero' => 301, 'largo' => 4053, 'ancho' => 1798, 'alto' => 1440,
                'ejes' => 2583, 'peso' => 1245, 'carga' => 440,
                'precio_nuevo' => 22400, 'precio_ocasion' => 13900,
                'valoracion' => 4.4, 'destacado' => 0,
                'resumen' => 'La ciudadana por excelencia con sistema híbrido de nueva generación: 4,2 litros de consumo y una calidez de uso difícil de igualar.',
                'texto' => [
                    'La quinta generación del Clio elevó la percepción de calidad un peldaño. Los materiales del salpicadero, las costuras y el sistema multimedia de 9,3 pulgadas dan la sensación de un coche de segmento superior.',
                    'El sistema E-Tech combina un motor térmico de 1,6 litros con dos motores eléctricos y un cambio multimodo sin embrague convencional. En ciudad circula mucho a batería y el consumo real apenas supera los cuatro litros.',
                ],
                'fuertes' => ['Precio de entrada', 'Calidad interior', 'Consumo urbano mínimo', 'Fácil de aparcar'],
                'debiles' => ['Maletero de 301 litros', 'Prestaciones modestas', 'Espacio trasero justo'],
            ],
            [
                'clave' => 'renault-megane', 'marca' => 'renault',
                'modelo' => 'Mégane E-Tech', 'version' => 'EV60 220 cv',
                'categoria' => 'suv', 'anio_desde' => 2022,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 220, 'par_nm' => 300, 'aceleracion' => 7.4, 'vel_max' => 160,
                'kwh100' => 16.0, 'autonomia' => 470, 'bateria' => 60.0, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 440, 'largo' => 4473, 'ancho' => 1864, 'alto' => 1506,
                'ejes' => 2775, 'peso' => 1755, 'carga' => 500,
                'precio_nuevo' => 37900, 'precio_ocasion' => 24500,
                'valoracion' => 4.3, 'destacado' => 0,
                'resumen' => 'El eléctrico de Renault se apoya en la plataforma CMF-EV para ofrecer 470 km de autonomía, un habitáculo diáfano y recargas de 130 kW.',
                'texto' => [
                    'El Mégane E-Tech abandona el nombre del compacto tradicional para apostar por un crossover de líneas suaves y bajo coeficiente aerodinámico. La ausencia de marcos en las puertas y el techo vidriado refuerzan la sensación de amplitud.',
                    'La batería de 60 kWh pesa 300 kg pero se integra en el suelo, rebajando el centro de gravedad. La recarga de 130 kW permite añadir 250 km de autonomía en un descanso de cuarto de hora.',
                ],
                'fuertes' => ['Autonomía de 470 km', 'Habitáculo diáfano', 'Calidad del tacto', 'Buen precio con ayudas'],
                'debiles' => ['Luminosidad de la pantalla en verano', 'Sin versión con tracción total', 'Asiento trasero algo corto'],
            ],
            [
                'clave' => 'peugeot-308', 'marca' => 'peugeot',
                'modelo' => '308', 'version' => 'Hybrid 180 eAT8',
                'categoria' => 'turismo', 'anio_desde' => 2021,
                'combustible' => 'hibrido_enchufable', 'transmision' => 'automatica', 'marchas' => 8,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 180, 'par_nm' => 360, 'cilindrada_cc' => 1499, 'cilindros' => 4,
                'cilindraje_text' => '1.5 PureTech PHEV', 'aceleracion' => 7.6, 'vel_max' => 225,
                'consumo' => 1.5, 'emisiones' => 34, 'eficiencia' => 'A',
                'autonomia' => 60, 'bateria' => 12.4, 'emisiones' => 34,
                'maletero' => 361, 'largo' => 4388, 'ancho' => 1848, 'alto' => 1450,
                'ejes' => 2671, 'peso' => 1605, 'carga' => 470,
                'precio_nuevo' => 34600, 'precio_ocasion' => 22900,
                'valoracion' => 4.3, 'destacado' => 0,
                'resumen' => 'Híbrido enchufable con 60 km de autonomía eléctrica: para quien tiene enchufe en casa, funciona como coche eléctrico de diario y térmico de viaje.',
                'texto' => [
                    'La generación actual del 308 incorpora el i-Cockpit con volante reducido y cuadro elevado. Resulta cuestión de hábito: quienes lo adoptan rara vez vuelven atrás, aunque el primer contacto resulta peculiar.',
                    'El sistema enchufable combina un 1.5 PureTech de 180 cv con un motor eléctrico y una batería de 12,4 kWh. Los 60 km de autonomía eléctrica cubren la inmensa mayoría de trayectos diarios en ciudad.',
                ],
                'fuertes' => ['60 km eléctricos', 'Consumo homologado de 1,5 l', 'Habitáculo característico', 'Buen equipamiento'],
                'debiles' => ['Maletero reducido por la batería', 'Peso con la batería', 'i-Cockpit no es para todos'],
            ],
            [
                'clave' => 'peugeot-3008', 'marca' => 'peugeot',
                'modelo' => '3008', 'version' => 'Hybrid 136 Eat8',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'hibrido', 'transmision' => 'automatica', 'marchas' => 8,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 136, 'par_nm' => 230, 'cilindrada_cc' => 1499, 'cilindros' => 4,
                'cilindraje_text' => '1.5 BlueHDi 136', 'aceleracion' => 10.7, 'vel_max' => 188,
                'consumo' => 5.5, 'emisiones' => 143, 'eficiencia' => 'C',
                'maletero' => 520, 'largo' => 4447, 'ancho' => 1841, 'alto' => 1620,
                'ejes' => 2675, 'peso' => 1495, 'carga' => 560,
                'precio_nuevo' => 36300, 'precio_ocasion' => 21900,
                'valoracion' => 4.2, 'destacado' => 0,
                'resumen' => 'El SUV francés de las ventas masivas ofrece 520 litros de maletero, un diseño afilado y versiones diésel muy contenidas en consumo.',
                'texto' => [
                    'El 3008 ha sabido mantenerse en lo alto de las listas combinando un diseño que ha envejecido bien con una practicidad demostrada. El maletero de 520 litros admite de serie una rueda de repuesto de tamaño completo.',
                    'La versión diésel de 136 cv con cambio automático de ocho marchas es la elección sensata para quien cubre muchos kilómetros: ronda los cinco y medio litros y mantiene las revoluciones bajas en autopista.',
                ],
                'fuertes' => ['Maletero de 520 litros', 'Confort de marcha', 'Consumo diésel', 'Acabado interior'],
                'debiles' => ['Dinámica poco participativa', 'Infotainment lento', 'Peso delantero en curva'],
            ],
            [
                'clave' => 'ford-puma', 'marca' => 'ford',
                'modelo' => 'Puma', 'version' => '1.0 EcoBoost 125 cv',
                'categoria' => 'suv', 'anio_desde' => 2019,
                'combustible' => 'gasolina', 'transmision' => 'manual', 'marchas' => 6,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 125, 'par_nm' => 240, 'cilindrada_cc' => 999, 'cilindros' => 3,
                'cilindraje_text' => '1.0 EcoBoost mHEV', 'aceleracion' => 10.0, 'vel_max' => 192,
                'consumo' => 5.4, 'emisiones' => 123, 'eficiencia' => 'C',
                'maletero' => 456, 'largo' => 4186, 'ancho' => 1805, 'alto' => 1554,
                'ejes' => 2588, 'peso' => 1234, 'carga' => 480,
                'precio_nuevo' => 25700, 'precio_ocasion' => 16400,
                'valoracion' => 4.5, 'destacado' => 1,
                'resumen' => 'Uno de los pocos crossover que se divierten al volante: motor de tres cilindros con tecnología mHEV y un maletero con el suelo dividido en dos niveles.',
                'texto' => [
                    'Ford diseñó el Puma sobre la plataforma del Fiesta y se nota en lo bueno: es ligero, directo y tiene un tacto de coche pequeño pese a su aspecto de SUV. La dirección es rápida y el eje trasero sigue con voluntad.',
                    'El maletero incorpora el MegaBox, una charca estanca de 80 litros bajo el suelo que admite transportar bebidas sin riesgo o enjuagar con manguera. Un detalle ingenioso que ningún rival ofrece.',
                ],
                'fuertes' => ['Dinámica ágil', 'MegaBox en el maletero', 'Consumo contenido', 'Precio razonable'],
                'debiles' => ['Aislamiento acústico mejorable', 'Tercera marcha corta', 'Equipamiento básico en la entrada'],
            ],
            [
                'clave' => 'ford-mach-e', 'marca' => 'ford',
                'modelo' => 'Mustang Mach-E', 'version' => 'Extended Range RWD 294 cv',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'trasera', 'puertas' => 5,
                'potencia_cv' => 294, 'par_nm' => 430, 'aceleracion' => 6.6, 'vel_max' => 180,
                'kwh100' => 17.8, 'autonomia' => 600, 'bateria' => 91.0, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 432, 'largo' => 4713, 'ancho' => 1881, 'alto' => 1624,
                'ejes' => 2971, 'peso' => 2169, 'carga' => 480,
                'precio_nuevo' => 56900, 'precio_ocasion' => 37900,
                'valoracion' => 4.4, 'destacado' => 0,
                'resumen' => 'Seiscentos kilómetros de autonomía con el nombre de Mustang: Ford firma un SUV eléctrico grande, rápido y con un maletero más amplio de lo que su línea deja intuir.',
                'texto' => [
                    'La Mach-E toma el legado del Mustang y lo traslada a un formato más práctico. El resultado no es una pastilla: acelera con contundencia y mantiene una puesta a punto firme, casi deportiva.',
                    'La versión de autonomía extendida alcanza los 600 km homologados. La batería de 91 kWh admite picos de 150 kW y el interior prescinde casi por completo de botones físicos en favor de una pantalla vertical de 15,5 pulgadas.',
                ],
                'fuertes' => ['Autonomía de 600 km', 'Prestaciones', 'Espacio interior', 'Diferenciación de marca'],
                'debiles' => ['Control táctil de todo', 'Consumo en autopista', 'Servicio de red menos extendido'],
            ],
            [
                'clave' => 'hyundai-tucson', 'marca' => 'hyundai',
                'modelo' => 'Tucson', 'version' => '1.6 T-GDi Hybrid 230 cv',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'hibrido', 'transmision' => 'automatica', 'marchas' => 6,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 230, 'par_nm' => 265, 'cilindrada_cc' => 1598, 'cilindros' => 4,
                'cilindraje_text' => '1.6 T-GDi + motor eléctrico', 'aceleracion' => 8.4, 'vel_max' => 193,
                'consumo' => 5.7, 'emisiones' => 130, 'eficiencia' => 'C',
                'maletero' => 577, 'largo' => 4500, 'ancho' => 1865, 'alto' => 1665,
                'ejes' => 2755, 'peso' => 1595, 'carga' => 600,
                'precio_nuevo' => 36900, 'precio_ocasion' => 24700,
                'valoracion' => 4.5, 'destacado' => 0,
                'resumen' => 'Diseño paramétrico, 577 litros de maletero y garantía de cinco años: el Tucson híbrido es una de las propuestas más completas del segmento.',
                'texto' => [
                    'La cuarta generación abandonó las líneas suaves por un lenguaje de cristales y aristas que le da una presencia inusual. Los diurnos ocultos en la parrilla son el detalle que más llama la atención al anochecer.',
                    'El híbrido de 230 cv combina el turboalimentado de 1,6 litros con un motor eléctrico de 44,2 kW. Funciona con naturalidad y el consumo real ronda los seis litros con uso mixto equilibrado.',
                ],
                'fuertes' => ['Garantía de 5 años', 'Maletero de 577 litros', 'Diferenciación estética', 'Equipamiento generoso'],
                'debiles' => ['Diseño polarizante', 'Aislamiento en autopista', 'Cambio automático a veces indeciso'],
            ],
            [
                'clave' => 'hyundai-ioniq5', 'marca' => 'hyundai',
                'modelo' => 'IONIQ 5', 'version' => '77 kWh Premium 229 cv',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'electrico', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'trasera', 'puertas' => 5,
                'potencia_cv' => 229, 'par_nm' => 350, 'aceleracion' => 7.5, 'vel_max' => 185,
                'kwh100' => 16.8, 'autonomia' => 485, 'bateria' => 77.4, 'emisiones' => 0,
                'eficiencia' => 'A',
                'maletero' => 527, 'largo' => 4635, 'ancho' => 1890, 'alto' => 1605,
                'ejes' => 3000, 'peso' => 1985, 'carga' => 540,
                'precio_nuevo' => 47900, 'precio_ocasion' => 31500,
                'valoracion' => 4.7, 'destacado' => 1,
                'resumen' => 'El IONIQ 5 combina una estética retrofuturista con recarga de 800 voltios, una autonomía de 485 km y un habitáculo con el suelo completamente plano.',
                'texto' => [
                    'Sobre la plataforma E-GMP, el IONIQ 5 ofrece un interejes de tres metros en un cuerpo de 4,63 metros. El resultado es un interior convertible con banco deslizante y una mesa plegable que convierte el salpicadero en escritorio.',
                    'La arquitectura de 800 voltios permite recargas de hasta 240 kW: en diez minutos se añaden 100 km de autonomía. Además admite carga bidireccional, de modo que el coche puede alimentar una casa o una tienda de campaña.',
                ],
                'fuertes' => ['Recarga de 800 V / 240 kW', 'Espacio interior', 'Diseño distintivo', 'Carga bidireccional'],
                'debiles' => ['Peso elevado', 'Luz de marcha atrás polémica', 'Autonomía baja en frío'],
            ],
            [
                'clave' => 'kia-niro', 'marca' => 'kia',
                'modelo' => 'Niro', 'version' => '1.6 GDi Hybrid 139 cv',
                'categoria' => 'suv', 'anio_desde' => 2022,
                'combustible' => 'hibrido', 'transmision' => 'automatica', 'marchas' => 6,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 139, 'par_nm' => 265, 'cilindrada_cc' => 1598, 'cilindros' => 4,
                'cilindraje_text' => '1.6 GDi + 32 kW eléctrico', 'aceleracion' => 11.2, 'vel_max' => 170,
                'consumo' => 4.8, 'emisiones' => 109, 'eficiencia' => 'B',
                'maletero' => 427, 'largo' => 4420, 'ancho' => 1825, 'alto' => 1625,
                'ejes' => 2700, 'peso' => 1465, 'carga' => 500,
                'precio_nuevo' => 32500, 'precio_ocasion' => 21400,
                'valoracion' => 4.4, 'destacado' => 0,
                'resumen' => 'El híbrido más prudente de Kia: 4,8 litros de consumo, garantía de siete años y materiales reciclados en un buen número de superficies interiores.',
                'texto' => [
                    'La segunda generación crece en todo sentido y adopta una estética con franjas de cladding que buscan diferenciar las tres versiones de propulsión: híbrido, enchufable y eléctrico comparten carrocería.',
                    'El sistema híbrido de 139 cv trabaja de forma discreta y eficiente. El consumo real en ciudad baja de los cinco litros, y la garantía de siete años ofrece una tranquilidad que el mercado secundario valora mucho.',
                ],
                'fuertes' => ['Consumo de 4,8 l/100 km', 'Garantía de 7 años', 'Comodidad de marcha', 'Materiales reciclados'],
                'debiles' => ['Aceleración lenta', 'Maletero justo', 'Diseño discutido'],
            ],
            [
                'clave' => 'volvo-xc60', 'marca' => 'volvo',
                'modelo' => 'XC60', 'version' => 'B5 AWD 250 cv',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'hibrido_enchufable', 'transmision' => 'automatica', 'marchas' => 8,
                'traccion' => 'cuatro_ruedas', 'puertas' => 5,
                'potencia_cv' => 250, 'par_nm' => 350, 'cilindrada_cc' => 1968, 'cilindros' => 4,
                'cilindraje_text' => '2.0 B5 mHEV', 'aceleracion' => 7.6, 'vel_max' => 180,
                'consumo' => 6.7, 'emisiones' => 152, 'eficiencia' => 'D',
                'maletero' => 505, 'largo' => 4708, 'ancho' => 1902, 'alto' => 1658,
                'ejes' => 2865, 'peso' => 1915, 'carga' => 540,
                'precio_nuevo' => 55400, 'precio_ocasion' => 35800,
                'valoracion' => 4.5, 'destacado' => 0,
                'resumen' => 'El SUV sueco combina equipamiento de seguridad de serie con un interior de materiales nobles y una garantía de seguridad que sigue definiendo la marca.',
                'texto' => [
                    'Volvo entrega el City Safety de serie: frenada de emergencia, detección de peatones, ciclistas y animales, y asistencia de mantenimiento de carril. El XC60 ha obtenido cinco estrellas Euro NCAP con puntuaciones altísimas en protección de adultos.',
                    'El interior evoca un salón nórdico: madera clara, lana de lana y superficies mate. El sistema Sensus, con pantalla vertical de nueve pulgadas, ha mejorado mucho tras las últimas actualizaciones de software.',
                ],
                'fuertes' => ['Seguridad de referencia', 'Calidad del habitáculo', 'Confort en viaje largo', 'Diseño sobrio y duradero'],
                'debiles' => ['Consumo del bloque B5', 'Sensus mejorable', 'Peso alto en las versiones enchufables'],
            ],
            [
                'clave' => 'porsche-911', 'marca' => 'porsche',
                'modelo' => '911', 'version' => 'Carrera S 450 cv',
                'categoria' => 'deportivo', 'anio_desde' => 2019,
                'combustible' => 'gasolina', 'transmision' => 'dct', 'marchas' => 8,
                'traccion' => 'trasera', 'puertas' => 3,
                'potencia_cv' => 450, 'par_nm' => 530, 'cilindrada_cc' => 2981, 'cilindros' => 6,
                'cilindraje_text' => '3.0 Twin-Turbo Boxer', 'aceleracion' => 3.5, 'vel_max' => 308,
                'consumo' => 10.4, 'emisiones' => 237, 'eficiencia' => 'F',
                'maletero' => 132, 'largo' => 4519, 'ancho' => 1852, 'alto' => 1300,
                'ejes' => 2450, 'peso' => 1565, 'carga' => 300,
                'precio_nuevo' => 143000, 'precio_ocasion' => 118000,
                'valoracion' => 4.9, 'destacado' => 1,
                'resumen' => 'La novena generación del 911 conserva el motor boxer biturbo trasero y lo une a un cambio de doble embrague de ocho marchas con 3,5 segundos de 0 a 100.',
                'texto' => [
                    'La generación 992 mantiene la esencia: motor boxer en el eje trasero, chasis mixto de aluminio y acero y una dirección que sigue siendo una referencia. A pesar de crecer en anchura, el 911 se comporta como un coche pequeño.',
                    'El PDK de ocho marchas cambia con una rapidez que roza lo instantáneo y el biturbo de 3,0 litros entrega su par de forma muy lineal. En circuito, el sistema de refrigeración rediseñado admite uso intensivo sin perder potencia.',
                ],
                'fuertes' => ['Prestaciones de superdeportivo', 'Versatilidad diaria', 'Valor de reventa', 'Ingeniería del chasis'],
                'debiles' => ['Precio de mantenimiento', 'Maletero delantero de 132 l', 'Consumo real a dos cifras'],
            ],
            [
                'clave' => 'skoda-octavia', 'marca' => 'skoda',
                'modelo' => 'Octavia', 'version' => 'Combi 2.0 TDI 150 cv DSG',
                'categoria' => 'berlina', 'anio_desde' => 2020,
                'combustible' => 'diesel', 'transmision' => 'dct', 'marchas' => 7,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 150, 'par_nm' => 360, 'cilindrada_cc' => 1968, 'cilindros' => 4,
                'cilindraje_text' => '2.0 TDI EVO', 'aceleracion' => 8.8, 'vel_max' => 228,
                'consumo' => 4.7, 'emisiones' => 123, 'eficiencia' => 'B',
                'maletero' => 640, 'largo' => 4689, 'ancho' => 1829, 'alto' => 1468,
                'ejes' => 2686, 'peso' => 1437, 'carga' => 570,
                'precio_nuevo' => 32300, 'precio_ocasion' => 20300,
                'valoracion' => 4.6, 'destacado' => 1,
                'resumen' => 'Con 640 litros de maletero y 4,7 litros de consumo, la Octavia Combi sigue siendo el familiar más racional del mercado.',
                'texto' => [
                    'La cuarta generación crece cuatro centímetros y aprovecha cada uno para ganar espacio. El maletero de 640 litros admite maletas de cabina en vertical y conserva un hueco subterráneo para objetos pequeños.',
                    'El 2.0 TDI de 150 cv con DSG es una fórmula conocida y muy madura: prestaciones sobradas para el uso familiar, consumo bajo en carretera y una mecánica cuyos costes de mantenimiento están perfectamente dimensionados.',
                ],
                'fuertes' => ['Maletero de 640 litros', 'Consumo de 4,7 litros', 'Espacio para piernas', 'Relación precio-tamaño'],
                'debiles' => ['Interior algo sobrio', 'Sonido de rodadura', 'Diseño demasiado prudente'],
            ],
            [
                'clave' => 'mazda-cx5', 'marca' => 'mazda',
                'modelo' => 'CX-5', 'version' => '2.0 Skyactiv-G 165 cv',
                'categoria' => 'suv', 'anio_desde' => 2017,
                'combustible' => 'gasolina', 'transmision' => 'automatica', 'marchas' => 6,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 165, 'par_nm' => 213, 'cilindrada_cc' => 1997, 'cilindros' => 4,
                'cilindraje_text' => '2.0 Skyactiv-G', 'aceleracion' => 10.5, 'vel_max' => 194,
                'consumo' => 6.9, 'emisiones' => 158, 'eficiencia' => 'D',
                'maletero' => 506, 'largo' => 4575, 'ancho' => 1840, 'alto' => 1690,
                'ejes' => 2700, 'peso' => 1515, 'carga' => 550,
                'precio_nuevo' => 33700, 'precio_ocasion' => 19800,
                'valoracion' => 4.5, 'destacado' => 0,
                'resumen' => 'Uno de los SUV de gasolina más fiables del mercado, con un interior cuidado y una puesta a punto que privilegia la conducción.',
                'texto' => [
                    'Mazda ha renunciado al diésel en Europa y apuesta por motores de gasolina atmosféricos con alta relación de compresión. El 2.0 Skyactiv-G es elástico, silencioso y destaca por su suavidad a bajas revoluciones.',
                    'El CX-5 destaca por la calidad del habitáculo: cuero, costuras y un montaje de primer nivel. La dirección y la suspensión están calibradas con criterio, sin caer en la rigidez injustificada.',
                ],
                'fuertes' => ['Fiabilidad demostrada', 'Calidad interior', 'Tacto de conducción', 'Puesta a punto equilibrada'],
                'debiles' => ['Consumo de gasolina elevado', 'Espacio trasero regular', 'Equipo multimedia anticuado'],
            ],
            [
                'clave' => 'dacia-duster', 'marca' => 'dacia',
                'modelo' => 'Duster', 'version' => '1.3 TCe 150 cv 4x2',
                'categoria' => 'suv', 'anio_desde' => 2018,
                'combustible' => 'gasolina', 'transmision' => 'manual', 'marchas' => 6,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 150, 'par_nm' => 250, 'cilindrada_cc' => 1332, 'cilindros' => 4,
                'cilindraje_text' => '1.3 TCe 150', 'aceleracion' => 9.2, 'vel_max' => 205,
                'consumo' => 6.4, 'emisiones' => 145, 'eficiencia' => 'C',
                'maletero' => 445, 'largo' => 4341, 'ancho' => 1804, 'alto' => 1693,
                'ejes' => 2634, 'peso' => 1350, 'carga' => 490,
                'precio_nuevo' => 21400, 'precio_ocasion' => 13700,
                'valoracion' => 4.3, 'destacado' => 1,
                'resumen' => 'La fórmula Dacia sigue funcionando: SUV de aspecto robusto, equipamiento honesto y el precio más bajo de su categoría.',
                'texto' => [
                    'La segunda generación crece, se moderniza y mejora el aislamiento sin renunciar a la propuesta de valor. El Duster es de esos coches que no intentan aparentar lo que no son, y por eso funciona.',
                    'El motor 1.3 TCe de 150 cv, compartido con el grupo Renault, muestra un carácter elástico y un consumo razonable. La versión 4x2 es la más racional; la 4x4 sigue disponible para quien precise tracción real.',
                ],
                'fuertes' => ['Precio imbatible', 'Robustez percibida', 'Coste de mantenimiento', 'Practicidad'],
                'debiles' => ['Calidad de acabado', 'Aislamiento acústico', 'Sistemas de ayuda poco avanzados'],
            ],
            [
                'clave' => 'nissan-qashqai', 'marca' => 'nissan',
                'modelo' => 'Qashqai', 'version' => 'e-Power 190 cv',
                'categoria' => 'suv', 'anio_desde' => 2021,
                'combustible' => 'hibrido', 'transmision' => 'automatica', 'marchas' => 1,
                'traccion' => 'delantera', 'puertas' => 5,
                'potencia_cv' => 190, 'par_nm' => 330, 'cilindrada_cc' => 1497, 'cilindros' => 3,
                'cilindraje_text' => '1.5 e-Power (eléctrico de serie)', 'aceleracion' => 7.9, 'vel_max' => 173,
                'consumo' => 5.3, 'emisiones' => 121, 'eficiencia' => 'B',
                'maletero' => 500, 'largo' => 4445, 'ancho' => 1840, 'alto' => 1625,
                'ejes' => 2665, 'peso' => 1545, 'carga' => 540,
                'precio_nuevo' => 34200, 'precio_ocasion' => 23500,
                'valoracion' => 4.4, 'destacado' => 0,
                'resumen' => 'e-Power hace que el Qashqai se conduzca como un eléctrico sin necesidad de enchufar: el motor de combustión sólo genera electricidad.',
                'texto' => [
                    'La tecnología e-Power invierte la lógica: las ruedas son movidas siempre por un motor eléctrico, mientras que el térmico de 1,5 litros trabaja exclusivamente como generador a régimen constante. La conducción es lineal y silenciosa, como en un eléctrico.',
                    'El resultado es un consumo de 5,3 litros sin necesidad de cargar y sin la ansiedad de autonomía. El Qashqai mantiene su gran virtud: un equipamiento amplio y una ergonomía de manual.',
                ],
                'fuertes' => ['Conducción tipo eléctrico', 'Sin necesidad de enchufar', 'Equipamiento', 'Espacio familiar'],
                'debiles' => ['Poco conocido en el mercado', 'Sin versión con tracción total', 'Prestaciones limitadas'],
            ],
        ];
    }
}
