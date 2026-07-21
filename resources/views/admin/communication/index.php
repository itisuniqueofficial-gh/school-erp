<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $notices */
/** @var array<int,array<string,mixed>> $messages */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-2">
    <!-- Notices -->
    <div class="space-y-4">
        <?php if (auth()->can('communication.send')): ?>
        <div class="card p-5">
            <h2 class="font-semibold text-slate-800">Publish notice</h2>
            <form method="POST" action="<?= e(url('/admin/communication/notices')) ?>" class="mt-4 space-y-3" data-loading>
                <?= csrf_field() ?>
                <div><label class="form-label required" for="title">Title</label><input id="title" name="title" class="form-input" required></div>
                <div><label class="form-label required" for="audience">Audience</label><select id="audience" name="audience" class="form-select" required><?php foreach(['all','students','parents','teachers','staff'] as $a):?><option value="<?=$a?>"><?=ucfirst($a)?></option><?php endforeach;?></select></div>
                <div><label class="form-label required" for="body">Message</label><textarea id="body" name="body" rows="3" class="form-textarea" required></textarea></div>
                <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-bullhorn"></i> Publish notice</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold text-slate-800">Notices</h2></div>
            <ul class="divide-y divide-slate-100">
                <?php if (empty($notices)): ?><li class="p-6 text-center text-slate-400">No notices.</li>
                <?php else: foreach ($notices as $n): ?>
                    <li class="p-4">
                        <div class="flex items-center justify-between">
                            <p class="font-medium text-slate-800"><?= e((string) $n['title']) ?></p>
                            <span class="badge badge-slate"><?= e((string) $n['audience']) ?></span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500"><?= e(mb_substr(strip_tags((string) $n['body']), 0, 140)) ?></p>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>

    <!-- Messages -->
    <div class="space-y-4">
        <?php if (auth()->can('communication.send')): ?>
        <div class="card p-5">
            <h2 class="font-semibold text-slate-800">Broadcast message</h2>
            <form method="POST" action="<?= e(url('/admin/communication/messages')) ?>" class="mt-4 space-y-3" data-loading>
                <?= csrf_field() ?>
                <div><label class="form-label required" for="subject">Subject</label><input id="subject" name="subject" class="form-input" required></div>
                <div><label class="form-label required" for="mbody">Message</label><textarea id="mbody" name="body" rows="3" class="form-textarea" required></textarea></div>
                <button class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-paper-plane"></i> Send to all users</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold text-slate-800">Sent messages</h2></div>
            <ul class="divide-y divide-slate-100">
                <?php if (empty($messages)): ?><li class="p-6 text-center text-slate-400">No messages.</li>
                <?php else: foreach ($messages as $m): ?>
                    <li class="p-4">
                        <p class="font-medium text-slate-800"><?= e((string) ($m['subject'] ?? '(no subject)')) ?></p>
                        <p class="mt-1 text-xs text-slate-400">by <?= e((string) ($m['sender_name'] ?? 'System')) ?> · <?= e((string) $m['created_at']) ?></p>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
