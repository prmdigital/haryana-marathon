<?php
/**
 * Razorpay webhook (optional but recommended). Marks a registration paid even
 * if the runner's browser closed before verify-payment.php ran.
 * Set the webhook URL in Razorpay to: https://your-domain/api/webhook.php
 * and put the webhook secret in config.local.php (RAZORPAY_WEBHOOK_SECRET).
 */
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$secret = RAZORPAY_WEBHOOK_SECRET;
if ($secret === '') { http_response_code(200); echo 'webhook not configured'; exit; }

$payload = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
if (!hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
  http_response_code(400); echo 'bad signature'; exit;
}

$data  = json_decode($payload, true) ?: [];
$event = $data['event'] ?? '';

if ($event === 'payment.captured' || $event === 'order.paid') {
  $pay   = $data['payload']['payment']['entity'] ?? [];
  $ord   = $data['payload']['order']['entity'] ?? [];
  $orderId   = $pay['order_id'] ?? ($ord['id'] ?? '');
  $paymentId = $pay['id'] ?? '';
  if ($orderId !== '') {
    try {
      db()->prepare(
        'UPDATE registrations
            SET status = "paid",
                razorpay_payment_id = COALESCE(razorpay_payment_id, ?),
                paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP)
          WHERE razorpay_order_id = ? AND status <> "paid"'
      )->execute([$paymentId !== '' ? $paymentId : null, $orderId]);
    } catch (Throwable $e) {
      error_log('[marathon webhook] ' . $e->getMessage());
    }
  }
}

http_response_code(200);
echo 'ok';
