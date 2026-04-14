<?php
require_once __DIR__ . '/../../includes/helpers.php';

cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metodo non permesso.', 405);
}

$session = requireAuth();

$body = getJsonBody();

$oldPass  = $body['old_password'] ?? '';
$newPass  = $body['new_password'] ?? '';
$confirm  = $body['confirm_password'] ?? '';

$user = Database::fetchOne('SELECT * FROM admin_users WHERE id = ?', [$session['user_id']]);
if (!$user) jsonError('Utente non trovato.', 404);

if (!password_verify($oldPass, $user['password'])) {
    jsonError('Password attuale errata.');
}

if ($newPass !== $confirm) {
    jsonError('Le password non corrispondono.');
}

Database::query(
    'UPDATE admin_users SET password = ? WHERE id = ?',
    [password_hash($newPass, PASSWORD_DEFAULT), $user['id']]
);

jsonSuccess([], 'Password cambiata con successo.');