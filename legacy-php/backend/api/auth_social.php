<?php
/**
 * Login/registo via Google ou Facebook.
 * POST /auth_social.php?provider=google    { id_token }
 * POST /auth_social.php?provider=facebook  { access_token }
 *
 * O frontend obtém o token do SDK do Google/Facebook e envia-o aqui.
 * Este ficheiro verifica o token directamente junto do Google/Facebook
 * (nunca confiamos em dados enviados pelo browser sem verificar),
 * depois cria a conta do cliente (se for a primeira vez) ou
 * autentica-o (se já existir), e devolve o nosso próprio token de sessão.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

function chamarUrl(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $resposta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resposta === false || $codigo !== 200) return null;
    $json = json_decode($resposta, true);
    return is_array($json) ? $json : null;
}

/** Cria a sessão na nossa base de dados a partir dos dados verificados do provedor. */
function entrarOuCriarCliente(PDO $db, string $provider, string $providerId, string $email, string $nome, ?string $avatar): array {
    $coluna = $provider === 'google' ? 'google_id' : 'facebook_id';

    // 1. Já existe conta ligada a este provider_id?
    $stmt = $db->prepare("SELECT * FROM clientes WHERE $coluna = ?");
    $stmt->execute([$providerId]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        // 2. Existe conta local com o mesmo email? Liga as contas.
        $stmt = $db->prepare('SELECT * FROM clientes WHERE email = ?');
        $stmt->execute([$email]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            $db->prepare("UPDATE clientes SET $coluna = ?, avatar_url = COALESCE(avatar_url, ?) WHERE id = ?")
               ->execute([$providerId, $avatar, $cliente['id']]);
        } else {
            // 3. Primeira vez — cria conta nova
            $stmt = $db->prepare("INSERT INTO clientes (nome, email, provider, $coluna, avatar_url)
                                   VALUES (?,?,?,?,?)");
            $stmt->execute([$nome, $email, $provider, $providerId, $avatar]);
            $stmt = $db->prepare('SELECT * FROM clientes WHERE id = ?');
            $stmt->execute([$db->lastInsertId()]);
            $cliente = $stmt->fetch();
        }
    }

    if (!$cliente['activo']) {
        jsonError('Esta conta foi desactivada.', 403);
    }

    $token = gerarToken(['tipo' => 'cliente', 'id' => $cliente['id'], 'nome' => $cliente['nome']]);
    return [
        'token' => $token,
        'cliente' => [
            'id' => $cliente['id'],
            'nome' => $cliente['nome'],
            'email' => $cliente['email'],
            'avatar_url' => $cliente['avatar_url'],
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método não suportado.', 405);

$provider = $_GET['provider'] ?? '';
$d = json_decode(file_get_contents('php://input'), true);
$db = getDB();

if ($provider === 'google') {
    $idToken = $d['id_token'] ?? '';
    if (!$idToken) jsonError('id_token é obrigatório.');

    // Verifica o token directamente junto do Google (fonte de verdade).
    $info = chamarUrl('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    if (!$info || empty($info['sub']) || empty($info['email'])) {
        jsonError('Token do Google inválido.', 401);
    }
    if (($info['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        jsonError('Token do Google não corresponde a esta aplicação.', 401);
    }

    $resultado = entrarOuCriarCliente(
        $db, 'google', $info['sub'], $info['email'],
        $info['name'] ?? explode('@', $info['email'])[0], $info['picture'] ?? null
    );
    jsonResponse($resultado);

} elseif ($provider === 'facebook') {
    $accessToken = $d['access_token'] ?? '';
    if (!$accessToken) jsonError('access_token é obrigatório.');

    // Confirma que o token pertence mesmo à nossa app (evita tokens forjados/de outra app).
    $debug = chamarUrl(
        "https://graph.facebook.com/debug_token?input_token=" . urlencode($accessToken) .
        "&access_token=" . urlencode(FACEBOOK_APP_ID . '|' . FACEBOOK_APP_SECRET)
    );
    if (!$debug || empty($debug['data']['is_valid']) || ($debug['data']['app_id'] ?? '') !== FACEBOOK_APP_ID) {
        jsonError('Token do Facebook inválido.', 401);
    }

    $perfil = chamarUrl(
        "https://graph.facebook.com/me?fields=id,name,email,picture&access_token=" . urlencode($accessToken)
    );
    if (!$perfil || empty($perfil['id'])) jsonError('Não foi possível obter o perfil do Facebook.', 401);
    if (empty($perfil['email'])) {
        jsonError('A tua conta Facebook não tem email público. Usa outro método de login.');
    }

    $resultado = entrarOuCriarCliente(
        $db, 'facebook', $perfil['id'], $perfil['email'],
        $perfil['name'] ?? 'Cliente', $perfil['picture']['data']['url'] ?? null
    );
    jsonResponse($resultado);

} else {
    jsonError('Provedor inválido. Usa "google" ou "facebook".', 404);
}
