<?php
/**
 * Resumo para o dashboard do admin.
 * GET /dashboard.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

exigirAdmin();
$db = getDB();

$vendasHoje = $db->query("SELECT COALESCE(SUM(total),0) AS total FROM encomendas
    WHERE DATE(criado_em) = CURDATE() AND estado_pagamento = 'pago'")->fetch()['total'];

$vendasMes = $db->query("SELECT COALESCE(SUM(total),0) AS total FROM encomendas
    WHERE MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE())
    AND estado_pagamento = 'pago'")->fetch()['total'];

$encomendasPendentes = $db->query("SELECT COUNT(*) AS n FROM encomendas
    WHERE status IN ('pendente','pago','confirmado')")->fetch()['n'];

$produtosStockBaixo = $db->query("SELECT id, nome, stock, stock_minimo FROM produtos
    WHERE stock <= stock_minimo AND activo = 1 ORDER BY stock ASC LIMIT 10")->fetchAll();

$totalClientes = $db->query("SELECT COUNT(*) AS n FROM clientes WHERE activo = 1")->fetch()['n'];

$ultimasEncomendas = $db->query("SELECT e.id, e.codigo, e.total, e.status, e.criado_em, c.nome AS cliente_nome
    FROM encomendas e JOIN clientes c ON c.id = e.cliente_id
    ORDER BY e.criado_em DESC LIMIT 10")->fetchAll();

$maisVendidos = $db->query("SELECT p.id, p.nome, SUM(ei.quantidade) AS total_vendido
    FROM encomenda_itens ei
    JOIN produtos p ON p.id = ei.produto_id
    JOIN encomendas e ON e.id = ei.encomenda_id
    WHERE e.estado_pagamento = 'pago'
    GROUP BY p.id, p.nome
    ORDER BY total_vendido DESC LIMIT 5")->fetchAll();

jsonResponse([
    'vendas_hoje' => (float)$vendasHoje,
    'vendas_mes' => (float)$vendasMes,
    'encomendas_pendentes' => (int)$encomendasPendentes,
    'total_clientes' => (int)$totalClientes,
    'produtos_stock_baixo' => $produtosStockBaixo,
    'ultimas_encomendas' => $ultimasEncomendas,
    'produtos_mais_vendidos' => $maisVendidos,
]);
