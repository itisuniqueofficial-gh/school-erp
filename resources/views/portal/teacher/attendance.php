<?php /** @var \Core\View\View $this */ ?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="card p-8 text-center">
    <i class="fa-solid fa-clipboard-check mb-3 text-4xl text-slate-300"></i>
    <p class="text-slate-500">Select a class and section from the admin Attendance module to mark daily attendance.</p>
    <a href="<?= e(url('/admin/attendance')) ?>" class="btn btn-primary mt-4 inline-flex"><i class="fa-solid fa-arrow-right"></i> Go to attendance</a>
</div>
<?php $this->endSection(); ?>
