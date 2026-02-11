<?php declare(strict_types=1); ?>
<?php
  $meta = is_array($meta ?? null) ? $meta : [];
  $auth = is_array($auth ?? null) ? $auth : ['is_authenticated' => false, 'user' => null];
  $vite = is_array($vite ?? null) ? $vite : ['js' => [], 'css' => []];

  $siteName = (string)($meta['site_name'] ?? 'Parcel Tracker');
  $pageTitle = (string)($title ?? $siteName);
  $description = (string)($meta['description'] ?? 'Track packages, monitor delivery timelines, and manage shipments in one place.');
  $robots = (string)($meta['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1');
  $url = (string)($meta['url'] ?? '/');
  $type = (string)($meta['type'] ?? 'website');
  $image = (string)($meta['image'] ?? '/assets/branding/parceltracker-logo-1024.png');
  $imageAlt = (string)($meta['image_alt'] ?? 'Parcel Tracker logo');
  $imageWidth = (string)($meta['image_width'] ?? '1024');
  $imageHeight = (string)($meta['image_height'] ?? '1024');
  $themeColor = (string)($meta['theme_color'] ?? '#f2f3f7');
  $siteUrl = (string)($meta['site_url'] ?? '');
  $host = (string)(parse_url($url, PHP_URL_HOST) ?? '');

  $isAuth = (bool)($auth['is_authenticated'] ?? false);
  $user = is_array($auth['user'] ?? null) ? $auth['user'] : null;
  $userName = trim((string)($user['name'] ?? ''));
  $userEmail = trim((string)($user['email'] ?? ''));
  $userAvatar = trim((string)($user['avatar_url'] ?? ''));
  $hasAvatar = $userAvatar !== '' && preg_match('#^https?://#i', $userAvatar) === 1;
  $avatarInitial = strtoupper(substr($userName !== '' ? $userName : ($userEmail !== '' ? $userEmail : 'U'), 0, 1));

  $page = (string)($page ?? 'home');
  $appVersion = trim((string)getenv('APP_VERSION'));
  if ($appVersion === '') {
    $firstJs = (string)($vite['js'][0] ?? '');
    if ($firstJs !== '' && preg_match('/-([A-Za-z0-9_-]{6,})\\.js$/', $firstJs, $m) === 1) {
      $appVersion = 'build-' . substr((string)$m[1], 0, 8);
    }
  }
  if ($appVersion === '') {
    $fallbackAsset = dirname(__DIR__) . '/public/assets/app.css';
    if (is_file($fallbackAsset)) {
      $mtime = (int)filemtime($fallbackAsset);
      $appVersion = 'local-' . gmdate('YmdHi', $mtime > 0 ? $mtime : time());
    } else {
      $appVersion = 'dev';
    }
  }

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
    <meta name="robots" content="<?= $e($robots) ?>">
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
    <script>
      (() => {
        try {
          const saved = localStorage.getItem("pt_theme");
          const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
          const theme = saved === "light" || saved === "dark" ? saved : (prefersDark ? "dark" : "light");
          document.documentElement.setAttribute("data-theme", theme);
        } catch (_) {
          document.documentElement.setAttribute("data-theme", "light");
        }
      })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
    <?php foreach (($vite['css'] ?? []) as $css): ?>
      <link rel="stylesheet" href="<?= $e((string)$css) ?>">
    <?php endforeach; ?>
  </head>
  <body>
    <div class="app-shell" id="app-shell" data-page="<?= $e($page) ?>" data-auth="<?= $isAuth ? '1' : '0' ?>">
      <header class="site-top">
        <div class="site-top__inner">
          <a class="brand" href="/">
            <img src="/assets/branding/parceltracker-logo-1024.png" alt="Parcel Tracker" class="brand__img">
            <span class="brand__text"><?= $e($siteName) ?></span>
          </a>

          <?php if ($isAuth): ?>
            <button type="button" class="nav-toggle" data-nav-toggle aria-label="Toggle menu">☰</button>
            <nav class="site-nav" data-nav>
              <a href="/" class="site-nav__link<?= $page === 'home' ? ' is-active' : '' ?>">Dashboard</a>
              <a href="#add-shipment" class="site-nav__link" data-add-shipment-open>Add Shipment</a>
            </nav>
            <div class="site-actions">
              <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle light and dark theme">Dark mode</button>
              <div class="avatar" title="<?= $e($userName !== '' ? $userName : $userEmail) ?>">
                <?php if ($hasAvatar): ?>
                  <img class="avatar__img" src="<?= $e($userAvatar) ?>" alt="<?= $e($userName !== '' ? $userName : 'Account avatar') ?>">
                <?php else: ?>
                  <?= $e($avatarInitial) ?>
                <?php endif; ?>
              </div>
              <form method="post" action="/logout" class="logout-form">
                <input type="hidden" name="csrf" value="<?= $e((string)($csrf ?? '')) ?>">
                <button class="btn btn--ghost" type="submit">Sign out</button>
              </form>
            </div>
          <?php else: ?>
            <div class="site-actions">
              <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle light and dark theme">Dark mode</button>
              <a href="/login" class="btn btn--ghost<?= $page === 'login' ? ' is-active' : '' ?>">Sign in</a>
              <a href="/signup" class="btn<?= $page === 'signup' ? ' is-active' : '' ?>">Create account</a>
            </div>
          <?php endif; ?>
        </div>
      </header>

      <main class="page-wrap">
        <?= $content ?? '' ?>
      </main>

      <?php if ($isAuth): ?>
        <div class="add-modal" id="add-modal" hidden aria-hidden="true">
          <div class="add-modal__backdrop" data-add-modal-close></div>
          <div class="add-modal__card" role="dialog" aria-modal="true" aria-labelledby="add-modal-title">
            <div class="add-modal__head">
              <h2 class="add-modal__title" id="add-modal-title">Add Shipment</h2>
              <button type="button" class="add-modal__close" data-add-modal-close aria-label="Close add shipment">×</button>
            </div>
            <p class="add-modal__sub">Create a new tracking card and start your timeline.</p>
            <form method="post" action="/shipments" class="form">
              <input type="hidden" name="csrf" value="<?= $e((string)($csrf ?? '')) ?>">

              <label class="lbl" for="modal_tracking_number">Tracking number</label>
              <input class="in" id="modal_tracking_number" name="tracking_number" autocomplete="off" required placeholder="e.g. 1Z999AA10123456784">

              <div class="row row--2">
                <div>
                  <label class="lbl" for="modal_label">Label (optional)</label>
                  <input class="in" id="modal_label" name="label" autocomplete="off" placeholder="Client return">
                </div>
                <div>
                  <label class="lbl" for="modal_carrier">Carrier (optional)</label>
                  <input class="in" id="modal_carrier" name="carrier" autocomplete="off" placeholder="UPS, USPS, FedEx...">
                </div>
              </div>

              <div class="actions">
                <button class="btn" type="submit">Create shipment</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <footer class="foot">
        <span class="foot__muted">
          Design influenced by Mr. Pablo Benito:
          <a href="https://pbenitol.wixsite.com/portfolio/parceltracker" rel="noopener" target="_blank">portfolio</a>
        </span>
        <span class="foot__muted">
          Version <span class="code"><?= $e($appVersion) ?></span> ·
          Developed with
          <a href="https://darkhorsevirtue.io" rel="noopener" target="_blank">Dark Horse Virtue</a>
        </span>
      </footer>
    </div>

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

    <div id="vue-enhancer" hidden data-page="<?= $e($page) ?>"></div>

    <div class="view-transition" id="view-transition" aria-hidden="true">
      <div class="view-transition__bg"></div>
      <div class="view-transition__card">
        <img src="/assets/graphics/route-map.svg" alt="" class="view-transition__map">
        <img src="/assets/graphics/truck.svg" alt="" class="view-transition__truck">
        <p class="view-transition__text">Routing to your next view...</p>
      </div>
    </div>

    <div class="pwa-ui" id="pwa-ui" hidden>
      <button type="button" class="pwa-install" id="pwa-install">Install App</button>
    </div>
    <div class="pwa-update" id="pwa-update" hidden>
      <span class="pwa-update__text">A new version is ready.</span>
      <button type="button" class="pwa-update__btn" id="pwa-update-btn">Update</button>
    </div>

    <?php if (!empty($vite['js'])): ?>
      <?php foreach (($vite['js'] ?? []) as $js): ?>
        <script type="module" src="<?= $e((string)$js) ?>"></script>
      <?php endforeach; ?>
    <?php else: ?>
      <script defer src="/assets/app.js"></script>
    <?php endif; ?>
  </body>
</html>
