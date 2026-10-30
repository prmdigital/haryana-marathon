<?php
/**
 * Public endpoint: receive a marathon registration.
 * Accepts multipart/form-data (so the ID-proof file can be attached).
 */
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';

require_method('POST');

$in = $_POST;

// ---- Collect + validate ---------------------------------------------------
$fullName = clean($in['fullName'] ?? '', 120);
$email    = clean($in['email'] ?? '', 160);
$mobile   = preg_replace('/\D/', '', (string)($in['mobile'] ?? ''));
$category = clean($in['category'] ?? '', 40);
$catLabel = clean($in['categoryLabel'] ?? '', 80);
$dob      = clean($in['dob'] ?? '', 10);
$gender   = clean($in['gender'] ?? '', 20);
$state    = clean($in['state'] ?? '', 80);
$city     = clean($in['city'] ?? '', 80);
$tshirt   = clean($in['tshirtSize'] ?? '', 10);
$idType   = clean($in['idProofType'] ?? '', 40);
$emergency = preg_replace('/\D/', '', (string)($in['emergencyPhone'] ?? ''));
$freeElig = !empty($in['freeEligible']) && $in['freeEligible'] !== 'false' ? 1 : 0;
$declared = !empty($in['declaration']) && $in['declaration'] !== 'false';

$errors = [];
if ($fullName === '')                                   $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errors[] = 'A valid email is required.';
if (!preg_match('/^\d{10}$/', $mobile))                 $errors[] = 'A valid 10-digit mobile number is required.';
if ($category === '')                                   $errors[] = 'Please select a race category.';
if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob))         $errors[] = 'Date of birth must be DD-MM-YYYY.';
if ($gender === '')                                     $errors[] = 'Please select a gender.';
if ($state === '')                                      $errors[] = 'Please select a state.';
if ($city === '')                                       $errors[] = 'City is required.';
if ($tshirt === '')                                     $errors[] = 'Please select a T-shirt size.';
if ($idType === '')                                     $errors[] = 'Please select an ID proof type.';
if (!$declared)                                         $errors[] = 'You must accept the declaration.';

if ($errors) json_err('Please correct the form.', 422, ['errors' => $errors]);

// This endpoint handles FREE registrations only (Disabled category or free-eligible).
if (!($category === 'disabled' || $freeElig)) {
  json_err('This category requires online payment.', 400);
}

// ---- Age on race day (21 March 2027), computed server-side ----------------
$age = null;
if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob, $m)) {
  $b = DateTime::createFromFormat('!Y-m-d', "{$m[3]}-{$m[2]}-{$m[1]}");
  if ($b) {
    $race = new DateTime('2027-03-21');
    $age = (int)$b->diff($race)->y;
    if ($age < 0 || $age > 120) $age = null;
  }
}

// ---- Handle the optional ID-proof upload ----------------------------------
$idPath = null; $idName = null;
if (!empty($_FILES['idFile']) && ($_FILES['idFile']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
  $f = $_FILES['idFile'];
  if ($f['size'] > MAX_UPLOAD_BYTES) json_err('ID proof file must be 5 MB or smaller.', 422);
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($f['tmp_name']);
  $allowed = $GLOBALS['ALLOWED_UPLOAD_MIME'];
  if (!isset($allowed[$mime])) json_err('ID proof must be a JPG, PNG, WebP or PDF.', 422);
  if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
  $idName = clean($f['name'], 255);
  $fname = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
  if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $fname)) {
    json_err('Could not save the uploaded file. Please try again.', 500);
  }
  $idPath = $fname;
}

// ---- Unique registration number -------------------------------------------
$pdo = db();
$regNo = '';
for ($i = 0; $i < 6; $i++) {
  $candidate = 'HM27-' . random_int(100000, 999999);
  $chk = $pdo->prepare('SELECT 1 FROM registrations WHERE reg_no = ?');
  $chk->execute([$candidate]);
  if (!$chk->fetch()) { $regNo = $candidate; break; }
}
if ($regNo === '') json_err('Could not generate a registration number. Please retry.', 500);

// ---- Insert ---------------------------------------------------------------
try {
  $stmt = $pdo->prepare(
    'INSERT INTO registrations
      (reg_no, full_name, email, mobile, category, category_label, dob, age_on_race_day,
       gender, state, city, tshirt_size, id_proof_type, id_file_path, id_file_name,
       emergency_phone, free_eligible, status, amount_paise, ip_address)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
  );
  $stmt->execute([
    $regNo, $fullName, $email, $mobile, $category, $catLabel, $dob, $age,
    $gender, $state, $city, $tshirt, $idType, $idPath, $idName,
    ($emergency !== '' ? $emergency : null), $freeElig, 'free', 0, client_ip(),
  ]);
} catch (Throwable $e) {
  json_err('Could not save your registration. Please try again.', 500);
}

json_out(['ok' => true, 'regNo' => $regNo]);
