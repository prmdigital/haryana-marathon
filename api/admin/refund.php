<?php
/**
 * Admin: refund a PAID registration through Razorpay.
 * POST (JSON): { id: <registration id>, amount?: <rupees — optional, partial> }
 * No amount  -> full refund of what was paid.
 * Marks the registration 'refunded' and records the Razorpay refund id.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_method('POST');
require_admin();
require_csrf();

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
  json_err('Razorpay is not configured on the server.', 503);
}

$in = read_json_body();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) json_err('Missing registration id.', 400);

$pdo = db();
$stmt = $pdo->prepare('SELECT id, reg_no, full_name, status, amount_paise, razorpay_payment_id FROM registrations WHERE id = ?');
$stmt->execute([$id]);
$reg = $stmt->fetch();

if (!$reg)                              json_err('Registration not found.', 404);
if ($reg['status'] !== 'paid')          json_err('Only a paid registration can be refunded.', 400);
$paymentId = (string)($reg['razorpay_payment_id'] ?? '');
if ($paymentId === '')                  json_err('No Razorpay payment is linked to this registration.', 400);

// Amount: full by default; optional partial (rupees in, paise out), capped at the paid amount.
$paid = (int)$reg['amount_paise'];
$amountPaise = $paid;
if (isset($in['amount']) && $in['amount'] !== '' && $in['amount'] !== null) {
  $amountPaise = (int)round(((float)$in['amount']) * 100);
  if ($amountPaise < 100)    json_err('Refund amount is too small.', 400);
  if ($amountPaise > $paid)  json_err('Refund amount cannot exceed the amount paid.', 400);
}

// ---- Call the Razorpay refund API -----------------------------------------
$payload = [
  'amount' => $amountPaise,
  'speed'  => 'normal',
  'notes'  => ['reg_no' => $reg['reg_no']],
];
$ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 30,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => json_encode($payload),
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
  CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
]);
$resp = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($cerr !== '') json_err('Could not reach Razorpay. Please try again.', 502);
$body = json_decode((string)$resp, true);
if ($code !== 200 || empty($body['id'])) {
  $msg = $body['error']['description'] ?? 'Razorpay rejected the refund.';
  json_err($msg, 502);
}

// ---- Record it -------------------------------------------------------------
// Always flip the status (works even on an older table without the extra
// columns), then best-effort store the refund details.
$pdo->prepare('UPDATE registrations SET status = ? WHERE id = ?')->execute(['refunded', $id]);
try {
  $pdo->prepare(
    'UPDATE registrations
        SET razorpay_refund_id = ?, refund_amount_paise = ?, refunded_at = CURRENT_TIMESTAMP
      WHERE id = ?'
  )->execute([$body['id'], $amountPaise, $id]);
} catch (Throwable $e) {
  // Refund columns not present yet — status is already set, so the record is safe.
  error_log('[marathon refund] detail columns missing: ' . $e->getMessage());
}

json_out([
  'ok'           => true,
  'refundId'     => $body['id'],
  'status'       => 'refunded',
  'amount_paise' => $amountPaise,
  'full'         => $amountPaise >= $paid,
]);
