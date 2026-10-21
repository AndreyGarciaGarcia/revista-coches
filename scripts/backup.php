<?php

/**
 * =====================================================================
 *  MotorPress · Copia de seguridad de la base de datos
 * =====================================================================
 *
 *   php scripts/backup.php                    volcado completo (gz)
 *   php scripts/backup.php --out=backups      carpeta de destino
 *   php scripts/backup.php --no-data          sólo estructura
 *   php scripts/backup.php --keep=10          conserva los 10 últimos
 *   php scripts/backup.php --tabla=noticias   sólo esas tablas
 *   php scripts/backup.php --list             lista las copias existentes
 *
 * El volcado se genera en PHP puro (no depende de mysqldump, que en
 * algunos entornos XAMPP no está operativo) y se comprime con gzip.
 * Usa las credenciales de mantenimiento DB_ROOT_* del .env.
 *
 * Restaurar:
 *   gunzip -c backups/revista_coches_20261007_153000.sql.gz | \
 *     mysql -u root -p revista_coches
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\Env;

$opciones = cli_args($argv, ['out::', 'keep::', 'no-data', 'tabla::', 'list', 'help']);

if (isset($opciones['help'])) {
    echo <<<TXT
MotorPress · Copia de seguridad

  php scripts/backup.php                 volcado completo comprimido
  php scripts/backup.php --out=ruta      carpeta de destino (por defecto backups/)
  php scripts/backup.php --no-data       sólo el esquema
  php scripts/backup.php --keep=N        elimina las copias más antiguas
  php scripts/backup.php --tabla=a,b     sólo esas tablas
  php scripts/backup.php --list          lista las copias disponibles

TXT;
    exit(0);
}

$raiz = BASE_PATH;
$destino = rtrim((string) ($opciones['out'] ?? $raiz . '/backups'), '/\\');
$esquema = Env::getString('DB_NAME', 'revista_coches');
$soloDatos = false;

/** @var array<int,string> $soloTabla */
$soloTabla = [];

foreach ((array) ($opciones['tabla'] ?? []) as $t) {
    foreach (explode(',', (string) $t) as $parte) {
        $parte = trim($parte);
        if ($parte !== '') {
            $soloTabla[] = $parte;
        }
    }
}

if (array_key_exists('no-data', $opciones)) {
    $soloDatos = true;
}

// ---------------------------------------------------------------------
//  Listado
// ---------------------------------------------------------------------

if (array_key_exists('list', $opciones)) {
    if (!is_dir($destino)) {
        echo "No existe la carpeta de copias: {$destino}\n";
        exit(0);
    }

    $ficheros = glob($destino . '/*.sql.gz') ?: [];
    rsort($ficheros);

    if ($ficheros === []) {
        echo "No hay copias en {$destino}\n";
        exit(0);
    }

    echo "Copias disponibles en {$destino}:\n\n";
    foreach ($ficheros as $f) {
        printf(
            "  %-46s %8.1f KB  %s\n",
            basename($f),
            filesize($f) / 1024,
            date('d/m/Y H:i', filemtime($f))
        );
    }

    exit(0);
}

// ---------------------------------------------------------------------
//  Conexión con privilegios de mantenimiento
// ---------------------------------------------------------------------

if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
    fwrite(STDERR, "No se pudo crear la carpeta de destino: {$destino}\n");
    exit(1);
}

try {
    $pdo = new PDO(
        'mysql:host=' . Env::getString('DB_HOST', '127.0.0.1')
        . ';port=' . Env::getString('DB_PORT', '3306')
        . ';dbname=' . $esquema . ';charset=utf8mb4',
        Env::getString('DB_ROOT_USER', Env::getString('DB_USER', 'root')),
        Env::getString('DB_ROOT_PASS', Env::getString('DB_PASS', '')),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo conectar: ' . $e->getMessage() . "\n");
    exit(1);
}

// ---------------------------------------------------------------------
//  Tablas del esquema
// ---------------------------------------------------------------------

$consulta = 'SELECT table_name AS t, table_rows AS filas
               FROM information_schema.tables
              WHERE table_schema = :s
                AND table_type = "BASE TABLE"
              ORDER BY table_name';

$tablas = [];
$sentencia = $pdo->prepare($consulta);
$sentencia->execute(['s' => $esquema]);

foreach ($sentencia as $fila) {
    $nombre = (string) $fila['t'];

    if ($soloTabla !== [] && !in_array($nombre, $soloTabla, true)) {
        continue;
    }

    $tablas[$nombre] = (int) $fila['filas'];
}

$sentencia->closeCursor();

if ($tablas === []) {
    fwrite(STDERR, "El esquema «{$esquema}» no contiene tablas.\n");
    exit(1);
}

$nombreFichero = sprintf(
    '%s_%s.sql.gz',
    $esquema,
    date('Ymd_His')
);

$ruta = rtrim($destino, '/\\') . DIRECTORY_SEPARATOR . $nombreFichero;

// ---------------------------------------------------------------------
//  Volcado
// ---------------------------------------------------------------------

$inicio = microtime(true);
$gzip = gzopen($ruta, 'wb6');

if ($gzip === false) {
    fwrite(STDERR, "No se pudo abrir {$ruta} para escritura.\n");
    exit(1);
}

$escribir = static function (string $texto) use ($gzip): void {
    gzwrite($gzip, $texto);
};

$fecha = date('c');

$escribir("-- =====================================================================\n");
$escribir("-- MotorPress · copia de seguridad\n");
$escribir("-- Esquema : {$esquema}\n");
$escribir("-- Fecha   : {$fecha}\n");
$escribir("-- PHP     : " . PHP_VERSION . "\n");
$escribir("-- MySQL   : " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n");
$escribir("-- Modo    : " . ($soloDatos ? 'sólo datos' : 'estructura + datos') . "\n");
$escribir("--\n");
$escribir("-- Generado por scripts/backup.php\n");
$escribir("-- =====================================================================\n\n");

$escribir("SET NAMES utf8mb4;\n");
$escribir("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
$escribir("SET FOREIGN_KEY_CHECKS = 0;\n\n");

// Las vistas que ya existan en el destino se retiran antes que las tablas
// a las que apuntan (si no, MySQL negaría el DROP TABLE).
$nombresVistas = [];

foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_ASSOC) as $filaVista) {
    $nombre = (string) array_values($filaVista)[0];

    if ($soloTabla === [] || in_array($nombre, $soloTabla, true)) {
        $nombresVistas[] = $nombre;
    }
}

foreach ($nombresVistas as $vistaPrevia) {
    $escribir('DROP VIEW IF EXISTS `' . str_replace('`', '', $vistaPrevia) . "`;\n");
}

if ($nombresVistas !== []) {
    $escribir("\n");
}

$filasTotales = 0;
$vistas = 0;
$triggers = 0;
$rutinasAlmacenadas = 0;

foreach ($tablas as $tabla => $estimadas) {
    $tablaSegura = str_replace('`', '``', $tabla);

    $escribir("-- ---------------------------------------------------------------------\n");
    $escribir("-- Tabla: {$tabla}\n");
    $escribir("-- ---------------------------------------------------------------------\n\n");

    $crear = $pdo->query("SHOW CREATE TABLE `{$tablaSegura}`")->fetch();

    if ($crear === false) {
        $escribir("-- No se pudo leer la definición de {$tabla}\n\n");
        continue;
    }

    $esquemaTabla = $crear['Create Table'] ?? array_values($crear)[1];

    $escribir("DROP TABLE IF EXISTS `{$tablaSegura}`;\n");
    $escribir($esquemaTabla . ";\n\n");

    if ($soloDatos) {
        continue;
    }

    // Lotes de 250 filas por sentencia INSERT (equilibrio entre tamaño y velocidad)
    $tamanioLote = 250;
    $offset = 0;
    $filasTabla = 0;

    while (true) {
        $sentencia = $pdo->query(
            "SELECT * FROM `{$tablaSegura}` LIMIT {$tamanioLote} OFFSET {$offset}"
        );

        if ($sentencia === false) {
            break;
        }

        $lote = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        $sentencia->closeCursor();

        if ($lote === []) {
            break;
        }

        $columnas = array_map(
            static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`',
            array_keys($lote[0])
        );

        $escribir('INSERT INTO `' . $tablaSegura . '` (' . implode(', ', $columnas) . ") VALUES\n");

        $bloques = [];

        foreach ($lote as $fila) {
            $valores = [];

            foreach ($fila as $valor) {
                if ($valor === null) {
                    $valores[] = 'NULL';
                    continue;
                }

                if (is_int($valor) || is_float($valor)) {
                    $valores[] = (string) $valor;
                    continue;
                }

                // PDO::quote() ya devuelve la cadena entrecomillada
                $valorCotado = $pdo->quote((string) $valor);
                $valores[] = $valorCotado === false ? 'NULL' : $valorCotado;
            }

            $bloques[] = '(' . implode(', ', $valores) . ')';
        }

        $escribir(implode(",\n", $bloques) . ";\n");

        $filasTotales += count($lote);
        $filasTabla += count($lote);
        $offset += count($lote);
        unset($lote, $bloques);
    }

    $escribir("\n");

    $total = number_format($filasTabla, 0, ',', '.');
    $nota = $filasTabla !== $estimadas
        ? ' (el esquema estimaba ' . number_format($estimadas, 0, ',', '.') . ')'
        : '';
    fwrite(STDOUT, sprintf("  %-34s %10s filas%s\n", $tabla, $total, $nota));
}

// ---------------------------------------------------------------------
//  Funciones, vistas, procedimientos y triggers
//
//  Se recogen todos los objetos y luego se emiten en orden topológico:
//  una vista puede usar una función, un trigger puede usar funciones y
//  un procedimiento puede usar a otro. Así la copia se puede reconstruir
//  desde cero sin errores de "objeto no existe".
// ---------------------------------------------------------------------

$definiciones = [];

// Procedimientos y funciones almacenados
$rutinas = [
    'PROCEDURE' => ['tipo' => 'procedimiento', 'clave' => 'Create Procedure'],
    'FUNCTION'  => ['tipo' => 'funcion', 'clave' => 'Create Function'],
];

foreach ($rutinas as $tipo => $datos) {
    $estado = $pdo->query("SHOW {$tipo} STATUS WHERE Db = " . $pdo->quote($esquema));

    foreach ($estado->fetchAll(PDO::FETCH_ASSOC) as $filaRutina) {
        $nombre = (string) $filaRutina['Name'];

        $crear = $pdo->query("SHOW CREATE {$tipo} `" . str_replace('`', '', $nombre) . '`')->fetch();

        if ($crear === false) {
            continue;
        }

        $definicion = (string) ($crear[$datos['clave']] ?? '');

        if ($definicion === '') {
            continue;
        }

        $definiciones[$nombre] = ['tipo' => $datos['tipo'], 'sql' => $definicion];
    }
}

// Vistas
foreach ($nombresVistas as $vista) {
    $crear = $pdo->query('SHOW CREATE VIEW `' . str_replace('`', '', $vista) . '`')->fetch();

    if ($crear !== false) {
        $definiciones[$vista] = [
            'tipo' => 'vista',
            'sql'  => (string) ($crear['Create View'] ?? array_values($crear)[1] ?? ''),
        ];
    }
}

// Triggers
foreach ($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $filaTrigger) {
    $nombre = (string) $filaTrigger['Trigger'];

    $crear = $pdo->query('SHOW CREATE TRIGGER `' . str_replace('`', '', $nombre) . '`')->fetch();

    if ($crear === false) {
        continue;
    }

    $definicion = (string) ($crear['SQL Original Statement'] ?? '');

    if ($definicion !== '') {
        $definiciones[$nombre] = ['tipo' => 'trigger', 'sql' => $definicion];
    }
}

// Índice en minúsculas: sólo se contemplan como dependencias los nombres
// que existen de verdad (así se ignoran variables locales tipo $v_total).
$indice = [];

foreach (array_keys($definiciones) as $nombreObjeto) {
    $indice[strtolower($nombreObjeto)] = $nombreObjeto;
}

$pendientes = [];

foreach ($definiciones as $nombre => $definicion) {
    preg_match_all('/\b(?:fn|sp|v)_[a-z0-9_]+/i', $definicion['sql'], $coincidencias);

    $dependencias = [];

    foreach (array_unique($coincidencias[0]) as $referencia) {
        $real = $indice[strtolower($referencia)] ?? null;

        if ($real !== null && $real !== $nombre) {
            $dependencias[] = $real;
        }
    }

    $pendientes[$nombre] = $definicion + ['deps' => array_values(array_unique($dependencias))];
}

$emitir = static function (string $nombre, array $definicion) use (
    $escribir,
    &$vistas,
    &$triggers,
    &$rutinasAlmacenadas
): void {
    $palabra = [
        'funcion'      => 'Función',
        'vista'        => 'Vista',
        'procedimiento'=> 'Procedimiento',
        'trigger'      => 'Trigger',
    ][$definicion['tipo']] ?? 'Objeto';

    $sep = str_repeat('-', 69);

    $escribir("-- {$sep}\n");
    $escribir("-- {$palabra}: {$nombre}\n");
    $escribir("-- {$sep}\n\n");

    $corte = str_replace('`', '', $nombre);

    switch ($definicion['tipo']) {
        case 'vista':
            $escribir("DROP VIEW IF EXISTS `{$corte}`;\n");
            $vistas++;
            break;

        case 'trigger':
            $escribir("DROP TRIGGER IF EXISTS `{$corte}`;\n");
            $triggers++;
            break;

        case 'funcion':
            $escribir("DROP FUNCTION IF EXISTS `{$corte}`;\n");
            $rutinasAlmacenadas++;
            break;

        default:
            $escribir("DROP PROCEDURE IF EXISTS `{$corte}`;\n");
            $rutinasAlmacenadas++;
    }

    $escribir(rtrim($definicion['sql']) . ";\n\n");

    fwrite(STDOUT, sprintf("  %-34s %10s %s\n", $nombre, '-', mb_strtolower($palabra)));
};

// Orden topológico (Kahn simplificado) con salida de emergencia si hubiera
// un ciclo: mejor un orden imperfecto que una copia incompleta.
$orden = [];

while ($pendientes !== []) {
    $avance = false;

    foreach ($pendientes as $nombre => $definicion) {
        if (array_diff($definicion['deps'], $orden) !== []) {
            continue;
        }

        $orden[] = $nombre;
        unset($pendientes[$nombre]);
        $avance = true;
    }

    if ($avance) {
        continue;
    }

    foreach (array_keys($pendientes) as $nombre) {
        $orden[] = $nombre;
        unset($pendientes[$nombre]);
    }

    break;
}

foreach ($orden as $nombre) {
    $emitir($nombre, $definiciones[$nombre]);
}
$escribir("SET FOREIGN_KEY_CHECKS = 1;\n");
$escribir("-- Fin de la copia\n");

gzclose($gzip);

// ---------------------------------------------------------------------
//  Limpieza de copias antiguas
// ---------------------------------------------------------------------

$mantener = (int) ($opciones['keep'] ?? 0);
$eliminadas = 0;

if ($mantener > 0) {
    $existentes = glob($destino . '/*.sql.gz') ?: [];
    rsort($existentes);

    foreach (array_slice($existentes, $mantener) as $vieja) {
        if (@unlink($vieja)) {
            $eliminadas++;
        }
    }
}

// ---------------------------------------------------------------------
//  Informe
// ---------------------------------------------------------------------

$segundos = microtime(true) - $inicio;
$peso = filesize($ruta) / 1024;

echo "\n";
echo "┌──────────────────────────────────────────────┐\n";
echo "│  MotorPress · copia de seguridad             │\n";
echo "└──────────────────────────────────────────────┘\n\n";
echo "  Fichero     : {$ruta}\n";
echo "  Tamaño      : " . number_format($peso, 1, ',', '.') . " KB\n";
echo "  Tablas      : " . count($tablas) . "\n";
echo "  Vistas      : {$vistas}\n";
echo "  Triggers    : {$triggers}\n";
echo "  Rutinas     : {$rutinasAlmacenadas}\n";
echo "  Filas       : " . number_format($filasTotales, 0, ',', '.') . "\n";
echo "  Duración    : " . number_format($segundos, 2, ',', '.') . " s\n";

if ($eliminadas > 0) {
    echo "  Copias borradas por retención: {$eliminadas}\n";
}

echo "\nVerifica que el fichero es restaurable antes de depender de él:\n";
echo "  gunzip -c \"{$ruta}\" | head -n 20\n\n";
