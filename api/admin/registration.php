<?php
/**
 * Admin: update or delete a single registration (Super Admin / Manager only).
 *   POST { id, action: "delete" }   -> remove the registration and its uploaded files
 *   POST { id, <fields...> }         -> update the editable fields
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_method('POST');
require_role(['super', 'manager']); // viewers cannot edit/delete
require_csrf();

const CATEGORY_LABELS = [
  'half'     => 'Half Marathon (21 KM)',
  'timed'    => 'Cross Country (10 KM)',
  'cause'    => 'Run for Cause (5 KM)',
  'children' => 'Run for Little Champions (3 KM)',
  'disabled' => 'Disabled Category (1 KM) — Free',
];

$pdo = db();
$in  = read_json_body();
$id  = (int)($in['id'] ?? 0);
if ($id <= 0) json_err('Missing id.', 400);

$stmt = $pdo->prepare('SELECT id, id_file_path, free_doc_path FROM registrations WHERE id = ?');
$stmt->execute([$id]);
$reg = $stmt->fetch();
if (!$reg) json_err('Registration not found.', 404);

// ---- Delete ---------------------------------------------------------------
if (($in['action'] ?? '') === 'delete') {
  foreach (['id_file_path', 'free_doc_path'] as $col) {
    $f = $reg[$col] ?? '';
    if ($f) {
      $p = UPLOAD_DIR . '/' . basename($f);
      if (is_file($p)) @unlink($p);
    }
  }
  $pdo->prepare('DELETE FROM registrations WHERE id = ?')->execute([$id]);
  json_out(['ok' => true]);
}

// ---- Update ---------------------------------------------------------------
$fullName  = clean($in['full_name'] ?? '', 120);
$email     = clean($in['email'] ?? '', 160);
$mobile    = preg_replace('/\D/', '', (string)($in['mobile'] ?? ''));
$category  = clean($in['category'] ?? '', 40);
$dob       = clean($in['dob'] ?? '', 10);
$gender    = clean($in['gender'] ?? '', 20);
$state     = clean($in['state'] ?? '', 80);
$city      = clean($in['city'] ?? '', 80);
$tshirt    = clean($in['tshirt_size'] ?? '', 10);
$idType    = clean($in['id_proof_type'] ?? '', 40);
$emergency = preg_replace('/\D/', '', (string)($in['emergency_phone'] ?? ''));

$e = [];
if ($fullName === '')                              $e[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $e[] = 'A valid email is required.';
if (!preg_match('/^\d{10}$/', $mobile))            $e[] = 'A valid 10-digit mobile number is required.';
if (!isset(CATEGORY_LABELS[$category]))            $e[] = 'Please select a valid category.';
if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob))    $e[] = 'Date of birth must be DD-MM-YYYY.';
if ($gender === '')                                $e[] = 'Gender is required.';
if ($state === '')                                 $e[] = 'State is required.';
if ($city === '')                                  $e[] = 'City is required.';
if ($tshirt === '')                                $e[] = 'T-shirt size is required.';
if ($idType === '')                                $e[] = 'ID proof type is required.';
if ($e) json_err('Please correct the form.', 422, ['errors' => $e]);

// Recompute age on race day from the (possibly updated) DOB.
$age = null;
if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob, $m)) {
  $b = DateTime::createFromFormat('!Y-m-d', "{$m[3]}-{$m[2]}-{$m[1]}");
  if ($b) { $age = (int)$b->diff(new DateTime('2027-03-21'))->y; if ($age < 0 || $age > 120) $age = null; }
}

$pdo->prepare(
  'UPDATE registrations
      SET full_name = ?, email = ?, mobile = ?, category = ?, category_label = ?,
          dob = ?, age_on_race_day = ?, gender = ?, state = ?, city = ?,
          tshirt_size = ?, id_proof_type = ?, emergency_phone = ?
    WHERE id = ?'
)->execute([
  $fullName, $email, $mobile, $category, CATEGORY_LABELS[$category],
  $dob, $age, $gender, $state, $city,
  $tshirt, $idType, ($emergency !== '' ? $emergency : null), $id,
]);

json_out(['ok' => true]);
