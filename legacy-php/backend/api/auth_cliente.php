<?php
/**
 * Registo e login de clientes.
 * ?acao=registar  |  ?acao=login
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$acao = $_GET['acao'] ?? '';
$dados = json_decode(file_get_contents('php://input'), true);
$db = getDB();

if ($acao === 'registar') {
    $nome = trim($dados['nome'] ?? '');
    $email = trim($dados['email'] ?? '');
    $telefone = trim($dados['telefone'] ?? '');
    $senha = $dados['senha'] ?? '';

    if (!$nome || !$email || !$senha) {
        jsonError('Nome, email e senha são obrigatórios.');
    }

    $stmt = $db->prepare('SELECT id FROM clientes WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonError('Este email já está registado.');
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO clientes (nome, email, telefone, senha_hash) VALUES (?,?,?,?)');
    $stmt->execute([$nome, $email, $telefone, $hash]);
    $clienteId = $db->lastInsertId();

    $token = gerarToken(['tipo' => 'cliente', 'id' => $clienteId, 'nome' => $nome]);
    jsonResponse(['token' => $token, 'cliente' => ['id' => $clienteId, 'nome' => $nome, 'email' => $email]], 201);

} elseif ($acao === 'login') {
    $email = trim($dados['email'] ?? '');
    $senha = $dados['senha'] ?? '';

    $stmt = $db->prepare('SELECT * FROM clientes WHERE email = ?');
    $stmt->execute([$email]);
    $cliente = $stmt->fetch();

    if (!$cliente || !$cliente['senha_hash'] || !password_verify($senha, $cliente['senha_hash'])) {
        if ($cliente && !$cliente['senha_hash']) {
            jsonError('Esta conta foi criada com Google/Facebook. Usa esse método para entrar.', 401);
        }
        jsonError('Credenciais inválidas.', 401);
    }

    $token = gerarToken(['tipo' => 'cliente', 'id' => $cliente['id'], 'nome' => $cliente['nome']]);
    jsonResponse(['token' => $token, 'cliente' => [
        'id' => $cliente['id'], 'nome' => $cliente['nome'], 'email' => $cliente['email'],
    ]]);

} else {
    jsonError('Acção inválida.', 404);
}
