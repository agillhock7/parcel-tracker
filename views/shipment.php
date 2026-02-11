<?php declare(strict_types=1); ?>

<?php
  $id = (string)($shipment['id'] ?? '');
  $label = trim((string)($shipment['label'] ?? ''));
  $tn = (string)($shipment['tracking_number'] ?? '');
  $st = (string)($shipment['status'] ?? 'unknown');
  $archived = !empty($shipment['archived']);
  $liveEnabled = !empty($tracking_live_enabled);
  $trackingProvider = strtolower(trim((string)($tracking_provider ?? '')));
  $providerLabel = $trackingProvider !== '' ? strtoupper($trackingProvider) : 'tracking API';
  $trackingConfigHint = trim((string)($tracking_config_hint ?? 'SHIP24_API_KEY'));
?>

<section class="detail-head" data-tour="status">
  <div>
    <a href="/" class="back-link">Back to dashboard</a>
    <h1 class="detail-head__title"><?= htmlspecialchars($label !== '' ? $label : 'Shipment', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
    <p class="detail-head__sub">
      <span class="code"><?= htmlspecialchars($tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      <span class="chip chip--<?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars(str_replace('_', ' ', $st), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </p>
  </div>
  <img src="/assets/graphics/truck.svg" alt="Delivery status graphic" class="detail-head__img">
</section>

<?php if (!empty($flash) && is_array($flash)): ?>
  <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
    <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<section class="dashboard-grid dashboard-grid--detail">
  <article class="card card--padded" data-tour="timeline">
    <div class="section-hd">
      <h2 class="card__title">Tracking Timeline</h2>
    </div>

    <?php if (empty($events)): ?>
      <p class="muted">No events yet.</p>
    <?php else: ?>
      <ol class="timeline">
        <?php foreach ($events as $ev): ?>
          <li class="timeline__item">
            <span class="timeline__dot"></span>
            <span class="timeline__body">
              <span class="timeline__meta">
                <span class="timeline__time"><?= htmlspecialchars((string)($ev['event_time'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php if (!empty($ev['location'])): ?>
                  <span class="timeline__loc"><?= htmlspecialchars((string)$ev['location'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endif; ?>
              </span>
              <span class="timeline__desc"><?= htmlspecialchars((string)($ev['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </article>

  <article class="card card--padded">
    <h2 class="card__title">Update Shipment</h2>
    <p class="card__sub">Add a new tracking event and keep delivery status current.</p>

    <form method="post" action="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/sync" class="sync-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <button class="btn btn--ghost" type="submit">Sync live tracking</button>
      <?php if ($liveEnabled): ?>
        <span class="sync-form__hint">Uses live carrier data (<?= htmlspecialchars($providerLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</span>
      <?php else: ?>
        <span class="sync-form__hint sync-form__hint--warn">Set `<?= htmlspecialchars($trackingConfigHint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>` in `.env` to enable live sync.</span>
      <?php endif; ?>
    </form>

    <form method="post" action="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/events" class="form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label class="lbl" for="event_time">Time</label>
      <input class="in" id="event_time" type="datetime-local" name="event_time" value="<?= htmlspecialchars((string)($defaultTime ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <div class="row row--2">
        <div>
          <label class="lbl" for="location">Location (optional)</label>
          <input class="in" id="location" name="location" placeholder="City, ST">
        </div>
        <div>
          <label class="lbl" for="status">Status</label>
          <select class="in" id="status" name="status">
            <?php foreach (['created','in_transit','out_for_delivery','delivered','exception','unknown'] as $opt): ?>
              <option value="<?= htmlspecialchars($opt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $opt === $st ? 'selected' : '' ?>>
                <?= htmlspecialchars(str_replace('_', ' ', $opt), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label class="lbl" for="description">Description</label>
      <textarea class="in" id="description" name="description" required placeholder="Package accepted, arrived at facility, out for delivery..."></textarea>

      <div class="actions">
        <button class="btn" type="submit">Save update</button>
      </div>
    </form>

    <hr class="divider">

    <form method="post" action="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/archive">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <input type="hidden" name="archived" value="<?= $archived ? '0' : '1' ?>">
      <button class="btn btn--danger" type="submit"><?= $archived ? 'Unarchive' : 'Archive' ?></button>
    </form>
  </article>
</section>
