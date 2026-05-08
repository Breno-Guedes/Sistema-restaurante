<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/sqlite.php';

// Caminho do banco de dados
define('DB_PATH', __DIR__ . '/../database/restaurante.db');

// Inicializar banco (cria schema + triggers, sem fazer seed novamente)
$pdo = initialize_database(DB_PATH, false);

// Função auxiliar para executar queries
function query_one(string $sql, array $params = []): ?array
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function query_all(string $sql, array $params = []): array
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function execute_query(string $sql, array $params = []): int
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function get_last_insert_id(): int
{
    global $pdo;
    return (int) $pdo->lastInsertId();
}
