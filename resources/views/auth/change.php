<?php /** @var \Core\View\View $this */ ?>
<?php $this->extend('layouts.guest'); ?>

<?php $this->section('form'); ?>
<h1 class="text-2xl font-bold text-slate-900">Change your password</h1>
<p class="mt-1 text-sm text-slate-500">Update the password for your account.</p>

<form method="POST" action="<?= e(url('/change-password')) ?>" class="mt-8 space-y-5" data-loading>
    <?= csrf_field() ?>
    <div>
        <label for="current_password" class="form-label required">Current password</label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="form-input">
        <?php if ($e = error('current_password')): ?><p class="form-error"><?= e($e) ?></p><?php endif; ?>
    </div>
    <div>
        <label for="password" class="form-label required">New password</label>
        <div class="relative">
            <input id="password" name="password" type="password" required autocomplete="new-password" class="form-input pr-10" placeholder="At least 8 characters">
            <button type="button" data-toggle-password="password" aria-label="Toggle password visibility" aria-pressed="false"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>
        <?php if ($e = error('password')): ?><p class="form-error"><?= e($e) ?></p><?php endif; ?>
    </div>
    <div>
        <label for="password_confirmation" class="form-label required">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="form-input">
    </div>
    <button type="submit" class="btn btn-primary w-full justify-center py-2.5">
        <i class="fa-solid fa-key"></i> Update password
    </button>
</form>
<?php $this->endSection(); ?>
