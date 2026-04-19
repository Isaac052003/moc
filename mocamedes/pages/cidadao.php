<?php
session_start();

// ==========================
// VERIFICAR SE É CIDADÃO
// ==========================
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

// Verificar se o tipo de usuário é cidadão
if($_SESSION['tipo'] != 'cidadao'){
    // Se for funcionário ou admin, redireciona para o painel correto
    if($_SESSION['tipo'] == 'funcionario' || $_SESSION['tipo'] == 'admin_municipal'){
        header("Location: funcionario.php");
        exit();
    } elseif($_SESSION['tipo'] == 'admin_sistema'){
        header("Location: admin.php");
        exit();
    } else {
        header("Location: index.php");
        exit();
    }
}

// Forçar não usar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once("../includes/conexao.php");

// ==========================
// FUNÇÃO PARA FORMATAR ESTADO NA EXIBIÇÃO
// ==========================
function formatarEstado($estado) {
    if($estado == 'Rececao') {
        return 'Recepção';
    }
    return str_replace('_', ' ', $estado);
}

// ==========================
// LOGOUT (Terminar Sessão)
// ==========================
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$nome_usuario = $_SESSION['nome'];

// ==========================
// BUSCAR NOTIFICAÇÕES NÃO LIDAS
// ==========================
$sql_notificacoes = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_notif = $conexao->prepare($sql_notificacoes);
$stmt_notif->bind_param("i", $usuario_id);
$stmt_notif->execute();
$notificacoes_nao_lidas = $stmt_notif->get_result()->fetch_assoc()['total'];

// ==========================
// BUSCAR ESTATÍSTICAS DO CIDADÃO
// ==========================
try {
    // Total de solicitações
    $sql_total = "SELECT COUNT(*) as total FROM solicitacoes WHERE usuario_id = ?";
    $stmt = $conexao->prepare($sql_total);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $total_solicitacoes = $stmt->get_result()->fetch_assoc()['total'];

    // Solicitações em andamento (todos os estados exceto Levantamento)
    $sql_andamento = "SELECT COUNT(*) as total FROM solicitacoes WHERE usuario_id = ? AND estado != 'Levantamento'";
    $stmt = $conexao->prepare($sql_andamento);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $andamento = $stmt->get_result()->fetch_assoc()['total'];

    // Solicitações concluídas
    $sql_concluidas = "SELECT COUNT(*) as total FROM solicitacoes WHERE usuario_id = ? AND estado = 'Levantamento'";
    $stmt = $conexao->prepare($sql_concluidas);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $concluidas = $stmt->get_result()->fetch_assoc()['total'];

    // Buscar últimas 5 solicitações
    $sql_ultimas = "SELECT 
                        s.id,
                        s.numero_processo,
                        serv.nome as servico,
                        s.estado,
                        s.data_solicitacao,
                        s.data_atualizacao
                    FROM solicitacoes s
                    JOIN servicos serv ON s.servico_id = serv.id
                    WHERE s.usuario_id = ? 
                    ORDER BY s.data_atualizacao DESC, s.id DESC 
                    LIMIT 5";
    $stmt = $conexao->prepare($sql_ultimas);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $ultimas_solicitacoes = $stmt->get_result();
    
} catch (Exception $e) {
    // Registrar erro em log
    error_log("Erro no painel do cidadão: " . $e->getMessage());
    $erro_banco = "Ocorreu um erro ao carregar seus dados. Tente novamente mais tarde.";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Cidadão - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::after {
            content: '👤';
            position: absolute;
            right: 2rem;
            bottom: 0.5rem;
            font-size: 5rem;
            opacity: 0.1;
            transform: rotate(10deg);
        }
        
        .action-card {
            text-align: center;
            padding: 2rem;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .action-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .action-description {
            color: var(--muted-fg);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--muted-fg);
            font-size: 0.875rem;
        }
        
        .quick-link {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: var(--radius);
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        
        .quick-link:hover {
            background: var(--muted);
            border-color: var(--border);
            transform: translateX(5px);
        }
        
        .quick-link-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: 700;
            transition: transform 0.2s;
        }
        
        .quick-link:hover .quick-link-icon {
            transform: scale(1.1);
        }
        
        .notification-badge {
            position: relative;
            display: inline-block;
        }
        
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--destructive);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }
        
        .ultima-atualizacao {
            font-size: 0.8rem;
            color: var(--muted-fg);
            margin-top: 0.5rem;
            text-align: right;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border-left: 4px solid var(--destructive);
        }
    </style>
</head>
<body>
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="container flex items-center justify-between">
            <span>Administração Municipal de Moçâmedes-Província do Namibe</span>
            <span class="desktop-only">Angola</span>
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
                <a href="../index.php">Início</a>
                <a href="servicos.php">Serviços</a>
                <a href="reportar.php">Reportar Problema</a>
                <a href="sugestoes.php">Sugestões</a>
                <a href="noticias.php">Notícias</a>
                <a href="processo.php">Acompanhar Processo</a>
            </nav>

            <div class="nav-auth">
                <!-- Menu do usuário logado com notificações -->
                <div class="user-menu">
                    <button class="btn btn-outline btn-sm notification-badge" onclick="toggleUserDropdown()">
                        <?php echo htmlspecialchars(explode(' ', $nome_usuario)[0]); ?> ▼
                        <?php if($notificacoes_nao_lidas > 0): ?>
                            <span class="notification-count"><?php echo $notificacoes_nao_lidas; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="userDropdown" class="user-dropdown hidden">
                        <a href="perfil.php">👤 Meu Perfil</a>
                        <a href="notificacoes.php">
                            🔔 Notificações 
                            <?php if($notificacoes_nao_lidas > 0): ?>
                                <span class="badge badge-red" style="float: right;"><?php echo $notificacoes_nao_lidas; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="solicitar.php">📝 Nova Solicitação</a>
                        <a href="processo.php">📊 Meus Processos</a>
                        <hr style="border: none; border-top: 1px solid var(--border); margin: 0.25rem 0;">
                        <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
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
            <a href="perfil.php">👤 Meu Perfil</a>
            <a href="notificacoes.php">🔔 Notificações <?php if($notificacoes_nao_lidas > 0): ?>(<?php echo $notificacoes_nao_lidas; ?>)<?php endif; ?></a>
            <a href="solicitar.php">📝 Nova Solicitação</a>
            <a href="processo.php">📊 Meus Processos</a>
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="container" style="padding: 2rem 1rem;">
            
            <?php if(isset($erro_banco)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($erro_banco); ?>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem;">Bem-vindo, <?php echo htmlspecialchars($nome_usuario); ?>! 👋</h2>
                <p style="opacity: 0.9; font-size: 1.1rem;">
                    Acompanhe e gerencie suas solicitações junto à Administração Municipal de Moçâmedes.
                </p>
                <div class="ultima-atualizacao">
                    Última atualização: <?php echo date('d/m/Y H:i:s'); ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-3" style="margin-bottom: 3rem;">
                <div class="card stat-card">
                    <div class="stat-value"><?php echo $total_solicitacoes; ?></div>
                    <div class="stat-label">Total de Solicitações</div>
                </div>
                <div class="card stat-card">
                    <div class="stat-value" style="color: var(--secondary);"><?php echo $andamento; ?></div>
                    <div class="stat-label">Em Andamento</div>
                </div>
                <div class="card stat-card">
                    <div class="stat-value" style="color: var(--primary);"><?php echo $concluidas; ?></div>
                    <div class="stat-label">Concluídas</div>
                </div>
            </div>

            <!-- Main Actions -->
            <h3 style="margin-bottom: 1.5rem;">O que deseja fazer?</h3>
            
            <div class="grid grid-2" style="margin-bottom: 3rem;">
                <!-- Card Fazer Solicitação -->
                <a href="solicitar.php" style="text-decoration: none;">
                    <div class="card action-card">
                        <div class="action-icon">📝</div>
                        <div class="action-title">Fazer Nova Solicitação</div>
                        <div class="action-description">
                            Solicite serviços municipais como regularização de terreno, licenças de construção, autorização para eventos e mais.
                        </div>
                        <span class="btn btn-primary">Solicitar Agora</span>
                    </div>
                </a>

                <!-- Card Acompanhar Processo -->
                <a href="processo.php" style="text-decoration: none;">
                    <div class="card action-card">
                        <div class="action-icon">📊</div>
                        <div class="action-title">Acompanhar Processo</div>
                        <div class="action-description">
                            Verifique o andamento das suas solicitações, consulte o estado atual e veja detalhes de cada processo.
                        </div>
                        <span class="btn btn-primary">Acompanhar</span>
                    </div>
                </a>
            </div>

            <!-- Últimas Solicitações -->
            <?php if($ultimas_solicitacoes && $ultimas_solicitacoes->num_rows > 0): ?>
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div>
                        <h3 style="font-size: 1.25rem;">Últimas Solicitações</h3>
                        <p style="color: var(--muted-fg); font-size: 0.875rem;">
                            Seus processos mais recentes
                        </p>
                    </div>
                    <a href="processo.php" class="btn btn-outline btn-sm">Ver Todos</a>
                </div>
                <div class="card-body">
                    <?php while($s = $ultimas_solicitacoes->fetch_assoc()): 
                        $badgeClass = 'badge-gray';
                        switch($s['estado']){
                            case 'Pendente': $badgeClass = 'badge-yellow'; break;
                            case 'Rececao': $badgeClass = 'badge-blue'; break;
                            case 'Area_tecnica': $badgeClass = 'badge-purple'; break;
                            case 'Vistoria': $badgeClass = 'badge-orange'; break;
                            case 'INOTU': $badgeClass = 'badge-primary'; break;
                            case 'Pagamento': $badgeClass = 'badge-green'; break;
                            case 'Levantamento': $badgeClass = 'badge-secondary'; break;
                        }
                    ?>
                    <div class="quick-link" style="margin-bottom: 0.5rem;">
                        <div class="quick-link-icon">📋</div>
                        <div style="flex: 1;">
                            <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <strong><?php echo htmlspecialchars($s['servico']); ?></strong>
                                    <span class="badge badge-outline" style="margin-left: 0.5rem;">
                                        <?php echo htmlspecialchars($s['numero_processo'] ?? '#' . str_pad($s['id'], 5, '0', STR_PAD_LEFT)); ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo formatarEstado($s['estado']); ?>
                                    </span>
                                    <span style="color: var(--muted-fg); font-size: 0.8rem;">
                                        <?php echo date('d/m/Y', strtotime($s['data_solicitacao'])); ?>
                                    </span>
                                    <a href="processo.php?id=<?php echo $s['id']; ?>" class="btn btn-ghost btn-sm" title="Ver detalhes">
                                        →
                                    </a>
                                </div>
                            </div>
                            <?php if(isset($s['data_atualizacao']) && $s['data_atualizacao'] != $s['data_solicitacao']): ?>
                            <div style="font-size: 0.7rem; color: var(--muted-fg); margin-top: 0.25rem;">
                                Atualizado: <?php echo date('d/m/Y H:i', strtotime($s['data_atualizacao'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Nenhuma solicitação -->
            <div class="card" style="text-align: center; padding: 3rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                <h3 style="margin-bottom: 1rem;">Nenhuma solicitação encontrada</h3>
                <p style="color: var(--muted-fg); margin-bottom: 2rem;">
                    Você ainda não possui nenhum processo em andamento. <br>
                    Inicie uma nova solicitação para acompanhar o andamento.
                </p>
                <a href="solicitar.php" class="btn btn-primary">
                    Fazer Primeira Solicitação
                </a>
            </div>
            <?php endif; ?>

            <!-- Links Úteis -->
            <div style="margin-top: 3rem;">
                <h4 style="margin-bottom: 1rem;">Links Úteis</h4>
                <div class="grid grid-3">
                    <a href="servicos.php" class="card quick-link" style="text-decoration: none;">
                        <span style="font-size: 1.5rem; margin-right: 0.5rem;">🔧</span>
                        <span>Ver todos os serviços</span>
                    </a>
                    <a href="reportar.php" class="card quick-link" style="text-decoration: none;">
                        <span style="font-size: 1.5rem; margin-right: 0.5rem;">⚠️</span>
                        <span>Reportar um problema</span>
                    </a>
                    <a href="sugestoes.php" class="card quick-link" style="text-decoration: none;">
                        <span style="font-size: 1.5rem; margin-right: 0.5rem;">💡</span>
                        <span>Enviar sugestão</span>
                    </a>
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
                        Província do Namibe - Angola
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
                © 2026 Portal do Moçamedense. Todos os direitos reservados.
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

        // NOTA: O auto-refresh foi REMOVIDO para não interromper o uso do assistente
        // O usuário pode recarregar manualmente quando quiser ver atualizações
    </script>
    
    <?php include 'muni_widget.php'; ?>
</body>
</html>