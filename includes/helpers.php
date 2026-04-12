<?php
/**
 * OTA — Helpers globali
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

// ── CORS ─────────────────────────────────────────────────
function cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, CORS_ORIGINS, true)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, ' . TOKEN_HEADER);
    header('Access-Control-Max-Age: 86400');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Risposta JSON ────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $code = 400): void
{
    jsonResponse(['success' => false, 'error' => $message], $code);
}

function jsonSuccess(array $data = [], string $message = 'OK'): void
{
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

// ── Body JSON input ──────────────────────────────────────
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

// ── Autenticazione token ─────────────────────────────────
function getAuthToken(): ?string
{
    // Cerca prima nell'header custom, poi in Authorization: Bearer
    $token = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper(TOKEN_HEADER))] ?? null;
    if (!$token) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            $token = $m[1];
        }
    }
    return $token ? trim($token) : null;
}

function requireAuth(): array
{
    $token = getAuthToken();
    if (!$token) {
        jsonError('Token mancante.', 401);
    }

    $session = Database::fetchOne(
        'SELECT s.*, u.id AS user_id, u.username, u.full_name, u.role, u.is_active
         FROM admin_sessions s
         JOIN admin_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW()',
        [$token]
    );

    if (!$session || !$session['is_active']) {
        jsonError('Sessione scaduta o non valida.', 401);
    }

    return $session;
}

function requireRole(array $session, string $role): void
{
    if ($session['role'] !== $role) {
        jsonError('Permessi insufficienti.', 403);
    }
}

// ── Log attività ─────────────────────────────────────────
function logActivity(int $userId = null, string $action = '', string $entity = null, int $entityId = null, string $details = null): void
{
    try {
        Database::query(
            'INSERT INTO activity_logs (user_id, action, entity, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $action, $entity, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (Throwable $e) {
        // non bloccare l'esecuzione per un log fallito
    }
}

// ── Sanitizzazione ───────────────────────────────────────
function sanitizeString(string $str, int $maxLen = 255): string
{
    return mb_substr(strip_tags(trim($str)), 0, $maxLen);
}

// ── Generazione token sicuro ─────────────────────────────
function generateToken(): string
{
    return bin2hex(random_bytes(32)); // 64 caratteri hex
}
