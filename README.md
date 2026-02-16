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
  - Default Amazon marketplace domain (for example `amazon.com`, `amazon.co.uk`, `amazon.de`)
  - Default associate/tracking tag
- Exposes WP-CLI sync command:

```bash
wp gift-hub sync
```

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
- Amazon-hosted image URLs are intentionally blocked for this step.

## Shortcodes
### `[gift_hub_product_slot]`
- Supports `index` attribute (1-based), e.g. `[gift_hub_product_slot index="1"]`.
- Renders product card number `index` from the current page's `top_picks` data when available.
- Falls back to the existing placeholder card when no `top_picks` data is available.
## Shortcodes
### `[gift_hub_product_slot]`
Existing product-slot placeholder shortcode used in Top Picks.

### `[gift_hub_list taxonomy="interest" term="Travel" limit="24"]`
Renders a simple list of `gift_page` links filtered by taxonomy term.

Attributes:
- `taxonomy` (required for useful output): one of `audience`, `occasion`, `budget`, `interest`
- `term` (required): term name or slug
- `limit` (optional): max results, default `24`, capped at `100`

Example:

```text
[gift_hub_list taxonomy="interest" term="Travel" limit="24"]
```

Use this shortcode in hub pages or internal landing pages to build additional internal linking paths.

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
Do **not** copy, scrape, or self-host Amazon product images manually. If you later need official Amazon images, use approved methods such as Product Advertising API or SiteStripe according to Amazon Associates policy.
