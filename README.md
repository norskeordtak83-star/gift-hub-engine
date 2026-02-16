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
- Exposes WP-CLI sync command:

```bash
wp gift-hub sync
```

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
- taxonomy arrays (`audience`, `occasion`, `budget`, `interest`)

The content avoids hard-coded prices in prose to keep pages evergreen.
