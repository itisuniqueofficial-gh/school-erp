<?php
/** @var int $code */
/** @var string $title */
/** @var string $message */
/** @var string|null $detail */
$code = $code ?? 503;
$title = $title ?? 'Service Unavailable';
$message = $message ?? 'Unable to connect to the database. Please check your database configuration or contact the administrator.';
$detail = $detail ?? null;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e((string) $code) ?> · <?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="flex h-full items-center justify-center bg-slate-50 px-6 text-slate-700">
    <main class="mx-auto max-w-lg text-center">
        <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-3xl text-amber-600">
            <i class="fa-solid fa-database"></i>
        </span>
        <h1 class="mt-6 text-5xl font-extrabold text-slate-900"><?= e((string) $code) ?></h1>
        <h2 class="mt-2 text-xl font-semibold text-slate-800"><?= e($title) ?></h2>
        <p class="mt-3 text-slate-500"><?= e($message) ?></p>

        <?php if (!empty($detail)): ?>
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-left text-xs font-mono text-amber-800">
            <p class="mb-1 font-semibold uppercase tracking-wide">Debug detail (shown only when APP_DEBUG=true)</p>
            <?= e($detail) ?>
        </div>
        <?php endif; ?>

        <a href="<?= e(url('/')) ?>"
           class="mt-8 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
            <i class="fa-solid fa-rotate-right"></i> Try again
        </a>
    </main>
</body>
</html>
