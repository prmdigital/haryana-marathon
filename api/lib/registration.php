<?php
/** Shared registration helpers (used by create-order.php). */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Collect + validate the registration fields from $_POST. Returns [data, errors]. */
function collect_registration(): array {
  $in = $_POST;
  $d = [
    'fullName'      => clean($in['fullName'] ?? '', 120),
    'email'         => clean($in['email'] ?? '', 160),
    'mobile'        => preg_replace('/\D/', '', (string)($in['mobile'] ?? '')),
    'category'      => clean($in['category'] ?? '', 40),
    'categoryLabel' => clean($in['categoryLabel'] ?? '', 80),
    'dob'           => clean($in['dob'] ?? '', 10),
    'gender'        => clean($in['gender'] ?? '', 20),
    'state'         => clean($in['state'] ?? '', 80),
    'city'          => clean($in['city'] ?? '', 80),
    'tshirt'        => clean($in['tshirtSize'] ?? '', 10),
    'idType'        => clean($in['idProofType'] ?? '', 40),
    'emergency'     => preg_replace('/\D/', '', (string)($in['emergencyPhone'] ?? '')),
    'freeEligible'  => (!empty($in['freeEligible']) && $in['freeEligible'] !== 'false') ? 1 : 0,
    'declared'      => (!empty($in['declaration']) && $in['declaration'] !== 'false'),
  ];
  $e = [];
  if ($d['fullName'] === '')                              $e[] = 'Full name is required.';
  if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL))    $e[] = 'A valid email is required.';
  if (!preg_match('/^\d{10}$/', $d['mobile']))            $e[] = 'A valid 10-digit mobile number is required.';
  if ($d['category'] === '')                              $e[] = 'Please select a race category.';
  if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $d['dob']))    $e[] = 'Date of birth must be DD-MM-YYYY.';
  if ($d['gender'] === '')                                $e[] = 'Please select a gender.';
  if ($d['state'] === '')                                 $e[] = 'Please select a state.';
  if ($d['city'] === '')                                  $e[] = 'City is required.';
  if ($d['tshirt'] === '')                                $e[] = 'Please select a T-shirt size.';
  if ($d['idType'] === '')                                $e[] = 'Please select an ID proof type.';
  if (!$d['declared'])                                    $e[] = 'You must accept the declaration.';
  return [$d, $e];
}

function age_on_race_day_from(string $dob): ?int {
  if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob, $m)) {
    $b = DateTime::createFromFormat('!Y-m-d', "{$m[3]}-{$m[2]}-{$m[1]}");
    if ($b) {
      $age = (int)$b->diff(new DateTime('2027-03-21'))->y;
      if ($age >= 0 && $age < 120) return $age;
    }
  }
  return null;
}

/** Store the uploaded ID file. Returns [path|null, name|null, error|null]. */
function store_id_file(): array {
  if (empty($_FILES['idFile']) || ($_FILES['idFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return [null, null, null];
  }
  $f = $_FILES['idFile'];
  if ($f['size'] > MAX_UPLOAD_BYTES) return [null, null, 'ID proof file must be 5 MB or smaller.'];
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  $allowed = $GLOBALS['ALLOWED_UPLOAD_MIME'];
  if (!isset($allowed[$mime])) return [null, null, 'ID proof must be a JPG, PNG, WebP or PDF.'];
  if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
  $name = clean($f['name'], 255);
  $fname = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
  if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $fname)) {
    return [null, null, 'Could not save the uploaded file. Please try again.'];
  }
  return [$fname, $name, null];
}

/** Entry fee for a category in paise (0 for free/unknown). Read from site_settings. */
function fee_paise(string $category): int {
  $map = ['half' => 'fee_half', 'timed' => 'fee_cross', 'cause' => 'fee_cause', 'children' => 'fee_children'];
  if (!isset($map[$category])) return 0;
  $st = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
  $st->execute([$map[$category]]);
  $rupees = (int)preg_replace('/\D/', '', (string)$st->fetchColumn());
  return $rupees * 100;
}

function new_reg_no(PDO $pdo): string {
  for ($i = 0; $i < 6; $i++) {
    $c = 'HM27-' . random_int(100000, 999999);
    $chk = $pdo->prepare('SELECT 1 FROM registrations WHERE reg_no = ?');
    $chk->execute([$c]);
    if (!$chk->fetch()) return $c;
  }
  return '';
}
