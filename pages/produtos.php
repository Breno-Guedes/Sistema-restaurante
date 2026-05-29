<?php
require_once __DIR__ . "/../config/config.php";

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "adicionar_produto") {
        $nome = trim($_POST["nome"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");
        $preco_raw = str_replace(",", ".", trim($_POST["preco"] ?? ""));
        $preco = is_numeric($preco_raw) ? (float) $preco_raw : null;
        $estoque = (int)($_POST["estoque"] ?? 0);
        $categoria_nome = trim($_POST["categoria"] ?? "");

        if ($nome === "" || $preco === null) {
            $mensagem = "Preencha nome e preco corretamente.";
            $tipo_mensagem = "erro";
        } else {
            $id_categoria = null;
            if ($categoria_nome !== "") {
                $categoria = query_one(
                    "SELECT id_categoria FROM categorias WHERE lower(nome) = lower(?)",
                    [$categoria_nome]
                );
                if ($categoria) {
                    $id_categoria = (int) $categoria["id_categoria"];
                } else {
                    execute_query("INSERT INTO categorias (nome) VALUES (?)", [$categoria_nome]);
                    $id_categoria = get_last_insert_id();
                }
            }

            try {
                execute_query(
                    "INSERT INTO produtos (nome, descricao, preco, id_categoria, estoque) VALUES (?, ?, ?, ?, ?)",
                    [$nome, $descricao, $preco, $id_categoria, $estoque]
                );
                $mensagem = "Produto $nome adicionado com sucesso!";
                $tipo_mensagem = "sucesso";
            } catch (Exception $e) {
                $mensagem = "Erro ao adicionar produto.";
                $tipo_mensagem = "erro";
            }
        }
    }

    if ($acao === "atualizar_estoque") {
        $id_produto = (int)($_POST["id_produto"] ?? 0);
        $estoque = (int)($_POST["estoque"] ?? 0);

        if ($id_produto <= 0) {
            $mensagem = "Produto invalido.";
            $tipo_mensagem = "erro";
        } else {
            try {
                execute_query("UPDATE produtos SET estoque = ? WHERE id_produto = ?", [$estoque, $id_produto]);
                $mensagem = "Estoque atualizado com sucesso!";
                $tipo_mensagem = "sucesso";
            } catch (Exception $e) {
                $mensagem = "Erro ao atualizar estoque.";
                $tipo_mensagem = "erro";
            }
        }
    }
}

$categorias = query_all("SELECT nome FROM categorias ORDER BY nome");
$produtos = query_all(
    "SELECT p.id_produto, p.nome, p.descricao, p.preco, p.estoque, c.nome as categoria
     FROM produtos p
     LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
     ORDER BY p.nome"
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Produtos - Restaurante</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <div class="nav-logo">🍔 RestauSys</div>
            <ul class="nav-links">
                <li><a href="../index.php">Dashboard</a></li>
                <li><a href="pedidos.php">PDV (Caixa)</a></li>
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="mesas.php">Mesas</a></li>
                <li><a href="produtos.php" class="active">Produtos</a></li>
                <li><a href="funcionarios.php">Funcionários</a></li>
                <li><a href="despesas.php">Despesas</a></li>
            </ul>
        </nav>

        <header>
            <div class="header-content">
                <h1>Gestão de Produtos</h1>
                <p class="header-subtitle">Cadastre novos itens e ajuste o estoque</p>
            </div>
        </header>
        <main>
            <?php if ($mensagem): ?>
                <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="card" style="border-top-color: #22c55e;">
                    <h3>➕ Novo Produto</h3>
                    <form method="POST" class="formulario">
                        <input type="hidden" name="acao" value="adicionar_produto">
                        <div class="form-grupo">
                            <label>Nome:</label>
                            <input type="text" name="nome" placeholder="Ex: Cheeseburger" required>
                        </div>
                        <div class="form-grupo">
                            <label>Descrição:</label>
                            <input type="text" name="descricao" placeholder="Ex: Pao, carne, queijo">
                        </div>
                        <div class="form-grupo">
                            <label>Preço (R$):</label>
                            <input type="text" name="preco" placeholder="Ex: 29.90" required>
                        </div>
                        <div class="form-grupo">
                            <label>Categoria:</label>
                            <input type="text" name="categoria" list="categorias" placeholder="Ex: Lanches">
                            <datalist id="categorias">
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?=htmlspecialchars($c["nome"])?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-grupo">
                            <label>Estoque Inicial:</label>
                            <input type="number" name="estoque" min="0" value="0">
                        </div>
                        <button type="submit" class="btn btn-adicionar">Salvar Produto</button>
                    </form>
                </div>

                <div class="card" style="grid-column: span 1; border-top-color: #667eea;">
                    <h3>Produtos e Estoque</h3>
                    <div style="max-height: 420px; overflow-y: auto;">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Categoria</th>
                                    <th>Preço</th>
                                    <th>Estoque</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $p): ?>
                                    <tr>
                                        <td>
                                            <strong><?=htmlspecialchars($p["nome"])?></strong>
                                            <?php if (!empty($p["descricao"])): ?>
                                                <div style="font-size: 0.85em; color: #666;">
                                                    <?=htmlspecialchars($p["descricao"])?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?=htmlspecialchars($p["categoria"] ?? "-")?></td>
                                        <td>R$ <?=number_format((float) $p["preco"], 2, ",", ".")?></td>
                                        <td><?= (int) $p["estoque"] ?></td>
                                        <td>
                                            <form method="POST" style="display:flex; gap: 8px; align-items:center;">
                                                <input type="hidden" name="acao" value="atualizar_estoque">
                                                <input type="hidden" name="id_produto" value="<?=$p["id_produto"]?>">
                                                <input type="number" name="estoque" min="0" value="<?= (int) $p["estoque"] ?>" style="width: 80px;">
                                                <button type="submit" class="btn btn-adicionar" title="Atualizar">Atualizar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
        <footer class="app-footer">
            &copy; <?=date("Y")?> Sistema RestauSys. Todos os direitos reservados.
        </footer>
    </div>
</body>
</html>
