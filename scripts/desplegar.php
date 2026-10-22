<?php

/**
 * =====================================================================
 *  MotorPress · Comprobación de despliegue
 * =====================================================================
 *
 *   php scripts/desplegar.php                      revisión del entorno local
 *   php scripts/desplegar.php --produccion         más exigente (producción real)
 *   php scripts/desplegar.php --url=https://…      mide el sitio ya publicado
 *
 * Comprueba, sin tocar nada, todo lo que suele fallar al publicar:
 * versión de PHP y extensiones, contenido del .env, accesos de escritura,
 * migraciones aplicadas y sin alterar, cuenta de administración, copia de
 * seguridad reciente y un puñado de rutas críticas.
 *
 * Devuelve 0 si todo está en verde y 1 si hay algún error, de modo que
 * puede usarse como paso previo de cualquier despliegue automatizado.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Toda la salida queda bufferizada: el despacho de rutas necesita poder abrir
// sesión más adelante y la sesión no admite que se haya enviado salida antes.
ob_start();

/** Cierra el búfer de salida emitiendo todo lo acumulado. */
$volcar = static function (): void {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
};

use App\Core\App;
use App\Core\Env;
use App\Core\Request;

$opciones = cli_args($argv, ['produccion', 'url::', 'help']);

if (isset($opciones['help'])) {
    echo <<<'TXT'
MotorPress · Comprobación de despliegue

  php scripts/desplegar.php                  revisión del entorno local
  php scripts/desplegar.php --produccion     exige configuración de producción
  php scripts/desplegar.php --url=URL        comprueba el sitio publicado por HTTP

TXT;
    $volcar();
    exit(0);
}

$produccion = isset($opciones['produccion']);
$errores = 0;
$avisos = 0;
$correctos = 0;

$ok = static function (string $texto) use (&$correctos): void {
    $correctos++;
    echo "  \033[32m✔\033[0m {$texto}\n";
};

$mal = static function (string $texto) use (&$errores): void {
    $errores++;
    echo "  \033[31m✗\033[0m {$texto}\n";
};

$aviso = static function (string $texto) use (&$avisos): void {
    $avisos++;
    echo "  \033[33m·\033[0m {$texto}\n";
};

$titulo = static function (string $texto): void {
    echo "\n  \033[1m{$texto}\033[0m\n";
};

// =====================================================================
//  Modo HTTP: el sitio ya está publicado
// =====================================================================

if (isset($opciones['url'])) {
    $base = rtrim((string) $opciones['url'], '/');

    if (!preg_match('#^https?://#', $base)) {
        fwrite(STDERR, "  ✗ La URL debe empezar por http:// o https://\n");
        exit(1);
    }

    /** @return array{estado:int, cab:array<string,string>, cuerpo:string} */
    $pedir = static function (string $url): array {
        $contexto = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 20,
                'ignore_errors' => true,
                'header'  => "User-Agent: MotorPress-desplegar/1.0\r\nAccept: text/html,application/json\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $cuerpo = @file_get_contents($url, false, $contexto);
        $lineas = $http_response_header ?? [];

        $estado = 0;
        $cab = [];

        foreach ($lineas as $i => $linea) {
            if ($i === 0 && preg_match('#HTTP/\S+\s+(\d{3})#', $linea, $m)) {
                $estado = (int) $m[1];
                continue;
            }

            $sep = strpos($linea, ':');
            if ($sep !== false) {
                $cab[strtolower(trim(substr($linea, 0, $sep)))] = trim(substr($linea, $sep + 1));
            }
        }

        return ['estado' => $estado, 'cab' => $cab, 'cuerpo' => (string) $cuerpo];
    };

    echo "┌──────────────────────────────────────────────────────┐\n";
    echo "│  MotorPress · Sitio publicado                        │\n";
    echo "└──────────────────────────────────────────────────────┘\n";
    echo "\n  {$base}\n";

    $titulo('API y esquema');

    $salud = $pedir($base . '/api/v1/salud');
    $json = json_decode($salud['cuerpo'], true);

    if ($salud['estado'] === 200 && is_array($json) && ($json['ok'] ?? false) === true) {
        $datos = is_array($json['data'] ?? null) ? $json['data'] : [];
        $aplicadas = (int) ($datos['migraciones'] ?? 0);
        $phpRemoto = (string) ($datos['php'] ?? '?');
        $mysqlRemoto = (string) ($datos['base_datos'] ?? '?');
        $archivos = count(glob(__DIR__ . '/../database/migrations/*.sql') ?: []);
        $ok("API responde con «ok» · PHP {$phpRemoto} · MySQL {$mysqlRemoto}");

        if ($aplicadas >= $archivos) {
            $ok("Migraciones aplicadas: {$aplicadas}/{$archivos}");
        } else {
            $mal("Sólo {$aplicadas} de {$archivos} migraciones aplicadas en el servidor");
        }
    } else {
        $mal("GET /api/v1/salud → {$salud['estado']} (se esperaba 200 con «ok»)");
    }

    $titulo('Portada y cabeceras');

    $portada = $pedir($base . '/');

    if ($portada['estado'] === 200 && str_contains($portada['cuerpo'], '<title>')) {
        $ok("GET / → 200 con contenido");
    } else {
        $mal("GET / → {$portada['estado']}");
    }

    foreach (['x-content-type-options' => 'nosniff', 'x-frame-options' => null, 'content-security-policy' => 'default-src'] as $cab => $esperado) {
        $valor = $portada['cab'][$cab] ?? '';
        if ($valor !== '' && ($esperado === null || str_contains($valor, $esperado))) {
            $ok("{$cab}: " . substr($valor, 0, 60));
        } else {
            $mal("Falta o no cuadra la cabecera {$cab}");
        }
    }

    if (!isset($portada['cab']['x-powered-by'])) {
        $ok("Sin cabecera X-Powered-By (no se filtra la versión de PHP)");
    } else {
        $aviso("El servidor publica X-Powered-By: revisa `Header unset X-Powered-By` en .htaccess");
    }

    $titulo('Puerta de acceso');

    $acceso = $pedir($base . '/acceso');

    if ($acceso['estado'] === 200) {
        $ok("GET /acceso → 200");
    } else {
        $mal("GET /acceso → {$acceso['estado']}");
    }

    if (str_contains($acceso['cuerpo'], 'Acceso restringido')) {
        $ok("Registro cerrado: se ofrece acceso restringido");
    } else {
        $aviso("No se reconoce el bloque «Acceso restringido» en /acceso");
    }

    if (str_contains($acceso['cuerpo'], 'Admin2026')) {
        $mal("La página de acceso publica credenciales de demostración");
    } else {
        $ok("Sin credenciales visibles en la página de acceso");
    }

    $titulo('Barreras del panel');

    $registro = $pedir($base . '/registro');
    if ($registro['estado'] === 404) {
        $ok("GET /registro → 404 (registro de cuentas cerrado)");
    } else {
        $mal("GET /registro → {$registro['estado']} (se esperaba 404): ¿REGISTRO_ABIERTO=false?");
    }

    $panel = $pedir($base . '/admin');
    if ($panel['estado'] === 401) {
        $ok("GET /admin sin sesión → 401 (cerrado a forasteros)");
    } else {
        $mal("GET /admin sin sesión → {$panel['estado']} (se esperaba 401)");
    }

    $deny = strtolower($panel['cab']['x-frame-options'] ?? '');
    if ($deny === 'deny') {
        $ok("X-Frame-Options: DENY en el panel");
    } else {
        $mal("El panel no envía X-Frame-Options: DENY (llega: «" . ($panel['cab']['x-frame-options'] ?? 'nada') . "»)");
    }

    if (str_contains(strtolower($panel['cab']['referrer-policy'] ?? ''), 'no-referrer')) {
        $ok("Referrer-Policy: no-referrer en el panel");
    } else {
        $aviso("El panel no envía Referrer-Policy: no-referrer");
    }

    if (str_contains(strtolower($panel['cab']['cache-control'] ?? ''), 'no-store')) {
        $ok("Cache-Control: no-store en el panel");
    } else {
        $aviso("El panel no envía Cache-Control: no-store");
    }

    $titulo('HTTPS');

    if (str_starts_with($base, 'https://')) {
        $ok("El sitio se sirve por HTTPS");
    } else {
        $redir = $pedir($base . '/');
        $aviso("Estás midiendo por HTTP: si ya tienes certificado, comprueba que http→https redirige");
    }

    echo "\n";
    $titulo("Resultado: {$correctos} correctos · {$avisos} avisos · {$errores} errores\n");
    $volcar();
    exit($errores > 0 ? 1 : 0);
}

// =====================================================================
//  Modo local: el entorno está listo para publicarse
// =====================================================================

echo "┌──────────────────────────────────────────────────────┐\n";
echo "│  MotorPress · Comprobación de despliegue             │\n";
echo "└──────────────────────────────────────────────────────┘\n";
echo $produccion ? "\n  Modo: PRODUCCIÓN (exigente)\n" : "\n  Modo: revisión local\n";

$titulo('Servidor PHP');

if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    $ok('PHP ' . PHP_VERSION . ' (mínimo 8.1)');
} else {
    $mal('PHP ' . PHP_VERSION . ' es anterior a 8.1');
}

$faltan = array_filter(['pdo_mysql', 'mbstring', 'json', 'dom', 'openssl', 'fileinfo'], static fn (string $e): bool => !extension_loaded($e));

if ($faltan === []) {
    $ok('Extensiones requeridas: pdo_mysql, mbstring, json, dom, openssl, fileinfo');
} else {
    $mal('Faltan extensiones: ' . implode(', ', $faltan));
}

$titulo('Configuración (.env)');

$raiz = dirname(__DIR__);

if (is_file($raiz . '/.env')) {
    $ok('.env presente (y fuera del repositorio: ' . (is_file($raiz . '/.gitignore') && str_contains((string) @file_get_contents($raiz . '/.gitignore'), '.env') ? 'ignorado ✔' : 'REVISAR .gitignore') . ')');
} else {
    $mal('No existe .env: copia .env.example y rellénalo');
}

$appKey = Env::getString('APP_KEY', '');
strlen($appKey) >= 32 ? $ok('APP_KEY configurada (' . strlen($appKey) . ' caracteres)') : $mal('APP_KEY vacía o demasiado corta (hacen falta 32+ caracteres aleatorios)');

$appUrl = Env::getString('APP_URL', '');
if ($appUrl === '') {
    $mal('APP_URL vacía');
} elseif ($produccion && !str_starts_with($appUrl, 'https://')) {
    $mal("APP_URL no es HTTPS: {$appUrl}");
} else {
    $ok("APP_URL = {$appUrl}");
}

$entorno = Env::getString('APP_ENV', 'local');
$debug = Env::getBool('APP_DEBUG', false);

if ($produccion) {
    $entorno === 'production' ? $ok('APP_ENV=production') : $mal("APP_ENV={$entorno}: debería ser «production»");
    $debug ? $mal('APP_DEBUG=true en producción: expone trazas de errores') : $ok('APP_DEBUG=false');
} else {
    $debug ? $aviso('APP_DEBUG=true (correcto en local, cámbialo al publicar)') : $ok('APP_DEBUG=false');
    $entorno === 'production' ? $ok('APP_ENV=production') : $aviso("APP_ENV={$entorno} (en producción debe ser «production»)");
}

foreach (['DB_HOST' => 'host de base de datos', 'DB_NAME' => 'esquema', 'DB_USER' => 'usuario de aplicación'] as $var => $que) {
    Env::getString($var, '') !== '' ? true : $mal("Falta {$var} ({$que})");
}

$adminEmail = Env::getString('ADMIN_EMAIL', '');
$adminEmail !== '' ? $ok("ADMIN_EMAIL = {$adminEmail} (lista blanca del panel)") : $mal('Falta ADMIN_EMAIL: sin él no entra nadie en el panel');

$hash = Env::getString('PANEL_CLAVE_HASH', '');
if ($hash === '') {
    $mal('Falta PANEL_CLAVE_HASH: genera la clave maestra con `php scripts/panel_clave.php`');
} elseif (str_starts_with($hash, '$argon2id$')) {
    $ok('PANEL_CLAVE_HASH presente (Argon2id)');
} else {
    $mal('PANEL_CLAVE_HASH no parece un hash Argon2id');
}

$panelNivel = Env::getInt('PANEL_NIVEL', 0);
$panelNivel >= 90 ? $ok("PANEL_NIVEL={$panelNivel}") : $aviso("PANEL_NIVEL={$panelNivel}: el panel admite niveles por debajo de administrador");

(int) Env::getInt('PANEL_INACTIVIDAD', 0) > 0 ? $ok('PANEL_INACTIVIDAD=' . Env::getInt('PANEL_INACTIVIDAD', 0) . ' s de cierre por inactividad') : $mal('Falta PANEL_INACTIVIDAD');

$registroAbierto = Env::getBool('REGISTRO_ABIERTO', true);
if ($registroAbierto) {
    $produccion ? $mal('REGISTRO_ABIERTO=true: cualquiera puede crear cuentas') : $aviso('REGISTRO_ABIERTO=true (cámbialo a false al publicar)');
} else {
    $ok('REGISTRO_ABIERTO=false (registro de cuentas cerrado)');
}

$titulo('Base de datos');

try {
    $db = \App\Core\Database::get();
    $version = (string) $db->scalar('SELECT VERSION()');
    $ok("Conexión correcta · MySQL {$version}");

    $archivos = array_map('basename', glob(__DIR__ . '/../database/migrations/*.sql') ?: []);
    $aplicadas = $db->select('SELECT archivo, checksum FROM migrations');
    $porNombre = array_column($aplicadas, 'checksum', 'archivo');

    $pendientes = array_diff($archivos, array_keys($porNombre));

    if ($pendientes === []) {
        $ok(count($archivos) . ' migraciones aplicadas, ninguna pendiente');
    } else {
        $mal('Migraciones pendientes: ' . implode(', ', $pendientes) . ' → `php scripts/migrate.php`');
    }

    $alteradas = [];
    foreach (array_intersect($archivos, array_keys($porNombre)) as $archivo) {
        $ruta = __DIR__ . '/../database/migrations/' . $archivo;
        if (is_file($ruta)) {
            $contenido = str_replace("\r\n", "\n", (string) file_get_contents($ruta));
            if (md5($contenido) !== $porNombre[$archivo]) {
                $alteradas[] = $archivo;
            }
        }
    }

    $alteradas === [] ? $ok('Ninguna migración modificada tras aplicarse (checksums correctos)') : $aviso('Migraciones alteradas en disco: ' . implode(', ', $alteradas));

    // -------------------------------------------------------------
    //  Objetos del esquema: vistas, rutinas y disparadores
    //
    //  Son imprescindibles: el panel da de alta artículos y vehículos
    //  con `slug` y `uuid` vacíos y son los disparadores los que los
    //  rellenan. El usuario de la aplicación no tiene privilegio
    //  `TRIGGER` (no los ve en information_schema), así que sólo el
    //  usuario privilegiado puede comprobar que siguen ahí.
    // -------------------------------------------------------------

    $crea = [
        'VIEW'    => '/\bCREATE\s+(?:OR\s+REPLACE\s+)?(?:DEFINER=`[^`]+`@`[^`]+`\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?VIEW\s+`?(\w+)`?/i',
        'ROUTINE' => '/\bCREATE\s+(?:OR\s+REPLACE\s+)?(?:DEFINER=`[^`]+`@`[^`]+`\s+)?(?:FUNCTION|PROCEDURE)\s+`?(\w+)`?/i',
        'TRIGGER' => '/\bCREATE\s+(?:DEFINER=`[^`]+`@`[^`]+`\s+)?TRIGGER\s+`?(\w+)`?/i',
    ];
    $borra = [
        'VIEW'    => '/\bDROP\s+VIEW\s+(?:IF\s+EXISTS\s+)?`?(\w+)`?/i',
        'ROUTINE' => '/\bDROP\s+(?:PROCEDURE|FUNCTION)\s+(?:IF\s+EXISTS\s+)?`?(\w+)`?/i',
        'TRIGGER' => '/\bDROP\s+TRIGGER\s+(?:IF\s+EXISTS\s+)?`?(\w+)`?/i',
    ];

    /** @var array<string,array<string,string>> */
    $esperados = ['VIEW' => [], 'ROUTINE' => [], 'TRIGGER' => []];

    foreach ($archivos as $archivo) {
        $ruta = __DIR__ . '/../database/migrations/' . $archivo;
        if (!is_file($ruta)) {
            continue;
        }
        $sql = str_replace("\r\n", "\n", (string) file_get_contents($ruta));

        foreach ($borra as $tipo => $patron) {
            if (preg_match_all($patron, $sql, $m)) {
                foreach ($m[1] as $nombre) {
                    unset($esperados[$tipo][strtolower($nombre)]);
                }
            }
        }
        foreach ($crea as $tipo => $patron) {
            if (preg_match_all($patron, $sql, $m)) {
                foreach ($m[1] as $nombre) {
                    $esperados[$tipo][strtolower($nombre)] = $archivo;
                }
            }
        }
    }

    $tablaInfo = ['VIEW' => 'views', 'ROUTINE' => 'routines', 'TRIGGER' => 'triggers'];
    $campoInfo = ['VIEW' => 'table_name', 'ROUTINE' => 'routine_name', 'TRIGGER' => 'trigger_name'];
    $esquemaInfo = ['VIEW' => 'table_schema', 'ROUTINE' => 'routine_schema', 'TRIGGER' => 'trigger_schema'];
    $esquemaNombre = Env::getString('DB_NAME', '');

    try {
        $mantenimiento = \App\Console\Migrator::privilegedConnection();
        $faltan = [];

        foreach ($esperados as $tipo => $nombres) {
            $consulta = "SELECT {$campoInfo[$tipo]} AS nombre FROM information_schema.{$tablaInfo[$tipo]} "
                      . "WHERE {$esquemaInfo[$tipo]} = :db";
            $stmt = $mantenimiento->prepare($consulta);
            $stmt->execute(['db' => $esquemaNombre]);
            $existentes = array_map('strtolower', array_column($stmt->fetchAll(), 'nombre'));

            foreach ($nombres as $nombre => $origen) {
                if (!in_array($nombre, $existentes, true)) {
                    $faltan[] = strtoupper($tipo) . ' ' . $nombre . ' (' . $origen . ')';
                }
            }
        }

        if ($faltan === []) {
            $ok(sprintf(
                'Esquema completo: %d vistas, %d rutinas y %d disparadores (comprobado como %s)',
                count($esperados['VIEW']),
                count($esperados['ROUTINE']),
                count($esperados['TRIGGER']),
                Env::getString('DB_ROOT_USER', '')
            ));
        } else {
            $mal('Faltan objetos del esquema: ' . implode(', ', $faltan)
                . ' → restaura una copia reciente (scripts/restaurar.php)');
        }
    } catch (Throwable $e) {
        $aviso('Objetos del esquema sin verificar (hacen falta DB_ROOT_USER/DB_ROOT_PASS): ' . $e->getMessage());
    }

    $activas = (int) $db->scalar('SELECT COUNT(*) FROM usuarios WHERE estado = \'activo\' AND borrado_en IS NULL');
    $admin = $db->first(
        'SELECT u.email, u.estado, r.nivel FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.email = ?',
        [$adminEmail]
    );

    if ($admin === null) {
        $mal("No existe ninguna cuenta con ADMIN_EMAIL ({$adminEmail})");
    } elseif ($admin['estado'] !== 'activo') {
        $mal("La cuenta de ADMIN_EMAIL está «{$admin['estado']}»");
    } elseif ((int) $admin['nivel'] < max(90, $panelNivel)) {
        $mal('La cuenta de ADMIN_EMAIL tiene nivel ' . (int) $admin['nivel'] . ' (hace falta ≥ 90)');
    } else {
        $ok("Cuenta de ADMIN_EMAIL activa con nivel {$admin['nivel']}");
    }

    if ($activas === 1) {
        $ok('Sólo hay 1 cuenta activa en la plataforma');
    } elseif ($produccion) {
        $aviso("Hay {$activas} cuentas activas: revisa si todas deben seguirlo");
    } else {
        $aviso("Hay {$activas} cuentas activas");
    }
} catch (Throwable $e) {
    $mal('Sin conexión a la base de datos: ' . $e->getMessage());
}

$titulo('Ficheros y permisos');

foreach (['storage', 'storage/sessions', 'storage/logs', 'storage/cache'] as $dir) {
    $ruta = $raiz . '/' . $dir;
    if (!is_dir($ruta)) {
        $mal("No existe {$dir}/");
    } elseif (!is_writable($ruta)) {
        $mal("{$dir}/ no es escribible para el servidor");
    } else {
        $ok("{$dir}/ escribible");
    }
}

$subidas = $raiz . '/public/assets/img/subidas';
if (is_dir($subidas)) {
    is_writable($subidas) ? $ok('public/assets/img/subidas/ escribible') : $mal('public/assets/img/subidas/ no es escribible');
}

foreach (['.htaccess' => 'raíz del proyecto', 'public/.htaccess' => 'directorio público', 'public/index.php' => 'front controller'] as $f => $que) {
    is_file($raiz . '/' . $f) ? $ok("{$f} presente ({$que})") : $mal("Falta {$f}");
}

$respaldos = glob($raiz . '/backups/*.sql.gz') ?: [];
if ($respaldos === []) {
    $aviso('Todavía no hay ninguna copia de seguridad → `php scripts/backup.php`');
} else {
    usort($respaldos, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
    $dias = (time() - filemtime($respaldos[0])) / 86400;
    $dias < 7
        ? $ok('Última copia de seguridad: hace ' . (int) $dias . ' día(s) (' . basename($respaldos[0]) . ')')
        : $aviso('La última copia tiene ' . (int) $dias . ' días: genera otra con `php scripts/backup.php`');
}

$titulo('Rutas críticas');

try {
    $app = App::boot();

    $comprobar = static function (string $uri, int $esperado, string $descripcion) use ($app, $ok, $mal): void {
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html'],
            [], [], []
        );

        ob_start();
        try {
            $estado = $app->dispatch($request)->getStatus();
        } catch (Throwable $e) {
            $estado = 599;
        }
        $emitido = (string) ob_get_clean();

        if ($estado === $esperado && $emitido === '') {
            $ok("{$uri} → {$estado} · {$descripcion}");
        } else {
            $mal("{$uri} → {$estado} (esperado {$esperado})" . ($emitido !== '' ? ' · avisos de PHP: ' . substr(trim($emitido), 0, 120) : ''));
        }
    };

    $comprobar('/', 200, 'portada');
    $comprobar('/acceso', 200, 'puerta de acceso');
    $comprobar('/api/v1/salud', 200, 'API de salud');
    $comprobar('/registro', $registroAbierto ? 200 : 404, $registroAbierto ? 'registro abierto' : 'registro cerrado');
    $comprobar('/admin', 401, 'panel cerrado sin sesión');
} catch (Throwable $e) {
    $mal('No se pudieron ejecutar las rutas: ' . $e->getMessage());
}

echo "\n";
$titulo("Resultado: {$correctos} correctos · {$avisos} avisos · {$errores} errores");

if ($errores === 0) {
    echo "  \033[32m✔ Listo para desplegar.\033[0m\n";
    if ($produccion) {
        echo "  Recuerda: sube el código SIN .env, importa la copia de backup.php\n";
        echo "  y vuelve a comprobar con:  php scripts/desplegar.php --url=https://tudominio.com\n";
    }
} else {
    echo "  \033[31m✗ Hay " . $errores . ' problema(s) que resolver antes de publicar.' . "\033[0m\n";
}

echo "\n";
$volcar();
exit($errores > 0 ? 1 : 0);
