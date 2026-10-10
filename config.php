<?php
declare(strict_types=1);

/*
 * Configuración de la base de datos.
 * Si publicas el proyecto en un repositorio, mueve estas credenciales a variables
 * de entorno y no compartas este archivo.
 */
const DB_HOST = 'mysql-danigt.alwaysdata.net';
const DB_NAME = 'danigt_hambugers';
const DB_USER = 'danigt';
const DB_PASS = 'clase1234';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
