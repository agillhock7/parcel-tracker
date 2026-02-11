<?php declare(strict_types=1); ?>
<?php
  $authUser = is_array(($auth['user'] ?? null)) ? $auth['user'] : [];
  $firstName = trim((string)($authUser['name'] ?? ''));
  if ($firstName !== '') {
    $parts = preg_split('/\s+/', $firstName) ?: [];
    $firstName = (string)($parts[0] ?? $firstName);
  } else {
    $firstName = 'there';
  }
?>

<section class="hero" data-tour="welcome">
  <div>
    <p class="hero__kicker">Welcome back, <?= htmlspecialchars($firstName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <h1 class="hero__title">My Packages</h1>
    <p class="hero__sub">Track active deliveries, review history, and update shipment events in real time.</p>
  </div>
  <div class="hero__actions">
    <button type="button" class="btn btn--ghost" data-start-tour>Take tour</button>
    <a href="#add" class="btn">Add shipment</a>
  </div>
</section>

<?php if (!empty($flash) && is_array($flash)): ?>
  <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
    <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<?php if (!empty($db_error)): ?>
  <div class="msg msg--err">
    <?= htmlspecialchars((string)$db_error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<section class="dashboard-grid">
  <article id="add" class="card card--padded" data-tour="add-form">
    <h2 class="card__title">Add Shipment</h2>
    <p class="card__sub">Create a shipment card with a tracking number and optional label/carrier.</p>

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
    <article class="card card--padded">
      <div class="section-hd">
        <h2 class="card__title">Upcoming Deliveries</h2>
        <span class="section-hd__meta"><?= (int)($counts['upcoming'] ?? 0) ?> active</span>
      </div>

      <?php if (empty($upcoming)): ?>
        <p class="muted">No active shipments yet.</p>
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

    <article class="card card--padded">
      <div class="section-hd">
        <h2 class="card__title">Past Deliveries</h2>
        <span class="section-hd__meta"><?= (int)($counts['past'] ?? 0) ?> total</span>
      </div>

      <?php if (empty($past)): ?>
        <p class="muted">No past deliveries yet.</p>
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
