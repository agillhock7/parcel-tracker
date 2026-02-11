(() => {
  const TOUR_KEY = "pt_onboarding_seen_v2";
  const root = document.querySelector("[data-tour-root]");
  const tour = document.getElementById("tour");

  if (!root || !tour) return;

  const byId = (id) => document.getElementById(id);
  const q = (s) => root.querySelector(s);
  const qAll = (s) => Array.from(root.querySelectorAll(s));

  const stepEl = byId("tour-step");
  const titleEl = byId("tour-title");
  const textEl = byId("tour-text");
  const nextBtn = byId("tour-next");
  const prevBtn = byId("tour-prev");
  const skipBtn = byId("tour-skip");
  const closeHit = tour.querySelector("[data-tour-close]");

  const hasHome = !!q('[data-tour="upcoming"]');
  const hasShipment = !!q('[data-tour="timeline"]');

  const defs = hasHome
    ? [
        { target: '[data-tour="welcome"]', title: "Welcome", text: "This is your parcel dashboard with current package activity." },
        { target: '[data-tour="tabs"]', title: "Quick sections", text: "Use these shortcuts to jump between package actions quickly." },
        { target: '[data-tour="upcoming"]', title: "Upcoming deliveries", text: "Track active packages and open each card for full timeline details." },
        { target: '[data-tour="add-form"]', title: "Add package", text: "Create a new shipment with tracking number, optional label, and carrier." },
        { target: '[data-tour="nav"]', title: "Bottom nav", text: "The bottom bar is optimized for mobile-style one-thumb navigation." },
      ]
    : hasShipment
      ? [
          { target: '[data-tour="status"]', title: "Shipment status", text: "The hero section highlights current delivery state at a glance." },
          { target: '[data-tour="timeline"]', title: "Tracking timeline", text: "Every status update is stored in chronological order for auditing." },
          { target: '[data-tour="nav"]', title: "Bottom nav", text: "Jump back home or add a package from anywhere in the app." },
        ]
      : [];

  if (!defs.length) return;

  let i = 0;
  let activeTarget = null;

  const clearHighlight = () => {
    qAll(".tour-target").forEach((el) => el.classList.remove("tour-target"));
    activeTarget = null;
  };

  const openTour = () => {
    tour.hidden = false;
    document.body.style.overflow = "hidden";
  };

  const closeTour = (markSeen = false) => {
    tour.hidden = true;
    document.body.style.overflow = "";
    clearHighlight();
    if (markSeen) {
      try {
        localStorage.setItem(TOUR_KEY, "1");
      } catch (_) {}
    }
  };

  const renderStep = () => {
    const def = defs[i];
    if (!def) return;

    clearHighlight();
    const target = q(def.target);
    if (target) {
      activeTarget = target;
      target.classList.add("tour-target");
      target.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
    }

    stepEl.textContent = `Step ${i + 1} of ${defs.length}`;
    titleEl.textContent = def.title;
    textEl.textContent = def.text;
    prevBtn.style.visibility = i === 0 ? "hidden" : "visible";
    nextBtn.textContent = i === defs.length - 1 ? "Done" : "Next";
  };

  const startTour = () => {
    i = 0;
    openTour();
    renderStep();
  };

  nextBtn?.addEventListener("click", () => {
    if (i >= defs.length - 1) {
      closeTour(true);
      return;
    }
    i += 1;
    renderStep();
  });

  prevBtn?.addEventListener("click", () => {
    if (i <= 0) return;
    i -= 1;
    renderStep();
  });

  skipBtn?.addEventListener("click", () => closeTour(true));
  closeHit?.addEventListener("click", () => closeTour(true));

  document.addEventListener("keydown", (ev) => {
    if (tour.hidden) return;
    if (ev.key === "Escape") closeTour(true);
    if (ev.key === "ArrowRight") nextBtn?.click();
    if (ev.key === "ArrowLeft") prevBtn?.click();
  });

  document.querySelectorAll("[data-start-tour]").forEach((btn) => {
    btn.addEventListener("click", startTour);
  });

  // First-run onboarding.
  let seen = false;
  try {
    seen = localStorage.getItem(TOUR_KEY) === "1";
  } catch (_) {}
  if (!seen) {
    window.setTimeout(startTour, 380);
  }
})();

