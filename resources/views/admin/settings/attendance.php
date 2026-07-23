<?php
/** @var \Core\View\View $this */
/** @var array<string,string> $config */
/** @var array<string,string> $types */
/** @var array<int,string> $days */
/** @var array<int,int> $weekend */
/** @var array<int,int> $working */
/** @var array<int,array{label:string,start:string,end:string,type:string}> $schedule */
/** @var string $endTime */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/settings')) ?>" class="hover:text-brand-600">Settings</a> / Attendance &amp; Calendar<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <i class="fa-solid fa-circle-info mr-1"></i>
    These settings apply across the whole ERP — attendance entry, dashboards, timetable, reports and APIs. Nothing is hardcoded; changing a value here reconfigures the system with no code changes.
</div>

<form method="POST" action="<?= e(url('/admin/settings/attendance')) ?>" class="space-y-6" data-loading>
    <?= csrf_field() ?>

    <!-- Attendance type -->
    <div class="card p-5">
        <h2 class="mb-1 font-semibold text-slate-800">Attendance Type</h2>
        <p class="mb-4 text-sm text-slate-500">Controls how attendance is captured everywhere (entry screens, dashboards, reports, APIs).</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label" for="attendance_type">Capture mode</label>
                <select id="attendance_type" name="attendance_type" class="form-input">
                    <?php foreach ($types as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= ($config['attendance_type'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-slate-400">Daily = one mark per day. Lecture/Period = per timetable slot. Session = morning/afternoon. Manual = free entry.</p>
            </div>
        </div>
    </div>

    <!-- Working week -->
    <div class="card p-5">
        <h2 class="mb-1 font-semibold text-slate-800">Working Week</h2>
        <p class="mb-4 text-sm text-slate-500">Select your weekend day(s). Working days and holiday blocking update automatically.</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label" for="week_start_day">Week starts on</label>
                <select id="week_start_day" name="week_start_day" class="form-input">
                    <?php foreach ($days as $num => $label): ?>
                        <option value="<?= (int) $num ?>" <?= (int) ($config['week_start_day'] ?? 1) === (int) $num ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <span class="form-label">Weekend day(s)</span>
                <div class="mt-1 flex flex-wrap gap-2">
                    <?php foreach ($days as $num => $label): ?>
                        <label class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm">
                            <input type="checkbox" name="weekend_days[]" value="<?= (int) $num ?>" <?= in_array((int) $num, $weekend, true) ? 'checked' : '' ?>>
                            <?= e(substr($label, 0, 3)) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm">
            <span class="font-medium text-slate-600">Working days preview:</span>
            <?php if ($working === []): ?>
                <span class="text-red-600">None — every day is marked as a weekend.</span>
            <?php else: ?>
                <span class="text-slate-800"><?= e(implode(', ', array_map(static fn ($d) => $days[$d], $working))) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Time arrangement -->
    <div class="card p-5">
        <h2 class="mb-1 font-semibold text-slate-800">Automatic Time Arrangement</h2>
        <p class="mb-4 text-sm text-slate-500">Set the opening time and durations — the ERP generates the period timetable automatically (no overlaps).</p>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div><label class="form-label" for="school_open_time" title="When the school day starts">Opening time</label><input id="school_open_time" name="school_open_time" type="time" class="form-input" value="<?= e($config['school_open_time']) ?>"></div>
            <div><label class="form-label" for="school_close_time" title="Must be after opening time">Closing time</label><input id="school_close_time" name="school_close_time" type="time" class="form-input" value="<?= e($config['school_close_time']) ?>"></div>
            <div><label class="form-label" for="periods_count">No. of periods</label><input id="periods_count" name="periods_count" type="number" min="1" max="20" class="form-input" value="<?= e($config['periods_count']) ?>"></div>
            <div><label class="form-label" for="period_duration_min">Period (min)</label><input id="period_duration_min" name="period_duration_min" type="number" min="1" max="240" class="form-input" value="<?= e($config['period_duration_min']) ?>"></div>
            <div><label class="form-label" for="assembly_duration_min" title="0 to skip">Assembly (min)</label><input id="assembly_duration_min" name="assembly_duration_min" type="number" min="0" max="120" class="form-input" value="<?= e($config['assembly_duration_min']) ?>"></div>
            <div><label class="form-label" for="break_duration_min">Break (min)</label><input id="break_duration_min" name="break_duration_min" type="number" min="0" max="120" class="form-input" value="<?= e($config['break_duration_min']) ?>"></div>
            <div><label class="form-label" for="lunch_duration_min">Lunch (min)</label><input id="lunch_duration_min" name="lunch_duration_min" type="number" min="0" max="240" class="form-input" value="<?= e($config['lunch_duration_min']) ?>"></div>
            <div><label class="form-label" for="buffer_between_min" title="Gap between periods for movement">Buffer (min)</label><input id="buffer_between_min" name="buffer_between_min" type="number" min="0" max="60" class="form-input" value="<?= e($config['buffer_between_min']) ?>"></div>
        </div>

        <div class="mt-4">
            <div class="mb-2 text-sm font-medium text-slate-600">Generated daily schedule <span class="text-slate-400">(ends <?= e($endTime) ?>)</span></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-slate-500"><th class="py-1 pr-4">Slot</th><th class="py-1 pr-4">Start</th><th class="py-1">End</th></tr></thead>
                    <tbody>
                        <?php foreach ($schedule as $slot): ?>
                            <tr class="border-t border-slate-100">
                                <td class="py-1 pr-4 <?= $slot['type'] !== 'period' ? 'italic text-slate-400' : 'text-slate-800' ?>"><?= e($slot['label']) ?></td>
                                <td class="py-1 pr-4 tabular-nums"><?= e($slot['start']) ?></td>
                                <td class="py-1 tabular-nums"><?= e($slot['end']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-400">The preview reflects the last saved configuration. Save to regenerate.</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Apply Changes</button>
        <a href="<?= e(url('/admin/settings')) ?>" class="btn btn-secondary">Cancel</a>
    </div>
</form>
<?php $this->endSection(); ?>
