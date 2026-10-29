<?php
/**
 * List / search registrations (admin only).
 * GET params: q (search), category, format=json|csv, page, per
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_method('GET');
require_admin();

$q        = clean($_GET['q'] ?? '', 80);
$category = clean($_GET['category'] ?? '', 40);
$format   = ($_GET['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = min(200, max(1, (int)($_GET['per'] ?? 50)));

$where = [];
$args  = [];
if ($q !== '') {
  $where[] = '(full_name LIKE ? OR email LIKE ? OR mobile LIKE ? OR reg_no LIKE ? OR city LIKE ?)';
  $like = '%' . $q . '%';
  array_push($args, $like, $like, $like, $like, $like);
}
if ($category !== '') {
  $where[] = 'category = ?';
  $args[] = $category;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$pdo = db();

$cols = 'id, reg_no, full_name, email, mobile, category, category_label, dob, age_on_race_day,
         gender, state, city, tshirt_size, id_proof_type, id_file_path, id_file_name,
         emergency_phone, free_eligible, created_at';

if ($format === 'csv') {
  $stmt = $pdo->prepare("SELECT $cols FROM registrations $whereSql ORDER BY created_at DESC");
  $stmt->execute($args);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="registrations-' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Reg No','Name','Email','Mobile','Category','DOB','Age','Gender','State','City',
                 'T-Shirt','ID Type','Has ID File','Emergency','Free Eligible','Registered At']);
  while ($r = $stmt->fetch()) {
    fputcsv($out, [
      $r['reg_no'], $r['full_name'], $r['email'], $r['mobile'], $r['category_label'],
      $r['dob'], $r['age_on_race_day'], $r['gender'], $r['state'], $r['city'],
      $r['tshirt_size'], $r['id_proof_type'], $r['id_file_path'] ? 'Yes' : 'No',
      $r['emergency_phone'], $r['free_eligible'] ? 'Yes' : 'No', $r['created_at'],
    ]);
  }
  fclose($out);
  exit;
}

// JSON (paginated)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations $whereSql");
$countStmt->execute($args);
$total = (int)$countStmt->fetchColumn();

$offset = ($page - 1) * $per;
$stmt = $pdo->prepare("SELECT $cols FROM registrations $whereSql ORDER BY created_at DESC LIMIT $per OFFSET $offset");
$stmt->execute($args);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
  $r['id'] = (int)$r['id'];
  $r['free_eligible'] = (bool)$r['free_eligible'];
  $r['has_id_file'] = !empty($r['id_file_path']);
  unset($r['id_file_path']); // never expose the raw path to the client
}
unset($r);

json_out(['ok' => true, 'total' => $total, 'page' => $page, 'per' => $per, 'rows' => $rows]);
