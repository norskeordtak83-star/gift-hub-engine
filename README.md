# gift-hub-engine

WordPress plugin for generating evergreen international gift idea pages from a local dataset.

## Features
- Registers `gift_page` custom post type.
- Registers taxonomies: `audience`, `occasion`, `budget`, `interest`.
- Imports and updates pages from `data/categories.json`.
- Renders a custom `gift_page` template with hero, intro, top picks, jump links, FAQ, FAQ schema, and ad placeholders.
- Adds internal linking for SEO:
  - **Related Gift Ideas** shows up to 8 related `gift_page` links.
  - Related ranking prioritizes shared terms in this order: `interest` → `occasion` → `audience` → `budget`.
  - Uses per-post transients for related links to reduce repeated query cost.
  - **Explore more** links to taxonomy term archives for terms on the current page, with filtered archive fallback links.
- Adds a Settings page at **Settings → Gift Hub Engine** for:
  - Default Amazon marketplace domain (for non-PAAPI fallback URLs)
  - Default associate/tracking tag
  - PA-API v5 toggle and credentials (`Access Key`, `Secret Key`, `Partner Tag`, marketplace: `US` / `UK` / `DE`)
- Exposes WP-CLI sync command:

```bash
wp gift-hub sync
```

## Amazon PA-API v5 enrichment
When enabled, Top Picks cards can be enriched by ASIN with:
- canonical Amazon product title
- primary product image URL (`Images.Primary.Large.URL`)
- canonical detail page URL (`/dp/{ASIN}/` style, no price data)

### Setup
1. Open **Settings → Gift Hub Engine**.
2. Enable **PA-API enrichment**.
3. Select marketplace (`US`, `UK`, `DE`).
4. Enter PA-API credentials (`Access Key`, `Secret Key`, `Partner Tag`).
5. Save settings.

### Cache behavior
- Cache key: `{marketplace}:{ASIN}`.
- Storage: WordPress option `ghe_paapi_cache`.
- TTL: 7 days for successful responses.
- Error backoff: exponential retry delay (up to 24h) to reduce throttling and repeated failures.
- The plugin stores and uses image URLs returned by PA-API only; it does not download or host product images.

### Rendering behavior
- If PA-API cache has data, cards use canonical title/image/URL.
- If data is missing or credentials are invalid, cards gracefully fall back to existing `label` / `notes` and a neutral placeholder image.
- The UI never renders Amazon price or stock claims.

## WP-CLI sync + optional PA-API cache warm
```bash
wp gift-hub sync --warm-cache --batch-size=5 --sleep-ms=500
```

Flags:
- `--warm-cache`: after sync, warms PA-API cache for ASINs found in synced pages.
- `--batch-size`: requests per batch (default `5`, clamped `1..10`).
- `--sleep-ms`: sleep between batches in milliseconds (default `500`).

If PA-API is disabled, warm-cache completes without requests.

## Top Picks dataset format
Each page can include optional `top_picks` items:

```json
{
  "slug": "gifts-for-frequent-travelers",
  "title": "International Gift Ideas for Frequent Travelers",
  "top_picks": [
    {
      "asin": "B0XXXXXXX",
      "label": "Packing Cubes Set",
      "url": "https://www.amazon.com/dp/B0XXXXXXX?tag=YOURTAG-20",
      "notes": "Short 1–2 sentence evergreen reason to recommend (no pricing).",
      "image_url": "https://your-cdn.example/images/packing-cubes.jpg"
    },
    {
      "asin": "B0YYYYYYY",
      "label": "Universal Adapter",
      "notes": "Evergreen utility description."
    }
  ]
}
```

Notes:
- `top_picks` is optional.
- If `top_picks` is missing/empty, the plugin keeps rendering existing placeholder product slots.
- If a `top_picks` item omits `url`, a default URL is built from plugin settings:
  - `https://{domain}/dp/{asin}/?tag={tag}` (tag omitted when empty)
- `image_url` is optional. If omitted (or invalid), a neutral placeholder icon is shown.

## Shortcodes
### `[gift_hub_product_slot]`
- Supports `index` attribute (1-based), e.g. `[gift_hub_product_slot index="1"]`.
- Renders product card number `index` from the current page's `top_picks` data when available.
- Falls back to the existing placeholder card when no `top_picks` data is available.

### `[gift_hub_list taxonomy="interest" term="Travel" limit="24"]`
Renders a simple list of `gift_page` links filtered by taxonomy term.

Attributes:
- `taxonomy` (required for useful output): one of `audience`, `occasion`, `budget`, `interest`
- `term` (required): term name or slug
- `limit` (optional): max results, default `24`, capped at `100`

## Dataset format
Edit `data/categories.json` with a `pages` array. Each page supports:
- `slug`
- `title`
- `intro`
- `section_headings` (array)
- `faq` (array of `{question, answer}`)
- `top_picks_count`
- `top_picks` (optional array as described above)
- taxonomy arrays (`audience`, `occasion`, `budget`, `interest`)

The content avoids hard-coded prices in prose to keep pages evergreen.

## Compliance note
- Do **not** scrape, download, or self-host Amazon product images manually.
- Use official Amazon sources (PA-API / Associates-approved methods) and comply with Amazon Associates Program policies.
- Avoid rendering price, availability, or “in stock” assertions from stale content.

## Troubleshooting
- **No enriched image/title appears:** confirm PA-API is enabled, credentials match selected marketplace, and ASIN is valid.
- **Repeated failures / throttling:** lower `--batch-size`, increase `--sleep-ms`, then retry warm-cache.
- **Still seeing fallback cards:** inspect `ghe_paapi_cache` option entries and wait for backoff to expire after credential fixes.
- **Site error concerns:** invalid or missing credentials should not break rendering; cards fall back automatically.
