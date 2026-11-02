<?php
/**
 * Securely stream a registrant's ID-proof file (admin only).
 * GET ?id=<registration id>
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_method('GET');
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_err('Missing id.', 400);

// Which file: the ID proof (default) or the free-eligibility document.
$type    = ($_GET['type'] ?? '') === 'free' ? 'free' : 'id';
$pathCol = $type === 'free' ? 'free_doc_path' : 'id_file_path';
$nameCol = $type === 'free' ? 'free_doc_name' : 'id_file_name';

$stmt = db()->prepare("SELECT $pathCol AS p, $nameCol AS n FROM registrations WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || empty($row['p'])) json_err('No file for this registration.', 404);

// Only ever a bare filename inside UPLOAD_DIR — block any path traversal.
$name = basename($row['p']);
$path = UPLOAD_DIR . '/' . $name;
if (!is_file($path)) json_err('File not found.', 404);

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
          'webp' => 'image/webp', 'pdf' => 'application/pdf'];
$ctype = $types[$ext] ?? 'application/octet-stream';

$downloadName = $row['n'] ?: (($type === 'free' ? 'eligibility.' : 'id-proof.') . $ext);
header('Content-Type: ' . $ctype);
header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\- ]/', '', $downloadName) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
