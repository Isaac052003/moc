<?php
// =========================================
// CONEXÃO COM O BANCO DE DADOS
// =========================================

$host = "localhost";
$user = "root";
$pass = "";
$db   = "mocamedes";

$conexao = new mysqli($host, $user, $pass, $db);

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}

$conexao->set_charset("utf8");

// =========================================
// FUNÇÕES AUXILIARES GLOBAIS
// =========================================

if (!function_exists('formatarKwanzas')) {
    function formatarKwanzas($valor) {
        return number_format($valor, 2, ',', '.') . ' Kz';
    }
}

if (!function_exists('formatarDataAngolana')) {
    function formatarDataAngolana($data) {
        if(empty($data)) return '';
        return date('d/m/Y', strtotime($data));
    }
}

if (!function_exists('formatarDataHoraAngolana')) {
    function formatarDataHoraAngolana($data) {
        if(empty($data)) return '';
        return date('d/m/Y H:i', strtotime($data));
    }
}

if (!function_exists('formatarEstadoParaExibicao')) {
    function formatarEstadoParaExibicao($estado) {
        $mapa = [
            'Pendente' => 'Pendente',
            'Rececao' => 'Recepção',
            'Area_tecnica' => 'Área Técnica',
            'Vistoria' => 'Vistoria',
            'INOTU' => 'INOTU',
            'Pagamento' => 'Pagamento',
            'Levantamento' => 'Levantamento'
        ];
        return $mapa[$estado] ?? $estado;
    }
}

if (!function_exists('getBadgeEstado')) {
    function getBadgeEstado($estado) {
        switch($estado){
            case 'Pendente': return 'badge-yellow';
            case 'Rececao': return 'badge-blue';
            case 'Area_tecnica': return 'badge-purple';
            case 'Vistoria': return 'badge-orange';
            case 'INOTU': return 'badge-primary';
            case 'Pagamento': return 'badge-green';
            case 'Levantamento': return 'badge-secondary';
            default: return 'badge-gray';
        }
    }
}

if (!function_exists('gerarNumeroProcesso')) {
    function gerarNumeroProcesso($conexao, $usuario_id) {
        $ano = date('Y');
        $mes = date('m');
        
        $sql_seq = "SELECT COUNT(*) as total FROM solicitacoes WHERE YEAR(data_solicitacao) = ? AND numero_processo IS NOT NULL";
        $stmt_seq = $conexao->prepare($sql_seq);
        $stmt_seq->bind_param("i", $ano);
        $stmt_seq->execute();
        $seq = $stmt_seq->get_result()->fetch_assoc()['total'] + 1;
        
        return $ano . $mes . str_pad($usuario_id, 3, '0', STR_PAD_LEFT) . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}

// =========================================
// FUNÇÕES RUPE (verificar se tabela existe)
// =========================================

// Verificar se a tabela guias_pagamento existe
$tabela_rupe_existe = false;
$result = $conexao->query("SHOW TABLES LIKE 'guias_pagamento'");
if($result && $result->num_rows > 0){
    $tabela_rupe_existe = true;
}

// Só definir funções RUPE se a tabela existir
if ($tabela_rupe_existe) {
    if (!function_exists('gerarRUPE')) {
        function gerarRUPE($conexao, $solicitacao_id, $valor = 0) {
            $ano = date('y');
            $mes = date('m');
            $dia = date('d');
            
            $id_formatado = str_pad($solicitacao_id, 4, '0', STR_PAD_LEFT);
            $aleatorio = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            
            $base = $ano . $mes . $dia . $id_formatado . $aleatorio;
            $soma = 0;
            for($i = 0; $i < strlen($base); $i++) {
                $soma += intval($base[$i]);
            }
            $digito = $soma % 10;
            
            $rupe = sprintf("%02d%02d%02d%04d%04d%d", 
                $ano, $mes, $dia, $solicitacao_id, $aleatorio, $digito);
            
            // Verificar duplicidade
            $verificar = $conexao->prepare("SELECT id FROM guias_pagamento WHERE rupe = ?");
            $verificar->bind_param("s", $rupe);
            $verificar->execute();
            $verificar->store_result();
            
            if($verificar->num_rows > 0) {
                return gerarRUPE($conexao, $solicitacao_id, $valor);
            }
            
            return $rupe;
        }
    }
    
    if (!function_exists('formatarRUPE')) {
        function formatarRUPE($rupe) {
            return substr($rupe, 0, 4) . ' ' . 
                   substr($rupe, 4, 4) . ' ' . 
                   substr($rupe, 8, 4) . ' ' . 
                   substr($rupe, 12, 4) . ' ' . 
                   substr($rupe, 16, 1);
        }
    }
    
    if (!function_exists('calcularDataLimitePagamento')) {
        function calcularDataLimitePagamento($dias = 30) {
            return date('Y-m-d', strtotime("+$dias days"));
        }
    }
} else {
    // Funções placeholder caso a tabela não exista
    if (!function_exists('gerarRUPE')) {
        function gerarRUPE($conexao, $solicitacao_id, $valor = 0) {
            return 'TABELA_NAO_EXISTE';
        }
    }
    
    if (!function_exists('formatarRUPE')) {
        function formatarRUPE($rupe) {
            return $rupe;
        }
    }
}

// =========================================
// OUTRAS FUNÇÕES
// =========================================

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// =========================================
// CONFIGURAÇÕES
// =========================================
date_default_timezone_set('Africa/Luanda');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug (descomentar se necessário)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
?>