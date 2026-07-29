-- ===========================================================================
-- 025 - Hall tickets / admit cards.
--
-- Exam entry documents scoped to academic year -> exam -> class -> section ->
-- student, with a type (hall ticket / back paper / repeater / supplementary /
-- practical / board / custom) and a lifecycle status. A unique public id
-- (HT-YYYY-NNNNN) backs the public QR verification. UNIQUE(exam, student, type)
-- prevents duplicate tickets. Additive & data-safe; does not touch exams/marks.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `hall_tickets` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ht_uid`             VARCHAR(24)  NOT NULL,
  `exam_id`            BIGINT UNSIGNED NOT NULL,
  `student_id`         BIGINT UNSIGNED NOT NULL,
  `class_id`           BIGINT UNSIGNED DEFAULT NULL,
  `section_id`         BIGINT UNSIGNED DEFAULT NULL,
  `type`               VARCHAR(40)  NOT NULL DEFAULT 'hall_ticket',
  `attempt_type`       VARCHAR(30)  NOT NULL DEFAULT 'regular',
  `status`             ENUM('draft','pending','approved','issued','revoked','expired') NOT NULL DEFAULT 'issued',
  `roll_no`            VARCHAR(30)  DEFAULT NULL,
  `exam_center`        VARCHAR(160) DEFAULT NULL,
  `instructions`       TEXT         DEFAULT NULL,
  `signature_required` TINYINT(1)   NOT NULL DEFAULT 0,
  `stamp_required`     TINYINT(1)   NOT NULL DEFAULT 0,
  `valid_from`         DATE         DEFAULT NULL,
  `valid_to`           DATE         DEFAULT NULL,
  `issued_by`          BIGINT UNSIGNED DEFAULT NULL,
  `issued_on`          DATE         NOT NULL,
  `revoked_reason`     VARCHAR(255) DEFAULT NULL,
  `created_at`         DATETIME DEFAULT NULL,
  `updated_at`         DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hall_tickets_uid` (`ht_uid`),
  UNIQUE KEY `uq_hall_tickets_exam_student_type` (`exam_id`, `student_id`, `type`),
  KEY `idx_hall_tickets_exam` (`exam_id`),
  KEY `idx_hall_tickets_student` (`student_id`),
  CONSTRAINT `fk_hall_tickets_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
