<?php
require_once __DIR__ . "/../config/config.php";

exigir_autenticacao();

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";
    
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
            } catch(Exception $e) {
                $mensagem = "Erro ao adicionar mesa. Número pode já existir.";
                $tipo_mensagem = "erro";
            }
        }
    }

    if ($acao === "remover_mesa") {
        if (!usuario_admin()) {
            $mensagem = "Acesso negado para remover mesas.";
            $tipo_mensagem = "erro";
        } else {
            $id_mesa = (int)$_POST["id_mesa"];
            try {
                execute_query("DELETE FROM mesas WHERE id_mesa = ?", [$id_mesa]);
                $mensagem = "Mesa removida com sucesso!";
                $tipo_mensagem = "sucesso";
            } catch(Exception $e) {
                $mensagem = "Erro: Mesa possui pedidos vinculados e não pode ser removida!";
                $tipo_mensagem = "erro";
            }
        }
    }

    if ($acao === "liberar_mesa") {
        $id_mesa = (int)$_POST["id_mesa"];
        try {
            execute_query("UPDATE mesas SET status = 'livre' WHERE id_mesa = ?", [$id_mesa]);
            $mensagem = "Mesa liberada com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch(Exception $e) {
            $mensagem = "Erro ao liberar mesa.";
            $tipo_mensagem = "erro";
        }
    }
}

$mesas = query_all("SELECT * FROM mesas ORDER BY numero ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Mesas - Restaurante</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <?php render_navegacao('mesas', '../'); ?>

        <header>
            <div class="header-content">
                <h1>Gestão de Mesas</h1>
                <p class="header-subtitle">Cadastre e gerencie a lotação do estabelecimento</p>
            </div>
        </header>
        <main>
            <?php if($mensagem): ?>
                <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
            <?php endif; ?>
            
            <div class="dashboard-grid">
                <?php if (usuario_admin()): ?>
                    <!-- Adicionar Mesa -->
                    <div class="card" style="border-top-color: #6c757d;">
                        <h3>➕ Nova Mesa</h3>
                        <form method="POST" class="formulario">
                            <input type="hidden" name="acao" value="adicionar_mesa">
                            <div class="form-grupo">
                                <label>Número da Mesa:</label>
                                <input type="number" name="numero" required>
                            </div>
                            <div class="form-grupo">
                                <label>Capacidade (Pessoas):</label>
                                <input type="number" name="capacidade" required>
                            </div>
                            <button type="submit" class="btn " style="background: #6c757d; color: white;">Salvar Mesa</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card" style="border-top-color: #6c757d;">

                        <p style="color: #666;">O perfil Garçom pode apenas consultar e liberar mesas ocupadas.</p>
                    </div>
                <?php endif; ?>

                <!-- Lista de Mesas -->
                <div class="card" style="border-top-color: #e83e8c;">
                    <h3>Status do Salão</h3>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Mesa</th>
                                    <th>Capacidade</th>
                                    <th>Status</th>
                                    <?php if (usuario_admin()): ?><th>Ações</th><?php else: ?><th>Operação</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($mesas as $m): ?>
                                <tr>
                                    <td><strong><?=$m["numero"]?></strong></td>
                                    <td><?=$m["capacidade"]?> pessoas</td>
                                    <td>
                                        <span class="status-badge status-<?=$m["status"]?>"><?=$m["status"]?></span>
                                    </td>
                                    <td>
                                        <?php if ($m["status"] !== "livre"): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja liberar esta mesa?');">
                                                <input type="hidden" name="acao" value="liberar_mesa">
                                                <input type="hidden" name="id_mesa" value="<?=$m["id_mesa"]?>">
                                                <button type="submit" class="btn btn-adicionar" title="Liberar">Liberar</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">Mesa livre</span>
                                        <?php endif; ?>
                                        <?php if (usuario_admin()): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja remover esta mesa?');">
                                                <input type="hidden" name="acao" value="remover_mesa">
                                                <input type="hidden" name="id_mesa" value="<?=$m["id_mesa"]?>">
                                                <button type="submit" class="btn btn-remover" title="Remover">🗑️</button>
                                            </form>
                                        <?php endif; ?>
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