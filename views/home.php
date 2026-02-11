<?php declare(strict_types=1); ?>

<section class="hero">
  <div class="hero__copy">
    <h1 class="hero__title">Track what matters.</h1>
    <p class="hero__sub">A lightweight parcel tracker that runs on shared hosting. Start by adding a shipment, then append events as they happen.</p>
  </div>
</section>

<?php if (!empty($flash) && is_array($flash)): ?>
  <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
    <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<div class="grid">
  <section class="card">
    <div class="card__hd">Add Shipment</div>
    <div class="card__bd">
      <form method="post" action="/shipments" class="form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label class="lbl">Tracking number</label>
        <input class="in" name="tracking_number" autocomplete="off" required placeholder="e.g. 9400 1111 2222 3333 4444 55">

        <div class="row row--2">
          <div>
            <label class="lbl">Label (optional)</label>
            <input class="in" name="label" autocomplete="off" placeholder="Mom's package">
          </div>
          <div>
            <label class="lbl">Carrier (optional)</label>
            <input class="in" name="carrier" autocomplete="off" placeholder="USPS, UPS, FedEx...">
          </div>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Create shipment</button>
        </div>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card__hd">Shipments</div>
    <div class="card__bd">
      <?php if (empty($shipments)): ?>
        <p class="muted">No shipments yet.</p>
      <?php else: ?>
        <div class="list">
          <?php foreach ($shipments as $s): ?>
            <?php
              $id = (string)($s['id'] ?? '');
              $label = trim((string)($s['label'] ?? ''));
              $tn = (string)($s['tracking_number'] ?? '');
              $st = (string)($s['status'] ?? 'unknown');
              $upd = (string)($s['updated_at'] ?? '');
            ?>
            <a class="item" href="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <div class="item__main">
                <div class="item__title"><?= htmlspecialchars($label !== '' ? $label : $tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="item__meta">
                  <span class="code"><?= htmlspecialchars($tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <span class="dot"></span>
                  <span class="muted"><?= htmlspecialchars($upd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
              </div>
              <div class="item__side">
                <span class="chip chip--<?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

