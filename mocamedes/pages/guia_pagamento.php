<?php
session_start();

if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

require_once("../includes/conexao.php");

// ==========================
// LOGOUT
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
// OBTER RUPE DA URL
// ==========================
$rupe = isset($_GET['rupe']) ? $_GET['rupe'] : '';

if(empty($rupe)){
    header("Location: index.php");
    exit();
}

// ==========================
// BUSCAR DADOS DA GUIA
// ==========================
$sql = "SELECT g.*, s.id as solicitacao_id, s.numero_processo, s.descricao, u.email, u.telefone, u.nome as cidadao_nome
        FROM guias_pagamento g
        JOIN solicitacoes s ON g.solicitacao_id = s.id
        JOIN usuarios u ON s.usuario_id = u.id
        WHERE g.rupe = ?";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("s", $rupe);
$stmt->execute();
$guia = $stmt->get_result()->fetch_assoc();

if(!$guia){
    header("Location: index.php");
    exit();
}

// ==========================
// VERIFICAR SE O USUÁRIO TEM PERMISSÃO PARA VER A GUIA
// ==========================
$pode_ver = false;
if($tipo_usuario == 'cidadao'){
    // Cidadão só pode ver suas próprias guias
    $sql_check = "SELECT id FROM solicitacoes WHERE id = ? AND usuario_id = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("ii", $guia['solicitacao_id'], $usuario_id);
    $stmt_check->execute();
    if($stmt_check->get_result()->num_rows > 0){
        $pode_ver = true;
    }
} else {
    // Funcionários e admins podem ver todas
    $tipos_permitidos = ['funcionario', 'admin_municipal', 'admin_sistema'];
    if(in_array($tipo_usuario, $tipos_permitidos)){
        $pode_ver = true;
    }
}

if(!$pode_ver){
    header("Location: index.php");
    exit();
}

// ==========================
// PROCESSAR PAGAMENTO (SIMULAÇÃO) - APENAS PARA O CIDADÃO DONO DA GUIA
// ==========================
if(isset($_POST['simular_pagamento']) && $tipo_usuario == 'cidadao'){
    $forma_pagamento = $_POST['forma_pagamento'];
    $n_autenticacao = 'AUTH' . date('YmdHis') . rand(100, 999);
    
    $update = "UPDATE guias_pagamento 
               SET estado = 'pago', data_pagamento = NOW(), forma_pagamento = ?, n_autenticacao = ?
               WHERE rupe = ? AND estado = 'pendente'";
    $stmt_update = $conexao->prepare($update);
    $stmt_update->bind_param("sss", $forma_pagamento, $n_autenticacao, $rupe);
    
    if($stmt_update->execute() && $stmt_update->affected_rows > 0){
        // Atualizar estado da solicitação para próximo passo (ex: Levantamento)
        $update_solic = "UPDATE solicitacoes SET estado = 'Levantamento' WHERE id = ?";
        $stmt_solic = $conexao->prepare($update_solic);
        $stmt_solic->bind_param("i", $guia['solicitacao_id']);
        $stmt_solic->execute();
        
        // Criar notificação de pagamento confirmado
        $notificacao = "✅ Pagamento confirmado para o processo #" . $guia['numero_processo'] . ".\n\n";
        $notificacao .= "Nº de autenticação: " . $n_autenticacao . "\n";
        $notificacao .= "O processo foi atualizado para 'Levantamento'.";
        
        $sql_notif = "INSERT INTO notificacoes 
                      (usuario_id, tipo, titulo, mensagem, link) 
                      VALUES (?, 'sucesso', 'Pagamento Confirmado', ?, ?)";
        $link = "processo.php?id=" . $guia['solicitacao_id'];
        $stmt_notif = $conexao->prepare($sql_notif);
        $stmt_notif->bind_param("iss", $usuario_id, $notificacao, $link);
        $stmt_notif->execute();
        
        $sucesso = "Pagamento registado com sucesso! Nº de autenticação: " . $n_autenticacao;
        
        // Recarregar dados
        header("Location: guia_pagamento.php?rupe=" . urlencode($rupe) . "&pago=1");
        exit();
    } else {
        $erro = "Erro ao processar pagamento. A guia pode já estar paga ou expirada.";
    }
}

// ==========================
// VERIFICAR SE JÁ ESTÁ PAGO
// ==========================
$pago = isset($_GET['pago']);

// ==========================
// BUSCAR NOTIFICAÇÕES NÃO LIDAS (para o menu)
// ==========================
$sql_notif = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_notif = $conexao->prepare($sql_notif);
$stmt_notif->bind_param("i", $usuario_id);
$stmt_notif->execute();
$notificacoes_nao_lidas = $stmt_notif->get_result()->fetch_assoc()['total'];

// ==========================
// FORMATAR RUPE PARA EXIBIÇÃO
// ==========================
function formatarRUPE($rupe) {
    // Formato: XXXX XXXX XXXX XXXX X
    return substr($rupe, 0, 4) . ' ' . 
           substr($rupe, 4, 4) . ' ' . 
           substr($rupe, 8, 4) . ' ' . 
           substr($rupe, 12, 4) . ' ' . 
           substr($rupe, 16, 1);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guia de Pagamento - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .guia-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
        }
        
        .guia-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary);
        }
        
        .guia-header h1 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .guia-header .rupe-destaque {
            font-size: 1.5rem;
            font-family: monospace;
            background: var(--muted);
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            display: inline-block;
            letter-spacing: 2px;
        }
        
        .guia-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-grupo {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--muted-fg);
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-valor {
            font-weight: 600;
            font-size: 1.1rem;
            padding: 0.5rem;
            background: var(--muted);
            border-radius: var(--radius);
            border-left: 3px solid var(--primary);
        }
        
        .valor-destaque {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            text-align: center;
            padding: 1rem;
            background: linear-gradient(135deg, var(--muted) 0%, white 100%);
            border-radius: var(--radius);
            margin-bottom: 2rem;
            border: 2px dashed var(--primary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-pago {
            background: #d4edda;
            color: #155724;
        }
        
        .status-expirado {
            background: #f8d7da;
            color: #721c24;
        }
        
        .dados-bancarios {
            background: var(--muted);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-top: 2rem;
            border: 1px solid var(--border);
        }
        
        .simulacao-pagamento {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #e8f4fd;
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
        }
        
        .comprovante {
            background: #d4edda;
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1rem;
            border-left: 4px solid var(--secondary);
        }
        
        .btn-pagar {
            background: var(--secondary);
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-pagar:hover {
            background: #219a52;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-imprimir {
            background: var(--primary);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-imprimir:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }
        
        .btn-voltar {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-voltar:hover {
            background: var(--primary);
            color: white;
        }
        
        .acoes {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
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
        
        .mensagem-sucesso {
            background: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border-left: 4px solid var(--secondary);
        }
        
        .mensagem-erro {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border-left: 4px solid var(--destructive);
        }
        
        @media print {
            .btn-imprimir, .simulacao-pagamento, .acoes, .nav, .footer, .topbar, .user-menu {
                display: none !important;
            }
            
            .guia-container {
                box-shadow: none;
                margin: 0;
                padding: 1rem;
                border: 1px solid #ccc;
            }
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
                <a href="processo.php">Acompanhar Processo</a>
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
                        <a href="notificacoes.php">
                            🔔 Notificações 
                            <?php if($notificacoes_nao_lidas > 0): ?>
                                <span class="badge badge-red" style="float: right;"><?php echo $notificacoes_nao_lidas; ?></span>
                            <?php endif; ?>
                        </a>
                        <?php if($tipo_usuario == 'cidadao'): ?>
                            <a href="solicitar.php">📝 Nova Solicitação</a>
                        <?php endif; ?>
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
            <a href="notificacoes.php">🔔 Notificações <?php if($notificacoes_nao_lidas > 0): ?>(<?php echo $notificacoes_nao_lidas; ?>)<?php endif; ?></a>
            <?php if($tipo_usuario == 'cidadao'): ?>
                <a href="solicitar.php">📝 Nova Solicitação</a>
            <?php endif; ?>
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="container" style="padding: 2rem 1rem;">
            <!-- Botão voltar (apenas para mobile) -->
            <div style="margin-bottom: 1rem;">
                <a href="javascript:history.back()" class="btn btn-outline btn-sm">← Voltar</a>
            </div>

            <div class="guia-container">
                <!-- Mensagens de sucesso/erro -->
                <?php if(isset($sucesso)): ?>
                    <div class="mensagem-sucesso"><?php echo htmlspecialchars($sucesso); ?></div>
                <?php endif; ?>
                
                <?php if(isset($erro)): ?>
                    <div class="mensagem-erro"><?php echo htmlspecialchars($erro); ?></div>
                <?php endif; ?>

                <div class="guia-header">
                    <h1>🪙 Guia de Pagamento</h1>
                    <h2 style="color: var(--muted-fg); font-size: 1rem; margin-top: 0.3rem;">Recibo Único de Pagamento ao Estado (RUPE)</h2>
                    <div class="rupe-destaque"><?php echo formatarRUPE($rupe); ?></div>
                </div>

                <?php if($pago): ?>
                    <div class="comprovante">
                        <h3>✅ Pagamento Confirmado</h3>
                        <p>O pagamento foi processado com sucesso. O processo foi atualizado para <strong>Levantamento</strong>.</p>
                    </div>
                <?php endif; ?>

                <div class="valor-destaque">
                    <?php echo number_format($guia['valor'], 2, ',', '.'); ?> Kz
                </div>

                <div class="guia-info">
                    <div class="info-grupo">
                        <div class="info-label">NIF do Contribuinte</div>
                        <div class="info-valor"><?php echo htmlspecialchars($guia['nif_contribuinte']); ?></div>
                    </div>
                    
                    <div class="info-grupo">
                        <div class="info-label">Nome / Designação</div>
                        <div class="info-valor"><?php echo htmlspecialchars($guia['nome_contribuinte']); ?></div>
                    </div>
                    
                    <div class="info-grupo">
                        <div class="info-label">Serviço</div>
                        <div class="info-valor"><?php echo htmlspecialchars($guia['servico_nome']); ?></div>
                    </div>
                    
                    <div class="info-grupo">
                        <div class="info-label">Nº do Processo</div>
                        <div class="info-valor"><?php echo htmlspecialchars($guia['numero_processo']); ?></div>
                    </div>
                    
                    <div class="info-grupo">
                        <div class="info-label">Data de Emissão</div>
                        <div class="info-valor"><?php echo date('d/m/Y H:i', strtotime($guia['data_emissao'])); ?></div>
                    </div>
                    
                    <div class="info-grupo">
                        <div class="info-label">Data Limite</div>
                        <div class="info-valor">
                            <?php echo date('d/m/Y', strtotime($guia['data_limite'])); ?>
                            <?php 
                            $hoje = new DateTime();
                            $limite = new DateTime($guia['data_limite']);
                            if($hoje > $limite && $guia['estado'] == 'pendente'){
                                echo '<span class="status-badge status-expirado" style="margin-left: 0.5rem;">Expirado</span>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-grupo">
                        <div class="info-label">Estado</div>
                        <div class="info-valor">
                            <?php
                            $statusClass = 'status-pendente';
                            $statusText = 'Pendente';
                            if($guia['estado'] == 'pago'){
                                $statusClass = 'status-pago';
                                $statusText = 'Pago em ' . date('d/m/Y H:i', strtotime($guia['data_pagamento']));
                            } elseif($guia['estado'] == 'expirado'){
                                $statusClass = 'status-expirado';
                                $statusText = 'Expirado';
                            }
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </div>
                    </div>
                </div>

                <div class="dados-bancarios">
                    <h4 style="margin-bottom: 1rem; color: var(--primary);">🏦 Dados para Pagamento</h4>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <div>
                            <div class="info-label">Banco</div>
                            <div style="font-weight: 600;">Empresa Interbancária de Serviços, SA</div>
                        </div>
                        <div>
                            <div class="info-label">Balcão</div>
                            <div style="font-weight: 600;">Emis</div>
                        </div>
                        <div>
                            <div class="info-label">Referência</div>
                            <div style="font-weight: 600; font-family: monospace;"><?php echo substr($rupe, -8); ?></div>
                        </div>
                    </div>
                </div>

                <?php if($guia['estado'] == 'pendente' && $tipo_usuario == 'cidadao'): ?>
                    <div class="simulacao-pagamento">
                        <h4 style="margin-bottom: 1rem;">💳 Simulação de Pagamento</h4>
                        <p style="margin-bottom: 1rem; color: var(--muted-fg);">Esta é uma simulação. Selecione a forma de pagamento para testar:</p>
                        
                        <form method="POST">
                            <div style="margin: 1rem 0;">
                                <select name="forma_pagamento" class="form-control" required style="width: 100%; max-width: 300px;">
                                    <option value="multicaixa">Multicaixa</option>
                                    <option value="referencia">Referência Multicaixa</option>
                                    <option value="numerario">Numerário (Balcão)</option>
                                </select>
                            </div>
                            <button type="submit" name="simular_pagamento" class="btn-pagar">
                                💰 Simular Pagamento
                            </button>
                        </form>
                        
                        <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--muted-fg);">
                            <small>⚠️ Após o pagamento simulado, o processo será atualizado para "Levantamento".</small>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if($guia['estado'] == 'pago'): ?>
                    <div style="text-align: center; margin-top: 2rem; padding: 1.5rem; background: var(--muted); border-radius: var(--radius);">
                        <h4 style="color: var(--secondary); margin-bottom: 1rem;">📄 Comprovante de Pagamento</h4>
                        <p><strong>Nº Autenticação:</strong> <span style="font-family: monospace;"><?php echo $guia['n_autenticacao']; ?></span></p>
                        <p><strong>Forma de Pagamento:</strong> <?php echo ucfirst($guia['forma_pagamento']); ?></p>
                        <p><strong>Data/Hora:</strong> <?php echo date('d/m/Y H:i:s', strtotime($guia['data_pagamento'])); ?></p>
                    </div>
                <?php endif; ?>

                <div class="acoes">
                    <button onclick="window.print()" class="btn-imprimir">
                        🖨️ Imprimir Guia
                    </button>
                    
                    <?php if($tipo_usuario == 'cidadao'): ?>
                        <a href="processo.php?id=<?php echo $guia['solicitacao_id']; ?>" class="btn-voltar">
                            📋 Ver Processo
                        </a>
                    <?php elseif(in_array($tipo_usuario, ['funcionario', 'admin_municipal', 'admin_sistema'])): ?>
                        <a href="detalhes.php?id=<?php echo $guia['solicitacao_id']; ?>" class="btn-voltar">
                            📋 Ver Detalhes
                        </a>
                    <?php endif; ?>
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
    </script>
</body>
</html>