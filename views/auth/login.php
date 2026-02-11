<?php declare(strict_types=1); ?>

<section class="auth-wrap">
  <article class="card card--padded auth-card">
    <h1 class="card__title">Sign in</h1>
    <p class="card__sub">Access your secure shipment dashboard.</p>

    <?php if (!empty($flash) && is_array($flash)): ?>
      <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/login" class="form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label class="lbl" for="email">Email</label>
      <input class="in" type="email" id="email" name="email" autocomplete="email" required>

      <label class="lbl" for="password">Password</label>
      <input class="in" type="password" id="password" name="password" autocomplete="current-password" required>

      <div class="actions">
        <button class="btn" type="submit">Sign in</button>
      </div>
    </form>

    <p class="auth-card__switch">No account yet? <a href="/signup">Create one</a>.</p>
  </article>
</section>
