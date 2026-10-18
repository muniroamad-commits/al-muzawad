<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$dados = json_decode(file_get_contents('php://input'), true);
$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';

if (!$email || !$senha) {
    jsonError('Email e senha são obrigatórios.');
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM admins WHERE email = ?');
$stmt->execute([$email]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($senha, $admin['senha_hash'])) {
    jsonError('Credenciais inválidas.', 401);
}

$token = gerarToken(['tipo' => 'admin', 'id' => $admin['id'], 'nome' => $admin['nome']]);

jsonResponse([
    'token' => $token,
    'admin' => ['id' => $admin['id'], 'nome' => $admin['nome'], 'email' => $admin['email']],
]);
