<?php
/**
 * Endpoint de iniciação de pagamento.
 * POST /pagamentos.php   { encomenda_id, metodo, numero_telefone? }
 *
 * IMPORTANTE: Este ficheiro contém a ESTRUTURA pronta para ligar aos
 * gateways reais. As funções processarMpesa/processarEmola/processarMkesh/
 * processarCartao são placeholders — quando tiveres as credenciais
 * comerciais (conta M-Pesa Business API, e-Mola, mKesh ou um gateway de
 * cartão como PaySuite), substituis o conteúdo dessas funções pela
 * chamada real à API do agregador. Por agora simulam sucesso para que
 * possas testar o fluxo completo da loja.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método não suportado.', 405);

$cliente = exigirCliente();
$d = json_decode(file_get_contents('php://input'), true);

$encomendaId = $d['encomenda_id'] ?? null;
$metodo = $d['metodo'] ?? null;

if (!$encomendaId || !$metodo) jsonError('encomenda_id e metodo são obrigatórios.');

$db = getDB();
$stmt = $db->prepare('SELECT * FROM encomendas WHERE id = ? AND cliente_id = ?');
$stmt->execute([$encomendaId, $cliente['id']]);
$encomenda = $stmt->fetch();
if (!$encomenda) jsonError('Encomenda não encontrada.', 404);

if ($encomenda['estado_pagamento'] === 'pago') {
    jsonError('Esta encomenda já foi paga.');
}

// --- Encaminha para o processador correcto ---
switch ($metodo) {
    case 'mpesa':
        $resultado = processarMpesa($encomenda, $d);
        break;
    case 'emola':
        $resultado = processarEmola($encomenda, $d);
        break;
    case 'mkesh':
        $resultado = processarMkesh($encomenda, $d);
        break;
    case 'cartao':
        $resultado = processarCartao($encomenda, $d);
        break;
    case 'entrega':
        // Pagamento na entrega — não processa nada agora, só confirma a encomenda
        $resultado = ['sucesso' => true, 'referencia' => 'PAGAMENTO_NA_ENTREGA', 'bruto' => null];
        break;
    default:
        jsonError('Método de pagamento inválido.');
}

// --- Regista a transacção ---
$stmtPag = $db->prepare('INSERT INTO pagamentos (encomenda_id, metodo, valor, estado, resposta_gateway) VALUES (?,?,?,?,?)');
$stmtPag->execute([
    $encomendaId, $metodo, $encomenda['total'],
    $resultado['sucesso'] ? 'pago' : 'falhado',
    json_encode($resultado['bruto']),
]);

if ($resultado['sucesso']) {
    $novoEstadoEncomenda = $metodo === 'entrega' ? 'confirmado' : 'pago';
    $db->prepare('UPDATE encomendas SET estado_pagamento = ?, referencia_pagamento = ?, status = ? WHERE id = ?')
       ->execute(['pago', $resultado['referencia'], $novoEstadoEncomenda, $encomendaId]);

    jsonResponse([
        'sucesso' => true,
        'mensagem' => 'Pagamento confirmado.',
        'referencia' => $resultado['referencia'],
    ]);
} else {
    $db->prepare('UPDATE encomendas SET estado_pagamento = ? WHERE id = ?')
       ->execute(['falhado', $encomendaId]);
    jsonError($resultado['mensagem'] ?? 'Falha no processamento do pagamento.', 402);
}

// ============================================================
// FUNÇÕES DE INTEGRAÇÃO — substituir pelo código real do gateway
// ============================================================

function processarMpesa(array $encomenda, array $dados): array {
    // TODO: Chamar a API M-Pesa (ex: via agregador PaySuite ou API directa Vodacom)
    // Precisas de: numero_telefone, chave da API, PIN/certificado do comerciante
    if (empty($dados['numero_telefone'])) {
        return ['sucesso' => false, 'mensagem' => 'Número de telefone M-Pesa é obrigatório.'];
    }
    // --- SIMULAÇÃO (remover quando integrar API real) ---
    return ['sucesso' => true, 'referencia' => 'MPESA-' . strtoupper(uniqid()), 'bruto' => ['simulado' => true]];
}

function processarEmola(array $encomenda, array $dados): array {
    // TODO: Chamar a API e-Mola
    if (empty($dados['numero_telefone'])) {
        return ['sucesso' => false, 'mensagem' => 'Número de telefone e-Mola é obrigatório.'];
    }
    return ['sucesso' => true, 'referencia' => 'EMOLA-' . strtoupper(uniqid()), 'bruto' => ['simulado' => true]];
}

function processarMkesh(array $encomenda, array $dados): array {
    // TODO: Chamar a API mKesh
    if (empty($dados['numero_telefone'])) {
        return ['sucesso' => false, 'mensagem' => 'Número de telefone mKesh é obrigatório.'];
    }
    return ['sucesso' => true, 'referencia' => 'MKESH-' . strtoupper(uniqid()), 'bruto' => ['simulado' => true]];
}

function processarCartao(array $encomenda, array $dados): array {
    // TODO: Integrar gateway de cartão (ex: PaySuite, Stripe se disponível, etc.)
    if (empty($dados['token_cartao'])) {
        return ['sucesso' => false, 'mensagem' => 'Dados do cartão em falta.'];
    }
    return ['sucesso' => true, 'referencia' => 'CARTAO-' . strtoupper(uniqid()), 'bruto' => ['simulado' => true]];
}
