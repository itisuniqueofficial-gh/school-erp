<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Generates automated, non-destructive reminders as in-app notifications:
 * birthdays, upcoming fee due dates, upcoming examinations, and holidays.
 * Intended to be run daily via cron (`php bin/console reminders`).
 */
final class ReminderService
{
    /** @return array<string,int> counts of reminders created per type */
    public function run(): array
    {
        return [
            'birthdays' => $this->birthdays(),
            'fees_due'  => $this->feesDue(),
            'exams'     => $this->upcomingExams(),
            'holidays'  => $this->upcomingHolidays(),
        ];
    }

    private function notify(int $userId, string $type, string $title, string $body): void
    {
        try {
            QueryBuilder::table('notifications')->insert([
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => $title,
                'body'       => mb_substr($body, 0, 480),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    private function birthdays(): int
    {
        $count = 0;
        try {
            $today = date('m-d');
            $students = QueryBuilder::table('students')
                ->select(['id', 'user_id', 'first_name', 'dob'])
                ->whereNotNull('dob')->whereNull('deleted_at')->get();
            foreach ($students as $s) {
                if ($s['dob'] && date('m-d', strtotime((string) $s['dob'])) === $today && !empty($s['user_id'])) {
                    $this->notify((int) $s['user_id'], 'birthday', 'Happy Birthday!', 'Wishing you a wonderful day, ' . $s['first_name'] . '!');
                    $count++;
                }
            }
        } catch (\Throwable) {
        }
        return $count;
    }

    private function feesDue(int $withinDays = 7): int
    {
        $count = 0;
        try {
            $limit = date('Y-m-d', time() + $withinDays * 86400);
            $invoices = QueryBuilder::table('fee_invoices')
                ->select(['fee_invoices.id', 'fee_invoices.due_date', 'students.user_id', 'fee_invoices.invoice_no'])
                ->leftJoin('students', 'fee_invoices.student_id', '=', 'students.id')
                ->whereIn('fee_invoices.status', ['unpaid', 'partial'])
                ->whereNotNull('fee_invoices.due_date')
                ->where('fee_invoices.due_date', '<=', $limit)
                ->get();
            foreach ($invoices as $inv) {
                if (!empty($inv['user_id'])) {
                    $this->notify((int) $inv['user_id'], 'fee_due', 'Fee payment due', 'Invoice ' . $inv['invoice_no'] . ' is due on ' . $inv['due_date'] . '.');
                    $count++;
                }
            }
        } catch (\Throwable) {
        }
        return $count;
    }

    private function upcomingExams(int $withinDays = 7): int
    {
        $count = 0;
        try {
            $limit = date('Y-m-d', time() + $withinDays * 86400);
            $exams = QueryBuilder::table('exams')
                ->whereNotNull('start_date')
                ->where('start_date', '>=', date('Y-m-d'))
                ->where('start_date', '<=', $limit)
                ->get();
            if ($exams === []) {
                return 0;
            }
            // Notify all students of upcoming exams.
            $students = QueryBuilder::table('students')->select(['user_id'])->whereNotNull('user_id')->whereNull('deleted_at')->get();
            foreach ($exams as $exam) {
                foreach ($students as $s) {
                    $this->notify((int) $s['user_id'], 'exam', 'Upcoming examination', $exam['name'] . ' begins on ' . $exam['start_date'] . '.');
                    $count++;
                }
            }
        } catch (\Throwable) {
        }
        return $count;
    }

    private function upcomingHolidays(int $withinDays = 3): int
    {
        $count = 0;
        try {
            $limit = date('Y-m-d', time() + $withinDays * 86400);
            $holidays = QueryBuilder::table('holidays')
                ->where('date', '>=', date('Y-m-d'))->where('date', '<=', $limit)->get();
            if ($holidays === []) {
                return 0;
            }
            $users = QueryBuilder::table('users')->select(['id'])->where('is_active', 1)->whereNull('deleted_at')->get();
            foreach ($holidays as $h) {
                foreach ($users as $u) {
                    $this->notify((int) $u['id'], 'holiday', 'Upcoming holiday', $h['name'] . ' on ' . $h['date'] . '.');
                    $count++;
                }
            }
        } catch (\Throwable) {
        }
        return $count;
    }
}
