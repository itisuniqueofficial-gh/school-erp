<?php
/** @var int $code */
/** @var string $title */
/** @var string $message */
$code = $code ?? 500;
$title = $title ?? 'Error';
$message = $message ?? 'Something went wrong.';
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
<body class="flex h-full items-center justify-center bg-slate-50 text-slate-700">
    <main class="mx-auto max-w-md px-6 text-center">
        <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-50 text-3xl text-blue-600">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </span>
        <h1 class="mt-6 text-6xl font-extrabold text-slate-900"><?= e((string) $code) ?></h1>
        <h2 class="mt-2 text-xl font-semibold text-slate-800"><?= e($title) ?></h2>
        <p class="mt-2 text-slate-500"><?= e($message) ?></p>
        <a href="<?= e(url('/')) ?>"
           class="mt-8 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
            <i class="fa-solid fa-house"></i> Back to home
        </a>
    </main>
</body>
</html>
