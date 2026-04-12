<?php
/**
 * Database connection helper for OTA APIs.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Database.php';

$pdo = Database::getInstance();
