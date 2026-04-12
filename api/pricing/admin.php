<?php
// api/pricing/admin.php
// CRUD tariffe per l'admin dashboard
// Richiede header X-OTA-Token valido

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-OTA-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../auth/check_token.php'; // sets $currentUser or exits 401

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Helper: formatta prezzo
function fmt($v) { return round((float)$v, 2); }

// Helper: log attività
function logAction(PDO $pdo, int $userId, string $action, string $entity, ?int $entityId, string $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $action, $entity, $entityId, $details, $ip]);
}

// ── Router ───────────────────────────────────────────────────────────────────
// GET  /admin.php?resource=all         — tutto
// GET  /admin.php?resource=memberships
// POST /admin.php?resource=memberships
// PUT  /admin.php?resource=memberships&id=X
// DELETE /admin.php?resource=memberships&id=X
// (stesso schema per: courses, courts, extras, membership_features)

$resource = $_GET['resource'] ?? 'all';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // ════════════════════════════════════════════════════════
    // GET ALL — restituisce tutte le sezioni in una volta
    // ════════════════════════════════════════════════════════
    if ($method === 'GET' && $resource === 'all') {

        // Memberships + features
        $ms = $pdo->query("SELECT * FROM pricing_memberships ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ms as &$m) {
            $m['price'] = (float)$m['price'];
            $m['is_featured'] = (bool)$m['is_featured'];
            $fs = $pdo->prepare("SELECT * FROM pricing_membership_features WHERE membership_id=? ORDER BY sort_order");
            $fs->execute([$m['id']]);
            $m['features'] = $fs->fetchAll(PDO::FETCH_ASSOC);
        }

        $courses = $pdo->query("SELECT * FROM pricing_courses ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($courses as &$c) { $c['price'] = (float)$c['price']; }

        $courts = $pdo->query("SELECT * FROM pricing_courts ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($courts as &$ct) {
            $ct['price_day']     = (float)$ct['price_day'];
            $ct['price_evening'] = (float)$ct['price_evening'];
            $ct['price_weekend'] = (float)$ct['price_weekend'];
        }

        $extras = $pdo->query("SELECT * FROM pricing_extras ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($extras as &$e) { $e['price'] = (float)$e['price']; }

        echo json_encode(['success' => true, 'memberships' => $ms, 'courses' => $courses, 'courts' => $courts, 'extras' => $extras], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ════════════════════════════════════════════════════════
    // MEMBERSHIPS
    // ════════════════════════════════════════════════════════
    if ($resource === 'memberships') {

        if ($method === 'PUT' && $id) {
            $fields = [];
            $params = [];
            if (isset($body['name']))       { $fields[] = 'name=?';       $params[] = $body['name']; }
            if (isset($body['price']))      { $fields[] = 'price=?';      $params[] = fmt($body['price']); }
            if (isset($body['period']))     { $fields[] = 'period=?';     $params[] = $body['period']; }
            if (isset($body['is_featured'])){ $fields[] = 'is_featured=?';$params[] = (int)$body['is_featured']; }
            if (isset($body['badge_text'])) { $fields[] = 'badge_text=?'; $params[] = $body['badge_text'] ?: null; }
            if (isset($body['is_active']))  { $fields[] = 'is_active=?';  $params[] = (int)$body['is_active']; }
            if ($fields) {
                $params[] = $id;
                $pdo->prepare("UPDATE pricing_memberships SET ".implode(',',$fields)." WHERE id=?")->execute($params);
                logAction($pdo, $currentUser['id'], 'update_membership', 'membership', $id, json_encode($body));
            }
            echo json_encode(['success' => true, 'message' => 'Tessera aggiornata.']);
            exit;
        }
    }

    // ════════════════════════════════════════════════════════
    // MEMBERSHIP FEATURES
    // ════════════════════════════════════════════════════════
    if ($resource === 'membership_features') {

        if ($method === 'POST') {
            // Aggiungi feature
            $stmt = $pdo->prepare("INSERT INTO pricing_membership_features (membership_id, feature_text, sort_order) VALUES (?,?,?)");
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM pricing_membership_features WHERE membership_id=?");
            $maxOrder->execute([$body['membership_id']]);
            $nextOrder = (int)$maxOrder->fetchColumn();
            $stmt->execute([$body['membership_id'], $body['feature_text'], $nextOrder]);
            $newId = (int)$pdo->lastInsertId();
            logAction($pdo, $currentUser['id'], 'add_feature', 'membership_feature', $newId, $body['feature_text']);
            echo json_encode(['success' => true, 'id' => $newId]);
            exit;
        }

        if ($method === 'PUT' && $id) {
            $pdo->prepare("UPDATE pricing_membership_features SET feature_text=?, sort_order=? WHERE id=?")
                ->execute([$body['feature_text'], $body['sort_order'] ?? 0, $id]);
            logAction($pdo, $currentUser['id'], 'update_feature', 'membership_feature', $id, $body['feature_text']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($method === 'DELETE' && $id) {
            $pdo->prepare("DELETE FROM pricing_membership_features WHERE id=?")->execute([$id]);
            logAction($pdo, $currentUser['id'], 'delete_feature', 'membership_feature', $id);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // ════════════════════════════════════════════════════════
    // COURSES
    // ════════════════════════════════════════════════════════
    if ($resource === 'courses') {

        if ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO pricing_courses (group_slug, group_label, name, price, period, sort_order) VALUES (?,?,?,?,?,?)");
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM pricing_courses");
            $maxOrder->execute();
            $nextOrder = (int)$maxOrder->fetchColumn();
            $stmt->execute([$body['group_slug'], $body['group_label'], $body['name'], fmt($body['price']), $body['period'], $nextOrder]);
            $newId = (int)$pdo->lastInsertId();
            logAction($pdo, $currentUser['id'], 'add_course', 'course', $newId, $body['name']);
            echo json_encode(['success' => true, 'id' => $newId]);
            exit;
        }

        if ($method === 'PUT' && $id) {
            $fields = []; $params = [];
            foreach (['name','group_slug','group_label','period'] as $f) {
                if (isset($body[$f])) { $fields[] = "$f=?"; $params[] = $body[$f]; }
            }
            if (isset($body['price']))     { $fields[] = 'price=?';     $params[] = fmt($body['price']); }
            if (isset($body['is_active'])) { $fields[] = 'is_active=?'; $params[] = (int)$body['is_active']; }
            if ($fields) {
                $params[] = $id;
                $pdo->prepare("UPDATE pricing_courses SET ".implode(',',$fields)." WHERE id=?")->execute($params);
                logAction($pdo, $currentUser['id'], 'update_course', 'course', $id, json_encode($body));
            }
            echo json_encode(['success' => true, 'message' => 'Corso aggiornato.']);
            exit;
        }

        if ($method === 'DELETE' && $id) {
            $pdo->prepare("DELETE FROM pricing_courses WHERE id=?")->execute([$id]);
            logAction($pdo, $currentUser['id'], 'delete_course', 'course', $id);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // ════════════════════════════════════════════════════════
    // COURTS
    // ════════════════════════════════════════════════════════
    if ($resource === 'courts') {

        if ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO pricing_courts (surface_label, surface_slug, price_day, price_evening, price_weekend, sort_order) VALUES (?,?,?,?,?,?)");
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM pricing_courts");
            $maxOrder->execute();
            $nextOrder = (int)$maxOrder->fetchColumn();
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-',$body['surface_label']));
            $stmt->execute([$body['surface_label'], $slug, fmt($body['price_day']), fmt($body['price_evening']), fmt($body['price_weekend']), $nextOrder]);
            $newId = (int)$pdo->lastInsertId();
            logAction($pdo, $currentUser['id'], 'add_court_price', 'court', $newId, $body['surface_label']);
            echo json_encode(['success' => true, 'id' => $newId]);
            exit;
        }

        if ($method === 'PUT' && $id) {
            $fields = []; $params = [];
            if (isset($body['surface_label'])) { $fields[] = 'surface_label=?'; $params[] = $body['surface_label']; }
            if (isset($body['price_day']))      { $fields[] = 'price_day=?';     $params[] = fmt($body['price_day']); }
            if (isset($body['price_evening']))  { $fields[] = 'price_evening=?'; $params[] = fmt($body['price_evening']); }
            if (isset($body['price_weekend']))  { $fields[] = 'price_weekend=?'; $params[] = fmt($body['price_weekend']); }
            if (isset($body['is_active']))      { $fields[] = 'is_active=?';     $params[] = (int)$body['is_active']; }
            if ($fields) {
                $params[] = $id;
                $pdo->prepare("UPDATE pricing_courts SET ".implode(',',$fields)." WHERE id=?")->execute($params);
                logAction($pdo, $currentUser['id'], 'update_court_price', 'court', $id, json_encode($body));
            }
            echo json_encode(['success' => true, 'message' => 'Tariffa campo aggiornata.']);
            exit;
        }

        if ($method === 'DELETE' && $id) {
            $pdo->prepare("DELETE FROM pricing_courts WHERE id=?")->execute([$id]);
            logAction($pdo, $currentUser['id'], 'delete_court_price', 'court', $id);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // ════════════════════════════════════════════════════════
    // EXTRAS
    // ════════════════════════════════════════════════════════
    if ($resource === 'extras') {

        if ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO pricing_extras (name, price, note, sort_order) VALUES (?,?,?,?)");
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM pricing_extras");
            $maxOrder->execute();
            $nextOrder = (int)$maxOrder->fetchColumn();
            $stmt->execute([$body['name'], fmt($body['price']), $body['note'] ?? null, $nextOrder]);
            $newId = (int)$pdo->lastInsertId();
            logAction($pdo, $currentUser['id'], 'add_extra', 'extra', $newId, $body['name']);
            echo json_encode(['success' => true, 'id' => $newId]);
            exit;
        }

        if ($method === 'PUT' && $id) {
            $fields = []; $params = [];
            if (isset($body['name']))      { $fields[] = 'name=?';      $params[] = $body['name']; }
            if (isset($body['price']))     { $fields[] = 'price=?';     $params[] = fmt($body['price']); }
            if (isset($body['note']))      { $fields[] = 'note=?';      $params[] = $body['note'] ?: null; }
            if (isset($body['is_active'])) { $fields[] = 'is_active=?'; $params[] = (int)$body['is_active']; }
            if ($fields) {
                $params[] = $id;
                $pdo->prepare("UPDATE pricing_extras SET ".implode(',',$fields)." WHERE id=?")->execute($params);
                logAction($pdo, $currentUser['id'], 'update_extra', 'extra', $id, json_encode($body));
            }
            echo json_encode(['success' => true, 'message' => 'Extra aggiornato.']);
            exit;
        }

        if ($method === 'DELETE' && $id) {
            $pdo->prepare("DELETE FROM pricing_extras WHERE id=?")->execute([$id]);
            logAction($pdo, $currentUser['id'], 'delete_extra', 'extra', $id);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Richiesta non valida.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno: ' . $e->getMessage()]);
}
