<?php
/**
 * Configurações gerais da aplicação.
 */
define('JWT_SECRET', 'MUDA_ESTA_CHAVE_PARA_ALGO_SECRETO_E_UNICO');
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('UPLOAD_URL', '/backend/uploads/products/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// --- Login social ---
// Google: cria um "OAuth client ID" em https://console.cloud.google.com/apis/credentials
//   (tipo "Web application"). Usa o Client ID aqui (não é segredo, é público).
define('GOOGLE_CLIENT_ID', 'COLOCA_AQUI_O_TEU_GOOGLE_CLIENT_ID.apps.googleusercontent.com');

// Facebook: cria uma app em https://developers.facebook.com/apps
//   Precisas do App ID (público) e do App Secret (mantém privado, nunca no frontend).
define('FACEBOOK_APP_ID', 'COLOCA_AQUI_O_TEU_FACEBOOK_APP_ID');
define('FACEBOOK_APP_SECRET', 'COLOCA_AQUI_O_TEU_FACEBOOK_APP_SECRET');

// CORS — ajusta o domínio quando a app estiver em produção
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function jsonResponse($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $msg, int $status = 400): void {
    jsonResponse(['erro' => $msg], $status);
}
