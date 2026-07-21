<?php
/** @var \Core\View\View $this */
/** @var array{total:int,per_page:int,current_page:int,last_page:int,from:int,to:int} $meta */
$meta = $meta ?? ['total' => 0, 'per_page' => 15, 'current_page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
if (($meta['total'] ?? 0) === 0) {
    return;
}
$current = (int) $meta['current_page'];
$last = (int) $meta['last_page'];

// Preserve existing query parameters when building page links.
$params = $_GET;
$buildUrl = static function (int $page) use ($params): string {
    $params['page'] = $page;
    return '?' . http_build_query($params);
};

$window = 2;
$start = max(1, $current - $window);
$end = min($last, $current + $window);
?>
<nav class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row" aria-label="Pagination">
    <p class="text-sm text-slate-500">
        Showing <span class="font-medium text-slate-700"><?= (int) $meta['from'] ?></span>
        to <span class="font-medium text-slate-700"><?= (int) $meta['to'] ?></span>
        of <span class="font-medium text-slate-700"><?= (int) $meta['total'] ?></span> results
    </p>
    <div class="flex items-center gap-1">
        <?php if ($current > 1): ?>
            <a href="<?= e($buildUrl($current - 1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">Prev</a>
        <?php endif; ?>
        <?php if ($start > 1): ?>
            <a href="<?= e($buildUrl(1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">1</a>
            <?php if ($start > 2): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?= e($buildUrl($i)) ?>"
               class="rounded-lg border px-3 py-1.5 text-sm <?= $i === $current ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end < $last): ?>
            <?php if ($end < $last - 1): ?><span class="px-1 text-slate-400">…</span><?php endif; ?>
            <a href="<?= e($buildUrl($last)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50"><?= $last ?></a>
        <?php endif; ?>
        <?php if ($current < $last): ?>
            <a href="<?= e($buildUrl($current + 1)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">Next</a>
        <?php endif; ?>
    </div>
</nav>
