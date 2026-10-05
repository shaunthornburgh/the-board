# The Board

A small members app for appraised development opportunities. Screening and analysis happen outside this app. The Board stores and displays appraisals, and the operator decides what members see. It does not decide whether a deal passes. Deals are entered by the operator; there is no bot endpoint.

v1 shows permitted-development deals (type `pd`, labelled *Permitted development*). Deals belong to a `development_types` table, so other types can be added later. Only `pd` is seeded.

## Two apps

| Folder      | Owns |
| ----------- | ---- |
| `frontend/` | The site. Nuxt 4 + Tailwind. Every page route: public homepage, login, invite and request-invite pages, board, deal page, settings, user menu, and the operator screens (`/operator/deals`, `/operator/members`). It calls the API with a Bearer token. |
| `backend/`  | The API. Laravel 13, JSON only, every route in `routes/api.php` under `/api`. Sanctum personal access tokens, deals, members, PDF packs, photo storage, migrations and seed data. `routes/web.php` only redirects `/` to the site. |
| root        | `compose.yaml` (runs both), this README. |

## Run it

```sh
docker compose up
```

| What | URL |
| ---- | --- |
| Site (Nuxt) | **http://localhost:3000** |
| API (Laravel) | **http://localhost:8000/api** |

Compose starts two services, built from `backend/Dockerfile` and `frontend/Dockerfile`:

- **app** (Laravel, port 8000) runs the migrations and seeds the sample data on first start, then serves the API.
- **web** (Nuxt, port 3000) waits until the API is healthy. While rendering on the server it calls the API at `http://app:8000`; in the browser it calls `http://localhost:8000`. Set these with `NUXT_API_BASE` and `NUXT_PUBLIC_API_BASE`. The API only accepts browser calls from `FRONTEND_URL` (CORS).

The SQLite database and uploaded photos live on the `board-data` volume, so changes survive a restart. Seeding runs once per volume. To start again from fresh sample data:

```sh
docker compose down -v && docker compose up
```

## Logins

| Role     | Email                    | Password   |
| -------- | ------------------------ | ---------- |
| Operator | `operator@theboard.test` | `password` |
| Member   | `member@theboard.test`   | `password` |

These are demo credentials for a local install only.

## Auth

The API uses Laravel Sanctum personal access tokens. There are no session cookies, no CSRF cookie and no `/spa` routes.

- `POST /api/login` with `email` and `password` returns `{ token, user }`.
- Send the token on every call as `Authorization: Bearer <token>`.
- `GET /api/me` returns the user for the token (or `null` without one) and the public membership terms.
- `POST /api/logout` revokes the token.
- Tokens expire after 30 days (`SANCTUM_EXPIRATION`, in minutes). Revoking a member deletes their tokens; changing your password deletes your other tokens.

The site keeps the token in its own `board_token` cookie so it can render signed-in pages on the server. *Keep me signed in* keeps that cookie for 30 days; otherwise it ends with the browser session.

```sh
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"member@theboard.test","password":"password"}' | jq -r .token)

curl -s http://localhost:8000/api/deals/1 -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## API routes

| Route | Who |
| ----- | --- |
| `GET /api/me`, `GET /api/teasers`, `POST /api/login`, `POST /api/invite-requests`, `GET`/`POST /api/invite/{token}`, `GET /api/photos/{file}` | Anyone |
| `POST /api/logout`, `GET /api/account`, `PUT /api/settings` | Any token |
| `GET /api/board`, `GET /api/deals/{id}`, `GET /api/deals/{id}/pack` | Active member or operator token; drafts are 404 to members |
| `/api/operator/deals…`, `/api/operator/members…` | Operator token; members get 403 |

## What is in it

- **Homepage** (`/`, public): banner with John's photo, the four benefits, the price, and every published deal as a teaser (photo, town, asking price, one line). No address, comps, tenant, vacant area or pack. *Request an invite* opens a short form; requests appear on the operator's Members page. Nothing is emailed.
- **Board** (`/board`): published deals, newest first, filtered by county, with a map. Click a pin or a card to open the deal.
- **Deal** (`/deals/{id}`, members only; logged-out visitors go to the login page and come back to the deal): address, photo, asking price, tenure, type, source, the appraisal note, tenant, vacant area, comparables and evidence. If the operator has generated a pack, members can download it as a PDF (an authenticated API call).
- **Operator deals** (`/operator/deals`, operator only; members get a 404): drafts and published deals. Publish, unpublish and edit, upload the deal's photo and write the teaser line shown on the homepage. Paste a listing URL to start an empty draft.
- **Members** (`/operator/members`, operator only): invite a member, mark them invited or active, or revoke them. An invite produces a link (with a copy button) where the member sets their own password. Invite requests from the homepage form are listed alongside. Nothing is emailed.
- **User menu** (top right, every page): logged out it shows *Log in*; logged in, the name opens *Settings* and *Sign out*, plus *Operator screens* for the operator. Signing out revokes the token and returns to the homepage.
- **Settings** (`/settings`): change your own name, email and password. A new password or email needs the current password. Price (£500 per month ex VAT) and the three-month minimum term are shown read-only.

### Rules the app enforces

- Members see published deals only. A draft returns 404 to a member, including its pack.
- New deals start as drafts and stay hidden until the operator publishes them.
- Membership is invite only. There is no sign-up page.
- Invited and active members both hold a place, and the room is capped at 20. You cannot invite or reinstate anyone once the room is full.
- A revoked member cannot sign in, and their tokens are deleted straight away.
- An invited member can sign in but only sees their settings page. They reach the board once the operator sets them active. The three-month minimum term runs from the date they are set active.
- The operator can only publish a deal that has an address and its own photo, stored and served by the API. A verdict is not needed.
- The appraisal verdict (`send` or `mention`) is shown to the operator only, on the edit form. Members and visitors never see it.

### PDF pack

When the operator clicks *Generate pack*, the deal gets a pack. Each download is built from the stored appraisal and stamped with the downloading member's email and the time. Every page carries the line: *Screen of listing material, not a valuation, not advice.*

## Sample data

There are 20 fictional deals, marked **Sample** and referenced `SAMPLE-01` to `SAMPLE-20`, spread across the South East and East of England. 14 are published and 6 are drafts. Street names, tenants and listings are all made up, and source links point to `example.com`. Towns and coordinates are approximate so the map works. Nothing is scraped.

Each sample deal has its own photo in `backend/public/images/deals/`, taken from Wikimedia Commons. The photos are illustrative and do not show the listed properties; authors and licences are in `backend/public/images/deals/CREDITS.md`. Operator uploads are stored on the `board-data` volume and served from `/api/photos`.

Map tiles load from OpenStreetMap, so the map needs an internet connection.

## Running without Docker

Docker Compose is the supported path. With PHP 8.3+, Composer and Node 22.19+ installed locally:

```sh
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve            # API on http://localhost:8000

cd frontend
npm install && npm run dev   # site on http://localhost:3000
```

In `backend/.env`, `APP_URL` is the API (`http://localhost:8000`) and `FRONTEND_URL` is the site (`http://localhost:3000`), as in `.env.example`. The Nuxt defaults already point at `http://localhost:8000`.

Run the API tests with `cd backend && php artisan test`.

## Stack

Nuxt 4 and Tailwind 4 (default theme) in `frontend/`, with Leaflet for the map. Laravel 13 in `backend/` as a JSON API, with Sanctum, SQLite and dompdf for the pack.

## Out of scope

Stripe and billing, forum and comments, any screening logic, a bot or import endpoint, crawling and scraping, emailing invites, an editor role and a GDV calculator.
