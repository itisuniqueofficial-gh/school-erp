<?php
/** @var \Core\View\View $this */
/** @var array<int,array{name:string,size:int,created:int}> $backups */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<form method="POST" action="<?= e(url('/admin/backups')) ?>" class="inline">
    <?= csrf_field() ?>
    <button class="btn btn-primary"><i class="fa-solid fa-database"></i> Create backup</button>
</form>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="font-semibold text-slate-800">Database backups</h2>
        <p class="text-sm text-slate-500">Restoring a backup overwrites current data. Download first to be safe.</p>
    </div>
    <table class="data-table table-cards">
        <thead><tr><th>File</th><th>Size</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php if (empty($backups)): ?><tr><td colspan="4" class="py-8 text-center text-slate-400">No backups yet.</td></tr>
        <?php else: foreach ($backups as $b): ?>
            <tr>
                <td data-label="File" class="font-mono text-xs"><?= e($b['name']) ?></td>
                <td data-label="Size"><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                <td data-label="Created"><?= e(date('Y-m-d H:i', $b['created'])) ?></td>
                <td data-label="Actions" class="text-right">
                    <a href="<?= e(url('/admin/backups/download?file=' . urlencode($b['name']))) ?>" class="text-brand-600 hover:underline text-sm">Download</a>
                    <form method="POST" action="<?= e(url('/admin/backups/restore')) ?>" class="inline ml-2">
                        <?= csrf_field() ?><input type="hidden" name="file" value="<?= e($b['name']) ?>">
                        <button class="text-amber-600 hover:underline text-sm" data-confirm="Restore this backup? Current data will be overwritten.">Restore</button>
                    </form>
                    <form method="POST" action="<?= e(url('/admin/backups/delete')) ?>" class="inline ml-2">
                        <?= csrf_field() ?><input type="hidden" name="file" value="<?= e($b['name']) ?>">
                        <button class="text-red-600 hover:underline text-sm" data-confirm="Delete this backup file?">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
