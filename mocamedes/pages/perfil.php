<?php
session_start();

// ==========================
// VERIFICAR SE USUÁRIO ESTÁ LOGADO
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
// LOGOUT
// ==========================
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: login.php");
    exit();
}

// ==========================
// BUSCAR DADOS DO USUÁRIO
// ==========================
$sql = "SELECT id, nome, email, bi, telefone, tipo, estado, foto, data_registro, ultimo_login 
        FROM usuarios WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// ==========================
// BUSCAR NOTIFICAÇÕES NÃO LIDAS
// ==========================
$sql_notif = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
$stmt_notif = $conexao->prepare($sql_notif);
$stmt_notif->bind_param("i", $usuario_id);
$stmt_notif->execute();
$notificacoes_nao_lidas = $stmt_notif->get_result()->fetch_assoc()['total'];

// ==========================
// ATUALIZAR PERFIL
// ==========================
$mensagem = '';
$erro = '';

if(isset($_POST['atualizar_perfil'])){
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    
    // Validar campos
    if(empty($nome) || empty($email)){
        $erro = "Nome e email são obrigatórios.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $erro = "Email inválido.";
    } else {
        // Verificar se email já existe (exceto o do próprio usuário)
        $check_email = "SELECT id FROM usuarios WHERE email = ? AND id != ?";
        $stmt_check = $conexao->prepare($check_email);
        $stmt_check->bind_param("si", $email, $usuario_id);
        $stmt_check->execute();
        if($stmt_check->get_result()->num_rows > 0){
            $erro = "Este email já está em uso por outro usuário.";
        } else {
            // Atualizar dados
            $update = "UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?";
            $stmt_update = $conexao->prepare($update);
            $stmt_update->bind_param("sssi", $nome, $email, $telefone, $usuario_id);
            
            if($stmt_update->execute()){
                $_SESSION['nome'] = $nome;
                $mensagem = "Perfil atualizado com sucesso!";
                
                // Atualizar dados na variável
                $usuario['nome'] = $nome;
                $usuario['email'] = $email;
                $usuario['telefone'] = $telefone;
            } else {
                $erro = "Erro ao atualizar perfil.";
            }
        }
    }
}

// ==========================
// ALTERAR SENHA
// ==========================
if(isset($_POST['alterar_senha'])){
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    // Validar campos
    if(empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)){
        $erro = "Todos os campos de senha são obrigatórios.";
    } elseif($nova_senha != $confirmar_senha){
        $erro = "A nova senha e a confirmação não coincidem.";
    } elseif(strlen($nova_senha) < 6){
        $erro = "A nova senha deve ter pelo menos 6 caracteres.";
    } else {
        // Verificar senha atual
        $check_senha = "SELECT senha FROM usuarios WHERE id = ?";
        $stmt_check = $conexao->prepare($check_senha);
        $stmt_check->bind_param("i", $usuario_id);
        $stmt_check->execute();
        $senha_hash = $stmt_check->get_result()->fetch_assoc()['senha'];
        
        // Verificar se a senha atual está correta
        if($senha_atual === $senha_hash){ // Senha em texto simples
            // Atualizar senha
            $update = "UPDATE usuarios SET senha = ? WHERE id = ?";
            $stmt_update = $conexao->prepare($update);
            $stmt_update->bind_param("si", $nova_senha, $usuario_id);
            
            if($stmt_update->execute()){
                $mensagem = "Senha alterada com sucesso!";
            } else {
                $erro = "Erro ao alterar senha.";
            }
        } else {
            $erro = "Senha atual incorreta.";
        }
    }
}

// ==========================
// UPLOAD DE FOTO
// ==========================
if(isset($_POST['upload_foto']) && isset($_FILES['foto'])){
    $foto = $_FILES['foto'];
    
    // Verificar se há erro no upload
    if($foto['error'] != 0){
        $erro = "Erro ao fazer upload da imagem.";
    } else {
        // Verificar tipo de arquivo
        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if(!in_array($foto['type'], $tipos_permitidos)){
            $erro = "Apenas imagens JPG, PNG e GIF são permitidas.";
        } elseif($foto['size'] > 2 * 1024 * 1024){ // 2MB
            $erro = "A imagem não pode ter mais de 2MB.";
        } else {
            // Criar diretório se não existir
            $upload_dir = 'uploads/fotos/';
            if(!file_exists($upload_dir)){
                mkdir($upload_dir, 0777, true);
            }
            
            // Gerar nome único para o arquivo
            $extensao = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nome_arquivo = 'foto_' . $usuario_id . '_' . time() . '.' . $extensao;
            $caminho_completo = $upload_dir . $nome_arquivo;
            
            // Fazer upload
            if(move_uploaded_file($foto['tmp_name'], $caminho_completo)){
                // Atualizar banco de dados
                $update = "UPDATE usuarios SET foto = ? WHERE id = ?";
                $stmt_update = $conexao->prepare($update);
                $stmt_update->bind_param("si", $caminho_completo, $usuario_id);
                
                if($stmt_update->execute()){
                    $mensagem = "Foto atualizada com sucesso!";
                    $usuario['foto'] = $caminho_completo;
                } else {
                    $erro = "Erro ao salvar foto no banco de dados.";
                }
            } else {
                $erro = "Erro ao fazer upload da imagem.";
            }
        }
    }
}

// ==========================
// REMOVER FOTO
// ==========================
if(isset($_GET['remover_foto'])){
    if(!empty($usuario['foto']) && file_exists($usuario['foto'])){
        unlink($usuario['foto']); // Remove o arquivo
    }
    
    $update = "UPDATE usuarios SET foto = NULL WHERE id = ?";
    $stmt_update = $conexao->prepare($update);
    $stmt_update->bind_param("i", $usuario_id);
    $stmt_update->execute();
    
    $mensagem = "Foto removida com sucesso!";
    $usuario['foto'] = null;
    header("Location: perfil.php?sucesso=1");
    exit();
}

// ==========================
// ESTATÍSTICAS POR TIPO DE USUÁRIO
// ==========================
$total_solicitacoes = 0;
$total_processos = 0;
$ultimo_acesso = $usuario['ultimo_login'] ?? 'Nunca acessou';

if($tipo_usuario == 'cidadao'){
    $sql_stats = "SELECT COUNT(*) as total FROM solicitacoes WHERE usuario_id = ?";
    $stmt_stats = $conexao->prepare($sql_stats);
    $stmt_stats->bind_param("i", $usuario_id);
    $stmt_stats->execute();
    $total_solicitacoes = $stmt_stats->get_result()->fetch_assoc()['total'];
    
    $sql_andamento = "SELECT COUNT(*) as total FROM solicitacoes WHERE usuario_id = ? AND estado != 'Levantamento'";
    $stmt_andamento = $conexao->prepare($sql_andamento);
    $stmt_andamento->bind_param("i", $usuario_id);
    $stmt_andamento->execute();
    $total_processos = $stmt_andamento->get_result()->fetch_assoc()['total'];
    
} elseif($tipo_usuario == 'funcionario' || $tipo_usuario == 'admin_municipal'){
    $sql_stats = "SELECT COUNT(*) as total FROM solicitacoes";
    $result = $conexao->query($sql_stats);
    $total_solicitacoes = $result->fetch_assoc()['total'];
    
    $sql_pendentes = "SELECT COUNT(*) as total FROM solicitacoes WHERE estado = 'Pendente'";
    $result = $conexao->query($sql_pendentes);
    $total_processos = $result->fetch_assoc()['total'];
    
} elseif($tipo_usuario == 'admin_sistema'){
    $sql_stats = "SELECT COUNT(*) as total FROM usuarios";
    $result = $conexao->query($sql_stats);
    $total_solicitacoes = $result->fetch_assoc()['total'];
    
    $sql_ativos = "SELECT COUNT(*) as total FROM usuarios WHERE estado = 'ativo'";
    $result = $conexao->query($sql_ativos);
    $total_processos = $result->fetch_assoc()['total'];
}

// Mensagem de sucesso via GET
if(isset($_GET['sucesso'])){
    $mensagem = "Operação realizada com sucesso!";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <title>Meu Perfil - Portal do Moçamedense</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .perfil-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .perfil-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .perfil-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            overflow: hidden;
            border: 4px solid var(--primary-light);
        }
        
        .perfil-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .perfil-info h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .perfil-info .badge {
            font-size: 0.9rem;
            padding: 0.3rem 1rem;
        }
        
        .perfil-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: var(--muted);
            padding: 1.5rem;
            border-radius: var(--radius);
            text-align: center;
        }
        
        .stat-box .numero {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-box .label {
            color: var(--muted-fg);
            font-size: 0.9rem;
        }
        
        .perfil-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid var(--border);
            margin-bottom: 2rem;
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
        
        .foto-upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .foto-upload-area:hover {
            border-color: var(--primary);
            background: var(--muted);
        }
        
        .foto-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            overflow: hidden;
            border: 3px solid var(--primary);
        }
        
        .foto-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .acoes-foto {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
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
        
        .info-item {
            margin-bottom: 1.5rem;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--muted-fg);
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-weight: 600;
            font-size: 1.1rem;
            padding: 0.5rem;
            background: var(--muted);
            border-radius: var(--radius);
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="topbar">
            <div class="container flex items-center justify-between">
                <span>Administração Municipal de Moçâmedes - Província do Namibe</span>
                <span class="desktop-only">Angola</span>
            </div>
        </div>

        <div class="container header-inner">
            <a class="logo" href="index.php">
                <div class="logo-icon"><img src="../assets/img/favicon.ico" alt=""></div>
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
                    <button class="btn btn-outline btn-sm" onclick="toggleUserDropdown()">
                        <?php echo htmlspecialchars(explode(' ', $nome_usuario)[0]); ?> ▼
                        <?php if($notificacoes_nao_lidas > 0): ?>
                            <span style="background: var(--destructive); color: white; border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.7rem; margin-left: 0.3rem;"><?php echo $notificacoes_nao_lidas; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="userDropdown" class="user-dropdown hidden">
                        <?php if($tipo_usuario == 'cidadao'): ?>
                            <a href="cidadao.php">👤 Meu Painel</a>
                            <a href="notificacoes.php">🔔 Notificações</a>
                            <a href="solicitar.php">📝 Nova Solicitação</a>
                            <a href="processo.php">📊 Meus Processos</a>
                        <?php elseif($tipo_usuario == 'funcionario'): ?>
                            <a href="funcionario.php">📋 Painel Funcionário</a>
                        <?php elseif($tipo_usuario == 'admin_municipal'): ?>
                            <a href="funcionario.php">📋 Painel Admin Municipal</a>
                        <?php elseif($tipo_usuario == 'admin_sistema'): ?>
                            <a href="admin.php">⚙️ Painel Admin Sistema</a>
                        <?php endif; ?>
                        <a href="perfil.php" class="active">👤 Meu Perfil</a>
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
                <a href="notificacoes.php">🔔 Notificações <?php if($notificacoes_nao_lidas > 0): ?>(<?php echo $notificacoes_nao_lidas; ?>)<?php endif; ?></a>
                <a href="solicitar.php">📝 Nova Solicitação</a>
                <a href="processo.php">📊 Meus Processos</a>
            <?php elseif($tipo_usuario == 'funcionario'): ?>
                <a href="funcionario.php">📋 Painel Funcionário</a>
            <?php elseif($tipo_usuario == 'admin_municipal'): ?>
                <a href="funcionario.php">📋 Painel Admin Municipal</a>
            <?php elseif($tipo_usuario == 'admin_sistema'): ?>
                <a href="admin.php">⚙️ Painel Admin Sistema</a>
            <?php endif; ?>
            <a href="perfil.php">👤 Meu Perfil</a>
            <a href="?logout=1" style="color: var(--destructive);">🚪 Terminar Sessão</a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main>
        <div class="perfil-container">
            
            <!-- Mensagens -->
            <?php if($mensagem): ?>
                <div class="mensagem-sucesso"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php endif; ?>
            
            <?php if($erro): ?>
                <div class="mensagem-erro"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <!-- Cabeçalho do Perfil -->
            <div class="perfil-header">
                <div class="perfil-avatar">
                    <?php if(!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                        <img src="<?php echo $usuario['foto']; ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="perfil-info">
                    <h1><?php echo htmlspecialchars($usuario['nome']); ?></h1>
                    <span class="badge <?php 
                        if($tipo_usuario == 'cidadao') echo 'badge-blue';
                        elseif($tipo_usuario == 'funcionario') echo 'badge-green';
                        elseif($tipo_usuario == 'admin_municipal') echo 'badge-purple';
                        elseif($tipo_usuario == 'admin_sistema') echo 'badge-primary';
                    ?>">
                        <?php 
                        if($tipo_usuario == 'cidadao') echo 'Cidadão';
                        elseif($tipo_usuario == 'funcionario') echo 'Funcionário';
                        elseif($tipo_usuario == 'admin_municipal') echo 'Administrador Municipal';
                        elseif($tipo_usuario == 'admin_sistema') echo 'Administrador do Sistema';
                        ?>
                    </span>
                    <p style="margin-top: 0.5rem; color: var(--muted-fg);">
                        <span>📅 Membro desde <?php echo date('d/m/Y', strtotime($usuario['data_registro'])); ?></span>
                        <span style="margin-left: 1rem;">🕐 Último acesso: <?php echo $ultimo_acesso; ?></span>
                    </p>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="perfil-stats">
                <?php if($tipo_usuario == 'cidadao'): ?>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_solicitacoes; ?></div>
                        <div class="label">Total de Solicitações</div>
                    </div>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_processos; ?></div>
                        <div class="label">Em Andamento</div>
                    </div>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_solicitacoes - $total_processos; ?></div>
                        <div class="label">Concluídos</div>
                    </div>
                <?php elseif($tipo_usuario == 'funcionario' || $tipo_usuario == 'admin_municipal'): ?>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_solicitacoes; ?></div>
                        <div class="label">Total Processos</div>
                    </div>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_processos; ?></div>
                        <div class="label">Pendentes</div>
                    </div>
                    <div class="stat-box">
                        <div class="numero"><?php echo date('d/m/Y'); ?></div>
                        <div class="label">Hoje</div>
                    </div>
                <?php elseif($tipo_usuario == 'admin_sistema'): ?>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_solicitacoes; ?></div>
                        <div class="label">Total Usuários</div>
                    </div>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_processos; ?></div>
                        <div class="label">Usuários Ativos</div>
                    </div>
                    <div class="stat-box">
                        <div class="numero"><?php echo $total_solicitacoes - $total_processos; ?></div>
                        <div class="label">Inativos</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tabs -->
            <div class="perfil-tabs">
                <button class="tab-btn active" onclick="openTab(event, 'dados-pessoais')">📋 Dados Pessoais</button>
                <button class="tab-btn" onclick="openTab(event, 'alterar-senha')">🔐 Alterar Senha</button>
                <button class="tab-btn" onclick="openTab(event, 'foto')">📸 Foto de Perfil</button>
                <?php if($tipo_usuario == 'cidadao'): ?>
                    <button class="tab-btn" onclick="openTab(event, 'minhas-solicitacoes')">📄 Minhas Solicitações</button>
                <?php endif; ?>
            </div>

            <!-- Tab: Dados Pessoais -->
            <div id="dados-pessoais" class="tab-pane active">
                <div class="card">
                    <div class="card-header">
                        <h3>Editar Dados Pessoais</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>Nome Completo</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Telefone</label>
                                <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>" placeholder="+244 000 000 000">
                            </div>
                            
                            <div class="form-group">
                                <label>Nº do Bilhete de Identidade</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['bi']); ?>" readonly disabled>
                                <small style="color: var(--muted-fg);">O BI não pode ser alterado</small>
                            </div>
                            
                            <button type="submit" name="atualizar_perfil" class="btn btn-primary">
                                Salvar Alterações
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: Alterar Senha -->
            <div id="alterar-senha" class="tab-pane">
                <div class="card">
                    <div class="card-header">
                        <h3>Alterar Palavra-passe</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>Senha Atual</label>
                                <input type="password" name="senha_atual" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Nova Senha</label>
                                <input type="password" name="nova_senha" class="form-control" minlength="6" required>
                                <small style="color: var(--muted-fg);">Mínimo de 6 caracteres</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Confirmar Nova Senha</label>
                                <input type="password" name="confirmar_senha" class="form-control" minlength="6" required>
                            </div>
                            
                            <button type="submit" name="alterar_senha" class="btn btn-primary">
                                Alterar Senha
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: Foto de Perfil -->
            <div id="foto" class="tab-pane">
                <div class="card">
                    <div class="card-header">
                        <h3>Foto de Perfil</h3>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; margin-bottom: 2rem;">
                            <div class="foto-preview">
                                <?php if(!empty($usuario['foto']) && file_exists($usuario['foto'])): ?>
                                    <img src="<?php echo $usuario['foto']; ?>" alt="Foto de perfil">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: var(--muted); display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                                        <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="foto-upload-area" onclick="document.getElementById('foto-input').click()">
                                <input type="file" id="foto-input" name="foto" accept="image/*" style="display: none;" onchange="this.form.submit()">
                                <span style="font-size: 2rem; display: block; margin-bottom: 0.5rem;">📸</span>
                                <p>Clique para selecionar uma imagem</p>
                                <small style="color: var(--muted-fg);">Formatos: JPG, PNG, GIF (max. 2MB)</small>
                            </div>
                            <button type="submit" name="upload_foto" style="display: none;" id="upload-btn">Upload</button>
                        </form>
                        
                        <?php if(!empty($usuario['foto'])): ?>
                            <div class="acoes-foto" style="margin-top: 1rem;">
                                <a href="?remover_foto=1" class="btn btn-outline btn-sm" onclick="return confirm('Tem certeza que deseja remover sua foto?')">
                                    🗑️ Remover Foto
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab: Minhas Solicitações (apenas para cidadãos) -->
            <?php if($tipo_usuario == 'cidadao'): ?>
            <div id="minhas-solicitacoes" class="tab-pane">
                <div class="card">
                    <div class="card-header">
                        <h3>Minhas Solicitações Recentes</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $sql_solic = "SELECT s.id, s.numero_processo, serv.nome as servico, s.estado, s.data_solicitacao 
                                     FROM solicitacoes s
                                     JOIN servicos serv ON s.servico_id = serv.id
                                     WHERE s.usuario_id = ?
                                     ORDER BY s.id DESC LIMIT 5";
                        $stmt_solic = $conexao->prepare($sql_solic);
                        $stmt_solic->bind_param("i", $usuario_id);
                        $stmt_solic->execute();
                        $solicitacoes = $stmt_solic->get_result();
                        ?>
                        
                        <?php if($solicitacoes->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Nº Processo</th>
                                            <th>Serviço</th>
                                            <th>Data</th>
                                            <th>Estado</th>
                                            <th>Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($s = $solicitacoes->fetch_assoc()): 
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
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['numero_processo']); ?></td>
                                            <td><?php echo htmlspecialchars($s['servico']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($s['data_solicitacao'])); ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo str_replace('_', ' ', $s['estado']); ?></span></td>
                                            <td><a href="processo.php?id=<?php echo $s['id']; ?>" class="btn btn-outline btn-sm">Ver</a></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="processo.php" class="btn btn-outline">Ver todas as solicitações</a>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--muted-fg);">Nenhuma solicitação encontrada.</p>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="solicitar.php" class="btn btn-primary">Fazer primeira solicitação</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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

        function openTab(event, tabId) {
            var i, tabPanes, tabBtns;
            
            tabPanes = document.getElementsByClassName("tab-pane");
            for (i = 0; i < tabPanes.length; i++) {
                tabPanes[i].classList.remove('active');
            }
            
            tabBtns = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tabBtns.length; i++) {
                tabBtns[i].classList.remove('active');
            }
            
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>
