<?php
/**
 * POST /api/auth/logout        — invalida il token corrente
 * POST /api/auth/change-password — cambia la password
 * GET  /api/auth/me            — dati utente corrente
 */

require_once __DIR__ . '/../../includes/helpers.php';

cors();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$endpoint = basename($uri, '.php');

// ── GET /api/auth/me ─────────────────────────────────────
if ($endpoint === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $session = requireAuth();
    jsonSuccess([
        'user' => [
            'id'        => $session['user_id'],
            'username'  => $session['username'],
            'full_name' => $session['full_name'],
            'role'      => $session['role'],
        ]
    ]);
}

// ── POST /api/auth/logout ────────────────────────────────
if ($endpoint === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $session = requireAuth();
    $token = getAuthToken();

    Database::query('DELETE FROM admin_sessions WHERE token = ?', [$token]);
    logActivity($session['user_id'], 'logout', 'user', $session['user_id']);

    jsonSuccess([], 'Disconnesso con successo.');
}

// ── POST /api/auth/change-password ───────────────────────
if ($endpoint === 'change-password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $session  = requireAuth();
    $body     = getJsonBody();
    $oldPass  = $body['old_password'] ?? '';
    $newPass  = $body['new_password'] ?? '';
    $confirm  = $body['confirm_password'] ?? '';

    if (!$oldPass || !$newPass || !$confirm) {
        jsonError('Tutti i campi sono obbligatori.');
    }
    if ($newPass !== $confirm) {
        jsonError('Le nuove password non coincidono.');
    }
    if (strlen($newPass) < 8) {
        jsonError('La nuova password deve contenere almeno 8 caratteri.');
    }
    if (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
        jsonError('La password deve contenere almeno una lettera maiuscola e un numero.');
    }

    $user = Database::fetchOne(
        'SELECT * FROM admin_users WHERE id = ?',
        [$session['user_id']]
    );

    if (!password_verify($oldPass, $user['password_hash'])) {
        jsonError('La password attuale non è corretta.', 401);
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    Database::query(
        'UPDATE admin_users SET password_hash = ? WHERE id = ?',
        [$hash, $session['user_id']]
    );

    // Invalida tutte le altre sessioni (sicurezza)
    Database::query(
        'DELETE FROM admin_sessions WHERE user_id = ? AND token != ?',
        [$session['user_id'], getAuthToken()]
    );

    logActivity($session['user_id'], 'change_password', 'user', $session['user_id']);
    jsonSuccess([], 'Password aggiornata con successo.');
}

jsonError('Endpoint non trovato.', 404);
