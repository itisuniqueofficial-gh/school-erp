<?php /** @var \Core\View\View $this */ ?>
<?php $this->extend('layouts.guest'); ?>

<?php $this->section('form'); ?>
<h1 class="text-2xl font-bold text-slate-900">Sign in to your account</h1>
<p class="mt-1 text-sm text-slate-500">Enter your credentials to access the portal.</p>

<form method="POST" action="<?= e(url('/login')) ?>" class="mt-8 space-y-5" data-loading>
    <?= csrf_field() ?>

    <div>
        <label for="login" class="form-label required">Email or Username</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <i class="fa-solid fa-user"></i>
            </span>
            <input id="login" name="login" type="text" autocomplete="username" required
                   value="<?= e((string) old('login')) ?>"
                   class="form-input pl-10" placeholder="you@school.test"
                   aria-invalid="<?= error('login') ? 'true' : 'false' ?>">
        </div>
        <?php if ($e = error('login')): ?><p class="form-error"><?= e($e) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="password" class="form-label required">Password</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <i class="fa-solid fa-lock"></i>
            </span>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="form-input pl-10 pr-10" placeholder="••••••••">
            <button type="button" data-toggle-password="password"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                    aria-label="Toggle password visibility">
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>
        <?php if ($e = error('password')): ?><p class="form-error"><?= e($e) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Remember me
        </label>
        <a href="<?= e(url('/forgot-password')) ?>" class="text-sm font-medium text-brand-600 hover:text-brand-700">Forgot password?</a>
    </div>

    <button type="submit" class="btn btn-primary w-full justify-center py-2.5">
        <i class="fa-solid fa-right-to-bracket"></i> Sign in
    </button>
</form>

<div class="mt-8 rounded-lg bg-slate-50 p-4 text-xs text-slate-500">
    <p class="font-semibold text-slate-600">Demo accounts (password: <code>Password123!</code>)</p>
    <p class="mt-1">superadmin@school.test · teacher@school.test · student@school.test · parent@school.test</p>
</div>
<?php $this->endSection(); ?>

<?php // Password visibility toggle is handled app-wide in assets/js/app.js. ?>
