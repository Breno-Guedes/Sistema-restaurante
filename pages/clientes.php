<?php
require_once __DIR__ . "/../config/config.php";

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";
    
    if ($acao === "adicionar_cliente") {
        $nome = $_POST["nome"];
        $telefone = $_POST["telefone"];
        $email = $_POST["email"];
        try {
            execute_query("INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)", [$nome, $telefone, $email]);
            $mensagem = "Cliente $nome adicionado com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch(Exception $e) {
            $mensagem = "Erro ao adicionar cliente.";
            $tipo_mensagem = "erro";
        }
    }
    
    if ($acao === "remover_cliente") {
        $id_cliente = (int)$_POST["id_cliente"];
        try {
            execute_query("DELETE FROM clientes WHERE id_cliente = ?", [$id_cliente]);
            $mensagem = "Cliente removido com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch(Exception $e) {
            $mensagem = "Erro: Cliente possui pedidos vinculados e não pode ser removido!";
            $tipo_mensagem = "erro";
        }
    }
}

$clientes = query_all("SELECT * FROM clientes ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Clientes - Restaurante</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <div class="nav-logo">🍔 RestauSys</div>
            <ul class="nav-links">
                <li><a href="../index.php">Dashboard</a></li>
                <li><a href="pedidos.php">PDV (Caixa)</a></li>
                <li><a href="clientes.php" class="active">Clientes</a></li>
                <li><a href="mesas.php">Mesas</a></li>
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="funcionarios.php">Funcionários</a></li>
                <li><a href="despesas.php">Despesas</a></li>
            </ul>
        </nav>

        <header>
            <div class="header-content">
                <h1>Gestão de Clientes</h1>
                <p class="header-subtitle">Cadastre e gerencie os clientes do estabelecimento</p>
            </div>
        </header>
        <main>
            <?php if($mensagem): ?>
                <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
            <?php endif; ?>
            
            <div class="dashboard-grid">
                <!-- Adicionar Cliente -->
                <div class="card" style="border-top-color: #17a2b8;">
                    <h3>➕ Novo Cliente</h3>
                    <form method="POST" class="formulario">
                        <input type="hidden" name="acao" value="adicionar_cliente">
                        <div class="form-grupo">
                            <label>Nome:</label>
                            <input type="text" name="nome" placeholder="Ex: João da Silva" required>
                        </div>
                        <div class="form-grupo">
                            <label>Telefone:</label>
                            <input type="text" name="telefone" placeholder="(11) 99999-9999">
                        </div>
                        <div class="form-grupo">
                            <label>E-mail:</label>
                            <input type="email" name="email" placeholder="joao@email.com">
                        </div>
                        <button type="submit" class="btn btn-adicionar">Salvar Cliente</button>
                    </form>
                </div>

                <!-- Lista de Clientes -->
                <div class="card" style="border-top-color: #667eea; grid-column: span 1;">
                    <h3>Lista de Clientes</h3>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Contato</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($clientes as $c): ?>
                                <tr>
                                    <td><strong><?=$c["nome"]?></strong></td>
                                    <td>
                                        <small><?= $c["telefone"] ?: "S/ Tel" ?></small><br>
                                        <small><?= $c["email"] ?: "S/ Email" ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja realmente remover?');">
                                            <input type="hidden" name="acao" value="remover_cliente">
                                            <input type="hidden" name="id_cliente" value="<?=$c["id_cliente"]?>">
                                            <button type="submit" class="btn btn-remover" title="Remover">🗑️</button>
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