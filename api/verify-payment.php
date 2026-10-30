<?php
/**
 * Public endpoint: verify a Razorpay payment and mark the registration paid.
 * Request : { razorpay_order_id, razorpay_payment_id, razorpay_signature }
 */
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';

require_method('POST');
$in = read_json_body();
$orderId   = trim((string)($in['razorpay_order_id'] ?? ''));
$paymentId = trim((string)($in['razorpay_payment_id'] ?? ''));
$signature = trim((string)($in['razorpay_signature'] ?? ''));
if ($orderId === '' || $paymentId === '' || $signature === '') {
  json_err('Incomplete payment details.', 400);
}

// Razorpay signs "<order_id>|<payment_id>" with the key secret. Without this
// check anyone could claim a payment succeeded.
$expected = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $signature)) {
  json_err('We could not verify that payment.', 400);
}

$pdo = db();
$st = $pdo->prepare('SELECT reg_no, status FROM registrations WHERE razorpay_order_id = ? LIMIT 1');
$st->execute([$orderId]);
$reg = $st->fetch();
if (!$reg) json_err('We could not find that registration.', 404);

if ($reg['status'] === 'paid') {
  json_out(['ok' => true, 'regNo' => $reg['reg_no'], 'alreadyConfirmed' => true]);
}

$pdo->prepare(
  'UPDATE registrations
      SET status = "paid", razorpay_payment_id = ?, paid_at = CURRENT_TIMESTAMP
    WHERE razorpay_order_id = ? AND status <> "paid"'
)->execute([$paymentId, $orderId]);

json_out(['ok' => true, 'regNo' => $reg['reg_no']]);
