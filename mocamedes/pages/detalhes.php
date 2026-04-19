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
// LOGOUT (Terminar Sessão)
// ==========================
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$nome_usuario = $_SESSION['nome'];
$tipo_usuario = $_SESSION['tipo'];

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
// FUNÇÃO PARA GERAR RUPE (se não existir no conexao.php)
// ==========================
if (!function_exists('gerarRUPE')) {
    function gerarRUPE($conexao, $solicitacao_id, $valor = 0) {
        $ano = date('y');
        $mes = date('m');
        $dia = date('d');
        
        $id_formatado = str_pad($solicitacao_id, 4, '0', STR_PAD_LEFT);
        $aleatorio = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        $base = $ano . $mes . $dia . $id_formatado . $aleatorio;
        $soma = 0;
        for($i = 0; $i < strlen($base); $i++) {
            $soma += intval($base[$i]);
        }
        $digito = $soma % 10;
        
        $rupe = sprintf("%02d%02d%02d%04d%04d%d", 
            $ano, $mes, $dia, $solicitacao_id, $aleatorio, $digito);
        
        // Verificar duplicidade
        $verificar = $conexao->prepare("SELECT id FROM guias_pagamento WHERE rupe = ?");
        $verificar->bind_param("s", $rupe);
        $verificar->execute();
        $verificar->store_result();
        
        if($verificar->num_rows > 0) {
            return gerarRUPE($conexao, $solicitacao_id, $valor);
        }
        
        return $rupe;
    }
}

if (!function_exists('formatarRUPE')) {
    function formatarRUPE($rupe) {
        return substr($rupe, 0, 4) . ' ' . 
               substr($rupe, 4, 4) . ' ' . 
               substr($rupe, 8, 4) . ' ' . 
               substr($rupe, 12, 4) . ' ' . 
               substr($rupe, 16, 1);
    }
}

// ==========================
// VERIFICAR PERMISSÕES (funcionário ou admin)
// ==========================
$tipos_permitidos = ['funcionario', 'admin_municipal', 'admin_sistema'];
if(!in_array($tipo_usuario, $tipos_permitidos)){
    // Se for cidadão, redireciona para a visualização pública
    if($tipo_usuario == 'cidadao' && isset($_GET['id'])){
        header("Location: processo.php?id=" . (int)$_GET['id']);
        exit();
    }
    header("Location: index.php");
    exit();
}

// ==========================
// OBTER ID DA SOLICITAÇÃO
// ==========================
$solicitacao_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($solicitacao_id == 0){
    header("Location: funcionario.php");
    exit();
}

// ==========================
// BUSCAR DETALHES DA SOLICITAÇÃO
// ==========================
$sql = "SELECT 
            s.id,
            s.numero_processo,
            s.descricao,
            s.estado,
            s.data_solicitacao,
            s.data_atualizacao,
            u.id as usuario_id,
            u.nome as usuario_nome,
            u.email as usuario_email,
            u.bi as usuario_bi,
            u.telefone as usuario_telefone,
            serv.nome as servico_nome,
            serv.id as servico_id
        FROM solicitacoes s
        JOIN usuarios u ON s.usuario_id = u.id
        JOIN servicos serv ON s.servico_id = serv.id
        WHERE s.id = ?";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $solicitacao_id);
$stmt->execute();
$resultado = $stmt->get_result();

if($resultado->num_rows == 0){
    header("Location: funcionario.php");
    exit();
}

$solicitacao = $resultado->fetch_assoc();

// ==========================
// ATUALIZAR ESTADO DA SOLICITAÇÃO (COM GERAÇÃO DE NÚMERO E RUPE)
// ==========================
if(isset($_POST['atualizar_estado'])){
    $novo_estado = $_POST['estado'];
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
    
    // Validar estado permitido
    $estados_permitidos = ['Pendente', 'Rececao', 'Area_tecnica', 'Vistoria', 'INOTU', 'Pagamento', 'Levantamento'];
    
    if(in_array($novo_estado, $estados_permitidos)){
        // Buscar estado anterior
        $estado_anterior = $solicitacao['estado'];
        $numero_processo = $solicitacao['numero_processo'];
        $numero_gerado = false;
        $rupe_gerado = false;
        $rupe = '';
        $valor = 0;
        $data_limite = '';
        
        // =========================================
        // SE O NOVO ESTADO FOR "RECEPCAO" E NÃO HOUVER NÚMERO DO PROCESSO, GERAR
        // =========================================
        if($novo_estado == 'Rececao' && empty($numero_processo)){
            $numero_processo = gerarNumeroProcesso($conexao, $solicitacao['usuario_id']);
            $numero_gerado = true;
        }
        
        // =========================================
        // SE O NOVO ESTADO FOR "PAGAMENTO", GERAR GUIA RUPE
        // =========================================
        if($novo_estado == 'Pagamento' && $estado_anterior != 'Pagamento') {
            
            // Buscar dados completos do cidadão e solicitação
            $sql_dados = "SELECT 
                            u.nome, 
                            u.bi, 
                            u.email,
                            s.descricao, 
                            serv.nome as servico_nome, 
                            COALESCE(serv.valor_padrao, 5000) as valor_padrao
                          FROM solicitacoes s
                          JOIN usuarios u ON s.usuario_id = u.id
                          JOIN servicos serv ON s.servico_id = serv.id
                          WHERE s.id = ?";
            $stmt_dados = $conexao->prepare($sql_dados);
            $stmt_dados->bind_param("i", $solicitacao_id);
            $stmt_dados->execute();
            $dados = $stmt_dados->get_result()->fetch_assoc();
            
            if($dados){
                // Usar BI como NIF
                $nif = $dados['bi'];
                $valor = $dados['valor_padrao'];
                
                // Gerar RUPE único
                $rupe = gerarRUPE($conexao, $solicitacao_id, $valor);
                
                // Data limite (30 dias a partir de hoje)
                $data_limite = date('Y-m-d', strtotime('+30 days'));
                
                // Inserir guia de pagamento
                $sql_guia = "INSERT INTO guias_pagamento 
                            (rupe, solicitacao_id, nif_contribuinte, nome_contribuinte, servico_nome, valor, data_limite)
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_guia = $conexao->prepare($sql_guia);
                $stmt_guia->bind_param("sisssds", $rupe, $solicitacao_id, $nif, $dados['nome'], $dados['servico_nome'], $valor, $data_limite);
                
                if($stmt_guia->execute()){
                    $rupe_gerado = true;
                }
            }
        }
        
        // Atualizar solicitação (incluindo número_processo se foi gerado)
        if($numero_gerado){
            $update = "UPDATE solicitacoes SET estado = ?, numero_processo = ?, data_atualizacao = NOW() WHERE id = ?";
            $stmt_update = $conexao->prepare($update);
            $stmt_update->bind_param("ssi", $novo_estado, $numero_processo, $solicitacao_id);
        } else {
            $update = "UPDATE solicitacoes SET estado = ?, data_atualizacao = NOW() WHERE id = ?";
            $stmt_update = $conexao->prepare($update);
            $stmt_update->bind_param("si", $novo_estado, $solicitacao_id);
        }
        
        if($stmt_update->execute()){
            // Registrar na tramitação
            $insert = "INSERT INTO tramitacao (solicitacao_id, funcionario_id, estado_anterior, estado_novo, comentario) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conexao->prepare($insert);
            $stmt_insert->bind_param("iisss", $solicitacao_id, $usuario_id, $estado_anterior, $novo_estado, $comentario);
            $stmt_insert->execute();
            
            // =========================================
            // CRIAR NOTIFICAÇÃO PARA O CIDADÃO
            // =========================================
            if($rupe_gerado){
                $titulo = "💰 Guia de Pagamento Emitida";
                $notificacao = "Foi emitida uma guia de pagamento para o seu processo #" . ($numero_processo ?? 'sem número') . ".\n\n";
                $notificacao .= "📌 RUPE: " . $rupe . "\n";
                $notificacao .= "💰 Valor: " . number_format($valor, 2, ',', '.') . " Kz\n";
                $notificacao .= "📅 Data limite: " . date('d/m/Y', strtotime($data_limite)) . "\n\n";
                $notificacao .= "Efetue o pagamento dentro do prazo para dar continuidade ao processo.";
                $tipo_notif = 'sucesso';
            } elseif($numero_gerado){
                $titulo = "✅ Processo Recebido - Número Gerado";
                $notificacao = "O seu processo foi recebido pela administração!\n\n";
                $notificacao .= "📌 Número do processo: " . $numero_processo . "\n\n";
                $notificacao .= "Guarde este número para acompanhar o andamento do seu processo.";
                $tipo_notif = 'info';
            } else {
                $titulo = "Processo Atualizado";
                $notificacao = "O seu processo #" . ($solicitacao['numero_processo'] ?? 'sem número') . " foi atualizado para: " . str_replace('_', ' ', $novo_estado);
                $tipo_notif = 'info';
            }
            
            $sql_notif = "INSERT INTO notificacoes (usuario_id, remetente_id, tipo, titulo, mensagem, link) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $link = "processo.php?id=" . $solicitacao_id;
            $stmt_notif = $conexao->prepare($sql_notif);
            $stmt_notif->bind_param("iissss", $solicitacao['usuario_id'], $usuario_id, $tipo_notif, $titulo, $notificacao, $link);
            $stmt_notif->execute();
            
            // Redirecionar para evitar reenvio
            $redirect = "detalhes.php?id=" . $solicitacao_id . "&sucesso=1";
            if($numero_gerado){
                $redirect .= "&numero=" . $numero_processo;
            }
            if($rupe_gerado){
                $redirect .= "&rupe=" . $rupe;
            }
            header("Location: " . $redirect);
            exit();
        } else {
            $erro = "Erro ao atualizar estado.";
        }
    } else {
        $erro = "Estado inválido.";
    }
}

// ==========================
// SOLICITAR CORREÇÃO
// ==========================
if(isset($_POST['solicitar_correcao'])){
    $mensagem_correcao = trim($_POST['mensagem_correcao']);
    
    if(!empty($mensagem_correcao)){
        // Inserir correção com funcionario_id
        $insert = "INSERT INTO correcoes (solicitacao_id, funcionario_id, mensagem) VALUES (?, ?, ?)";
        $stmt_insert = $conexao->prepare($insert);
        $stmt_insert->bind_param("iis", $solicitacao_id, $usuario_id, $mensagem_correcao);
        
        if($stmt_insert->execute()){
            // Atualizar estado para Pendente se necessário (mas não se já estiver concluído)
            if($solicitacao['estado'] != 'Pendente' && $solicitacao['estado'] != 'Levantamento'){
                $update = "UPDATE solicitacoes SET estado = 'Pendente', data_atualizacao = NOW() WHERE id = ?";
                $stmt_update = $conexao->prepare($update);
                $stmt_update->bind_param("i", $solicitacao_id);
                $stmt_update->execute();
                
                // Registrar a mudança na tramitação
                $insert_tramite = "INSERT INTO tramitacao (solicitacao_id, funcionario_id, estado_anterior, estado_novo, comentario) 
                                  VALUES (?, ?, ?, 'Pendente', 'Correção solicitada')";
                $stmt_tramite = $conexao->prepare($insert_tramite);
                $stmt_tramite->bind_param("iis", $solicitacao_id, $usuario_id, $solicitacao['estado']);
                $stmt_tramite->execute();
            }
            
            $titulo_notificacao = "Correção Necessária - Processo #" . $solicitacao['numero_processo'];
            
            // Mensagem completa: inclui a observação do funcionário
            $mensagem_completa = "Foi solicitada uma correção para o seu processo #" . $solicitacao['numero_processo'] . ".\n\n";
            $mensagem_completa .= "Mensagem do funcionário:\n" . $mensagem_correcao . "\n\n";
            $mensagem_completa .= "Por favor, acesse o processo para fazer as correções necessárias.";
            
            // Link para o processo
            $link = "processo.php?id=" . $solicitacao_id;
            
            // Inserir notificação
            $sql_notif = "INSERT INTO notificacoes 
                          (usuario_id, remetente_id, tipo, titulo, mensagem, link) 
                          VALUES (?, ?, 'aviso', ?, ?, ?)";
            $stmt_notif = $conexao->prepare($sql_notif);
            $stmt_notif->bind_param("iisss", $solicitacao['usuario_id'], $usuario_id, $titulo_notificacao, $mensagem_completa, $link);
            $stmt_notif->execute();
            
            $sucesso = "Correção solicitada com sucesso! O cidadão foi notificado.";
            
            // Recarregar a página para mostrar a nova correção
            header("Location: detalhes.php?id=" . $solicitacao_id . "&sucesso=2");
            exit();
        } else {
            $erro = "Erro ao solicitar correção.";
        }
    } else {
        $erro = "Por favor, escreva a mensagem de correção.";
    }
}

// ==========================
// BUSCAR DOCUMENTOS
// ==========================
$sql_docs = "SELECT id, tipo_documento, arquivo, data_upload FROM documentos WHERE solicitacao_id = ?";
$stmt_docs = $conexao->prepare($sql_docs);
$stmt_docs->bind_param("i", $solicitacao_id);
$stmt_docs->execute();
$documentos = $stmt_docs->get_result();

// ==========================
// BUSCAR HISTÓRICO DE TRAMITAÇÃO
// ==========================
$sql_historico = "SELECT 
                    t.*,
                    u.nome as funcionario_nome
                  FROM tramitacao t
                  JOIN usuarios u ON t.funcionario_id = u.id
                  WHERE t.solicitacao_id = ?
                  ORDER BY t.data_movimento DESC";
$stmt_historico = $conexao->prepare($sql_historico);
$stmt_historico->bind_param("i", $solicitacao_id);
$stmt_historico->execute();
$historico = $stmt_historico->get_result();

// ==========================
// BUSCAR CORREÇÕES
// ==========================
$sql_correcoes = "SELECT c.*, u.nome as funcionario_nome 
                  FROM correcoes c
                  LEFT JOIN usuarios u ON c.funcionario_id = u.id
                  WHERE c.solicitacao_id = ? 
                  ORDER BY c.data_pedido DESC";
$stmt_correcoes = $conexao->prepare($sql_correcoes);
$stmt_correcoes->bind_param("i", $solicitacao_id);
$stmt_correcoes->execute();
$correcoes = $stmt_correcoes->get_result();

// ==========================
// MENSAGENS DE SUCESSO VIA GET
// ==========================
if(isset($_GET['sucesso'])){
    if($_GET['sucesso'] == 1){
        if(isset($_GET['numero']) && isset($_GET['rupe'])){
            $sucesso = "✅ Estado atualizado com sucesso! Número do processo: <strong>" . htmlspecialchars($_GET['numero']) . "</strong> | RUPE gerado: <strong>" . htmlspecialchars($_GET['rupe']) . "</strong>";
        } elseif(isset($_GET['numero'])){
            $sucesso = "✅ Estado atualizado com sucesso! Número do processo gerado: <strong>" . htmlspecialchars($_GET['numero']) . "</strong>";
        } elseif(isset($_GET['rupe'])){
            $sucesso = "✅ Estado atualizado com sucesso! RUPE gerado: <strong>" . htmlspecialchars($_GET['rupe']) . "</strong>";
        } else {
            $sucesso = "✅ Estado atualizado com sucesso!";
        }
    } elseif($_GET['sucesso'] == 2){
        $sucesso = "✅ Correção solicitada com sucesso! O cidadão foi notificado.";
    }
}

// ==========================
// BUSCAR NOTIFICAÇÕES NÃO LIDAS (para o menu)
// ==========================
$sql_notif = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_notif = $conexao->prepare($sql_notif);
$stmt_notif->bind_param("i", $usuario_id);
$stmt_notif->execute();
$notificacoes_nao_lidas = $stmt_notif->get_result()->fetch_assoc()['total'];

// Função para obter cor do badge do estado
function getEstadoBadge($estado) {
    switch($estado){
        case 'Pendente': return 'badge-yellow';
        case 'Rececao': return 'badge-blue';
        case 'Area_tecnica': return 'badge-purple';
        case 'Vistoria': return 'badge-orange';
        case 'INOTU': return 'badge-primary';
        case 'Pagamento': return 'badge-green';
        case 'Levantamento': return 'badge-secondary';
        default: return 'badge-gray';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Solicitação #<?php echo htmlspecialchars($solicitacao['numero_processo'] ?? 'Aguardando Nº'); ?> - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .document-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 0.5rem;
            transition: all 0.2s;
        }
        
        .document-card:hover {
            background: var(--muted);
            border-color: var(--primary);
        }
        
        .document-icon {
            font-size: 2rem;
            margin-right: 1rem;
            color: var(--primary);
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        
        .document-meta {
            font-size: 0.8rem;
            color: var(--muted-fg);
        }
        
        .btn-pdf {
            background: var(--destructive);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: opacity 0.2s;
        }
        
        .btn-pdf:hover {
            opacity: 0.9;
            color: white;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            padding: 1rem;
            background: var(--muted);
            border-radius: var(--radius);
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--muted-fg);
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .historico-item {
            padding: 1rem;
            border-left: 3px solid var(--primary);
            background: var(--muted);
            border-radius: 0 var(--radius) var(--radius) 0;
            margin-bottom: 0.5rem;
        }
        
        .correcao-item {
            padding: 1rem;
            border-left: 3px solid var(--destructive);
            background: #fee2e2;
            border-radius: 0 var(--radius) var(--radius) 0;
            margin-bottom: 0.5rem;
        }
        
        .correcao-resolvida {
            border-left: 3px solid var(--secondary);
            background: #dcfce7;
        }
        
        .correcao-mensagem {
            font-style: italic;
            margin: 0.5rem 0;
        }
        
        .tab-container {
            margin-top: 2rem;
        }
        
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--border);
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--muted-fg);
            position: relative;
            transition: color 0.2s;
        }
        
        .tab-btn:hover {
            color: var(--primary);
        }
        
        .tab-btn.active {
            color: var(--primary);
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .badge-pendente {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .info-box {
            background: var(--muted);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        
        .badge-sem-numero {
            background: #fff3cd;
            color: #856404;
            border: 1px dashed #ffc107;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
        }
        
        .numero-gerado-destaque {
            background: #d4edda;
            color: #155724;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            border-left: 4px solid var(--secondary);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.9; background: #c3e6cb; }
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
                <a href="index.php">Início</a>
                <a href="servicos.php">Serviços</a>
                <a href="reportar.php">Reportar Problema</a>
                <a href="sugestoes.php">Sugestões</a>
                <a href="noticias.php">Notícias</a>
                <a href="funcionario.php">Painel</a>
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
                        <a href="funcionario.php">📋 Painel</a>
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
            <a href="funcionario.php">Painel</a>
            <hr style="border: none; border-top: 1px solid var(--border); margin: 0.5rem 0;">
            <a href="perfil.php">👤 Meu Perfil</a>
            <a href="funcionario.php">📋 Painel</a>
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="container" style="padding: 2rem 1rem;">
            <!-- Breadcrumb -->
            <div style="margin-bottom: 2rem;">
                <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Detalhes da Solicitação</h1>
                        <p style="color: var(--muted-fg);">
                            <a href="funcionario.php" style="color: var(--primary);">Painel</a> / 
                            <span>Processo #<?php echo htmlspecialchars($solicitacao['numero_processo'] ?? 'Aguardando recepção'); ?></span>
                        </p>
                    </div>
                    <div class="action-buttons">
                        <a href="funcionario.php" class="btn btn-outline">← Voltar</a>
                    </div>
                </div>
            </div>

            <!-- Mensagens de sucesso/erro -->
            <?php if(isset($sucesso)): ?>
                <div class="badge badge-green" style="display: block; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius); background: #dcfce7; color: #166534; font-weight: normal;">
                    <?php echo $sucesso; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($erro)): ?>
                <div class="badge badge-red" style="display: block; padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius); background: #fee2e2; color: #991b1b; font-weight: normal;">
                    <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- Informação sobre geração de número -->
            <?php if(empty($solicitacao['numero_processo'])): ?>
            <div class="info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.5rem;">ℹ️</span>
                    <div>
                        <strong>Processo aguardando recepção</strong>
                        <p style="margin-top: 0.3rem;">O número do processo será gerado automaticamente quando o estado for alterado para <strong>Recepção</strong>.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status atual -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-body">
                    <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <span class="badge <?php echo getEstadoBadge($solicitacao['estado']); ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                Estado: <?php echo str_replace('_', ' ', htmlspecialchars($solicitacao['estado'])); ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-outline">📅 Criado: <?php echo date('d/m/Y H:i', strtotime($solicitacao['data_solicitacao'])); ?></span>
                            <?php if($solicitacao['data_atualizacao'] != $solicitacao['data_solicitacao']): ?>
                                <span class="badge badge-outline">🔄 Atualizado: <?php echo date('d/m/Y H:i', strtotime($solicitacao['data_atualizacao'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações do Cidadão e Processo -->
            <div class="grid grid-2" style="gap: 2rem; margin-bottom: 2rem;">
                <!-- Dados do Cidadão -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="font-size: 1.25rem;">👤 Dados do Cidadão</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-label">Nome</div>
                            <div class="info-value"><?php echo htmlspecialchars($solicitacao['usuario_nome']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($solicitacao['usuario_email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">BI</div>
                            <div class="info-value"><?php echo htmlspecialchars($solicitacao['usuario_bi']); ?></div>
                        </div>
                        <?php if(!empty($solicitacao['usuario_telefone'])): ?>
                        <div class="info-item">
                            <div class="info-label">Telefone</div>
                            <div class="info-value"><?php echo htmlspecialchars($solicitacao['usuario_telefone']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dados do Processo -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="font-size: 1.25rem;">📋 Dados do Processo</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-label">Nº do Processo</div>
                            <div class="info-value">
                                <?php if(!empty($solicitacao['numero_processo'])): ?>
                                    <span style="font-family: monospace; font-size: 1.2rem;"><?php echo htmlspecialchars($solicitacao['numero_processo']); ?></span>
                                <?php else: ?>
                                    <span class="badge-sem-numero">⏳ A ser gerado na recepção</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Serviço</div>
                            <div class="info-value"><?php echo htmlspecialchars($solicitacao['servico_nome']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Data da Solicitação</div>
                            <div class="info-value"><?php echo date('d/m/Y', strtotime($solicitacao['data_solicitacao'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Descrição do Pedido -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h3 style="font-size: 1.25rem;">📝 Descrição do Pedido</h3>
                </div>
                <div class="card-body">
                    <p style="line-height: 1.6; white-space: pre-line; background: var(--muted); padding: 1rem; border-radius: var(--radius);">
                        <?php echo nl2br(htmlspecialchars($solicitacao['descricao'])); ?>
                    </p>
                </div>
            </div>

            <!-- Tabs para Documentos, Correções e Histórico -->
            <div class="card">
                <div class="card-body">
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-btn active" onclick="openTab(event, 'documentos')">📄 Documentos (<?php echo $documentos->num_rows; ?>)</button>
                            <button class="tab-btn" onclick="openTab(event, 'correcoes')">⚠️ Correções (<?php echo $correcoes->num_rows; ?>)</button>
                            <button class="tab-btn" onclick="openTab(event, 'historico')">📊 Histórico (<?php echo $historico->num_rows; ?>)</button>
                            <button class="tab-btn" onclick="openTab(event, 'acoes')">⚡ Ações</button>
                        </div>

                        <!-- Tab Documentos -->
                        <div id="documentos" class="tab-pane active">
                            <?php if($documentos->num_rows > 0): ?>
                                <?php while($doc = $documentos->fetch_assoc()): ?>
                                    <div class="document-card">
                                        <div style="display: flex; align-items: center; flex: 1;">
                                            <span class="document-icon">📄</span>
                                            <div class="document-info">
                                                <div class="document-name"><?php echo htmlspecialchars($doc['tipo_documento']); ?></div>
                                                <div class="document-meta">
                                                    Enviado em: <?php echo date('d/m/Y H:i', strtotime($doc['data_upload'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($doc['arquivo']); ?>" target="_blank" class="btn-pdf">
                                            📥 Visualizar PDF
                                        </a>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="info-box" style="text-align: center;">
                                    <p style="color: var(--muted-fg);">Nenhum documento anexado a esta solicitação.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab Correções -->
                        <div id="correcoes" class="tab-pane">
                            <h4 style="margin-bottom: 1rem;">Histórico de Correções</h4>
                            
                            <?php if($correcoes->num_rows > 0): ?>
                                <?php while($cor = $correcoes->fetch_assoc()): 
                                    $classe_correcao = $cor['resolvido'] ? 'correcao-resolvida' : '';
                                ?>
                                    <div class="correcao-item <?php echo $classe_correcao; ?>">
                                        <div class="flex items-center justify-between" style="margin-bottom: 0.5rem;">
                                            <span class="badge <?php echo $cor['resolvido'] ? 'badge-green' : 'badge-red'; ?>">
                                                <?php echo $cor['resolvido'] ? 'Resolvido' : 'Pendente'; ?>
                                            </span>
                                            <small><?php echo date('d/m/Y H:i', strtotime($cor['data_pedido'])); ?></small>
                                        </div>
                                        <div class="correcao-mensagem">
                                            <?php echo nl2br(htmlspecialchars($cor['mensagem'])); ?>
                                        </div>
                                        <small style="color: var(--muted-fg);">
                                            Solicitado por: <?php echo htmlspecialchars($cor['funcionario_nome'] ?? 'Sistema'); ?>
                                        </small>
                                        <?php if($cor['resolvido'] && $cor['data_resolucao']): ?>
                                            <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--secondary);">
                                                ✅ Resolvido em: <?php echo date('d/m/Y H:i', strtotime($cor['data_resolucao'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="info-box" style="text-align: center;">
                                    <p style="color: var(--muted-fg);">Nenhuma correção registrada para esta solicitação.</p>
                                </div>
                            <?php endif; ?>

                            <!-- Formulário para solicitar nova correção -->
                            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                                <h5 style="margin-bottom: 1rem;">Solicitar Nova Correção</h5>
                                <form method="POST">
                                    <div class="form-group">
                                        <textarea name="mensagem_correcao" class="form-control" rows="3" placeholder="Descreva o que precisa ser corrigido..." required></textarea>
                                    </div>
                                    <button type="submit" name="solicitar_correcao" class="btn btn-primary">
                                        Solicitar Correção
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Tab Histórico -->
                        <div id="historico" class="tab-pane">
                            <?php if($historico->num_rows > 0): ?>
                                <?php while($his = $historico->fetch_assoc()): ?>
                                    <div class="historico-item">
                                        <div class="flex items-center justify-between" style="margin-bottom: 0.5rem;">
                                            <span class="badge <?php echo getEstadoBadge($his['estado_novo']); ?>">
                                                <?php echo str_replace('_', ' ', htmlspecialchars($his['estado_novo'])); ?>
                                            </span>
                                            <small><?php echo date('d/m/Y H:i', strtotime($his['data_movimento'])); ?></small>
                                        </div>
                                        <?php if($his['estado_anterior']): ?>
                                            <div style="font-size: 0.8rem; color: var(--muted-fg); margin-bottom: 0.5rem;">
                                                De: <?php echo str_replace('_', ' ', htmlspecialchars($his['estado_anterior'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(!empty($his['comentario'])): ?>
                                            <p style="margin: 0.5rem 0; background: white; padding: 0.5rem; border-radius: var(--radius);">
                                                <?php echo nl2br(htmlspecialchars($his['comentario'])); ?>
                                            </p>
                                        <?php endif; ?>
                                        <small style="color: var(--muted-fg);">
                                            Por: <?php echo htmlspecialchars($his['funcionario_nome']); ?>
                                        </small>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="info-box" style="text-align: center;">
                                    <p style="color: var(--muted-fg);">Nenhum histórico de movimentação.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab Ações -->
                        <div id="acoes" class="tab-pane">
                            <h4 style="margin-bottom: 1rem;">Atualizar Estado do Processo</h4>
                            
                            <form method="POST" class="card" style="padding: 1rem; background: var(--muted);">
                                <div class="form-group">
                                    <label for="estado">Novo Estado</label>
                                    <select name="estado" id="estado" class="form-control" required>
                                        <option value="Pendente" <?php if($solicitacao['estado'] == 'Pendente') echo 'selected'; ?>>⏳ Pendente</option>
                                        <option value="Rececao" <?php if($solicitacao['estado'] == 'Rececao') echo 'selected'; ?>>📥 Recepção <?php echo empty($solicitacao['numero_processo']) ? '(gerar nº)' : ''; ?></option>
                                        <option value="Area_tecnica" <?php if($solicitacao['estado'] == 'Area_tecnica') echo 'selected'; ?>>🔧 Área Técnica</option>
                                        <option value="Vistoria" <?php if($solicitacao['estado'] == 'Vistoria') echo 'selected'; ?>>🔍 Vistoria</option>
                                        <option value="INOTU" <?php if($solicitacao['estado'] == 'INOTU') echo 'selected'; ?>>📐 INOTU</option>
                                        <option value="Pagamento" <?php if($solicitacao['estado'] == 'Pagamento') echo 'selected'; ?>>💰 Pagamento <?php echo $solicitacao['estado'] != 'Pagamento' ? '(gerar RUPE)' : ''; ?></option>
                                        <option value="Levantamento" <?php if($solicitacao['estado'] == 'Levantamento') echo 'selected'; ?>>✅ Levantamento</option>
                                    </select>
                                    <?php if(empty($solicitacao['numero_processo'])): ?>
                                        <small style="color: var(--primary); display: block; margin-top: 0.3rem;">
                                            ⚡ Ao selecionar "Recepção", o número do processo será gerado automaticamente.
                                        </small>
                                    <?php endif; ?>
                                    <?php if($solicitacao['estado'] != 'Pagamento'): ?>
                                        <small style="color: var(--secondary); display: block; margin-top: 0.3rem;">
                                            💰 Ao selecionar "Pagamento", uma guia RUPE será gerada automaticamente.
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label for="comentario">Comentário (opcional)</label>
                                    <textarea name="comentario" id="comentario" class="form-control" rows="3" placeholder="Adicione um comentário sobre esta atualização..."></textarea>
                                </div>

                                <button type="submit" name="atualizar_estado" class="btn btn-primary btn-block">
                                    Atualizar Estado
                                </button>
                            </form>

                            <!-- Ações Rápidas -->
                            <div style="margin-top: 2rem;">
                                <h5 style="margin-bottom: 1rem;">Ações Rápidas</h5>
                                <div class="action-buttons">
                                    <button onclick="setEstado('Vistoria')" class="btn btn-outline">
                                        🔍 Marcar para Vistoria
                                    </button>
                                    <button onclick="setEstado('Pagamento')" class="btn btn-outline">
                                        💰 Marcar para Pagamento
                                    </button>
                                    <button onclick="setEstado('Levantamento')" class="btn btn-outline">
                                        ✅ Marcar como Concluído
                                    </button>
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: var(--radius); border-left: 4px solid #ffc107;">
                                <strong>⚠️ Importante:</strong>
                                <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                                    Ao solicitar uma correção, o processo volta para "Pendente" automaticamente.
                                </p>
                            </div>
                        </div>
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

        // Função para abrir tabs
        function openTab(event, tabName) {
            var i, tabPanes, tabBtns;
            
            tabPanes = document.getElementsByClassName("tab-pane");
            for (i = 0; i < tabPanes.length; i++) {
                tabPanes[i].classList.remove('active');
            }
            
            tabBtns = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tabBtns.length; i++) {
                tabBtns[i].classList.remove('active');
            }
            
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Função para ações rápidas
        function setEstado(estado) {
            document.getElementById('estado').value = estado;
            document.getElementById('estado').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Auto-refresh a cada 60 segundos para verificar atualizações
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>