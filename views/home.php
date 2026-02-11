<?php declare(strict_types=1); ?>

<header class="topbar">
  <div class="topbar__copy">
    <p class="topbar__kicker">Welcome back</p>
    <h1 class="topbar__title">My packages</h1>
  </div>
  <div class="topbar__badge" aria-hidden="true">PT</div>
</header>

<div class="tabs" role="navigation" aria-label="Shortcuts">
  <a class="tab" href="/#add">Add package</a>
  <a class="tab" href="/">My packages</a>
  <a class="tab" href="/health">Help</a>
</div>

<?php if (!empty($flash) && is_array($flash)): ?>
  <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
    <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
<?php endif; ?>

<section class="section">
  <div class="section__hd">
    <h2 class="section__title">Upcoming deliveries</h2>
    <span class="section__muted"><?= (int)($counts['upcoming'] ?? 0) ?> active</span>
  </div>

  <?php if (empty($upcoming)): ?>
    <div class="card card--plain">
      <div class="card__bd">
        <p class="muted">No active shipments yet. Add one below.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="stack">
      <?php foreach ($upcoming as $s): ?>
        <?php
          $id = (string)($s['id'] ?? '');
          $label = trim((string)($s['label'] ?? ''));
          $tn = (string)($s['tracking_number'] ?? '');
          $st = (string)($s['status'] ?? 'unknown');
          $loc = trim((string)($s['last_location'] ?? ''));
        ?>
        <a class="pkg" href="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <div class="pkg__left">
            <div class="pkg__title"><?= htmlspecialchars($label !== '' ? $label : $tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="pkg__meta">
              <span class="code"><?= htmlspecialchars($tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <?php if ($loc !== ''): ?>
                <span class="dot"></span>
                <span class="muted"><?= htmlspecialchars($loc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="pkg__right">
            <span class="chip chip--<?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <span class="btn btn--track" aria-hidden="true">Track</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section__hd">
    <h2 class="section__title">Past deliveries</h2>
    <span class="section__muted"><?= (int)($counts['past'] ?? 0) ?> total</span>
  </div>

  <?php if (empty($past)): ?>
    <div class="card card--plain">
      <div class="card__bd">
        <p class="muted">No past deliveries yet.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="stack">
      <?php foreach ($past as $s): ?>
        <?php
          $id = (string)($s['id'] ?? '');
          $label = trim((string)($s['label'] ?? ''));
          $tn = (string)($s['tracking_number'] ?? '');
          $st = (string)($s['status'] ?? 'unknown');
          $loc = trim((string)($s['last_location'] ?? ''));
        ?>
        <a class="pkg pkg--past" href="/shipments/<?= htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <div class="pkg__left">
            <div class="pkg__title"><?= htmlspecialchars($label !== '' ? $label : $tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="pkg__meta">
              <span class="code"><?= htmlspecialchars($tn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <?php if ($loc !== ''): ?>
                <span class="dot"></span>
                <span class="muted"><?= htmlspecialchars($loc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="pkg__right">
            <span class="chip chip--<?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($st, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section id="add" class="section">
  <div class="section__hd">
    <h2 class="section__title">Add package</h2>
    <span class="section__muted">Create a new tracking card</span>
  </div>
  <div class="card">
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
          <button class="btn" type="submit">Add</button>
        </div>
      </form>
    </div>
  </div>
</section>
