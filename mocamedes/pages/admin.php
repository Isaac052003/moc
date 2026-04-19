<?php
session_start();

// Verificar se é admin do sistema
if(!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] != 'admin_sistema'){
    header("Location: login.php");
    exit();
}

require_once("../includes/conexao.php");

// ==========================
// LOGOUT (Terminar Sessão)
// ==========================
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: login.php");
    exit();
}

// ==========================
// CADASTRO DE USUÁRIO (COM SENHA NORMAL)
// ==========================
if(isset($_POST['cadastrar_usuario'])){

    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $bi = $_POST['bi'];
    $senha = $_POST['senha']; // Senha normal (sem hash)
    $tipo = $_POST['tipo'];

    // Validar senha mínima
    if(strlen($senha) < 6){
        $erro = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        // Verificar se email ou BI já existem
        $check = $conexao->prepare("SELECT id FROM usuarios WHERE email = ? OR bi = ?");
        $check->bind_param("ss", $email, $bi);
        $check->execute();
        $result = $check->get_result();
        
        if($result->num_rows > 0){
            $erro = "Já existe um usuário com este email ou BI.";
        } else {
            // Inserir com senha NORMAL (sem hash)
            $sql = "INSERT INTO usuarios (nome, email, bi, senha, tipo) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conexao->prepare($sql);
            $stmt->bind_param("sssss", $nome, $email, $bi, $senha, $tipo);
            
            if($stmt->execute()){
                $sucesso = "Usuário cadastrado com sucesso! Senha: $senha";
            } else {
                $erro = "Erro ao cadastrar usuário: " . $conexao->error;
            }
        }
    }
}

// ==========================
// ELIMINAR USUÁRIO
// ==========================
if(isset($_GET['eliminar'])){
    $id_eliminar = $_GET['eliminar'];
    
    // Não permitir eliminar o próprio admin
    if($id_eliminar == $_SESSION['usuario_id']){
        $erro = "Não pode eliminar a sua própria conta.";
    } else {
        // Verificar se usuário existe
        $check = $conexao->prepare("SELECT id, tipo FROM usuarios WHERE id = ?");
        $check->bind_param("i", $id_eliminar);
        $check->execute();
        $result = $check->get_result();
        
        if($result->num_rows > 0){
            $usuario = $result->fetch_assoc();
            
            // Não permitir eliminar outros admins do sistema
            if($usuario['tipo'] == 'admin_sistema'){
                $erro = "Não pode eliminar outro administrador do sistema.";
            } else {
                // Eliminar usuário
                $delete = $conexao->prepare("DELETE FROM usuarios WHERE id = ?");
                $delete->bind_param("i", $id_eliminar);
                
                if($delete->execute()){
                    $sucesso = "Usuário eliminado com sucesso!";
                } else {
                    $erro = "Erro ao eliminar usuário: " . $conexao->error;
                }
            }
        } else {
            $erro = "Usuário não encontrado.";
        }
    }
}

// ==========================
// ESTATÍSTICAS COMPLETAS
// ==========================

// Total de usuários por tipo
$stats = [];

// Cidadãos
$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'cidadao'");
$stats['cidadaos'] = $result->fetch_assoc()['total'];

// Funcionários
$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'funcionario'");
$stats['funcionarios'] = $result->fetch_assoc()['total'];

// Admin Municipal
$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'admin_municipal'");
$stats['admin_municipal'] = $result->fetch_assoc()['total'];

// Admin Sistema
$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'admin_sistema'");
$stats['admin_sistema'] = $result->fetch_assoc()['total'];

// Totais gerais
$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios");
$stats['total_geral'] = $result->fetch_assoc()['total'];

$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'ativo'");
$stats['total_ativos'] = $result->fetch_assoc()['total'];

$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'inativo'");
$stats['total_inativos'] = $result->fetch_assoc()['total'];

// Novos usuários este mês
$inicio_mes = date('Y-m-01 00:00:00');
$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE data_registro >= '$inicio_mes'");
$stats['novos_mes'] = $result->fetch_assoc()['total'];

// ==========================
// LISTA DE TODOS OS USUÁRIOS (exceto admin_sistema para não mostrar outros admins)
// ==========================
$sql = "SELECT id, nome, email, bi, tipo, estado, data_registro 
        FROM usuarios 
        WHERE tipo IN ('cidadao', 'funcionario', 'admin_municipal') 
        ORDER BY 
            CASE tipo
                WHEN 'admin_municipal' THEN 1
                WHEN 'funcionario' THEN 2
                WHEN 'cidadao' THEN 3
            END,
            id DESC";
$resultado = $conexao->query($sql);

// ==========================
// GRÁFICO DE DISTRIBUIÇÃO
// ==========================
$dados_grafico = [
    'Cidadãos' => $stats['cidadaos'],
    'Funcionários' => $stats['funcionarios'],
    'Admins Municipais' => $stats['admin_municipal']
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Admin do Sistema - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .stat-card-detailed {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.25rem;
            border: 1px solid var(--border);
            transition: transform 0.2s;
        }
        
        .stat-card-detailed:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .stat-icon-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--muted);
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .filter-section {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-btn-tipo {
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border);
            background: white;
            transition: all 0.2s;
        }
        
        .filter-btn-tipo:hover {
            background: var(--muted);
        }
        
        .filter-btn-tipo.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-eliminar {
            background: var(--destructive);
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 0.8rem;
            transition: opacity 0.2s;
        }
        
        .btn-eliminar:hover {
            opacity: 0.9;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-overlay.hidden {
            display: none;
        }
        
        .modal {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            max-width: 400px;
            width: 90%;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <!-- Modal de confirmação para eliminar -->
    <div id="modalConfirmar" class="modal-overlay hidden">
        <div class="modal">
            <h3 style="margin-bottom: 1rem; color: var(--destructive);">⚠️ Confirmar Eliminação</h3>
            <p id="modalMensagem">Tem certeza que deseja eliminar este usuário?</p>
            <p style="font-size: 0.875rem; color: var(--muted-fg); margin-top: 0.5rem;">Esta ação não pode ser desfeita.</p>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
                <a href="#" id="btnConfirmarEliminar" class="btn btn-destructive">Eliminar</a>
            </div>
        </div>
    </div>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="container flex items-center justify-between">
            <span>Administração Municipal de Moçâmedes — Província do Namibe</span>
            <span class="desktop-only">República de Angola</span>
        </div>
    </div>

    <!-- HEADER -->
    <header class="header">
        <div class="container header-inner">
            <a class="logo" href="index.php">
                <div class="logo-icon">
                    <img src="../assets/img/favicon.ico" alt="Brasão" style="width: 24px; height: 24px;">
                </div>
                <div class="logo-text">
                    <h1>Portal do Moçamedense</h1>
                    <p>Administração Municipal</p>
                </div>
            </a>

            <nav class="nav">
                <a href="index.php">Início</a>
                <a href="servicos.php">Serviços</a>
                <a href="reportar.php">Reportar Problema</a>
                <a href="sugestoes.php">Sugestões</a>
                <a href="noticias.php">Notícias</a>
                <a href="processo.php">Acompanhar Processo</a>
            </nav>

            <div class="nav-auth">
                <!-- Menu do usuário logado -->
                <div class="user-menu">
                    <button class="btn btn-outline btn-sm" onclick="toggleUserDropdown()">
                        <?php echo $_SESSION['nome']; ?> ▼
                    </button>
                    <div id="userDropdown" class="user-dropdown hidden">
                        <a href="perfil.php">Meu Perfil</a>
                        <a href="configuracoes.php">Configurações</a>
                        <hr style="border: none; border-top: 1px solid var(--border); margin: 0.25rem 0;">
                        <a href="?logout=1" style="color: var(--destructive);">Terminar Sessão</a>
                    </div>
                </div>
            </div>

            <button class="mobile-toggle" onclick="document.querySelector('.mobile-nav').classList.toggle('open')">☰</button>
        </div>

        <!-- Mobile Navigation -->
        <div class="mobile-nav hidden">
            <a href="index.php">Início</a>
            <a href="servicos.php">Serviços</a>
            <a href="reportar.php">Reportar Problema</a>
            <a href="sugestoes.php">Sugestões</a>
            <a href="noticias.php">Notícias</a>
            <a href="processo.php">Acompanhar Processo</a>
            <hr style="border: none; border-top: 1px solid var(--border); margin: 0.5rem 0;">
            <a href="perfil.php">Meu Perfil</a>
            <a href="configuracoes.php">Configurações</a>
            <a href="?logout=1" style="color: var(--destructive);">Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="container" style="padding: 2rem 1rem;">
            <!-- Título e breadcrumb -->
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Painel do Administrador do Sistema</h1>
                <p style="color: var(--muted-fg);">
                    <a href="index.php" style="color: var(--primary);">Início</a> / 
                    <span>Painel Admin</span>
                </p>
            </div>

            <!-- Mensagens de sucesso/erro -->
            <?php if(isset($sucesso)): ?>
                <div class="badge badge-green" style="display: block; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius); background: #dcfce7; color: #166534; font-weight: normal;">
                    <?php echo $sucesso; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($erro)): ?>
                <div class="badge badge-red" style="display: block; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius); background: #fee2e2; color: #991b1b; font-weight: normal;">
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <!-- Cards de Estatísticas Detalhadas -->
            <div class="grid grid-4" style="margin-bottom: 2rem;">
                <div class="stat-card-detailed">
                    <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
                        <div class="stat-icon-small" style="background: #dbeafe; color: #1e40af;">👥</div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--muted-fg);">Total Geral</span>
                            <h3 style="font-size: 2rem; line-height: 1;"><?php echo $stats['total_geral']; ?></h3>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 100%"></div>
                    </div>
                    <div class="flex justify-between" style="font-size: 0.8rem;">
                        <span>Ativos: <?php echo $stats['total_ativos']; ?></span>
                        <span>Inativos: <?php echo $stats['total_inativos']; ?></span>
                    </div>
                </div>

                <div class="stat-card-detailed">
                    <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
                        <div class="stat-icon-small" style="background: #dbeafe; color: #1e40af;">👤</div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--muted-fg);">Cidadãos</span>
                            <h3 style="font-size: 2rem; line-height: 1;"><?php echo $stats['cidadaos']; ?></h3>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <?php $percent = $stats['total_geral'] > 0 ? round(($stats['cidadaos'] / $stats['total_geral']) * 100) : 0; ?>
                        <div class="progress-fill" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <div style="font-size: 0.8rem;"><?php echo $percent; ?>% do total</div>
                </div>

                <div class="stat-card-detailed">
                    <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
                        <div class="stat-icon-small" style="background: #dcfce7; color: #166534;">👨‍💼</div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--muted-fg);">Funcionários</span>
                            <h3 style="font-size: 2rem; line-height: 1;"><?php echo $stats['funcionarios']; ?></h3>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <?php $percent = $stats['total_geral'] > 0 ? round(($stats['funcionarios'] / $stats['total_geral']) * 100) : 0; ?>
                        <div class="progress-fill" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <div style="font-size: 0.8rem;"><?php echo $percent; ?>% do total</div>
                </div>

                <div class="stat-card-detailed">
                    <div class="flex items-center gap-2" style="margin-bottom: 1rem;">
                        <div class="stat-icon-small" style="background: #f3e8ff; color: #7c3aed;">👑</div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--muted-fg);">Admins</span>
                            <h3 style="font-size: 2rem; line-height: 1;"><?php echo $stats['admin_municipal'] + $stats['admin_sistema']; ?></h3>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: space-between;">
                        <span style="font-size: 0.8rem;">Municipal: <?php echo $stats['admin_municipal']; ?></span>
                        <span style="font-size: 0.8rem;">Sistema: <?php echo $stats['admin_sistema']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Grid de 2 colunas para formulário e gráfico -->
            <div class="grid grid-2" style="gap: 2rem; margin-bottom: 2rem;">
                <!-- FORMULÁRIO DE CADASTRO (COM SENHA NORMAL) -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="font-size: 1.25rem;">Cadastrar Novo Usuário</h3>
                        <p style="color: var(--muted-fg); font-size: 0.875rem;">Preencha os dados para criar um novo funcionário ou admin municipal</p>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="nome">Nome Completo</label>
                                <input type="text" id="nome" name="nome" class="form-control" placeholder="Digite o nome completo" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="email@exemplo.com" required>
                            </div>

                            <div class="form-group">
                                <label for="bi">Nº Bilhete de Identidade</label>
                                <input type="text" id="bi" name="bi" class="form-control" placeholder="000000000LA000" required>
                            </div>

                            <div class="form-group">
                                <label for="senha">Palavra-passe</label>
                                <input type="text" id="senha" name="senha" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
                                <small style="color: var(--muted-fg);">Senha em texto normal (sem criptografia)</small>
                            </div>

                            <div class="form-group">
                                <label for="tipo">Tipo de Usuário</label>
                                <select id="tipo" name="tipo" class="form-control" required>
                                    <option value="funcionario">Funcionário</option>
                                    <option value="admin_municipal">Administrador Municipal</option>
                                </select>
                            </div>

                            <button type="submit" name="cadastrar_usuario" class="btn btn-primary btn-block">
                                Cadastrar Usuário
                            </button>
                        </form>
                    </div>
                </div>

                <!-- GRÁFICO DE DISTRIBUIÇÃO -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="font-size: 1.25rem;">Distribuição de Usuários</h3>
                        <p style="color: var(--muted-fg); font-size: 0.875rem;">Percentual por tipo de usuário</p>
                    </div>
                    <div class="card-body">
                        <div class="chart-placeholder" style="height: 250px; display: block;">
                            <div style="padding: 1rem;">
                                <?php foreach($dados_grafico as $tipo => $quantidade): 
                                    $percent = $stats['total_geral'] > 0 ? round(($quantidade / $stats['total_geral']) * 100) : 0;
                                ?>
                                <div style="margin-bottom: 1.5rem;">
                                    <div class="flex justify-between" style="margin-bottom: 0.3rem;">
                                        <span><?php echo $tipo; ?></span>
                                        <span><strong><?php echo $quantidade; ?></strong> (<?php echo $percent; ?>%)</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                    <div class="flex justify-between">
                                        <span>📅 Novos este mês:</span>
                                        <span class="badge badge-green">+<?php echo $stats['novos_mes']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTROS RÁPIDOS -->
            <div style="margin-bottom: 1rem;">
                <div class="filter-section">
                    <button class="filter-btn-tipo active" onclick="filtrarTodos()">Todos</button>
                    <button class="filter-btn-tipo" onclick="filtrarTipo('cidadao')">Cidadãos</button>
                    <button class="filter-btn-tipo" onclick="filtrarTipo('funcionario')">Funcionários</button>
                    <button class="filter-btn-tipo" onclick="filtrarTipo('admin_municipal')">Admins Municipais</button>
                </div>
            </div>

            <!-- LISTA DE USUÁRIOS COM OPÇÃO DE ELIMINAR -->
            <div class="card">
                <div class="card-header">
                    <h3 style="font-size: 1.25rem;">Todos os Usuários</h3>
                    <p style="color: var(--muted-fg); font-size: 0.875rem;">Lista completa de cidadãos, funcionários e administradores municipais</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="tabelaUsuarios">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>BI</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Data Registro</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = $resultado->fetch_assoc()){ 
                                    $tipoClass = 'badge-blue';
                                    $tipoLabel = 'Cidadão';
                                    $tipoFilter = 'cidadao';
                                    
                                    if($u['tipo'] == 'funcionario'){
                                        $tipoClass = 'badge-green';
                                        $tipoLabel = 'Funcionário';
                                        $tipoFilter = 'funcionario';
                                    } elseif($u['tipo'] == 'admin_municipal'){
                                        $tipoClass = 'badge-purple';
                                        $tipoLabel = 'Admin Municipal';
                                        $tipoFilter = 'admin_municipal';
                                    }
                                    
                                    $estadoClass = $u['estado'] == 'ativo' ? 'badge-green' : 'badge-red';
                                ?>
                                <tr data-tipo="<?php echo $tipoFilter; ?>">
                                    <td><span class="badge badge-outline">#<?php echo $u['id']; ?></span></td>
                                    <td><strong><?php echo $u['nome']; ?></strong></td>
                                    <td><?php echo $u['email']; ?></td>
                                    <td><?php echo $u['bi']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $tipoClass; ?>">
                                            <?php echo $tipoLabel; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $estadoClass; ?>">
                                            <?php echo ucfirst($u['estado']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($u['data_registro'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                            <form method="POST" action="atualizar_estado.php" style="display: flex; gap: 0.3rem;">
                                                <input type="hidden" name="usuario_id" value="<?php echo $u['id']; ?>">
                                                <select name="estado" class="form-control" style="width: auto; padding: 0.3rem; font-size: 0.8rem;">
                                                    <option value="ativo" <?php if($u['estado']=='ativo') echo 'selected'; ?>>Ativo</option>
                                                    <option value="inativo" <?php if($u['estado']=='inativo') echo 'selected'; ?>>Inativo</option>
                                                </select>
                                                <button type="submit" class="btn btn-outline btn-sm" style="padding: 0.3rem 0.5rem;">✓</button>
                                            </form>
                                            
                                            <?php if($u['id'] != $_SESSION['usuario_id']): ?>
                                                <button onclick="confirmarEliminar(<?php echo $u['id']; ?>, '<?php echo addslashes($u['nome']); ?>')" class="btn-eliminar">
                                                    🗑️
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="grid grid-3">
                <div>
                    <h3>Portal do Moçâmedense</h3>
                    <p>
                        Administração Municipal de Moçâmedes<br>
                        Província do Namibe — Angola
                    </p>
                </div>

                <div>
                    <h4>Links Rápidos</h4>
                    <div class="footer-links">
                        <a href="servicos.php">Serviços</a>
                        <a href="noticias.php">Notícias</a>
                        <a href="reportar.php">Reportar Problema</a>
                        <a href="sugestoes.php">Sugestões</a>
                    </div>
                </div>

                <div>
                    <h4>Contactos</h4>
                    <p>
                        Moçâmedes, Namibe<br>
                        Angola<br>
                        Tel: +244 000 000 000
                    </p>
                </div>
            </div>

            <div class="footer-bottom">
                © 2026 Administração Municipal de Moçâmedes. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <script>
        // Toggle do menu dropdown do usuário
        function toggleUserDropdown() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        }

        // Fechar dropdown ao clicar fora
        window.onclick = function(event) {
            if (!event.target.matches('.btn-outline')) {
                var dropdowns = document.getElementsByClassName("user-dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (!openDropdown.classList.contains('hidden')) {
                        openDropdown.classList.add('hidden');
                    }
                }
            }
        }

        // Mobile menu toggle
        document.querySelector('.mobile-toggle').addEventListener('click', function() {
            document.querySelector('.mobile-nav').classList.toggle('hidden');
        });

        // Funções de filtro
        function filtrarTodos() {
            const linhas = document.querySelectorAll('#tabelaUsuarios tbody tr');
            linhas.forEach(linha => linha.style.display = '');
            
            document.querySelectorAll('.filter-btn-tipo').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function filtrarTipo(tipo) {
            const linhas = document.querySelectorAll('#tabelaUsuarios tbody tr');
            linhas.forEach(linha => {
                if(linha.dataset.tipo === tipo) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
            
            document.querySelectorAll('.filter-btn-tipo').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        // Modal de confirmação para eliminar
        const modal = document.getElementById('modalConfirmar');
        const btnConfirmar = document.getElementById('btnConfirmarEliminar');
        const modalMensagem = document.getElementById('modalMensagem');

        function confirmarEliminar(id, nome) {
            modalMensagem.textContent = `Tem certeza que deseja eliminar o usuário "${nome}"?`;
            btnConfirmar.href = `?eliminar=${id}`;
            modal.classList.remove('hidden');
        }

        function fecharModal() {
            modal.classList.add('hidden');
        }

        // Fechar modal ao clicar fora
        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                fecharModal();
            }
        });

        // Validação do formulário de cadastro
        document.querySelector('form').addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            if(senha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres.');
            }
        });
    </script>
</body>
</html>