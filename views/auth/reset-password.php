<?php declare(strict_types=1); ?>
<?php
  $token = (string)($token ?? '');
  $tokenValid = !empty($token_valid);
?>

<section class="auth-wrap">
  <article class="card card--padded auth-card">
    <h1 class="card__title">Set new password</h1>
    <p class="card__sub">Choose a strong password with at least 10 characters, upper/lowercase, and numbers.</p>

    <?php if (!empty($flash) && is_array($flash)): ?>
      <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if (!$tokenValid): ?>
      <div class="msg msg--err">This reset link is invalid. Request a new one.</div>
      <p class="auth-card__switch"><a href="/forgot-password">Request a fresh reset link</a>.</p>
    <?php else: ?>
      <form method="post" action="/reset-password" class="form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label class="lbl" for="password">New password</label>
        <input class="in" type="password" id="password" name="password" autocomplete="new-password" minlength="10" required>

        <label class="lbl" for="password_confirm">Confirm password</label>
        <input class="in" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="10" required>

        <div class="actions">
          <button class="btn" type="submit">Update password</button>
        </div>
      </form>
    <?php endif; ?>

    <p class="auth-card__switch"><a href="/login">Back to sign in</a>.</p>
  </article>
</section>
