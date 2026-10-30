-- Shaheed-E-Azam Bhagat Singh Haryana Marathon 2027 — database schema
-- Run once (the setup.php page does this for you automatically).
-- Engine/charset chosen for broad Hostinger MySQL compatibility.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Registrations
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registrations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  reg_no          VARCHAR(20)  NOT NULL UNIQUE,
  full_name       VARCHAR(120) NOT NULL,
  email           VARCHAR(160) NOT NULL,
  mobile          VARCHAR(15)  NOT NULL,
  category        VARCHAR(40)  NOT NULL,
  category_label  VARCHAR(80)  NOT NULL,
  dob             VARCHAR(10)  NOT NULL,   -- stored as DD-MM-YYYY (as entered)
  age_on_race_day INT          NULL,
  gender          VARCHAR(20)  NOT NULL,
  state           VARCHAR(80)  NOT NULL,
  city            VARCHAR(80)  NOT NULL,
  tshirt_size     VARCHAR(10)  NOT NULL,
  id_proof_type   VARCHAR(40)  NOT NULL,
  id_file_path    VARCHAR(255) NULL,       -- relative path inside uploads/
  id_file_name    VARCHAR(255) NULL,       -- original filename
  emergency_phone VARCHAR(15)  NULL,
  free_eligible   TINYINT(1)   NOT NULL DEFAULT 0,
  status          VARCHAR(16)  NOT NULL DEFAULT 'free',   -- free | pending | paid | failed
  amount_paise    INT          NOT NULL DEFAULT 0,
  razorpay_order_id   VARCHAR(40) NULL,
  razorpay_payment_id VARCHAR(40) NULL,
  paid_at         TIMESTAMP    NULL,
  razorpay_refund_id  VARCHAR(40) NULL,
  refund_amount_paise INT          NULL,
  refunded_at         TIMESTAMP    NULL,
  ip_address      VARCHAR(45)  NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_category (category),
  INDEX idx_email (email),
  INDEX idx_status (status),
  INDEX idx_order (razorpay_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Admin users (login accounts for the admin panel)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    TIMESTAMP    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Editable site settings (key/value). The front-end fetches these and falls
-- back to its built-in defaults if any key is missing, so layout never breaks.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key   VARCHAR(60)  NOT NULL PRIMARY KEY,
  setting_value TEXT         NULL,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the editable keys with the current live values.
INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('countdown_target',        '2027-03-21T05:00:00+05:30'),
  ('registration_open_label', 'Till 14 March 2027'),
  ('fee_half',                '1,899'),
  ('fee_cross',               '1,499'),
  ('fee_cause',               '599'),
  ('fee_children',            '299'),
  ('earlybird_enabled',       '1'),
  ('earlybird_text1',         '40% OFF till Diwali'),
  ('earlybird_text2',         '30% OFF till 31 December'),
  ('earlybird_tier1_pct',     '40'),
  ('earlybird_tier1_until',   '2026-11-08'),
  ('earlybird_tier2_pct',     '30'),
  ('earlybird_tier2_until',   '2026-12-31'),
  ('contact_phone_reg',       '+91 73409 92413'),
  ('contact_phone_general',   '+91 81467 02026'),
  ('contact_phone_emergency', '+91 73411 24861'),
  ('contact_email_reg',       'registration@shaheedbhagatsinghmarathon.com'),
  ('contact_email_info',      'info@shaheedbhagatsinghmarathon.com'),
  ('contact_email_sponsor',   'sponsors@shaheedbhagatsinghmarathon.com'),
  ('contact_phone_sponsor',   '+91 98153 24861'),
  ('disabled_note',           'Free entry with dedicated support for differently-abled participants.'),
  ('time_half_assembly',      '5:00 AM'),
  ('time_half_flag',          '5:30 AM'),
  ('time_cross_assembly',     '5:40 AM'),
  ('time_cross_flag',         '6:15 AM'),
  ('time_cause_assembly',     '6:30 AM'),
  ('time_cause_flag',         '7:15 AM'),
  ('time_children_assembly',  '6:30 AM'),
  ('time_children_flag',      '7:20 AM'),
  ('time_disabled_assembly',  '6:30 AM'),
  ('time_disabled_flag',      '7:00 AM'),
  ('time_anthem',             '7:50 AM'),
  ('time_cultural',           '9:00 AM'),
  ('time_awards',             '9:30 AM'),
  ('note_health_1',           'Medical certificate recommended for 21KM participants'),
  ('note_health_2',           'Emergency medical teams on standby'),
  ('note_health_3',           'Hydration stations'),
  ('note_timing_1',           'Report 30 minutes before your race start time'),
  ('note_timing_2',           'Chip timing for accurate results: 21KM & 10KM'),
  ('note_timing_3',           'Follow traffic rules and marshal instructions'),
  ('cat_half_name',           'Half Marathon'),
  ('cat_half_subtitle',       ''),
  ('cat_half_distance',       '21'),
  ('cat_half_live',           'Timer Run'),
  ('cat_half_tags',           'Open for all M/W, Above 18 years'),
  ('cat_half_ages',           '18 to 35, Above 35 to 45, Above 45 to 55, Above 55 to 65, 65+'),
  ('cat_cross_name',          'Cross Country'),
  ('cat_cross_subtitle',      ''),
  ('cat_cross_distance',      '10'),
  ('cat_cross_live',          'Timer Run'),
  ('cat_cross_tags',          'Open for all M/W, Above 18 years'),
  ('cat_cross_ages',          '18 to 35, Above 35 to 45, Above 45 to 55, Above 55 to 65, 65+'),
  ('cat_cause_name',          'Run for Cause'),
  ('cat_cause_subtitle',      'Run for Drug-Free India'),
  ('cat_cause_distance',      '5'),
  ('cat_cause_live',          ''),
  ('cat_cause_tags',          'Open for all, Above 14 years'),
  ('cat_cause_ages',          'Above 14 and open for all age groups'),
  ('cat_children_name',       'Run for Little Champions'),
  ('cat_children_subtitle',   'Children under 14 years'),
  ('cat_children_distance',   '3'),
  ('cat_children_live',       ''),
  ('cat_children_tags',       'Under 14 years'),
  ('cat_children_ages',       'Under 14 years'),
  ('cat_disabled_name',       'Disabled Category'),
  ('cat_disabled_subtitle',   ''),
  ('cat_disabled_distance',   '1'),
  ('cat_disabled_live',       ''),
  ('cat_disabled_tags',       'Open for all age groups (M/W) differently abled'),
  ('cat_disabled_ages',       'Open for all age groups'),
  ('race_title_main',         'Race Categories &'),
  ('race_title_accent',       'Route'),
  ('race_subtitle',           'Choose your challenge and run for a cause. All races start from Tau Devi Lal Stadium, Panchkula.'),
  ('label_age_categories',    'Age Categories:'),
  ('label_assembly',          'Assembly Time:'),
  ('label_flagoff',           'Flag-off:'),
  ('tribute_eyebrow',         'Shaheed-E-Azam Bhagat Singh Ji'),
  ('tribute_heading',         'Honour the Sacrifice. Empower the Youth. Build a Drug-Free India.'),
  ('route_eyebrow',           'The Course'),
  ('route_title_main',        'Route Plan for the Shaheed-E-Azam Bhagat Singh'),
  ('route_title_accent',      'Haryana Marathon'),
  ('route_subtitle',          'Every race starts and finishes at Tau Devi Lal Stadium, Panchkula.')
ON DUPLICATE KEY UPDATE setting_key = setting_key; -- never overwrite edited values
