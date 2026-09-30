<?php
declare(strict_types=1);

/** Shared PDO connection to the storefront's own database. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        // All timestamps are stored in UTC.
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

/** Runs a prepared statement and returns it. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Current UTC time as a MySQL DATETIME(3) string. */
function db_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
}

/** Converts an ISO 8601 timestamp (any offset) to a UTC DATETIME(3) string, or null if unparseable. */
function db_datetime(?string $iso): ?string
{
    if ($iso === null || $iso === '') return null;
    try {
        $d = new DateTimeImmutable($iso);
    } catch (Exception $e) {
        return null;
    }
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
}
