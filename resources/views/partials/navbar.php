<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $user */
$name = $user['name'] ?? 'User';
$roleLabel = ucwords(str_replace('_', ' ', (string) ($user['roles'][0] ?? 'user')));
$initials = strtoupper(substr((string) $name, 0, 1));
$__isStaff = (bool) array_intersect($user['roles'] ?? [], ['super_admin','school_admin','principal','vice_principal','receptionist','hr','accountant','teacher','class_teacher','librarian','transport_manager']);
$__unread = 0;
try { $__unread = (new \App\Services\NotificationService())->unreadCount((int) auth()->id()); } catch (\Throwable) {}
?>
<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button data-sidebar-toggle class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>

    <!-- Global search -->
    <?php if ($__isStaff): ?>
    <form method="GET" action="<?= e(url('/search')) ?>" class="relative hidden max-w-md flex-1 sm:block">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
            <i class="fa-solid fa-magnifying-glass text-sm"></i>
        </span>
        <input type="search" name="q" placeholder="Search students, teachers, classes..." aria-label="Search"
               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-10 pr-3 text-sm focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
    </form>
    <?php endif; ?>

    <div class="ml-auto flex items-center gap-2">
        <!-- Notifications -->
        <a href="<?= e(url('/notifications')) ?>" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Notifications (<?= (int) $__unread ?> unread)">
            <i class="fa-solid fa-bell"></i>
            <?php if ($__unread > 0): ?>
            <span class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"><?= $__unread > 99 ? '99+' : (int) $__unread ?></span>
            <?php endif; ?>
        </a>

        <!-- User menu -->
        <div class="relative">
            <button data-dropdown-toggle="user-menu" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100" aria-haspopup="true">
                <?php $this->include('partials.avatar', ['avatar' => $user['avatar'] ?? null, 'name' => (string) $name, 'size' => 32]); ?>
                <span class="hidden text-left sm:block">
                    <span class="block text-sm font-semibold leading-tight text-slate-800"><?= e((string) $name) ?></span>
                    <span class="block text-xs leading-tight text-slate-400"><?= e($roleLabel) ?></span>
                </span>
                <i class="fa-solid fa-chevron-down hidden text-xs text-slate-400 sm:block"></i>
            </button>
            <div id="user-menu" data-dropdown class="absolute right-0 mt-2 hidden w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                <a href="<?= e(url('/account')) ?>" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    <i class="fa-solid fa-circle-user mr-2 text-slate-400"></i> My Profile
                </a>
                <a href="<?= e(url('/change-password')) ?>" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    <i class="fa-solid fa-key mr-2 text-slate-400"></i> Change Password
                </a>
                <form method="POST" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                        <i class="fa-solid fa-right-from-bracket mr-2"></i> Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
