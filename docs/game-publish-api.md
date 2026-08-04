# Game Resource API — Agent Guide

Use this document to **create, list, inspect, update, and delete** game resources on **hgame** via JSON API.

On this site, public “resources” are games. There is no separate resource type in the API yet.

No multipart uploads: send publicly reachable image URLs; the server downloads them.

## Base URL

| Environment | Base |
|-------------|------|
| Local (Herd) | `http://hgame.test` |
| API prefix | `/api/v1` |

Full endpoint base: `http://hgame.test/api/v1`

## Authentication

All endpoints require:

```http
Authorization: Bearer {TOKEN}
Accept: application/json
Content-Type: application/json
```

- Token must belong to a user with `is_admin = true`.
- Mint a token in **Admin → Settings → API tokens** (listed and copyable anytime), or via CLI:

```bash
php artisan game:token {admin-email-or-id} --name=game-publish
```

- Rate limit: **60 requests / minute** per token.
- Non-admin → `403`. Missing/invalid token → `401`.

## Recommended workflow

1. `GET /api/v1/taxonomies` — load allowed categories, platforms, languages.
2. Host cover, screenshot, and any detail/release body images somewhere HTTP-accessible (JPEG/PNG/WebP/GIF, ≤ 20MB each).
3. `POST /api/v1/games` — create the game in one request (detail `<img src>` URLs are ingested automatically).
4. `GET /api/v1/games` or `GET /api/v1/games/{slug}` — list or inspect.
5. `PATCH /api/v1/games/{slug}` — partial edit (only send fields that change).
6. `DELETE /api/v1/games/{slug}` — remove when needed.
7. Open `data.url` in responses for the public details page.

## Endpoints

### GET `/api/v1/taxonomies`

Returns existing taxonomies. **Do not invent** category / platform / language names; match these values (name or slug/code).

**Response `200`:**

```json
{
  "data": {
    "categories": [{ "name": "Visual Novel", "slug": "visual-novel" }],
    "platforms": [{ "name": "Windows", "slug": "windows" }],
    "languages": [{ "name": "Chinese", "code": "zh" }],
    "sources": [
      { "name": "DLsite", "slug": "dlsite", "favicon_url": "/images/sources/dlsite.ico" },
      { "name": "Steam", "slug": "steam", "favicon_url": "/images/sources/steam.ico" }
    ]
  }
}
```

Resolution rules when saving:

| Field | Match against |
|-------|----------------|
| `category` | category `name` or `slug` (case-insensitive name) |
| `releases[].platforms[]` | platform `name` or `slug` |
| `releases[].languages[]` | language `name` or `code` |
| `source_name` | Prefer a `sources[].name` from taxonomies (`DLsite` or `Steam`) for the local storefront icon |
| `tags[]` | created if missing (multi-word tags OK as one array item) |

---

### GET `/api/v1/games`

List games (all statuses; admin catalog, not only public published ones).

**Query parameters:**

| Param | Notes |
|-------|--------|
| `q` | Search title, subtitle, developer, category, tags, platforms, languages |
| `status` | `draft` \| `published` \| `unlisted` |
| `category` | Category name or slug |
| `page` | Page number (default 1) |
| `per_page` | 1–100 (default 20) |

**Response `200`:**

```json
{
  "data": [
    {
      "id": "senren-banka",
      "title": "Senren Banka",
      "subtitle": "A spring tale",
      "status": "published",
      "category": "Visual Novel",
      "developer": "Yuzu Soft",
      "url": "http://hgame.test/resources/senren-banka/details",
      "cover_url": "http://hgame.test/storage/games/covers/....png",
      "published_at": "2026-07-29T12:00:00+00:00",
      "screenshots_count": 2,
      "releases_count": 1
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

---

### POST `/api/v1/games`

Create a game with optional screenshots and releases.

**Success:** `201`  
**Validation / media errors:** `422` with `errors` object  
**Auth errors:** `401` / `403`

#### Minimal body

```json
{
  "title": "Senren Banka",
  "cover_url": "https://cdn.example.com/cover.png"
}
```

#### Full body (recommended for agents)

```json
{
  "title": "Senren Banka",
  "subtitle": "A spring tale",
  "slug": "senren-banka",
  "category": "Visual Novel",
  "tags": ["Romance", "Slice of Life"],
  "developer": "Yuzu Soft",
  "source_name": "DLsite",
  "source_id": "RJ01123456",
  "source_url": "https://www.dlsite.com/maniax/work/=/product_id/RJ01123456.html",
  "release_date": "2016-07-29",
  "description": "<p>Short HTML synopsis is allowed.</p><p><img src=\"https://cdn.example.com/detail-1.png\" alt=\"Scene\"></p>",
  "cover_url": "https://cdn.example.com/cover.png",
  "status": "published",
  "screenshots": [
    "https://cdn.example.com/shot-1.png",
    "https://cdn.example.com/shot-2.png"
  ],
  "releases": [
    {
      "title": "Windows Chinese package",
      "platforms": ["Windows"],
      "languages": ["Chinese"],
      "version": "1.0",
      "file_size": "5.4 GB",
      "description": "<p>Optional release notes with <img src=\"https://cdn.example.com/patch-notes.png\"></p>",
      "download_links": [
        "https://example.com/game.zip"
      ]
    }
  ]
}
```

#### Field reference (create)

| Field | Required | Notes |
|-------|----------|--------|
| `title` | yes | max 255 |
| `cover_url` | yes | Absolute URL; server downloads image |
| `subtitle` | no | max 255 |
| `slug` | no | `alpha_dash`, unique; auto from title if omitted |
| `category` | no | Must already exist (name or slug) |
| `tags` | no | Array of strings; created if missing |
| `developer` | no | max 255 |
| `source_name` | no | Prefer **`DLsite`** or **`Steam`** (see `/taxonomies` → `sources`). Local favicon icons are used for both. |
| `source_id` | no | Work ID, e.g. DLsite `RJ01123456` or Steam App ID `1234560` |
| `source_url` | no | Absolute product page URL (e.g. DLsite work page or `https://store.steampowered.com/app/…`) |
| `release_date` | no | Date string, e.g. `2016-07-29` |
| `description` | no | HTML string; remote `<img src>` are downloaded (see Images) |
| `status` | no | `draft` \| `published` \| `unlisted` (default **`published`**) |
| `published_at` | no | Default `now()` when status is not `draft` |
| `screenshots` | no | Array of image URLs, max 50 |
| `releases` | no | Array of release objects |

**Each release:**

| Field | Required | Notes |
|-------|----------|--------|
| `title` | yes | max 255 |
| `platforms` | yes | ≥1 existing platform name/slug |
| `languages` | yes | ≥1 existing language name/code |
| `download_links` | yes | ≥1 absolute URLs |
| `version` | no | |
| `file_size` | no | Display string, e.g. `"5.4 GB"` |
| `description` | no | HTML; remote `<img src>` are downloaded like game details |
| `is_active` | no | default `true` |
| `published_at` | no | default `now()` |

Download link labels are derived from the URL host automatically.

#### Success response `201` / detail shape

```json
{
  "data": {
    "id": "senren-banka",
    "title": "Senren Banka",
    "subtitle": "A spring tale",
    "status": "published",
    "category": "Visual Novel",
    "tags": ["Romance", "Slice of Life"],
    "developer": "Yuzu Soft",
    "source_name": "DLsite",
    "source_id": "RJ01123456",
    "source_url": "https://www.dlsite.com/maniax/work/=/product_id/RJ01123456.html",
    "source": {
      "name": "DLsite",
      "id": "RJ01123456",
      "url": "https://www.dlsite.com/maniax/work/=/product_id/RJ01123456.html",
      "faviconUrl": "/images/sources/dlsite.ico"
    },
    "release_date": "2016-07-29",
    "description": "<p>Short HTML synopsis is allowed.</p>...",
    "cover_url": "http://hgame.test/storage/games/covers/....png",
    "published_at": "2026-07-29T12:00:00+00:00",
    "url": "http://hgame.test/resources/senren-banka/details",
    "screenshots": [
      "http://hgame.test/storage/games/screenshots/....png"
    ],
    "releases": [
      {
        "title": "Windows Chinese package",
        "platforms": ["Windows"],
        "languages": ["Chinese"],
        "version": "1.0",
        "file_size": "5.4 GB",
        "description": null,
        "is_active": true,
        "published_at": "2026-07-29T12:00:00+00:00",
        "download_links": ["https://example.com/game.zip"]
      }
    ],
    "screenshots_count": 2,
    "releases_count": 1
  }
}
```

- `id` is the **slug** (use it for list/show/update/delete).
- Public page shows the game when `status=published` and `published_at <= now`.
- Releases appear on the site only if `is_active=true` and they have at least one download link.

---

### GET `/api/v1/games/{slug}`

Full detail for one game (same `data` shape as create/update).

**Response `200`** / **`404`** if slug missing.

---

### PUT / PATCH `/api/v1/games/{slug}`

Partial update. **Only fields present in the JSON body are changed.**

Examples:

- Metadata only: `{ "title": "New title", "status": "draft" }`
- Replace cover: `{ "cover_url": "https://cdn.example.com/new-cover.png" }`
- Replace all tags: `{ "tags": ["Romance"] }` (empty array clears tags)
- Replace all screenshots: `{ "screenshots": ["https://..."] }` (empty array clears)
- Replace **all** releases: `{ "releases": [ ... ] }` (empty array clears)

When `releases` is sent, existing releases are deleted and recreated from the payload (full replace).

**Success:** `200` with full detail payload.  
**Validation:** `422`. **Not found:** `404`.

Field rules match create, except:

| Field | Notes |
|-------|--------|
| All fields | optional (`sometimes`) — omit to leave unchanged |
| `cover_url` | if sent, must be a valid URL (downloads new cover, drops old) |
| `slug` | if sent, must stay unique |

---

### DELETE `/api/v1/games/{slug}`

Permanently deletes the game, related releases/links/screenshots, and unreferenced media files.

**Response `200`:**

```json
{
  "data": {
    "id": "senren-banka",
    "deleted": true
  }
}
```

**Not found:** `404`.

## Images (important)

- **No** `multipart/form-data` file upload in v1.
- Pass `cover_url` and `screenshots[]` as **HTTPS/HTTP URLs** the server can fetch.
- **Details / release HTML images:** put remote URLs in `<img src="https://...">` inside `description` (or `releases[].description`). The server downloads each unique remote image into `games/content` and rewrites `src` to a local `/storage/...` path (same as admin RichEditor attachments).
- Already-local paths (`/storage/games/...` or full site URLs pointing at them) are left unchanged.
- `data:` URIs are rejected.
- Max remote images per HTML field: **30**.
- Allowed types: `image/jpeg`, `image/png`, `image/webp`, `image/gif`.
- Max size per file: **20MB**.
- Failed download / wrong type → `422` with `errors.media`, `errors.description`, or `errors.releases.N.description`.

## cURL examples

```bash
TOKEN="your-token-here"
BASE="http://hgame.test/api/v1"

# 1) Taxonomies
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/taxonomies"

# 2) Create
curl -sS -X POST "$BASE/games" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Senren Banka",
    "category": "Visual Novel",
    "tags": ["Romance", "Slice of Life"],
    "developer": "Yuzu Soft",
    "cover_url": "https://cdn.example.com/cover.png",
    "screenshots": ["https://cdn.example.com/shot-1.png"],
    "releases": [{
      "title": "Windows Chinese",
      "platforms": ["Windows"],
      "languages": ["Chinese"],
      "download_links": ["https://example.com/game.zip"]
    }]
  }'

# 3) List
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/games?q=senren&status=published"

# 4) Show
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/games/senren-banka"

# 5) Partial update
curl -sS -X PATCH "$BASE/games/senren-banka" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"title":"Senren Banka (Updated)","status":"unlisted"}'

# 6) Delete
curl -sS -X DELETE "$BASE/games/senren-banka" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

## Common errors

| Status | Meaning | What to do |
|--------|---------|------------|
| `401` | Bad/missing token | Re-mint with `php artisan game:token ...` |
| `403` | User not admin | Use an admin account |
| `404` | Unknown slug | List games or check `id` from create |
| `422` `slug` | Slug already taken | Change `slug` or omit it |
| `422` `category` | Unknown category | Use a name/slug from `/taxonomies` |
| `422` `releases` | Unknown platform/language | Use values from `/taxonomies` |
| `422` `cover_url` / `media` / `description` | Bad or unreachable image | Fix URL; ensure public access and image MIME |
| `429` | Rate limited | Wait; max 60/min |

## Out of scope (do not attempt)

- Multipart binary image upload
- Auto-creating categories, platforms, or languages
- Unauthenticated public JSON catalog
- Docs / non-game content via this API

## Agent checklist

- [ ] Have admin Bearer token
- [ ] Called `/taxonomies` and mapped names
- [ ] Cover image URL is public and ≤ 20MB
- [ ] Screenshot URLs (if any) are public images
- [ ] Detail/release `<img src>` (if any) are public image URLs (not data URIs)
- [ ] Each release has platforms, languages, and ≥1 download link
- [ ] POST `/games` → expect `201` and `data.url`
- [ ] GET `/games` or GET `/games/{id}` to verify
- [ ] PATCH `/games/{id}` for edits (send only changed fields)
- [ ] DELETE `/games/{id}` when removing a resource
