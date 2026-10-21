<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Database;
use App\Core\Str;

/**
 * Semilla editorial: 16 artículos redactados, con cuerpo en HTML,
 * categorías, autores, etiquetas y vehículos relacionados.
 *
 * Las fechas se generan hacia atrás desde hoy para que la portada
 * siempre muestre contenido con aspecto de actual.
 */
final class SeedNoticias
{
    /** @var callable(string):void|null */
    private $out;

    public function __construct(?callable $out = null)
    {
        $this->out = $out;
    }

    public function run(): int
    {
        $db = Database::get();
        $creados = 0;

        foreach ($this->datos() as $i => $noticia) {
            $existe = $db->first('SELECT id FROM noticias WHERE titulo = :t', ['t' => $noticia['titulo']]);
            if ($existe !== null) {
                continue;
            }

            $categoria = $db->first('SELECT id FROM categorias WHERE slug = :s', ['s' => $noticia['categoria']]);
            $autor = $db->first('SELECT id FROM usuarios WHERE email = :e', ['e' => $noticia['autor']]);
            $imagen = $db->first('SELECT id FROM imagenes WHERE ruta LIKE :r', [
                'r' => '%/' . $noticia['clave'] . '.jpg',
            ]);

            $fecha = date('Y-m-d H:i:s', strtotime('-' . ($i * 3 + ($i % 4)) . ' days'));

            $id = $db->insert('noticias', [
                'uuid'             => '',
                'titulo'           => $noticia['titulo'],
                'slug'             => '',
                'resumen'          => $noticia['resumen'],
                'cuerpo'           => $noticia['cuerpo'],
                'categoria_id'     => $categoria !== null ? (int) $categoria['id'] : null,
                'autor_id'         => $autor !== null ? (int) $autor['id'] : null,
                'imagen_id'        => $imagen !== null ? (int) $imagen['id'] : null,
                'pie_foto'         => $imagen !== null ? 'Fotografía: Wikimedia Commons' : null,
                'estado'           => $noticia['estado'] ?? 'publicado',
                'destacada'        => $noticia['destacada'] ?? 0,
                'fijada'           => $noticia['fijada'] ?? 0,
                'permitir_comentarios' => 1,
                'vistas'           => $noticia['vistas'] ?? 0,
                'publicado_at'     => $fecha,
                'creado_en'        => $fecha,
            ]);

            if ($id === 0) {
                continue;
            }
            $creados++;

            // Etiquetas
            foreach ($noticia['etiquetas'] ?? [] as $etiqueta) {
                $row = $db->first('SELECT id FROM etiquetas WHERE slug = :s', ['s' => Str::slug($etiqueta)]);
                if ($row === null) {
                    $db->insert('etiquetas', ['nombre' => $etiqueta, 'slug' => Str::slug($etiqueta)]);
                    $row = $db->first('SELECT id FROM etiquetas WHERE slug = :s', ['s' => Str::slug($etiqueta)]);
                }
                if ($row !== null) {
                    $db->execute(
                        'INSERT IGNORE INTO noticias_etiquetas (noticia_id, etiqueta_id) VALUES (:n, :e)',
                        ['n' => $id, 'e' => (int) $row['id']]
                    );
                }
            }

            // Vehículos relacionados
            foreach ($noticia['coches'] ?? [] as $cocheClave) {
                $coche = $db->first('SELECT id FROM coches WHERE slug LIKE :s', ['s' => $cocheClave . '%']);
                if ($coche !== null) {
                    $db->execute(
                        'INSERT IGNORE INTO noticias_coches (noticia_id, coche_id) VALUES (:n, :c)',
                        ['n' => $id, 'c' => (int) $coche['id']]
                    );
                }
            }
        }

        if ($this->out) {
            ($this->out)("   {$creados} artículos nuevos");
        }

        return $creados;
    }

    /**
     * Asigna la fotografía de portada a los artículos que ya existen y
     * completa los vehículos relacionados. Idempotente.
     */
    public function vincularImagenes(): int
    {
        $db = Database::get();
        $vinculadas = 0;

        foreach ($this->datos() as $noticia) {
            $fila = $db->first('SELECT id, imagen_id FROM noticias WHERE titulo = :t LIMIT 1', [
                't' => $noticia['titulo'],
            ]);

            if ($fila === null) {
                continue;
            }

            $noticiaId = (int) $fila['id'];

            if ($fila['imagen_id'] === null) {
                $imagen = $db->first('SELECT id FROM imagenes WHERE ruta LIKE :r', [
                    'r' => '%/' . $noticia['clave'] . '.jpg',
                ]);

                if ($imagen !== null) {
                    $db->update('noticias', [
                        'imagen_id' => (int) $imagen['id'],
                        'pie_foto'  => 'Fotografía: Wikimedia Commons',
                    ], ['id' => $noticiaId]);
                    $vinculadas++;
                }
            }

            foreach ($noticia['coches'] ?? [] as $cocheClave) {
                $coche = $db->first('SELECT id FROM coches WHERE slug LIKE :s LIMIT 1', [
                    's' => $cocheClave . '%',
                ]);

                if ($coche !== null) {
                    $db->execute(
                        'INSERT IGNORE INTO noticias_coches (noticia_id, coche_id) VALUES (:n, :c)',
                        ['n' => $noticiaId, 'c' => (int) $coche['id']]
                    );
                }
            }
        }

        return $vinculadas;
    }

    // =================================================================
    //  ARTÍCULOS
    // =================================================================

    /** @return array<int,array<string,mixed>> */
    private function datos(): array
    {
        return [
            [
                'clave' => 'futuro-electrico',
                'titulo' => 'El futuro es eléctrico: por qué 2026 marca el punto de inflexión',
                'categoria' => 'electricos', 'autor' => 'redaccion@motorpress.dev',
                'destacada' => 1, 'fijada' => 1, 'vistas' => 4820,
                'resumen' => 'Las ventas de vehículos eléctricos superan ya el 20 % en Europa y la red de recarga crece a ritmo de doble dígito. Analizamos qué ha cambiado y qué queda por resolver.',
                'etiquetas' => ['eléctrico', 'autonomía', 'carga rápida', 'sostenibilidad'],
                'coches' => ['tesla-model-3', 'bmw-i4', 'hyundai-ioniq5'],
                'cuerpo' => <<<HTML
<p>Durante una década la movilidad eléctrica convivió con una misma pregunta: <em>¿y si no hay dónde cargar?</em> Esa duda ha perdido buena parte de su fuerza. Según los últimos datos de la red europea de recarga, el número de puntos públicos activos crece a un ritmo superior al 25 % anual y, en los corredores principales, la distancia entre estaciones de alta potencia ya rara vez supera los cincuenta kilómetros.</p>

<h2>Lo que ha cambiado en 2026</h2>
<p>Tres factores explican la aceleración. El primero es la caída del precio de las celdas de batería, que ha permitido ofrecer autonomías superiores a los 500 kilómetros en vehículos por debajo de los 40.000 euros. El segundo es la madurez de las plataformas: ya no hay coches eléctricos "adaptados" de combustión, sino arquitecturas diseñadas desde cero con suelo plano y ejes largos. El tercero, y quizá el menos visible, es que los fabricantes han empezado a tratar la recarga como un servicio propio en lugar de una dependencia de terceros.</p>
<p>Ese último punto es decisivo. Cuando el conductor puede abrir la aplicación, ver en tiempo real la disponibilidad de un cargador, reservarlo y pagar sin tarjeta física, la ansiedad de autonomía deja de ser un problema técnico para convertirse en un problema de experiencia de usuario. Y ese, precisamente, es un problema que sí se resuelve.</p>

<h2>Lo que sigue pendiente</h2>
<p>Aun así, quedan tres frenos claros. El primero es la potencia disponible en la red secundaria: muchos puntos públicos siguen limitados a 50 o 75 kW, insuficientes para los vehículos capaces de aceptar 250. El segundo es el precio de la electricidad en horas punta, que en algunos mercados ha encarecido la recarga pública hasta el punto de acercarse al coste de un diésel eficiente. El tercero es la convivencia entre tecnologías: hidrógeno, e-fuels y biogás seguirán ocupando un espacio real en aplicaciones específicas.</p>
<blockquote>La pregunta ya no es si el coche eléctrico se impone, sino en qué plazo se normaliza en cada segmento del mercado.</blockquote>
<p>Nuestra previsión es directa: en el segmento de turismos y SUV familiares, la propulsión eléctrica será mayoritaria antes de 2030. En furgonetas de reparto, taxis y vehículos de alta kilometraje, esa llegada ya ha ocurrido. En el ámbito del remolque ligero y las largas distancias sin infraestructura, el combustible líquido conservará su papel bastante más tiempo.</p>
HTML,
            ],
            [
                'clave' => 'prueba-bmw-serie-3',
                'titulo' => 'Probamos el BMW Serie 3: el referente alemán sigue marcando el listón',
                'categoria' => 'pruebas', 'autor' => 'autor@motorpress.dev',
                'destacada' => 1, 'vistas' => 3410,
                'resumen' => 'Tres días y algo más de mil kilómetros con la 320d xDrive. Un balance honesto sobre lo que hace bien y dónde se le notan los años de desarrollo.',
                'etiquetas' => ['prueba', 'berlina', 'análisis'],
                'coches' => ['bmw-serie-3', 'audi-a4', 'mercedes-clase-c'],
                'cuerpo' => <<<HTML
<p>Hay coches que se explican solos al volante y otros que necesitan mil páginas de ficha técnica. La tercera generación de la Serie 3 pertenece, sin discusión, al primer grupo. Media hora de conducción bastan para entender por qué sigue siendo la referencia de su categoría.</p>

<h2>Chasis y dirección</h2>
<p>El reparto de pesos cercano al 50/50 no es marketing: se nota en cada cambio de dirección. La dirección eléctrica ha ganado peso y precisión respecto a la generación anterior, y el eje trasero responde con una naturalidad que invita a tomar las curvas con más garra de la que el uso racional aconsejaría. La suspensión adaptativa, de opcional, trabaja en dos registros bien diferenciados: filtrado en modo confort y una firmeza muy contenida en deportivo.</p>
<p>Con la 320d xDrive, el bloque de dos litros entrega 400 Nm desde apenas 1.750 rpm. No es un motor emocional, pero tiene una elásticidad que simplifica la conducción: casi nunca hace falta cambiar. El automático de ocho relaciones ejecuta su trabajo con una pulcritud que roza lo invisible.</p>

<h2>En el día a día</h2>
<p>El habitáculo confirma la línea de calidad percibida que BMW ha mejorado mucho en los últimos años. Los materiales son nobles en las zonas que la mano toca con frecuencia y la ergonomía, aunque dependa de una pantalla central, conserva botones físicos para lo esencial: clima, volumen y accesos rápidos.</p>
<ul>
<li><strong>A favor:</strong> dinámica, consumo real de 5,4 litros, calidad de acabado y conservación de valor.</li>
<li><strong>En contra:</strong> el equipamiento opcional se encarece con rapidez y la suspensión de serie se muestra rígida sobre adoquines.</li>
</ul>
<p>Conclusión: sigue siendo el coche que compraríamos para conducir. Si lo que buscas es la mayor cantidad de equipamiento por euro, la competencia coreana ofrece propuestas más generosas. Si lo que buscas es carretera, la respuesta sale de Múnich.</p>
HTML,
            ],
            [
                'clave' => 'cupra-formentor-vz',
                'titulo' => 'Cupra Formentor VZ: 390 caballos de pura adrenalina',
                'categoria' => 'pruebas', 'autor' => 'redaccion@motorpress.dev',
                'destacada' => 1, 'vistas' => 2890,
                'resumen' => 'Cinco cilindros, tracción total y 4,9 segundos. El VZ más radical de Cupra convierte un crossover familiar en un coche de circuito con matrícula.',
                'etiquetas' => ['deportivo', 'prueba', 'SUV'],
                'coches' => ['cupra-formentor'],
                'cuerpo' => <<<HTML
<p>El Formentor nació como un crossover razonable. Con la llegada del motor de cinco cilindros y 390 cv, la razón dejó de ser su principal atributo. Lo que tenemos delante es, ante todo, un placer bien justificado.</p>

<h2>El sonido como argumento</h2>
<p>El 2.5 TFSI de origen Audi es una pieza musical. Las veintiuna marchas que suena al tercer tiempo son inconfundibles y Cupra ha trabajado la válvula de escape para que el sonido llegue al habitáculo sin resultar estridente. En vacío, con la puerta abierta y el ralentí caliente, es de las pocas cosas que todavía provocan una sonrisa automática.</p>
<p>Los 420 Nm están disponibles desde un régimen modesto, de modo que la aceleración es inmediata y no lineal: el coche parece empujar con más fuerza de la que la cifra promete. El DSG de siete relaciones ejecuta los cambios con la contundencia que la ocasión merece.</p>

<h2>Sobre el asfalto</h2>
<p>La tracción total reparte empuje con una tendencia marcadamente trasera, y el autobloqueante ayuda a girar el coche acelerando. Sobre asfalto seco el VZ se comporta con una honestidad que sorprende: no es un coche de track day, pero admite un ritmo exigente sin rebentarse en la segunda curva.</p>
<p>El consumo, eso sí, es el de un deportivo: en uso tranquilo ronda los ocho litros, y en uso divertido sube sin problemas a doce. El precio de la gasolina de 98 octanos es el tributo que hay que pagar por este nivel de prestaciones.</p>
HTML,
            ],
            [
                'clave' => 'tesla-model-y-autonomia',
                'titulo' => 'Tesla Model Y: medimos la autonomía real en 400 kilómetros',
                'categoria' => 'pruebas', 'autor' => 'redaccion@motorpress.dev',
                'vistas' => 2650,
                'resumen' => '533 km homologados. Hemos recorrido ciudad, autovía y carretera de montaña para comprobar qué queda de esa cifra cuando el termómetro marca 4 grados.',
                'etiquetas' => ['eléctrico', 'autonomía', 'análisis'],
                'coches' => ['tesla-model-y', 'vw-id4'],
                'cuerpo' => <<<HTML
<p>La autonomía homologada se mide en un ciclo que pocos conductores replican. Por eso hemos diseñado un recorrido cerrado de 400 kilómetros que mezcla los tres escenarios habituales: 120 km de ciudad, 220 km de autovía a velocidad de crucero y 60 km de carretera de montaña con pendientes pronunciadas.</p>

<h2>Los resultados</h2>
<p>Con 4 grados ambientales y climatización activa, el Model Y Long Range ha consumido 17,4 kWh/100 km de media, una cifra un 10 % por encima de la homologada. Proyectada sobre su batería de 75 kWh, eso se traduce en unos 430 kilómetros reales de autonomía con un uso equilibrado.</p>
<p>El dato más relevante no es la cifra en sí, sino su estabilidad: en trayectos cortos el consumo se dispara por la climatización, mientras que en rutas largas el sistema encuentra su punto óptimo y se mantiene plana durante cientos de kilómetros.</p>

<h2>Consejos para exprimir la batería</h2>
<ol>
<li>Precondiciona el coche mientras sigue conectado al enchufe: la energía sale de la red, no de la batería.</li>
<li>En autovía, 110 km/h consume aproximadamente un 15 % menos que 130 km/h.</li>
<li>El precalentamiento de batería durante los últimos 30 minutos de ruta marca la diferencia en invierno.</li>
<li>Usa el modo de recuperación media: en ciudad recupera hasta un 15 % de lo consumido.</li>
</ol>
<p>Conclusión: el Model Y cumple, pero no es milagroso. Cualquiera que prometa más del 80 % de la cifra homologada en invierno está midiendo mal o exagerando.</p>
HTML,
            ],
            [
                'clave' => 'compra-ocasion-guia',
                'titulo' => 'Guía completa para comprar coche de ocasión sin caer en trampas',
                'categoria' => 'guia-compra', 'autor' => 'autor@motorpress.dev',
                'fijada' => 1, 'vistas' => 6120,
                'resumen' => 'Historial, kilometraje, subasta, financiación y garantía. Una lista de verificación práctica para comprar de segunda mano con los ojos abiertos.',
                'etiquetas' => ['usado', 'consejos', 'financiación'],
                'coches' => [],
                'cuerpo' => <<<HTML
<p>Comprar un coche de segunda mano bien resuelto puede ahorrarte entre un 30 y un 50 % del precio de un vehículo nuevo. Comprarlo mal puede costarte mucho más que eso. Estos son los pasos que seguimos en la redacción antes de firmar cualquier cosa.</p>

<h2>1. Antes de ver el coche</h2>
<p>Solicita el informe de historial por número de bastidor. En España, la DGT facilita datos de kilómetros, titularidades, cargas y siniestralidad. Un informe limpio no garantiza nada, pero uno sucio ahorra una visita inútil.</p>
<p>Después, compara el precio con las ofertas realmente equivalentes: misma versión, mismo nivel de equipamiento y kilometraje similar. Una rebaja muy por debajo del mercado casi siempre tiene una explicación.</p>

<h2>2. Durante la inspección</h2>
<ul>
<li>Revisa el espesor de la pintura con un medidor: diferencias bruscas entre paneles indican reparación.</li>
<li>Comprueba la fecha de las cuatro ruedas; neumáticos desiguales delatan problemas de suspensión o de alineación.</li>
<li>Mira el estado de las juntas de las puertas y el maletero: óxido allí suele ser el principio, no el final.</li>
<li>Arranca en frío si puedes: humo azul, blanco persistente o vibraciones al ralentí son señales de alarma.</li>
<li>Revisa los niveles de aceite y refrigerante y la coloración de ambos.</li>
</ul>

<h2>3. Kilometraje y mantenimiento</h2>
<p>El kilometraje por sí mismo no dice nada sin el contexto. Un vehículo con 180.000 km de autopista y mantenimiento documentado suele estar en mejor estado que uno con 90.000 km de ciudad y visitas esporádicas al taller. Exige facturas, y si no las hay, descuenta el coste de una revisión completa.</p>

<h2>4. La parte contractual</h2>
<p>Desde 2022, la compra entre particulares no incluye garantía legal, mientras que en profesionales hay doce meses mínimos. Dónde compras cambia tus derechos de forma radical: consúltalo antes de negociar.</p>
<blockquote>Regla práctica: si la oferta parece demasiado buena para ser verdad, probablemente oculta algo que el vendedor no quiere que veas.</blockquote>
<p>Por último, nunca pagues el importe total por adelantado sin haber visto el coche en persona ni haber comprobado que la documentación corresponde exactamente a él.</p>
HTML,
            ],
            [
                'clave' => 'hibridos-enchufables',
                'titulo' => 'Híbridos enchufables: cuándo compensan de verdad',
                'categoria' => 'guia-compra', 'autor' => 'redaccion@motorpress.dev',
                'destacada' => 1, 'vistas' => 3980,
                'resumen' => 'La clave no está en el coche, sino en tus kilómetros. Hacemos las cuentas de cuántos trayectos diarios necesitas para que un PHEV tenga sentido económico.',
                'etiquetas' => ['híbrido', 'consejos', 'financiación'],
                'coches' => ['peugeot-308', 'volvo-xc60'],
                'cuerpo' => <<<HTML
<p>El híbrido enchufable es la tecnología más malinterpretada del mercado. Vendida como la opción perfecta para todos, en realidad sólo tiene sentido bajo condiciones bastante concretas. Vamos con las cuentas.</p>

<h2>El escenario ideal</h2>
<p>Un PHEV con 60 km de autonomía eléctrica cubre por completo la vida de quien recorre menos de 50 kilómetros al día y dispone de enchufe en casa o en el trabajo. En esas condiciones, el vehículo circula la práctica totalidad del tiempo en modo eléctrico y el motor de combustión sólo se activa en viajes largos.</p>
<p>El desglose de costes para 20.000 km anuales quedaría así:</p>
<ul>
<li><strong>Con enchufe diario:</strong> 45 % eléctrico + 55 % térmico → aproximadamente 780 € de combustible.</li>
<li><strong>Sin enchufe:</strong> uso exclusivo en térmico con el peso extra de la batería → 1.050 € y mayor consumo.</li>
<li><strong>Diesel eficiente equivalente:</strong> en torno a 980 € con los mismos kilómetros.</li>
</ul>
<p>La diferencia entre los dos escenarios del PHEV supera los 270 euros anuales. Todo depende, por tanto, de una variable que no está en la ficha técnica: si vas a enchufarlo.</p>

<h2>Cuándo no merece la pena</h2>
<p>Si tu trayecto diario supera los 80 km, si no tienes punto de recarga accesible o si cambias de coche antes de cuatro años, el PHEV pierde buena parte de su atractivo. En ese caso, un híbrido sin enchufar ofrece un consumo similar con menos peso, menos complejidad y un precio de compra notablemente menor.</p>
<p>Y una advertencia que no viene mal: la batería de un PHEV es pequeña, así que trabaja siempre al límite de su capacidad. Eso no significa que falle, pero sí que su degradación se nota antes. Conviene comprobar el estado de la batería en la revisión de compra.</p>
HTML,
            ],
            [
                'clave' => 'formula-1-sostenible',
                'titulo' => 'Fórmula 1: la revolución de los combustibles sostenibles',
                'categoria' => 'deportes', 'autor' => 'redaccion@motorpress.dev',
                'vistas' => 2210,
                'resumen' => 'Desde 2026 los monoplazas correrán con combustibles de síntesis con al menos un 65 % de origen sostenible. Explicamos qué cambia y qué no.',
                'etiquetas' => ['Fórmula 1', 'deportivo', 'sostenibilidad'],
                'coches' => [],
                'cuerpo' => <<<HTML
<p>La Fórmula 1 afronta desde 2026 el cambio técnico más relevante de su era híbrida. La norma no sólo potencia la parte eléctrica del tren motriz: obliga a que el combustible contenga al menos un 65 % de materiales de origen no fósil.</p>

<h2>¿Qué son los e-fuels?</h2>
<p>Se producen combinando hidrógeno —obtenido con electricidad renovable— con dióxido de carbono capturado. El resultado es una molécula de hidrocarburo químicamente idéntica a la gasolina convencional, por lo que puede utilizarse en motores de combustión sin ninguna modificación.</p>
<p>La ventaja teórica es evidente: si el carbono emitido procede de la atmósfera, el ciclo se aproxima al carbono neutral. La desventaja es la eficiencia: producir un litro de e-fuel exige entre tres y cuatro veces más energía que cargar una batería para recorrer la misma distancia.</p>

<h2>Qué cambia en la pista</h2>
<p>La nueva especificación reduce la potencia de la unidad de potencia eléctrica y aumenta la del motor térmico, de modo que el reparto se acerca al 50/50. Se elimina el flujo de recarga directa y se potencia la recuperación de energía en frenada, lo que debería devolver cierto protagonismo al pilotaje.</p>
<blockquote>La Fórmula 1 no pretende ser un laboratorio de vehículos de calle: aspira a ser el escaparate de los combustibles imposibles de fabricar a gran escala hoy.</blockquote>
<p>Críticos y partidarios coinciden en un punto: el verdadero test no llegará en 2026, sino cuando se publiquen los precios de producción a escala industrial. Si los e-fuels se quedan en un producto de lujo, su papel quedará limitado a competición y patrimonio. Si bajan de coste, podrían rescatar a millones de vehículos existentes que ya circulan.</p>
HTML,
            ],
            [
                'clave' => 'clasicos-revalorizados',
                'titulo' => 'Los clásicos que más han revalorizado en la última década',
                'categoria' => 'clasicos', 'autor' => 'autor@motorpress.dev',
                'vistas' => 1870,
                'resumen' => 'No todo vale. Analizamos qué factores determinan la revalorización de un vehículo de colección y cuáles son las apuestas más sólidas del momento.',
                'etiquetas' => ['clásico', 'subasta', 'análisis'],
                'coches' => [],
                'cuerpo' => <<<HTML
<p>Invertir en coches clásicos tiene una ventaja evidente: se puede usar. Pero la regla de oro sigue siendo la de siempre —baja producción, estado original y documentación completa— y no todos los modelos cumplen los tres a la vez.</p>

<h2>Qué revaloriza y qué no</h2>
<p>Las subastas de los últimos años muestran tres patrones claros. Primero, la escasez: los modelos fabricados en menos de 5.000 unidades mantienen su valor con mucha más firmeza. Segundo, la originalidad: una restauración excesiva, por bien hecha que esté, resta dinero. Tercero, la procedencia: historial documentado y titulares conocidos añaden una prima que puede superar el 20 %.</p>
<p>En contrapartida, los vehículos con producciones masivas, grandes reparaciones o bastidores reapuntados rara vez superan el valor de compra más los gastos de restauración.</p>

<h2>Un caso práctico</h2>
<p>Imaginamos un coupe deportivo de los años noventa con 80.000 km originales, carnet de mantenimiento completo y pintura de fábrica intacta. Diez años atrás podía adquirirse por unos 25.000 euros. Hoy, en las mismas condiciones, las casas de subastas lo sitúan por encima de los 70.000. La misma unidad, con 200.000 km y una restauración completa, apenas alcanza la mitad.</p>
<h2>Riesgos que conviene conocer</h2>
<ul>
<li>El mercado es ilíquido: vender puede llevar meses, no días.</li>
<li>El seguro de valor convenido cuesta entre el 1 % y el 1,5 % anual.</li>
<li>Mantener un clásico en circulación supone entre 1.500 y 3.000 euros al año.</li>
<li>Los impuestos y las restricciones de acceso pueden cambiar en tu ciudad.</li>
</ul>
<p>Conclusión: es un mercado atractivo para quien lo disfruta primero y lo rentabiliza después. Como única inversión, exige paciencia y conocimiento.</p>
HTML,
            ],
            [
                'clave' => 'hidrogeno-futuro',
                'titulo' => 'Hidrógeno: ¿el combustible del futuro o una promesa aplazada?',
                'categoria' => 'sostenibilidad', 'autor' => 'redaccion@motorpress.dev',
                'vistas' => 1640,
                'resumen' => 'Pila de combustible, recarga en tres minutos y autonomía de 600 km. Repasamos dónde encaja de verdad el hidrógeno y dónde no.',
                'etiquetas' => ['hidrógeno', 'sostenibilidad', 'análisis'],
                'coches' => [],
                'cuerpo' => <<<HTML
<p>Cada pocos años el hidrógeno vuelve a la conversación con la misma promesa: llenar el depósito en tres minutos y recorrer 600 kilómetros. Veamos con rigor dónde encaja y dónde no.</p>

<h2>Cómo funciona</h2>
<p>Una pila de combustible no quema nada. Oxida hidrógeno con oxígeno del aire y produce electricidad, agua y calor. No hay combustión, no hay emisiones y el ruido es apenas perceptible. La tecnología está madura y los vehículos que circulan hoy ofrecen prestaciones equivalentes a las de un diésel.</p>

<h2>El problema no es el coche</h2>
<p>El problema es la cadena. Producir hidrógeno verde por electrólisis consume aproximadamente el 60 % de la electricidad de partida, frente al 85-90 % que aprovecha directamente una batería. Compresión, transporte y almacenamiento añaden más pérdidas. El resultado es que un hidrógeno que llega al coche puede haber costado el doble de energía que el mismo trayecto cubierto con electricidad directa.</p>

<h2>Dónde sí tiene sentido</h2>
<ul>
<li><strong>Transporte pesado:</strong> largas distancias con necesidad de carga útil y paradas cortas.</li>
<li><strong>Flotas urbanas:</strong> autobuses y reparto con depot de recarga centralizado.</li>
<li><strong>Industria:</strong> siderurgia, química y calor de proceso donde la electrificación es inviable.</li>
<li><strong>Generación renovable excedente:</strong> almacenamiento estacional.</li>
</ul>
<p>Para el turismo familiar urbano, con enchufe disponible, el hidrógeno no compite: pierde en eficiencia, en precio y en infraestructura. Su papel será probablemente relevante, pero no en el garaje de casa.</p>
HTML,
            ],
            [
                'clave' => 'asistentes-conduccion',
                'titulo' => 'Asistentes a la conducción: qué permite realmente la ley nivel 2',
                'categoria' => 'tecnologia', 'autor' => 'redaccion@motorpress.dev',
                'vistas' => 2340,
                'resumen' => 'Control de crucero adaptativo, mantenimiento de carril y frenada de emergencia. Qué obliga la normativa y qué responsabilidad sigue siendo del conductor.',
                'etiquetas' => ['IA', 'seguridad', 'consejos'],
                'coches' => [],
                'cuerpo' => <<<HTML
<p>Los sistemas de asistencia llegan al mercado con nombres propios, interfaces distintas y grados de capacidad muy desiguales. Por detrás, la normativa europea los clasifica en niveles bien definidos. Conviene entenderlos.</p>

<h2>Nivel 0 y 1</h2>
<p>El nivel 0 no asiste: informa. El nivel 1 mantiene una variable, sea la velocidad o la distancia, pero no dirige. El control de crucero adaptativo clásico pertenece aquí: acelera y frena por ti, pero no toca el volante.</p>

<h2>Nivel 2: la asistencia sostenida</h2>
<p>El nivel 2 combina control de velocidad y mantenimiento activo de carril. El sistema acelera, frena y dirige de forma simultánea, pero <strong>el conductor sigue siendo el responsable en todo momento</strong>. La normativa obliga a que el sistema vigile que el conductor presta atención: detección ocular, presión en el volante o en la mano sobre el volante.</p>
<blockquote>Si el sistema te pide que toques el volante, es porque legalmente no puede seguir sin ti. No es una sugerencia.</blockquote>

<h2>Qué hacer y qué no</h2>
<ul>
<li>Úsalo en autopista y autovía con tráfico fluido: es donde aporta.</li>
<li>No lo uses en lluvia intensa, niebla o carreteras con obras mal señalizadas.</li>
<li>Nunca apartes la vista del camino por más de tres segundos.</li>
<li>Recuerda que la frenada de emergencia autónoma tiene límites: no detecta todos los obstáculos estáticos.</li>
</ul>
<h2>Nivel 3 y más allá</h2>
<p>El nivel 3, ya autorizado en algunos países, transfieren la responsabilidad al vehículo en condiciones concretas. Exige redundancia en sensores, redundancia en frenado y una definición de "condiciones concretas" que aún genera debates legibles en los tribunales. Para el usuario, la diferencia es enorme: en nivel 3 puedes mirar el móvil; en nivel 2, no.</p>
HTML,
            ],
            [
                'clave' => 'dacia-duster-analisis',
                'titulo' => 'Dacia Duster: la fórmula low-cost sigue funcionando',
                'categoria' => 'pruebas', 'autor' => 'autor@motorpress.dev',
                'destacado' => 1, 'vistas' => 3120,
                'resumen' => 'Prueba del Duster 1.3 TCe 150. Un SUV honesto, robusto y con el precio más bajo de su categoría, pero que no intenta aparentar lo que no es.',
                'etiquetas' => ['prueba', 'SUV', 'consejos'],
                'coches' => ['dacia-duster'],
                'cuerpo' => <<<HTML
<p>Dacia ha resuelto algo que muchos fabricantes con mucho más presupuesto no consiguen: construir un coche sin pretensiones que además resulte deseable. El Duster es el mejor ejemplo de esa filosofía.</p>

<h2>Lo que se nota al conducir</h2>
<p>La dirección es ligera y el eje trasero sigue sin dramatismos. No es un coche divertido, pero tampoco lo pretende: absorbe badenes, admite caminos de tierra sin angustia y mantiene una compostura firme en autopista. Para la mayoría de los usos familiares, es exactamente lo que se necesita.</p>
<p>El motor 1.3 TCe de 150 cv, compartido con el grupo Renault, muestra un carácter elástico y un consumo razonable: 6,4 litros en nuestro test con uso mixto realista. El cambio manual de seis marchas es preciso, si bien la palanca recorre un desarrollo algo largo.</p>

<h2>Interior y equipamiento</h2>
<p>Aquí es donde se ve el precio. Hay plásticos duros, la insonorización es justa y el sistema multimedia de la versión de entrada resulta básico. Pero las plazas son amplias, el maletero de 445 litros es generoso y todo está donde debe estar.</p>
<ul>
<li><strong>A favor:</strong> precio, robustez, coste de mantenimiento, garantía de tres años.</li>
<li><strong>En contra:</strong> acabado, aislamiento acústico y sistemas de asistencia poco avanzados.</li>
</ul>
<p>Si necesitas un SUV para la familia y no quieres pagar de más, sigue siendo la apuesta más sensata del mercado. Si buscas una sensación de calidad premium, tendrás que subir de gama y, sobre todo, de precio.</p>
HTML,
            ],
            [
                'clave' => 'comparativa-berlinas',
                'titulo' => 'Comparativa: berlina alemana frente a la nueva generación coreana',
                'categoria' => 'pruebas', 'autor' => 'redaccion@motorpress.dev',
                'vistas' => 2760,
                'resumen' => 'Hemos puesto sobre la mesa cinco berlinas de tamaño medio para comprobar si la propuesta asiática ya ha alcanzado al tradicional trío alemán.',
                'etiquetas' => ['comparativa', 'berlina', 'análisis'],
                'coches' => ['bmw-serie-3', 'mercedes-clase-c', 'audi-a4'],
                'cuerpo' => <<<HTML
<p>Durante veinte años, la respuesta a "¿qué berlina de tamaño medio compras?" era casi siempre alemana. La pregunta de hoy tiene más matices.</p>

<h2>Conducción</h2>
<p>Aquí el liderazgo sigue siendo alemán. La Serie 3 mantiene la dirección más expresiva, la Clase C gana en confort y la A4 ofrece el equilibrio más neutro. Las propuestas coreanas han mejorado enormemente, pero en filtración de vibraciones finas y en la progresividad de la dirección todavía se nota el trabajo de los equipos de Múnich, Stuttgart e Ingolstadt.</p>

<h2>Calidad percibida</h2>
<p>El salpicadero es donde la brecha se ha reducido más. Los materiales blandos, las costuras y el ajuste de paneles están a la altura. Donde sigue existiendo diferencia es en los detalles: el tacto de los botones, el peso de una puerta, el sonido de un interruptor.</p>

<h2>Equipamiento por euro</h2>
<p>Aquí la balanza se inclina del otro lado. Con el mismo presupuesto, una berlina coreana incluye habitualmente asientos ventilados, techo panorámico, head-up display y un paquete de asistencia completo de serie, mientras que la alemana los ofrece como opción de pago.</p>

<h2>Valor de reventa</h2>
<p>El último apartado devuelve la ventaja a Alemania. Tras cuatro años, las berlinas alemanas conservan entre el 45 y el 55 % de su valor frente al 35-45 % de las alternativas. Esa diferencia, sumada a un precio de entrada superior, suele igualar la ganancia de equipamiento inicial.</p>
<p>Conclusión práctica: si compras para renovar pronto, el equipamiento coreano puede salir a cuenta. Si mantienes el coche más de cinco años, la reventa alemana recupera la diferencia.</p>
HTML,
            ],
            [
                'clave' => 'mantenimiento-itv',
                'titulo' => 'Taller y mantenimiento: qué revisar antes de la ITV',
                'categoria' => 'motor', 'autor' => 'autor@motorpress.dev',
                'vistas' => 1520,
                'resumen' => 'Una lista corta y ordenada de comprobaciones que evitan el suspenso en la inspección técnica y, sobre todo, averías caras a posteriori.',
                'etiquetas' => ['consejos', 'seguridad'],
                'coches' => [],
                'cuerpo' => <<<HTML
<p>La ITV suspende por motivos sorprendentemente sencillos. Con una hora de trabajo la noche anterior se evita la mayor parte de los casos.</p>

<h2>Lo que más suspende</h2>
<ol>
<li><strong>Iluminación:</strong> fundido un piloto, mal orientado un foco o desajustado el nivelado de los xenón. El 30 % de las suspensiones vienen de aquí.</li>
<li><strong>Neumáticos:</strong> la indicación de desgaste debe ser visible; menos de 1,6 mm es suspensión automática.</li>
<li><strong>Frenos:</strong> las pastillas por debajo de 3 mm y los discos muy marcados son motivo de reparación obligatoria.</li>
<li><strong>Emisiones:</strong> revisa la luz de motor y la sonda antes de presentarte.</li>
<li><strong>Carrocería:</strong> óxido activo en zonas estructurales, grietas en cristales y puntos de soldadura alterados.</li>
<li><strong>Cinturones y airbags:</strong> el testigo del airbag encendida suspende siempre.</li>
</ol>

<h2>Comprobaciones de taller que no son ITV pero importan</h2>
<ul>
<li>Nivel y coloración del refrigerante: un tono café indica problema serio.</li>
<li>Estado de la correa de distribución si el vehículo no la tiene por cadena.</li>
<li>Fecha y presión de las ruedas de repuesto.</li>
<li>Funcionamiento del freno de mano y del limitador.</li>
<li>Limpieza del filtro de habitáculo: huele mal en verano, es eso.</li>
</ul>
<p>Un consejo final: lleva el libro de mantenimiento al día y con facturas. En la inspección técnica y, sobre todo, en la venta futura del vehículo, esa carpeta vale más que cualquier negociación.</p>
HTML,
            ],
            [
                'clave' => 'mercado-ocasion-precios',
                'titulo' => 'Mercado de ocasión: los precios bajan en los SUV compactos',
                'categoria' => 'mercado', 'autor' => 'redaccion@motorpress.dev',
                'vistas' => 2010,
                'resumen' => 'El exceso de stock y la llegada de modelos eléctricos empujan a la baja el mercado de segunda mano en el segmento más disputado del país.',
                'etiquetas' => ['usado', 'análisis', 'financiación'],
                'coches' => ['hyundai-tucson', 'peugeot-3008', 'ford-puma'],
                'cuerpo' => <<<HTML
<p>Después de tres años de subidas continuadas, el mercado de segunda mano ha empezado a corregir. La corrección no es uniforme: afecta con fuerza a un segmento y apenas se nota en otro.</p>

<h2>Dónde bajan</h2>
<p>Los SUV compactos de tres a cinco años, la categoría más abundante en las carteras de renting y de concesionario, acumulan las mayores caídas. El exceso de oferta y la velocidad con la que envejecen los modelos de combustión empujan los precios a la baja en torno a un 6-8 % interanual.</p>

<h2>Dónde aguantan</h2>
<p>Los vehículos con garantía larga, los híbridos de marcas con historial de fiabilidad y los modelos con producción limitada mantienen su valor. También resisten los utilitarios pequeños, donde la demanda de primera mano sigue siendo alta y el precio nuevo sigue encareciéndose.</p>

<h2>Cómo aprovecharlo</h2>
<ul>
<li>Si vendes, hazlo rápido: cada mes de espera cuesta entre un 0,5 y un 1 % de valor.</li>
<li>Si compras, negocia con datos: pide el histórico de precios del modelo concreto.</li>
<li>Mira el coste total, no el precio: seguros, impuestos y consumos igualan más de lo que parece.</li>
<li>Los vehículos de renting a punto de devolver suelen ser la mejor relación calidad-precio del mercado.</li>
</ul>
<p>De cara a los próximos doce meses esperamos una estabilización más que una caída brusca: los coches eléctricos usados son el único segmento con recorrido claro a la baja.</p>
HTML,
            ],
            [
                'clave' => 'seguridad-volvo',
                'titulo' => 'Volvo y la seguridad: de los cinturones de tres puntos a la conducción autónoma',
                'categoria' => 'tecnologia', 'autor' => 'redaccion@motorpress.dev',
                'vistas' => 1430,
                'resumen' => 'Casi setenta años separan la patente del cinturón de tres puntos del sistema que frena por ti antes de que veas el peligro. Un repaso a la historia de una obsesión.',
                'etiquetas' => ['seguridad', 'consejos', 'IA'],
                'coches' => ['volvo-xc60'],
                'cuerpo' => <<<HTML
<p>En 1959, Volvo puso a disposición de la industria la patente del cinturón de tres puntos. Fue una decisión deliberada de no lucrarse con algo que salvaba vidas. Ese gesto define mejor que cualquier anuncio la historia de la marca sueca.</p>

<h2>Hitos que siguen vigentes</h2>
<p>Los nombres propios pueden resultar raros, pero sus efectos se notan cada día:</p>
<ul>
<li><strong>1959 — Cinturón de tres puntos.</strong> La geometría que usamos hoy se diseñó en Suecia.</li>
<li><strong>1970 — Módulo de deformación controlada.</strong> El coche se deforma para que el habitáculo no lo haga.</li>
<li><strong>1991 — SIPS.</strong> Protección lateral con estructuras reforzadas en las puertas.</li>
<li><strong>1998 — SIPS-BAG.</strong> Primer airbag de cortina de serie en un vehículo europeo.</li>
<li><strong>2008 — City Safety.</strong> Frenada automática a baja velocidad, hoy obligatoria en Europa.</li>
</ul>

<h2>Lo que viene</h2>
<p>La apuesta actual ya no es sólo proteger tras el impacto, sino evitarlo. La detección de peatones, ciclistas y animales, la vigilancia de atención del conductor y la frenada autónoma de emergencia forman hoy un sistema integrado que trabaja de forma invisible.</p>
<blockquote>Ningún fabricante ha salvado tantas vidas por euro invertido en I+D. Ninguno lo ha publicitado tampoco con tanta discreción.</blockquote>
<p>Queda un desafío que la seguridad activa no resuelve: el factor humano. Según los datos europeos, la fatiga y la distracción siguen detrás de más de un tercio de los accidentes graves. Ningún sensor ha aprendido todavía a sustituir del todo a un conductor atento.</p>
HTML,
            ],
            [
                'clave' => 'mazda-mx5-aniversario',
                'titulo' => 'Mazda MX-5: 35 años de la mejor roadster del mundo',
                'categoria' => 'clasicos', 'autor' => 'autor@motorpress.dev',
                'vistas' => 1980,
                'resumen' => 'Menos de mil kilos, tracción trasera y techo de tela. El pequeño deportivo japonés sigue siendo la demostración de que la diversión no es cuestión de potencia.',
                'etiquetas' => ['clásico', 'deportivo', 'prueba'],
                'coches' => [],
                'cuerpo' => <<<HTML
<p>El MX-5 nació en 1989 con una idea casi contraria al tiempo: que un deportivo no necesita trescientos caballos para ser memorable. Treinta y cinco años y cuatro generaciones después, la idea sigue intacta.</p>

<h2>La fórmula</h2>
<p>Motor delantero, eje trasero rígido con barras de reacción, reparto de pesos cercano al 50/50 y un peso en orden de marcha que no llega a los mil kilos. Con 181 cv la cuarta generación no es especialmente rápida, pero rara vez se queda corta.</p>
<p>La clave está en la relación entre potencia y masa, y en algo menos medible: la comunicación. El volante informa, el eje trasero avisa y el cambio manual tiene una carrera corta y metálica que invita a seguir conduciendo cuando el destino ya está alcanzado.</p>

<h2>Por qué envejece tan bien</h2>
<p>Tres razones. La primera, mecánica: los motores Skyactiv-G son simples, fiables y su mantenimiento está perfectamente dimensionado. La segunda, de mercado: la producción ha sido constante y no hay huecos en el historial. La tercera, de espíritu: el MX-5 nunca compitió en un segmento donde pudiera quedar obsoleto.</p>
<ul>
<li><strong>Puntos fuertes:</strong> diversión por euro, fiabilidad, comunidad de propietarios y reventa.</li>
<li><strong>Puntos débiles:</strong> dos plazas reales, maletero mínimo y sujeción limitada en lluvia intensa.</li>
</ul>
<p>Hay coches más rápidos, más cómodos y más equipados. Ninguno convierte una ruta secundaria cualquiera en el mejor plan del día con tanta economía de medios.</p>
HTML,
            ],
        ];
    }
}
