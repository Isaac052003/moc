<?php
session_start();

// ==========================
// VERIFICAR SE É FUNCIONÁRIO OU ADMIN
// ==========================
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

// Verificar se o tipo de usuário tem permissão
$tipos_permitidos = ['funcionario', 'admin_municipal', 'admin_sistema'];
if(!in_array($_SESSION['tipo'], $tipos_permitidos)){
    header("Location: index.php");
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
// FUNÇÃO PARA GERAR NÚMERO DO PROCESSO
// ==========================
function gerarNumeroProcesso($conexao, $usuario_id) {
    $ano = date('Y');
    $mes = date('m');
    
    // Buscar o próximo número sequencial
    $sql_seq = "SELECT COUNT(*) as total FROM solicitacoes WHERE YEAR(data_solicitacao) = ? AND numero_processo IS NOT NULL";
    $stmt_seq = $conexao->prepare($sql_seq);
    $stmt_seq->bind_param("i", $ano);
    $stmt_seq->execute();
    $seq = $stmt_seq->get_result()->fetch_assoc()['total'] + 1;
    
    return $ano . $mes . str_pad($usuario_id, 3, '0', STR_PAD_LEFT) . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ==========================
// ATUALIZAR ESTADO DA SOLICITAÇÃO (COM GERAÇÃO DE RUPE)
// ==========================
if(isset($_POST['atualizar_estado'])){
    $id = (int)$_POST['solicitacao_id'];
    $estado = $_POST['estado'];
    
    // Validar estado permitido
    $estados_permitidos = ['Pendente', 'Rececao', 'Area_tecnica', 'Vistoria', 'INOTU', 'Pagamento', 'Levantamento'];
    
    if(in_array($estado, $estados_permitidos)){
        
        // Buscar informações da solicitação antes de atualizar
        $sql_info = "SELECT usuario_id, numero_processo, estado as estado_anterior FROM solicitacoes WHERE id = ?";
        $stmt_info = $conexao->prepare($sql_info);
        $stmt_info->bind_param("i", $id);
        $stmt_info->execute();
        $info = $stmt_info->get_result()->fetch_assoc();
        
        if($info){
            
            // =========================================
            // SE O NOVO ESTADO FOR "RECEPCAO" E NÃO HOUVER NÚMERO DO PROCESSO, GERAR
            // =========================================
            $numero_processo = $info['numero_processo'];
            $numero_gerado = false;
            $rupe_gerado = false;
            
            if($estado == 'Rececao' && empty($numero_processo)){
                $numero_processo = gerarNumeroProcesso($conexao, $info['usuario_id']);
                $numero_gerado = true;
            }
            
            // Atualizar a solicitação (incluindo número_processo se foi gerado)
            if($numero_gerado){
                $sql = "UPDATE solicitacoes SET estado = ?, numero_processo = ?, data_atualizacao = NOW() WHERE id = ?";
                $stmt = $conexao->prepare($sql);
                $stmt->bind_param("ssi", $estado, $numero_processo, $id);
            } else {
                $sql = "UPDATE solicitacoes SET estado = ?, data_atualizacao = NOW() WHERE id = ?";
                $stmt = $conexao->prepare($sql);
                $stmt->bind_param("si", $estado, $id);
            }
            
            if($stmt->execute()){
                // Registrar na tramitação
                $sql_tramitacao = "INSERT INTO tramitacao 
                                   (solicitacao_id, funcionario_id, estado_anterior, estado_novo, comentario) 
                                   VALUES (?, ?, ?, ?, 'Atualizado via painel')";
                $stmt_tramitacao = $conexao->prepare($sql_tramitacao);
                $stmt_tramitacao->bind_param("iiss", $id, $_SESSION['usuario_id'], $info['estado_anterior'], $estado);
                $stmt_tramitacao->execute();
                
                // =========================================
                // SE O NOVO ESTADO FOR "PAGAMENTO", GERAR GUIA RUPE
                // =========================================
                if($estado == 'Pagamento' && $info['estado_anterior'] != 'Pagamento') {
                    
                    // Buscar dados completos do cidadão e solicitação
                    $sql_dados = "SELECT 
                                    u.nome, 
                                    u.bi, 
                                    u.email,
                                    s.descricao, 
                                    serv.nome as servico_nome, 
                                    serv.valor_padrao
                                  FROM solicitacoes s
                                  JOIN usuarios u ON s.usuario_id = u.id
                                  JOIN servicos serv ON s.servico_id = serv.id
                                  WHERE s.id = ?";
                    $stmt_dados = $conexao->prepare($sql_dados);
                    $stmt_dados->bind_param("i", $id);
                    $stmt_dados->execute();
                    $dados = $stmt_dados->get_result()->fetch_assoc();
                    
                    if($dados){
                        // Usar BI como NIF (ou campo específico se existir)
                        $nif = $dados['bi'];
                        $valor = $dados['valor_padrao'] ?? 5000.00; // Valor padrão se não definido
                        
                        // Gerar RUPE único
                        $rupe = gerarRUPE($conexao, $id, $valor);
                        
                        // Data limite (30 dias a partir de hoje)
                        $data_limite = date('Y-m-d', strtotime('+30 days'));
                        
                        // Inserir guia de pagamento
                        $sql_guia = "INSERT INTO guias_pagamento 
                                    (rupe, solicitacao_id, nif_contribuinte, nome_contribuinte, servico_nome, valor, data_limite)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt_guia = $conexao->prepare($sql_guia);
                        $stmt_guia->bind_param("sisssds", $rupe, $id, $nif, $dados['nome'], $dados['servico_nome'], $valor, $data_limite);
                        
                        if($stmt_guia->execute()){
                            $rupe_gerado = true;
                            
                            // Notificação especial com RUPE
                            $titulo = "💰 Guia de Pagamento Emitida";
                            $notificacao = "Foi emitida uma guia de pagamento para o seu processo #" . ($numero_processo ?? 'sem número') . ".\n\n";
                            $notificacao .= "📌 RUPE: " . $rupe . "\n";
                            $notificacao .= "💰 Valor: " . number_format($valor, 2, ',', '.') . " Kz\n";
                            $notificacao .= "📅 Data limite: " . date('d/m/Y', strtotime($data_limite)) . "\n\n";
                            $notificacao .= "Efetue o pagamento dentro do prazo para dar continuidade ao processo.";
                            
                            $link = "processo.php?id=" . $id;
                            
                            $sql_notif_rupe = "INSERT INTO notificacoes 
                                              (usuario_id, remetente_id, tipo, titulo, mensagem, link) 
                                              VALUES (?, ?, 'sucesso', ?, ?, ?)";
                            $stmt_notif_rupe = $conexao->prepare($sql_notif_rupe);
                            $stmt_notif_rupe->bind_param("iisss", $info['usuario_id'], $_SESSION['usuario_id'], $titulo, $notificacao, $link);
                            $stmt_notif_rupe->execute();
                        }
                    }
                }
                
                // =========================================
                // CRIAR NOTIFICAÇÃO PARA O CIDADÃO (PADRÃO)
                // =========================================
                if(!$rupe_gerado){
                    if($numero_gerado){
                        // Notificação especial para quando o número é gerado
                        $titulo = "✅ Processo Recebido - Número Gerado";
                        $notificacao = "O seu processo foi recebido pela administração!\n\n";
                        $notificacao .= "📌 Número do processo: " . $numero_processo . "\n\n";
                        $notificacao .= "Guarde este número para acompanhar o andamento do seu processo.";
                    } else {
                        $titulo = "Processo Atualizado";
                        $notificacao = "O seu processo #" . ($info['numero_processo'] ?? 'sem número') . " foi atualizado para: " . str_replace('_', ' ', $estado);
                    }
                    
                    $sql_notif = "INSERT INTO notificacoes 
                                  (usuario_id, remetente_id, tipo, titulo, mensagem, link) 
                                  VALUES (?, ?, 'info', ?, ?, ?)";
                    $link = "processo.php?id=" . $id;
                    $stmt_notif = $conexao->prepare($sql_notif);
                    $stmt_notif->bind_param("iisss", $info['usuario_id'], $_SESSION['usuario_id'], $titulo, $notificacao, $link);
                    $stmt_notif->execute();
                }
                
                // Mensagem de sucesso
                $estado_display = ($estado == 'Rececao') ? 'Recepção' : str_replace('_', ' ', $estado);
                $sucesso = "Solicitação #$id atualizada para: " . $estado_display;
                
                if($numero_gerado){
                    $sucesso .= " | Número do processo gerado: " . $numero_processo;
                }
                
                if($rupe_gerado){
                    $sucesso .= " | RUPE gerado: " . $rupe;
                }
            } else {
                $erro = "Erro ao atualizar solicitação";
            }
        } else {
            $erro = "Solicitação não encontrada";
        }
    } else {
        $erro = "Estado inválido";
    }
}

// ==========================
// BUSCAR SOLICITAÇÕES (COM PREPARED STATEMENT)
// ==========================
$sql = "SELECT 
            s.id,
            s.numero_processo,
            u.nome as usuario,
            u.email,
            serv.nome as servico,
            s.estado,
            s.data_solicitacao,
            s.descricao
        FROM solicitacoes s
        JOIN usuarios u ON s.usuario_id = u.id
        JOIN servicos serv ON s.servico_id = serv.id
        ORDER BY 
            CASE s.estado
                WHEN 'Pendente' THEN 1
                WHEN 'Rececao' THEN 2
                WHEN 'Area_tecnica' THEN 3
                WHEN 'Vistoria' THEN 4
                WHEN 'INOTU' THEN 5
                WHEN 'Pagamento' THEN 6
                WHEN 'Levantamento' THEN 7
                ELSE 8
            END,
            s.id DESC";

$resultado = $conexao->query($sql);

// ==========================
// CONTAGENS POR ESTADO (COM PREPARED STATEMENT)
// ==========================
$contagens = [];
$estados = ['Pendente', 'Rececao', 'Area_tecnica', 'Vistoria', 'INOTU', 'Pagamento', 'Levantamento'];

foreach($estados as $estado){
    $query = "SELECT COUNT(*) as total FROM solicitacoes WHERE estado = ?";
    $stmt = $conexao->prepare($query);
    $stmt->bind_param("s", $estado);
    $stmt->execute();
    $result = $stmt->get_result();
    $contagens[$estado] = $result->fetch_assoc()['total'];
}

// ==========================
// BUSCAR GUIAS DE PAGAMENTO RECENTES
// ==========================
$sql_guias_recentes = "SELECT g.*, s.numero_processo, u.nome as cidadao_nome 
                       FROM guias_pagamento g
                       JOIN solicitacoes s ON g.solicitacao_id = s.id
                       JOIN usuarios u ON s.usuario_id = u.id
                       WHERE g.estado = 'pendente'
                       ORDER BY g.data_emissao DESC
                       LIMIT 5";
$guias_recentes = $conexao->query($sql_guias_recentes);

// Função para formatar estado na exibição
function formatarEstado($estado) {
    if($estado == 'Rececao') {
        return 'Recepção';
    }
    return str_replace('_', ' ', $estado);
}

// ==========================
// BUSCAR NOTIFICAÇÕES NÃO LIDAS (para o menu)
// ==========================
$sql_notif = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_notif = $conexao->prepare($sql_notif);
$stmt_notif->bind_param("i", $_SESSION['usuario_id']);
$stmt_notif->execute();
$notificacoes_nao_lidas = $stmt_notif->get_result()->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Funcionário - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .badge-sem-numero {
            background: #fff3cd;
            color: #856404;
            border: 1px dashed #ffc107;
        }
        
        .numero-gerado-destaque {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; background: #d4edda; }
            100% { opacity: 1; }
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
        }
        
        /* Estilos para a seção de RUPE */
        .rupe-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #e8f4fd 0%, white 100%);
            border-radius: var(--radius);
            border: 2px solid var(--primary);
        }
        
        .rupe-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .rupe-header h3 {
            color: var(--primary);
            margin: 0;
        }
        
        .rupe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .rupe-card {
            background: white;
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 3px solid var(--primary);
        }
        
        .rupe-card .rupe-numero {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .rupe-card .info {
            font-size: 0.9rem;
            color: var(--muted-fg);
        }
        
        .rupe-card .valor {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .rupe-card .data-limite {
            color: #856404;
            background: #fff3cd;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .badge-rupe {
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="container flex items-center justify-between">
            <span>Administração Municipal de Moçâmedes - Província do Namibe</span>
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
                <a href="noticias.php">Notícias</a>
                <a href="funcionario.php" class="active">Painel</a>
            </nav>

            <div class="nav-auth">
                <!-- Menu do usuário logado -->
                <div class="user-menu">
                    <button class="btn btn-outline btn-sm notification-badge" onclick="toggleUserDropdown()">
                        <?php echo htmlspecialchars(explode(' ', $_SESSION['nome'])[0]); ?> ▼
                        <?php if($notificacoes_nao_lidas > 0): ?>
                            <span class="notification-count"><?php echo $notificacoes_nao_lidas; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="userDropdown" class="user-dropdown hidden">
                        <a href="perfil.php">👤 Meu Perfil</a>
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
            <a href="funcionario.php">Painel</a>
            <hr style="border: none; border-top: 1px solid var(--border); margin: 0.5rem 0;">
            <a href="perfil.php">👤 Meu Perfil</a>
            
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="container" style="padding: 2rem 1rem;">
            <!-- Título e breadcrumb -->
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Painel de Solicitações</h1>
                <p style="color: var(--muted-fg);">
                    <a href="index.php" style="color: var(--primary);">Início</a> / 
                    <span>Painel do Funcionário</span>
                </p>
            </div>

            <!-- Mensagens de sucesso/erro -->
            <?php if(isset($sucesso)): ?>
                <div class="badge badge-green" style="display: block; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius); background: #dcfce7; color: #166534; font-weight: normal;">
                    <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($erro)): ?>
                <div class="badge badge-red" style="display: block; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius); background: #fee2e2; color: #991b1b; font-weight: normal;">
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- Seção de GUIAS RUPE Pendentes -->
            <?php if($guias_recentes && $guias_recentes->num_rows > 0): ?>
            <div class="rupe-section">
                <div class="rupe-header">
                    <span style="font-size: 2rem;">💰</span>
                    <h3>Guias de Pagamento (RUPE) Pendentes</h3>
                </div>
                
                <div class="rupe-grid">
                    <?php while($guia = $guias_recentes->fetch_assoc()): ?>
                    <div class="rupe-card">
                        <div class="rupe-numero"><?php echo formatarRUPE($guia['rupe']); ?></div>
                        <div class="info"><strong>Processo:</strong> <?php echo htmlspecialchars($guia['numero_processo']); ?></div>
                        <div class="info"><strong>Cidadão:</strong> <?php echo htmlspecialchars($guia['cidadao_nome']); ?></div>
                        <div class="info"><strong>Serviço:</strong> <?php echo htmlspecialchars($guia['servico_nome']); ?></div>
                        <div class="valor"><?php echo formatarKwanzas($guia['valor']); ?></div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                            <span class="data-limite">⏰ <?php echo date('d/m/Y', strtotime($guia['data_limite'])); ?></span>
                            <a href="guia_pagamento.php?rupe=<?php echo $guia['rupe']; ?>" class="btn btn-outline btn-sm">
                                Ver Guia
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cards de Estatísticas -->
            <div class="grid grid-4" style="margin-bottom: 2rem;">
                <div class="card" style="text-align: center; padding: 1rem;">
                    <div class="stat-icon">📋</div>
                    <div class="stat-value"><?php echo array_sum($contagens); ?></div>
                    <div class="stat-label">Total de Solicitações</div>
                </div>
                
                <div class="card" style="text-align: center; padding: 1rem; border-left: 4px solid #f1c40f;">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value"><?php echo $contagens['Pendente']; ?></div>
                    <div class="stat-label">Pendentes</div>
                </div>
                
                <div class="card" style="text-align: center; padding: 1rem; border-left: 4px solid #3498db;">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-value"><?php echo $contagens['Rececao'] + $contagens['Area_tecnica'] + $contagens['Vistoria'] + $contagens['INOTU']; ?></div>
                    <div class="stat-label">Em Processamento</div>
                </div>
                
                <div class="card" style="text-align: center; padding: 1rem; border-left: 4px solid #27ae60;">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php echo $contagens['Levantamento']; ?></div>
                    <div class="stat-label">Concluídos</div>
                </div>
            </div>

            <!-- Informação sobre geração de números e RUPE -->
            <div style="margin-bottom: 1rem; padding: 0.5rem; background: #e8f4fd; border-radius: var(--radius); border-left: 4px solid var(--primary);">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 1.2rem;">ℹ️</span>
                    <span><strong>Nota:</strong> 
                        <span style="background: var(--primary); color: white; padding: 0.2rem 0.5rem; border-radius: 3px; margin: 0 0.3rem;">Recepção</span> → Gera número do processo |
                        <span style="background: var(--secondary); color: white; padding: 0.2rem 0.5rem; border-radius: 3px; margin: 0 0.3rem;">Pagamento</span> → Gera guia RUPE
                    </span>
                </div>
            </div>

            <!-- Filtros Rápidos -->
            <div class="filter-bar" style="margin-bottom: 1.5rem;">
                <button class="filter-btn active" onclick="filtrarTodos()">Todos</button>
                <button class="filter-btn" onclick="filtrarEstado('Pendente')">Pendentes</button>
                <button class="filter-btn" onclick="filtrarEstado('Rececao')">Recepção</button>
                <button class="filter-btn" onclick="filtrarEstado('Area_tecnica')">Área Técnica</button>
                <button class="filter-btn" onclick="filtrarEstado('Vistoria')">Vistoria</button>
                <button class="filter-btn" onclick="filtrarEstado('INOTU')">INOTU</button>
                <button class="filter-btn" onclick="filtrarEstado('Pagamento')">Pagamento</button>
                <button class="filter-btn" onclick="filtrarEstado('Levantamento')">Levantamento</button>
            </div>

            <!-- Tabela de Solicitações -->
            <div class="card">
                <div class="card-header">
                    <h3 style="font-size: 1.25rem;">Lista de Solicitações</h3>
                    <p style="color: var(--muted-fg); font-size: 0.875rem;">
                        Gerencie o fluxo das solicitações dos cidadãos
                    </p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="tabelaSolicitacoes">
                            <thead>
                                <tr>
                                    <th>Nº Processo</th>
                                    <th>Cidadão</th>
                                    <th>Email</th>
                                    <th>Serviço</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($s = $resultado->fetch_assoc()){ 
                                    // Determinar a cor do badge baseado no estado
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
                                    
                                    // Verificar se tem número do processo
                                    $tem_numero = !empty($s['numero_processo']);
                                ?>
                                <tr data-estado="<?php echo htmlspecialchars($s['estado']); ?>">
                                    <td>
                                        <?php if($tem_numero): ?>
                                            <span class="badge badge-outline" style="background: #e8f4fd; border-color: var(--primary);">
                                                <?php echo htmlspecialchars($s['numero_processo']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-outline badge-sem-numero">
                                                ⏳ Aguardando recepção
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($s['usuario']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo htmlspecialchars($s['servico']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($s['data_solicitacao'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo formatarEstado($s['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Formulário para atualização rápida -->
                                        <form method="POST" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.5rem;">
                                            <input type="hidden" name="solicitacao_id" value="<?php echo $s['id']; ?>">
                                            <input type="hidden" name="atualizar_estado" value="1">
                                            <select name="estado" class="form-control" style="width: auto; padding: 0.3rem; min-width: 120px;" onchange="this.form.submit()">
                                                <option value="Pendente" <?php if($s['estado']=='Pendente') echo 'selected'; ?>>Pendente</option>
                                                <option value="Rececao" <?php if($s['estado']=='Rececao') echo 'selected'; ?>>Recepção <?php echo !$tem_numero ? '(gerar nº)' : ''; ?></option>
                                                <option value="Area_tecnica" <?php if($s['estado']=='Area_tecnica') echo 'selected'; ?>>Área Técnica</option>
                                                <option value="Vistoria" <?php if($s['estado']=='Vistoria') echo 'selected'; ?>>Vistoria</option>
                                                <option value="INOTU" <?php if($s['estado']=='INOTU') echo 'selected'; ?>>INOTU</option>
                                                <option value="Pagamento" <?php if($s['estado']=='Pagamento') echo 'selected'; ?>>Pagamento <?php echo $s['estado'] != 'Pagamento' ? '(gerar RUPE)' : ''; ?></option>
                                                <option value="Levantamento" <?php if($s['estado']=='Levantamento') echo 'selected'; ?>>Levantamento</option>
                                            </select>
                                        </form>
                                        
                                        <!-- Link para detalhes completos -->
                                        <div style="text-align: center;">
                                            <a href="detalhes.php?id=<?php echo $s['id']; ?>" class="btn btn-outline btn-sm">
                                                Ver Detalhes
                                            </a>
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

        // Funções de filtro
        function filtrarTodos() {
            const linhas = document.querySelectorAll('#tabelaSolicitacoes tbody tr');
            linhas.forEach(linha => linha.style.display = '');
            
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function filtrarEstado(estado) {
            const linhas = document.querySelectorAll('#tabelaSolicitacoes tbody tr');
            linhas.forEach(linha => {
                if(linha.dataset.estado === estado) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
            
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        // Adicionar evento de clique aos botões de filtro
        document.querySelectorAll('.filter-btn').forEach((btn, index) => {
            btn.addEventListener('click', function(e) {
                if(index === 0) {
                    filtrarTodos();
                } else {
                    const estados = ['Pendente', 'Rececao', 'Area_tecnica', 'Vistoria', 'INOTU', 'Pagamento', 'Levantamento'];
                    filtrarEstado(estados[index - 1]);
                }
            });
        });
    </script>
</body>
</html>