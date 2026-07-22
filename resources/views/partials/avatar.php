<?php
/**
 * Reusable avatar component.
 *
 * @var \Core\View\View $this
 * Params (via include):
 *   string|null $avatar  Stored avatar filename (users.avatar / staff.photo), or null.
 *   string      $name    Display name (used for the initials fallback).
 *   int         $size    Pixel size of the circle (default 40).
 */
$avatarFile = $avatar ?? null;
$avatarName = trim((string) ($name ?? 'User')) ?: 'User';
$px = (int) ($size ?? 40);

$parts = preg_split('/\s+/', $avatarName) ?: [];
$initials = strtoupper(substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? substr($parts[count($parts) - 1], 0, 1) : ''));
if ($initials === '') {
    $initials = 'U';
}
$palette = ['bg-brand-600', 'bg-emerald-600', 'bg-indigo-600', 'bg-rose-600', 'bg-amber-600', 'bg-cyan-600', 'bg-fuchsia-600', 'bg-teal-600'];
$bg = $palette[abs(crc32($avatarName)) % count($palette)];
?>
<?php if (!empty($avatarFile)): ?>
    <?php $avatarId = pathinfo((string) $avatarFile, PATHINFO_FILENAME); ?>
    <img src="<?= e(url('/media/avatar/' . $avatarId . '/thumb')) ?>" alt="<?= e($avatarName) ?>"
         class="shrink-0 rounded-full object-cover ring-1 ring-slate-200"
         style="height:<?= $px ?>px;width:<?= $px ?>px" loading="lazy">
<?php else: ?>
    <span class="inline-flex shrink-0 items-center justify-center rounded-full <?= $bg ?> font-semibold text-white"
          style="height:<?= $px ?>px;width:<?= $px ?>px;font-size:<?= max(10, (int) ($px * 0.4)) ?>px"
          aria-label="<?= e($avatarName) ?>"><?= e($initials) ?></span>
<?php endif; ?>
