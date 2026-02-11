<?php declare(strict_types=1); ?>

<?php
  $id = (string)($shipment['id'] ?? '');
  $label = trim((string)($shipment['label'] ?? ''));
  $tn = (string)($shipment['tracking_number'] ?? '');
  $st = (string)($shipment['status'] ?? 'unknown');
  $archived = !empty($shipment['archived']);
  $liveEnabled = !empty($tracking_live_enabled);
  $trackingConfigHint = trim((string)($tracking_config_hint ?? 'SHIP24_API_KEY'));
  $eventCount = count($events);
  $latestEventTime = $eventCount > 0 ? (string)($events[0]['event_time'] ?? '') : '';
?>

<section class="detail-head detail-head--visual" data-tour="status" data-reveal>
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

<section class="detail-stats" data-reveal data-reveal-delay="1">
  <article class="detail-stat">
    <p class="detail-stat__label">Status</p>
    <p class="detail-stat__value"><?= htmlspecialchars(str_replace('_', ' ', $st), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
  </article>
  <article class="detail-stat">
    <p class="detail-stat__label">Timeline Events</p>
    <p class="detail-stat__value"><?= $eventCount ?></p>
  </article>
  <article class="detail-stat">
    <p class="detail-stat__label">Latest Update</p>
    <p class="detail-stat__value"><?= htmlspecialchars($latestEventTime !== '' ? $latestEventTime : 'No updates yet', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
  </article>
</section>

<?php if (!empty($flash) && is_array($flash)): ?>
  <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>" data-reveal data-reveal-delay="1">
    <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<section class="dashboard-grid dashboard-grid--detail">
  <article class="card card--padded" data-tour="timeline" data-reveal data-reveal-delay="1">
    <div class="section-hd">
      <h2 class="card__title">Tracking Timeline</h2>
    </div>

    <?php if (empty($events)): ?>
      <div class="empty-state">
        <img src="/assets/graphics/route-map.svg" alt="" class="empty-state__img" aria-hidden="true">
        <p class="empty-state__title">No timeline events yet</p>
        <p class="empty-state__text">Use “Check Tracking with AI” or add a manual event to start the timeline.</p>
      </div>
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

  <article class="card card--padded" data-reveal data-reveal-delay="2">
    <h2 class="card__title">Update Shipment</h2>
    <p class="card__sub">Add a new tracking event and keep delivery status current.</p>

    <form method="post" action="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/sync" class="sync-form" data-ai-sync-form>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <button class="btn btn--ghost sync-form__btn" type="submit" data-ai-sync-btn>
        <span class="sync-form__label">Check Tracking with AI</span>
        <span class="sync-form__busy" aria-hidden="true"></span>
      </button>
      <?php if (!$liveEnabled): ?>
        <span class="sync-form__hint sync-form__hint--warn">Set `<?= htmlspecialchars($trackingConfigHint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>` in `.env` to enable live sync.</span>
      <?php endif; ?>
    </form>
    <div class="sync-visual" data-ai-sync-visual hidden aria-live="polite" aria-label="Checking tracking with AI">
      <p class="sync-visual__text">AI is checking the latest carrier scan...</p>
      <div class="sync-visual__lane">
        <img src="/assets/graphics/truck.svg" alt="" class="sync-visual__truck" aria-hidden="true">
        <div class="sync-visual__track"></div>
      </div>
    </div>

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
