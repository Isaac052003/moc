<?php
session_start();

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
// MARCAR NOTIFICAÇÃO COMO LIDA
// ==========================
if(isset($_GET['marcar_lida'])){
    $notificacao_id = (int)$_GET['marcar_lida'];
    
    $sql = "UPDATE notificacoes SET lida = TRUE WHERE id = ? AND usuario_id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $notificacao_id, $usuario_id);
    $stmt->execute();
    
    header("Location: notificacoes.php");
    exit();
}

// ==========================
// MARCAR TODAS COMO LIDAS
// ==========================
if(isset($_GET['marcar_todas'])){
    $sql = "UPDATE notificacoes SET lida = TRUE WHERE usuario_id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    
    header("Location: notificacoes.php");
    exit();
}

// ==========================
// ELIMINAR NOTIFICAÇÃO
// ==========================
if(isset($_GET['eliminar'])){
    $notificacao_id = (int)$_GET['eliminar'];
    
    $sql = "DELETE FROM notificacoes WHERE id = ? AND usuario_id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $notificacao_id, $usuario_id);
    $stmt->execute();
    
    header("Location: notificacoes.php");
    exit();
}

// ==========================
// ELIMINAR TODAS AS NOTIFICAÇÕES
// ==========================
if(isset($_GET['eliminar_todas'])){
    $sql = "DELETE FROM notificacoes WHERE usuario_id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    
    header("Location: notificacoes.php");
    exit();
}

// ==========================
// FUNÇÃO PARA FORMATAR MENSAGEM DE NOTIFICAÇÃO
// ==========================
function formatarMensagemNotificacao($mensagem) {
    // Destacar números de processo na mensagem
    $pattern = '/#([A-Z0-9]+)/';
    $mensagem = preg_replace($pattern, '<strong style="color: var(--primary);">#$1</strong>', $mensagem);
    
    // Destacar palavras-chave
    $palavras_chave = [
        'Receção' => '<span style="color: var(--primary); font-weight: 600;">Receção</span>',
        'Pendente' => '<span style="color: #f1c40f; font-weight: 600;">Pendente</span>',
        'aprovado' => '<span style="color: var(--secondary); font-weight: 600;">aprovado</span>',
        'correção' => '<span style="color: var(--destructive); font-weight: 600;">correção</span>'
    ];
    
    foreach($palavras_chave as $palavra => $substituicao) {
        $mensagem = str_replace($palavra, $substituicao, $mensagem);
    }
    
    return $mensagem;
}

// ==========================
// BUSCAR NOTIFICAÇÕES DO USUÁRIO COM MAIS DETALHES
// ==========================
$sql = "SELECT 
            n.*,
            u.nome as remetente_nome,
            u.tipo as remetente_tipo,
            s.numero_processo,
            s.estado as processo_estado
        FROM notificacoes n
        LEFT JOIN usuarios u ON n.remetente_id = u.id
        LEFT JOIN solicitacoes s ON s.id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(n.link, '=', -1), '&', 1) AS UNSIGNED)
        WHERE n.usuario_id = ?
        ORDER BY n.data_envio DESC";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$notificacoes = $stmt->get_result();

// ==========================
// CONTAR NÃO LIDAS
// ==========================
$sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_nao_lidas = $conexao->prepare($sql_nao_lidas);
$stmt_nao_lidas->bind_param("i", $usuario_id);
$stmt_nao_lidas->execute();
$nao_lidas = $stmt_nao_lidas->get_result()->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .notificacao-card {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            position: relative;
        }
        
        .notificacao-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .notificacao-card.nao-lida {
            background: #f0f7ff;
            border-left: 4px solid var(--primary);
        }
        
        .notificacao-card.lida {
            background: white;
            opacity: 0.8;
        }
        
        .notificacao-icone {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .icone-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .icone-sucesso {
            background: #dcfce7;
            color: #166534;
        }
        
        .icone-aviso {
            background: #fed7aa;
            color: #9a3412;
        }
        
        .icone-erro {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .notificacao-conteudo {
            flex: 1;
        }
        
        .notificacao-titulo {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .notificacao-mensagem {
            color: var(--fg);
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }
        
        .notificacao-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--muted-fg);
            flex-wrap: wrap;
        }
        
        .notificacao-acoes {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
        }
        
        .badge-tipo {
            padding: 0.2rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-sucesso {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-aviso {
            background: #fed7aa;
            color: #9a3412;
        }
        
        .badge-erro {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-acao {
            padding: 0.4rem;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-lida {
            background: var(--muted);
            color: var(--muted-fg);
        }
        
        .btn-lida:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-eliminar {
            background: var(--muted);
            color: var(--destructive);
        }
        
        .btn-eliminar:hover {
            background: var(--destructive);
            color: white;
        }
        
        .btn-link {
            background: var(--muted);
            color: var(--primary);
        }
        
        .btn-link:hover {
            background: var(--primary);
            color: white;
        }
        
        .notificacao-vazia {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--muted);
            border-radius: var(--radius);
        }
        
        .notificacao-vazia .icone {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--muted-fg);
        }
        
        .acoes-barra {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filtros {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filtro-btn {
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            border: 1px solid var(--border);
            background: white;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .filtro-btn:hover {
            background: var(--muted);
        }
        
        .filtro-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
        
        .contador {
            background: var(--destructive);
            color: white;
            border-radius: 9999px;
            padding: 0.2rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .numero-processo-destaque {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .info-adicional {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: var(--muted);
            border-radius: var(--radius);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Modal de confirmação -->
    <div id="modalConfirmar" class="modal-overlay hidden">
        <div class="modal">
            <h3 id="modalTitulo" style="margin-bottom: 1rem; color: var(--destructive);">⚠️ Confirmar Ação</h3>
            <p id="modalMensagem">Tem certeza que deseja realizar esta ação?</p>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
                <a href="#" id="btnConfirmar" class="btn btn-destructive">Confirmar</a>
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
                    <button class="btn btn-outline btn-sm notification-badge" onclick="toggleUserDropdown()">
                        <?php echo htmlspecialchars(explode(' ', $nome_usuario)[0]); ?> ▼
                        <?php if($nao_lidas > 0): ?>
                            <span class="notification-count"><?php echo $nao_lidas; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="userDropdown" class="user-dropdown hidden">
                        <?php if($tipo_usuario == 'cidadao'): ?>
                            <a href="cidadao.php">👤 Meu Painel</a>
                        <?php elseif($tipo_usuario == 'funcionario'): ?>
                            <a href="funcionario.php">📋 Painel Funcionário</a>
                        <?php elseif($tipo_usuario == 'admin_municipal'): ?>
                            <a href="funcionario.php">📋 Painel Admin Municipal</a>
                        <?php elseif($tipo_usuario == 'admin_sistema'): ?>
                            <a href="admin.php">⚙️ Painel Admin Sistema</a>
                        <?php endif; ?>
                        <a href="perfil.php">👤 Meu Perfil</a>
                        <a href="notificacoes.php" class="active">
                            🔔 Notificações 
                            <?php if($nao_lidas > 0): ?>
                                <span class="badge badge-red" style="float: right;"><?php echo $nao_lidas; ?></span>
                            <?php endif; ?>
                        </a>
                        <?php if($tipo_usuario == 'cidadao'): ?>
                            <a href="solicitar.php">📝 Nova Solicitação</a>
                        <?php endif; ?>
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
            <?php if($tipo_usuario == 'cidadao'): ?>
                <a href="cidadao.php">👤 Meu Painel</a>
            <?php elseif($tipo_usuario == 'funcionario'): ?>
                <a href="funcionario.php">📋 Painel Funcionário</a>
            <?php elseif($tipo_usuario == 'admin_municipal'): ?>
                <a href="funcionario.php">📋 Painel Admin Municipal</a>
            <?php elseif($tipo_usuario == 'admin_sistema'): ?>
                <a href="admin.php">⚙️ Painel Admin Sistema</a>
            <?php endif; ?>
            <a href="perfil.php">👤 Meu Perfil</a>
            <a href="notificacoes.php">🔔 Notificações <?php if($nao_lidas > 0): ?>(<?php echo $nao_lidas; ?>)<?php endif; ?></a>
            <?php if($tipo_usuario == 'cidadao'): ?>
                <a href="solicitar.php">📝 Nova Solicitação</a>
            <?php endif; ?>
            <a href="processo.php">📊 Meus Processos</a>
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="container" style="padding: 2rem 1rem;">
            <!-- Título e breadcrumb -->
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Notificações</h1>
                <p style="color: var(--muted-fg);">
                    <a href="index.php" style="color: var(--primary);">Início</a> / 
                    <span>Notificações</span>
                </p>
            </div>

            <!-- Barra de ações -->
            <div class="acoes-barra">
                <div class="filtros">
                    <button class="filtro-btn active" onclick="filtrarNotificacoes('todas')">Todas</button>
                    <button class="filtro-btn" onclick="filtrarNotificacoes('nao-lidas')">Não lidas (<?php echo $nao_lidas; ?>)</button>
                    <button class="filtro-btn" onclick="filtrarNotificacoes('lidas')">Lidas</button>
                </div>
                
                <?php if($notificacoes->num_rows > 0): ?>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if($nao_lidas > 0): ?>
                        <a href="?marcar_todas=1" class="btn btn-outline btn-sm">
                            ✓ Marcar todas como lidas
                        </a>
                    <?php endif; ?>
                    <button onclick="confirmarEliminarTodas()" class="btn btn-outline btn-sm" style="color: var(--destructive);">
                        🗑️ Limpar todas
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Lista de notificações -->
            <?php if($notificacoes->num_rows > 0): ?>
                <div id="lista-notificacoes">
                    <?php while($notif = $notificacoes->fetch_assoc()): 
                        // Determinar ícone e cor baseado no tipo
                        $icone = '📌';
                        $classe_icone = 'icone-info';
                        $classe_badge = 'badge-info';
                        $classe_card = $notif['lida'] ? 'lida' : 'nao-lida';
                        
                        // Verificar se é notificação de número de processo gerado
                        $tem_numero_processo = false;
                        if(strpos($notif['mensagem'], 'número do processo') !== false || 
                           strpos($notif['mensagem'], 'Nº do processo') !== false ||
                           !empty($notif['numero_processo'])) {
                            $tem_numero_processo = true;
                            $icone = '🔢';
                            $classe_icone = 'icone-sucesso';
                            $classe_badge = 'badge-sucesso';
                        }
                        
                        switch($notif['tipo']){
                            case 'sucesso':
                                $icone = '✅';
                                $classe_icone = 'icone-sucesso';
                                $classe_badge = 'badge-sucesso';
                                break;
                            case 'aviso':
                                $icone = '⚠️';
                                $classe_icone = 'icone-aviso';
                                $classe_badge = 'badge-aviso';
                                break;
                            case 'erro':
                                $icone = '❌';
                                $classe_icone = 'icone-erro';
                                $classe_badge = 'badge-erro';
                                break;
                            default:
                                if(!$tem_numero_processo){
                                    $icone = 'ℹ️';
                                    $classe_icone = 'icone-info';
                                    $classe_badge = 'badge-info';
                                }
                        }
                        
                        // Formatar a mensagem
                        $mensagem_formatada = formatarMensagemNotificacao($notif['mensagem']);
                    ?>
                    <div class="notificacao-card <?php echo $classe_card; ?>" data-lida="<?php echo $notif['lida'] ? '1' : '0'; ?>">
                        <div class="notificacao-icone <?php echo $classe_icone; ?>">
                            <?php echo $icone; ?>
                        </div>
                        
                        <div class="notificacao-conteudo">
                            <div class="notificacao-titulo">
                                <?php if(!empty($notif['titulo'])): ?>
                                    <span><?php echo htmlspecialchars($notif['titulo']); ?></span>
                                <?php else: ?>
                                    <span>Notificação do Sistema</span>
                                <?php endif; ?>
                                
                                <span class="badge-tipo <?php echo $classe_badge; ?>">
                                    <?php echo ucfirst($notif['tipo'] ?? 'info'); ?>
                                </span>
                                
                                <?php if(!$notif['lida']): ?>
                                    <span class="badge badge-primary">Nova</span>
                                <?php endif; ?>
                                
                                <?php if($tem_numero_processo && !empty($notif['numero_processo'])): ?>
                                    <span class="numero-processo-destaque">#<?php echo $notif['numero_processo']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notificacao-mensagem">
                                <?php echo $mensagem_formatada; ?>
                            </div>
                            
                            <?php if($tem_numero_processo && !empty($notif['numero_processo'])): ?>
                            <div class="info-adicional">
                                <strong>🔑 Número do processo:</strong> 
                                <span style="font-family: monospace; font-size: 1rem; color: var(--primary);"><?php echo $notif['numero_processo']; ?></span>
                                <br>
                                <small>Guarde este número para acompanhar o seu processo.</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="notificacao-meta">
                                <span>📅 <?php echo date('d/m/Y H:i', strtotime($notif['data_envio'])); ?></span>
                                
                                <?php if(!empty($notif['remetente_nome'])): ?>
                                    <span>👤 De: <?php echo htmlspecialchars($notif['remetente_nome']); ?></span>
                                <?php endif; ?>
                                
                                <?php if(!empty($notif['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="badge badge-outline">
                                        🔗 Ver processo
                                    </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($notif['processo_estado'])): ?>
                                    <span class="badge <?php 
                                        switch($notif['processo_estado']){
                                            case 'Pendente': echo 'badge-yellow'; break;
                                            case 'Rececao': echo 'badge-blue'; break;
                                            case 'Levantamento': echo 'badge-secondary'; break;
                                            default: echo 'badge-gray';
                                        }
                                    ?>">
                                        Estado: <?php echo str_replace('_', ' ', $notif['processo_estado']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="notificacao-acoes">
                            <?php if(!$notif['lida']): ?>
                                <a href="?marcar_lida=<?php echo $notif['id']; ?>" class="btn-acao btn-lida" title="Marcar como lida">
                                    ✓
                                </a>
                            <?php endif; ?>
                            
                            <?php if(!empty($notif['link'])): ?>
                                <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="btn-acao btn-link" title="Ver processo">
                                    🔗
                                </a>
                            <?php endif; ?>
                            
                            <button onclick="confirmarEliminar(<?php echo $notif['id']; ?>)" class="btn-acao btn-eliminar" title="Eliminar">
                                🗑️
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Nenhuma notificação -->
                <div class="notificacao-vazia">
                    <div class="icone">🔔</div>
                    <h3 style="margin-bottom: 1rem;">Nenhuma notificação</h3>
                    <p style="color: var(--muted-fg); margin-bottom: 2rem;">
                        Você não possui notificações no momento.
                    </p>
                    <?php if($tipo_usuario == 'cidadao'): ?>
                        <a href="solicitar.php" class="btn btn-primary">
                            Fazer uma solicitação
                        </a>
                    <?php else: ?>
                        <a href="processo.php" class="btn btn-primary">
                            Ver processos
                        </a>
                    <?php endif; ?>
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

        // Modal de confirmação
        const modal = document.getElementById('modalConfirmar');
        const modalTitulo = document.getElementById('modalTitulo');
        const modalMensagem = document.getElementById('modalMensagem');
        const btnConfirmar = document.getElementById('btnConfirmar');
        
        function confirmarEliminar(id) {
            modalTitulo.textContent = '🗑️ Eliminar Notificação';
            modalMensagem.textContent = 'Tem certeza que deseja eliminar esta notificação?';
            btnConfirmar.href = '?eliminar=' + id;
            modal.classList.remove('hidden');
        }

        function confirmarEliminarTodas() {
            modalTitulo.textContent = '🗑️ Eliminar Todas';
            modalMensagem.textContent = 'Tem certeza que deseja eliminar TODAS as notificações? Esta ação não pode ser desfeita.';
            btnConfirmar.href = '?eliminar_todas=1';
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

        // Filtros
        function filtrarNotificacoes(filtro) {
            const notificacoes = document.querySelectorAll('.notificacao-card');
            
            document.querySelectorAll('.filtro-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            notificacoes.forEach(notif => {
                const lida = notif.dataset.lida === '1';
                
                if(filtro === 'todas') {
                    notif.style.display = 'flex';
                } else if(filtro === 'nao-lidas' && !lida) {
                    notif.style.display = 'flex';
                } else if(filtro === 'lidas' && lida) {
                    notif.style.display = 'flex';
                } else {
                    notif.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>