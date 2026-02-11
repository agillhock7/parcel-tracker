# Repository Guidelines

## Project Structure & Module Organization

- `src/`: application source code.
- `src/routes/`: HTTP route handlers (keep them thin; delegate to services/repositories).
- `src/repositories/`: data access (DB/files/external APIs). Keep IO here.
- `views/`: server-rendered templates.
- `views/partials/`: shared template fragments.
- `views/projects/`: project-specific pages/templates.
- `public/`: static assets (CSS, images, client JS).
- `data/`: local data files used for development (do not store secrets here).

If you add new top-level folders, document them here and keep responsibilities non-overlapping.

## Build, Test, and Development Commands

This repo currently has no committed build tooling in this workspace. When adding Node tooling, standardize on:

- `npm run dev`: run locally with file watching.
- `npm run test`: run the full test suite.
- `npm run lint`: static checks (fail CI on warnings where possible).
- `npm run format`: auto-format the codebase.
- `npm run build`: produce production artifacts (if applicable).

Keep scripts in `package.json` as the single source of truth for local/CI commands.

## Coding Style & Naming Conventions

- Indentation: 2 spaces for JS/TS/JSON/CSS; no tabs.
- Files: `kebab-case` for routes/templates (`tracking-status.ts`, `shipment-details.ejs`).
- Identifiers: `camelCase` for variables/functions, `PascalCase` for types/classes.
- Prefer small modules with explicit exports; avoid circular dependencies between `routes/` and `repositories/`.

If you introduce formatters/linters (e.g., Prettier/ESLint), run them via `npm run format` / `npm run lint`.

## Testing Guidelines

- Place tests under `tests/` (or `src/**/__tests__/`), and name them `*.test.*`.
- Unit tests should mock IO; integration tests may touch `data/` fixtures.
- Add a short README section describing how to run tests once a framework is chosen (Jest/Vitest/etc.).

## Commit & Pull Request Guidelines

No git history is available in this workspace, so follow this convention until the project establishes one:

- Commits: `type(scope): summary` (e.g., `fix(tracking): handle missing carrier`).
- PRs: include a clear description, testing notes (`npm run test` output), and screenshots for UI/template changes.
- Keep PRs focused; link issues when applicable.

## Security & Configuration Tips

- Store secrets in environment variables; commit an `.env.example`, not `.env`.
- Don’t commit API keys, tokens, or production data into `data/` or `public/`.
