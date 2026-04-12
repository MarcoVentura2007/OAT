<?php
/**
 * Verifica la validità del token OTA e restituisce l'utente corrente.
 */

require_once __DIR__ . '/../../includes/helpers.php';

$currentUser = requireAuth();
