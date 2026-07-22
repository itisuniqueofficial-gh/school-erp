<?php /** @var \Core\View\View $this */ ?>
<?php /** @var string $token */ ?>
<?php /** @var string $email */ ?>
<?php $this->extend('layouts.guest'); ?>

<?php $this->section('form'); ?>
<h1 class="text-2xl font-bold text-slate-900">Reset your password</h1>
<p class="mt-1 text-sm text-slate-500">Choose a new password for your account.</p>

<form method="POST" action="<?= e(url('/reset-password')) ?>" class="mt-8 space-y-5" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div>
        <label for="email" class="form-label required">Email address</label>
        <input id="email" name="email" type="email" required value="<?= e($email) ?>" class="form-input">
        <?php if ($e = error('email')): ?><p class="form-error"><?= e($e) ?></p><?php endif; ?>
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
        <i class="fa-solid fa-key"></i> Reset password
    </button>
</form>
<?php $this->endSection(); ?>
