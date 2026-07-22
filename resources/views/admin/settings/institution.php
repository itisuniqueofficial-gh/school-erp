<?php
/** @var \Core\View\View $this */
/** @var array<string,bool> $modules */
/** @var array<string,string> $moduleLabels */
/** @var array<string,string> $terms */
/** @var \App\Services\InstitutionService $svc */
/** @var array<string,string> $types */
/** @var array<string,string> $boards */
/** @var array<string,string> $profile */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/settings')) ?>" class="hover:text-brand-600">Settings</a> / ERP Configuration<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <i class="fa-solid fa-sliders mr-1"></i>
    Configure the ERP for your institution type. Disabled modules are hidden from navigation and their routes are blocked server-side. No data is deleted when a module is disabled.
</div>

<form method="POST" action="<?= e(url('/admin/settings/institution')) ?>" class="space-y-6" data-loading>
    <?= csrf_field() ?>

    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Institution Profile</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><label class="form-label" for="profile_name">Institution name</label><input id="profile_name" name="profile_name" class="form-input" value="<?= e($profile['name']) ?>"></div>
            <div><label class="form-label" for="profile_code">Institution / School code</label><input id="profile_code" name="profile_code" class="form-input" value="<?= e($profile['code']) ?>"></div>
            <div>
                <label class="form-label" for="profile_type">Institution type</label>
                <select id="profile_type" name="profile_type" class="form-select">
                    <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>" <?= $profile['type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="profile_board">Board / Affiliation</label>
                <select id="profile_board" name="profile_board" class="form-select">
                    <?php foreach ($boards as $k => $label): ?><option value="<?= e($k) ?>" <?= $profile['board'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="mb-1 font-semibold text-slate-800">Branding</h2>
        <p class="mb-4 text-sm text-slate-500">These values appear across the ERP (title, sidebar, footer, login) — no template edits needed.</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><label class="form-label" for="profile_short_name">Short name</label><input id="profile_short_name" name="profile_short_name" class="form-input" value="<?= e($profile['short_name']) ?>"></div>
            <div><label class="form-label" for="profile_copyright">Copyright text</label><input id="profile_copyright" name="profile_copyright" class="form-input" value="<?= e($profile['copyright']) ?>" placeholder="© <?= date('Y') ?> Your School"></div>
            <div><label class="form-label" for="profile_support_email">Support email</label><input id="profile_support_email" name="profile_support_email" type="email" class="form-input" value="<?= e($profile['support_email']) ?>"></div>
            <div><label class="form-label" for="profile_support_phone">Support phone</label><input id="profile_support_phone" name="profile_support_phone" class="form-input" value="<?= e($profile['support_phone']) ?>"></div>
            <div class="sm:col-span-2"><label class="form-label" for="profile_footer_text">Footer text</label><input id="profile_footer_text" name="profile_footer_text" class="form-input" value="<?= e($profile['footer_text']) ?>"></div>
            <div class="sm:col-span-2"><label class="form-label" for="profile_login_welcome">Login welcome heading</label><input id="profile_login_welcome" name="profile_login_welcome" class="form-input" value="<?= e($profile['login_welcome']) ?>"></div>
            <div class="sm:col-span-2"><label class="form-label" for="profile_login_desc">Login description</label><input id="profile_login_desc" name="profile_login_desc" class="form-input" value="<?= e($profile['login_desc']) ?>"></div>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="mb-1 font-semibold text-slate-800">Modules</h2>
        <p class="mb-4 text-sm text-slate-500">Enable only what your institution needs. Core modules (students, academic, users) are always on.</p>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($moduleLabels as $key => $label): ?>
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                <input type="checkbox" name="modules[<?= e($key) ?>]" value="1" <?= !empty($modules[$key]) ? 'checked' : '' ?> class="rounded border-slate-300">
                <span><?= e($label) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card p-5">
        <h2 class="mb-1 font-semibold text-slate-800">Terminology</h2>
        <p class="mb-4 text-sm text-slate-500">Rename entities to match your institution (e.g. Student → Learner, Class → Course). Leave blank to use the default.</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($terms as $key => $default): ?>
            <div>
                <label class="form-label" for="term_<?= e($key) ?>"><?= e($default) ?></label>
                <input id="term_<?= e($key) ?>" name="terms[<?= e($key) ?>]" class="form-input" value="<?= e($svc->term($key) === $default ? '' : $svc->term($key)) ?>" placeholder="<?= e($default) ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="text-right"><button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save configuration</button></div>
</form>
<?php $this->endSection(); ?>
