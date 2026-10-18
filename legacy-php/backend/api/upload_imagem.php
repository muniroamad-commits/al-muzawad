<?php
/**
 * Upload de imagem de um produto (admin).
 * POST multipart/form-data: campo "imagem" + "produto_id" + opcional "principal"
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método não suportado.', 405);
if (empty($_FILES['imagem'])) jsonError('Nenhuma imagem enviada.');

$produtoId = $_POST['produto_id'] ?? null;
if (!$produtoId) jsonError('produto_id é obrigatório.');

$arquivo = $_FILES['imagem'];

if ($arquivo['error'] !== UPLOAD_ERR_OK) jsonError('Erro no upload da imagem.');
if ($arquivo['size'] > MAX_UPLOAD_SIZE) jsonError('Imagem demasiado grande (máx 5MB).');

$tipoMime = mime_content_type($arquivo['tmp_name']);
if (!in_array($tipoMime, ALLOWED_IMAGE_TYPES)) {
    jsonError('Tipo de ficheiro não permitido. Use JPEG, PNG ou WebP.');
}

$db = getDB();
$stmt = $db->prepare('SELECT id FROM produtos WHERE id = ?');
$stmt->execute([$produtoId]);
if (!$stmt->fetch()) jsonError('Produto não encontrado.', 404);

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

$extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$nomeArquivo = 'produto_' . $produtoId . '_' . uniqid() . '.' . $extensoes[$tipoMime];
$caminhoCompleto = UPLOAD_DIR . $nomeArquivo;

if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
    jsonError('Falha ao guardar a imagem no servidor.', 500);
}

$principal = isset($_POST['principal']) && $_POST['principal'] === '1' ? 1 : 0;

if ($principal) {
    $db->prepare('UPDATE produto_imagens SET principal = 0 WHERE produto_id = ?')->execute([$produtoId]);
}

$caminhoRelativo = UPLOAD_URL . $nomeArquivo;
$stmt = $db->prepare('INSERT INTO produto_imagens (produto_id, caminho, principal) VALUES (?,?,?)');
$stmt->execute([$produtoId, $caminhoRelativo, $principal]);

jsonResponse([
    'id' => $db->lastInsertId(),
    'caminho' => $caminhoRelativo,
    'mensagem' => 'Imagem enviada com sucesso.',
], 201);
