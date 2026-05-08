<?php
require_once __DIR__ . "/config/config.php";

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";
    
    if ($acao === "adicionar_mesa") {
        $numero = (int)$_POST["numero"];
        $capacidade = (int)$_POST["capacidade"];
        try {
            execute_query("INSERT INTO mesas (numero, capacidade) VALUES (?, ?)", [$numero, $capacidade]);
            $mensagem = "? Mesa $numero adicionada com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch(Exception $e) {
            $mensagem = "? Erro ao adicionar mesa. Número pode já existir.";
            $tipo_mensagem = "erro";
        }
    }
    
    if ($acao === "adicionar_cliente") {
        $nome = $_POST["nome"];
        $telefone = $_POST["telefone"];
        $email = $_POST["email"];
        try {
            execute_query("INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)", [$nome, $telefone, $email]);
            $mensagem = "? Cliente $nome adicionado com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch(Exception $e) {
            $mensagem = "? Erro ao adicionar cliente.";
            $tipo_mensagem = "erro";
        }
    }
    
    if ($acao === "remover_cliente") {
        $id_cliente = (int)$_POST["id_cliente"];
        try {
            execute_query("DELETE FROM clientes WHERE id_cliente = ?", [$id_cliente]);
            $mensagem = "? Cliente removido com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch(Exception $e) {
            $mensagem = "? Erro: Cliente possui pedidos vinculados e não pode ser removido!";
            $tipo_mensagem = "erro";
        }
    }
}

// Buscar Faturamento
$faturamento = query_one("SELECT SUM(ip.quantidade * ip.preco_unitario) as total 
                          FROM itens_pedido ip 
                          JOIN pedidos p ON ip.id_pedido = p.id_pedido 
                          WHERE p.status = 'fechado'");
$total_faturamento = $faturamento["total"] ?? 0;

// Pedidos Abertos
$pedidos_abertos = query_all("SELECT p.id_pedido, c.nome, m.numero, p.data_pedido 
                              FROM pedidos p 
                              JOIN clientes c ON p.id_cliente = c.id_cliente 
                              JOIN mesas m ON p.id_mesa = m.id_mesa 
                              WHERE p.status = 'aberto'");

// Clientes para listagem
$clientes = query_all("SELECT * FROM clientes ORDER BY nome ASC");
$mesas = query_all("SELECT * FROM mesas ORDER BY numero ASC");

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel do Restaurante</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 4px solid #667eea;}
        .card h3 { margin-bottom: 15px; color: #333; }
        .btn-link { background: #667eea; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; display: inline-block; margin-bottom: 20px; font-weight: bold;}
        .btn-link:hover { opacity: 0.9; }
        table.pequena td, table.pequena th { padding: 8px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1>Painel do Restaurante</h1>
                <p class="header-subtitle">Visão Geral do Sistema</p>
            </div>
        </header>
        <main>
            <?php if($mensagem): ?>
                <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <a href="pages/pedidos.php" class="btn-link">Ir para PDV (Novo Pedido/Ponto de Venda)</a>
            </div>
            
            <div class="dashboard-grid">
                <!-- Faturamento -->
                <div class="card" style="border-top-color: #28a745;">
                    <h3>Faturamento Total</h3>
                    <p style="font-size: 2.5em; font-weight: bold; color: #28a745;">R$ <?=number_format($total_faturamento, 2, ",", ".")?></p>
                    <p style="color: #666; font-size: 0.9em; margin-top: 10px;">Com base em todos os pedidos finalizados.</p>
                </div>
                
                <!-- Pedidos Ativos -->
                <div class="card" style="border-top-color: #ffc107;">
                    <h3>Pedidos em Aberto</h3>
                    <?php if(empty($pedidos_abertos)): ?>
                        <p style="color: #666;">Todos os pedidos foram finalizados.</p>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach($pedidos_abertos as $p): ?>
                                <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
                                    <span><strong>#<?=$p["id_pedido"]?></strong> - <?=$p["nome"]?> (Mesa <?=$p["numero"]?>)</span>
                                    <a href="pages/pedidos.php?id=<?=$p["id_pedido"]?>" class="btn btn-criar" style="padding: 5px 10px; font-size: 0.8em; width: auto;">Ver/Finalizar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Adicionar Cliente -->
                <div class="card" style="border-top-color: #17a2b8;">
                    <h3>Adicionar Cliente</h3>
                    <form method="POST" style="display: flex; gap: 10px; flex-direction: column;">
                        <input type="hidden" name="acao" value="adicionar_cliente">
                        <input type="text" name="nome" placeholder="Nome do Cliente" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                        <input type="text" name="telefone" placeholder="Telefone (opcional)" style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                        <input type="email" name="email" placeholder="E-mail (opcional)" style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                        <button type="submit" class="btn btn-adicionar" style="width: 100%;">Salvar Cliente</button>
                    </form>
                </div>

                <!-- Adicionar Mesa -->
                <div class="card" style="border-top-color: #6c757d;">
                    <h3>Adicionar Mesa</h3>
                    <form method="POST" style="display: flex; gap: 10px; flex-direction: column;">
                        <input type="hidden" name="acao" value="adicionar_mesa">
                        <input type="number" name="numero" placeholder="Número da Mesa" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                        <input type="number" name="capacidade" placeholder="Capacidade (Pessoas)" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                        <button type="submit" class="btn " style="width: 100%; background: #6c757d; color: white;">Salvar Mesa</button>
                    </form>
                </div>

                <!-- Lista de Mesas -->
                <div class="card" style="grid-column: span 1; border-top-color: #e83e8c;">
                    <h3>Status das Mesas</h3>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <table class="tabela pequena">
                            <thead>
                                <tr>
                                    <th>Mesa</th>
                                    <th>Capac.</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($mesas as $m): ?>
                                <tr>
                                    <td><?=$m["numero"]?></td>
                                    <td><?=$m["capacidade"]?></td>
                                    <td>
                                        <span class="status-badge status-<?=$m["status"]?>"><?=$m["status"]?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Remover Cliente e Visualizar Clientes -->
                <div class="card" style="border-top-color: #dc3545;">
                    <h3>Gerenciar Clientes</h3>
                    <div style="margin-bottom: 20px;">
                        <p style="font-size: 0.9em; color: #666; margin-bottom: 10px;">Selecione um cliente para remover do sistema:</p>
                        <form method="POST" style="display: flex; gap: 10px; flex-direction: column;">
                            <input type="hidden" name="acao" value="remover_cliente">
                            <select name="id_cliente" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                <option value="">Selecione um cliente</option>
                                <?php foreach($clientes as $c): ?>
                                    <option value="<?=$c["id_cliente"]?>"><?=$c["nome"]?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-remover" style="width: 100%;">Remover Cliente</button>
                        </form>
                    </div>
                    
                    <h4 style="margin-bottom: 10px; border-top: 1px solid #eee; padding-top: 15px;">Últimos Clientes</h4>
                    <div style="max-height: 150px; overflow-y: auto;">
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach($clientes as $c): ?>
                                <li style="padding: 5px 0; border-bottom: 1px solid #eee; font-size: 0.9em;">
                                        <strong><?=$c["nome"]?></strong> 
                                    <?= $c["telefone"] ? " - " . $c["telefone"] : "" ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
</body>
</html>

