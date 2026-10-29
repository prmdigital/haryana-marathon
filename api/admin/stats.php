<?php
/** Dashboard headline numbers + per-category counts (admin only). */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_method('GET');
require_admin();

$pdo = db();
$total  = (int) $pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$today  = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$week   = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
$free   = (int) $pdo->query('SELECT COUNT(*) FROM registrations WHERE free_eligible = 1')->fetchColumn();
$withId = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE id_file_path IS NOT NULL AND id_file_path <> ''")->fetchColumn();

$cat = [];
foreach ($pdo->query('SELECT category, COUNT(*) n FROM registrations GROUP BY category')->fetchAll() as $r) {
  $cat[$r['category']] = (int) $r['n'];
}

json_out([
  'ok'    => true,
  'stats' => compact('total', 'today', 'week', 'free', 'withId'),
  'byCategory' => $cat,
]);
