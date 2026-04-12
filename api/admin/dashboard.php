<?php
/**
 * GET /api/admin/stats  — statistiche dashboard
 * GET /api/admin/logs   — log attività (admin)
 * GET /api/admin/users  — lista utenti (superadmin)
 */

require_once __DIR__ . '/../../includes/helpers.php';

cors();

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? '';

$session = requireAuth();

// ── GET /api/admin/stats ────────────────────────────────
if ($resource === 'stats' && $method === 'GET') {
    $totalPhotos   = Database::fetchOne('SELECT COUNT(*) AS n FROM gallery_photos')['n'];
    $visiblePhotos = Database::fetchOne('SELECT COUNT(*) AS n FROM gallery_photos WHERE is_visible = 1')['n'];
    $hiddenPhotos  = Database::fetchOne('SELECT COUNT(*) AS n FROM gallery_photos WHERE is_visible = 0')['n'];
    $totalSize     = Database::fetchOne('SELECT COALESCE(SUM(file_size),0) AS s FROM gallery_photos')['s'];

    $byCategory = Database::fetchAll(
        'SELECT c.label, c.slug, COUNT(p.id) AS count
         FROM gallery_categories c
         LEFT JOIN gallery_photos p ON p.category_id = c.id
         GROUP BY c.id ORDER BY c.sort_order'
    );

    $recentPhotos = Database::fetchAll(
        'SELECT p.id, p.title, p.filepath, p.created_at, c.label AS category
         FROM gallery_photos p JOIN gallery_categories c ON c.id = p.category_id
         ORDER BY p.created_at DESC LIMIT 5'
    );
    foreach ($recentPhotos as &$p) { $p['url'] = UPLOAD_URL . $p['filepath']; }

    jsonSuccess([
        'stats' => [
            'total_photos'   => (int)$totalPhotos,
            'visible_photos' => (int)$visiblePhotos,
            'hidden_photos'  => (int)$hiddenPhotos,
            'total_size_mb'  => round($totalSize / 1024 / 1024, 2),
            'by_category'    => $byCategory,
        ],
        'recent_photos' => $recentPhotos,
    ]);
}

// ── GET /api/admin/logs ─────────────────────────────────
if ($resource === 'logs' && $method === 'GET') {
    $limit  = min((int)($_GET['limit'] ?? 50), 200);
    $offset = (int)($_GET['offset'] ?? 0);

    $logs = Database::fetchAll(
        'SELECT l.*, u.full_name AS user_name
         FROM activity_logs l
         LEFT JOIN admin_users u ON u.id = l.user_id
         ORDER BY l.created_at DESC
         LIMIT ? OFFSET ?',
        [$limit, $offset]
    );
    $total = Database::fetchOne('SELECT COUNT(*) AS n FROM activity_logs')['n'];

    jsonSuccess(['logs' => $logs, 'total' => (int)$total, 'limit' => $limit, 'offset' => $offset]);
}

// ── GET /api/admin/users ────────────────────────────────
if ($resource === 'users' && $method === 'GET') {
    requireRole($session, 'superadmin');
    $users = Database::fetchAll(
        'SELECT id, username, email, full_name, role, is_active, last_login_at, created_at
         FROM admin_users ORDER BY created_at'
    );
    jsonSuccess(['users' => $users]);
}

jsonError('Endpoint non trovato.', 404);
