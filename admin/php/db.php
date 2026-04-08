<?php
// AntiSpam Shield — Database Connection (SQLite / PostgreSQL)

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $provider = getenv('DB_PROVIDER') ?: 'sqlite';

        if ($provider === 'postgres') {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_PORT') ?: '5432',
                getenv('DB_NAME') ?: 'antispam');
            $pdo = new PDO($dsn,
                getenv('DB_USER') ?: 'antispam',
                getenv('DB_PASSWORD') ?: '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
        } elseif ($provider === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_NAME') ?: 'antispam');
            $pdo = new PDO($dsn,
                getenv('DB_USER') ?: 'antispam',
                getenv('DB_PASSWORD') ?: '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
        } else {
            $dbDir = getenv('ANTISPAM_DATA_DIR') ?: realpath(__DIR__ . '/../../data');
            $pdo = new PDO('sqlite:' . $dbDir . '/antispam.db', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
    }
    return $pdo;
}
