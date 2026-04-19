<?php
// includes/header.php
// Variáveis esperadas: $page_title, $active_nav, $usuario_logado, $nome_usuario, $tipo_usuario, $notificacoes_nao_lidas
$page_title = $page_title ?? 'Portal do Moçamedense';
$active_nav = $active_nav ?? '';
$notificacoes_nao_lidas = $notificacoes_nao_lidas ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <base href="/mocamedes/">
<link rel="icon" href="assets/img/favicon2.png">
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <meta name="description" content="Portal digital da Administração Municipal de Moçâmedes. Solicite serviços, acompanhe processos e participe na sua comunidade.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/mocamedes/assets/css/style.css">
</head>
<body>

<!-- HEADER -->
<header class="header">
  <div class="container header-inner">

    <a class="logo" href="/mocamedes/index.php">
      <div class="logo-icon">
        <img src="/mocamedes/assets/img/favicon.ico" alt="Logo Moçâmedes">
      </div>
      <div class="logo-text">
        <h1>Portal do Moçamedense</h1>
        <p>Administração Municipal</p>
      </div>
    </a>

    <nav class="nav">
      <?php if($usuario_logado && in_array($tipo_usuario, ['funcionario','admin_municipal','admin_sistema'])): ?>
        <a href="/mocamedes/index.php" class="<?php echo $active_nav=='inicio'?'active':''; ?>">Início</a>
        <a href="/mocamedes/pages/noticias.php" class="<?php echo $active_nav=='noticias'?'active':''; ?>">Notícias</a>
      <?php else: ?>
        <a href="/mocamedes/index.php" class="<?php echo $active_nav=='inicio'?'active':''; ?>">Início</a>
        <a href="/mocamedes/pages/servicos.php" class="<?php echo $active_nav=='servicos'?'active':''; ?>">Serviços</a>
        <a href="/mocamedes/pages/reportar.php" class="<?php echo $active_nav=='reportar'?'active':''; ?>">Reportar</a>
        <a href="/mocamedes/pages/sugestoes.php" class="<?php echo $active_nav=='sugestoes'?'active':''; ?>">Sugestões</a>
        <a href="/mocamedes/pages/noticias.php" class="<?php echo $active_nav=='noticias'?'active':''; ?>">Notícias</a>
        <a href="/mocamedes/pages/processo.php" class="<?php echo $active_nav=='processo'?'active':''; ?>">Processos</a>
      <?php endif; ?>
    </nav>

    <div class="nav-auth">
      <?php if($usuario_logado): ?>
        <div class="user-menu">
          <button class="btn btn-outline btn-sm notification-badge" onclick="toggleUserDropdown()">
            👤 <?php echo htmlspecialchars(explode(' ', $nome_usuario)[0]); ?> ▾
            <?php if($notificacoes_nao_lidas > 0): ?>
              <span class="notification-count"><?php echo $notificacoes_nao_lidas; ?></span>
            <?php endif; ?>
          </button>
          <div id="userDropdown" class="user-dropdown hidden">
            <?php if($tipo_usuario == 'cidadao'): ?>
              <a href="/mocamedes/pages/cidadao.php">👤 Meu Painel</a>
              <a href="/mocamedes/pages/notificacoes.php">
                🔔 Notificações
                <?php if($notificacoes_nao_lidas > 0): ?>
                  <span class="badge badge-red" style="margin-left:auto"><?php echo $notificacoes_nao_lidas; ?></span>
                <?php endif; ?>
              </a>
              <a href="/mocamedes/pages/solicitar.php">📝 Nova Solicitação</a>
              <a href="/mocamedes/pages/processo.php">📊 Meus Processos</a>
            <?php elseif($tipo_usuario == 'funcionario'): ?>
              <a href="/mocamedes/pages/funcionario.php">📋 Painel Funcionário</a>
            <?php elseif($tipo_usuario == 'admin_municipal'): ?>
              <a href="/mocamedes/pages/funcionario.php">📋 Painel Admin Municipal</a>
            <?php elseif($tipo_usuario == 'admin_sistema'): ?>
              <a href="/mocamedes/pages/admin.php">⚙️ Painel Admin Sistema</a>
            <?php endif; ?>
            <a href="/mocamedes/pages/perfil.php">⚙️ Meu Perfil</a>
            <hr>
            <a href="/mocamedes/index.php?logout=1" style="color:var(--destructive)">🚪 Terminar Sessão</a>
          </div>
        </div>
      <?php else: ?>
        <a class="btn btn-outline btn-sm" href="/mocamedes/pages/login.php">Entrar</a>
        <a class="btn btn-primary btn-sm" href="/mocamedes/pages/cadastro.php">Cadastrar</a>
      <?php endif; ?>
    </div>

    <button class="mobile-toggle" aria-label="Menu">☰</button>
  </div>

  <!-- Mobile Nav -->
  <div class="mobile-nav hidden">
    <?php if($usuario_logado && in_array($tipo_usuario, ['funcionario','admin_municipal','admin_sistema'])): ?>
      <a href="/mocamedes/index.php">🏠 Início</a>
      <a href="/mocamedes/pages/noticias.php">📰 Notícias</a>
      <hr style="border:none;border-top:1px solid var(--border);margin:0.5rem 0">
      <?php if($tipo_usuario == 'funcionario'): ?>
        <a href="/mocamedes/pages/funcionario.php">📋 Painel Funcionário</a>
      <?php elseif($tipo_usuario == 'admin_municipal'): ?>
        <a href="/mocamedes/pages/funcionario.php">📋 Painel Admin Municipal</a>
      <?php elseif($tipo_usuario == 'admin_sistema'): ?>
        <a href="/mocamedes/pages/admin.php">⚙️ Painel Admin</a>
      <?php endif; ?>
      <a href="/mocamedes/pages/perfil.php">👤 Meu Perfil</a>
      <a href="/mocamedes/index.php?logout=1" style="color:var(--destructive)">🚪 Terminar Sessão</a>
    <?php elseif($usuario_logado): ?>
      <a href="/mocamedes/index.php">🏠 Início</a>
      <a href="/mocamedes/pages/servicos.php">📄 Serviços</a>
      <a href="/mocamedes/pages/reportar.php">⚠️ Reportar</a>
      <a href="/mocamedes/pages/sugestoes.php">💬 Sugestões</a>
      <a href="/mocamedes/pages/noticias.php">📰 Notícias</a>
      <a href="/mocamedes/pages/processo.php">🔍 Processos</a>
      <hr style="border:none;border-top:1px solid var(--border);margin:0.5rem 0">
      <a href="/mocamedes/pages/cidadao.php">👤 Meu Painel</a>
      <a href="/mocamedes/pages/notificacoes.php">🔔 Notificações <?php if($notificacoes_nao_lidas > 0): ?>(<?php echo $notificacoes_nao_lidas; ?>)<?php endif; ?></a>
      <a href="/mocamedes/pages/solicitar.php">📝 Nova Solicitação</a>
      <a href="/mocamedes/pages/perfil.php">⚙️ Meu Perfil</a>
      <a href="/mocamedes/index.php?logout=1" style="color:var(--destructive)">🚪 Terminar Sessão</a>
    <?php else: ?>
      <a href="/mocamedes/index.php">🏠 Início</a>
      <a href="/mocamedes/pages/servicos.php">📄 Serviços</a>
      <a href="/mocamedes/pages/reportar.php">⚠️ Reportar</a>
      <a href="/mocamedes/pages/sugestoes.php">💬 Sugestões</a>
      <a href="/mocamedes/pages/noticias.php">📰 Notícias</a>
      <a href="/mocamedes/pages/processo.php">🔍 Processos</a>
      <hr style="border:none;border-top:1px solid var(--border);margin:0.5rem 0">
      <div class="flex gap-2" style="margin-top:0.5rem">
        <a class="btn btn-outline btn-sm flex-1" href="/mocamedes/pages/login.php">Entrar</a>
        <a class="btn btn-primary btn-sm flex-1" href="/mocamedes/pages/cadastro.php">Cadastrar</a>
      </div>
    <?php endif; ?>
  </div>
</header>
