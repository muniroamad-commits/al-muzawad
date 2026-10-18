<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = getDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $db->query('SELECT * FROM categorias ORDER BY ordem ASC, nome ASC');
    jsonResponse($stmt->fetchAll());

} elseif ($metodo === 'POST') {
    exigirAdmin();
    $d = json_decode(file_get_contents('php://input'), true);
    if (empty($d['nome'])) jsonError('Nome é obrigatório.');

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $d['nome']), '-'));
    $stmt = $db->prepare('INSERT INTO categorias (nome, slug, descricao, ordem) VALUES (?,?,?,?)');
    $stmt->execute([$d['nome'], $slug, $d['descricao'] ?? null, $d['ordem'] ?? 0]);

    jsonResponse(['id' => $db->lastInsertId(), 'mensagem' => 'Categoria criada.'], 201);

} else {
    jsonError('Método não suportado.', 405);
}
