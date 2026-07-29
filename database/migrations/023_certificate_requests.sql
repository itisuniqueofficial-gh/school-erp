-- ===========================================================================
-- 023 - Certificate request & approval workflow.
--
-- Students (or staff on their behalf) request a certificate stating a purpose;
-- authorised staff review and approve/reject. Approval auto-issues the actual
-- certificate via the existing certificate engine and links it here. Requests
-- are never deleted (history preserved). Additive & data-safe.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `certificate_requests` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `req_uid`        VARCHAR(24)  NOT NULL,
  `student_id`     BIGINT UNSIGNED NOT NULL,
  `type`           VARCHAR(40)  NOT NULL,
  `purpose`        VARCHAR(100) DEFAULT NULL,
  `reason`         VARCHAR(500) DEFAULT NULL,
  `status`         ENUM('submitted','under_review','changes_requested','approved','rejected','generated','cancelled') NOT NULL DEFAULT 'submitted',
  `certificate_id` BIGINT UNSIGNED DEFAULT NULL,
  `cert_uid`       VARCHAR(24)  DEFAULT NULL,
  `requested_by`   BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_by`    BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at`    DATETIME     DEFAULT NULL,
  `review_note`    VARCHAR(500) DEFAULT NULL,
  `created_at`     DATETIME DEFAULT NULL,
  `updated_at`     DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cert_requests_uid` (`req_uid`),
  KEY `idx_cert_requests_student` (`student_id`),
  KEY `idx_cert_requests_status` (`status`),
  CONSTRAINT `fk_cert_requests_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
