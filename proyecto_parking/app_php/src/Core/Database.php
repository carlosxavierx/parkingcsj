<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Connexió única a MariaDB amb PDO.
 * - Sentències preparades reals (EMULATE_PREPARES = false) contra la injecció SQL.
 * - Errors com a excepcions; mai es mostren a l'usuari.
 * - L'usuari de BD (csj_app) només té SELECT, INSERT i UPDATE: res es pot esborrar.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $db = Config::get('db');
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Mateixa zona horària que PHP (Europe/Madrid)
            self::$pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
        }
        return self::$pdo;
    }

    /** Executa una consulta preparada i retorna el PDOStatement. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $fila = self::run($sql, $params)->fetch();
        return $fila === false ? null : $fila;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /** Executa $fn dins d'una transacció (commit o rollback automàtic). */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $resultat = $fn($pdo);
            $pdo->commit();
            return $resultat;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
