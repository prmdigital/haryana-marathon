<?php
/**
 * Admin settings: GET all, or POST { settings: {key: value, ...} } to update.
 * Only whitelisted keys can be written.
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();

// Keys the admin panel is allowed to edit.
const EDITABLE_KEYS = [
  'countdown_target', 'registration_open_label',
  'fee_half', 'fee_cross', 'fee_cause', 'fee_children',
  'earlybird_enabled', 'earlybird_text1', 'earlybird_text2',
  'contact_phone_reg', 'contact_phone_general', 'contact_phone_emergency',
  'contact_email_reg', 'contact_email_info', 'contact_email_sponsor',
];

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
  $out = [];
  foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
  json_out(['ok' => true, 'settings' => $out, 'editable' => EDITABLE_KEYS]);
}

require_method('POST');
require_csrf();

$in = read_json_body();
$settings = $in['settings'] ?? null;
if (!is_array($settings)) json_err('No settings provided.', 400);

$stmt = $pdo->prepare(
  'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);
$updated = 0;
foreach ($settings as $key => $value) {
  if (!in_array($key, EDITABLE_KEYS, true)) continue;
  $stmt->execute([$key, clean((string)$value, 2000)]);
  $updated++;
}

json_out(['ok' => true, 'updated' => $updated]);
