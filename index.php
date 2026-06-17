<?php
require_once __DIR__ . "/config/config.php";

$mensagem = "";
$tipo_mensagem = "";

if (isset($_GET["acao"]) && $_GET["acao"] === "sair") {
    logout_usuario();
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "login_admin") {
        $login = trim($_POST["login"] ?? "");
        $senha = trim($_POST["senha"] ?? "");

        if ($login === "admin" && $senha === "admin") {
            login_admin();
            header("Location: index.php");
            exit;
        }

        $mensagem = "Login ou senha inválidos.";
        $tipo_mensagem = "erro";
    }

    if ($acao === "entrar_garcom") {
        login_garcom();
        header("Location: index.php");
        exit;
    }

    if (usuario_autenticado()) {
        if ($acao === "adicionar_mesa") {
            if (!usuario_admin()) {
                $mensagem = "Acesso negado para adicionar mesas.";
                $tipo_mensagem = "erro";
            } else {
                $numero = (int)$_POST["numero"];
                $capacidade = (int)$_POST["capacidade"];
                try {
                    execute_query("INSERT INTO mesas (numero, capacidade) VALUES (?, ?)", [$numero, $capacidade]);
                    $mensagem = "Mesa $numero adicionada com sucesso!";
                    $tipo_mensagem = "sucesso";
                } catch (Exception $e) {
                    $mensagem = "Erro ao adicionar mesa. Número pode já existir.";
                    $tipo_mensagem = "erro";
                }
            }
        }

        if ($acao === "adicionar_cliente") {
            $nome = trim($_POST["nome"] ?? "");
            $telefone = trim($_POST["telefone"] ?? "");
            $email = trim($_POST["email"] ?? "");

            try {
                execute_query("INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)", [$nome, $telefone, $email]);
                $mensagem = "Cliente $nome adicionado com sucesso!";
                $tipo_mensagem = "sucesso";
            } catch (Exception $e) {
                $mensagem = "Erro ao adicionar cliente.";
                $tipo_mensagem = "erro";
            }
        }

        if ($acao === "remover_cliente") {
            if (!usuario_admin()) {
                $mensagem = "Acesso negado para remover clientes.";
                $tipo_mensagem = "erro";
            } else {
                $id_cliente = (int)$_POST["id_cliente"];
                try {
                    execute_query("DELETE FROM clientes WHERE id_cliente = ?", [$id_cliente]);
                    $mensagem = "Cliente removido com sucesso!";
                    $tipo_mensagem = "sucesso";
                } catch (Exception $e) {
                    $mensagem = "Erro: Cliente possui pedidos vinculados e não pode ser removido!";
                    $tipo_mensagem = "erro";
                }
            }
        }
    }
}

if (!usuario_autenticado()) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso ao Sistema - Restaurante</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
        <div class="auth-page">
            <div class="auth-shell">
                <section class="auth-hero">
                    <div>
                        <p style="text-transform: uppercase; letter-spacing: 0.18em; font-size: 0.8rem; opacity: 0.8;">Sistema RestauSys</p>
                        <h1>Controle de acesso por perfil</h1>
                        <p>Entre como Administrador para liberar todas as áreas do sistema ou avance como Garçom para usar apenas os recursos operacionais.</p>
                    </div>

                    <div class="auth-points">
                        <div class="auth-point">Administrador: acesso completo a funcionários, salários, despesas, produtos e demais rotinas de gestão.</div>
                        <div class="auth-point">Garçom: acesso automático às funções operacionais, sem permissão para áreas restritas.</div>
                    </div>
                </section>

                <section class="auth-card">
                    <h2>Acesso ao sistema</h2>
                    <p class="auth-subtitle">A tela de login é exclusiva para o Administrador.</p>

                    <?php if ($mensagem): ?>
                        <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
                    <?php endif; ?>

                    <form method="POST" class="formulario">
                        <input type="hidden" name="acao" value="login_admin">
                        <div class="form-grupo">
                            <label>Login:</label>
                            <input type="text" name="login" placeholder="*****" required>
                        </div>
                        <div class="form-grupo">
                            <label>Senha:</label>
                            <input type="password" name="senha" placeholder="*****" required>
                        </div>
                        <button type="submit" class="btn btn-adicionar">Entrar como Administrador</button>
                    </form>

                    <div class="auth-actions">
                        <form method="POST">
                            <input type="hidden" name="acao" value="entrar_garcom">
                            <button type="submit" class="btn btn-secundario" style="width: 100%;">Pular login e entrar como Garçom</button>
                        </form>
                    </div>

                </section>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$vendas = query_one("SELECT SUM(ip.quantidade * ip.preco_unitario) as total 
                     FROM itens_pedido ip 
                     JOIN pedidos p ON ip.id_pedido = p.id_pedido 
                     WHERE p.status = 'fechado'");
$total_vendas = $vendas["total"] ?? 0;

$pedidos_abertos = query_all("SELECT p.id_pedido, c.nome, m.numero, p.data_pedido 
                              FROM pedidos p 
                              JOIN clientes c ON p.id_cliente = c.id_cliente 
                              JOIN mesas m ON p.id_mesa = m.id_mesa 
                              WHERE p.status = 'aberto'");

$clientes = query_all("SELECT * FROM clientes ORDER BY nome ASC");
$mesas = query_all("SELECT * FROM mesas ORDER BY numero ASC");

$despesas = query_one("SELECT SUM(valor) as total FROM despesas");
$salarios = query_one("SELECT SUM(salario) as total FROM funcionarios");
$total_despesas = ($despesas["total"] ?? 0) + ($salarios["total"] ?? 0);
$total_faturamento = $total_vendas - $total_despesas;

$perfil_logado = usuario_admin() ? "Administrador" : "Garçom";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <?php render_navegacao('dashboard', ''); ?>
        <header>
            <div class="header-content">
                <h1>Painel do Restaurante</h1>
                <p class="header-subtitle">Perfil ativo: <?=$perfil_logado?></p>
            </div>
        </header>
        <main>
            <?php if ($mensagem): ?>
                <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
            <?php endif; ?>

            <?php if (usuario_admin()): ?>
                <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                    <a href="pages/pedidos.php" class="btn-link">Ir para Caixa</a>
                    <a href="pages/pedidos.php#finalizados" class="btn-link">Ver Pedidos Finalizados</a>
                    <a href="pages/produtos.php" class="btn-link">Produtos</a>
                    <a href="pages/funcionarios.php" class="btn-link">Funcionários</a>
                    <a href="pages/despesas.php" class="btn-link">Despesas</a>
                    <a href="pages/relatorios.php" class="btn-link">Relatorios</a>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <?php if (usuario_admin()): ?>
                    <div class="card" style="border-top-color: #28a745;">
                        <h3>Faturamento Total</h3>
                        <p style="font-size: 2.5em; font-weight: bold; color: #28a745;">R$ <?=number_format((float) $total_faturamento, 2, ",", ".")?></p>
                        <p style="color: #666; font-size: 0.9em; margin-top: 10px;">Vendas finalizadas - despesas e salários.</p>
                    </div>
                <?php endif; ?>

                <div class="card" style="border-top-color: #ffc107;">
                    <h3>Pedidos em Aberto</h3>
                    <?php if (empty($pedidos_abertos)): ?>
                        <p style="color: #666;">Todos os pedidos foram finalizados.</p>
                    <?php else: ?>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($pedidos_abertos as $p): ?>
                                <li style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                                    <span>
                                        <strong>#<?=$p["id_pedido"]?></strong> - <?=$p["nome"]?> (Mesa <?=$p["numero"]?>)
                                        <small style="display: block; color: #666;">Data: <?=date("d/m/Y H:i", strtotime($p["data_pedido"]))?></small>
                                    </span>
                                    <a href="pages/pedidos.php?id=<?=$p["id_pedido"]?>" class="btn btn-criar" style="padding: 5px 10px; font-size: 0.8em; width: auto;">Ver/Finalizar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <?php if (usuario_admin()): ?>
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

                    <div class="card" style="border-top-color: #6c757d;">
                        <h3>Adicionar Mesa</h3>
                        <form method="POST" style="display: flex; gap: 10px; flex-direction: column;">
                            <input type="hidden" name="acao" value="adicionar_mesa">
                            <input type="number" name="numero" placeholder="Número da Mesa" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                            <input type="number" name="capacidade" placeholder="Capacidade (Pessoas)" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                            <button type="submit" class="btn" style="width: 100%; background: #6c757d; color: white;">Salvar Mesa</button>
                        </form>
                    </div>
                <?php endif; ?>

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
                                <?php foreach ($mesas as $m): ?>
                                <tr>
                                    <td><?=$m["numero"]?></td>
                                    <td><?=$m["capacidade"]?></td>
                                    <td><span class="status-badge status-<?=$m["status"]?>"><?=$m["status"]?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (usuario_admin()): ?>
                    <div class="card" style="border-top-color: #dc3545;">
                        <h3>Gerenciar Clientes</h3>
                        <div style="margin-bottom: 20px;">
                            <p style="font-size: 0.9em; color: #666; margin-bottom: 10px;">Selecione um cliente para remover do sistema:</p>
                            <form method="POST" style="display: flex; gap: 10px; flex-direction: column;">
                                <input type="hidden" name="acao" value="remover_cliente">
                                <select name="id_cliente" required style="padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                    <option value="">Selecione um cliente</option>
                                    <?php foreach ($clientes as $c): ?>
                                        <option value="<?=$c["id_cliente"]?>"><?=$c["nome"]?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-remover" style="width: 100%;">Remover Cliente</button>
                            </form>
                        </div>

                        <h4 style="margin-bottom: 10px; border-top: 1px solid #eee; padding-top: 15px;">Últimos Clientes</h4>
                        <div style="max-height: 150px; overflow-y: auto;">
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ($clientes as $c): ?>
                                    <li style="padding: 5px 0; border-bottom: 1px solid #eee; font-size: 0.9em;">
                                        <strong><?=$c["nome"]?></strong>
                                        <?= $c["telefone"] ? " - " . $c["telefone"] : "" ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <footer class="app-footer">
            &copy; <?=date("Y")?> Sistema RestauSys. Todos os direitos reservados.
        </footer>
    </div>
</body>
</html>
