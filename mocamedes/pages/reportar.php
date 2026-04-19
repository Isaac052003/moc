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

$page_title   = "Reportar Problema";
$active_nav   = strtolower("⚠️");
require_once("../includes/header.php");
?>

<main style="min-height:60vh; display:flex; align-items:center; justify-content:center;">
  <div class="container" style="text-align:center; padding: 4rem 1rem;">
    <div style="font-size:4rem; margin-bottom:1rem;">⚠️</div>
    <h2 style="font-size:2rem; margin-bottom:0.75rem;">Reportar Problema</h2>
    <p style="color:var(--muted-foreground); max-width:480px; margin:0 auto 2rem;">
      Esta funcionalidade está em desenvolvimento. Em breve poderá reportar problemas e ocorrências na sua comunidade.
    </p>
    <a href="/mocamedes/index.php" class="btn btn-primary">🏠 Voltar ao Início</a>
  </div>
</main>

<?php require_once("../includes/footer.php"); ?>
