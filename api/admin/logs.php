<?php
/**
 * GET /api/admin/logs — log attività (admin)
 */

require_once __DIR__ . '/../../includes/helpers.php';

cors();

$method = $_SERVER['REQUEST_METHOD'];
$session = requireAuth();

if ($method !== 'GET') jsonError('Method not allowed', 405);

// ── GET /api/admin/logs ────────────────────────────────
$limit  = (int)($_GET['limit'] ?? 30);
$offset = (int)($_GET['offset'] ?? 0);

$logs = Database::fetchAll(
    'SELECT id, action, details, ip_address, user_agent, created_at
     FROM admin_logs
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?',
    [$limit, $offset]
);

jsonSuccess(['logs' => $logs]);