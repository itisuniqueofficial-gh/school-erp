<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $user */
$name = (string) ($user['name'] ?? 'User');
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>My Profile<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card p-6 text-center">
        <div class="mx-auto flex justify-center">
            <?php $this->include('partials.avatar', ['avatar' => $user['avatar'] ?? null, 'name' => $name, 'size' => 112]); ?>
        </div>
        <h2 class="mt-4 text-lg font-bold text-slate-900"><?= e($name) ?></h2>
        <p class="text-sm text-slate-500"><?= e((string) ($user['email'] ?? '')) ?></p>
        <?php if (!empty($user['uid'])): ?>
        <p class="mt-1 flex items-center justify-center gap-2 text-sm">
            <span class="font-mono text-slate-700"><?= e((string) $user['uid']) ?></span>
            <button type="button" data-copy="<?= e((string) $user['uid']) ?>" class="text-brand-600 hover:text-brand-800" title="Copy User ID" aria-label="Copy User ID"><i class="fa-solid fa-copy"></i></button>
        </p>
        <?php endif; ?>

        <div class="mt-5 border-t border-slate-100 pt-4 text-left">
            <label class="form-label" for="account-photo">Profile picture</label>
            <form method="POST" action="<?= e(url('/account/photo')) ?>" enctype="multipart/form-data" class="space-y-2" data-loading>
                <?= csrf_field() ?>
                <img id="account-photo-preview" class="mx-auto hidden h-24 w-24 rounded-full object-cover ring-1 ring-slate-200" alt="Preview">
                <input id="account-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required
                       class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700 hover:file:bg-brand-100"
                       data-image-preview="#account-photo-preview">
                <p class="text-xs text-slate-400">JPG, PNG or WebP. The image is re-encoded on the server for safety.</p>
                <button class="btn btn-primary btn-sm w-full justify-center"><i class="fa-solid fa-upload"></i> <?= !empty($user['avatar']) ? 'Replace' : 'Upload' ?> picture</button>
            </form>
            <?php if (!empty($user['avatar'])): ?>
            <form method="POST" action="<?= e(url('/account/photo')) ?>" class="mt-2" data-confirm="Remove your profile picture?">
                <?= csrf_field() ?><?= method_field('DELETE') ?>
                <button class="text-sm text-red-600 hover:underline"><i class="fa-solid fa-trash"></i> Remove picture</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Account</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Name</dt><dd class="text-sm text-slate-700"><?= e($name) ?></dd></div>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Email</dt><dd class="text-sm text-slate-700"><?= e((string) ($user['email'] ?? '—')) ?></dd></div>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Username</dt><dd class="text-sm text-slate-700"><?= e((string) ($user['username'] ?? '—')) ?></dd></div>
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Phone</dt><dd class="text-sm text-slate-700"><?= e((string) ($user['phone'] ?? '—')) ?></dd></div>
        </dl>
        <div class="mt-6 border-t border-slate-100 pt-4">
            <a href="<?= e(url('/change-password')) ?>" class="btn btn-secondary"><i class="fa-solid fa-key"></i> Change password</a>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
