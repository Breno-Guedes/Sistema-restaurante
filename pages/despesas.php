<?php
require_once __DIR__ . "/../config/config.php";

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "adicionar_despesa") {
        $descricao = trim($_POST["descricao"] ?? "");
        $categoria = trim($_POST["categoria"] ?? "");
        $valor_raw = str_replace(",", ".", trim($_POST["valor"] ?? ""));
        $valor = is_numeric($valor_raw) ? (float) $valor_raw : null;
        $data_despesa = trim($_POST["data_despesa"] ?? "");
        $data_despesa = $data_despesa !== "" ? $data_despesa : null;

        if ($descricao === "" || $categoria === "" || $valor === null) {
            $mensagem = "Preencha descricao, categoria e valor corretamente.";
            $tipo_mensagem = "erro";
        } else {
            try {
                execute_query(
                    "INSERT INTO despesas (descricao, categoria, valor, data_despesa) VALUES (?, ?, ?, ?)",
                    [$descricao, $categoria, $valor, $data_despesa]
                );
                $mensagem = "Despesa adicionada com sucesso!";
                $tipo_mensagem = "sucesso";
            } catch (Exception $e) {
                $mensagem = "Erro ao adicionar despesa.";
                $tipo_mensagem = "erro";
            }
        }
    }

    if ($acao === "remover_despesa") {
        $id_despesa = (int)($_POST["id_despesa"] ?? 0);
        try {
            execute_query("DELETE FROM despesas WHERE id_despesa = ?", [$id_despesa]);
            $mensagem = "Despesa removida com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch (Exception $e) {
            $mensagem = "Erro ao remover despesa.";
            $tipo_mensagem = "erro";
        }
    }
}

$despesas = query_all("SELECT * FROM despesas ORDER BY data_despesa DESC, id_despesa DESC");
$funcionarios = query_all("SELECT id_funcionario, nome, cargo, salario, data_contratacao FROM funcionarios ORDER BY nome");
$total_despesas = query_one("SELECT SUM(valor) as total FROM despesas");
$total_salarios = query_one("SELECT SUM(salario) as total FROM funcionarios");
$valor_total = ($total_despesas["total"] ?? 0) + ($total_salarios["total"] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Controle de Despesas - Restaurante</title>
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
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="funcionarios.php">Funcionários</a></li>
                <li><a href="despesas.php" class="active">Despesas</a></li>
            </ul>
        </nav>

        <header>
            <div class="header-content">
                <h1>Controle de Despesas</h1>
                <p class="header-subtitle">Registre os custos do restaurante</p>
            </div>
        </header>
        <main>
            <?php if ($mensagem): ?>
                <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="card" style="border-top-color: #f59e0b;">
                    <h3>➕ Nova Despesa</h3>
                    <form method="POST" class="formulario">
                        <input type="hidden" name="acao" value="adicionar_despesa">
                        <div class="form-grupo">
                            <label>Descricao:</label>
                            <input type="text" name="descricao" placeholder="Ex: Energia eletrica" required>
                        </div>
                        <div class="form-grupo">
                            <label>Categoria:</label>
                            <input type="text" name="categoria" placeholder="Ex: Energia" required>
                        </div>
                        <div class="form-grupo">
                            <label>Valor (R$):</label>
                            <input type="text" name="valor" placeholder="Ex: 850.00" required>
                        </div>
                        <div class="form-grupo">
                            <label>Data:</label>
                            <input type="date" name="data_despesa">
                        </div>
                        <button type="submit" class="btn btn-adicionar">Salvar Despesa</button>
                    </form>
                </div>

                <div class="card" style="border-top-color: #ef4444;">
                    <h3>Total de Despesas</h3>
                    <p style="font-size: 2.2em; font-weight: bold; color: #ef4444;">
                        R$ <?=number_format((float) $valor_total, 2, ",", ".")?>
                    </p>
                    <p style="color: #666; font-size: 0.9em; margin-top: 8px;">Despesas cadastradas + sálarios dos funcionarios.</p>
                </div>

                <div class="card" style="grid-column: span 2; border-top-color: #667eea;">
                    <h3>Historico de Despesas</h3>
                    <div style="max-height: 420px; overflow-y: auto;">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Descricao</th>
                                    <th>Categoria</th>
                                    <th>Valor</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($despesas as $d): ?>
                                    <tr>
                                        <td><strong><?=htmlspecialchars($d["descricao"])?></strong></td>
                                        <td><?=htmlspecialchars($d["categoria"])?></td>
                                        <td>R$ <?=number_format((float) $d["valor"], 2, ",", ".")?></td>
                                        <td>
                                            <?php if (!empty($d["data_despesa"])): ?>
                                                <?=date("d/m/Y", strtotime($d["data_despesa"]))?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja realmente remover?');">
                                                <input type="hidden" name="acao" value="remover_despesa">
                                                <input type="hidden" name="id_despesa" value="<?=$d["id_despesa"]?>">
                                                <button type="submit" class="btn btn-remover" title="Remover">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card" style="grid-column: span 2; border-top-color: #0ea5e9;">
                    <h3>Salários de Funcionários</h3>
                    <div style="max-height: 320px; overflow-y: auto;">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cargo</th>
                                    <th>Salário</th>
                                    <th>Contratação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($funcionarios as $f): ?>
                                    <tr>
                                        <td><strong><?=htmlspecialchars($f["nome"])?></strong></td>
                                        <td><?=htmlspecialchars($f["cargo"])?></td>
                                        <td>
                                            <?php if ($f["salario"] !== null && $f["salario"] !== ""): ?>
                                                R$ <?=number_format((float) $f["salario"], 2, ",", ".")?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($f["data_contratacao"])): ?>
                                                <?=date("d/m/Y", strtotime($f["data_contratacao"]))?>
                                            <?php else: ?>
                                                -
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
