<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed>|null $student */
/** @var array<int,array<string,mixed>> $documents */
/** @var array<string,string> $documentTypes */
/** @var array{percent:int,missing:array<int,string>} $completion */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>

<div class="card mb-4 flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
        <h2 class="font-semibold text-slate-800">Document completion</h2>
        <p class="text-2xl font-bold text-slate-900"><?= (int) $completion['percent'] ?>%</p>
    </div>
    <?php if (!empty($completion['missing'])): ?>
    <div class="text-sm text-amber-700">
        <p class="font-medium">Still required:</p>
        <?php foreach ($completion['missing'] as $m): ?><span class="badge badge-amber mr-1"><?= e($m) ?></span><?php endforeach; ?>
    </div>
    <?php else: ?>
        <span class="badge badge-green">All required documents uploaded</span>
    <?php endif; ?>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table table-cards">
            <thead><tr><th>Document</th><th>Type</th><th>Uploaded</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($documents)): ?>
                <tr><td colspan="5" class="py-8 text-center text-slate-400">You have not uploaded any documents yet.</td></tr>
            <?php else: foreach ($documents as $doc):
                $vs = (string) ($doc['verification_status'] ?? 'pending');
                $badge = $vs === 'verified' ? 'badge-green' : ($vs === 'rejected' ? 'badge-red' : 'badge-slate'); ?>
                <tr>
                    <td data-label="Document" class="font-medium text-slate-800"><?= e((string) $doc['title']) ?></td>
                    <td data-label="Type"><?= e((string) ($documentTypes[$doc['type']] ?? $doc['type'] ?? '—')) ?></td>
                    <td data-label="Uploaded"><?= e((string) ($doc['created_at'] ?? '')) ?></td>
                    <td data-label="Status"><span class="badge <?= $badge ?>"><?= e(ucfirst($vs)) ?></span></td>
                    <td data-label="" class="text-right">
                        <a href="<?= e(url('/student/documents/' . $doc['id'] . '/download')) ?>" target="_blank" class="text-brand-600 hover:underline"><i class="fa-solid fa-eye"></i> View</a>
                    </td>
                </tr>
                <?php if ($vs === 'rejected'): ?>
                <tr><td colspan="5" class="bg-red-50 px-4 py-2 text-xs text-red-700"><i class="fa-solid fa-circle-exclamation mr-1"></i> This document was rejected. Please upload a clearer/valid copy.</td></tr>
                <?php endif; ?>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 p-4">
        <h3 class="mb-3 font-semibold text-slate-800">Upload a document</h3>
        <form method="POST" action="<?= e(url('/student/documents')) ?>" enctype="multipart/form-data" class="grid grid-cols-1 gap-3 sm:grid-cols-4" data-loading>
            <?= csrf_field() ?>
            <input type="text" name="title" placeholder="Title (optional)" class="form-input">
            <select name="type" class="form-select" required><?php foreach ($documentTypes as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
            <input type="file" name="document" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            <button class="btn btn-primary justify-center"><i class="fa-solid fa-upload"></i> Upload</button>
        </form>
        <p class="mt-2 text-xs text-slate-400">Allowed: PDF, JPG, PNG, WEBP. Your documents are private — only you and authorized staff can access them. Uploads start as “Pending” until verified.</p>
    </div>
</div>
<?php $this->endSection(); ?>
