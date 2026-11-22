-- SMART-DTR: restore id columns that lost AUTO_INCREMENT during a phpMyAdmin import.
--
-- Symptom on the registration page:
--   Registration could not be saved because of a server problem.
-- The database error behind that message is:
--   SQLSTATE[HY000]: General error: 1364 Field 'id' doesn't have a default value
--
-- Prefer the app command (from the project directory, over SSH):
--   php artisan smartdtr:repair-schema
--
-- Or paste this file into phpMyAdmin (SQL tab) or run:
--   mysql -u USER -p DATABASE < database/repair-auto-increment.sql
--
-- Safe to run more than once. Existing rows and their ids are kept.
-- staff_users, personnel, and audit_logs are first because registration writes those.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `staff_users` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `personnel` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `audit_logs` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `staff_users` MODIFY `role` ENUM('employee', 'teacher', 'non_teaching', 'cleaning_staff') NOT NULL DEFAULT 'employee';

ALTER TABLE `admin_users` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `attendance` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `attendance_logs` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `clearance_requests` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `compensatory_time_offs` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `device_heartbeats` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `document_signatories` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `face_enrollments` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `failed_jobs` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `file_201_categories` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `holidays` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `jobs` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `leave_balances` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `leave_cards` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `leave_records` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `leave_requests_data` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `locator_requests` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `medical_certificates` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `migrations` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `personal_data_sheets` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `personnel_201_files` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `personnel_daily_status` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `position_description_forms` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `ris_items` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `ris_slips` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `rsmi_items` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `rsmi_reports` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `schedule` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `schedule_overrides` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `service_records` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `travel_orders` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `work_experience_sheets` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;

SET FOREIGN_KEY_CHECKS = 1;
