<?php

/**
 * =====================================================================
 *  MotorPress · Instalador
 * =====================================================================
 *
 *   php scripts/install.php              instalación completa
 *   php scripts/install.php --force      reinstala (borra el esquema)
 *   php scripts/install.php --no-seed    sólo base de datos
 *   php scripts/install.php --no-images  no descarga fotografías ni vídeos
 *
 * Pasos:
 *   1. Crea la base de datos si no existe
 *   2. Crea el usuario de aplicación con MÍNIMOS privilegios
 *   3. Bloquea el acceso remoto a la base de datos
 *   4. Aplica las migraciones (esquema, vistas, procedimientos,
 *      disparadores)
 *   5. Descarga fotografías, galerías por vehículo y vídeos, con
 *      autoría y licencia documentadas
 *   6. Carga los datos de partida
 *   7. Verifica la conexión con el usuario restringido
 *
 * Sólo este script necesita credenciales de mantenimiento. La web en
 * funcionamiento jamás las utiliza.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Console\Migrator;
use App\Console\Seeder;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;

$opciones = cli_args($argv, ['force', 'no-seed', 'no-images', 'help']);

if (isset($opciones['help'])) {
    echo <<<'TXT'
MotorPress · Instalador

  php scripts/install.php              Instalación completa
  php scripts/install.php --force      Borra y recrea el esquema
  php scripts/install.php --no-seed    Sólo estructura de la base de datos
  php scripts/install.php --no-images  No descarga fotografías

TXT;
    exit(0);
}

$force = isset($opciones['force']);
$conDatos = !isset($opciones['no-seed']);
$conImagenes = !isset($opciones['no-images']);

$paso = static function (string $texto): void {
    echo "\n\033[1m▸ {$texto}\033[0m\n";
};
$ok = static function (string $texto): void {
    echo "  \033[32m✔\033[0m {$texto}\n";
};
$aviso = static function (string $texto): void {
    echo "  \033[33m!\033[0m {$texto}\n";
};

$inicio = microtime(true);

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   MotorPress · Instalación del sistema                 ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";

$host = Env::getString('DB_HOST', '127.0.0.1');
$puerto = Env::getInt('DB_PORT', 3306);
$nombre = Env::required('DB_NAME');
$usuarioApp = Env::required('DB_USER');
$passApp = Env::getString('DB_PASS', '');

if ($passApp === '') {
    fwrite(STDERR, "\n  ✗ DB_PASS no puede estar vacío en .env\n\n");
    exit(1);
}

// ---------------------------------------------------------------------
//  1. Conexión de mantenimiento
// ---------------------------------------------------------------------
$paso("Conexión de mantenimiento ({$host}:{$puerto})");

try {
    $raiz = Migrator::privilegedConnection();
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  ✗ {$e->getMessage()}\n");
    fwrite(STDERR, "    Comprueba DB_ROOT_USER / DB_ROOT_PASS en .env\n\n");
    exit(1);
}
$ok('conectado');

// ---------------------------------------------------------------------
//  2. Base de datos
// ---------------------------------------------------------------------
$paso('Base de datos');

if ($force) {
    $raiz->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '', $nombre) . '`');
    $aviso('esquema anterior eliminado');
}

$raiz->exec(
    'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $nombre) . '`'
    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci'
);

$raiz->exec('USE `' . str_replace('`', '', $nombre) . '`');
$ok("«{$nombre}» lista (utf8mb4 / ci)");

// ---------------------------------------------------------------------
//  3. Usuario de aplicación con privilegios mínimos
//
//  Esta es la medida de seguridad más importante del proyecto: la web
//  NUNCA se conecta como root. El usuario de aplicación sólo puede
//  leer y escribir datos; no puede crear tablas, borrar el esquema,
//  ni conceder privilegios.
// ---------------------------------------------------------------------
$paso('Usuario de aplicación con privilegios mínimos');

$ident = "'" . str_replace("'", "''", $usuarioApp) . "'";
$passSql = "'" . str_replace("'", "''", $passApp) . "'";

// Se elimina primero para garantizar que no arrastra privilegios viejos
foreach (['localhost', '127.0.0.1'] as $hostApp) {
    $raiz->exec("DROP USER IF EXISTS {$ident}@'{$hostApp}'");
}

// El usuario se recrea desde cero: no puede arrastrar privilegios antiguos
$raiz->exec("CREATE USER {$ident}@'localhost' IDENTIFIED BY {$passSql}");
$raiz->exec("CREATE USER {$ident}@'127.0.0.1' IDENTIFIED BY {$passSql}");

$dbSql = '`' . str_replace('`', '', $nombre) . '`';
$privilegios = 'SELECT, INSERT, UPDATE, DELETE, EXECUTE, SHOW VIEW';

$raiz->exec("GRANT {$privilegios} ON {$dbSql}.* TO {$ident}@'localhost'");
$raiz->exec("GRANT {$privilegios} ON {$dbSql}.* TO {$ident}@'127.0.0.1'");
$raiz->exec('FLUSH PRIVILEGES');

// Ninguna cuenta debe ser accesible desde fuera de esta máquina
$sistema = ['mysql.session', 'mysql.sys', 'mysql.infoschema'];
$locales = ['localhost', '127.0.0.1', '::1'];

$remotas = $raiz->query(
    'SELECT user, host FROM mysql.user'
)->fetchAll(\PDO::FETCH_ASSOC);

$eliminadas = [];
foreach ($remotas as $cuenta) {
    $u = (string) $cuenta['user'];
    $h = (string) $cuenta['host'];

    if (in_array($u, $sistema, true) || in_array($h, $locales, true)) {
        continue;
    }

    $raiz->exec("DROP USER IF EXISTS '" . str_replace("'", "''", $u) . "'@'" . str_replace("'", "''", $h) . "'");
    $eliminadas[] = $u . '@' . $h;
}

if ($eliminadas !== []) {
    $aviso('cuentas con acceso remoto eliminadas: ' . implode(', ', $eliminadas));
}

$raiz->exec('FLUSH PRIVILEGES');
$ok("«{$usuarioApp}» con acceso sólo de lectura/escritura de datos");
$ok('ninguna cuenta admite conexiones desde otra máquina');

// ---------------------------------------------------------------------
//  4. Migraciones
// ---------------------------------------------------------------------
$paso('Migraciones');

Database::reset();

try {
    $migrator = (new Migrator(BASE_PATH))->withPdo($raiz);
    $resultado = $migrator->migrate($ok);
    $ok(sprintf(
        '%d aplicada(s) · %d ya estaban',
        count($resultado['aplicadas']),
        $resultado['omitidas']
    ));
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  ✗ {$e->getMessage()}\n\n");
    exit(1);
}

// ---------------------------------------------------------------------
//  5. Fotografía y vídeo
// ---------------------------------------------------------------------
if ($conImagenes) {
    $paso('Fotografías de Wikimedia Commons');

    $salida = [];
    $codigo = 0;
    exec('php ' . escapeshellarg(BASE_PATH . '/scripts/descargar-imagenes.php') . ' 2>&1', $salida, $codigo);
    $ultima = end($salida) ?: '';
    if ($codigo === 0) {
        $ok(trim($ultima));
    } else {
        $aviso('no se pudieron descargar todas las imágenes: ' . trim($ultima));
    }

    // Material adicional de la ficha: galería por tipo de toma y vídeo.
    foreach ([
        ['Galerías de los vehículos', 'descargar-galeria.php'],
        ['Vídeos de los vehículos',   'descargar-videos.php'],
    ] as [$titulo, $script]) {
        $paso($titulo);

        $salida = [];
        $codigo = 0;
        exec('php ' . escapeshellarg(BASE_PATH . '/scripts/' . $script) . ' 2>&1', $salida, $codigo);
        $ultima = end($salida) ?: '';
        if ($codigo === 0) {
            $ok(trim($ultima));
        } else {
            $aviso('falló ' . $script . ': ' . trim($ultima));
        }
    }
} else {
    $aviso('descarga de imágenes y vídeos omitida (--no-images)');
}

// ---------------------------------------------------------------------
//  6. Datos de partida
// ---------------------------------------------------------------------
if ($conDatos) {
    $paso('Datos de partida');

    try {
        $seeder = new Seeder($ok);
        $resumen = $seeder->run();
        $ok(sprintf(
            '%d roles · %d usuarios · %d categorías · %d marcas · %d vehículos · %d artículos · %d imágenes · %d fotos de galería · %d vídeos',
            $resumen['roles'],
            $resumen['usuarios'],
            $resumen['categorias'],
            $resumen['marcas'],
            $resumen['coches'],
            $resumen['noticias'],
            $resumen['imagenes'],
            $resumen['fotos'] ?? 0,
            $resumen['videos'] ?? 0
        ));
    } catch (\Throwable $e) {
        fwrite(STDERR, "\n  ✗ Error cargando datos: {$e->getMessage()}\n");
        fwrite(STDERR, "    {$e->getFile()}:{$e->getLine()}\n\n");
        exit(1);
    }
} else {
    $aviso('datos de partida omitidos (--no-seed)');
}

// ---------------------------------------------------------------------
//  7. Verificación final con el usuario restringido
// ---------------------------------------------------------------------
$paso('Verificación de seguridad');

Database::reset();

try {
    $db = Database::get();
    $tablas = count($db->tables());
    $coches = $db->count('SELECT COUNT(*) FROM coches');
    $noticias = $db->count('SELECT COUNT(*) FROM noticias');

    $privs = $db->select('SHOW GRANTS FOR CURRENT_USER()');
    $permitidos = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'EXECUTE', 'SHOW VIEW', 'USAGE'];
    $excedentes = [];

    foreach ($privs as $fila) {
        $texto = (string) reset($fila);

        if (str_contains($texto, 'WITH GRANT OPTION')) {
            $excedentes[] = 'GRANT OPTION';
        }

        if (preg_match('/\bGRANT (.+?)\s+ON\b/i', $texto, $m) === 1) {
            foreach (explode(',', $m[1]) as $priv) {
                $priv = strtoupper(trim($priv));
                if ($priv !== '' && !in_array($priv, $permitidos, true)) {
                    $excedentes[] = $priv;
                }
            }
        }
    }

    $excedentes = array_values(array_unique($excedentes));

    $ok("conexión con «{$usuarioApp}» · {$tablas} tablas · {$coches} vehículos · {$noticias} artículos");
    $ok($excedentes === []
        ? 'privilegios mínimos confirmados: no puede crear, alterar ni borrar el esquema'
        : 'ATENCIÓN: privilegios inesperados → ' . implode(', ', $excedentes));
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  ✗ La verificación ha fallado: {$e->getMessage()}\n\n");
    exit(1);
}

// ---------------------------------------------------------------------
//  Resumen
// ---------------------------------------------------------------------
$segundos = round(microtime(true) - $inicio, 1);
$url = Env::getString('APP_URL', '');

echo "\n\033[1m────────────────────────────────────────────────────\033[0m\n";
echo "\033[32m✔ Instalación completada en {$segundos} s\033[0m\n\n";
echo "  Sitio        {$url}/\n";
echo "  Catálogo     {$url}/coches\n";
echo "  Admin        {$url}/admin\n";
echo "  API          {$url}/api/v1/coches\n\n";
echo "  Acceso       " . Env::getString('ADMIN_EMAIL', 'admin@motorpress.dev')
    . ' / ' . Env::getString('ADMIN_PASSWORD', 'Admin2026!') . "\n";
echo "  Usuario BD   " . $usuarioApp . " (sólo SELECT/INSERT/UPDATE/DELETE)\n\n";
echo "  \033[33mGuarda las credenciales de .env en un lugar seguro.\033[0m\n";
echo "  \033[33mEl fichero .env está excluido del repositorio.\033[0m\n\n";

Logger::info('Instalación completada', ['segundos' => $segundos]);
