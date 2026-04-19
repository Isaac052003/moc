<?php
require_once("../includes/conexao.php");

$mensagem = "";
$tipo_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome     = trim($_POST["nome"] ?? '');
    $email    = trim($_POST["email"] ?? '');
    $bi       = trim($_POST["bilhete"] ?? '');
    $senha    = $_POST["senha"] ?? '';
    $confirmar = $_POST["confirmar_senha"] ?? '';

    if ($senha != $confirmar) {
        $mensagem = "As palavras-passe não coincidem.";
        $tipo_msg = "error";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A palavra-passe deve ter pelo menos 6 caracteres.";
        $tipo_msg = "error";
    } else {
        $verificar = $conexao->prepare("SELECT id FROM usuarios WHERE email=? OR bi=?");
        $verificar->bind_param("ss", $email, $bi);
        $verificar->execute();
        if ($verificar->get_result()->num_rows > 0) {
            $mensagem = "Email ou Bilhete de Identidade já cadastrado.";
            $tipo_msg = "error";
        } else {
            $stmt = $conexao->prepare("INSERT INTO usuarios (nome,email,bi,senha,tipo) VALUES (?,?,?,?,'cidadao')");
            $stmt->bind_param("ssss", $nome, $email, $bi, $senha);
            if ($stmt->execute()) {
                $mensagem = "Conta criada com sucesso! Já pode entrar no portal.";
                $tipo_msg = "success";
            } else {
                $mensagem = "Erro ao cadastrar. Tente novamente.";
                $tipo_msg = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="../assets/img/favicon.ico" type="image/x-icon">
  <title>Cadastro — Portal do Moçamedense</title>
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

      <h2 class="auth-form-title">Criar conta</h2>
      <p class="auth-form-sub">Preencha os seus dados para se registar</p>

      <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $tipo_msg === 'success' ? 'success' : 'error'; ?>">
          <?php echo $tipo_msg === 'success' ? '✅' : '⚠️'; ?>
          <?php echo htmlspecialchars($mensagem); ?>
          <?php if ($tipo_msg === 'success'): ?>
            <a href="login.php" style="font-weight:700;margin-left:.5rem">Entrar agora →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($tipo_msg !== 'success'): ?>
      <form method="POST" autocomplete="off">

        <div class="form-group">
          <label for="nome">Nome Completo</label>
          <input
            type="text"
            name="nome"
            id="nome"
            class="form-control"
            placeholder="Ex: João Manuel Silva"
            value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>"
            required
          >
        </div>

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
          >
        </div>

        <div class="form-group">
          <label for="bilhete">Bilhete de Identidade</label>
          <input
            type="text"
            name="bilhete"
            id="bilhete"
            class="form-control"
            placeholder="Número do BI"
            value="<?php echo htmlspecialchars($_POST['bilhete'] ?? ''); ?>"
            required
          >
          <p class="form-hint">O BI é necessário para validar a sua identidade</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label for="senha">Palavra-passe</label>
            <input
              type="password"
              name="senha"
              id="senha"
              class="form-control"
              placeholder="Mínimo 6 caracteres"
              required
              oninput="avaliarSenha(this.value)"
            >
            <p id="forca-senha" class="form-hint"></p>
          </div>

          <div class="form-group">
            <label for="confirmar_senha">Confirmar Palavra-passe</label>
            <input
              type="password"
              name="confirmar_senha"
              id="confirmar_senha"
              class="form-control"
              placeholder="Repita a senha"
              required
            >
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          Criar Conta Grátis ✨
        </button>

      </form>
      <?php endif; ?>

      <p class="auth-footer">
        Já tem conta?
        <a href="login.php">Entrar agora</a>
      </p>

      <p class="auth-footer" style="margin-top:.5rem">
        <a href="../index.php" style="color:var(--muted-fg)">← Voltar ao Portal</a>
      </p>

    </div>
  </div>

</div>

<script>
function avaliarSenha(senha) {
  const el = document.getElementById('forca-senha');
  if (!el) return;
  if (senha.length === 0) { el.textContent = ''; el.className = 'form-hint'; return; }
  if (senha.length < 6) { el.textContent = '⚠️ Muito curta (mínimo 6 caracteres)'; el.className = 'senha-fraca'; }
  else if (senha.length < 10 || !/[0-9]/.test(senha)) { el.textContent = '🟡 Força média'; el.className = 'senha-media'; }
  else { el.textContent = '✅ Palavra-passe forte'; el.className = 'senha-forte'; }
}
</script>
</body>
</html>
