<?php /** @var \Core\View\View $this */ ?>
<?php $this->extend('layouts.guest'); ?>

<?php $this->section('form'); ?>
<h1 class="text-2xl font-bold text-slate-900">Forgot your password?</h1>
<p class="mt-1 text-sm text-slate-500">Enter your email and we'll send you a reset link.</p>

<form method="POST" action="<?= e(url('/forgot-password')) ?>" class="mt-8 space-y-5" data-loading>
    <?= csrf_field() ?>
    <div>
        <label for="email" class="form-label required">Email address</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <i class="fa-solid fa-envelope"></i>
            </span>
            <input id="email" name="email" type="email" required autocomplete="email"
                   value="<?= e((string) old('email')) ?>" class="form-input pl-10" placeholder="you@school.test">
        </div>
        <?php if ($e = error('email')): ?><p class="form-error"><?= e($e) ?></p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary w-full justify-center py-2.5">
        <i class="fa-solid fa-paper-plane"></i> Send reset link
    </button>
    <a href="<?= e(url('/login')) ?>" class="block text-center text-sm font-medium text-brand-600 hover:text-brand-700">
        <i class="fa-solid fa-arrow-left mr-1"></i> Back to sign in
    </a>
</form>
<?php $this->endSection(); ?>
