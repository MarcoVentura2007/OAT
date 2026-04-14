<?php
/**
 * OTA — Gallery API
 *
 * GET    /api/gallery/photos          — lista foto pubbliche (no auth)
 * GET    /api/gallery/photos?cat=campi — filtro categoria
 * GET    /api/gallery/photos/all      — lista completa (admin)
 * GET    /api/gallery/categories      — lista categorie (no auth)
 * POST   /api/gallery/upload          — carica nuova foto (admin)
 * PUT    /api/gallery/photos/{id}     — modifica titolo/categoria/visibilità (admin)
 * DELETE /api/gallery/photos/{id}     — elimina foto (admin)
 * PUT    /api/gallery/photos/{id}/sort — riordina (admin)
 */

require_once __DIR__ . '/../../includes/helpers.php';

cors();

$method = $_SERVER['REQUEST_METHOD'];
$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
// uri esempio: CircoloTennis/api/gallery/photos/42  oppure CircoloTennis/api/gallery/upload
$parts  = explode('/', $uri);
$apiIndex = array_search('api', $parts);
if ($apiIndex === false) jsonError('Invalid API path', 400);
$resource = $parts[$apiIndex + 2] ?? '';
$idParam  = (int)($parts[$apiIndex + 3] ?? 0);
$action   = $parts[$apiIndex + 4] ?? '';

// ── GET /api/gallery/categories ─────────────────────────
if ($resource === 'categories' && $method === 'GET') {
    $cats = Database::fetchAll('SELECT * FROM gallery_categories ORDER BY sort_order');
    jsonSuccess(['categories' => $cats]);
}

// ── GET /api/gallery/photos/all (admin) ─────────────────
if ($resource === 'photos.php' && $idParam === 0 && $parts[$apiIndex + 3] === 'all' && $method === 'GET') {
    $session = requireAuth();
    $photos = Database::fetchAll(
        'SELECT p.*, c.slug AS category_slug, c.label AS category_label, u.full_name AS uploaded_by_name
         FROM gallery_photos p
         JOIN gallery_categories c ON c.id = p.category_id
         JOIN admin_users u ON u.id = p.uploaded_by
         ORDER BY p.sort_order DESC, p.created_at DESC'
    );
    foreach ($photos as &$p) {
        $p['url'] = '../public\uploads/' . ltrim($p['filepath'], '/');
    }
    jsonSuccess(['photos' => $photos, 'total' => count($photos)]);
}

// ── GET /api/gallery/photos (pubblico) ──────────────────
if ($resource === 'photos.php' && $idParam === 0 && $method === 'GET') {
    $catSlug = sanitizeString($_GET['cat'] ?? '', 40);
    $params  = [];
    $where   = 'p.is_visible = 1';

    if ($catSlug && $catSlug !== 'tutti') {
        $where  .= ' AND c.slug = ?';
        $params[] = $catSlug;
    }

    $photos = Database::fetchAll(
        "SELECT p.id, p.title, p.description, p.filepath, p.width, p.height, p.created_at,
                c.slug AS category_slug, c.label AS category_label
         FROM gallery_photos p
         JOIN gallery_categories c ON c.id = p.category_id
         WHERE $where
         ORDER BY p.sort_order DESC, p.created_at DESC",
        $params
    );

    foreach ($photos as &$p) {
        $p['url'] = '../public\uploads/' . ltrim($p['filepath'], '/');
    }

    jsonSuccess(['photos' => $photos, 'total' => count($photos)]);
}

// ── PUT /api/gallery/photos/{id} (admin) ────────────────
if ($resource === 'photos.php' && $idParam > 0 && $method === 'PUT' && !$action) {
    $session = requireAuth();
    $body    = getJsonBody();

    $photo = Database::fetchOne('SELECT * FROM gallery_photos WHERE id = ?', [$idParam]);
    if (!$photo) jsonError('Foto non trovata.', 404);

    $fields  = [];
    $params  = [];

    if (isset($body['title'])) {
        $fields[] = 'title = ?';
        $params[] = sanitizeString($body['title'], 160);
    }
    if (isset($body['description'])) {
        $fields[] = 'description = ?';
        $params[] = sanitizeString($body['description'], 1000);
    }
    if (isset($body['category'])) {
        $cat = Database::fetchOne('SELECT id FROM gallery_categories WHERE slug = ?', [$body['category']]);
        if ($cat) { $fields[] = 'category_id = ?'; $params[] = $cat['id']; }
    }
    if (isset($body['is_visible'])) {
        $fields[] = 'is_visible = ?';
        $params[] = (int)(bool)$body['is_visible'];
    }

    if (empty($fields)) jsonError('Nessun campo da aggiornare.');

    $params[] = $idParam;
    Database::query('UPDATE gallery_photos SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

    logActivity($session['user_id'], 'photo_update', 'photo', $idParam, json_encode($body));

    $updated = Database::fetchOne(
        'SELECT p.*, c.slug AS category_slug, c.label AS category_label
         FROM gallery_photos p JOIN gallery_categories c ON c.id = p.category_id
         WHERE p.id = ?', [$idParam]
    );
    $updated['url'] = '../public\uploads/' . ltrim($updated['filepath'], '/');

    jsonSuccess(['photo' => $updated], 'Foto aggiornata.');
}

// ── DELETE /api/gallery/photos/{id} (admin) ─────────────
if ($resource === 'photos.php' && $idParam > 0 && $method === 'DELETE') {
    $session = requireAuth();

    $photo = Database::fetchOne('SELECT * FROM gallery_photos WHERE id = ?', [$idParam]);
    if (!$photo) jsonError('Foto non trovata.', 404);

    // Elimina file fisico
    $fullPath = UPLOAD_DIR . $photo['filepath'];
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }

    Database::query('DELETE FROM gallery_photos WHERE id = ?', [$idParam]);
    logActivity($session['user_id'], 'photo_delete', 'photo', $idParam, json_encode(['title' => $photo['title']]));

    jsonSuccess([], 'Foto eliminata con successo.');
}

// ── PUT /api/gallery/photos/{id}/sort (admin) ────────────
if ($resource === 'photos.php' && $idParam > 0 && $action === 'sort' && $method === 'PUT') {
    $session = requireAuth();
    $body    = getJsonBody();
    $order   = (int)($body['sort_order'] ?? 0);

    Database::query('UPDATE gallery_photos SET sort_order = ? WHERE id = ?', [$order, $idParam]);
    jsonSuccess([], 'Ordine aggiornato.');
}

jsonError('Endpoint non trovato.', 404);
