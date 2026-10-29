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

$stmt = db()->prepare('SELECT id_file_path, id_file_name FROM registrations WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || empty($row['id_file_path'])) json_err('No file for this registration.', 404);

// Only ever a bare filename inside UPLOAD_DIR — block any path traversal.
$name = basename($row['id_file_path']);
$path = UPLOAD_DIR . '/' . $name;
if (!is_file($path)) json_err('File not found.', 404);

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
          'webp' => 'image/webp', 'pdf' => 'application/pdf'];
$ctype = $types[$ext] ?? 'application/octet-stream';

$downloadName = $row['id_file_name'] ?: ('id-proof.' . $ext);
header('Content-Type: ' . $ctype);
header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\- ]/', '', $downloadName) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
