<?php declare(strict_types=1); ?>
<?php
  $authUser = is_array(($auth['user'] ?? null)) ? $auth['user'] : [];
  $upcoming = is_array($upcoming ?? null) ? $upcoming : [];
  $past = is_array($past ?? null) ? $past : [];
  $firstName = trim((string)($authUser['name'] ?? ''));
  if ($firstName !== '') {
    $parts = preg_split('/\s+/', $firstName) ?: [];
    $firstName = (string)($parts[0] ?? $firstName);
  } else {
    $firstName = 'there';
  }
  $activeCount = count($upcoming);
  $pastCount = count($past);
  $movingCount = count(array_filter($upcoming, static function (array $shipment): bool {
    $status = (string)($shipment['status'] ?? 'unknown');
    return in_array($status, ['in_transit', 'out_for_delivery'], true);
  }));
?>

<section class="hero hero--visual" data-tour="welcome" data-reveal>
  <div class="hero__content">
    <p class="hero__kicker">Welcome back, <?= htmlspecialchars($firstName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <h1 class="hero__title">My Packages</h1>
    <p class="hero__sub">Track active deliveries, review history, and update shipment events in real time.</p>
    <div class="hero__actions">
      <button type="button" class="btn btn--ghost" data-start-tour>Take tour</button>
      <a href="#add-shipment" class="btn" data-add-shipment-open>Add shipment</a>
    </div>
  </div>
  <div class="hero-art" aria-hidden="true" data-reveal data-reveal-delay="1">
    <img src="/assets/graphics/route-map.svg" alt="" class="hero-art__bg">
    <div class="hero-art__truck-wrap">
      <img src="/assets/graphics/truck.svg" alt="" class="hero-art__truck">
    </div>
    <div class="hero-art__badge">AI + carrier sync</div>
  </div>
</section>

<section class="stats-strip" data-reveal data-reveal-delay="1">
  <article class="stat-card">
    <p class="stat-card__label">Active</p>
    <p class="stat-card__value"><?= $activeCount ?></p>
  </article>
  <article class="stat-card">
    <p class="stat-card__label">In Motion</p>
    <p class="stat-card__value"><?= $movingCount ?></p>
  </article>
  <article class="stat-card">
    <p class="stat-card__label">Past Deliveries</p>
    <p class="stat-card__value"><?= $pastCount ?></p>
  </article>
</section>

<?php if (!empty($flash) && is_array($flash)): ?>
  <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>" data-reveal data-reveal-delay="1">
    <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if (!empty($db_error)): ?>
  <div class="msg msg--err" data-reveal data-reveal-delay="1">
    <?= htmlspecialchars((string)$db_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<section class="dashboard-grid">
  <article id="add" class="card card--padded add-card" data-tour="add-form" data-reveal data-reveal-delay="1">
    <div class="add-card__head">
      <div>
        <h2 class="card__title">Add Shipment</h2>
        <p class="card__sub">Create a shipment card with a tracking number and optional label/carrier.</p>
      </div>
      <img src="/assets/graphics/package-box.svg" alt="" class="add-card__img" aria-hidden="true">
    </div>

    <form method="post" action="/shipments" class="form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label class="lbl" for="tracking_number">Tracking number</label>
      <input class="in" id="tracking_number" name="tracking_number" autocomplete="off" required placeholder="e.g. 9400 1111 2222 3333 4444 55">

      <div class="row row--2">
        <div>
          <label class="lbl" for="label">Label (optional)</label>
          <input class="in" id="label" name="label" autocomplete="off" placeholder="Mom's package">
        </div>
        <div>
          <label class="lbl" for="carrier">Carrier (optional)</label>
          <input class="in" id="carrier" name="carrier" autocomplete="off" placeholder="USPS, UPS, FedEx...">
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="submit">Create shipment</button>
      </div>
    </form>
  </article>

  <div class="stack" data-tour="upcoming">
    <article class="card card--padded" data-reveal data-reveal-delay="2">
      <div class="section-hd">
        <h2 class="card__title">Upcoming Deliveries</h2>
        <span class="section-hd__meta"><?= (int)($counts['upcoming'] ?? 0) ?> active</span>
      </div>

      <?php if (empty($upcoming)): ?>
        <div class="empty-state">
          <img src="/assets/graphics/package-box.svg" alt="" class="empty-state__img" aria-hidden="true">
          <p class="empty-state__title">No active shipments yet</p>
          <p class="empty-state__text">Add your first tracking number to start building a live timeline.</p>
        </div>
      <?php else: ?>
        <div class="ship-list">
          <?php foreach ($upcoming as $s): ?>
            <?php
              $id = (string)($s['id'] ?? '');
              $label = trim((string)($s['label'] ?? ''));
              $tn = (string)($s['tracking_number'] ?? '');
              $st = (string)($s['status'] ?? 'unknown');
              $loc = trim((string)($s['last_location'] ?? ''));
            ?>
            <a class="ship-card" href="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <span class="ship-card__thumb">
                <img src="/assets/graphics/truck.svg" alt="" aria-hidden="true">
              </span>
              <span class="ship-card__body">
                <span class="ship-card__title"><?= htmlspecialchars($label !== '' ? $label : $tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span class="ship-card__meta">
                  <span class="code"><?= htmlspecialchars($tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <?php if ($loc !== ''): ?>
                    <span class="dot"></span>
                    <span><?= htmlspecialchars($loc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <?php endif; ?>
                </span>
              </span>
              <span class="chip chip--<?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars(str_replace('_', ' ', $st), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>

    <article class="card card--padded" data-reveal data-reveal-delay="3">
      <div class="section-hd">
        <h2 class="card__title">Past Deliveries</h2>
        <span class="section-hd__meta"><?= (int)($counts['past'] ?? 0) ?> total</span>
      </div>

      <?php if (empty($past)): ?>
        <div class="empty-state empty-state--muted">
          <img src="/assets/graphics/route-map.svg" alt="" class="empty-state__img" aria-hidden="true">
          <p class="empty-state__title">No completed deliveries yet</p>
          <p class="empty-state__text">Completed and archived shipments will appear here.</p>
        </div>
      <?php else: ?>
        <div class="ship-list ship-list--past">
          <?php foreach ($past as $s): ?>
            <?php
              $id = (string)($s['id'] ?? '');
              $label = trim((string)($s['label'] ?? ''));
              $tn = (string)($s['tracking_number'] ?? '');
              $st = (string)($s['status'] ?? 'unknown');
            ?>
            <a class="ship-card" href="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <span class="ship-card__thumb ship-card__thumb--muted">
                <img src="/assets/graphics/truck.svg" alt="" aria-hidden="true">
              </span>
              <span class="ship-card__body">
                <span class="ship-card__title"><?= htmlspecialchars($label !== '' ? $label : $tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span class="ship-card__meta">
                  <span class="code"><?= htmlspecialchars($tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </span>
              </span>
              <span class="chip chip--<?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars(str_replace('_', ' ', $st), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
  </div>
</section>
