# Sistema de Restaurante

Este é um sistema simples desenvolvido em PHP projetado para ajudar no gerenciamento diário de um restaurante. O sistema utiliza um banco de dados SQLite para armazenamento leve e fácil configuração.

## Funcionalidades

Baseado na estrutura do projeto, o sistema conta com as seguintes funcionalidades principais:

- **Gerenciamento de Clientes** (`pages/clientes.php`): Cadastro, edição e listagem dos clientes do restaurante.
- **Gerenciamento de Mesas** (`pages/mesas.php`): Controle da disponibilidade, reservas e status das mesas do estabelecimento.
- **Gerenciamento de Pedidos** (`pages/pedidos.php`): Realização e acompanhamento dos pedidos feitos nas mesas pelos clientes.

## Tecnologias Utilizadas

- **Backend:** PHP
- **Banco de Dados:** SQLite (`database/sqlite.php`)
- **Estilização:** CSS (`css/style.css`)

## Estrutura do Projeto

```text
├── config/
│   └── config.php       # Configurações gerais do sistema
├── css/
│   └── style.css        # Folha de estilos da aplicação
├── database/
│   └── sqlite.php       # Conexão e manipulação do banco de dados SQLite
├── pages/
│   ├── clientes.php     # Página de gestão de clientes
│   ├── mesas.php        # Página de gestão de mesas
│   └── pedidos.php      # Página de gestão de pedidos
└── index.php            # Ponto de entrada do sistema (tela inicial)
```

## Como Executar

1. Certifique-se de ter um servidor web local configurado com suporte a PHP (como XAMPP, WAMP ou o servidor embutido do PHP).
2. O servidor deve ter a extensão SQLite habilitada em seu `php.ini`.
3. Clone ou mova este diretório para a pasta pública do seu servidor (ex: `htdocs` no XAMPP).
4. Acesse o sistema através do navegador: `http://localhost/php/Sistema_restaurante/`
