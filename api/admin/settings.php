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
  'earlybird_tier1_pct', 'earlybird_tier1_until', 'earlybird_tier2_pct', 'earlybird_tier2_until',
  'contact_phone_reg', 'contact_phone_general', 'contact_phone_emergency',
  'contact_email_reg', 'contact_email_info', 'contact_email_sponsor',
  'contact_phone_sponsor', 'disabled_note',
  'time_half_assembly', 'time_half_flag', 'time_cross_assembly', 'time_cross_flag',
  'time_cause_assembly', 'time_cause_flag', 'time_children_assembly', 'time_children_flag',
  'time_disabled_assembly', 'time_disabled_flag',
  'time_anthem', 'time_cultural', 'time_awards',
  'note_health_1', 'note_health_2', 'note_health_3',
  'note_timing_1', 'note_timing_2', 'note_timing_3',
  'cat_half_name', 'cat_half_subtitle', 'cat_half_distance', 'cat_half_live', 'cat_half_tags', 'cat_half_ages',
  'cat_cross_name', 'cat_cross_subtitle', 'cat_cross_distance', 'cat_cross_live', 'cat_cross_tags', 'cat_cross_ages',
  'cat_cause_name', 'cat_cause_subtitle', 'cat_cause_distance', 'cat_cause_live', 'cat_cause_tags', 'cat_cause_ages',
  'cat_children_name', 'cat_children_subtitle', 'cat_children_distance', 'cat_children_live', 'cat_children_tags', 'cat_children_ages',
  'cat_disabled_name', 'cat_disabled_subtitle', 'cat_disabled_distance', 'cat_disabled_live', 'cat_disabled_tags', 'cat_disabled_ages',
  'race_title_main', 'race_title_accent', 'race_subtitle',
  'label_age_categories', 'label_assembly', 'label_flagoff',
  'tribute_eyebrow', 'tribute_heading',
  'route_eyebrow', 'route_title_main', 'route_title_accent', 'route_subtitle',
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
