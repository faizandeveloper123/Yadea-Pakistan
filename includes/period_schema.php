<?php
if (!defined('HIIFI')) exit('Direct access not allowed.');

// Idempotent schema support for the Timetable / Class Period module.
// Safe to require on every page (CREATE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS / seed).

db_query("CREATE TABLE IF NOT EXISTS `period_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  UNIQUE KEY `uq_period_category_name` (`name`)
) ENGINE=InnoDB");

db_query("CREATE TABLE IF NOT EXISTS `class_periods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `section_id` INT DEFAULT NULL,
  `period_cat_id` INT DEFAULT NULL
) ENGINE=InnoDB");

try { db_query("ALTER TABLE `periods` ADD COLUMN IF NOT EXISTS `category_id` INT DEFAULT NULL"); } catch (Throwable $e) {}
try { db_query("ALTER TABLE `class_periods` ADD COLUMN IF NOT EXISTS `period_cat_id` INT DEFAULT NULL"); } catch (Throwable $e) {}

// Unique (class, section) index so bulk assignment can upsert safely.
try { db_query("ALTER TABLE `class_periods` ADD UNIQUE INDEX `uq_class_section` (`class_id`, `section_id`)"); } catch (Throwable $e) {}

// Seed default period categories if the table is empty.
$cnt = 0;
$r = @db_query("SELECT COUNT(*) AS c FROM period_categories");
if ($r && ($row = $r->fetch_assoc())) { $cnt = (int) $row['c']; }
if ($cnt === 0) {
    foreach (['Primary', 'Middle', 'High', 'BS'] as $cat) {
        $st = db_prepare("INSERT INTO period_categories (name) VALUES (?)");
        $st->bind_param('s', $cat);
        $st->execute();
    }
}