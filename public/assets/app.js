(() => {
  const TOUR_KEY = "pt_onboarding_seen_v3";
  const root = document.getElementById("app-shell");
  const tour = document.getElementById("tour");
  if (!root || !tour) return;

  const q = (s) => document.querySelector(s);
  const qAll = (s) => Array.from(document.querySelectorAll(s));

  const navBtn = q("[data-nav-toggle]");
  const nav = q("[data-nav]");
  navBtn?.addEventListener("click", () => nav?.classList.toggle("is-open"));

  qAll("[data-ai-sync-form]").forEach((form) => {
    form.addEventListener("submit", (ev) => {
      if (form.dataset.submitting === "1") {
        ev.preventDefault();
        return;
      }
      form.dataset.submitting = "1";

      const btn = form.querySelector("[data-ai-sync-btn]");
      if (btn instanceof HTMLButtonElement) {
        btn.disabled = true;
        btn.classList.add("is-loading");
      }

      const visual = form.parentElement?.querySelector("[data-ai-sync-visual]");
      if (visual instanceof HTMLElement) {
        visual.hidden = false;
        requestAnimationFrame(() => visual.classList.add("is-active"));
      }

      // Keep a short delay so users can perceive the action feedback.
      ev.preventDefault();
      window.setTimeout(() => form.submit(), 320);
    });
  });

  const revealEls = qAll("[data-reveal]");
  if (revealEls.length) {
    if ("IntersectionObserver" in window) {
      const obs = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-visible");
            obs.unobserve(entry.target);
          });
        },
        { threshold: 0.18 }
      );
      revealEls.forEach((el) => obs.observe(el));
    } else {
      revealEls.forEach((el) => el.classList.add("is-visible"));
    }
  }

  const steps = [];
  if (q('[data-tour="welcome"]')) {
    steps.push(
      { target: '[data-tour="welcome"]', title: 'Welcome', text: 'This is your dashboard with active and past shipments.' },
      { target: '[data-tour="add-form"]', title: 'Add shipment', text: 'Create a shipment with a tracking number and optional carrier details.' },
      { target: '[data-tour="upcoming"]', title: 'Track progress', text: 'Open any shipment card to review and update its timeline.' }
    );
  }
  if (q('[data-tour="timeline"]')) {
    steps.push(
      { target: '[data-tour="timeline"]', title: 'Timeline', text: 'Shipment events are shown from newest to oldest for quick review.' }
    );
  }

  if (!steps.length) return;

  const stepEl = document.getElementById("tour-step");
  const titleEl = document.getElementById("tour-title");
  const textEl = document.getElementById("tour-text");
  const nextBtn = document.getElementById("tour-next");
  const prevBtn = document.getElementById("tour-prev");
  const skipBtn = document.getElementById("tour-skip");
  const closeHit = q("[data-tour-close]");

  let index = 0;

  const clearTarget = () => qAll(".tour-target").forEach((el) => el.classList.remove("tour-target"));

  const closeTour = (markSeen = false) => {
    tour.hidden = true;
    document.body.style.overflow = "";
    clearTarget();
    if (markSeen) {
      try { localStorage.setItem(TOUR_KEY, "1"); } catch (_) {}
    }
  };

  const render = () => {
    const def = steps[index];
    if (!def) return;

    clearTarget();
    const target = q(def.target);
    if (target) {
      target.classList.add("tour-target");
      target.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
    }

    if (stepEl) stepEl.textContent = `Step ${index + 1} of ${steps.length}`;
    if (titleEl) titleEl.textContent = def.title;
    if (textEl) textEl.textContent = def.text;
    if (prevBtn) prevBtn.style.visibility = index === 0 ? "hidden" : "visible";
    if (nextBtn) nextBtn.textContent = index === steps.length - 1 ? "Done" : "Next";
  };

  const startTour = () => {
    index = 0;
    tour.hidden = false;
    document.body.style.overflow = "hidden";
    render();
  };

  nextBtn?.addEventListener("click", () => {
    if (index >= steps.length - 1) {
      closeTour(true);
      return;
    }
    index += 1;
    render();
  });

  prevBtn?.addEventListener("click", () => {
    if (index <= 0) return;
    index -= 1;
    render();
  });

  skipBtn?.addEventListener("click", () => closeTour(true));
  closeHit?.addEventListener("click", () => closeTour(true));

  document.addEventListener("keydown", (ev) => {
    if (tour.hidden) return;
    if (ev.key === "Escape") closeTour(true);
  });

  qAll("[data-start-tour]").forEach((btn) => btn.addEventListener("click", startTour));

  let seen = false;
  try { seen = localStorage.getItem(TOUR_KEY) === "1"; } catch (_) {}
  if (!seen) {
    setTimeout(startTour, 420);
  }
})();
