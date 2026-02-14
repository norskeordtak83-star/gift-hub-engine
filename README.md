# gift-hub-engine

WordPress plugin for generating evergreen international gift idea pages from a local dataset.

## Features
- Registers `gift_page` custom post type.
- Registers taxonomies: `audience`, `occasion`, `budget`, `interest`.
- Imports and updates pages from `data/categories.json`.
- Renders a custom `gift_page` template with hero, intro, top picks, jump links, FAQ, FAQ schema, and ad placeholders.
- Exposes WP-CLI sync command:

```bash
wp gift-hub sync
```

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
