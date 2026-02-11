import { createApp, onMounted } from "vue";

const TOUR_KEY = "pt_onboarding_seen_v3";
const THEME_KEY = "pt_theme";

const AppEnhancer = {
  setup() {
    onMounted(() => {
      const root = document.getElementById("app-shell");
      const tour = document.getElementById("tour");
      if (!root || !tour) return;

      const q = (s) => document.querySelector(s);
      const qAll = (s) => Array.from(document.querySelectorAll(s));

      const navBtn = q("[data-nav-toggle]");
      const nav = q("[data-nav]");
      navBtn?.addEventListener("click", () => nav?.classList.toggle("is-open"));

      const themeToggle = document.getElementById("theme-toggle");
      const setTheme = (theme) => {
        const normalized = theme === "dark" ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", normalized);
        if (themeToggle) {
          themeToggle.textContent = normalized === "dark" ? "Light mode" : "Dark mode";
        }
      };
      const currentTheme = document.documentElement.getAttribute("data-theme") || "light";
      setTheme(currentTheme);
      themeToggle?.addEventListener("click", () => {
        const now = document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
        const next = now === "dark" ? "light" : "dark";
        setTheme(next);
        try {
          localStorage.setItem(THEME_KEY, next);
        } catch (_) {
          // ignore
        }
      });

      const addModal = document.getElementById("add-modal");
      const addModalInput = document.getElementById("modal_tracking_number");
      const syncBodyLock = () => {
        const modalOpen = addModal && !addModal.hidden;
        const tourOpen = !tour.hidden;
        document.body.style.overflow = modalOpen || tourOpen ? "hidden" : "";
      };
      const openAddModal = () => {
        if (!(addModal instanceof HTMLElement)) return;
        addModal.hidden = false;
        addModal.setAttribute("aria-hidden", "false");
        syncBodyLock();
        window.setTimeout(() => {
          if (addModalInput instanceof HTMLInputElement) {
            addModalInput.focus();
          }
        }, 20);
      };
      const closeAddModal = () => {
        if (!(addModal instanceof HTMLElement)) return;
        addModal.hidden = true;
        addModal.setAttribute("aria-hidden", "true");
        syncBodyLock();
      };
      qAll("[data-add-shipment-open]").forEach((el) => {
        el.addEventListener("click", (ev) => {
          if (!(addModal instanceof HTMLElement)) return;
          ev.preventDefault();
          openAddModal();
        });
      });
      qAll("[data-add-modal-close]").forEach((el) => {
        el.addEventListener("click", () => closeAddModal());
      });

      let deferredInstallPrompt = null;
      const pwaUi = document.getElementById("pwa-ui");
      const pwaInstallBtn = document.getElementById("pwa-install");
      const pwaUpdate = document.getElementById("pwa-update");
      const pwaUpdateBtn = document.getElementById("pwa-update-btn");
      let swReg = null;

      if ("serviceWorker" in navigator) {
        const isLocalHost = ["localhost", "127.0.0.1"].includes(window.location.hostname);
        const canRegister = window.location.protocol === "https:" || isLocalHost;
        if (canRegister) {
          navigator.serviceWorker
            .register("/sw.js")
            .then((reg) => {
              swReg = reg;

              const showUpdate = () => {
                if (!pwaUpdate) return;
                pwaUpdate.hidden = false;
              };

              if (reg.waiting) showUpdate();
              reg.addEventListener("updatefound", () => {
                const worker = reg.installing;
                if (!worker) return;
                worker.addEventListener("statechange", () => {
                  if (worker.state === "installed" && navigator.serviceWorker.controller) {
                    showUpdate();
                  }
                });
              });
            })
            .catch(() => {
              // no-op
            });

          let refreshing = false;
          navigator.serviceWorker.addEventListener("controllerchange", () => {
            if (refreshing) return;
            refreshing = true;
            window.location.reload();
          });
        }
      }

      pwaUpdateBtn?.addEventListener("click", () => {
        if (!swReg || !swReg.waiting) return;
        swReg.waiting.postMessage({ type: "SKIP_WAITING" });
      });

      window.addEventListener("beforeinstallprompt", (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        if (pwaUi) pwaUi.hidden = false;
      });

      pwaInstallBtn?.addEventListener("click", async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        try {
          await deferredInstallPrompt.userChoice;
        } catch (_) {
          // ignore
        }
        deferredInstallPrompt = null;
        if (pwaUi) pwaUi.hidden = true;
      });

      window.addEventListener("appinstalled", () => {
        deferredInstallPrompt = null;
        if (pwaUi) pwaUi.hidden = true;
      });

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
          const observer = new IntersectionObserver(
            (entries) => {
              entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
              });
            },
            { threshold: 0.18 }
          );
          revealEls.forEach((el) => observer.observe(el));
        } else {
          revealEls.forEach((el) => el.classList.add("is-visible"));
        }
      }

      const steps = [];
      if (q('[data-tour="welcome"]')) {
        steps.push(
          { target: '[data-tour="welcome"]', title: "Welcome", text: "This is your dashboard with active and past shipments." },
          { target: '[data-tour="add-form"]', title: "Add shipment", text: "Create a shipment with a tracking number and optional carrier details." },
          { target: '[data-tour="upcoming"]', title: "Track progress", text: "Open any shipment card to review and update its timeline." }
        );
      }
      if (q('[data-tour="timeline"]')) {
        steps.push({ target: '[data-tour="timeline"]', title: "Timeline", text: "Shipment events are shown from newest to oldest for quick review." });
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
        syncBodyLock();
        clearTarget();
        if (markSeen) {
          try {
            localStorage.setItem(TOUR_KEY, "1");
          } catch (_) {
            // ignore
          }
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
        syncBodyLock();
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
        if (ev.key !== "Escape") return;
        if (addModal instanceof HTMLElement && !addModal.hidden) {
          closeAddModal();
          return;
        }
        if (tour.hidden) return;
        closeTour(true);
      });

      qAll("[data-start-tour]").forEach((btn) => btn.addEventListener("click", startTour));

      let seen = false;
      try {
        seen = localStorage.getItem(TOUR_KEY) === "1";
      } catch (_) {
        // ignore
      }
      if (!seen) {
        setTimeout(startTour, 420);
      }
    });

    return {};
  },
  template: "<span></span>",
};

const mountEl = document.getElementById("vue-enhancer");
if (mountEl) {
  createApp(AppEnhancer).mount(mountEl);
}
