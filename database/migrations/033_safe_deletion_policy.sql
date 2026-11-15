-- ===========================================================================
-- 033 - Safe deletion policy: history can no longer be destroyed by a cascade.
--
-- Before this migration, hard-deleting a student, staff member, class, section,
-- academic year, exam or invoice silently cascaded into fee invoices, payments,
-- marks, attendance, report cards, certificates, enrollments, payroll, ...
-- Those foreign keys now RESTRICT: the database refuses the delete while any
-- financial or academic record still depends on the row. The application
-- already soft-deletes students and staff (deleted_at); this makes the database
-- enforce the same rule for any other path (manual SQL, bugs, imports).
-- Purely structural: no row is changed or removed. Each statement swaps one
-- constraint atomically in a single ALTER (new constraint `<name>_r` added, old one dropped;
-- MariaDB rejects re-using a name inside one ALTER). PostgreSQL runs the whole file in one transaction. Link tables (guardians, roles, tokens) keep CASCADE.
-- ===========================================================================

ALTER TABLE `certificate_requests` ADD CONSTRAINT `fk_cert_requests_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_cert_requests_student`;
ALTER TABLE `certificates` ADD CONSTRAINT `fk_certificates_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_certificates_student`;
ALTER TABLE `exam_marks` ADD CONSTRAINT `fk_mark_schedule_r` FOREIGN KEY (`exam_schedule_id`) REFERENCES `exam_schedules` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_mark_schedule`;
ALTER TABLE `exam_marks` ADD CONSTRAINT `fk_mark_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_mark_student`;
ALTER TABLE `exam_schedules` ADD CONSTRAINT `fk_es_exam_r` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_es_exam`;
ALTER TABLE `fee_invoices` ADD CONSTRAINT `fk_inv_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_inv_student`;
ALTER TABLE `fee_payments` ADD CONSTRAINT `fk_pay_invoice_r` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_pay_invoice`;
ALTER TABLE `fee_payments` ADD CONSTRAINT `fk_pay_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_pay_student`;
ALTER TABLE `hall_tickets` ADD CONSTRAINT `fk_hall_tickets_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_hall_tickets_student`;
ALTER TABLE `leaves` ADD CONSTRAINT `fk_leave_staff_r` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_leave_staff`;
ALTER TABLE `payrolls` ADD CONSTRAINT `fk_payroll_staff_r` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_payroll_staff`;
ALTER TABLE `report_card_versions` ADD CONSTRAINT `fk_rcv_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_rcv_student`;
ALTER TABLE `report_cards` ADD CONSTRAINT `fk_rc_exam_r` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_rc_exam`;
ALTER TABLE `report_cards` ADD CONSTRAINT `fk_rc_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_rc_student`;
ALTER TABLE `staff_attendance` ADD CONSTRAINT `fk_satt_staff_r` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_satt_staff`;
ALTER TABLE `student_attendance` ADD CONSTRAINT `fk_att_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_att_student`;
ALTER TABLE `student_documents` ADD CONSTRAINT `fk_sd_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_sd_student`;
ALTER TABLE `student_enrollments` ADD CONSTRAINT `fk_enr_class_r` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_enr_class`;
ALTER TABLE `student_enrollments` ADD CONSTRAINT `fk_enr_section_r` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_enr_section`;
ALTER TABLE `student_enrollments` ADD CONSTRAINT `fk_enr_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_enr_student`;
ALTER TABLE `student_enrollments` ADD CONSTRAINT `fk_enr_year_r` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_enr_year`;
ALTER TABLE `student_promotions` ADD CONSTRAINT `fk_sp_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_sp_student`;
ALTER TABLE `subject_attendance` ADD CONSTRAINT `fk_subatt_class_r` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_subatt_class`;
ALTER TABLE `subject_attendance` ADD CONSTRAINT `fk_subatt_section_r` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_subatt_section`;
ALTER TABLE `subject_attendance` ADD CONSTRAINT `fk_subatt_student_r` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_subatt_student`;
ALTER TABLE `subject_attendance` ADD CONSTRAINT `fk_subatt_subject_r` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_subatt_subject`;
ALTER TABLE `subject_attendance` ADD CONSTRAINT `fk_subatt_year_r` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_subatt_year`;
ALTER TABLE `teacher_assignment_history` ADD CONSTRAINT `fk_tah_staff_r` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE RESTRICT, DROP FOREIGN KEY `fk_tah_staff`;

-- Permission for the database administration dashboard (roles that already manage settings).
INSERT IGNORE INTO `permissions` (`slug`, `name`, `module`, `created_at`, `updated_at`)
VALUES ('database.manage', 'Manage database', 'database', NOW(), NOW());

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT rp.`role_id`, np.`id`
FROM `role_permissions` rp
JOIN `permissions` op ON op.`id` = rp.`permission_id` AND op.`slug` = 'settings.manage'
JOIN `permissions` np ON np.`slug` = 'database.manage';
