<?php
session_start();
require_once('../includes/conexao.php');
/**
 * =============================================
 * MUNI — LÓGICA DO CHAT (SEM HTML)
 * Portal do Moçamedense
 * =============================================
 * Este ficheiro processa APENAS os pedidos AJAX do assistente.
 * Não gera nenhum output HTML.
 *
 * Como funciona:
 *   cidadao.php (topo) → inclui este ficheiro → responde JSON → exit
 * =============================================
 */

// Segurança — só executa se houver sessão activa
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['resposta' => 'Sessão expirada. Por favor, faça login novamente.']);
    exit;
}

// Limpar qualquer output anterior
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// =============================================
// ⚙️  CONFIGURAÇÃO — EDITA AQUI
// =============================================
define('MUNI_WORKER_URL',   'https://muni-proxy.isaacjohnnycatropa.workers.dev');
define('MUNI_SECRET_TOKEN', ''); // opcional
// =============================================

$assistente_usuario_id   = (int)$_SESSION['usuario_id'];
$assistente_nome_usuario = $_SESSION['nome'] ?? 'Cidadão';

$mensagem = trim($_POST['mensagem'] ?? '');
if ($mensagem === '') {
    echo json_encode(['resposta' => 'Por favor, escreva a sua mensagem.']);
    exit;
}

// ── Estatísticas do cidadão ─────────────────────────────────
$st = $conexao->prepare("
    SELECT COUNT(*) AS total,
           SUM(estado='Levantamento') AS concluidas,
           SUM(estado!='Levantamento') AS andamento
    FROM solicitacoes WHERE usuario_id=?
");
$st->bind_param('i', $assistente_usuario_id);
$st->execute();
$stats = $st->get_result()->fetch_assoc();

// ── Lista de processos ──────────────────────────────────────
$st2 = $conexao->prepare("
    SELECT s.id, s.numero_processo, serv.nome AS servico,
           s.estado, s.data_atualizacao
    FROM solicitacoes s
    JOIN servicos serv ON s.servico_id = serv.id
    WHERE s.usuario_id = ?
    ORDER BY s.data_atualizacao DESC
    LIMIT 10
");
$st2->bind_param('i', $assistente_usuario_id);
$st2->execute();
$res = $st2->get_result();

$mapa = [
    'Pendente'     => 'Pendente',
    'Rececao'      => 'Recepção',
    'Area_tecnica' => 'Área Técnica',
    'Vistoria'     => 'Vistoria',
    'INOTU'        => 'INOTU',
    'Pagamento'    => 'Pagamento',
    'Levantamento' => 'Levantamento (Concluído)',
];

$lista = '';
while ($r = $res->fetch_assoc()) {
    $num  = $r['numero_processo'] ?? 'Aguarda atribuição';
    $est  = $mapa[$r['estado']] ?? $r['estado'];
    $lista .= "- Processo nº {$num} | Serviço: {$r['servico']} | Estado: {$est} | Última actualização: {$r['data_atualizacao']}\n";
}
if (!$lista) $lista = 'Nenhuma solicitação registada.';

$total      = (int)($stats['total']      ?? 0);
$concluidas = (int)($stats['concluidas'] ?? 0);
$andamento  = (int)($stats['andamento']  ?? 0);

// ── System prompt ───────────────────────────────────────────
$system = <<<SYS
Você é o MUNI, Assistente Virtual do Portal do Moçamedense — Administração Municipal de Moçâmedes, Província do Namibe, Angola.
Responda SEMPRE em Português de Angola, de forma clara, cordial e directa. Use "cidadão" para se referir ao utilizador.

=== CIDADÃO LOGADO ===
Nome: {$assistente_nome_usuario}
Total de solicitações: {$total} | Em andamento: {$andamento} | Concluídas: {$concluidas}

=== PROCESSOS ===
{$lista}

=== SERVIÇOS E DOCUMENTOS NECESSÁRIOS ===
1. REGULARIZAÇÃO DE TERRENO
   - Requerimento dirigido ao Administrador Municipal
   - Bilhete de Identidade (cópia legível)
   - Documento do Terreno (título, contrato de compra/venda, etc.)

2. AUTORIZAÇÃO PARA EVENTO
   - Requerimento dirigido ao Administrador Municipal
   - Bilhete de Identidade (cópia legível)

3. LICENÇA DE CONSTRUÇÃO
   - Requerimento dirigido ao Administrador Municipal
   - Bilhete de Identidade (cópia legível)
   - Documento do Terreno

=== FASES DO PROCESSO ===
Pendente → Recepção → Área Técnica → Vistoria → INOTU → Pagamento → Levantamento

- Pendente: Aguarda recepção pelo funcionário municipal.
- Recepção: Processo recebido, em verificação.
- Área Técnica: Em análise pela equipa técnica.
- Vistoria: Vistoria ao local agendada.
- INOTU: No Instituto Nacional de Ordenamento do Território e Urbanismo.
- Pagamento: Guia RUPE gerada — o cidadão deve efectuar o pagamento.
- Levantamento: Concluído. Pode levantar o documento na administração.

=== MODELO DE REQUERIMENTO ===
Quando pedido, usa EXACTAMENTE este modelo preenchendo com os dados do cidadão:

---

Ao Exmo. Sr.
Administrador Municipal de Moçâmedes

[NOME COMPLETO], portador do Bilhete de Identidade nº [NBI], residente em [MORADA], vem por meio deste requerimento solicitar a Vossa Excelência [DESCREVER O PEDIDO].

Termos em que,
Espera deferimento.

Atenciosamente,

Moçâmedes, [DATA]

_______________________
[NOME COMPLETO]

---

=== REGRAS ===
- Usa APENAS os dados reais acima para processos. NUNCA inventes números ou estados falsos.
- Se não tiveres informação suficiente, sugere contactar: A Administração Municipal de Moçâmedes ou ligar para +244 000 000 000
- Responde de forma concisa. Usa emojis com moderação.
SYS;

// ── Histórico ───────────────────────────────────────────────
$historico = json_decode($_POST['historico'] ?? '[]', true);
if (!is_array($historico)) $historico = [];
$messages   = array_slice($historico, -10);
$messages[] = ['role' => 'user', 'content' => $mensagem];

// ── Chamada ao Cloudflare Worker ────────────────────────────
$payload = json_encode(['system' => $system, 'messages' => $messages]);
$headers = ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)];
if (MUNI_SECRET_TOKEN !== '') {
    $headers[] = 'X-MUNI-Token: ' . MUNI_SECRET_TOKEN;
}

$ch = curl_init(MUNI_WORKER_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

if ($raw === false || $httpCode !== 200) {
    echo json_encode([
        'resposta' => "⚠️ Não foi possível obter resposta. Tente novamente.\n(Erro: " . ($err ?: "HTTP {$httpCode}") . ")"
    ]);
    exit;
}

$data = json_decode($raw, true);
if ($data === null || isset($data['erro'])) {
    echo json_encode(['resposta' => '⚠️ Erro na resposta do servidor. Tente novamente.']);
    exit;
}

echo json_encode(['resposta' => $data['resposta'] ?? 'Sem resposta do assistente.']);
exit;
