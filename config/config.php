<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../database/sqlite.php';

const PERFIL_ADMIN = 'admin';
const PERFIL_GARCOM = 'garcom';

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

function perfil_ativo(): string
{
    return $_SESSION['perfil'] ?? '';
}

function nome_usuario_ativo(): string
{
    return $_SESSION['usuario_nome'] ?? '';
}

function usuario_autenticado(): bool
{
    return perfil_ativo() !== '';
}

function usuario_admin(): bool
{
    return perfil_ativo() === PERFIL_ADMIN;
}

function usuario_garcom(): bool
{
    return perfil_ativo() === PERFIL_GARCOM;
}

function login_admin(): void
{
    $_SESSION['perfil'] = PERFIL_ADMIN;
    $_SESSION['usuario_nome'] = 'Administrador';
}

function login_garcom(): void
{
    $_SESSION['perfil'] = PERFIL_GARCOM;
    $_SESSION['usuario_nome'] = 'Garçom';
}

function logout_usuario(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}

function exigir_autenticacao(): void
{
    if (!usuario_autenticado()) {
        header('Location: /php/Sistema_restaurante/index.php');
        exit;
    }
}

function exigir_admin(): void
{
    if (!usuario_admin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Acesso restrito</title><link rel="stylesheet" href="../css/style.css"></head><body><div class="container"><main><div class="alerta-erro"><div class="alerta-icon">⛔</div><div class="alerta-texto">Acesso restrito ao Administrador.</div><div class="alerta-subtexto">Este perfil não possui permissão para esta área.</div><p style="margin-top: 20px;"><a class="btn btn-adicionar" href="../index.php">Voltar ao início</a></p></div></main></div></body></html>';
        exit;
    }
}

function render_navegacao(string $ativo, string $base_path = '../'): void
{
    $perfil = perfil_ativo();
    $usuario = nome_usuario_ativo();

    $itens = [
        ['chave' => 'dashboard', 'titulo' => 'Dashboard', 'href' => $base_path . 'index.php', 'roles' => [PERFIL_ADMIN, PERFIL_GARCOM]],
        ['chave' => 'pedidos', 'titulo' => 'Caixa', 'href' => $base_path . 'pages/pedidos.php', 'roles' => [PERFIL_ADMIN, PERFIL_GARCOM]],
        ['chave' => 'clientes', 'titulo' => 'Clientes', 'href' => $base_path . 'pages/clientes.php', 'roles' => [PERFIL_ADMIN, PERFIL_GARCOM]],
        ['chave' => 'mesas', 'titulo' => 'Mesas', 'href' => $base_path . 'pages/mesas.php', 'roles' => [PERFIL_ADMIN, PERFIL_GARCOM]],
        ['chave' => 'produtos', 'titulo' => 'Produtos', 'href' => $base_path . 'pages/produtos.php', 'roles' => [PERFIL_ADMIN, PERFIL_GARCOM]],
        ['chave' => 'funcionarios', 'titulo' => 'Funcionários', 'href' => $base_path . 'pages/funcionarios.php', 'roles' => [PERFIL_ADMIN]],
        ['chave' => 'despesas', 'titulo' => 'Despesas', 'href' => $base_path . 'pages/despesas.php', 'roles' => [PERFIL_ADMIN]],
    ];

    echo '<nav class="navbar">';
    echo '<div class="nav-logo">🍔 RestauSys</div>';
    echo '<div class="nav-right">';
    echo '<ul class="nav-links">';

    foreach ($itens as $item) {
        if ($perfil === '' || !in_array($perfil, $item['roles'], true)) {
            continue;
        }

        $classe_ativa = $item['chave'] === $ativo ? ' class="active"' : '';
        echo '<li><a' . $classe_ativa . ' href="' . htmlspecialchars($item['href']) . '">' . htmlspecialchars($item['titulo']) . '</a></li>';
    }

    echo '</ul>';

    if ($perfil !== '') {
        $rotulo_perfil = $perfil === PERFIL_ADMIN ? 'Administrador' : 'Garçom';
        echo '<div class="nav-user">';
        echo '<span class="nav-role nav-role-' . htmlspecialchars($perfil) . '">' . htmlspecialchars($rotulo_perfil) . '</span>';
        if ($usuario !== '') {
            echo '<span class="nav-user-name">' . htmlspecialchars($usuario) . '</span>';
        }
        echo '<a class="nav-logout" href="' . htmlspecialchars($base_path . 'index.php?acao=sair') . '">Sair</a>';
        echo '</div>';
    }

    echo '</div>';
    echo '</nav>';
}
