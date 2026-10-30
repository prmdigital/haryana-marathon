<?php
/**
 * Public endpoint: start a PAID registration and create a Razorpay order.
 * Accepts multipart/form-data (same fields as register.php, incl. idFile).
 * Returns { ok, keyId, orderId, amount, currency, regNo, name, email, mobile }.
 */
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/registration.php';

require_method('POST');

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
  json_err('Online payment is not set up yet. Please contact the organisers.', 503);
}

[$d, $errors] = collect_registration();
if ($errors) json_err('Please correct the form.', 422, ['errors' => $errors]);

if ($d['category'] === 'disabled' || $d['freeEligible']) {
  json_err('This is a free registration — no payment needed.', 400);
}

$amount = fee_paise($d['category']);
// Auto-apply the current early-bird discount (server-side, never trust the client).
$pct = early_bird_pct();
if ($pct > 0) $amount = (int)round($amount * (100 - $pct) / 100);
if ($amount < 100) json_err('This category does not require online payment.', 400);

[$idPath, $idName, $idErr] = store_id_file();
if ($idErr) json_err($idErr, 422);

$age = age_on_race_day_from($d['dob']);
$pdo = db();
$regNo = new_reg_no($pdo);
if ($regNo === '') json_err('Could not generate a registration number. Please retry.', 500);

// Write the pending row BEFORE opening checkout, so a payment can always be
// traced to a person even if the browser is closed mid-transaction.
try {
  $pdo->prepare(
    'INSERT INTO registrations
      (reg_no, full_name, email, mobile, category, category_label, dob, age_on_race_day,
       gender, state, city, tshirt_size, id_proof_type, id_file_path, id_file_name,
       emergency_phone, free_eligible, status, amount_paise, ip_address)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
  )->execute([
    $regNo, $d['fullName'], $d['email'], $d['mobile'], $d['category'], $d['categoryLabel'], $d['dob'], $age,
    $d['gender'], $d['state'], $d['city'], $d['tshirt'], $d['idType'], $idPath, $idName,
    ($d['emergency'] !== '' ? $d['emergency'] : null), 0, 'pending', $amount, client_ip(),
  ]);
} catch (Throwable $e) {
  json_err('Could not start your registration. Please try again.', 500);
}

// ---- Create the Razorpay order -------------------------------------------
$order = [
  'amount' => $amount, 'currency' => 'INR', 'receipt' => $regNo, 'payment_capture' => 1,
  'notes' => [
    'event'    => 'Shaheed-E-Azam Bhagat Singh Haryana Marathon 2027',
    'reg_no'   => $regNo,
    'category' => $d['categoryLabel'],
  ],
];
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 30,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => json_encode($order),
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
  CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
]);
$resp = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($cerr !== '') json_err('Could not reach the payment gateway. Please try again.', 502);
$body = json_decode((string)$resp, true);
if ($code === 401) json_err('Online payment is not configured correctly. Please contact the organisers.', 500);
if ($code !== 200 || empty($body['id'])) {
  json_err('The payment gateway rejected the request. Please try again.', 502);
}

$pdo->prepare('UPDATE registrations SET razorpay_order_id = ? WHERE reg_no = ?')->execute([$body['id'], $regNo]);

json_out([
  'ok'            => true,
  'keyId'         => RAZORPAY_KEY_ID,
  'orderId'       => $body['id'],
  'amount'        => (int)$body['amount'],
  'currency'      => $body['currency'],
  'regNo'         => $regNo,
  'name'          => $d['fullName'],
  'email'         => $d['email'],
  'mobile'        => $d['mobile'],
  'categoryLabel' => $d['categoryLabel'],
]);
