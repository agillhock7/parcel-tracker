<?php declare(strict_types=1); ?>
<?php
  $authData = is_array($auth ?? null) ? $auth : [];
  $oauthProviders = is_array($authData['oauth_providers'] ?? null) ? $authData['oauth_providers'] : [];
?>

<section class="auth-wrap">
  <article class="card card--padded auth-card">
    <h1 class="card__title">Create account</h1>
    <p class="card__sub">Use a strong password. Minimum 10 chars, with upper/lowercase and numbers.</p>

    <?php if (!empty($flash) && is_array($flash)): ?>
      <div class="msg <?= ($flash['type'] ?? '') === 'err' ? 'msg--err' : 'msg--ok' ?>">
        <?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/signup" class="form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <label class="lbl" for="name">Full name</label>
      <input class="in" id="name" name="name" autocomplete="name" required>

      <label class="lbl" for="email">Email</label>
      <input class="in" type="email" id="email" name="email" autocomplete="email" required>

      <label class="lbl" for="password">Password</label>
      <input class="in" type="password" id="password" name="password" autocomplete="new-password" minlength="10" required>

      <div class="actions">
        <button class="btn" type="submit">Create account</button>
      </div>
    </form>

    <?php if (!empty($oauthProviders)): ?>
      <div class="oauth">
        <p class="oauth__label">Or sign up with</p>
        <div class="oauth__actions">
          <?php if (!empty($oauthProviders['github'])): ?>
            <a class="btn btn--ghost oauth__btn" href="/auth/github/start">GitHub</a>
          <?php endif; ?>
          <?php if (!empty($oauthProviders['discord'])): ?>
            <a class="btn btn--ghost oauth__btn" href="/auth/discord/start">Discord</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <p class="auth-card__switch">Already have an account? <a href="/login">Sign in</a>.</p>
  </article>
</section>
