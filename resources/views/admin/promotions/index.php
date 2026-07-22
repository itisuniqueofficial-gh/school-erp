<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $years */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $exams */
/** @var array<string,mixed> $filters */
/** @var array<int,array<string,mixed>> $review */
$classNames = [];
foreach ($classes as $c) { $classNames[(int) $c['id']] = (string) $c['name']; }
$resultBadge = ['promoted' => 'badge-green', 'failed' => 'badge-red', 'graduated' => 'badge-blue', 'no_result' => 'badge-slate'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Promotion &amp; Rollover<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <i class="fa-solid fa-arrow-up-right-dots mr-1"></i>
    Review exam-based eligibility, then promote selected students into the next class for a new academic year. History is preserved — new enrolments and roll numbers are created; nothing is overwritten.
</div>

<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div>
            <label class="form-label" for="from_year_id">Source academic year</label>
            <select id="from_year_id" name="from_year_id" class="form-select">
                <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>" <?= (int) $filters['from_year_id'] === (int) $y['id'] ? 'selected' : '' ?>><?= e((string) $y['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="class_id">Class (optional)</label>
            <select id="class_id" name="class_id" class="form-select"><option value="0">All classes</option>
                <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($filters['class_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="exam_id">Promotion exam (optional)</label>
            <select id="exam_id" name="exam_id" class="form-select"><option value="0">No exam (all eligible)</option>
                <?php foreach ($exams as $ex): ?><option value="<?= (int) $ex['id'] ?>" <?= (int) ($filters['exam_id'] ?? 0) === (int) $ex['id'] ? 'selected' : '' ?>><?= e((string) $ex['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end"><button class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> Evaluate</button></div>
    </div>
</form>

<form method="POST" action="<?= e(url('/admin/promotions/apply')) ?>" class="card overflow-hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="from_year_id" value="<?= (int) $filters['from_year_id'] ?>">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
        <span class="text-sm text-slate-500">Promote selected into year:</span>
        <select name="to_year_id" class="form-select w-auto" required>
            <option value="">Target year</option>
            <?php foreach ($years as $y): ?><?php if ((int) $y['id'] !== (int) $filters['from_year_id']): ?><option value="<?= (int) $y['id'] ?>"><?= e((string) $y['name']) ?></option><?php endif; ?><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" data-confirm="Apply promotion to the selected students? History is preserved."><i class="fa-solid fa-check"></i> Apply promotion</button>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table table-cards">
            <thead><tr>
                <th class="w-8"><input type="checkbox" data-check-all></th>
                <th>Student</th><th>Current class</th><th>Roll</th><th>Exam %</th><th>Result</th><th>Action</th><th>Next class</th>
            </tr></thead>
            <tbody>
            <?php if (empty($review)): ?>
                <tr><td colspan="8" class="py-10 text-center text-slate-400">Select a source academic year and click Evaluate.</td></tr>
            <?php else: foreach ($review as $r): $sid = (int) $r['student_id']; $graduating = $r['result'] === 'graduated'; ?>
                <tr>
                    <td data-label=""><input type="checkbox" name="student_ids[]" value="<?= $sid ?>" class="row-check" <?= $r['result'] === 'no_result' ? '' : 'checked' ?>></td>
                    <td data-label="Student"><span class="font-medium text-slate-800"><?= e((string) $r['name']) ?></span><span class="block font-mono text-[11px] text-slate-400"><?= e((string) $r['uid']) ?></span></td>
                    <td data-label="Class"><?= e((string) $r['from_class']) ?></td>
                    <td data-label="Roll"><?= e((string) ($r['roll_no'] ?? '—')) ?></td>
                    <td data-label="Exam %"><?= $r['percentage'] !== null ? e((string) $r['percentage']) . '%' : '—' ?></td>
                    <td data-label="Result"><span class="badge <?= $resultBadge[$r['result']] ?? 'badge-slate' ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $r['result']))) ?></span></td>
                    <td data-label="Action">
                        <select name="decisions[<?= $sid ?>][action]" class="form-select">
                            <option value="promote" <?= $r['result'] === 'promoted' ? 'selected' : '' ?>>Promote</option>
                            <option value="repeat" <?= $r['result'] === 'failed' ? 'selected' : '' ?>>Repeat class</option>
                            <option value="graduated" <?= $graduating ? 'selected' : '' ?>>Graduate</option>
                            <option value="hold">Hold</option>
                        </select>
                        <input type="hidden" name="decisions[<?= $sid ?>][to_section_id]" value="<?= (int) ($r['from_section_id'] ?? 0) ?>">
                    </td>
                    <td data-label="Next class">
                        <select name="decisions[<?= $sid ?>][to_class_id]" class="form-select">
                            <option value="<?= (int) $r['from_class_id'] ?>">Repeat: <?= e((string) $r['from_class']) ?></option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) ($r['next_class_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</form>
<?php $this->endSection(); ?>
<?php $this->section('page_scripts'); ?>
<script>$(function(){ $('[data-check-all]').on('change', function(){ $('.row-check').prop('checked', this.checked); }); });</script>
<?php $this->endSection(); ?>
