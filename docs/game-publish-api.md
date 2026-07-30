# Game Publish API — Agent Guide

Use this document to publish game resources to **hgame** via JSON API.
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
4. Optional: `GET /api/v1/games/{slug}` — confirm the result.
5. Open `data.url` in the response for the public details page.

## Endpoints

### GET `/api/v1/taxonomies`

Returns existing taxonomies. **Do not invent** category / platform / language names; match these values (name or slug/code).

**Response `200`:**

```json
{
  "data": {
    "categories": [{ "name": "Visual Novel", "slug": "visual-novel" }],
    "platforms": [{ "name": "Windows", "slug": "windows" }],
    "languages": [{ "name": "Chinese", "code": "zh" }]
  }
}
```

Resolution rules when publishing:

| Field | Match against |
|-------|----------------|
| `category` | category `name` or `slug` (case-insensitive name) |
| `releases[].platforms[]` | platform `name` or `slug` |
| `releases[].languages[]` | language `name` or `code` |
| `tags[]` | created if missing (multi-word tags OK as one array item) |

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

#### Field reference

| Field | Required | Notes |
|-------|----------|--------|
| `title` | yes | max 255 |
| `cover_url` | yes | Absolute URL; server downloads image |
| `subtitle` | no | max 255 |
| `slug` | no | `alpha_dash`, unique; auto from title if omitted |
| `category` | no | Must already exist (name or slug) |
| `tags` | no | Array of strings; created if missing |
| `developer` | no | max 255 |
| `source_name` | no | Storefront label in hero, e.g. `DLsite` |
| `source_id` | no | Work ID, e.g. `RJ01123456` |
| `source_url` | no | Absolute product page URL (also used for favicon host) |
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

#### Success response `201`

```json
{
  "data": {
    "id": "senren-banka",
    "title": "Senren Banka",
    "subtitle": "A spring tale",
    "status": "published",
    "url": "http://hgame.test/resources/senren-banka/details",
    "screenshots_count": 2,
    "releases_count": 1
  }
}
```

- `id` is the **slug** (use it for follow-up `GET`).
- Public page shows the game when `status=published` and `published_at <= now`.
- Releases appear on the site only if `is_active=true` and they have at least one download link.

### GET `/api/v1/games/{slug}`

Confirm a created game. Same `data` shape as create.

**Response `200`** / **`404`** if slug missing.

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

# 2) Publish
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

# 3) Confirm
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/games/senren-banka"
```

## Common errors

| Status | Meaning | What to do |
|--------|---------|------------|
| `401` | Bad/missing token | Re-mint with `php artisan game:token ...` |
| `403` | User not admin | Use an admin account |
| `422` `slug` | Slug already taken | Change `slug` or omit it |
| `422` `category` | Unknown category | Use a name/slug from `/taxonomies` |
| `422` `releases` | Unknown platform/language | Use values from `/taxonomies` |
| `422` `cover_url` / `media` / `description` | Bad or unreachable image | Fix URL; ensure public access and image MIME |
| `429` | Rate limited | Wait; max 60/min |

## Out of scope (do not attempt)

- Update / delete games via API
- Multipart binary image upload
- Auto-creating categories, platforms, or languages
- Unauthenticated public JSON catalog

## Agent checklist

- [ ] Have admin Bearer token
- [ ] Called `/taxonomies` and mapped names
- [ ] Cover image URL is public and ≤ 20MB
- [ ] Screenshot URLs (if any) are public images
- [ ] Detail/release `<img src>` (if any) are public image URLs (not data URIs)
- [ ] Each release has platforms, languages, and ≥1 download link
- [ ] POST `/games` → expect `201` and `data.url`
- [ ] Optionally GET `/games/{id}` to verify counts
