<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;

/**
 * Ejecutor de migraciones SQL.
 *
 * Cada fichero de database/migrations/ se aplica UNA sola vez y queda
 * registrado con su checksum en la tabla `migrations`. Si se modifica
 * un fichero ya aplicado se avisa (para forzarlo hay que eliminar su
 * registro).
 *
 * El parser entiende la directiva DELIMITER de la consola de MySQL,
 * imprescindible para los cuerpos de disparadores y procedimientos que
 * contienen punto y coma internos.
 */
final class Migrator
{
    private const DIR = 'database/migrations';

    private readonly string $baseDir;

    private ?\PDO $pdo = null;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir ?? dirname(__DIR__, 2);
    }

    /** Inyecta una conexión ya abierta (la usa el instalador). */
    public function withPdo(\PDO $pdo): static
    {
        $this->pdo = $pdo;
        return $this;
    }

    /**
     * Conexión con privilegios de mantenimiento.
     *
     * Las migraciones ejecutan DDL (CREATE VIEW, TRIGGER, PROCEDURE),
     * algo que el usuario de aplicación NO puede ni debe hacer. Por eso
     * este objeto nunca usa la conexión de la web.
     */
    public function pdo(): \PDO
    {
        return $this->pdo ??= self::privilegedConnection();
    }

    /** @return array<string,string> fichero => checksum */
    public function files(): array
    {
        $files = glob($this->baseDir . '/' . self::DIR . '/*.sql') ?: [];
        sort($files);

        $out = [];
        foreach ($files as $file) {
            $out[basename($file)] = (string) md5_file($file);
        }

        return $out;
    }

    /**
     * Aplica las migraciones pendientes.
     *
     * @param callable(string):void|null $out  Progreso para la consola
     * @return array{aplicadas:array<int,string>, omitidas:int}
     */
    public function migrate(?callable $out = null): array
    {
        $pdo = $this->pdo();
        $this->ensureTable();

        $aplicadas = [];
        $omitidas = 0;
        $existentes = $this->applied();

        foreach ($this->files() as $archivo => $checksum) {
            if (isset($existentes[$archivo])) {
                if ($existentes[$archivo] !== $checksum) {
                    $message = "AVISO: {$archivo} cambió después de aplicarse; los cambios NO se han ejecutado.";
                    $out && $out($message);
                    Logger::warning($message);
                }
                $omitidas++;
                continue;
            }

            $ruta = $this->baseDir . '/' . self::DIR . '/' . $archivo;
            $sql = (string) file_get_contents($ruta);

            $out && $out("Aplicando {$archivo}…");
            $inicio = microtime(true);

            try {
                foreach ($this->split($sql) as $statement) {
                    if ($statement === '') {
                        continue;
                    }
                    $pdo->exec($statement);
                }
            } catch (\Throwable $e) {
                Logger::error("Fallo en {$archivo}: " . $e->getMessage());
                throw new \RuntimeException(
                    "La migración {$archivo} ha fallado: " . $e->getMessage(),
                    0,
                    $e
                );
            }

            $ms = (int) round((microtime(true) - $inicio) * 1000);

            $stmt = $pdo->prepare(
                'INSERT INTO migrations (archivo, checksum, duracion_ms) VALUES (:archivo, :checksum, :ms)'
            );
            $stmt->execute([
                'archivo'  => $archivo,
                'checksum' => $checksum,
                'ms'       => min(65535, $ms),
            ]);

            $aplicadas[] = $archivo;
            $out && $out("  ✔ lista ({$ms} ms)");
        }

        return ['aplicadas' => $aplicadas, 'omitidas' => $omitidas];
    }

    /**
     * Estado detallado de las migraciones ya registradas.
     *
     * @return array<string,array{checksum:string,aplicada_en:string,duracion_ms:int}>
     */
    public function status(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT archivo, checksum, aplicada_en, duracion_ms FROM migrations ORDER BY archivo'
        );

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['archivo']] = [
                'checksum'    => (string) $row['checksum'],
                'aplicada_en' => (string) $row['aplicada_en'],
                'duracion_ms' => (int) $row['duracion_ms'],
            ];
        }

        return $out;
    }

    /** @return array<string,string> archivo => checksum */
    public function applied(): array
    {
        $stmt = $this->pdo()->query('SELECT archivo, checksum FROM migrations ORDER BY archivo');

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['archivo']] = (string) $row['checksum'];
        }

        return $out;
    }

    private function ensureTable(): void
    {
        $this->pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
                archivo     VARCHAR(120)      NOT NULL,
                checksum    CHAR(32)          NOT NULL,
                aplicada_en DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
                duracion_ms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_archivo (archivo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
            SQL);
    }

    /**
     * Divide el script en sentencias atendiendo a DELIMITER.
     *
     * @return array<int,string>
     */
    public function split(string $sql): array
    {
        $statements = [];
        $delimiter = ';';
        $buffer = '';

        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m)) {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                    $buffer = '';
                }
                $delimiter = $m[1];
                continue;
            }

            $buffer .= $line . "\n";

            // ¿Termina ya la sentencia actual con el delimitador vigente?
            $trimmed = rtrim($buffer);
            if ($delimiter !== '' && str_ends_with($trimmed, $delimiter)) {
                $statements[] = trim(substr($trimmed, 0, -strlen($delimiter)));
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        // Se descartan los bloques que sólo contienen comentarios:
        // MySQL responde "Query was empty" (1065) si se le envían.
        return array_values(array_filter(
            $statements,
            static fn (string $s): bool => trim((string) preg_replace('/^\s*--.*$/m', '', $s)) !== ''
        ));
    }

    /**
     * Conexión temporal con privilegios ampliados (sólo para el
     * instalador). Nunca se usa en la petición web.
     */
    public static function privilegedConnection(): \PDO
    {
        $host = Env::getString('DB_HOST', '127.0.0.1');
        $port = Env::getInt('DB_PORT', 3306);
        $user = Env::getString('DB_ROOT_USER', '');
        $pass = Env::getString('DB_ROOT_PASS', '');

        if ($user === '') {
            throw new \RuntimeException(
                'Faltan DB_ROOT_USER y DB_ROOT_PASS en .env (credenciales de mantenimiento).'
            );
        }

        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);

        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Selecciona el esquema si ya existe (si no, lo hará el instalador)
        $nombre = Env::getString('DB_NAME', '');
        if ($nombre !== '') {
            $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$nombre]);
            if ($stmt->fetch() !== false) {
                $pdo->exec('USE `' . str_replace('`', '', $nombre) . '`');
            }
        }

        return $pdo;
    }
}
