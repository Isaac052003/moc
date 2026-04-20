<?php
session_start();
require_once("includes/conexao.php");

// ============== VERIFICAR SESSÃO ==============
$usuario_logado = false;
$nome_usuario   = '';
$tipo_usuario   = '';

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

// ============== LOGOUT ==============
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// ============== ESTATÍSTICAS ==============
$stats = [];

$result = $conexao->query("SELECT COUNT(*) as total FROM servicos WHERE ativo = 1");
$stats['servicos'] = $result ? $result->fetch_assoc()['total'] : 13;

$result = $conexao->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'cidadao'");
$stats['cidadaos'] = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conexao->query("SELECT COUNT(*) as total FROM solicitacoes WHERE estado = 'Levantamento'");
$total_resolvidos = $result ? $result->fetch_assoc()['total'] : 0;
$result = $conexao->query("SELECT COUNT(*) as total FROM solicitacoes");
$total_processos = $result ? $result->fetch_assoc()['total'] : 1;
$stats['resolvidos'] = $total_processos > 0 ? round(($total_resolvidos / $total_processos) * 100) : 98;
$stats['satisfacao'] = 4.8;

// ============== NOTÍCIAS ==============
$sql_noticias = "SELECT id, titulo, resumo, imagem, data_publicacao FROM noticias ORDER BY data_publicacao DESC LIMIT 3";
$noticias = $conexao->query($sql_noticias);

// ============== PAGE VARS ==============
$page_title = 'Portal do Moçamedense';
$active_nav = 'inicio';

require_once("includes/header.php");
?>

<!-- ======================== HERO ======================== -->
<section class="hero">

  <!-- Animated Canvas -->
  <canvas id="heroCanvas" class="hero-canvas" style="position:absolute;inset:0;width:100%;height:100%;z-index:0"></canvas>

  <!-- Gradient -->
  <div class="hero-gradient"></div>

  <!-- Orbs -->
  <div class="hero-particles">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="orb orb-4"></div>
  </div>

  <!-- Stars -->
  <div class="hero-stars" id="heroStars"></div>

  <!-- Image overlay -->
  <div class="hero-img-overlay"></div>

  <!-- Geometric SVG lines -->
  <div class="hero-lines">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
      <line x1="0" y1="200" x2="1440" y2="600" stroke="white" stroke-width="1"/>
      <line x1="1440" y1="100" x2="0" y2="700" stroke="white" stroke-width="1"/>
      <circle cx="720" cy="450" r="350" fill="none" stroke="white" stroke-width="1"/>
      <circle cx="720" cy="450" r="200" fill="none" stroke="white" stroke-width="0.5"/>
      <polygon points="720,100 1200,700 240,700" fill="none" stroke="white" stroke-width="0.5"/>
    </svg>
  </div>

  <!-- Content -->
  <div class="container">
    <div class="hero-content">

      <div class="hero-eyebrow">
        <span class="dot"></span>
        Administração Municipal de Moçâmedes
        <span class="dot"></span>
      </div>

      <h2 class="hero-title">
        Portal do<br>
        <span class="highlight">Moçamedense</span>
      </h2>

      <p class="hero-subtitle">
        Modernizando os serviços públicos da Administração Municipal.
        Solicite serviços, acompanhe processos e participe activamente na construção da sua comunidade.
      </p>

      <div class="hero-actions">
        <a class="btn btn-accent btn-xl" href="pages/servicos.php">
          📄 Explorar Serviços
        </a>
        <?php if (!$usuario_logado): ?>
          <a class="btn btn-outline-light btn-xl" href="pages/cadastro.php">
            ✨ Criar Conta Grátis
          </a>
        <?php else: ?>
          <?php
            $painel_url = 'pages/cidadao.php';
            if ($tipo_usuario == 'funcionario' || $tipo_usuario == 'admin_municipal') $painel_url = 'pages/funcionario.php';
            if ($tipo_usuario == 'admin_sistema') $painel_url = 'pages/admin.php';
          ?>
          <a class="btn btn-outline-light btn-xl" href="<?php echo $painel_url; ?>">
            📊 Meu Painel
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Scroll indicator -->
  <div class="hero-scroll">
    <div class="scroll-mouse"><div class="scroll-wheel"></div></div>
    <span>Scroll</span>
  </div>

</section>


<!-- ======================== QUICK ACCESS ======================== -->
<section class="quick-access-section">
  <div class="container">
    <div class="quick-grid">

      <a class="quick-card blue animate-on-scroll" href="pages/servicos.php">
        <div class="quick-icon blue">📄</div>
        <div>
          <h3>Serviços</h3>
          <p>Solicite serviços administrativos online, de forma rápida e segura.</p>
        </div>
        <span class="quick-arrow">Ver serviços →</span>
      </a>

      <a class="quick-card red animate-on-scroll animate-delay-1" href="pages/reportar.php">
        <div class="quick-icon red">⚠️</div>
        <div>
          <h3>Reportar Problema</h3>
          <p>Identifique e reporte problemas na cidade para resolução imediata.</p>
        </div>
        <span class="quick-arrow">Reportar →</span>
      </a>

      <a class="quick-card green animate-on-scroll animate-delay-2" href="pages/sugestoes.php">
        <div class="quick-icon green">💬</div>
        <div>
          <h3>Sugestões</h3>
          <p>A sua opinião importa. Envie sugestões para melhorar o município.</p>
        </div>
        <span class="quick-arrow">Sugerir →</span>
      </a>

      <a class="quick-card gold animate-on-scroll animate-delay-3" href="pages/processo.php">
        <div class="quick-icon gold">🔍</div>
        <div>
          <h3>Acompanhar Processo</h3>
          <p>Consulte o estado dos seus pedidos e documentos em tempo real.</p>
        </div>
        <span class="quick-arrow">Consultar →</span>
      </a>

    </div>
  </div>
</section>


<!-- ======================== STATS ======================== -->
<section class="stats-section" style="margin-top:4rem">
  <div class="container">
    <div class="stats-grid">

      <div class="stat-item animate-on-scroll">
        <span class="stat-icon">🏛️</span>
        <div class="stat-value" data-count="<?php echo $stats['servicos']; ?>" data-suffix="">0</div>
        <div class="stat-label">Serviços Disponíveis</div>
      </div>

      <div class="stat-item animate-on-scroll animate-delay-1">
        <span class="stat-icon">👥</span>
        <div class="stat-value" data-count="<?php echo $stats['cidadaos']; ?>" data-suffix="+">0+</div>
        <div class="stat-label">Cidadãos Registados</div>
      </div>

      <div class="stat-item animate-on-scroll animate-delay-2">
        <span class="stat-icon">✅</span>
        <div class="stat-value" data-count="<?php echo $stats['resolvidos']; ?>" data-suffix="%">0%</div>
        <div class="stat-label">Pedidos Resolvidos</div>
      </div>

      <div class="stat-item animate-on-scroll animate-delay-3">
        <span class="stat-icon">⭐</span>
        <div class="stat-value" data-count="<?php echo $stats['satisfacao']; ?>" data-suffix="/5">0/5</div>
        <div class="stat-label">Satisfação dos Cidadãos</div>
      </div>

    </div>
  </div>
</section>


<!-- ======================== SOBRE ======================== -->
<section class="about-section">
  <div class="container">
    <div class="about-grid">

      <!-- Visual -->
      <div class="about-visual animate-on-scroll">
        <div class="about-corner"></div>
        <img
          src="assets/img/img1.jpg"
          alt="Moçâmedes"
          class="about-img"
          onerror="this.parentElement.style.background='linear-gradient(135deg,#0a2540,#1a5fa8)'"
        >
        <div class="about-badge-float">
          <span class="num">2026</span>
          <span class="lbl">Portal Digital</span>
        </div>
      </div>

      <!-- Text -->
      <div class="animate-on-scroll animate-delay-1">
        <p class="about-eyebrow">Sobre o Portal</p>
        <h2 class="about-title">Serviços Públicos ao alcance de todos</h2>
        <p class="about-body">
          O Portal do Moçamedense é a plataforma digital oficial da Administração Municipal de Moçâmedes.
          Criada para modernizar e simplificar o acesso aos serviços públicos, esta plataforma permite aos
          cidadãos interagir com a administração local de forma rápida, transparente e eficiente.
        </p>

        <div class="about-features">
          <div class="about-feature">
            <span class="feature-check">✓</span>
            Solicitação de serviços administrativos online, 24h por dia
          </div>
          <div class="about-feature">
            <span class="feature-check">✓</span>
            Acompanhamento em tempo real do estado dos seus processos
          </div>
          <div class="about-feature">
            <span class="feature-check">✓</span>
            Reporte de problemas e envio de sugestões de forma fácil
          </div>
          <div class="about-feature">
            <span class="feature-check">✓</span>
            Notificações automáticas sobre as suas solicitações
          </div>
          <div class="about-feature">
            <span class="feature-check">✓</span>
            Gestão de documentos e guias de pagamento digitais
          </div>
        </div>

        <div class="flex gap-3 flex-wrap">
          <a class="btn btn-primary btn-lg" href="pages/servicos.php">Ver Todos os Serviços</a>
          <a class="btn btn-outline btn-lg" href="pages/cadastro.php">Criar Conta</a>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ======================== NOTÍCIAS ======================== -->
<?php
$has_noticias = $noticias && $noticias->num_rows > 0;
if ($has_noticias):
?>
<section class="section" style="background:var(--bg-dark)">
  <div class="container">

    <div class="section-header-row animate-on-scroll">
      <div>
        <p class="section-eyebrow" style="justify-content:flex-start">
          Actualidade
        </p>
        <h2 class="section-title">Últimas Notícias</h2>
      </div>
      <a class="btn btn-outline" href="pages/noticias.php">Ver todas →</a>
    </div>

    <div class="grid grid-3">
      <?php
      $i = 0;
      while ($noticia = $noticias->fetch_assoc()):
        $data_fmt = date('d M Y', strtotime($noticia['data_publicacao']));
      ?>
      <div class="news-card animate-on-scroll animate-delay-<?php echo $i; ?>">
        <?php if (!empty($noticia['imagem']) && file_exists('uploads/noticias/'.$noticia['imagem'])): ?>
          <img src="uploads/noticias/<?php echo htmlspecialchars($noticia['imagem']); ?>" alt="" class="news-img">
        <?php else: ?>
          <div class="news-img-placeholder">📰</div>
        <?php endif; ?>
        <div class="news-body">
          <div class="news-meta">
            <span class="badge badge-primary">Notícia</span>
            <span class="news-date"><?php echo $data_fmt; ?></span>
          </div>
          <h3 class="news-title"><?php echo htmlspecialchars($noticia['titulo']); ?></h3>
          <p class="news-summary"><?php echo htmlspecialchars(mb_substr($noticia['resumo'], 0, 120)); ?>...</p>
          <a href="pages/noticias.php?id=<?php echo $noticia['id']; ?>" class="news-link">
            Ler mais →
          </a>
        </div>
      </div>
      <?php $i++; endwhile; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ======================== CTA ======================== -->
<?php if (!$usuario_logado): ?>
<section class="section">
  <div class="container">
    <div class="card" style="background:linear-gradient(135deg,var(--primary) 0%,var(--primary-mid) 100%);border:none;padding:3.5rem;text-align:center;color:white;position:relative;overflow:hidden">
      <div style="position:absolute;inset:0;background:radial-gradient(ellipse 60% 80% at 50% 50%,rgba(45,140,240,0.2) 0%,transparent 70%);pointer-events:none"></div>
      <div style="position:relative;z-index:1">
        <p style="font-family:'Space Mono',monospace;font-size:var(--text-xs);letter-spacing:.15em;text-transform:uppercase;color:var(--accent-light);margin-bottom:1rem">Comece Hoje</p>
        <h2 style="font-size:var(--text-4xl);margin-bottom:1rem;color:white">Junte-se ao Portal do Moçamedense</h2>
        <p style="color:rgba(255,255,255,0.7);max-width:520px;margin:0 auto 2rem;line-height:1.7">
          Registe-se gratuitamente e tenha acesso a todos os serviços da Administração Municipal de forma digital e conveniente.
        </p>
        <div class="flex gap-3 justify-center flex-wrap">
          <a class="btn btn-accent btn-xl" href="pages/cadastro.php">✨ Criar Conta Grátis</a>
          <a class="btn btn-outline-light btn-xl" href="pages/servicos.php">📄 Ver Serviços</a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>


<?php require_once("includes/footer.php"); ?>

