# The Board

A small members app for appraised development opportunities, and the system of record for the **scout → analyst → review → published** pipeline. Scout bots push candidates over the API, the operator triages them, the Analyst bot gives a verdict, and the operator makes the final call on what members see. The app does not screen deals itself.

Every deal has one development type (`development_types`, only `pd` seeded) shown to members, and any number of strategy tags (`strategies`: PD, HMO, Potential HMO, Social housing) for the operator.

## The pipeline

```
new ──► awaiting_analyst ──► analyst_done ──► awaiting_review ──► published
 │                                │                 │    ▲             │
 └──► rejected ◄──────────────────┴─────────────────┘    └─ unpublish ─┘
```

| Status | How a deal gets here | What the operator can do |
| ------ | -------------------- | ------------------------ |
| `new` | A scout pushes it, or the operator pastes a listing URL | Send to Analyst, Reject |
| `awaiting_analyst` | *Send to Analyst* | Wait; Retry if the call failed; record the result by hand |
| `analyst_done` | The Analyst job (or the operator) records a verdict | Move to awaiting review, Reject |
| `awaiting_review` | *Move to awaiting review*, or *Unpublish* | Publish, Reject |
| `published` | *Publish* (needs an address and the deal's own photo) | Unpublish |
| `rejected` | *Reject*, with an optional reason | Nothing; rejected is final for now |

There is no skipping the analyst, and no restoring a rejected deal. Every move goes through `App\Services\DealPipeline`, which refuses anything else with a 422 and writes each change to `deal_status_events` (who, when, from, to, note). The deal page shows that history.

Members only ever see `published` deals. The analyst's verdict and notes, scout notes and rejection reasons are operator-only.

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
| `GET /api/board`, `GET /api/deals/{id}`, `GET /api/deals/{id}/pack` | Active member or operator token; anything not published is 404 to members |
| `/api/scout/…` | Scout service token (`scout:write`); everyone else gets 403 |
| `/api/operator/deals…`, `/api/operator/members…` | Operator token; members get 403 |

### Operator deal endpoints

All under `/api/operator`, operator token only. Pipeline actions return `{ message, deal, history }`; a move that is not allowed from the deal's status returns 422 with a `message`.

| Route | Does |
| ----- | ---- |
| `GET /deals?status=new&q=&page=` | One status, 100 per page, newest change first, plus `statuses` with a count each. Without `status`, every deal. |
| `POST /deals` `{source_url}` | Add a deal by hand. Starts as `new`. |
| `GET /deals/{id}`, `PUT /deals/{id}` | Read and edit. `PUT` accepts `strategies: ["hmo", …]` (slugs); leave it out to keep the tags. |
| `POST /deals/{id}/reject` `{reason?}` | From `new`, `analyst_done` or `awaiting_review` |
| `POST /deals/bulk/reject` `{ids[], reason?}` | Same, many at once. Returns `done` ids and `skipped` (id → why). |
| `POST /deals/{id}/send-to-analyst` | `new` → `awaiting_analyst`, then runs the Analyst job. On a deal already awaiting the analyst, retries. |
| `POST /deals/bulk/send-to-analyst` `{ids[]}` | Same, up to 100 |
| `POST /deals/{id}/analyst-result` `{verdict, notes?}` | Record the analyst's answer by hand: `send`, `mention`, `reject` (`KILL` is accepted and stored as `reject`). `awaiting_analyst` → `analyst_done`. |
| `POST /deals/{id}/awaiting-review` | `analyst_done` → `awaiting_review` |
| `POST /deals/{id}/publish` | `awaiting_review` → `published` (address and own photo required) |
| `POST /deals/{id}/unpublish` | `published` → `awaiting_review` |
| `POST /deals/{id}/photo`, `POST`/`DELETE /deals/{id}/pack` | Unchanged |

## Scout API

Scouts push candidates and track what they have seen here instead of in Google Drive CSVs. A listing is identified by `source` (e.g. `rightmove`, lower-cased by the API) plus `source_key` (the listing's ID or short code).

**Issue a token** for each scout. It is shown once, and it does not expire unless you pass `--days`:

```sh
docker compose exec app php artisan board:scout-token rightmove-scout
docker compose exec app php artisan board:scout-token rightmove-scout --revoke   # delete its tokens
```

This creates a `service` account (`rightmove-scout@scouts.theboard.internal`) that cannot sign in or see the board. Scout routes check the role as well as the `scout:write` ability, so an operator's login token cannot push. They are limited to 120 requests a minute.

**`POST /api/scout/candidates`**: up to 100 per call.

```sh
curl -s -X POST http://localhost:8000/api/scout/candidates \
  -H "Authorization: Bearer $SCOUT_TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"candidates": [{
        "source": "rightmove", "source_key": "150001",
        "source_url": "https://www.rightmove.co.uk/properties/150001",
        "address": "1 High Street", "town": "Ashford", "county": "Kent", "postcode": "TN23",
        "asking_price": 500000, "tenure": "freehold", "type": "pd",
        "strategies": ["PD", "Potential HMO"],
        "notes": "Upper floors vacant.", "payload": {"anything": "kept as-is"}
      }]}'
```

Only `source` and `source_key` are required. `strategies` takes labels or slugs in any case; unknown ones fail that candidate. Each candidate gets its own result, so one bad row does not sink the batch:

```json
{ "created": 1, "duplicates": 0, "invalid": 0,
  "results": [{ "index": 0, "result": "created", "source_key": "150001", "deal_id": 31, "status": "new" }] }
```

`result` is `created` (a new deal in `new`), `duplicate` (that listing is already a deal: `deal_id` and its `status` are returned, nothing changes) or `invalid` (with `errors`). The response is 201 if anything was created, otherwise 200. Every pushed candidate is also marked seen.

**`POST /api/scout/seen`** `{"listings": [{"source", "source_key", "source_url?"}]}`: mark up to 500 listings as seen, whether or not they became candidates. Seeing one again updates `last_seen_at`.

**`POST /api/scout/seen/check`** `{"listings": [{"source", "source_key"}]}`: returns `{"seen": [...]}` with just the ones seen before (with `first_seen_at`, `last_seen_at`, `scout`, `deal_id`). Unseen listings are left out.

## Analyst

*Send to Analyst* moves the deal to `awaiting_analyst` and dispatches `App\Jobs\RunAnalyst`. The job calls the `AnalystClient` chosen by `ANALYST_DRIVER` and writes the answer back:

- `analyst_verdict` (`send` / `mention` / `reject`; the bot's `KILL` means `reject`), `analyst_notes` and `analyst_completed_at`, and the deal moves to `analyst_done`.
- If the operator has not set the member-facing verdict yet, a `send` or `mention` is copied into it, and `appraised_at` is set. The analyst's notes are **not** copied into the appraisal note members read; the operator writes that.
- If the call fails, the message is saved in `analyst_error` and shown on the deal, and the deal stays in `awaiting_analyst` with a *Retry* button. On a real queue the job also retries itself twice (after 30s, then 2 minutes).

| Driver | Behaviour |
| ------ | --------- |
| `stub` (default) | `StubAnalystClient`. No network. Returns a placeholder verdict that is the same each time for a given deal, with notes starting `[stub]`. |
| `grok` | `GrokAnalystClient`. **Not yet tested against the real bot.** Sends `POST GROK_ANALYST_URL` with `Authorization: Bearer GROK_API_KEY` and `{ "model": GROK_MODEL, "deal": { id, reference, source, source_key, source_url, address, town, county, postcode, asking_price, tenure, type, strategies[], scout_notes } }`, and expects `{ "verdict": "SEND" \| "MENTION" \| "KILL" \| "REJECT", "notes": "…" }`. Any other response is recorded as an error. |

**To wire up the real bot:** make the bot accept that request (or change the body and response parsing in `GrokAnalystClient::analyse()`), set the env vars below, and set `ANALYST_DRIVER=grok`. Compose runs jobs on the `sync` queue, so the Analyst call happens inside the operator's request. A real bot can be slow, so also set `QUEUE_CONNECTION=database` and add a worker service to `compose.yaml` running `php artisan queue:work --tries=3` with the same image, environment and volume as `app`. The page then shows *Waiting for the analyst…* until the job finishes.

## Environment variables

Added in this release (see `backend/.env.example`):

| Variable | Default | Purpose |
| -------- | ------- | ------- |
| `ANALYST_DRIVER` | `stub` | `stub` or `grok` |
| `ANALYST_TIMEOUT` | `60` | Seconds to wait for the Analyst bot |
| `GROK_ANALYST_URL` | — | The Analyst bot endpoint |
| `GROK_API_KEY` | — | Bearer token for it |
| `GROK_MODEL` | — | Passed through as `model` |
| `SANCTUM_EXPIRATION` | `43200` | Minutes a **site login** token lasts. Now set on each token (`expires_at`), so scout tokens can last longer. |

## What is in it

- **Homepage** (`/`, public): banner with John's photo, the four benefits, the price, and every published deal as a teaser (photo, town, asking price, one line). No address, comps, tenant, vacant area or pack. *Request an invite* opens a short form; requests appear on the operator's Members page. Nothing is emailed.
- **Board** (`/board`): published deals, newest first, filtered by county, with a map. Click a pin or a card to open the deal.
- **Deal** (`/deals/{id}`, members only; logged-out visitors go to the login page and come back to the deal): address, photo, asking price, tenure, type, source, the appraisal note, tenant, vacant area, comparables and evidence. If the operator has generated a pack, members can download it as a PDF (an authenticated API call).
- **Operator deals** (`/operator/deals`, operator only; members get a 404): the pipeline, with a tab and count for each status and a search box. Each row shows strategy tags, the analyst's verdict (or the error from a failed call) and the actions its status allows: *Send to Analyst*, *Move to review*, *Publish*, *Unpublish*, *Reject*. Tick rows to reject (with a reason) or send to the analyst in bulk. Paste a listing URL to add a deal by hand.
- **Operator deal page** (`/operator/deals/{id}`): the same actions, *Reject…* with a reason, an Analyst panel (verdict, notes, when it was asked and answered, any error, *Retry*, *Record result by hand*), the scout's listing ID and notes, and the status history. Below that, edit the deal, toggle its strategy tags, upload its photo, write the teaser line and generate the pack.
- **Members** (`/operator/members`, operator only): invite a member, mark them invited or active, or revoke them. An invite produces a link (with a copy button) where the member sets their own password. Invite requests from the homepage form are listed alongside. Nothing is emailed.
- **User menu** (top right, every page): logged out it shows *Log in*; logged in, the name opens *Settings* and *Sign out*, plus *Operator screens* for the operator. Signing out revokes the token and returns to the homepage.
- **Settings** (`/settings`): change your own name, email and password. A new password or email needs the current password. Price (£500 per month ex VAT) and the three-month minimum term are shown read-only.

### Rules the app enforces

- Members see published deals only. Any other status returns 404 to a member, including the pack.
- New deals start as `new` and stay hidden until the operator publishes them. Only a deal in `awaiting_review` can be published.
- Membership is invite only. There is no sign-up page.
- Invited and active members both hold a place, and the room is capped at 20. You cannot invite or reinstate anyone once the room is full.
- A revoked member cannot sign in, and their tokens are deleted straight away.
- An invited member can sign in but only sees their settings page. They reach the board once the operator sets them active. The three-month minimum term runs from the date they are set active.
- The operator can only publish a deal that has an address and its own photo, stored and served by the API. A verdict is not needed.
- The appraisal verdict (`send` or `mention`), the analyst's verdict and notes, scout notes and rejection reasons are shown to the operator only. Members and visitors never see them.
- A listing (`source` + `source_key`) can only become one deal.

### PDF pack

When the operator clicks *Generate pack*, the deal gets a pack. Each download is built from the stored appraisal and stamped with the downloading member's email and the time. Every page carries the line: *Screen of listing material, not a valuation, not advice.*

## Sample data

There are 24 fictional deals, marked **Sample** and referenced `SAMPLE-01` to `SAMPLE-24`, spread across the South East and East of England. 14 are published, 6 are awaiting review, and 4 (`SAMPLE-21` to `SAMPLE-24`) are scout candidates in `new` with no photo or appraisal, so there is something to send to the analyst. Street names, tenants and listings are all made up, and source links point to `example.com`. Towns and coordinates are approximate so the map works. Nothing is scraped.

Each sample deal has its own photo in `backend/public/images/deals/`, taken from Wikimedia Commons. The photos are illustrative and do not show the listed properties; authors and licences are in `backend/public/images/deals/CREDITS.md`. Operator uploads are stored on the `board-data` volume and served from `/api/photos`.

Map tiles load from OpenStreetMap, so the map needs an internet connection.

### Upgrading an existing volume

`docker compose up` runs the new migrations on start. Existing drafts become `awaiting_review`, published deals stay published, the four strategy tags are created, and site login tokens get an `expires_at` 30 days after they were issued. The four new sample candidates only appear on a fresh volume (`docker compose down -v`).

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

Stripe and billing, student pay-per-deal, forum and comments, any screening logic in the app itself, crawling and scraping, emailing invites, an editor role, a GDV calculator, and restoring rejected deals.
