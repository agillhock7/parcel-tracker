# Repository Guidelines

This repository targets deployment to **cPanel shared hosting** using **cPanel Git Version Control**. Develop locally, then deploy by pushing commits and triggering a “Deploy HEAD Commit” on the server. Host-specific notes belong in `AGENTS.private.md` (ignored by git).

## Project Structure

This repo is intended to keep only the web entrypoint publicly accessible.

- `public/`: web root (prefer your domain/subdomain DocumentRoot pointing here)
- `src/`: application code (controllers/services/repositories/models)
- `views/`: templates (if server-rendered)
- `storage/`: writable runtime files (logs/cache) and must not be under `public/`
- `sql/`: schema and migrations (e.g., `sql/migrations/2026_02_10_0001_create_shipments.sql`)
- `data/`: development-only local data; do not commit real customer/tracking data

## Development & Deployment

- Local changes only. Avoid editing code on the server except `.env`, DB/user setup, migrations, and `.htaccess`.
- Build steps: if the server cannot run builds reliably, build locally and commit the produced assets (for example, `public/assets/`).

Recommended command shape (update once tooling exists):
- `composer install` (PHP deps, if used)
- `npm ci && npm run build` (asset build, if used)

## Coding & Security Conventions

- Indent with 2 spaces; keep files `kebab-case` and code identifiers `camelCase`/`PascalCase`.
- Never commit `.env`, API keys, DB credentials, or production data. Keep secrets in environment variables and maintain `.env.example`.

## Testing & PRs

- Add tests under `tests/` or `src/**/__tests__/` and name them `*.test.*`. Mock IO in unit tests.
- Commits: `feat(scope): ...`, `fix(scope): ...`, `chore(scope): ...`.
- PRs should include: what changed, how it was tested (commands and results), and screenshots for any UI/template changes.
