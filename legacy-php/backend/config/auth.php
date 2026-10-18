<?php
/**
 * Autenticação simples baseada em tokens (sem dependências externas).
 * Gera um token assinado (estilo JWT simplificado) para admin e clientes.
 */
require_once __DIR__ . '/app.php';

function gerarToken(array $payload): string {
    $payload['exp'] = time() + (60 * 60 * 24 * 7); // 7 dias
    $json = base64_encode(json_encode($payload));
    $assinatura = hash_hmac('sha256', $json, JWT_SECRET);
    return $json . '.' . $assinatura;
}

function validarToken(?string $token): ?array {
    if (!$token) return null;
    $partes = explode('.', $token);
    if (count($partes) !== 2) return null;
    [$json, $assinatura] = $partes;
    $esperado = hash_hmac('sha256', $json, JWT_SECRET);
    if (!hash_equals($esperado, $assinatura)) return null;
    $payload = json_decode(base64_decode($json), true);
    if (!$payload || $payload['exp'] < time()) return null;
    return $payload;
}

function pegarTokenHeader(): ?string {
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization') {
            return str_replace('Bearer ', '', $v);
        }
    }
    return null;
}

/** Exige que o pedido venha de um admin autenticado. */
function exigirAdmin(): array {
    $payload = validarToken(pegarTokenHeader());
    if (!$payload || ($payload['tipo'] ?? '') !== 'admin') {
        jsonError('Não autorizado. Faça login como administrador.', 401);
    }
    return $payload;
}

/** Exige que o pedido venha de um cliente autenticado. */
function exigirCliente(): array {
    $payload = validarToken(pegarTokenHeader());
    if (!$payload || ($payload['tipo'] ?? '') !== 'cliente') {
        jsonError('Não autorizado. Faça login.', 401);
    }
    return $payload;
}
