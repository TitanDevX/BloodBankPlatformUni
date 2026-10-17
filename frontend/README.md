# Hema frontend

React + Vite frontend for the blood bank platform. All project changes are contained in `frontend/`.

## Run

```sh
npm install
npm run dev
```

On Windows PowerShell with restricted script execution, use `npm.cmd` instead of `npm`.

`npm run build` creates `dist/`; `npm test` checks role navigation and the session API client.

## Screens

- `#/login`: staff sign-in, password visibility, validation, recovery guidance, demo entry.
- Staff accounts are created by an administrator through the backend API. There is no public registration screen.
- `#/workspace/Overview`: responsive shared workspace with role-specific navigation. Demo mode previews all eight documented roles, and contains no operational records. Module destinations are explicit layout placeholders, not implemented clinical workflows.

## Connect authentication

Run Laravel on `127.0.0.1:8001` and Vite with `npm run dev`. Vite forwards `/v1` requests to Laravel, so login works without a frontend environment file. To use another Laravel origin, set `VITE_BACKEND_ORIGIN` in `.env.local`. For a separate API origin in deployment, set `VITE_API_BASE_URL` including `/v1` and configure CORS; otherwise route `/v1` to Laravel on the same origin. Login calls `POST /v1/sessions` with `{email,password}` and consumes `{user,accessToken}`. Logout calls `DELETE /v1/sessions/current`. Tokens live only in React memory, so a reload signs out locally; no passwords or bearer tokens are persisted. If logout fails, the frontend warns that server revocation was not confirmed.

The session and user-management API routes are implemented; other operational routes in the API reference remain proposed. Real operational modules must implement authenticated requests, handle expired/deactivated sessions, and use authoritative server authorization. Frontend navigation is only a UX boundary. Do not expose the demo as authenticated backend access.

## Design and source decisions

Warm ivory, oxblood and muted sage; a custom droplet/cross logo; an original SVG/CSS journey illustration; shared form fields, buttons, dialogs, brand and network illustration. Google Fonts provide DM Sans and Manrope with local system fallbacks. No stock photography or remote image dependency.

Reference: `docs/BloodBank_Requirements.docx` (FR-01/02/17), `docs/role_permission_matrix.xlsx`, `docs/BACKEND_API_REFERENCE.md`, API conventions, software architecture and master ERD. Specialist role mappings remain proposed in the source; navigation reflects them without granting administrators implicit collection/laboratory powers. Bank-admin staff management is a navigation destination for same-bank limited assignment, not general account creation.

Keyboard focus, semantic labels, native form validation, modal focus containment, reduced-motion support and responsive navigation are included. Replace preview privacy/support guidance with institution-specific content before production.
