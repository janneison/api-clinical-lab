<?php

namespace ClinicalLab\Infrastructure\Persistence;

use PDO;

class PdoConnectionFactory
{
    public static function fromEnv(): PDO
    {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME');
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }
}
