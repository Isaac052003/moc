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
// BUSCAR NOTIFICAÇÕES NÃO LIDAS
// ==========================
$sql_notif = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_notif = $conexao->prepare($sql_notif);
$stmt_notif->bind_param("i", $usuario_id);
$stmt_notif->execute();
$notificacoes_nao_lidas = $stmt_notif->get_result()->fetch_assoc()['total'];

// Verificar se é cidadão (redirecionar se não for)
if($tipo_usuario != 'cidadao'){
    header("Location: index.php");
    exit();
}

// ==========================
// PROCESSAR SOLICITAÇÃO
// ==========================
$erro = "";
$sucesso = "";

if(isset($_POST['enviar_solicitacao'])){
    
    $servico = $_POST['servico'];
    $descricao = trim($_POST['descricao']);
    
    // Validar campos
    if(empty($servico) || empty($descricao)){
        $erro = "Por favor, selecione um serviço e preencha a descrição.";
    } else {
        
        // Mapear serviço para ID
        $servico_id = 0;
        switch($servico){
            case 'terreno':
                $servico_id = 1; // Regularização de Terreno
                break;
            case 'evento':
                $servico_id = 2; // Autorização para Evento
                break;
            case 'construcao':
                $servico_id = 3; // Licença de Construção
                break;
        }
        
        // =========================================
        // NÃO GERAR NÚMERO DO PROCESSO AQUI
        // O número será gerado quando o funcionário mudar para "Recepção"
        // =========================================
        
        // Inserir solicitação SEM número do processo (NULL)
        $sql = "INSERT INTO solicitacoes (usuario_id, servico_id, descricao, estado) 
                VALUES (?, ?, ?, 'Pendente')";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("iis", $usuario_id, $servico_id, $descricao);
        
        if($stmt->execute()){
            $solicitacao_id = $stmt->insert_id;
            
            // Processar uploads de documentos
            $upload_dir = "uploads/solicitacoes/" . $solicitacao_id . "/";
            
            // Criar diretório se não existir
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Array com os documentos esperados para cada serviço
            $documentos = [];
            $documentos_obrigatorios = true;
            
            if($servico == 'terreno'){
                $documentos = [
                    'req_terreno' => 'Requerimento',
                    'bi_terreno' => 'Bilhete de Identidade',
                    'doc_terreno' => 'Documento do Terreno'
                ];
            } elseif($servico == 'evento') {
                $documentos = [
                    'req_evento' => 'Requerimento',
                    'bi_evento' => 'Bilhete de Identidade'
                ];
            } elseif($servico == 'construcao') {
                $documentos = [
                    'req_const' => 'Requerimento',
                    'bi_const' => 'Bilhete de Identidade',
                    'doc_const' => 'Documento do Terreno'
                ];
            }
            
            // Fazer upload dos documentos
            foreach($documentos as $campo => $tipo_doc){
                if(isset($_FILES[$campo]) && $_FILES[$campo]['error'] == 0){
                    $extensao = pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION);
                    $nome_arquivo = strtolower(str_replace(' ', '_', $tipo_doc)) . '_' . date('YmdHis') . '.' . $extensao;
                    $caminho_arquivo = $upload_dir . $nome_arquivo;
                    
                    if(move_uploaded_file($_FILES[$campo]['tmp_name'], $caminho_arquivo)){
                        // Salvar no banco de dados
                        $sql_doc = "INSERT INTO documentos (solicitacao_id, tipo_documento, arquivo) 
                                   VALUES (?, ?, ?)";
                        $stmt_doc = $conexao->prepare($sql_doc);
                        $stmt_doc->bind_param("iss", $solicitacao_id, $tipo_doc, $caminho_arquivo);
                        $stmt_doc->execute();
                    }
                } else {
                    // Se algum documento obrigatório não foi enviado, marcar flag
                    $documentos_obrigatorios = false;
                }
            }
            
            // Criar notificação para funcionários (sem número do processo ainda)
            $notificacao = "Nova solicitação de $nome_usuario aguardando recepção";
            $sql_notif = "INSERT INTO notificacoes (usuario_id, remetente_id, tipo, titulo, mensagem, link) VALUES (?, ?, 'info', 'Nova Solicitação', ?, ?)";
            $stmt_notif = $conexao->prepare($sql_notif);
            $link = "detalhes.php?id=" . $solicitacao_id;
            
            // Notificar todos os funcionários e admins
            $sql_func = "SELECT id FROM usuarios WHERE tipo IN ('funcionario', 'admin_municipal', 'admin_sistema') AND estado = 'ativo'";
            $funcionarios = $conexao->query($sql_func);
            while($func = $funcionarios->fetch_assoc()){
                $stmt_notif->bind_param("iiss", $func['id'], $usuario_id, $notificacao, $link);
                $stmt_notif->execute();
            }
            
            // Mensagem de sucesso SEM o número do processo
            $sucesso = "✅ Solicitação enviada com sucesso! Aguarde a recepção do seu processo. O número do processo será gerado quando a administração fizer a recepção da sua solicitação.";
            
        } else {
            $erro = "Erro ao enviar solicitação. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <title>Solicitar Serviço - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <style>
        .solicitacao-container {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .solicitacao-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--primary);
        }
        
        .info-usuario {
            background: var(--muted);
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            border-left: 4px solid var(--primary);
        }
        
        .upload-area {
            background: var(--muted);
            padding: 15px;
            border-radius: var(--radius);
            border: 2px dashed var(--border);
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: var(--primary);
            background: #f0f7ff;
        }
        
        .upload-area label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .upload-area input[type="file"] {
            padding: 8px;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            width: 100%;
        }
        
        .hidden {
            display: none;
        }
        
        .mensagem-sucesso {
            background: #dcfce7;
            color: #166534;
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            border-left: 4px solid var(--secondary);
        }
        
        .mensagem-erro {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            border-left: 4px solid var(--destructive);
        }
        
        .btn-enviar {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-enviar:hover {
            background: var(--primary-light);
        }
        
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1rem;
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
                <a href="servicos.php" class="active">Serviços</a>
                <a href="reportar.php">Reportar Problema</a>
                <a href="sugestoes.php">Sugestões</a>
                <a href="noticias.php">Notícias</a>
                <a href="processo.php">Acompanhar Processo</a>
            </nav>

            <div class="nav-auth">
                <!-- Menu do usuário logado -->
                <div class="user-menu">
                    <button class="btn btn-outline btn-sm notification-badge" onclick="toggleUserDropdown()">
                        Olá, <?php echo htmlspecialchars(explode(' ', $nome_usuario)[0]); ?> ▼
                        <?php if($notificacoes_nao_lidas > 0): ?>
                            <span class="notification-count"><?php echo $notificacoes_nao_lidas; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="userDropdown" class="user-dropdown hidden">
                        <a href="cidadao.php">👤 Meu Painel</a>
                        <a href="notificacoes.php">
                            🔔 Notificações 
                            <?php if($notificacoes_nao_lidas > 0): ?>
                                <span class="badge badge-red" style="float: right;"><?php echo $notificacoes_nao_lidas; ?></span>
                            <?php endif; ?>
                        </a>
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
            <a href="cidadao.php">👤 Meu Painel</a>
            <a href="notificacoes.php">🔔 Notificações <?php if($notificacoes_nao_lidas > 0): ?>(<?php echo $notificacoes_nao_lidas; ?>)<?php endif; ?></a>
            <a href="processo.php">📊 Meus Processos</a>
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="solicitacao-container">
            <h2>Solicitação de Serviço</h2>
            
            <!-- Informação do usuário logado -->
            <div class="info-usuario">
                <div class="flex items-center gap-2">
                    <span style="font-size: 1.5rem;">👤</span>
                    <div>
                        <strong>Solicitante:</strong> <?php echo htmlspecialchars($nome_usuario); ?><br>
                        <small style="color: var(--muted-fg);">ID: <?php echo $usuario_id; ?></small>
                    </div>
                </div>
            </div>

            <!-- Mensagens de sucesso/erro -->
            <?php if($sucesso != ""): ?>
                <div class="mensagem-sucesso">
                    <strong>✅ <?php echo $sucesso; ?></strong>
                </div>
                <div class="info-box">
                    <strong>ℹ️ Informação importante:</strong>
                    <p style="margin-top: 0.5rem; margin-bottom: 0;">
                        O número do seu processo será gerado quando a administração municipal fizer a recepção da sua solicitação. 
                        Você receberá uma notificação assim que o processo for recebido.
                    </p>
                </div>
            <?php endif; ?>

            <?php if($erro != ""): ?>
                <div class="mensagem-erro">
                    ❌ <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="servico">Tipo de Serviço *</label>
                    <select name="servico" id="servico" class="form-control" required>
                        <option value="">Selecione um serviço</option>
                        <option value="terreno">Regularização de Terreno</option>
                        <option value="evento">Autorização para Evento</option>
                        <option value="construcao">Licença de Construção</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição do Pedido *</label>
                    <textarea name="descricao" id="descricao" class="form-control" rows="5" required></textarea>
                    <small style="color: var(--muted-fg);">Descreva detalhadamente o que precisa</small>
                </div>

                <!-- DOCUMENTOS TERRENO -->
                <div id="docs_terreno" class="hidden">
                    <h4 style="margin: 20px 0 10px; color: var(--primary);">Documentos para Regularização de Terreno</h4>
                    <div class="upload-area">
                        <label>Requerimento (PDF) *</label>
                        <input type="file" name="req_terreno" accept=".pdf" id="req_terreno">
                    </div>

                    <div class="upload-area">
                        <label>Bilhete de Identidade (PDF) *</label>
                        <input type="file" name="bi_terreno" accept=".pdf" id="bi_terreno">
                    </div>

                    <div class="upload-area">
                        <label>Documento do Terreno (PDF) *</label>
                        <input type="file" name="doc_terreno" accept=".pdf" id="doc_terreno">
                    </div>
                </div>

                <!-- DOCUMENTOS EVENTO -->
                <div id="docs_evento" class="hidden">
                    <h4 style="margin: 20px 0 10px; color: var(--primary);">Documentos para Autorização de Evento</h4>
                    <div class="upload-area">
                        <label>Requerimento (PDF) *</label>
                        <input type="file" name="req_evento" accept=".pdf" id="req_evento">
                    </div>

                    <div class="upload-area">
                        <label>Bilhete de Identidade (PDF) *</label>
                        <input type="file" name="bi_evento" accept=".pdf" id="bi_evento">
                    </div>
                </div>

                <!-- DOCUMENTOS CONSTRUÇÃO -->
                <div id="docs_construcao" class="hidden">
                    <h4 style="margin: 20px 0 10px; color: var(--primary);">Documentos para Licença de Construção</h4>
                    <div class="upload-area">
                        <label>Requerimento (PDF) *</label>
                        <input type="file" name="req_const" accept=".pdf" id="req_const">
                    </div>

                    <div class="upload-area">
                        <label>Bilhete de Identidade (PDF) *</label>
                        <input type="file" name="bi_const" accept=".pdf" id="bi_const">
                    </div>

                    <div class="upload-area">
                        <label>Documento do Terreno (PDF) *</label>
                        <input type="file" name="doc_const" accept=".pdf" id="doc_const">
                    </div>
                </div>

                <br>

                <div style="text-align: center;">
                    <button type="submit" name="enviar_solicitacao" class="btn-enviar" id="btnEnviar">
                        Enviar Solicitação
                    </button>
                </div>
            </form>
            
            <!-- Informação adicional sobre o número do processo -->
            <div style="margin-top: 2rem; padding: 1rem; background: var(--muted); border-radius: var(--radius);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.5rem;">ℹ️</span>
                    <div>
                        <strong>Sobre o número do processo:</strong>
                        <p style="margin-top: 0.3rem; color: var(--muted-fg); font-size: 0.9rem;">
                            O número do processo será gerado apenas quando a administração municipal fizer a recepção da sua solicitação. 
                            Você será notificado assim que isso acontecer.
                        </p>
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

        // Mostrar/esconder documentos baseado no serviço selecionado
        const servico = document.getElementById("servico");
        const terreno = document.getElementById("docs_terreno");
        const evento = document.getElementById("docs_evento");
        const construcao = document.getElementById("docs_construcao");

        servico.addEventListener("change", function(){
            terreno.classList.add("hidden");
            evento.classList.add("hidden");
            construcao.classList.add("hidden");

            if(this.value === "terreno"){
                terreno.classList.remove("hidden");
                // Tornar campos obrigatórios
                document.getElementById('req_terreno').required = true;
                document.getElementById('bi_terreno').required = true;
                document.getElementById('doc_terreno').required = true;
            } else {
                document.getElementById('req_terreno').required = false;
                document.getElementById('bi_terreno').required = false;
                document.getElementById('doc_terreno').required = false;
            }

            if(this.value === "evento"){
                evento.classList.remove("hidden");
                document.getElementById('req_evento').required = true;
                document.getElementById('bi_evento').required = true;
            } else {
                document.getElementById('req_evento').required = false;
                document.getElementById('bi_evento').required = false;
            }

            if(this.value === "construcao"){
                construcao.classList.remove("hidden");
                document.getElementById('req_const').required = true;
                document.getElementById('bi_const').required = true;
                document.getElementById('doc_const').required = true;
            } else {
                document.getElementById('req_const').required = false;
                document.getElementById('bi_const').required = false;
                document.getElementById('doc_const').required = false;
            }
        });

        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const servicoSelecionado = servico.value;
            const descricao = document.getElementById('descricao').value;
            
            if(descricao.length < 20) {
                e.preventDefault();
                alert('A descrição deve ter pelo menos 20 caracteres.');
                return;
            }
            
            if(!servicoSelecionado) {
                e.preventDefault();
                alert('Selecione um serviço.');
                return;
            }
            
            // Validar documentos baseado no serviço
            if(servicoSelecionado === 'terreno') {
                if(!document.getElementById('req_terreno').files.length ||
                   !document.getElementById('bi_terreno').files.length ||
                   !document.getElementById('doc_terreno').files.length) {
                    e.preventDefault();
                    alert('Por favor, anexe todos os documentos necessários para Regularização de Terreno.');
                }
            } else if(servicoSelecionado === 'evento') {
                if(!document.getElementById('req_evento').files.length ||
                   !document.getElementById('bi_evento').files.length) {
                    e.preventDefault();
                    alert('Por favor, anexe todos os documentos necessários para Autorização de Evento.');
                }
            } else if(servicoSelecionado === 'construcao') {
                if(!document.getElementById('req_const').files.length ||
                   !document.getElementById('bi_const').files.length ||
                   !document.getElementById('doc_const').files.length) {
                    e.preventDefault();
                    alert('Por favor, anexe todos os documentos necessários para Licença de Construção.');
                }
            }
        });
    </script>
</body>
</html>
