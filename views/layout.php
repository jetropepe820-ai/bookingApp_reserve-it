<?php
$user = current_user();
$successMessage = flash('success');
$errorMessage = flash('error');
$errors = $errors ?? [];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
  <meta name="availability-url" content="<?= h(config('app_path') . '/api/v1/courts') ?>">
  <title><?= h($title ?? config('app_name')) ?> · <?= h(config('app_name')) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="<?= h(url('asset', ['file' => 'site.css'])) ?>">
</head>
<body class="min-h-screen bg-stone-50 font-sans text-slate-900">
  <header class="border-b border-stone-200 bg-stone-50">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-10">
      <a href="<?= h(url('home')) ?>" class="flex items-center gap-2 font-display text-xl font-extrabold tracking-tight">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-lime-300 text-sm">R</span>
        RESERVE-IT<span class="-ml-2 text-lime-700">.</span>
      </a>
      <nav class="flex items-center gap-4 text-sm font-semibold sm:gap-7">
        <a class="hover:text-lime-800" href="<?= h(url('home')) ?>#courts">Find a court</a>
        <?php if ($user): ?>
          <a class="hover:text-lime-800" href="<?= h(url('dashboard')) ?>">My bookings</a>
          <?php if ($user['role'] === 'admin'): ?>
            <a class="hover:text-lime-800" href="<?= h(url('admin')) ?>">Admin</a>
          <?php endif; ?>
          <form method="post" action="<?= h(url('logout')) ?>">
            <?= csrf_field() ?>
            <button class="hover:text-lime-800">Sign out</button>
          </form>
        <?php else: ?>
          <a class="hover:text-lime-800" href="<?= h(url('login')) ?>">Sign in</a>
          <a
            href="<?= h(url('register')) ?>"
            class="rounded bg-slate-900 px-4 py-2.5 text-white hover:bg-slate-700"
          >Create account</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php if ($successMessage): ?>
    <div class="mx-auto mt-5 max-w-7xl px-5">
      <div class="rounded-lg border border-lime-200 bg-lime-50 px-4 py-3 text-sm text-lime-900">
        <?= h($successMessage) ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
    <div class="mx-auto mt-5 max-w-7xl px-5">
      <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        <?= h($errorMessage) ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="mx-auto mt-5 max-w-3xl px-5">
      <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        <?php foreach ($errors as $error): ?>
          <p><?= h($error) ?></p>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <main>
    <?php require $viewFile; ?>
  </main>

  <footer class="mt-20 border-t border-stone-200 px-5 py-7 text-xs text-slate-500">
    <div class="mx-auto flex max-w-7xl justify-between">
      <span class="font-bold text-slate-800">RESERVE-IT<span class="text-lime-700">.</span></span>
      <span>Make time for your game. · © <?= date('Y') ?></span>
    </div>
  </footer>

  <script src="<?= h(url('asset', ['file' => 'app.js'])) ?>" defer></script>
</body>
</html>
