<?php
/**
 * Public endpoint: return the editable site settings as a JSON object.
 * The front-end merges these over its built-in defaults, so missing keys or a
 * failed request simply fall back to the hard-coded values (layout unaffected).
 */
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';

require_method('GET');

try {
  $rows = db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
} catch (Throwable $e) {
  json_err('Could not load settings.', 500);
}

$out = [];
foreach ($rows as $r) {
  $out[$r['setting_key']] = $r['setting_value'];
}

// Cache a little at the CDN/browser; content changes are not second-critical.
header('Cache-Control: public, max-age=60');
json_out(['ok' => true, 'settings' => $out]);
