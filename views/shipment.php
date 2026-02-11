<?php declare(strict_types=1); ?>

<?php
  $id = (string)($shipment['id'] ?? '');
  $label = trim((string)($shipment['label'] ?? ''));
  $tn = (string)($shipment['tracking_number'] ?? '');
  $st = (string)($shipment['status'] ?? 'unknown');
  $archived = !empty($shipment['archived']);
?>

<div class="crumbs">
  <a href="/" class="crumbs__link">All shipments</a>
  <button type="button" class="crumbs__menu" aria-label="More options">⋮</button>
</div>

<section class="status" data-tour="status">
  <h1 class="status__title">Package <?= htmlspecialchars(str_replace('_', ' ', $st), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>!</h1>
  <p class="status__sub"><?= htmlspecialchars($label !== '' ? $label : 'Parcel added to your tracking list', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
  <img src="/assets/graphics/truck.svg" alt="Parcel is moving through transit points" class="status__img">
</section>

<section class="tracking-number">
  <p class="tracking-number__label">Tracking number</p>
  <p class="tracking-number__value"><?= htmlspecialchars($tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
</section>

<?php if (!empty($flash) && is_array($flash)): ?>
  <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
    <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<div class="grid grid--detail">
  <section class="card" id="change">
    <div class="card__hd">Update Shipment</div>
    <div class="card__bd">
      <form method="post" action="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/events" class="form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label class="lbl">Time</label>
        <input class="in" type="datetime-local" name="event_time" value="<?= htmlspecialchars((string)($defaultTime ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <div class="row row--2">
          <div>
            <label class="lbl">Location (optional)</label>
            <input class="in" name="location" placeholder="City, ST">
          </div>
          <div>
            <label class="lbl">Status</label>
            <select class="in" name="status">
              <?php foreach (['created','in_transit','out_for_delivery','delivered','exception','unknown'] as $opt): ?>
                <option value="<?= htmlspecialchars($opt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $opt === $st ? 'selected' : '' ?>>
                  <?= htmlspecialchars($opt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <label class="lbl">Description</label>
        <textarea class="in" name="description" required placeholder="Package accepted, arrived at facility, out for delivery..."></textarea>

        <div class="actions">
          <button class="btn" type="submit">Save update</button>
        </div>
      </form>

      <div class="divider"></div>

      <form method="post" action="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/archive">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="archived" value="<?= $archived ? '0' : '1' ?>">
        <button class="btn btn--danger" type="submit"><?= $archived ? 'Unarchive' : 'Archive' ?></button>
      </form>
    </div>
  </section>

  <section class="card" data-tour="timeline">
    <div class="card__hd">Tracking timeline</div>
    <div class="card__bd">
      <?php if (empty($events)): ?>
        <p class="muted">No events yet.</p>
      <?php else: ?>
        <ol class="timeline">
          <?php foreach ($events as $ev): ?>
            <li class="timeline__item">
              <div class="timeline__dot"></div>
              <div class="timeline__body">
                <div class="timeline__top">
                  <div class="timeline__time"><?= htmlspecialchars((string)($ev['event_time'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <?php if (!empty($ev['location'])): ?>
                    <div class="timeline__loc"><?= htmlspecialchars((string)$ev['location'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <?php endif; ?>
                </div>
                <div class="timeline__desc"><?= htmlspecialchars((string)($ev['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </section>
</div>
