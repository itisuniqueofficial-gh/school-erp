# Database Design

The schema targets **MySQL 8+** (InnoDB, `utf8mb4`). It is fully normalized with
foreign keys, indexes on all lookup/foreign-key columns, unique constraints on
natural keys, and soft-delete columns (`deleted_at`) on primary entities.

- Migrations: `database/migrations/*.sql` (run in filename order).
- Consolidated import: `database/schema.sql`.
- Seed data: `database/seeds/DatabaseSeeder.php`.
- Runner/CLI: `php bin/console migrate` / `seed` / `migrate:status`.

Total tables: **66** (verified) plus the `migrations` tracking table.

## Domain overview

| Domain          | Key tables |
|-----------------|-----------|
| Auth & RBAC     | `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `remember_tokens`, `password_resets`, `api_tokens`, `audit_logs` |
| Academic        | `academic_years`, `classes`, `sections`, `subjects`, `class_subjects`, `syllabi` |
| Staff           | `departments`, `staff`, `teacher_qualifications`, `teacher_subjects` |
| Students        | `students`, `guardians`, `student_guardians`, `student_documents`, `student_promotions` |
| Attendance/TT   | `student_attendance`, `staff_attendance`, `rooms`, `timetable_slots`, `timetables` |
| Examinations    | `exam_types`, `exams`, `exam_schedules`, `grades`, `exam_marks`, `report_cards` |
| Fees            | `fee_categories`, `fee_structures`, `fee_structure_items`, `fee_discounts`, `fee_invoices`, `fee_invoice_items`, `fee_payments` |
| Library         | `book_categories`, `books`, `book_issues` |
| Transport       | `transport_routes`, `vehicles`, `transport_assignments` |
| Hostel          | `hostels`, `hostel_rooms`, `hostel_allocations` |
| Inventory       | `item_categories`, `vendors`, `inventory_items`, `purchases`, `purchase_items` |
| HR & Payroll    | `leave_types`, `leaves`, `holidays`, `payrolls` |
| Communication   | `notices`, `messages`, `message_recipients`, `notifications`, `communication_logs` |
| Settings        | `settings` |

## Core relationships (ER)

```mermaid
erDiagram
    users ||--o{ user_roles : has
    roles ||--o{ user_roles : assigned
    roles ||--o{ role_permissions : grants
    permissions ||--o{ role_permissions : in
    users ||--o| students : "is (optional)"
    users ||--o| staff : "is (optional)"
    users ||--o| guardians : "is (optional)"

    classes ||--o{ sections : contains
    classes ||--o{ class_subjects : offers
    subjects ||--o{ class_subjects : in
    academic_years ||--o{ students : enrolls

    classes ||--o{ students : groups
    sections ||--o{ students : groups
    students ||--o{ student_guardians : has
    guardians ||--o{ student_guardians : of
    students ||--o{ student_documents : owns
    students ||--o{ student_attendance : records

    staff ||--o{ teacher_subjects : teaches
    subjects ||--o{ teacher_subjects : taught
    staff ||--o{ staff_attendance : records

    exams ||--o{ exam_schedules : schedules
    exam_schedules ||--o{ exam_marks : records
    students ||--o{ exam_marks : scores
    exams ||--o{ report_cards : produces
    grades ||--o{ exam_marks : grades

    fee_structures ||--o{ fee_structure_items : contains
    students ||--o{ fee_invoices : billed
    fee_invoices ||--o{ fee_invoice_items : contains
    fee_invoices ||--o{ fee_payments : "paid by"

    books ||--o{ book_issues : issued
    students ||--o{ book_issues : borrows
    transport_routes ||--o{ transport_assignments : serves
    students ||--o| transport_assignments : uses
```

## Conventions

- Primary keys: `BIGINT UNSIGNED AUTO_INCREMENT` for high-volume tables, `INT UNSIGNED` for lookups.
- Timestamps: `created_at`, `updated_at` (nullable `DATETIME`, set by the ORM layer).
- Soft deletes: `deleted_at` on `users`, `staff`, `students`.
- Money: `DECIMAL(12,2)`. Percentages/GPA: `DECIMAL(5,2)`/`DECIMAL(4,2)`.
- Enumerations use MySQL `ENUM` for constrained status columns.
- Every foreign key has an explicit `ON DELETE` policy (`CASCADE` for owned rows,
  `SET NULL` for optional references).
