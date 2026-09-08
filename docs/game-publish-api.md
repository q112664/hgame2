# Game Publish API — agent spec

Feed this entire document to an agent as the source of truth for creating and editing games on this site.

Public “resources” are games. There is no separate resource type.

No multipart uploads. Send publicly reachable HTTP(S) image URLs; the server downloads them.

## Base URL

| Environment | Base |
|-------------|------|
| Local (Herd) | `http://hgame.test` |
| API prefix | `/api/v1` |

Full endpoint root: `{origin}/api/v1`

## Authentication

Every request:

```http
Authorization: Bearer {TOKEN}
Accept: application/json
Content-Type: application/json
```

- Token must belong to a user with `is_admin = true`.
- Mint a token in **Admin → Settings → API tokens**, or: `php artisan game:token {admin-email-or-id} --name=game-publish`
- Rate limit: **60 requests / minute** per token.
- Missing/invalid token → `401`. Non-admin → `403`.

## How to choose an endpoint

| Goal | Call |
|------|------|
| Create a game in one shot | `POST /games` with the full body |
| Change title, cover, status, synopsis, source, category | `PATCH /games/{slug}` with only those keys |
| Replace **all** tags / screenshots / detail versions / releases | `PATCH /games/{slug}` with that array (omit keys you must not touch) |
| Add / replace / delete **one** screenshot | nested `/screenshots` |
| Add tags without dropping existing ones / detach one tag | nested `/tags` |
| Add or edit **one** language’s details | `PUT` or `PATCH /detail-versions/{language}` |
| Delete one language’s details | `DELETE /detail-versions/{language}` |
| Add / edit / delete **one** download package | nested `/releases` |
| Inspect current ids | `GET /games/{slug}` |

**Never send a collection on `PATCH /games/{slug}` unless you intend to replace that whole collection.** Nested routes exist so you can edit one item.

`id` on the game object is the **slug**. Nested routes do **not** all take integer ids:

- `/screenshots/{id}` and `/releases/{id}` — integer ids from `screenshot_items[]` / `releases[]`
- `/detail-versions/{language}` — taxonomy language **code or name** (`en`, `ja`, `Chinese`), not `detail_versions[].id`
- Download links have no nested route. Change them with `PATCH /releases/{id}` and a full `download_links` array for that package. `download_link_items[].id` is display-only.

## Recommended workflows

**New game**

1. `GET /taxonomies`
2. Host cover, screenshots, and any HTML `<img>` files on public HTTP URLs (JPEG/PNG/WebP/GIF, ≤ 20MB).
3. `POST /games` with everything you know.
4. Open `data.url` for the public page.

**Edit one screenshot**

1. `GET /games/{slug}` → read `screenshot_items[]`.
2. `POST /games/{slug}/screenshots` to append, or `PATCH /games/{slug}/screenshots/{id}` to change URL/order, or `DELETE` that id.

**Edit one download package**

1. `GET /games/{slug}` → read `releases[].id`.
2. `POST /games/{slug}/releases` to add a package.
3. `PATCH /games/{slug}/releases/{id}` with only the fields that change. If you change links, send the **full** `download_links` array for **that package only**.
4. Pass `"touch_downloads": true` only when this change should show as a download update on the site (Updated chip, favorite notifications, IndexNow if enabled).

## Game object (detail)

Returned by create, show, update, and every nested write.

```json
{
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
  "description": "<p>English synopsis HTML.</p>",
  "detail_versions": [
    {
      "id": 10,
      "language": { "name": "English", "code": "en" },
      "description": "<p>English details.</p>",
      "sort_order": 0
    }
  ],
  "cover_url": "http://hgame.test/storage/games/covers/....png",
  "published_at": "2026-07-29T12:00:00+00:00",
  "url": "http://hgame.test/games/senren-banka",
  "screenshots": ["http://hgame.test/storage/games/screenshots/....png"],
  "screenshot_items": [
    { "id": 5, "url": "http://hgame.test/storage/games/screenshots/....png", "sort_order": 0 }
  ],
  "releases": [
    {
      "id": 12,
      "title": "Windows Chinese package",
      "platforms": ["Windows"],
      "languages": ["Chinese"],
      "version": "1.0",
      "file_size": "5.4 GB",
      "description": null,
      "is_active": true,
      "published_at": "2026-07-29T12:00:00+00:00",
      "contributor": { "name": "Uploader", "email": "uploader@example.com" },
      "download_links": ["https://example.com/game.zip"],
      "download_link_items": [{ "id": 44, "url": "https://example.com/game.zip" }]
    }
  ],
  "screenshots_count": 1,
  "releases_count": 1
}
```

Notes:

- Game `id` is the slug. Use it in every nested path.
- `screenshots` stays an array of URL strings (compat). Use `screenshot_items` when you need ids.
- `download_links` stays an array of URL strings. Use `download_link_items` when you need link ids. Nested APIs do **not** PATCH a single link by id; send the package’s full `download_links` list.
- `description` is the English synopsis on the game itself. Other languages live in `detail_versions`.
- Public page is visible when `status=published` and `published_at <= now`.
- A release is shown on the site when `is_active=true` and it has at least one download link.

---

## GET `/api/v1/taxonomies`

Allowed categories, platforms, languages, and known sources. **Do not invent** category / platform / language names.

**Response `200`**

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

Resolution when saving:

| Field | Match |
|-------|--------|
| `category` | category `name` or `slug` (name case-insensitive) |
| `releases[].platforms[]` / nested `platforms[]` | platform `name` or `slug` |
| `releases[].languages[]` / nested `languages[]` / `{language}` path | language `name` or `code` |
| `source_name` | Prefer `sources[].name` (`DLsite` or `Steam`) for the storefront icon |
| `tags[]` | created if missing |

---

## GET `/api/v1/games`

Admin catalog (all statuses).

| Query | Notes |
|-------|--------|
| `q` | Search title, subtitle, developer, category, tags, platforms, languages |
| `status` | `draft` \| `published` \| `unlisted` |
| `category` | Category name or slug |
| `page` | Default 1 |
| `per_page` | 1–100, default 20 |

List items are summaries (`id`, `title`, `subtitle`, `status`, `category`, `developer`, `url`, `cover_url`, `published_at`, `screenshots_count`, `releases_count`). They do **not** include screenshot/release ids. Call show for those.

---

## POST `/api/v1/games`

Create a game. Success `201` with the detail object. Validation / media errors `422`.

### Minimal body

```json
{
  "title": "Senren Banka",
  "cover_url": "https://cdn.example.com/cover.png"
}
```

### Full body

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
  "description": "<p>Short HTML synopsis.</p><p><img src=\"https://cdn.example.com/detail-1.png\" alt=\"Scene\"></p>",
  "detail_versions": [
    { "language": "en", "description": "<p>English details.</p>" },
    { "language": "ja", "description": "<p>日本語</p>" }
  ],
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
      "description": "<p>Optional notes.</p>",
      "download_links": ["https://example.com/game.zip"]
    }
  ]
}
```

### Create fields

| Field | Required | Notes |
|-------|----------|--------|
| `title` | yes | max 255 |
| `cover_url` | yes | Absolute URL; server downloads the image |
| `subtitle` | no | max 255 |
| `slug` | no | `alpha_dash`, unique; auto from title if omitted |
| `category` | no | Must already exist |
| `tags` | no | String array; created if missing |
| `developer` | no | |
| `source_name` | no | Prefer `DLsite` or `Steam` |
| `source_id` | no | e.g. `RJ01123456` or Steam app id |
| `source_url` | no | Absolute product URL |
| `source_icon_url` | no | Upserts the reusable source icon library when `source_name` is set |
| `source_host_hint` | no | Same as above for host hint |
| `release_date` | no | `YYYY-MM-DD` |
| `description` | no | HTML; remote `<img src>` ingested |
| `detail_versions` | no | Max 20. Each needs `language`. Duplicate languages → `422` |
| `status` | no | `draft` \| `published` \| `unlisted` (default **`published`**) |
| `published_at` | no | Default `now()` when status is not `draft` |
| `screenshots` | no | Image URLs, max 50 |
| `releases` | no | Array of packages |

**Each release on create / bulk replace**

| Field | Required | Notes |
|-------|----------|--------|
| `title` | yes | max 255 |
| `platforms` | yes | ≥1 existing platform name/slug |
| `languages` | yes | ≥1 existing language name/code |
| `download_links` | yes | ≥1 absolute URLs |
| `version` | no | |
| `file_size` | no | Display string, e.g. `"5.4 GB"` |
| `description` | no | HTML |
| `is_active` | no | default `true` |
| `published_at` | no | default `now()` |
| `contributor` | no | Existing user **email** |

Download link labels are derived from the URL host.

First publish does **not** set `downloads_updated_at`. The public “Updated” chip stays hidden until a later explicit `touch_downloads: true`.

---

## GET `/api/v1/games/{slug}`

Full detail. `200` / `404`.

---

## PUT / PATCH `/api/v1/games/{slug}`

Partial update of **top-level fields**. Only keys present in the JSON are changed.

If you send a collection key, that collection is **replaced in full**:

| Body | Effect |
|------|--------|
| omit `tags` | tags unchanged |
| `"tags": ["Romance"]` | tags become exactly that list (empty array clears) |
| omit `screenshots` | screenshots unchanged |
| `"screenshots": ["https://..."]` | screenshots become exactly that list |
| omit `detail_versions` | localized details unchanged |
| `"detail_versions": [...]` | localized details become exactly that list |
| omit `releases` | download packages unchanged |
| `"releases": [...]` | **delete all packages and recreate** from the array (new integer ids). Empty array clears. |

Use nested routes instead of sending `releases` / `screenshots` when you only want to change one item.

Other examples:

- Metadata: `{ "title": "New title", "status": "draft" }`
- Cover: `{ "cover_url": "https://cdn.example.com/new-cover.png" }`
- Mark a download update without changing files: `{ "touch_downloads": true }`

`touch_downloads` (boolean, optional): when true, sets `downloads_updated_at` now (Updated chip, favorite notifications, IndexNow if enabled in site settings). Does nothing on first create.

Success `200` with full detail. `422` / `404`.

Field rules match create, except every field is optional (`sometimes`). `cover_url` if sent must be a valid URL. `slug` if sent must stay unique.

---

## DELETE `/api/v1/games/{slug}`

Permanently deletes the game, related rows, and unreferenced media.

```json
{ "data": { "id": "senren-banka", "deleted": true } }
```

---

## Nested writes

All nested writes return the **full game detail** (same `data` shape as show). `POST` is `201`; `PUT`/`PATCH`/`DELETE` are `200`.

Child `{id}` values that belong to another game → `404`.

### Screenshots

Max **50** per game.

**POST `/games/{slug}/screenshots`** — append one image.

```json
{ "url": "https://cdn.example.com/shot-3.png" }
```

**PATCH `/games/{slug}/screenshots/{id}`** — change that row. Send `url` and/or `sort_order`. The integer `id` is kept when the URL changes.

```json
{ "url": "https://cdn.example.com/shot-3b.png" }
```

```json
{ "sort_order": 0 }
```

`sort_order` is a 0-based position in the gallery (clamped).

**DELETE `/games/{slug}/screenshots/{id}`** — remove that image only.

### Tags

Tags are strings. Identity is the name (or slug). Detach does **not** delete the global tag.

**POST `/games/{slug}/tags`** — attach; existing tags stay.

```json
{ "tags": ["Drama", "Slice of Life"] }
```

**DELETE `/games/{slug}/tags/{tag}`** — detach one. `{tag}` is the slug (`slice-of-life`) or the name (URL-encode spaces: `Slice%20of%20Life`). Unknown tag → `404`. Detaching a tag the game does not have still returns `200`.

### Localized details

`{language}` is a language **code or name** from `/taxonomies` (`en`, `ja`, `Chinese`, …), matched case-insensitively. Unknown language → `422`. English synopsis on the game (`description`) is separate and is not this resource. Do not put `detail_versions[].id` in this path.

**PUT or PATCH `/games/{slug}/detail-versions/{language}`** — create or overwrite that language only. Other languages stay. `{language}` is matched case-insensitively (`JA` and `ja` are the same).

```json
{ "description": "<p>日本語の詳細</p>", "sort_order": 1 }
```

`description` may be null. `sort_order` optional (append on create, keep on update). Max **20** languages per game.

**DELETE `/games/{slug}/detail-versions/{language}`** — remove that language only. Missing translation → `404`.

### Releases (download packages)

**POST `/games/{slug}/releases`** — add one package. Existing packages stay. Same fields as a create-time release object, plus optional `touch_downloads`.

```json
{
  "title": "Mac English package",
  "platforms": ["Mac"],
  "languages": ["English"],
  "version": "1.0",
  "file_size": "5.4 GB",
  "download_links": ["https://example.com/mac.zip"],
  "touch_downloads": true
}
```

**PATCH `/games/{slug}/releases/{id}`** — update that package in place. Send only keys that change. Omitted keys (including `published_at` and `contributor`) are kept. The integer `id` is kept.

If `download_links` is sent, it **replaces all links on this package only**. Other packages are untouched.

```json
{
  "version": "1.1",
  "download_links": ["https://example.com/game-v1-1.zip"],
  "touch_downloads": true
}
```

Empty body → `422`. At least one of: `title`, `platforms`, `languages`, `version`, `file_size`, `description`, `is_active`, `published_at`, `contributor`, `download_links`, `touch_downloads`.

**DELETE `/games/{slug}/releases/{id}`** — delete that package only. Does **not** set `touch_downloads`.

There is no nested download-link route. Change links by PATCHing the package.

---

## Images

- No `multipart/form-data` in v1.
- `cover_url` and screenshot `url` values must be http(s) URLs the server can fetch.
- HTML in `description` / `detail_versions` / `releases[].description`: remote `<img src="https://...">` are downloaded into `games/content` and rewritten to local `/storage/...` paths.
- Already-local `/storage/games/...` (or site URLs pointing at them) are left unchanged.
- `data:` URIs are rejected.
- Max remote images per HTML field: **30**.
- Types: `image/jpeg`, `image/png`, `image/webp`, `image/gif`.
- Max size: **20MB**.
- Failed download → `422` on `media`, `description`, `url`, or `releases.N.description`.

---

## Updated signal and IndexNow

`downloads_updated_at` drives the public Updated chip, favorite “downloads updated” notifications, catalog `sort=updated`, sitemap lastmod, and (if enabled in Admin → Site settings → SEO) IndexNow.

| Action | Bumps Updated? |
|--------|----------------|
| `POST /games` (first publish) | no |
| Nested or bulk edit without `touch_downloads` | no |
| `"touch_downloads": true` on `PATCH /games/{slug}` or nested release create/update | yes |
| Delete a package / screenshot / tag | no |

Do not set `touch_downloads` for typo fixes, title edits, or adding a screenshot. Set it when the downloadable files actually changed.

IndexNow is configured in the admin UI (enable + generate key). Agents do not call IndexNow themselves. Google does not use IndexNow.

---

## Source library (optional)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/sources` | List known sources |
| POST | `/sources` | Upsert `{ name, slug?, host_hint?, icon_url?, sort_order? }` |
| DELETE | `/sources/{slug}` | Remove a library source |

Games still store `source_name` / `source_id` / `source_url` on the game itself.

---

## Errors

| Status | Meaning | What to do |
|--------|---------|------------|
| `401` | Bad/missing token | Re-mint `php artisan game:token ...` |
| `403` | User not admin | Use an admin account |
| `404` | Unknown slug, nested id, or tag | `GET` the game; check `screenshot_items` / `releases` |
| `422` `slug` | Slug taken | Change or omit `slug` |
| `422` `category` | Unknown category | Use `/taxonomies` |
| `422` `releases` / `platforms` / `languages` | Unknown platform or language | Use `/taxonomies` |
| `422` `language` | Unknown language on detail-versions | Use `/taxonomies` |
| `422` `url` / `cover_url` / `media` / `description` | Bad image | Public http(s), allowed MIME, ≤ 20MB |
| `429` | Rate limited | Wait; max 60/min |

---

## cURL examples

```bash
TOKEN="your-token-here"
BASE="http://hgame.test/api/v1"

# Taxonomies
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/taxonomies"

# Create
curl -sS -X POST "$BASE/games" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Senren Banka",
    "category": "Visual Novel",
    "tags": ["Romance"],
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

# Show (read integer ids)
curl -sS -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/games/senren-banka"

# Append a screenshot
curl -sS -X POST "$BASE/games/senren-banka/screenshots" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://cdn.example.com/shot-2.png"}'

# Replace one screenshot by id
curl -sS -X PATCH "$BASE/games/senren-banka/screenshots/5" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://cdn.example.com/shot-2b.png"}'

# Attach tags
curl -sS -X POST "$BASE/games/senren-banka/tags" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"tags":["Drama"]}'

# Detach one tag
curl -sS -X DELETE "$BASE/games/senren-banka/tags/romance" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# Upsert Japanese details only (PUT or PATCH; language code is case-insensitive)
curl -sS -X PUT "$BASE/games/senren-banka/detail-versions/ja" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"description":"<p>日本語</p>"}'

# Patch one download package and mark it as a file update
curl -sS -X PATCH "$BASE/games/senren-banka/releases/12" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"version":"1.1","download_links":["https://example.com/game-v1-1.zip"],"touch_downloads":true}'
```

---

## Out of scope (do not attempt)

- Multipart binary image upload
- Auto-creating categories, platforms, or languages
- Merging / renaming / deleting global tags from this API
- Unauthenticated public JSON catalog
- Docs / non-game content via this API
- Calling IndexNow yourself

## Agent checklist

- [ ] Admin Bearer token
- [ ] Called `/taxonomies` and mapped names
- [ ] Cover / screenshot / HTML image URLs are public and ≤ 20MB
- [ ] For edits: `GET` the game; use integer ids for `/screenshots/{id}` and `/releases/{id}`; use language code/name for `/detail-versions/{language}`
- [ ] Do not send `screenshots` / `releases` / `tags` / `detail_versions` on `PATCH /games/{slug}` unless replacing the whole list
- [ ] Each new release has platforms, languages, and ≥1 download link
- [ ] `touch_downloads: true` only when downloadable files actually changed
- [ ] `POST /games` or nested write → expect `data.url`; screenshot and release rows keep stable integer `id`s
