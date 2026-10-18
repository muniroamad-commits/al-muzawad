<?php
/**
 * Endpoint de encomendas.
 * POST /encomendas.php                 -> cliente cria encomenda (checkout)
 * GET  /encomendas.php                 -> admin: lista todas | cliente: lista as suas
 * GET  /encomendas.php?id=5            -> detalhe de uma encomenda
 * POST /encomendas.php?id=5&_method=PUT -> admin actualiza status
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = getDB();
$metodo = $_SERVER['REQUEST_METHOD'];
$metodoReal = $_GET['_method'] ?? $metodo;
$id = $_GET['id'] ?? null;

function gerarCodigoEncomenda(PDO $db): string {
    $data = date('Ymd');
    $stmt = $db->query("SELECT COUNT(*) AS n FROM encomendas WHERE DATE(criado_em) = CURDATE()");
    $n = $stmt->fetch()['n'] + 1;
    return 'ENC-' . $data . '-' . str_pad($n, 4, '0', STR_PAD_LEFT);
}

switch ($metodoReal) {

    case 'POST':
        $cliente = exigirCliente();
        $d = json_decode(file_get_contents('php://input'), true);

        if (empty($d['itens']) || !is_array($d['itens'])) jsonError('O carrinho está vazio.');
        if (empty($d['endereco_entrega'])) jsonError('Endereço de entrega é obrigatório.');
        if (empty($d['metodo_pagamento'])) jsonError('Método de pagamento é obrigatório.');

        $metodosValidos = ['mpesa','emola','mkesh','cartao','entrega'];
        if (!in_array($d['metodo_pagamento'], $metodosValidos)) jsonError('Método de pagamento inválido.');

        $db->beginTransaction();
        try {
            $subtotal = 0;
            $itensValidados = [];

            foreach ($d['itens'] as $item) {
                $stmt = $db->prepare('SELECT * FROM produtos WHERE id = ? AND activo = 1 FOR UPDATE');
                $stmt->execute([$item['produto_id']]);
                $produto = $stmt->fetch();
                if (!$produto) throw new Exception('Produto não encontrado: ' . $item['produto_id']);

                $qtd = (int)$item['quantidade'];
                if ($qtd < 1) throw new Exception('Quantidade inválida.');
                if ($produto['stock'] < $qtd) throw new Exception('Stock insuficiente para: ' . $produto['nome']);

                $precoUnit = $produto['preco_promocional'] ?? $produto['preco'];
                $itemSubtotal = $precoUnit * $qtd;
                $subtotal += $itemSubtotal;

                $itensValidados[] = [
                    'produto_id' => $produto['id'],
                    'variante_id' => $item['variante_id'] ?? null,
                    'nome' => $produto['nome'],
                    'preco' => $precoUnit,
                    'qtd' => $qtd,
                    'subtotal' => $itemSubtotal,
                ];

                $db->prepare('UPDATE produtos SET stock = stock - ? WHERE id = ?')
                   ->execute([$qtd, $produto['id']]);
            }

            $taxaEntrega = $d['taxa_entrega'] ?? 0;
            $total = $subtotal + $taxaEntrega;
            $codigo = gerarCodigoEncomenda($db);

            $stmt = $db->prepare('INSERT INTO encomendas
                (cliente_id, codigo, subtotal, taxa_entrega, total, metodo_pagamento,
                 endereco_entrega, cidade_entrega, telefone_contacto, notas)
                VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $cliente['id'], $codigo, $subtotal, $taxaEntrega, $total, $d['metodo_pagamento'],
                $d['endereco_entrega'], $d['cidade_entrega'] ?? null,
                $d['telefone_contacto'] ?? null, $d['notas'] ?? null,
            ]);
            $encomendaId = $db->lastInsertId();

            $stmtItem = $db->prepare('INSERT INTO encomenda_itens
                (encomenda_id, produto_id, variante_id, nome_produto, preco_unitario, quantidade, subtotal)
                VALUES (?,?,?,?,?,?,?)');
            foreach ($itensValidados as $it) {
                $stmtItem->execute([
                    $encomendaId, $it['produto_id'], $it['variante_id'],
                    $it['nome'], $it['preco'], $it['qtd'], $it['subtotal'],
                ]);
            }

            $db->commit();

            jsonResponse([
                'id' => $encomendaId,
                'codigo' => $codigo,
                'total' => $total,
                'metodo_pagamento' => $d['metodo_pagamento'],
                'mensagem' => 'Encomenda criada. Prossiga para o pagamento.',
            ], 201);

        } catch (Exception $e) {
            $db->rollBack();
            jsonError($e->getMessage());
        }
        break;

    case 'GET':
        if ($id) {
            $payload = validarToken(pegarTokenHeader());
            if (!$payload) jsonError('Não autorizado.', 401);

            $stmt = $db->prepare('SELECT * FROM encomendas WHERE id = ?');
            $stmt->execute([$id]);
            $encomenda = $stmt->fetch();
            if (!$encomenda) jsonError('Encomenda não encontrada.', 404);

            if ($payload['tipo'] === 'cliente' && $encomenda['cliente_id'] != $payload['id']) {
                jsonError('Não autorizado.', 403);
            }

            $itens = $db->prepare('SELECT * FROM encomenda_itens WHERE encomenda_id = ?');
            $itens->execute([$id]);
            $encomenda['itens'] = $itens->fetchAll();

            jsonResponse($encomenda);

        } else {
            $payload = validarToken(pegarTokenHeader());
            if (!$payload) jsonError('Não autorizado.', 401);

            if ($payload['tipo'] === 'admin') {
                $status = $_GET['status'] ?? null;
                $sql = 'SELECT e.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone
                        FROM encomendas e JOIN clientes c ON c.id = e.cliente_id WHERE 1=1';
                $params = [];
                if ($status) { $sql .= ' AND e.status = ?'; $params[] = $status; }
                $sql .= ' ORDER BY e.criado_em DESC';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $db->prepare('SELECT * FROM encomendas WHERE cliente_id = ? ORDER BY criado_em DESC');
                $stmt->execute([$payload['id']]);
            }
            jsonResponse($stmt->fetchAll());
        }
        break;

    case 'PUT':
        exigirAdmin();
        if (!$id) jsonError('ID da encomenda em falta.');
        $d = json_decode(file_get_contents('php://input'), true);

        $statusValidos = ['pendente','pago','confirmado','em_preparacao','enviado','entregue','cancelado'];
        if (empty($d['status']) || !in_array($d['status'], $statusValidos)) {
            jsonError('Status inválido.');
        }

        $stmt = $db->prepare('UPDATE encomendas SET status = ? WHERE id = ?');
        $stmt->execute([$d['status'], $id]);

        jsonResponse(['mensagem' => 'Status da encomenda actualizado.']);
        break;

    default:
        jsonError('Método não suportado.', 405);
}
