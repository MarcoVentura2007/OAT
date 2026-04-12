<?php
// api/pricing/public.php
// Restituisce tutte le tariffe per la pagina pubblica tariffe.html
// GET /api/pricing/public.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/db.php';

try {
    // ── Tessere ──────────────────────────────────────────────
    $stmtM = $pdo->query("
        SELECT m.id, m.slug, m.name, m.price, m.period, m.is_featured, m.badge_text,
               GROUP_CONCAT(f.feature_text ORDER BY f.sort_order SEPARATOR '||') AS features
        FROM pricing_memberships m
        LEFT JOIN pricing_membership_features f ON f.membership_id = m.id
        WHERE m.is_active = 1
        GROUP BY m.id
        ORDER BY m.sort_order
    ");
    $memberships = [];
    foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['features'] = $row['features'] ? explode('||', $row['features']) : [];
        $row['price'] = (float)$row['price'];
        $row['is_featured'] = (bool)$row['is_featured'];
        $memberships[] = $row;
    }

    // ── Corsi ────────────────────────────────────────────────
    $stmtC = $pdo->query("
        SELECT id, group_slug, group_label, name, price, period, sort_order
        FROM pricing_courses
        WHERE is_active = 1
        ORDER BY sort_order
    ");
    $courses = [];
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['price'] = (float)$row['price'];
        $gs = $row['group_slug'];
        if (!isset($courses[$gs])) {
            $courses[$gs] = ['slug' => $gs, 'label' => $row['group_label'], 'items' => []];
        }
        $courses[$gs]['items'][] = $row;
    }
    $courses = array_values($courses);

    // ── Campi ────────────────────────────────────────────────
    $stmtCt = $pdo->query("
        SELECT id, surface_label, surface_slug, price_day, price_evening, price_weekend
        FROM pricing_courts
        WHERE is_active = 1
        ORDER BY sort_order
    ");
    $courts = [];
    foreach ($stmtCt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['price_day']     = (float)$row['price_day'];
        $row['price_evening'] = (float)$row['price_evening'];
        $row['price_weekend'] = (float)$row['price_weekend'];
        $courts[] = $row;
    }

    // ── Extra ────────────────────────────────────────────────
    $stmtE = $pdo->query("
        SELECT id, name, price, note
        FROM pricing_extras
        WHERE is_active = 1
        ORDER BY sort_order
    ");
    $extras = [];
    foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['price'] = (float)$row['price'];
        $extras[] = $row;
    }

    echo json_encode([
        'success'     => true,
        'memberships' => $memberships,
        'courses'     => $courses,
        'courts'      => $courts,
        'extras'      => $extras,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno del server.']);
}