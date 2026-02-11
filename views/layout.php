<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars((string)($title ?? 'Parcel Tracker'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
  </head>
  <body>
    <main class="wrap">
      <?= $content ?? '' ?>
      <footer class="foot">
        <span class="foot__muted">
          Design influenced by Mr. Pablo Benito:
          <a href="https://pbenitol.wixsite.com/portfolio/parceltracker" rel="noopener" target="_blank">portfolio</a>
        </span>
      </footer>
    </main>

    <nav class="bottomnav" aria-label="Primary">
      <a class="bottomnav__item" href="/">
        <span class="bottomnav__ico" aria-hidden="true">⌂</span>
        <span class="bottomnav__lbl">Home</span>
      </a>
      <a class="bottomnav__item" href="/#add">
        <span class="bottomnav__plus" aria-hidden="true">+</span>
        <span class="bottomnav__lbl">Add</span>
      </a>
      <a class="bottomnav__item" href="/health">
        <span class="bottomnav__ico" aria-hidden="true">♥</span>
        <span class="bottomnav__lbl">Health</span>
      </a>
    </nav>
  </body>
</html>
