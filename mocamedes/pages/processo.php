<?php
session_start();

// ==========================
// VERIFICAÇÕES INICIAIS
// ==========================
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

require_once("../includes/conexao.php");

// ==========================
// DEFINIR VARIÁVEIS DO USUÁRIO
// ==========================
$usuario_id = $_SESSION['usuario_id'];
$nome_usuario = $_SESSION['nome'];
$tipo_usuario = $_SESSION['tipo'];

// ==========================
// LOGOUT (Terminar Sessão)
// ==========================
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: login.php");
    exit();
}

// ==========================
// FORÇAR ATUALIZAÇÃO DOS DADOS
// ==========================
@$conexao->query("SET SESSION query_cache_type = OFF");

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
// BUSCAR NOTIFICAÇÕES NÃO LIDAS
// ==========================
$notificacoes_nao_lidas = 0;
$sql_notificacoes = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_notif = $conexao->prepare($sql_notificacoes);
if($stmt_notif){
    $stmt_notif->bind_param("i", $usuario_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    if($result_notif){
        $notificacoes_nao_lidas = $result_notif->fetch_assoc()['total'];
    }
    $stmt_notif->close();
}

// ==========================
// BUSCAR SOLICITAÇÕES DO USUÁRIO
// ==========================
$solicitacoes = null;
$sql = "SELECT 
            s.id,
            s.numero_processo,
            serv.nome as servico,
            s.estado,
            s.data_solicitacao,
            s.data_atualizacao,
            s.descricao
        FROM solicitacoes s
        JOIN servicos serv ON s.servico_id = serv.id
        WHERE s.usuario_id = ? 
        ORDER BY s.data_atualizacao DESC, s.id DESC";

$stmt = $conexao->prepare($sql);
if($stmt){
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $solicitacoes = $stmt->get_result();
} else {
    $solicitacoes = new stdClass();
    $solicitacoes->num_rows = 0;
}

// ==========================
// SE FOI SELECIONADA UMA SOLICITAÇÃO ESPECÍFICA
// ==========================
$solicitacao_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$solicitacao_especifica = null;

if($solicitacao_id > 0 && $solicitacoes && $solicitacoes->num_rows > 0){
    $sql_especifica = "SELECT 
                        s.id,
                        s.numero_processo,
                        s.descricao,
                        s.estado,
                        s.data_solicitacao,
                        s.data_atualizacao,
                        serv.nome as servico
                      FROM solicitacoes s
                      JOIN servicos serv ON s.servico_id = serv.id
                      WHERE s.id = ? AND s.usuario_id = ?";
    $stmt_esp = $conexao->prepare($sql_especifica);
    if($stmt_esp){
        $stmt_esp->bind_param("ii", $solicitacao_id, $usuario_id);
        $stmt_esp->execute();
        $result_esp = $stmt_esp->get_result();
        
        if($result_esp && $result_esp->num_rows > 0){
            $solicitacao_especifica = $result_esp->fetch_assoc();
        }
        $stmt_esp->close();
    }
}

// ==========================
// DETERMINAR QUAL SOLICITAÇÃO MOSTRAR NA TIMELINE
// ==========================
$solicitacao_timeline = $solicitacao_especifica;

if(!$solicitacao_timeline && $solicitacoes && $solicitacoes->num_rows > 0){
    $solicitacoes->data_seek(0);
    $solicitacao_timeline = $solicitacoes->fetch_assoc();
    $solicitacoes->data_seek(0);
}

// ==========================
// CALCULAR STATUS PARA TIMELINE
// ==========================
$progresso = 1;
$estado_atual = "Nenhuma solicitação encontrada";
$data_atualizacao = null;
$tem_numero_processo = false;

if($solicitacao_timeline){
    $estado_atual = $solicitacao_timeline['estado'];
    $data_atualizacao = $solicitacao_timeline['data_atualizacao'];
    $tem_numero_processo = !empty($solicitacao_timeline['numero_processo']);
    
    switch($estado_atual){
        case "Pendente": $progresso = 1; break;
        case "Rececao": $progresso = 2; break;
        case "Area_tecnica": $progresso = 3; break;
        case "Vistoria": $progresso = 4; break;
        case "INOTU": $progresso = 5; break;
        case "Pagamento": $progresso = 6; break;
        case "Levantamento": $progresso = 7; break;
    }
}

// ==========================
// BUSCAR GUIA DE PAGAMENTO (RUPE) SE O PROCESSO ESTIVER EM PAGAMENTO
// ==========================
$guia_pagamento = null;
if($progresso == 6 && $solicitacao_timeline){
    $sql_guia = "SELECT * FROM guias_pagamento 
                 WHERE solicitacao_id = ? AND estado = 'pendente'
                 ORDER BY id DESC LIMIT 1";
    $stmt_guia = $conexao->prepare($sql_guia);
    $stmt_guia->bind_param("i", $solicitacao_timeline['id']);
    $stmt_guia->execute();
    $guia_pagamento = $stmt_guia->get_result()->fetch_assoc();
}

// DEBUG - Remova depois
// echo "<!-- DEBUG: Estado atual = " . $estado_atual . ", Progresso = " . $progresso . " -->";
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhamento de Processos - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .timeline-container {
            padding: 2rem 0;
            overflow-x: auto;
        }
        
        .timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-width: 800px;
            position: relative;
            padding: 1rem 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 35px;
            left: 50px;
            right: 50px;
            height: 4px;
            background: var(--border);
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
            opacity: 0.5;
            transition: all 0.3s;
        }
        
        .step.active {
            opacity: 1;
        }
        
        .step.completed {
            opacity: 0.8;
        }
        
        .circle {
            width: 50px;
            height: 50px;
            background: white;
            border: 3px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--muted-fg);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .step.active .circle {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 0 4px rgba(26, 82, 118, 0.2);
        }
        
        .step.completed .circle {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
        }
        
        .label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted-fg);
            max-width: 100px;
            margin: 0 auto;
        }
        
        .step.active .label {
            color: var(--primary);
            font-weight: 700;
        }
        
        .notification-card {
            background: var(--muted);
            border-left: 4px solid var(--primary);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin: 2rem 0;
        }
        
        .info-box {
            background: var(--muted);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary);
        }
        
        .processo-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .processo-tag {
            padding: 0.5rem 1rem;
            background: var(--muted);
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
            text-decoration: none;
            color: var(--fg);
        }
        
        .processo-tag:hover {
            background: var(--primary-light);
            color: white;
        }
        
        .processo-tag.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
            min-width: 20px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .estado-destaque {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--muted) 0%, white 100%);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        
        .progresso-bar {
            width: 100%;
            height: 10px;
            background: var(--border);
            border-radius: 5px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progresso-fill {
            height: 100%;
            background: var(--primary);
            transition: width 0.5s;
            border-radius: 5px;
        }
        
        .badge-sem-numero {
            background: #fff3cd;
            color: #856404;
            border: 1px dashed #ffc107;
            padding: 0.3rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .numero-processo-destaque {
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .info-recepcao {
            background: #e8f4fd;
            border-left: 4px solid var(--primary);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        
        .info-recepcao .icone {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        /* Estilos para a guia RUPE */
        .guia-rupe {
            margin-top: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #e8f4fd 0%, white 100%);
            border-radius: var(--radius);
            border: 2px solid var(--primary);
            position: relative;
            overflow: hidden;
        }
        
        .guia-rupe::before {
            content: '💰';
            position: absolute;
            right: 1rem;
            bottom: 0.5rem;
            font-size: 4rem;
            opacity: 0.1;
            transform: rotate(10deg);
        }
        
        .guia-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .guia-header h4 {
            color: var(--primary);
            font-size: 1.3rem;
            margin: 0;
        }
        
        .rupe-numero {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            background: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            border: 2px dashed var(--primary);
            display: inline-block;
            letter-spacing: 2px;
        }
        
        .guia-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .guia-info-item {
            padding: 0.8rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .guia-info-label {
            font-size: 0.8rem;
            color: var(--muted-fg);
            margin-bottom: 0.3rem;
        }
        
        .guia-info-valor {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .guia-acoes {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .btn-guia {
            background: var(--primary);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-guia:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }
        
        .btn-guia-secundario {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-guia-secundario:hover {
            background: var(--primary);
            color: white;
        }
        
        .data-limite {
            color: #856404;
            background: #fff3cd;
            padding: 0.3rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.9rem;
            display: inline-block;
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
                <a href="index.php">Início</a>
                <a href="servicos.php">Serviços</a>
                <a href="reportar.php">Reportar Problema</a>
                <a href="sugestoes.php">Sugestões</a>
                <a href="noticias.php">Notícias</a>
                <a href="processo.php" class="active">Acompanhar Processo</a>
            </nav>

            <div class="nav-auth">
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
                        <hr style="border: none; border-top: 1px solid var(--border); margin: 0.25rem 0;">
                        <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
                    </div>
                </div>
            </div>

            <button class="mobile-toggle" onclick="document.querySelector('.mobile-nav').classList.toggle('open')">☰</button>
        </div>

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
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="container" style="padding: 2rem 1rem;">
            <!-- Título e breadcrumb -->
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Acompanhamento de Processos</h1>
                <p style="color: var(--muted-fg);">
                    <a href="index.php" style="color: var(--primary);">Início</a> / 
                    <span>Acompanhar Processo</span>
                </p>
            </div>

            <?php if($solicitacoes && $solicitacoes->num_rows > 0): ?>
                
                <!-- Seletor de Processos -->
                <?php if($solicitacoes->num_rows > 1): ?>
                <div class="processo-selector">
                    <span style="padding: 0.5rem; color: var(--muted-fg);">Ver processo:</span>
                    <?php 
                    $solicitacoes->data_seek(0);
                    while($proc = $solicitacoes->fetch_assoc()): 
                        $active = ($solicitacao_especifica && $solicitacao_especifica['id'] == $proc['id']) ? 'active' : '';
                        $tem_numero = !empty($proc['numero_processo']);
                    ?>
                        <a href="processo.php?id=<?php echo $proc['id']; ?>" class="processo-tag <?php echo $active; ?>" title="<?php echo $tem_numero ? $proc['numero_processo'] : 'Aguardando número'; ?>">
                            <?php 
                            if($tem_numero){
                                echo htmlspecialchars($proc['numero_processo']);
                            } else {
                                echo '#' . str_pad($proc['id'], 5, '0', STR_PAD_LEFT) . ' ⏳';
                            }
                            ?>
                        </a>
                    <?php endwhile; 
                    $solicitacoes->data_seek(0);
                    ?>
                </div>
                <?php endif; ?>

                <!-- Indicador de última atualização -->
                <?php if($solicitacao_timeline): ?>
                <div class="info-box">
                    <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <strong>Processo:</strong> 
                            <?php if($tem_numero_processo): ?>
                                <span class="numero-processo-destaque"><?php echo htmlspecialchars($solicitacao_timeline['numero_processo']); ?></span>
                            <?php else: ?>
                                <span class="badge-sem-numero">⏳ Aguardando recepção</span>
                            <?php endif; ?>
                        </div>
                        <span id="data-atualizacao" class="badge badge-outline">
                            📅 <?php echo date('d/m/Y H:i:s', strtotime($data_atualizacao)); ?>
                        </span>
                    </div>
                    
                    <?php if(!$tem_numero_processo && $estado_atual == 'Pendente'): ?>
                    <div style="margin-top: 0.8rem; padding: 0.5rem; background: #fff3cd; border-radius: var(--radius); font-size: 0.9rem;">
                        <span style="font-weight: 600;">ℹ️ Informação:</span> O número do processo será gerado quando a administração fizer a recepção da sua solicitação.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Timeline do processo -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3 style="font-size: 1.25rem;">Andamento do Processo</h3>
                        <p style="color: var(--muted-fg); font-size: 0.875rem;">
                            Acompanhe o estado atual da sua solicitação
                        </p>
                    </div>
                    <div class="card-body">
                        
                        <!-- Destaque do Estado Atual -->
                        <?php
                            $badgeClass = 'badge-gray';
                            
                            switch($estado_atual){
                                case 'Pendente': $badgeClass = 'badge-yellow'; break;
                                case 'Rececao': $badgeClass = 'badge-blue'; break;
                                case 'Area_tecnica': $badgeClass = 'badge-purple'; break;
                                case 'Vistoria': $badgeClass = 'badge-orange'; break;
                                case 'INOTU': $badgeClass = 'badge-primary'; break;
                                case 'Pagamento': $badgeClass = 'badge-green'; break;
                                case 'Levantamento': $badgeClass = 'badge-secondary'; break;
                            }
                        ?>
                        
                        <div class="estado-destaque">
                            <div style="font-size: 0.9rem; color: var(--muted-fg); margin-bottom: 0.5rem;">Estado Atual do Processo</div>
                            <span class="badge <?php echo $badgeClass; ?>" style="font-size: 1.5rem; padding: 1rem 2rem;">
                                <?php echo formatarEstado($estado_atual); ?>
                            </span>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                                Progresso: <?php echo $progresso; ?> de 7
                            </div>
                        </div>

                        <!-- Barra de Progresso -->
                        <div style="margin-bottom: 2rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="font-size: 0.8rem;">Início</span>
                                <span style="font-size: 0.8rem;">Conclusão</span>
                            </div>
                            <div class="progresso-bar">
                                <div class="progresso-fill" style="width: <?php echo ($progresso / 7) * 100; ?>%;"></div>
                            </div>
                            <div style="text-align: center; margin-top: 0.5rem; font-weight: 600;">
                                <?php echo round(($progresso / 7) * 100); ?>% concluído
                            </div>
                        </div>

                        <!-- Timeline Visual -->
                        <div class="timeline-container">
                            <div class="timeline">
                                <!-- Passo 1 -->
                                <div class="step <?php echo ($progresso >= 1) ? 'active' : ''; ?> <?php echo ($progresso > 1) ? 'completed' : ''; ?>">
                                    <div class="circle">1</div>
                                    <div class="label">Pendente</div>
                                </div>

                                <!-- Passo 2 -->
                                <div class="step <?php echo ($progresso >= 2) ? 'active' : ''; ?> <?php echo ($progresso > 2) ? 'completed' : ''; ?>">
                                    <div class="circle">2</div>
                                    <div class="label">Recepção</div>
                                </div>

                                <!-- Passo 3 -->
                                <div class="step <?php echo ($progresso >= 3) ? 'active' : ''; ?> <?php echo ($progresso > 3) ? 'completed' : ''; ?>">
                                    <div class="circle">3</div>
                                    <div class="label">Área Técnica</div>
                                </div>

                                <!-- Passo 4 -->
                                <div class="step <?php echo ($progresso >= 4) ? 'active' : ''; ?> <?php echo ($progresso > 4) ? 'completed' : ''; ?>">
                                    <div class="circle">4</div>
                                    <div class="label">Vistoria</div>
                                </div>

                                <!-- Passo 5 -->
                                <div class="step <?php echo ($progresso >= 5) ? 'active' : ''; ?> <?php echo ($progresso > 5) ? 'completed' : ''; ?>">
                                    <div class="circle">5</div>
                                    <div class="label">INOTU</div>
                                </div>

                                <!-- Passo 6 -->
                                <div class="step <?php echo ($progresso >= 6) ? 'active' : ''; ?> <?php echo ($progresso > 6) ? 'completed' : ''; ?>">
                                    <div class="circle">6</div>
                                    <div class="label">Pagamento</div>
                                </div>

                                <!-- Passo 7 -->
                                <div class="step <?php echo ($progresso >= 7) ? 'active' : ''; ?>">
                                    <div class="circle">7</div>
                                    <div class="label">Levantamento</div>
                                </div>
                            </div>
                        </div>

                        <!-- Descrição do processo -->
                        <?php if($solicitacao_especifica && !empty($solicitacao_especifica['descricao'])): ?>
                        <div style="margin-top: 2rem; padding: 1rem; background: var(--muted); border-radius: var(--radius);">
                            <h5 style="margin-bottom: 0.5rem;">📝 Descrição do Pedido:</h5>
                            <p style="white-space: pre-line;"><?php echo nl2br(htmlspecialchars($solicitacao_especifica['descricao'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- ========================================= -->
                        <!-- GUIA DE PAGAMENTO RUPE (se estiver em pagamento) -->
                        <!-- ========================================= -->
                        <?php if($progresso == 6 && $guia_pagamento): ?>
                        <div class="guia-rupe">
                            <div class="guia-header">
                                <span style="font-size: 2.5rem;">💰</span>
                                <h4>Guia de Pagamento - RUPE</h4>
                            </div>
                            
                            <div style="text-align: center; margin-bottom: 1.5rem;">
                                <div class="rupe-numero">
                                    <?php echo formatarRUPE($guia_pagamento['rupe']); ?>
                                </div>
                                <div style="margin-top: 0.5rem;">
                                    <span class="badge badge-primary">Registro Único de Pagamento ao Estado</span>
                                </div>
                            </div>
                            
                            <div class="guia-info-grid">
                                <div class="guia-info-item">
                                    <div class="guia-info-label">NIF do Contribuinte</div>
                                    <div class="guia-info-valor"><?php echo htmlspecialchars($guia_pagamento['nif_contribuinte']); ?></div>
                                </div>
                                
                                <div class="guia-info-item">
                                    <div class="guia-info-label">Nome / Designação</div>
                                    <div class="guia-info-valor"><?php echo htmlspecialchars($guia_pagamento['nome_contribuinte']); ?></div>
                                </div>
                                
                                <div class="guia-info-item">
                                    <div class="guia-info-label">Serviço</div>
                                    <div class="guia-info-valor"><?php echo htmlspecialchars($guia_pagamento['servico_nome']); ?></div>
                                </div>
                                
                                <div class="guia-info-item">
                                    <div class="guia-info-label">Valor a Pagar</div>
                                    <div class="guia-info-valor" style="color: var(--primary); font-size: 1.3rem;">
                                        <?php echo formatarKwanzas($guia_pagamento['valor']); ?>
                                    </div>
                                </div>
                                
                                <div class="guia-info-item">
                                    <div class="guia-info-label">Data de Emissão</div>
                                    <div class="guia-info-valor"><?php echo date('d/m/Y', strtotime($guia_pagamento['data_emissao'])); ?></div>
                                </div>
                                
                                <div class="guia-info-item">
                                    <div class="guia-info-label">Data Limite</div>
                                    <div class="guia-info-valor">
                                        <span class="data-limite">
                                            ⏰ <?php echo date('d/m/Y', strtotime($guia_pagamento['data_limite'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="background: white; padding: 1rem; border-radius: var(--radius); margin: 1rem 0;">
                                <h5 style="margin-bottom: 0.8rem;">📋 Dados Bancários para Pagamento</h5>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                                    <div>
                                        <div class="guia-info-label">Banco</div>
                                        <div>Empresa Interbancária de Serviços, SA</div>
                                    </div>
                                    <div>
                                        <div class="guia-info-label">Balcão</div>
                                        <div>Emis</div>
                                    </div>
                                    <div>
                                        <div class="guia-info-label">Referência</div>
                                        <div style="font-family: monospace;"><?php echo substr($guia_pagamento['rupe'], -8); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="guia-acoes">
                                <a href="guia_pagamento.php?rupe=<?php echo $guia_pagamento['rupe']; ?>" class="btn-guia">
                                    📄 Ver Guia Completa
                                </a>
                                <a href="guia_pagamento.php?rupe=<?php echo $guia_pagamento['rupe']; ?>&print=1" class="btn-guia btn-guia-secundario" target="_blank">
                                    🖨️ Imprimir Guia
                                </a>
                            </div>
                            
                            <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--muted-fg); text-align: center;">
                                <p>⚠️ O pagamento deve ser efetuado até a data limite para dar continuidade ao processo.</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Mensagem Informativa -->
                        <div class="notification-card">
                            <h4 style="margin-bottom: 1rem; color: var(--primary);">📋 Informação do Processo</h4>
                            <p style="font-size: 1rem; line-height: 1.6;">
                                <?php
                                switch($progresso){
                                    case 1:
                                        echo "✅ <strong>Pedido recebido com sucesso!</strong> O seu processo está pendente de verificação inicial. ";
                                        echo "O número do processo será gerado quando a administração fizer a recepção da sua solicitação.";
                                        break;
                                    case 2:
                                        echo "📥 <strong>Processo em análise inicial.</strong> O seu pedido foi recebido oficialmente pela Administração Municipal. ";
                                        if($tem_numero_processo){
                                            echo "Número do processo: <strong>" . $solicitacao_timeline['numero_processo'] . "</strong>";
                                        }
                                        break;
                                    case 3:
                                        echo "🔧 <strong>Em análise técnica.</strong> O processo está sendo avaliado pelos técnicos da Administração Municipal.";
                                        break;
                                    case 4:
                                        echo "🏗️ <strong>Aguardando vistoria.</strong> Foi agendada uma vistoria no local.";
                                        break;
                                    case 5:
                                        echo "📐 <strong>Processo no INOTU.</strong> Seu processo foi encaminhado ao INOTU para elaboração do croquis.";
                                        break;
                                    case 6:
                                        echo "💰 <strong>Aguardando pagamento.</strong> O croquis foi emitido. Dirija-se à Administração Municipal para efetuar o pagamento.<br>";
                                        echo "Utilize o <strong>RUPE</strong> gerado para efetuar o pagamento.";
                                        break;
                                    case 7:
                                        echo "🎉 <strong>Processo concluído!</strong> Parabéns! Seu processo está finalizado. Pode levantar a documentação.";
                                        break;
                                    default:
                                        echo "📋 Acompanhe o andamento do seu processo através desta timeline.";
                                }
                                ?>
                            </p>
                            
                            <?php if(!$tem_numero_processo && $progresso == 1): ?>
                            <div style="margin-top: 1rem; padding: 0.8rem; background: #fff3cd; border-radius: var(--radius);">
                                <strong>🔑 Número do processo:</strong> Será gerado na recepção
                            </div>
                            <?php elseif($tem_numero_processo && $progresso != 6): ?>
                            <div style="margin-top: 1rem; padding: 0.8rem; background: #d4edda; border-radius: var(--radius);">
                                <strong>🔑 Número do processo:</strong> 
                                <span style="font-family: monospace; font-size: 1.2rem;"><?php echo $solicitacao_timeline['numero_processo']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Lista de todas as solicitações -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="font-size: 1.25rem;">Histórico de Solicitações</h3>
                        <p style="color: var(--muted-fg); font-size: 0.875rem;">
                            Todas as suas solicitações registradas no sistema
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nº Processo</th>
                                        <th>Serviço</th>
                                        <th>Data</th>
                                        <th>Estado</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $solicitacoes->data_seek(0);
                                    while($s = $solicitacoes->fetch_assoc()): 
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
                                        $tem_numero = !empty($s['numero_processo']);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if($tem_numero): ?>
                                                <span class="badge badge-outline" style="background: #e8f4fd; border-color: var(--primary);">
                                                    <?php echo htmlspecialchars($s['numero_processo']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-outline" style="background: #fff3cd; border: 1px dashed #ffc107;">
                                                    ⏳ Aguardando
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($s['servico']); ?></strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($s['data_solicitacao'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo formatarEstado($s['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="processo.php?id=<?php echo $s['id']; ?>" class="btn btn-outline btn-sm">
                                                Ver Detalhes
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Nenhuma solicitação encontrada -->
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                    <h3 style="margin-bottom: 1rem;">Nenhuma solicitação encontrada</h3>
                    <p style="color: var(--muted-fg); margin-bottom: 2rem;">
                        Você ainda não possui nenhum processo em andamento.
                    </p>
                    <a href="solicitar.php" class="btn btn-primary">
                        Fazer Primeira Solicitação
                    </a>
                </div>
            <?php endif; ?>
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
                © 2026 Portal do Moçamedense. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <script>
        function toggleUserDropdown() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        }

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

        document.querySelector('.mobile-toggle').addEventListener('click', function() {
            document.querySelector('.mobile-nav').classList.toggle('hidden');
        });

        // Auto-refresh a cada 30 segundos para verificar atualizações
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>