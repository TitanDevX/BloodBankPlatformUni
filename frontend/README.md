# Hema frontend

React + Vite frontend for the blood bank platform. All project changes are contained in `frontend/`.

## Run

```sh
npm install
npm run dev
```

On Windows PowerShell with restricted script execution, use `npm.cmd` instead of `npm`.

`npm run build` creates `dist/`; `npm test` checks the role navigation boundaries.

## Screens

- `#/login`: staff sign-in, password visibility, validation, recovery guidance, demo entry.
- `#/register`: staff access-request draft. Download it for your administrator; it does not create an account or send an email. Donors have no login, and public registration is not in the documented API.
- `#/workspace/Overview`: responsive shared workspace with role-specific navigation. Demo mode previews all eight documented roles, and contains no operational records. Module destinations are explicit layout placeholders, not implemented clinical workflows.

## Connect authentication

Copy `.env.example` to `.env.local` and set `VITE_API_BASE_URL` to the backend URL including `/v1`. Restart Vite. Login calls `POST /sessions` with `{email,password}` and consumes the documented `{user,accessToken}` response. Logout calls `DELETE /sessions/current`. The backend needs compatible CORS configuration. Tokens live only in React memory, so a reload signs out locally; no passwords or bearer tokens are persisted. Server-side token expiry/revocation must be configured by the backend. If logout fails, the frontend warns that server revocation was not confirmed.

The API reference describes proposed endpoints, not working routes. No account-request, reset-password or operational endpoints are invented here. Real operational modules must implement authenticated requests, handle expired/deactivated sessions, and use authoritative server authorization. Frontend navigation is only a UX boundary. Do not expose the demo as authenticated backend access.

## Design and source decisions

Warm ivory, oxblood and muted sage; a custom droplet/cross logo; an original SVG/CSS journey illustration; shared form fields, buttons, dialogs, brand and network illustration. Google Fonts provide DM Sans and Manrope with local system fallbacks. No stock photography or remote image dependency.

Reference: `docs/BloodBank_Requirements.docx` (FR-01/02/17), `docs/role_permission_matrix.xlsx`, `docs/BACKEND_API_REFERENCE.md`, API conventions, software architecture and master ERD. Specialist role mappings remain proposed in the source; navigation reflects them without granting administrators implicit collection/laboratory powers. Bank-admin staff management is a navigation destination for same-bank limited assignment, not general account creation.

Keyboard focus, semantic labels, native form validation, modal focus containment, reduced-motion support and responsive navigation are included. Replace preview privacy/support guidance with institution-specific content before production.
