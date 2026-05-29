<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

// Variáveis de controle
$mensagem = '';
$tipo_mensagem = '';
$id_pedido_atual = null;
$cliente_selecionado = null;
$mesa_selecionada = null;
$info_cliente = null;
$info_mesa = null;

// Pegar ID do pedido da URL se existir (para abrir um pedido existente)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $id_pedido_atual = (int)$_GET['id'];
}

// ============ PROCESSAMENTO DE FORMULÁRIOS ============

// Ação: Criar novo pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_pedido') {
    try {
        $id_cliente = (int)$_POST['id_cliente'] ?? 0;
        $id_mesa = (int)$_POST['id_mesa'] ?? 0;
        $id_funcionario = (int)($_POST['id_funcionario'] ?? 0);
        $id_funcionario = $id_funcionario > 0 ? $id_funcionario : null;

        if ($id_cliente <= 0 || $id_mesa <= 0) {
            throw new Exception('Selecione cliente e mesa válidos!');
        }

        // Verificar se mesa está disponível
        $mesa_check = query_one("SELECT status FROM mesas WHERE id_mesa = ?", [$id_mesa]);
        if ($mesa_check['status'] !== 'livre') {
            throw new Exception('Mesa não está disponível!');
        }

        $sql = "INSERT INTO pedidos (id_cliente, id_mesa, id_funcionario, status) 
            VALUES (?, ?, ?, 'aberto')";
        execute_query($sql, [$id_cliente, $id_mesa, $id_funcionario]);
        
        $id_pedido_atual = get_last_insert_id();
        $cliente_selecionado = $id_cliente;
        $mesa_selecionada = $id_mesa;
        
        // Atualizar status da mesa
        execute_query("UPDATE mesas SET status = 'ocupada' WHERE id_mesa = ?", [$id_mesa]);
        
        $mensagem = 'Pedido criado com sucesso! ID: #' . $id_pedido_atual;
        $tipo_mensagem = 'sucesso';
    } catch (Exception $e) {
        $mensagem = 'Erro ao criar pedido: ' . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// Ação: Adicionar item ao pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'adicionar_item') {
    try {
        $id_pedido = (int)$_POST['id_pedido'] ?? 0;
        $id_produto = (int)$_POST['id_produto'] ?? 0;
        $quantidade = (int)$_POST['quantidade'] ?? 0;

        if ($id_pedido <= 0 || $id_produto <= 0 || $quantidade <= 0) {
            throw new Exception('Dados inválidos!');
        }

        // Buscar preço e estoque do produto
        $produto = query_one("SELECT nome, preco, estoque FROM produtos WHERE id_produto = ?", [$id_produto]);
        if (!$produto) {
            throw new Exception('Produto não encontrado!');
        }

        if ($quantidade > $produto['estoque']) {
            throw new Exception('Quantidade insuficiente em estoque! Disponível: ' . $produto['estoque'] . ' unidades');
        }

        $preco_unitario = $produto['preco'];
        $subtotal = $quantidade * $preco_unitario;

        $sql = "INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario) 
                VALUES (?, ?, ?, ?)";
        execute_query($sql, [$id_pedido, $id_produto, $quantidade, $preco_unitario]);

        $mensagem = htmlspecialchars($produto['nome']) . ' adicionado ao pedido! (x' . $quantidade . ')';
        $tipo_mensagem = 'sucesso';
        $id_pedido_atual = $id_pedido;
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// Ação: Remover item do pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'remover_item') {
    try {
        $id_item = (int)$_POST['id_item'] ?? 0;
        $id_pedido = (int)$_POST['id_pedido'] ?? 0;

        if ($id_item <= 0) {
            throw new Exception('Item inválido!');
        }

        // Buscar nome do produto para feedback
        $item_info = query_one(
            "SELECT p.nome FROM itens_pedido ip 
             JOIN produtos p ON ip.id_produto = p.id_produto 
             WHERE ip.id_item = ?", 
            [$id_item]
        );

        $sql = "DELETE FROM itens_pedido WHERE id_item = ?";
        execute_query($sql, [$id_item]);

        $mensagem = htmlspecialchars($item_info['nome']) . ' removido do pedido!';
        $tipo_mensagem = 'sucesso';
        $id_pedido_atual = $id_pedido;
    } catch (Exception $e) {
        $mensagem = 'Erro ao remover item: ' . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// Ação: Finalizar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'finalizar_pedido') {
    try {
        $id_pedido = (int)$_POST['id_pedido'] ?? 0;
        $id_mesa = (int)$_POST['id_mesa'] ?? 0;
        $forma_pagamento = $_POST['forma_pagamento'] ?? 'DINHEIRO';

        // Verificar se pedido tem itens
        $itens_count = query_one("SELECT COUNT(*) as total FROM itens_pedido WHERE id_pedido = ?", [$id_pedido]);
        if ($itens_count['total'] == 0) {
            throw new Exception('Não é permitido finalizar pedidos vazios!');
        }

        // Calcular total do pedido
        $total_result = query_one(
            "SELECT SUM(quantidade * preco_unitario) as total FROM itens_pedido WHERE id_pedido = ?", 
            [$id_pedido]
        );
        $total_pedido = $total_result['total'] ?? 0;

        $sql = "UPDATE pedidos SET status = 'fechado', forma_de_pagamento = ? WHERE id_pedido = ?";
        execute_query($sql, [$forma_pagamento, $id_pedido]);

        // Liberar a mesa
        if ($id_mesa > 0) {
            execute_query("UPDATE mesas SET status = 'livre' WHERE id_mesa = ?", [$id_mesa]);
        }

        $mensagem = 'Pedido #' . $id_pedido . ' finalizado com sucesso! Total: R$ ' . 
                    number_format($total_pedido, 2, ',', '.') . ' | Pagamento: ' . $forma_pagamento;
        $tipo_mensagem = 'sucesso';
        $id_pedido_atual = null;
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// ============ BUSCAR DADOS DO BANCO ============

$clientes = query_all("SELECT id_cliente, nome FROM clientes ORDER BY nome");
$mesas = query_all("SELECT id_mesa, numero, capacidade, status FROM mesas ORDER BY numero");
$produtos = query_all("SELECT id_produto, nome, descricao, preco, estoque FROM produtos ORDER BY nome");
$funcionarios = query_all("SELECT id_funcionario, nome, cargo FROM funcionarios ORDER BY nome");

$pedidos_abertos = query_all(
    "SELECT p.id_pedido, c.nome, m.numero, p.data_pedido
     FROM pedidos p
     JOIN clientes c ON p.id_cliente = c.id_cliente
     JOIN mesas m ON p.id_mesa = m.id_mesa
     WHERE p.status = 'aberto'
     ORDER BY p.data_pedido DESC"
);

$pedidos_finalizados = query_all(
    "SELECT p.id_pedido, c.nome, m.numero, p.data_pedido, p.forma_de_pagamento,
            SUM(ip.quantidade * ip.preco_unitario) as total
     FROM pedidos p
     JOIN clientes c ON p.id_cliente = c.id_cliente
     JOIN mesas m ON p.id_mesa = m.id_mesa
     LEFT JOIN itens_pedido ip ON p.id_pedido = ip.id_pedido
     WHERE p.status = 'fechado'
     GROUP BY p.id_pedido
     ORDER BY p.data_pedido DESC"
);

// Se houver um pedido em andamento, buscar seus itens e informações
$itens_pedido = [];
$total_pedido = 0;
if ($id_pedido_atual) {
    // Buscar informações do cliente e mesa
    $info_cliente = query_one(
        "SELECT c.nome, c.telefone FROM clientes c 
         JOIN pedidos p ON c.id_cliente = p.id_cliente 
         WHERE p.id_pedido = ?", 
        [$id_pedido_atual]
    );

    $info_mesa = query_one(
        "SELECT m.id_mesa, m.numero, m.capacidade FROM mesas m 
         JOIN pedidos p ON m.id_mesa = p.id_mesa 
         WHERE p.id_pedido = ?", 
        [$id_pedido_atual]
    );

    $itens_pedido = query_all(
        "SELECT ip.id_item, ip.id_pedido, ip.id_produto, p.nome, p.descricao, 
                ip.quantidade, ip.preco_unitario, 
                (ip.quantidade * ip.preco_unitario) as subtotal
         FROM itens_pedido ip
         JOIN produtos p ON ip.id_produto = p.id_produto
         WHERE ip.id_pedido = ?
         ORDER BY ip.id_item",
        [$id_pedido_atual]
    );

    foreach ($itens_pedido as $item) {
        $total_pedido += $item['subtotal'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Pedidos - Restaurante</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <div class="nav-logo">🍔 RestauSys</div>
            <ul class="nav-links">
                <li><a href="../index.php">Dashboard</a></li>
                <li><a href="pedidos.php" class="active">PDV (Caixa)</a></li>
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="mesas.php">Mesas</a></li>
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="funcionarios.php">Funcionários</a></li>
                <li><a href="despesas.php">Despesas</a></li>
            </ul>
        </nav>
        <header>
            <div class="header-content">
                <h1>Sistema de Pedidos</h1>
                <p class="header-subtitle">Gerenciador de Pedidos e Mesas</p>
            </div>
        </header>

        <!-- Exibir Mensagem com Auto-Dismiss -->
        <?php if ($mensagem): ?>
            <div class="mensagem mensagem-<?php echo $tipo_mensagem; ?>" id="mensagem">
                <span class="mensagem-texto"><?php echo htmlspecialchars($mensagem); ?></span>
                <button class="mensagem-fechar" onclick="this.parentElement.style.display='none';">&times;</button>
            </div>
            <script>
                setTimeout(() => {
                    const msg = document.getElementById('mensagem');
                    if (msg) msg.style.display = 'none';
                }, 5000);
            </script>
        <?php endif; ?>

        <main>
            <!-- SEÇÃO 1: CRIAR NOVO PEDIDO -->
            <section class="secao secao-criar">
                <h2>Novo Pedido</h2>
                <form method="POST" class="formulario">
                    <input type="hidden" name="acao" value="criar_pedido">

                    <div class="form-row">
                        <div class="form-grupo">
                            <label for="id_cliente">
                                <span class="label-icon"></span> Cliente:
                            </label>
                            <select name="id_cliente" id="id_cliente" required>
                                <option value="">-- Selecione um cliente --</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id_cliente']; ?>">
                                        <?php echo htmlspecialchars($cliente['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-grupo">
                            <label for="id_mesa">
                                <span class="label-icon"></span> Mesa:
                            </label>
                            <select name="id_mesa" id="id_mesa" required>
                                <option value="">-- Selecione uma mesa --</option>
                                <?php foreach ($mesas as $mesa): ?>
                                    <option value="<?php echo $mesa['id_mesa']; ?>"
                                        <?php echo ($mesa['status'] !== 'livre') ? 'disabled' : ''; ?>
                                        data-status="<?php echo $mesa['status']; ?>">
                                        Mesa <?php echo $mesa['numero']; ?> 
                                        (Cap: <?php echo $mesa['capacidade']; ?>) 
                                        <span class="status-badge status-<?php echo $mesa['status']; ?>">
                                            - <?php echo ucfirst($mesa['status']); ?>
                                        </span>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-grupo">
                            <label for="id_funcionario">
                                <span class="label-icon"></span> Funcionario:
                            </label>
                            <?php if (!empty($funcionarios)): ?>
                                <select name="id_funcionario" id="id_funcionario">
                                    <option value="">-- Selecionar funcionario --</option>
                                    <?php foreach ($funcionarios as $funcionario): ?>
                                        <option value="<?php echo $funcionario['id_funcionario']; ?>">
                                            <?php echo htmlspecialchars($funcionario['nome']); ?>
                                            (<?php echo htmlspecialchars($funcionario['cargo']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div style="padding: 10px; background: #f8f9fa; border-radius: 6px; color: #666;">
                                    Nenhum funcionario cadastrado.
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-criar">
                            <span class="btn-icon">➕</span> Criar Pedido
                        </button>
                    </div>
                </form>
            </section>

            <section class="secao secao-lista">
                <h2>Pedidos em Aberto</h2>
                <?php if (empty($pedidos_abertos)): ?>
                    <p style="color: #666;">Nenhum pedido em aberto no momento.</p>
                <?php else: ?>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Mesa</th>
                                    <th>Data</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_abertos as $p): ?>
                                    <tr>
                                        <td><strong>#<?php echo $p['id_pedido']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['nome']); ?></td>
                                        <td>Mesa <?php echo $p['numero']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($p['data_pedido'])); ?></td>
                                        <td>
                                            <a class="btn btn-criar" style="width: auto;" href="pedidos.php?id=<?php echo $p['id_pedido']; ?>">
                                                Ver/Finalizar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="secao secao-lista" id="finalizados">
                <h2>Pedidos Finalizados</h2>
                <?php if (empty($pedidos_finalizados)): ?>
                    <p style="color: #666;">Nenhum pedido finalizado ainda.</p>
                <?php else: ?>
                    <div class="tabela-container">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Mesa</th>
                                    <th>Data</th>
                                    <th>Pagamento</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_finalizados as $p): ?>
                                    <tr>
                                        <td><strong>#<?php echo $p['id_pedido']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['nome']); ?></td>
                                        <td>Mesa <?php echo $p['numero']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($p['data_pedido'])); ?></td>
                                        <td><?php echo htmlspecialchars($p['forma_de_pagamento']); ?></td>
                                        <td>R$ <?php echo number_format((float)($p['total'] ?? 0), 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- SEÇÃO 2: RESUMO DO PEDIDO ATUAL -->
            <?php if ($id_pedido_atual && $info_cliente && $info_mesa): ?>
                <section class="secao secao-resumo">
                    <h2>Resumo do Pedido #<?php echo $id_pedido_atual; ?></h2>
                    <div class="resumo-grid">
                        <div class="resumo-card">
                            <div class="resumo-label">👤 Cliente</div>
                            <div class="resumo-valor"><?php echo htmlspecialchars($info_cliente['nome']); ?></div>
                            <div class="resumo-details">☎️ <?php echo htmlspecialchars($info_cliente['telefone'] ?? 'N/A'); ?></div>
                        </div>

                        <div class="resumo-card">
                            <div class="resumo-label">🪑 Mesa</div>
                            <div class="resumo-valor">Mesa <?php echo $info_mesa['numero']; ?></div>
                            <div class="resumo-details">👥 Capacidade: <?php echo $info_mesa['capacidade']; ?> pessoas</div>
                        </div>

                        <div class="resumo-card">
                            <div class="resumo-label">Itens</div>
                            <div class="resumo-valor"><?php echo count($itens_pedido); ?></div>
                            <div class="resumo-details">total de produtos</div>
                        </div>

                        <div class="resumo-card resumo-total">
                            <div class="resumo-label">Total do Pedido</div>
                            <div class="resumo-valor">R$ <?php echo number_format($total_pedido, 2, ',', '.'); ?></div>
                            <div class="resumo-details">Atualizado em tempo real</div>
                        </div>
                    </div>
                </section>

                <!-- SEÇÃO 3: ADICIONAR ITENS AO PEDIDO -->
                <section class="secao secao-adicionar">
                    <h2>➕ Adicionar Itens</h2>
                    <form method="POST" class="formulario">
                        <input type="hidden" name="acao" value="adicionar_item">
                        <input type="hidden" name="id_pedido" value="<?php echo $id_pedido_atual; ?>">

                        <div class="form-row">
                            <div class="form-grupo form-produto">
                                <label for="id_produto">
                                    <span class="label-icon"></span> Produto:
                                </label>
                                <select name="id_produto" id="id_produto" required>
                                    <option value="">-- Selecione um produto --</option>
                                    <?php foreach ($produtos as $produto): ?>
                                        <option value="<?php echo $produto['id_produto']; ?>"
                                            <?php echo ($produto['estoque'] <= 0) ? 'disabled' : ''; ?>
                                            data-estoque="<?php echo $produto['estoque']; ?>"
                                            data-preco="<?php echo $produto['preco']; ?>">
                                            <?php echo htmlspecialchars($produto['nome']); ?> 
                                            - R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                                            <span class="estoque-info">(Est: <?php echo $produto['estoque']; ?>)</span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint" id="produto-hint">Selecione um produto acima</small>
                            </div>

                            <div class="form-grupo">
                                <label for="quantidade">
                                    <span class="label-icon"></span> Quantidade:
                                </label>
                                <input type="number" name="quantidade" id="quantidade" min="1" value="1" required>
                                <small class="form-hint" id="quantidade-hint">Disponível: -</small>
                            </div>

                            <button type="submit" class="btn btn-adicionar">
                                <span class="btn-icon"></span> Adicionar
                            </button>
                        </div>
                    </form>
                </section>

                <!-- SEÇÃO 4: ITENS DO PEDIDO ATUAL -->
                <section class="secao secao-itens">
                    <h2>Itens do Pedido (<?php echo count($itens_pedido); ?>)</h2>
                    
                    <?php if (!empty($itens_pedido)): ?>
                        <div class="tabela-container">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Produto</th>
                                        <th>Qtd</th>
                                        <th>Preço Unit.</th>
                                        <th>Subtotal</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens_pedido as $index => $item): ?>
                                        <tr class="item-row">
                                            <td class="item-numero"><?php echo ($index + 1); ?></td>
                                            <td class="item-nome">
                                                <strong><?php echo htmlspecialchars($item['nome']); ?></strong>
                                                <?php if ($item['descricao']): ?>
                                                    <div class="item-descricao"><?php echo htmlspecialchars($item['descricao']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="item-quantidade"><?php echo $item['quantidade']; ?></td>
                                            <td class="item-preco">R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                            <td class="item-subtotal">R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></td>
                                            <td class="item-acao">
                                                <form method="POST" class="form-remover" onsubmit="return confirm('Tem certeza que deseja remover este item?');">
                                                    <input type="hidden" name="acao" value="remover_item">
                                                    <input type="hidden" name="id_item" value="<?php echo $item['id_item']; ?>">
                                                    <input type="hidden" name="id_pedido" value="<?php echo $id_pedido_atual; ?>">
                                                    <button type="submit" class="btn btn-remover" title="Remover item">🗑️</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- TOTAL E FINALIZAR -->
                        <div class="secao-finalizacao">
                            <div class="total-pedido">
                                <div class="total-label">Total do Pedido</div>
                                <div class="total-valor">R$ <?php echo number_format($total_pedido, 2, ',', '.'); ?></div>
                            </div>

                            <form method="POST" class="formulario-pagamento">
                                <input type="hidden" name="acao" value="finalizar_pedido">
                                <input type="hidden" name="id_pedido" value="<?php echo $id_pedido_atual; ?>">
                                <input type="hidden" name="id_mesa" value="<?php echo $info_mesa ? $info_mesa['id_mesa'] : 0; ?>">

                                <div class="form-grupo">
                                    <label for="forma_pagamento">
                                        <span class="label-icon"></span> Forma de Pagamento:
                                    </label>
                                    <div class="opcoes-pagamento">
                                        <label class="opcao-pagamento">
                                            <input type="radio" name="forma_pagamento" value="DINHEIRO" checked>
                                            <span>Dinheiro</span>
                                        </label>
                                        <label class="opcao-pagamento">
                                            <input type="radio" name="forma_pagamento" value="PIX">
                                            <span>PIX</span>
                                        </label>
                                        <label class="opcao-pagamento">
                                            <input type="radio" name="forma_pagamento" value="CARTAO">
                                            <span>Cartão</span>
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-finalizar">
                                    <span class="btn-icon"></span> Finalizar Pedido
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alerta-vazio">
                            <div class="alerta-icon"></div>
                            <p class="alerta-texto">Nenhum item adicionado ainda.</p>
                            <p class="alerta-subtexto">Selecione produtos acima para começar!</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <?php if ($id_pedido_atual): ?>
                    <div class="alerta-erro">
                        <div class="alerta-icon"></div>
                        <p class="alerta-texto">Erro ao carregar dados do pedido</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        <footer class="app-footer">
            &copy; <?php echo date('Y'); ?> Sistema RestauSys. Todos os direitos reservados.
        </footer>
    </div>

    <script>
        // Atualizar dica de quantidade disponível
        document.getElementById('id_produto')?.addEventListener('change', function() {
            const estoque = this.options[this.selectedIndex].dataset.estoque;
            const hint = document.getElementById('quantidade-hint');
            const quantidadeInput = document.getElementById('quantidade');
            
            if (estoque) {
                hint.textContent = 'Disponível: ' + estoque + ' unidades';
                quantidadeInput.max = estoque;
            }
        });
    </script>
</body>
</html>
