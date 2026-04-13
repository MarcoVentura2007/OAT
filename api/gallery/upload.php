<?php
/**
 * POST /api/gallery/upload — carica nuova foto (admin)
 */

require_once __DIR__ . '/../../includes/helpers.php';

cors();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') jsonError('Method not allowed', 405);

$session = requireAuth();

if (empty($_FILES['photo'])) {
    jsonError('Nessun file caricato.');
}

$file     = $_FILES['photo'];
$title    = sanitizeString($_POST['title'] ?? 'Foto senza titolo', 160);
$desc     = sanitizeString($_POST['description'] ?? '', 1000);
$catSlug  = sanitizeString($_POST['category'] ?? 'altro', 40);

// Validazioni
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [1=>'File troppo grande (php.ini)',2=>'File troppo grande',3=>'Upload parziale',4=>'Nessun file'];
    jsonError($errors[$file['error']] ?? 'Errore di upload.');
}
if ($file['size'] > MAX_FILE_SIZE) {
    jsonError('Il file supera il limite di ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB.');
}

// Verifica MIME reale (non fidarsi dell'estensione)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_TYPES, true)) {
    jsonError('Tipo file non permesso. Usa JPG, PNG, WebP o GIF.');
}

// Trova categoria
$category = Database::fetchOne(
    'SELECT * FROM gallery_categories WHERE slug = ?',
    [$catSlug]
);
if (!$category) {
    $category = Database::fetchOne('SELECT * FROM gallery_categories WHERE slug = "altro"');
}

// Dimensioni immagine
$imgInfo = @getimagesize($file['tmp_name']);
$width   = $imgInfo[0] ?? null;
$height  = $imgInfo[1] ?? null;

// Genera nome file univoco
$ext      = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg'
};
$subDir   = $catSlug . '/' . date('Y/m');
$fullDir  = UPLOAD_DIR . $subDir;

if (!is_dir($fullDir) && !mkdir($fullDir, 0755, true)) {
    jsonError('Impossibile creare la directory di upload.', 500);
}

// Proteggi la directory uploads
if (!file_exists(UPLOAD_DIR . '.htaccess')) {
    file_put_contents(UPLOAD_DIR . '.htaccess',
        "Options -Indexes\nAddType application/octet-stream .php .phtml .php3\n");
}

$filename = uniqid('ota_', true) . '_' . time() . '.' . $ext;
$filepath = $subDir . '/' . $filename;
$destPath = UPLOAD_DIR . $filepath;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonError('Errore nel salvataggio del file.', 500);
}

// Ottieni sort_order max
$maxSort = Database::fetchOne('SELECT MAX(sort_order) AS m FROM gallery_photos')['m'] ?? 0;

// Inserisci in DB
$photoId = Database::insert(
    'INSERT INTO gallery_photos
        (category_id, title, description, filename, filepath, mime_type, file_size, width, height, sort_order, uploaded_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [
        $category['id'], $title, $desc ?: null, $filename, $filepath,
        $mime, $file['size'], $width, $height, (int)$maxSort + 1, $session['user_id']
    ]
);

logActivity($session['user_id'], 'photo_upload', 'photo', $photoId, json_encode(['title' => $title, 'category' => $catSlug]));

$photo = Database::fetchOne(
    'SELECT p.*, c.slug AS category_slug, c.label AS category_label
     FROM gallery_photos p JOIN gallery_categories c ON c.id = p.category_id
     WHERE p.id = ?',
    [$photoId]
);
$photo['url'] = '../public\uploads/' . ltrim($photo['filepath'], '/');

jsonSuccess(['photo' => $photo], 'Foto caricata con successo.');