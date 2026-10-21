<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Acceso a datos sobre PDO/MySQL 8.
 *
 * Todas las consultas pasan por consultas preparadas con parámetros
 * vinculados: no es posible inyectar SQL desde la aplicación.
 * La conexión se hace con un usuario de MÍNIMOS privilegios
 * (SELECT/INSERT/UPDATE/DELETE) que se crea en la instalación.
 */
final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;

    private function __construct()
    {
        $host = Env::getString('DB_HOST', '127.0.0.1');
        $port = Env::getInt('DB_PORT', 3306);
        $name = Env::required('DB_NAME');
        $user = Env::required('DB_USER');
        $pass = Env::getString('DB_PASS', '');
        $charset = Env::getString('DB_CHARSET', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // consultas preparadas reales
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => false,
            ]);
        } catch (PDOException $e) {
            Logger::error('No se pudo conectar a MySQL: ' . $e->getMessage());
            throw new \RuntimeException(
                'No se pudo conectar a la base de datos. Comprueba la configuración en .env y que MySQL esté en marcha.',
                0,
                $e
            );
        }
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    /** Reinicia la conexión (útil tras cambiar de esquema en el instalador). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // -----------------------------------------------------------------
    //  Consultas
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<int,array<string,mixed>> */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function count(string $sql, array $params = []): int
    {
        return (int) $this->scalar($sql, $params);
    }

    /** @param array<string,mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * INSERT con devolución del id generado.
     *
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $quoted = array_map(static fn (string $c): string => '`' . str_replace('`', '', $c) . '`', $columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            str_replace('`', '', $table),
            implode(', ', $quoted),
            implode(', ', $placeholders)
        );

        $this->run($sql, $data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * UPDATE parametrizado por columna.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = [];
        foreach ($data as $column => $_) {
            $set[] = '`' . str_replace('`', '', $column) . '` = :' . $column;
        }

        $cond = [];
        foreach ($where as $column => $_) {
            $cond[] = '`' . str_replace('`', '', $column) . '` = :w_' . $column;
        }

        $params = [];
        foreach ($data as $k => $v) {
            $params[$k] = $v;
        }
        foreach ($where as $k => $v) {
            $params['w_' . $k] = $v;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            str_replace('`', '', $table),
            implode(', ', $set),
            implode(' AND ', $cond)
        );

        return $this->execute($sql, $params);
    }

    public function delete(string $table, array $where): int
    {
        $cond = [];
        foreach ($where as $column => $_) {
            $cond[] = '`' . str_replace('`', '', $column) . '` = :' . $column;
        }

        return $this->execute(
            'DELETE FROM `' . str_replace('`', '', $table) . '` WHERE ' . implode(' AND ', $cond),
            $where
        );
    }

    // -----------------------------------------------------------------
    //  Transacciones
    // -----------------------------------------------------------------

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Ejecuta un callback dentro de una transacción. Si lanza una
     * excepción, se revierte todo y se relanza.
     *
     * @template T
     * @param callable(PDO):T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        $this->begin();
        try {
            $result = $callback($this->pdo);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // -----------------------------------------------------------------
    //  Metadatos (migraciones e instalador)
    // -----------------------------------------------------------------

    /** @return array<string,bool> */
    public function tables(): array
    {
        $rows = $this->select('SHOW TABLES');
        $tables = [];
        foreach ($rows as $row) {
            $value = array_values($row)[0];
            $tables[(string) $value] = true;
        }

        return $tables;
    }

    public function tableExists(string $table): bool
    {
        return isset($this->tables()[$table]);
    }

    /** Cambia el esquema activo (usado por el instalador). */
    public function useDatabase(string $name): void
    {
        $this->pdo->exec('USE `' . str_replace('`', '', $name) . '`');
    }

    public function execRaw(string $sql): void
    {
        $this->pdo->exec($sql);
    }
}
