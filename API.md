# WiserLeap API (Laravel 13)

Base URL: `https://api.wiserleap.com/api/v1` (local: `http://localhost:8000/api/v1`)

- JSON everywhere. Field names are **camelCase**, same as the Next.js admin.
- Errors: `{ "message": "...", "errors": { "field": ["..."] } }` with status 422 (validation),
  401 (not logged in), 403, 404, 429 (too many requests).
- Lists are paginated: `{ data: [...], links: {...}, meta: { current_page, last_page, per_page, total } }`.

---

## Authentication (Laravel Sanctum, cookie based)

The admin panel logs in with an **httpOnly session cookie**. No token is stored in JavaScript.

1. `GET /sanctum/csrf-cookie` (note: **not** under `/api`). Sets the `XSRF-TOKEN` cookie.
2. Send every request with `credentials: "include"`, and for POST/PUT/PATCH/DELETE add the header
   `X-XSRF-TOKEN: <decoded value of the XSRF-TOKEN cookie>`.
3. Also send `Accept: application/json`.

| Method | Endpoint | Body | Notes |
|---|---|---|---|
| GET | `/auth/registration-status` | – | `{ open: true/false }` (show or hide the sign-up page) |
| POST | `/auth/register` | `name, email, password, password_confirmation` | Only the **first** account, or when `ADMIN_REGISTRATION_ENABLED=true`. 201 + user |
| POST | `/auth/login` | `email, password, remember` | 5 tries/min per email+IP |
| GET | `/auth/user` | – | Current admin, or 401 |
| POST | `/auth/logout` | – | |
| POST | `/auth/forgot-password` | `email` | Emails a link to `FRONTEND_URL/reset-password?token=…&email=…`. Always the same answer |
| POST | `/auth/reset-password` | `token, email, password, password_confirmation` | |

User: `{ id, name, email }`

---

## Admin (login required)

### Dashboard
`GET /admin/dashboard`
```json
{
  "stats":   { "total": 12, "published": 8, "drafts": 4, "views": 11000 },
  "inquiries": { "new": 2, "total": 15 },
  "monthly": [ { "month": "2026-06", "label": "Jun", "count": 2, "views": 3100 } ],
  "recent":  [ Blog, ... 5 ],
  "activity":[ { "id": 1, "type": "published", "title": "…", "by": "Ahsan", "at": "ISO date" } ]
}
```
`type` is one of: `created`, `updated`, `published`, `unpublished`, `deleted`.

### Blogs

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/admin/blogs` | Query: `status` (draft/published), `categoryId` (id or `none`), `authorId` (id or `none`), `search`, `sort` (updated/newest/oldest/title/views), `perPage` (1–50, default 8), `page`. `meta.counts = { all, published, draft }` for the tabs. List items have no `content` |
| POST | `/admin/blogs` | **multipart/form-data**, fields below. 201 |
| GET | `/admin/blogs/{id}` | With `content` (for the editor) |
| POST | `/admin/blogs/{id}` + `_method=PUT` | Update (multipart, so the image can be sent) |
| PATCH | `/admin/blogs/{id}/status` | `{ "status": "published" | "draft" }` (publish/unpublish from the list) |
| DELETE | `/admin/blogs/{id}` | Also deletes the image file |

Blog fields (create/update):

| Field | Rules |
|---|---|
| `title` | required, 3–120 |
| `slug` | optional, lowercase-and-hyphens, max 140. Empty = made from the title. Duplicates get `-2`, `-3`… |
| `excerpt` | max 200 |
| `content` | HTML from the editor. Cleaned on the server (scripts, styles, iframes, `javascript:` links removed) |
| `image` | jpg/png/webp, max 2 MB (`BLOG_IMAGE_MAX_KB`). SVG is rejected |
| `removeImage` | `1` to remove the current image |
| `imageAlt` | max 150 |
| `categoryId` | **required**, existing category id |
| `authorId` | existing author id |
| `tags[]` | up to 10, each max 30, duplicates removed |
| `seoTitle` | max 60 |
| `metaDescription` | max 160 |
| `status` | `draft` or `published` |

**Publishing** (`status=published`) also needs: `excerpt`, readable `content`, an image (new or existing), `authorId`.
Missing ones come back as 422 errors on those fields. Drafts only need `title` + `categoryId`.
The first publish date is kept when a post is unpublished and published again.

Blog (admin):
```json
{
  "id": 3, "title": "…", "slug": "…", "excerpt": "…", "content": "<p>…</p>",
  "image": "https://api…/storage/blogs/2026/11/uuid.jpg", "imageAlt": "…",
  "categoryId": 1, "category": { "id": 1, "name": "MVP Development", "slug": "mvp-development" },
  "authorId": 1, "author": { "id": 1, "name": "Muhammad Ahsan", "image": null },
  "tags": ["MVP"], "seoTitle": "…", "metaDescription": "…",
  "status": "published", "views": 120,
  "publishedAt": "ISO", "createdAt": "ISO", "updatedAt": "ISO"
}
```

### Categories

| Method | Endpoint | Body | Notes |
|---|---|---|---|
| GET | `/admin/categories` | – | `{ id, name, slug, blogsCount }`, A–Z |
| POST | `/admin/categories` | `{ name }` | 2–40 chars, unique (case-insensitive). 201 |
| PUT | `/admin/categories/{id}` | `{ name }` | Blogs stay linked (by id) |
| DELETE | `/admin/categories/{id}` | – | Its blogs become uncategorised. Returns `affectedBlogs` |

### Authors

| Method | Endpoint | Body | Notes |
|---|---|---|---|
| GET | `/admin/authors` | – | `{ id, name, bio, image, blogsCount }`, A–Z |
| POST | `/admin/authors` | multipart: `name`, `bio`, `image` | name 2–60 unique, bio max 200, photo jpg/png/webp max 800 KB |
| POST | `/admin/authors/{id}` + `_method=PUT` | multipart, + `removeImage=1` to delete the photo | |
| DELETE | `/admin/authors/{id}` | – | Their blogs stay, without an author. Returns `affectedBlogs` |

### Inquiries (website contact form)

| Method | Endpoint | Body | Notes |
|---|---|---|---|
| GET | `/admin/inquiries` | – | Query: `status` (`new`, `read`, `replied`, `archived`), `search` (name, email, subject, message), `perPage` (1–50, default 15), `page`. Newest first. `meta.counts` = `{ all, new, read, replied, archived }` |
| GET | `/admin/inquiries/{id}` | – | Full message + `replies`. Opening a `new` inquiry marks it `read` |
| PATCH | `/admin/inquiries/{id}/status` | `{ status }` | `new` clears `readAt` (mark unread) |
| POST | `/admin/inquiries/{id}/reply` | `{ subject?, message }` | Emails the visitor (plain text, their message quoted), then records the reply and sets `replied`. Mail failure → 502, nothing recorded. 60/hour per admin |
| DELETE | `/admin/inquiries/{id}` | – | Also removes its replies |

Inquiry (list): `{ id, name, email, subject, budget, status, preview, repliesCount, notified, createdAt, readAt, repliedAt }`.
Detail adds `message`, `ipAddress`, `userAgent`, `replies: [{ id, subject, body, by, createdAt }]` (no `preview`).
`notified: false` = the email to the inbox failed (the inquiry is still saved).

---

## Contact form (server to server)

`POST /contact` — called by the Next.js server only, never the browser.

Headers: `X-Site-Key: <CONTACT_SITE_KEY>` (required; wrong/missing → 403, not configured → 503),
`X-Visitor-Ip` and `X-Visitor-Agent` (trusted only with a valid key).

Body: `{ name (2–100), email (max 200), subject? (max 150), budget? (max 100), message (10–5000) }` → `201 { message }`.
Saves the inquiry, then emails `CONTACT_NOTIFY_EMAIL` with reply-to = the visitor. The same email + message
within 10 minutes is accepted but not stored twice. Limits: 5 per 10 min per visitor IP, 5/hour per sender email, 300/hour overall.

---

## Public website (no login, published posts only, cached)

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/blogs` | Query: `category` (slug), `search`, `perPage` (1–24, default 9), `page`. Newest first |
| GET | `/blogs/{slug}` | `{ data: Post (with content), related: [3 posts] }`. Drafts → 404 |
| POST | `/blogs/{slug}/view` | Counts a view (20/min per visitor) |
| GET | `/categories` | Categories that have published posts: `{ name, slug, blogsCount }` |

Post (public):
```json
{
  "title": "…", "slug": "…", "excerpt": "…", "content": "<p>…</p>",
  "image": "https://…", "imageAlt": "…",
  "category": { "name": "…", "slug": "…" },
  "author": { "name": "…", "bio": "…", "image": null },
  "tags": [], "seoTitle": "…", "metaDescription": "…",
  "readingMinutes": 3, "publishedAt": "ISO", "updatedAt": "ISO"
}
```
(`content`, `seoTitle` and `metaDescription` only on the single post.)

---

## Example (Next.js, browser side)

```js
const API = process.env.NEXT_PUBLIC_API_URL; // e.g. https://api.wiserleap.com

function xsrf() {
  const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : "";
}

export async function api(path, { method = "GET", body } = {}) {
  if (method !== "GET" && !xsrf()) {
    await fetch(`${API}/sanctum/csrf-cookie`, { credentials: "include" });
  }
  const res = await fetch(`${API}/api/v1${path}`, {
    method,
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(method !== "GET" ? { "X-XSRF-TOKEN": xsrf() } : {}),
      ...(body && !(body instanceof FormData) ? { "Content-Type": "application/json" } : {}),
    },
    body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
  });
  const data = res.status === 204 ? null : await res.json();
  if (!res.ok) throw Object.assign(new Error(data?.message || "Request failed"), { status: res.status, errors: data?.errors });
  return data;
}

// await api("/auth/login", { method: "POST", body: { email, password, remember: true } });
// const form = new FormData(); form.append("title", "…"); form.append("status", "draft"); form.append("categoryId", 1);
// await api("/admin/blogs", { method: "POST", body: form });
// Update: form.append("_method", "PUT"); await api(`/admin/blogs/${id}`, { method: "POST", body: form });
```
