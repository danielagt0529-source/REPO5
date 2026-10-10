<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/** Crea las tablas necesarias si todavía no existen. */
function ensureTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pedidos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            cliente VARCHAR(120) NOT NULL,
            hamburguesa ENUM('Sencilla','Doble','Mixta') NOT NULL DEFAULT 'Sencilla',
            cantidad INT UNSIGNED NOT NULL DEFAULT 1,
            direccion_entrega VARCHAR(255) NOT NULL,
            propina DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            estado ENUM('Recibido','En preparación','En camino','Entregado','Cancelado') NOT NULL DEFAULT 'Recibido',
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pedidos_creado (creado_en),
            INDEX idx_pedidos_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
