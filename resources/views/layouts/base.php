<?php /** @var \Core\View\View $this */ ?>
<!DOCTYPE html>
<html lang="<?= e(config('app.locale', 'en')) ?>" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($this->yield('title', ($title ?? 'Dashboard') . ' · ' . ($appName ?? config('app.name')))) ?></title>
    <meta name="description" content="<?= e(($appName ?? config('app.name'))) ?> — School management system.">

    <?php $faviconUrl = function_exists('brand_logo_url') ? brand_logo_url() : null; ?>
    <?php if ($faviconUrl): ?>
        <link rel="icon" type="image/png" href="<?= e($faviconUrl) ?>">
        <link rel="apple-touch-icon" href="<?= e($faviconUrl) ?>">
    <?php else: ?>
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='5' fill='%232563eb'/%3E%3Cpath d='M12 5L3 9l9 4 7-3.1V15h2V9z' fill='white'/%3E%3Cpath d='M6 12.5V16c0 1.1 2.7 2 6 2s6-.9 6-2v-3.5l-6 2.7z' fill='white'/%3E%3C/svg%3E">
    <?php endif; ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef6ff', 100: '#d9eaff', 200: '#bcd8ff', 300: '#8ec0ff',
                            400: '#589dff', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a'
                        }
                    },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
                }
            }
        };
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <?= $this->yield('head') ?>
</head>
<body class="h-full text-slate-700 antialiased">
    <?= $this->yield('content') ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    <!-- SweetAlert2 (confirmations & alerts; app.js falls back to native confirm if unavailable) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="<?= e(asset('assets/js/animations.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <?= $this->yield('scripts') ?>
</body>
</html>
