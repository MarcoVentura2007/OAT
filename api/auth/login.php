<?php
/**
 * POST /api/auth/login
 * Body: { "username": "...", "password": "..." }
 * Risposta: { "success": true, "token": "...", "user": {...} }
 */

require_once __DIR__ . '/../../includes/helpers.php';

cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metodo non permesso.', 405);
}

$body = getJsonBody();
$username = sanitizeString($body['username'] ?? '', 60);
$password = $body['password'] ?? '';

if (!$username || !$password) {
    jsonError('Username e password obbligatori.');
}

// Trova utente
$user = Database::fetchOne(
    'SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1',
    [$username]
);

if (!$user || !password_verify($password, $user['password_hash'])) {
    // Log tentativo fallito
    logActivity(null, 'login_failed', 'user', null, json_encode(['username' => $username]));
    jsonError('Credenziali non valide.', 401);
}

// Elimina sessioni scadute di questo utente
Database::query(
    'DELETE FROM admin_sessions WHERE user_id = ? AND expires_at < NOW()',
    [$user['id']]
);

// Crea nuova sessione
$token   = generateToken();
$expires = date('Y-m-d H:i:s', time() + TOKEN_TTL);

Database::query(
    'INSERT INTO admin_sessions (user_id, token, ip_address, user_agent, expires_at)
     VALUES (?, ?, ?, ?, ?)',
    [
        $user['id'],
        $token,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $expires,
    ]
);

// Aggiorna last_login
Database::query(
    'UPDATE admin_users SET last_login_at = NOW() WHERE id = ?',
    [$user['id']]
);

logActivity($user['id'], 'login', 'user', $user['id']);

jsonSuccess([
    'token'      => $token,
    'expires_at' => $expires,
    'user'       => [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ],
]);
