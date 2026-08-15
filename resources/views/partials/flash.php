<?php /** @var \Core\View\View $this */ ?>
<?php
$flashes = [
    'success' => ['bg-green-50 border-green-200 text-green-800', 'fa-circle-check', 'status', 'polite'],
    'error'   => ['bg-red-50 border-red-200 text-red-800', 'fa-circle-exclamation', 'alert', 'assertive'],
    'warning' => ['bg-amber-50 border-amber-200 text-amber-800', 'fa-triangle-exclamation', 'alert', 'assertive'],
    'info'    => ['bg-blue-50 border-blue-200 text-blue-800', 'fa-circle-info', 'status', 'polite'],
];
?>
<?php foreach ($flashes as $key => [$classes, $icon, $role, $live]): ?>
    <?php if ($msg = flash($key)): ?>
        <div data-flash role="<?= $role ?>" aria-live="<?= $live ?>" class="mb-4 flex items-center gap-2 rounded-lg border px-4 py-3 text-sm <?= $classes ?>">
            <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i>
            <span><?= e((string) $msg) ?></span>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php $errs = errors(); ?>
<?php if (!empty($errs)): ?>
    <div data-flash role="alert" aria-live="assertive" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i> Please fix the following:</p>
        <ul class="mt-1 list-inside list-disc">
            <?php foreach ($errs as $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $err): ?>
                    <li><?= e((string) $err) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
