<?php
/**
 * GET /api/gallery/categories.php
 * Risposta: { "success": true, "categories": [...] }
 */

require_once __DIR__ . '/../../includes/helpers.php';

cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Metodo non permesso.', 405);
}

$categories = Database::fetchAll('SELECT * FROM gallery_categories ORDER BY sort_order');

jsonSuccess(['categories' => $categories]);
?>