<?php
session_start();
require_once("../includes/conexao.php");

$usuario_logado = false;
$nome_usuario   = '';
$tipo_usuario   = '';
$notificacoes_nao_lidas = 0;

if (isset($_SESSION['usuario_id'])) {
    $usuario_logado = true;
    $nome_usuario   = $_SESSION['nome'];
    $tipo_usuario   = $_SESSION['tipo'];
    $sql_notif = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE";
    $stmt_notif = $conexao->prepare($sql_notif);
    $stmt_notif->bind_param("i", $_SESSION['usuario_id']);
    $stmt_notif->execute();
    $notificacoes_nao_lidas = $stmt_notif->get_result()->fetch_assoc()['total'];
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: servicos.php"); exit(); }

// Buscar serviços
$sql_servicos = "SELECT * FROM servicos WHERE ativo = 1 ORDER BY nome ASC";
$servicos = $conexao->query($sql_servicos);

$page_title = 'Serviços';
$active_nav = 'servicos';
require_once("../includes/header.php");
?>

<!-- PAGE HERO -->
<div class="page-hero">
  <div class="container">
    <p style="font-family:'Space Mono',monospace;font-size:var(--text-xs);letter-spacing:.15em;text-transform:uppercase;color:var(--accent-light);margin-bottom:.5rem">Portal do Moçamedense</p>
    <h1>Serviços Disponíveis</h1>
    <p>Solicite serviços administrativos de forma digital, rápida e segura</p>
  </div>
</div>

<!-- SERVICES -->
<section class="section">
  <div class="container">

    <?php if (!$usuario_logado): ?>
    <div class="alert alert-info" style="margin-bottom:2rem">
      💡 <strong>Dica:</strong> Para solicitar serviços, precisa de
      <a href="login.php" style="font-weight:700;color:var(--primary-light)">entrar na sua conta</a>
      ou <a href="cadastro.php" style="font-weight:700;color:var(--primary-light)">cadastrar-se gratuitamente</a>.
    </div>
    <?php endif; ?>

    <?php if ($servicos && $servicos->num_rows > 0): ?>
    <div class="grid grid-3">
      <?php while ($servico = $servicos->fetch_assoc()): ?>
      <div class="service-card animate-on-scroll">
        <span class="service-icon">📋</span>
        <h3 class="service-title"><?php echo htmlspecialchars($servico['nome']); ?></h3>
        <p class="service-desc"><?php echo htmlspecialchars($servico['descricao']); ?></p>

        <?php if (!empty($servico['documentos_necessarios'])): ?>
        <div class="service-docs">
          <?php
          $docs = explode(',', $servico['documentos_necessarios']);
          foreach ($docs as $doc):
            $doc = trim($doc);
            if ($doc):
          ?>
            <span class="badge badge-gray">📎 <?php echo htmlspecialchars($doc); ?></span>
          <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($servico['prazo_dias'])): ?>
        <p class="service-prazo">
          ⏱️ Prazo: <?php echo $servico['prazo_dias']; ?> dia(s) úteis
        </p>
        <?php endif; ?>

        <?php if ($usuario_logado && $tipo_usuario == 'cidadao'): ?>
          <a class="btn btn-primary btn-block" href="solicitar.php?servico_id=<?php echo $servico['id']; ?>" style="margin-top:1rem">
            📝 Solicitar este Serviço
          </a>
        <?php elseif (!$usuario_logado): ?>
          <a class="btn btn-outline btn-block" href="login.php" style="margin-top:1rem">
            Entrar para Solicitar
          </a>
        <?php endif; ?>
      </div>
      <?php endwhile; ?>
    </div>

    <?php else: ?>
    <div style="text-align:center;padding:5rem 0">
      <div style="font-size:4rem;margin-bottom:1.5rem">📋</div>
      <h3 style="font-size:var(--text-2xl);margin-bottom:0.75rem">Nenhum serviço disponível</h3>
      <p style="color:var(--muted-fg)">Os serviços serão disponibilizados em breve.</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php require_once("../includes/footer.php"); ?>
