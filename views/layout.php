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
