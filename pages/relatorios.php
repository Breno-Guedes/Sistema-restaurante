<?php
require_once __DIR__ . "/../config/config.php";

exigir_autenticacao();
exigir_admin();

function dinheiro(float $valor): string
{
    return "R$ " . number_format($valor, 2, ",", ".");
}

function data_br(?string $data): string
{
    if (!$data) {
        return "-";
    }

    return date("d/m/Y H:i", strtotime($data));
}

$tipo_periodo = $_GET["periodo"] ?? "todos";
$data_dia = $_GET["data_dia"] ?? date("Y-m-d");
$mes = $_GET["mes"] ?? date("Y-m");
$ano = $_GET["ano"] ?? date("Y");
$data_inicio = $_GET["data_inicio"] ?? date("Y-m-01");
$data_fim = $_GET["data_fim"] ?? date("Y-m-d");

$where_periodo = "p.status = 'fechado'";
$params_periodo = [];
$rotulo_periodo = "Todo o periodo";

if ($tipo_periodo === "dia") {
    $where_periodo .= " AND date(p.data_pedido) = ?";
    $params_periodo[] = $data_dia;
    $rotulo_periodo = "Dia " . date("d/m/Y", strtotime($data_dia));
} elseif ($tipo_periodo === "mes") {
    $where_periodo .= " AND strftime('%Y-%m', p.data_pedido) = ?";
    $params_periodo[] = $mes;
    $rotulo_periodo = "Mes " . date("m/Y", strtotime($mes . "-01"));
} elseif ($tipo_periodo === "ano") {
    $where_periodo .= " AND strftime('%Y', p.data_pedido) = ?";
    $params_periodo[] = $ano;
    $rotulo_periodo = "Ano " . $ano;
} elseif ($tipo_periodo === "intervalo") {
    $where_periodo .= " AND date(p.data_pedido) BETWEEN ? AND ?";
    $params_periodo[] = $data_inicio;
    $params_periodo[] = $data_fim;
    $rotulo_periodo = date("d/m/Y", strtotime($data_inicio)) . " ate " . date("d/m/Y", strtotime($data_fim));
}

$ranking_produtos = query_all(
    "SELECT pr.id_produto, pr.nome, SUM(ip.quantidade) AS quantidade_vendida,
            SUM(ip.quantidade * ip.preco_unitario) AS faturamento
     FROM itens_pedido ip
     JOIN produtos pr ON pr.id_produto = ip.id_produto
     JOIN pedidos p ON p.id_pedido = ip.id_pedido
     WHERE p.status = 'fechado'
     GROUP BY pr.id_produto, pr.nome
     ORDER BY quantidade_vendida DESC, faturamento DESC, pr.nome ASC"
);
$produto_mais_vendido = $ranking_produtos[0] ?? null;

$ranking_clientes = query_all(
    "SELECT c.id_cliente, c.nome, COUNT(DISTINCT p.id_pedido) AS quantidade_pedidos,
            COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS total_gasto
     FROM clientes c
     JOIN pedidos p ON p.id_cliente = c.id_cliente
     LEFT JOIN itens_pedido ip ON ip.id_pedido = p.id_pedido
     WHERE p.status = 'fechado'
     GROUP BY c.id_cliente, c.nome
     ORDER BY quantidade_pedidos DESC, total_gasto DESC, c.nome ASC"
);
$cliente_mais_pedidos = $ranking_clientes[0] ?? null;

$pedido_maior_valor = query_one(
    "SELECT p.id_pedido, c.nome AS cliente, f.nome AS garcom, p.data_pedido,
            SUM(ip.quantidade * ip.preco_unitario) AS total
     FROM pedidos p
     JOIN clientes c ON c.id_cliente = p.id_cliente
     LEFT JOIN funcionarios f ON f.id_funcionario = p.id_funcionario
     JOIN itens_pedido ip ON ip.id_pedido = p.id_pedido
     WHERE p.status = 'fechado'
     GROUP BY p.id_pedido, c.nome, f.nome, p.data_pedido
     ORDER BY total DESC
     LIMIT 1"
);

$pedido_menor_valor = query_one(
    "SELECT p.id_pedido, c.nome AS cliente, f.nome AS garcom, p.data_pedido,
            SUM(ip.quantidade * ip.preco_unitario) AS total
     FROM pedidos p
     JOIN clientes c ON c.id_cliente = p.id_cliente
     LEFT JOIN funcionarios f ON f.id_funcionario = p.id_funcionario
     JOIN itens_pedido ip ON ip.id_pedido = p.id_pedido
     WHERE p.status = 'fechado'
     GROUP BY p.id_pedido, c.nome, f.nome, p.data_pedido
     ORDER BY total ASC
     LIMIT 1"
);

$ranking_garcons = query_all(
    "SELECT f.id_funcionario, f.nome, COUNT(DISTINCT p.id_pedido) AS quantidade_pedidos,
            COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS faturamento
     FROM funcionarios f
     JOIN pedidos p ON p.id_funcionario = f.id_funcionario
     LEFT JOIN itens_pedido ip ON ip.id_pedido = p.id_pedido
     WHERE p.status = 'fechado'
     GROUP BY f.id_funcionario, f.nome
     ORDER BY quantidade_pedidos DESC, faturamento DESC, f.nome ASC"
);
$garcom_mais_pedidos = $ranking_garcons[0] ?? null;

$faturamento_total = query_one(
    "SELECT COUNT(DISTINCT p.id_pedido) AS quantidade_pedidos,
            COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS faturamento
     FROM pedidos p
     LEFT JOIN itens_pedido ip ON ip.id_pedido = p.id_pedido
     WHERE p.status = 'fechado'"
);

$faturamento_periodo = query_one(
    "SELECT COUNT(DISTINCT p.id_pedido) AS quantidade_pedidos,
            COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS faturamento
     FROM pedidos p
     LEFT JOIN itens_pedido ip ON ip.id_pedido = p.id_pedido
     WHERE $where_periodo",
    $params_periodo
);

$produtos_menor_saida = query_all(
    "SELECT pr.id_produto, pr.nome,
            COALESCE(SUM(CASE WHEN p.status = 'fechado' THEN ip.quantidade ELSE 0 END), 0) AS quantidade_vendida,
            COALESCE(SUM(CASE WHEN p.status = 'fechado' THEN ip.quantidade * ip.preco_unitario ELSE 0 END), 0) AS faturamento
     FROM produtos pr
     LEFT JOIN itens_pedido ip ON ip.id_produto = pr.id_produto
     LEFT JOIN pedidos p ON p.id_pedido = ip.id_pedido
     GROUP BY pr.id_produto, pr.nome
     ORDER BY quantidade_vendida ASC, faturamento ASC, pr.nome ASC
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatorios Gerenciais - Restaurante</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <?php render_navegacao('relatorios', '../'); ?>

        <header>
            <div class="header-content">
                <h1>Relatorios Gerenciais</h1>
                <p class="header-subtitle">Indicadores de vendas, clientes, pedidos e equipe</p>
            </div>
        </header>

        <main>
            <section class="relatorio-metricas">
                <article class="metric-card">
                    <span class="metric-label">Produto mais vendido</span>
                    <strong><?=htmlspecialchars($produto_mais_vendido["nome"] ?? "Sem vendas")?></strong>
                    <small><?= (int)($produto_mais_vendido["quantidade_vendida"] ?? 0) ?> unidades | <?=dinheiro((float)($produto_mais_vendido["faturamento"] ?? 0))?></small>
                </article>

                <article class="metric-card">
                    <span class="metric-label">Cliente com mais pedidos</span>
                    <strong><?=htmlspecialchars($cliente_mais_pedidos["nome"] ?? "Sem pedidos")?></strong>
                    <small><?= (int)($cliente_mais_pedidos["quantidade_pedidos"] ?? 0) ?> pedidos | <?=dinheiro((float)($cliente_mais_pedidos["total_gasto"] ?? 0))?></small>
                </article>

                <article class="metric-card">
                    <span class="metric-label">Garçom com mais pedidos</span>
                    <strong><?=htmlspecialchars($garcom_mais_pedidos["nome"] ?? "Sem registros")?></strong>
                    <small><?= (int)($garcom_mais_pedidos["quantidade_pedidos"] ?? 0) ?> pedidos | <?=dinheiro((float)($garcom_mais_pedidos["faturamento"] ?? 0))?></small>
                </article>

                <article class="metric-card metric-card-total">
                    <span class="metric-label">Faturamento total</span>
                    <strong><?=dinheiro((float)($faturamento_total["faturamento"] ?? 0))?></strong>
                    <small><?= (int)($faturamento_total["quantidade_pedidos"] ?? 0) ?> pedidos concluidos</small>
                </article>
            </section>

            <section class="secao">
                <h2>Faturamento por Periodo</h2>
                <form method="GET" class="formulario relatorio-filtros">
                    <div class="form-row">
                        <div class="form-grupo">
                            <label>Tipo de filtro:</label>
                            <select name="periodo" id="periodo">
                                <option value="todos" <?=$tipo_periodo === "todos" ? "selected" : ""?>>Todo o periodo</option>
                                <option value="dia" <?=$tipo_periodo === "dia" ? "selected" : ""?>>Dia</option>
                                <option value="mes" <?=$tipo_periodo === "mes" ? "selected" : ""?>>Mês</option>
                                <option value="ano" <?=$tipo_periodo === "ano" ? "selected" : ""?>>Ano</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-adicionar">Filtrar</button>
                    </div>
                </form>
                <div class="periodo-resultado">
                    <span><?=htmlspecialchars($rotulo_periodo)?></span>
                    <strong><?=dinheiro((float)($faturamento_periodo["faturamento"] ?? 0))?></strong>
                    <small><?= (int)($faturamento_periodo["quantidade_pedidos"] ?? 0) ?> pedidos concluidos</small>
                </div>
            </section>

            <div class="relatorio-grid">
                <section class="secao">
                    <h2>Ranking de Produtos</h2>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead><tr><th>#</th><th>Produto</th><th>Qtd</th><th>Faturamento</th></tr></thead>
                            <tbody>
                                <?php foreach ($ranking_produtos as $i => $produto): ?>
                                    <tr>
                                        <td><?=($i + 1)?></td>
                                        <td><?=htmlspecialchars($produto["nome"])?></td>
                                        <td><?= (int)$produto["quantidade_vendida"] ?></td>
                                        <td><?=dinheiro((float)$produto["faturamento"])?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ranking_produtos)): ?><tr><td colspan="4">Nenhuma venda concluida.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="secao">
                    <h2>Ranking de Clientes</h2>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead><tr><th>#</th><th>Cliente</th><th>Pedidos</th><th>Total gasto</th></tr></thead>
                            <tbody>
                                <?php foreach ($ranking_clientes as $i => $cliente): ?>
                                    <tr>
                                        <td><?=($i + 1)?></td>
                                        <td><?=htmlspecialchars($cliente["nome"])?></td>
                                        <td><?= (int)$cliente["quantidade_pedidos"] ?></td>
                                        <td><?=dinheiro((float)$cliente["total_gasto"])?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ranking_clientes)): ?><tr><td colspan="4">Nenhum pedido concluido.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="secao">
                    <h2>Ranking de Garçons</h2>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead><tr><th>#</th><th>Garçom</th><th>Pedidos</th><th>Faturamento</th></tr></thead>
                            <tbody>
                                <?php foreach ($ranking_garcons as $i => $garcom): ?>
                                    <tr>
                                        <td><?=($i + 1)?></td>
                                        <td><?=htmlspecialchars($garcom["nome"])?></td>
                                        <td><?= (int)$garcom["quantidade_pedidos"] ?></td>
                                        <td><?=dinheiro((float)$garcom["faturamento"])?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ranking_garcons)): ?><tr><td colspan="4">Nenhum pedido com garcom registrado.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="secao">
                    <h2>Produtos com Menor Saida</h2>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead><tr><th>#</th><th>Produto</th><th>Qtd</th><th>Faturamento</th></tr></thead>
                            <tbody>
                                <?php foreach ($produtos_menor_saida as $i => $produto): ?>
                                    <tr>
                                        <td><?=($i + 1)?></td>
                                        <td><?=htmlspecialchars($produto["nome"])?></td>
                                        <td><?= (int)$produto["quantidade_vendida"] ?></td>
                                        <td><?=dinheiro((float)$produto["faturamento"])?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($produtos_menor_saida)): ?><tr><td colspan="4">Nenhum produto cadastrado.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section class="secao">
                <h2>Pedidos de Maior e Menor Valor</h2>
                <div class="pedido-extremos">
                    <?php foreach (["Maior valor" => $pedido_maior_valor, "Menor valor" => $pedido_menor_valor] as $titulo => $pedido): ?>
                        <article class="pedido-card">
                            <span class="metric-label"><?=$titulo?></span>
                            <?php if ($pedido): ?>
                                <strong>#<?= (int)$pedido["id_pedido"] ?> - <?=dinheiro((float)$pedido["total"])?></strong>
                                <small>Cliente: <?=htmlspecialchars($pedido["cliente"])?></small>
                                <small>Garcom: <?=htmlspecialchars($pedido["garcom"] ?? "Nao informado")?></small>
                                <small>Data: <?=data_br($pedido["data_pedido"])?></small>
                            <?php else: ?>
                                <strong>Sem pedidos concluidos</strong>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>

        <footer class="app-footer">
            &copy; <?=date("Y")?> Sistema RestauSys. Todos os direitos reservados.
        </footer>
    </div>
</body>
</html>
