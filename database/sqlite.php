<?php

declare(strict_types=1);

function get_sqlite_connection(string $db_path): PDO
{
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("PRAGMA foreign_keys = ON;");

    return $pdo;
}

function execute_statements(PDO $pdo, array $statements): void
{
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
}

function create_schema(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS clientes (\n"
            . "    id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    nome TEXT NOT NULL,\n"
            . "    telefone TEXT,\n"
            . "    email TEXT,\n"
            . "    data_cadastro TEXT DEFAULT CURRENT_TIMESTAMP\n"
            . ");",

        "CREATE TABLE IF NOT EXISTS funcionarios (\n"
            . "    id_funcionario INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    nome TEXT NOT NULL,\n"
            . "    cargo TEXT NOT NULL,\n"
            . "    salario NUMERIC,\n"
            . "    data_contratacao TEXT\n"
            . ");",

        "CREATE TABLE IF NOT EXISTS mesas (\n"
            . "    id_mesa INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    numero INTEGER NOT NULL UNIQUE,\n"
            . "    capacidade INTEGER NOT NULL,\n"
            . "    status TEXT NOT NULL DEFAULT 'livre' CHECK (status IN ('livre','ocupada','reservada'))\n"
            . ");",

        "CREATE TABLE IF NOT EXISTS categorias (\n"
            . "    id_categoria INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    nome TEXT NOT NULL\n"
            . ");",

        "CREATE TABLE IF NOT EXISTS produtos (\n"
            . "    id_produto INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    nome TEXT NOT NULL,\n"
            . "    descricao TEXT,\n"
            . "    preco NUMERIC NOT NULL,\n"
            . "    id_categoria INTEGER,\n"
            . "    estoque INTEGER DEFAULT 0,\n"
            . "    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria)\n"
            . ");",

        "CREATE TABLE IF NOT EXISTS pedidos (\n"
            . "    id_pedido INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    id_cliente INTEGER,\n"
            . "    id_mesa INTEGER,\n"
            . "    id_funcionario INTEGER,\n"
            . "    data_pedido TEXT DEFAULT CURRENT_TIMESTAMP,\n"
            . "    status TEXT NOT NULL DEFAULT 'aberto' CHECK (status IN ('aberto','fechado','cancelado')),\n"
            . "    forma_de_pagamento TEXT DEFAULT 'DINHEIRO' CHECK (forma_de_pagamento IN ('DINHEIRO','PIX','CARTAO')),\n"
            . "    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente),\n"
            . "    FOREIGN KEY (id_mesa) REFERENCES mesas(id_mesa),\n"
            . "    FOREIGN KEY (id_funcionario) REFERENCES funcionarios(id_funcionario)\n"
            . ");",

        "CREATE TABLE IF NOT EXISTS itens_pedido (\n"
            . "    id_item INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    id_pedido INTEGER NOT NULL,\n"
            . "    id_produto INTEGER NOT NULL,\n"
            . "    quantidade INTEGER NOT NULL,\n"
            . "    preco_unitario NUMERIC NOT NULL,\n"
            . "    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),\n"
            . "    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto)\n"
            . ");",

        "CREATE TABLE IF NOT EXISTS despesas (\n"
            . "    id_despesa INTEGER PRIMARY KEY AUTOINCREMENT,\n"
            . "    descricao TEXT NOT NULL,\n"
            . "    categoria TEXT NOT NULL,\n"
            . "    valor NUMERIC NOT NULL,\n"
            . "    data_despesa TEXT\n"
            . ");",
    ];

    execute_statements($pdo, $statements);
}

function create_triggers(PDO $pdo): void
{
    $statements = [
        "CREATE TRIGGER IF NOT EXISTS trg_itens_pedido_before_insert\n"
            . "BEFORE INSERT ON itens_pedido\n"
            . "FOR EACH ROW\n"
            . "BEGIN\n"
            . "    SELECT CASE\n"
            . "        WHEN (SELECT estoque FROM produtos WHERE id_produto = NEW.id_produto) < NEW.quantidade\n"
            . "        THEN RAISE(ABORT, 'Erro: Estoque insuficiente para este produto.')\n"
            . "    END;\n"
            . "    UPDATE produtos\n"
            . "    SET estoque = estoque - NEW.quantidade\n"
            . "    WHERE id_produto = NEW.id_produto;\n"
            . "END;",

        "CREATE TRIGGER IF NOT EXISTS trg_itens_pedido_before_update\n"
            . "BEFORE UPDATE ON itens_pedido\n"
            . "FOR EACH ROW\n"
            . "BEGIN\n"
            . "    SELECT CASE\n"
            . "        WHEN (NEW.quantidade - OLD.quantidade) > 0\n"
            . "             AND (SELECT estoque FROM produtos WHERE id_produto = NEW.id_produto) < (NEW.quantidade - OLD.quantidade)\n"
            . "        THEN RAISE(ABORT, 'Erro: Estoque insuficiente para adicionar mais unidades deste produto.')\n"
            . "    END;\n"
            . "    UPDATE produtos\n"
            . "    SET estoque = estoque - (NEW.quantidade - OLD.quantidade)\n"
            . "    WHERE id_produto = NEW.id_produto;\n"
            . "END;",

        "CREATE TRIGGER IF NOT EXISTS trg_itens_pedido_after_delete\n"
            . "AFTER DELETE ON itens_pedido\n"
            . "FOR EACH ROW\n"
            . "BEGIN\n"
            . "    UPDATE produtos\n"
            . "    SET estoque = estoque + OLD.quantidade\n"
            . "    WHERE id_produto = OLD.id_produto;\n"
            . "END;",
    ];

    execute_statements($pdo, $statements);
}

function seed_data(PDO $pdo): void
{
    // Verificar se já existem dados para evitar duplicação
    $result = $pdo->query("SELECT COUNT(*) as count FROM clientes");
    $row = $result->fetch();
    
    if ($row['count'] > 0) {
        return; // Dados já foram inseridos
    }

    $statements = [
        "INSERT INTO clientes (nome, telefone, email) VALUES\n"
            . "('Joao Silva', '11999990001', 'joao@email.com'),\n"
            . "('Maria Oliveira', '11999990002', 'maria@email.com'),\n"
            . "('Carlos Souza', '11999990003', 'carlos@email.com');",

        "INSERT INTO funcionarios (nome, cargo, salario, data_contratacao) VALUES\n"
            . "('Ana Costa', 'Garcom', 2500.00, '2023-01-10'),\n"
            . "('Pedro Lima', 'Garcom', 2400.00, '2023-03-15'),\n"
            . "('Fernanda Alves', 'Gerente', 5000.00, '2022-05-01');",

        "INSERT INTO mesas (numero, capacidade, status) VALUES\n"
            . "(1, 4, 'livre'),\n"
            . "(2, 2, 'ocupada'),\n"
            . "(3, 6, 'livre'),\n"
            . "(4, 8, 'reservada');",

        "INSERT INTO categorias (nome) VALUES\n"
            . "('Prato Principal'),\n"
            . "('Bebida'),\n"
            . "('Sobremesa');",

        "INSERT INTO produtos (nome, descricao, preco, id_categoria, estoque) VALUES\n"
            . "('Hamburguer Artesanal', 'Pao brioche, carne 180g, queijo e molho especial', 35.90, 1, 50),\n"
            . "('Pizza Calabresa', 'Pizza media com calabresa e cebola', 49.90, 1, 50),\n"
            . "('Refrigerante Lata', 'Lata 350ml', 6.00, 2, 50),\n"
            . "('Suco Natural', 'Suco de laranja 300ml', 8.50, 2, 50),\n"
            . "('Pudim', 'Pudim de leite condensado', 12.00, 3, 50);",

        "INSERT INTO pedidos (id_cliente, id_mesa, id_funcionario, status, forma_de_pagamento) VALUES\n"
            . "(1, 2, 1, 'aberto', 'DINHEIRO'),\n"
            . "(2, 3, 2, 'fechado', 'PIX'),\n"
            . "(3, 1, 1, 'aberto', 'CARTAO');",

        "INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario) VALUES\n"
            . "(1, 1, 2, 35.90),\n"
            . "(1, 3, 2, 6.00),\n"
            . "(2, 2, 1, 49.90),\n"
            . "(2, 4, 1, 8.50),\n"
            . "(3, 1, 1, 35.90),\n"
            . "(3, 5, 1, 12.00);",

        "INSERT INTO despesas (descricao, categoria, valor, data_despesa) VALUES\n"
            . "('Aluguel do salao', 'Aluguel', 3000.00, '2026-03-01'),\n"
            . "('Energia eletrica', 'Energia', 850.00, '2026-03-05'),\n"
            . "('Compra de carnes', 'Insumos', 1200.00, '2026-03-10'),\n"
            . "('Compra de bebidas', 'Insumos', 600.00, '2026-03-12');",
    ];

    execute_statements($pdo, $statements);
}

function drop_all_tables(PDO $pdo): void
{
    $pdo->exec("PRAGMA foreign_keys = OFF;");
    
    $tables = [
        'itens_pedido',
        'pedidos',
        'produtos',
        'categorias',
        'mesas',
        'funcionarios',
        'clientes',
        'despesas'
    ];
    
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
    }
    
    $pdo->exec("PRAGMA foreign_keys = ON;");
}

function initialize_database(string $db_path, bool $seed = true): PDO
{
    $pdo = get_sqlite_connection($db_path);

    if ($seed) {
        drop_all_tables($pdo);
    }

    create_schema($pdo);
    create_triggers($pdo);

    if ($seed) {
        seed_data($pdo);
    }

    return $pdo;
}
