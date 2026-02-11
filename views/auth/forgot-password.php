<?php declare(strict_types=1); ?>

<section class="auth-wrap">
  <article class="card card--padded auth-card">
    <h1 class="card__title">Reset password</h1>
    <p class="card__sub">Enter your account email and we will send a password reset link.</p>

    <?php if (!empty($flash) && is_array($flash)): ?>
      <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/forgot-password" class="form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label class="lbl" for="email">Email</label>
      <input class="in" type="email" id="email" name="email" autocomplete="email" required>

      <div class="actions">
        <button class="btn" type="submit">Send reset link</button>
      </div>
    </form>

    <p class="auth-card__switch">Remembered it? <a href="/login">Back to sign in</a>.</p>
  </article>
</section>
