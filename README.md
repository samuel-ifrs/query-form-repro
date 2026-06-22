# QUERY method: `<form>` vs `fetch()` — minimal repro

A zero-dependency repro for [whatwg/html#12594](https://github.com/whatwg/html/issues/12594)
("Support QUERY method in form submissions").

**▶ Live demo (no install):** https://samuel-ifrs.github.io/query-form-repro/

It shows that, in current browsers:

- **`<form method="query">` falls back to `GET`** — the request body is silently dropped,
  because HTML only defines `get`/`post` as form methods and an unknown value uses the
  invalid-value default (GET).
- **`fetch(url, { method: 'QUERY', body })` does send `QUERY`** on the wire — the Fetch
  Standard does not restrict arbitrary method names (only `CONNECT`/`TRACE`/`TRACK` are
  forbidden).

So the QUERY method ([RFC 10008](https://www.rfc-editor.org/info/rfc10008/)) is reachable
from script today, but **not** from declarative HTML forms — which is the gap #12594 is about.

## Run

No dependencies. Requires Node.js.

```bash
node server.js
# then open http://localhost:8790
```

## What to do

1. Click **Submit (query)** — the result page shows the method that actually reached the
   server. Expected: **GET** (fell back, body dropped).
2. Compare with **Submit (post)** and **Submit (get)** — these match.
3. Click **Send QUERY via fetch()** — the server log shows `actual=QUERY via=fetch`.

Watch the server terminal: each submission is logged with an `intended` vs `actual` column.

```
>> /submit  intended=QUERY  actual=GET    via=form  data={"name":"Maria","filter":"electronics"}
>> /submit  intended=POST   actual=POST   via=form  data={"name":"Maria","filter":"electronics"}
>> /submit  intended=GET    actual=GET    via=form  data={"name":"Maria","filter":"electronics"}
>> /submit  intended=QUERY  actual=QUERY  via=fetch data={"name":"Maria","filter":"electronics"}
```

## `echo.php` — a QUERY-aware endpoint for the live demo

GitHub Pages is static, and **no public echo service safelists QUERY in its CORS preflight**
(checked httpbingo.org, beeceptor, hoppscotch — none list QUERY in `Access-Control-Allow-Methods`),
so a cross-origin QUERY `fetch()` from the Pages demo gets blocked. That CORS gap is itself a data
point for this issue's open "is QUERY CORS-safelisted?" question.

`echo.php` solves it: host it on any PHP server and it

- answers the CORS preflight **allowing QUERY**, so cross-origin `fetch(url, {method:'QUERY'})` works;
- renders a nice HTML result page on form navigation, and returns JSON to `fetch()`
  (it switches on `Sec-Fetch-Mode`);
- compares the `__intended` method against the one that actually arrived.

Then edit the `ENDPOINT` constant in [`docs/index.html`](docs/index.html) to point at your hosted
`echo.php`.

## Why QUERY is a good fit for forms

Because QUERY is **safe and idempotent**, a `<form method="query">` submission would —
unlike POST — be safely bookmarkable, re-runnable on back/forward navigation, and cacheable,
while still carrying a request body. That fills the gap between:

- **GET** with a long query string (URL length limits, awkward for nested/structured criteria), and
- **POST** used for a read (semantically wrong; breaks caching, the back button, and sharing).

Typical use cases: faceted search, report builders, map bounding-box queries, and structured-query
endpoints driven by a plain HTML form with no JavaScript.
