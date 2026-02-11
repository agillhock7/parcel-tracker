<?php declare(strict_types=1); ?>
<?php
  $meta = is_array($meta ?? null) ? $meta : [];
  $siteName = (string)($meta['site_name'] ?? 'Parcel Tracker');
  $pageTitle = (string)($title ?? $siteName);
  $description = (string)($meta['description'] ?? 'Track packages, monitor delivery timelines, and manage shipments in one place.');
  $url = (string)($meta['url'] ?? '/');
  $type = (string)($meta['type'] ?? 'website');
  $image = (string)($meta['image'] ?? '/assets/branding/parceltracker-logo-1024.png');
  $imageAlt = (string)($meta['image_alt'] ?? 'Parcel Tracker logo');
  $imageWidth = (string)($meta['image_width'] ?? '1024');
  $imageHeight = (string)($meta['image_height'] ?? '1024');
  $themeColor = (string)($meta['theme_color'] ?? '#f6f7fb');
  $siteUrl = (string)($meta['site_url'] ?? '');
  $host = (string)(parse_url($url, PHP_URL_HOST) ?? '');

  $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

  $jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
      [
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => $siteUrl !== '' ? $siteUrl : $url,
        'description' => $description,
      ],
      [
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => $siteUrl !== '' ? $siteUrl : $url,
        'logo' => $image,
      ],
    ],
  ];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $e($pageTitle) ?></title>
    <meta name="description" content="<?= $e($description) ?>">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    <meta name="application-name" content="<?= $e($siteName) ?>">
    <meta name="apple-mobile-web-app-title" content="<?= $e($siteName) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="<?= $e($themeColor) ?>">
    <meta name="color-scheme" content="light">

    <link rel="canonical" href="<?= $e($url) ?>">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="icon" type="image/png" sizes="1024x1024" href="/assets/branding/parceltracker-logo-1024.png">
    <link rel="shortcut icon" href="/assets/branding/parceltracker-logo-1024.png">
    <link rel="apple-touch-icon" href="/assets/branding/parceltracker-logo-1024.png">
    <meta name="msapplication-TileColor" content="<?= $e($themeColor) ?>">
    <meta name="msapplication-config" content="/browserconfig.xml">

    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="<?= $e($type) ?>">
    <meta property="og:site_name" content="<?= $e($siteName) ?>">
    <meta property="og:title" content="<?= $e($pageTitle) ?>">
    <meta property="og:description" content="<?= $e($description) ?>">
    <meta property="og:url" content="<?= $e($url) ?>">
    <meta property="og:image" content="<?= $e($image) ?>">
    <meta property="og:image:secure_url" content="<?= $e($image) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="<?= $e($imageAlt) ?>">
    <meta property="og:image:width" content="<?= $e($imageWidth) ?>">
    <meta property="og:image:height" content="<?= $e($imageHeight) ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $e($url) ?>">
    <meta name="twitter:title" content="<?= $e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= $e($description) ?>">
    <meta name="twitter:image" content="<?= $e($image) ?>">
    <meta name="twitter:image:alt" content="<?= $e($imageAlt) ?>">
    <?php if ($host !== ''): ?>
      <meta name="twitter:domain" content="<?= $e($host) ?>">
    <?php endif; ?>

    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
  </head>
  <body>
    <div class="scene" aria-hidden="true">
      <span class="blob blob--a"></span>
      <span class="blob blob--b"></span>
    </div>

    <main class="wrap">
      <section class="phone" data-tour-root>
        <div class="phone__notch" aria-hidden="true"></div>
        <div class="phone__content">
          <?= $content ?? '' ?>
        </div>

        <nav class="bottomnav" aria-label="Primary" data-tour="nav">
          <a class="bottomnav__item<?= (($page ?? '') === 'home') ? ' is-active' : '' ?>" href="/">
            <span class="bottomnav__ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/></svg>
            </span>
            <span class="bottomnav__lbl">Home</span>
          </a>
          <a class="bottomnav__item is-add" href="/#add" data-tour="add-shortcut">
            <span class="bottomnav__plus" aria-hidden="true">+</span>
            <span class="bottomnav__lbl">Add</span>
          </a>
          <a class="bottomnav__item" href="/health">
            <span class="bottomnav__ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-3.5 4.3-5 8-5s6.5 1.5 8 5"/></svg>
            </span>
            <span class="bottomnav__lbl">Health</span>
          </a>
        </nav>
      </section>

      <footer class="foot">
        <span class="foot__muted">
          Design influenced by Mr. Pablo Benito:
          <a href="https://pbenitol.wixsite.com/portfolio/parceltracker" rel="noopener" target="_blank">portfolio</a>
        </span>
      </footer>
    </main>

    <div id="tour" class="tour" hidden aria-live="polite">
      <div class="tour__backdrop" data-tour-close></div>
      <div class="tour__card" role="dialog" aria-modal="true" aria-label="Onboarding tour">
        <p class="tour__step" id="tour-step">Step 1 of 4</p>
        <h2 class="tour__title" id="tour-title">Welcome</h2>
        <p class="tour__text" id="tour-text"></p>
        <div class="tour__actions">
          <button type="button" class="tour__btn tour__btn--ghost" id="tour-skip">Skip</button>
          <button type="button" class="tour__btn tour__btn--ghost" id="tour-prev">Back</button>
          <button type="button" class="tour__btn" id="tour-next">Next</button>
        </div>
      </div>
    </div>

    <script defer src="/assets/app.js"></script>
  </body>
</html>
