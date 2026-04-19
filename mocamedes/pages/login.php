<?php
session_start();
require_once("../includes/conexao.php");

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $sql  = "SELECT id, nome, senha, tipo, estado FROM usuarios WHERE email = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
        if ($usuario['estado'] != 'ativo') {
            $erro = "Conta inativa. Contate o administrador.";
        } elseif ($senha === $usuario['senha']) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nome']       = $usuario['nome'];
            $_SESSION['tipo']       = $usuario['tipo'];

            switch ($usuario['tipo']) {
                case 'cidadao':
                    header("Location: cidadao.php"); break;
                case 'funcionario':
                case 'comunicacao':
                    header("Location: funcionario.php"); break;
                case 'admin_municipal':
                    header("Location: funcionario.php"); break;
                case 'admin_sistema':
                    header("Location: admin.php"); break;
                default:
                    header("Location: ../index.php"); break;
            }
            exit();
        } else {
            $erro = "Palavra-passe incorrecta.";
        }
    } else {
        $erro = "Utilizador não encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="../assets/img/favicon.ico" type="image/x-icon">
  <title>Entrar — Portal do Moçamedense</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="auth-page">

  <!-- Form Panel -->
  <div class="auth-form-panel">
    <div class="auth-form-inner">

      <a class="auth-logo" href="../index.php">
        <div class="logo-icon" style="background:var(--primary)">
          <img src="../assets/img/favicon.ico" alt="" style="width:24px;filter:brightness(10)">
        </div>
        <div class="logo-text">
          <h1 style="color:var(--primary);font-size:1rem">Portal do Moçamedense</h1>
          <p>Administração Municipal</p>
        </div>
      </a>

      <h2 class="auth-form-title">Entrar na conta</h2>
      <p class="auth-form-sub">Aceda com o seu email e palavra-passe</p>

      <?php if ($erro): ?>
        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($erro); ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="on">

        <div class="form-group">
          <label for="email">Endereço de Email</label>
          <input
            type="email"
            name="email"
            id="email"
            class="form-control"
            placeholder="seu@email.ao"
            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
            required
            autocomplete="email"
          >
        </div>

        <div class="form-group">
          <label for="senha">Palavra-passe</label>
          <div style="position:relative">
            <input
              type="password"
              name="senha"
              id="senha"
              class="form-control"
              placeholder="••••••••"
              required
              autocomplete="current-password"
              style="padding-right:3rem"
            >
            <button type="button" onclick="toggleSenha('senha','toggleIcon')" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted-fg);font-size:1.1rem" id="toggleIcon">👁️</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:0.5rem">
          Entrar na Conta →
        </button>

      </form>

      <div class="auth-divider">ou</div>

      <a class="btn btn-outline btn-block" href="../index.php">
        🏠 Voltar ao Portal
      </a>

      <p class="auth-footer">
        Ainda não tem conta?
        <a href="cadastro.php">Cadastrar-se gratuitamente</a>
      </p>

    </div>
  </div>

</div>

<script>
function toggleSenha(inputId, btnId) {
  const inp = document.getElementById(inputId);
  const btn = document.getElementById(btnId);
  if (inp.type === 'password') {
    inp.type = 'text';
    btn.textContent = '🙈';
  } else {
    inp.type = 'password';
    btn.textContent = '👁️';
  }
}
</script>
</body>
</html>
