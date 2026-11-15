<?php
/** @var array<string,mixed> $student */
$name = trim((string) ($student['first_name'] . ' ' . $student['last_name']));
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>ID Card - <?= e($name) ?></title>
<style>
body{font-family:Arial,sans-serif;background:#f1f5f9;margin:0;padding:24px}
.card{width:340px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;padding:18px;margin:auto}
h1{font-size:16px;margin:0 0 12px;color:#0f172a}
dl{margin:0;display:grid;grid-template-columns:110px 1fr;gap:6px 8px;font-size:13px}
dt{color:#64748b}dd{margin:0;color:#0f172a;font-weight:600}
.prn{font-family:monospace;font-size:18px;letter-spacing:1px}
@media print{body{background:#fff;padding:0}.noprint{display:none}}
</style></head><body>
<div class="card">
  <h1><?= e((string) config('app.name', 'School')) ?> &middot; Student ID</h1>
  <dl>
    <dt>Name</dt><dd><?= e($name) ?></dd>
    <dt>PRN</dt><dd class="prn"><?= e((string) ($student['prn'] ?? '—')) ?></dd>
    <dt>Class</dt><dd><?= e(trim((string) ($student['class_name'] ?? '—') . ' ' . (string) ($student['section_name'] ?? ''))) ?></dd>
    <dt>Roll no</dt><dd><?= e((string) ($student['roll_no'] ?? '—')) ?></dd>
    <dt>Admission no</dt><dd><?= e((string) $student['admission_no']) ?></dd>
    <dt>Academic year</dt><dd><?= e((string) ($student['year_name'] ?? '—')) ?></dd>
  </dl>
</div>
<p class="noprint" style="text-align:center"><button onclick="window.print()">Print</button></p>
</body></html>
