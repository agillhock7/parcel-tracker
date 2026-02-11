<?php declare(strict_types=1); ?>
<?php
  $authData = is_array($auth ?? null) ? $auth : [];
  $oauthProviders = is_array($authData['oauth_providers'] ?? null) ? $authData['oauth_providers'] : [];
?>

<section class="auth-wrap">
  <header class="auth-brand" aria-label="Parcel Tracker branding">
    <span class="auth-brand__halo" aria-hidden="true"></span>
    <img src="/assets/branding/parceltracker-logo-1024.png" alt="Parcel Tracker" class="auth-brand__logo">
    <p class="auth-brand__name">Parcel Tracker</p>
    <p class="auth-brand__tagline">Secure shipment tracking across desktop and mobile.</p>
  </header>

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

    <p class="auth-card__switch"><a href="/forgot-password">Forgot your password?</a></p>

    <?php if (!empty($oauthProviders)): ?>
      <div class="oauth">
        <p class="oauth__label">Or continue with</p>
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

    <p class="auth-card__switch">No account yet? <a href="/signup">Create one</a>.</p>
  </article>
</section>
