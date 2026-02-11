<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars((string)($title ?? 'Parcel Tracker'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
  </head>
  <body>
    <div class="bg"></div>
    <header class="nav">
      <div class="nav__inner">
        <a class="brand" href="/">
          <span class="brand__mark" aria-hidden="true">PT</span>
          <span class="brand__name">Parcel Tracker</span>
        </a>
        <nav class="nav__links">
          <a href="/health" class="nav__pill">health</a>
        </nav>
      </div>
    </header>

    <main class="wrap">
      <?= $content ?? '' ?>
      <footer class="foot">
        <div class="foot__row">
          <span class="foot__muted">Prototype for cPanel shared hosting.</span>
          <span class="foot__muted">
            Design influenced by Mr. Pablo Benitez:
            <a href="https://pbenitol.wixsite.com/portfolio/parceltracker" rel="noopener" target="_blank">portfolio</a>
          </span>
        </div>
      </footer>
    </main>
  </body>
</html>

