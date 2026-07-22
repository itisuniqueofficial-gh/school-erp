<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $notifications */
/** @var int $unread */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<?php if ($unread > 0): ?>
<form method="POST" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn-secondary"><i class="fa-solid fa-check-double"></i> Mark all read</button></form>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="card divide-y divide-slate-100">
    <?php if (empty($notifications)): ?>
        <div class="p-8 text-center text-slate-400"><i class="fa-solid fa-bell-slash mb-2 text-2xl"></i><p>No notifications yet.</p></div>
    <?php else: foreach ($notifications as $n): ?>
        <a href="<?= e(url('/notifications/' . $n['id'] . '/read')) ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 <?= empty($n['read_at']) ? 'bg-brand-50/40' : '' ?>">
            <span class="mt-1 inline-flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-700"><i class="fa-solid fa-bell text-xs"></i></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-medium text-slate-800"><?= e((string) $n['title']) ?><?php if (empty($n['read_at'])): ?> <span class="ml-1 inline-block h-2 w-2 rounded-full bg-red-500 align-middle"></span><?php endif; ?></span>
                <?php if (!empty($n['body'])): ?><span class="block text-sm text-slate-500"><?= e((string) $n['body']) ?></span><?php endif; ?>
                <span class="block text-xs text-slate-400"><?= e((string) ($n['created_at'] ?? '')) ?></span>
            </span>
        </a>
    <?php endforeach; endif; ?>
</div>
<?php $this->endSection(); ?>
