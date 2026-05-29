<?php
require_once __DIR__ . "/../config/config.php";

exigir_autenticacao();
exigir_admin();

$mensagem = "";
$tipo_mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    if ($acao === "adicionar_funcionario") {
        $nome = trim($_POST["nome"] ?? "");
        $cargo = trim($_POST["cargo"] ?? "");
        $salario_raw = str_replace(",", ".", trim($_POST["salario"] ?? ""));
        $salario = is_numeric($salario_raw) ? (float) $salario_raw : null;
        $data_contratacao = trim($_POST["data_contratacao"] ?? "");
        $data_contratacao = $data_contratacao !== "" ? $data_contratacao : null;

        if ($nome === "" || $cargo === "") {
            $mensagem = "Preencha nome e cargo corretamente.";
            $tipo_mensagem = "erro";
        } elseif (mb_strtolower($nome) === "admin" || mb_strtolower($cargo) === "administrador") {
            $mensagem = "O administrador fixo não pode ser cadastrado pela tela de funcionários.";
            $tipo_mensagem = "erro";
        } else {
            try {
                execute_query(
                    "INSERT INTO funcionarios (nome, cargo, salario, data_contratacao) VALUES (?, ?, ?, ?)",
                    [$nome, $cargo, $salario, $data_contratacao]
                );
                $mensagem = "Funcionario $nome adicionado com sucesso!";
                $tipo_mensagem = "sucesso";
            } catch (Exception $e) {
                $mensagem = "Erro ao adicionar funcionario.";
                $tipo_mensagem = "erro";
            }
        }
    }

    if ($acao === "remover_funcionario") {
        $id_funcionario = (int)($_POST["id_funcionario"] ?? 0);
        try {
            execute_query("DELETE FROM funcionarios WHERE id_funcionario = ?", [$id_funcionario]);
            $mensagem = "Funcionario removido com sucesso!";
            $tipo_mensagem = "sucesso";
        } catch (Exception $e) {
            $mensagem = "Erro: Funcionario possui pedidos vinculados e nao pode ser removido!";
            $tipo_mensagem = "erro";
        }
    }
}

$funcionarios = query_all("SELECT * FROM funcionarios ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Funcionários - Restaurante</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <?php render_navegacao('funcionarios', '../'); ?>

        <header>
            <div class="header-content">
                <h1>Gestão de Funcionários</h1>
                <p class="header-subtitle">Cadastre e gerencie a equipe do restaurante</p>
            </div>
        </header>
        <main>
            <?php if ($mensagem): ?>
                <div class="mensagem mensagem-<?=$tipo_mensagem?>"><?=$mensagem?></div>
            <?php endif; ?>

            <div class="card" style="border-top-color: #0ea5e9; margin-bottom: 20px;">
                <strong>Administrador fixo:</strong> login admin e senha admin.
                Este usuário não faz parte do cadastro de funcionários e não pode ser criado, editado ou removido por esta tela.
            </div>

            <div class="dashboard-grid">
                <div class="card" style="border-top-color: #0ea5e9;">
                    <h3>➕ Novo Funcionário</h3>
                    <form method="POST" class="formulario">
                        <input type="hidden" name="acao" value="adicionar_funcionario">
                        <div class="form-grupo">
                            <label>Nome:</label>
                            <input type="text" name="nome" placeholder="Ex: Ana Costa" required>
                        </div>
                        <div class="form-grupo">
                            <label>Cargo:</label>
                            <input type="text" name="cargo" placeholder="Ex: Garcom" required>
                        </div>
                        <div class="form-grupo">
                            <label>Salario (R$):</label>
                            <input type="text" name="salario" placeholder="Ex: 2500.00">
                        </div>
                        <div class="form-grupo">
                            <label>Data de Contratacao:</label>
                            <input type="date" name="data_contratacao">
                        </div>
                        <button type="submit" class="btn btn-adicionar">Salvar Funcionário</button>
                    </form>
                </div>

                <div class="card" style="border-top-color: #667eea;">
                    <h3>Equipe</h3>
                    <div style="max-height: 420px; overflow-y: auto;">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cargo</th>
                                    <th>Salário</th>
                                    <th>Contratação</th>
                                    <th>Ações</th>
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
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja realmente remover?');">
                                                <input type="hidden" name="acao" value="remover_funcionario">
                                                <input type="hidden" name="id_funcionario" value="<?=$f["id_funcionario"]?>">
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
