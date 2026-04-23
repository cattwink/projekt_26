<?php

function connectDb(): PDO {
    $host = 'localhost';
    $dbname = 'ubytovani';
    $username = 'root';
    $password = 'admin';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException('Database connection failed: ' . $e->getMessage());
    }
}
