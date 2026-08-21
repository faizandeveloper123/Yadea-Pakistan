-- ============================================================
--  EVEE CRM - Dealer self-registration via website form
--  XAMPP MySQL (MariaDB 10.4+)
--
--  Adds:
--   staff_users.password_plain VARCHAR(255) - recoverable copy of
--   the password for accounts auto-created by the Dealership
--   Registration form, so the dealer can view it again under
--   Account settings. The API also adds this column lazily on
--   first use; this file just makes the step explicit/idempotent.
--
--  How to run (idempotent):
--    C:\xampp\mysql\bin\mysql.exe -u root < sql\dealer_registration.sql
-- ============================================================

USE evee_crm;

SET @pp_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'evee_crm'
    AND TABLE_NAME   = 'staff_users'
    AND COLUMN_NAME  = 'password_plain'
);
SET @pp_sql = IF(
  @pp_exists = 0,
  'ALTER TABLE staff_users ADD COLUMN password_plain VARCHAR(255) DEFAULT NULL AFTER password',
  'SELECT 1'
);
PREPARE stmt FROM @pp_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
