# Pixact Cost Calculator (WordPress Embed)

React + TypeScript + Vite + Tailwind calculator designed for embedding in WordPress pages via shortcode.

## What this project includes

- Multi-step calculator flow
- Branching logic for `App`, `Website`, and `SaaS`
- Config-driven pricing engine
- WordPress admin settings for editable questions and prices
- Live estimate preview
- Lead capture before final result
- WordPress runtime config loader with safe mock fallback

## Runtime Config Contract

The calculator reads WordPress data from a global object:

```js
window.PixactCalculator = {
  restUrl: "https://example.com/wp-json",
  nonce: "wp_rest_nonce",
  siteUrl: "https://example.com"
}
```

If this object is missing or incomplete, the app automatically runs in mock mode.

## WordPress Mount Target

The app mounts to:

- `#pixact-calculator-root` (preferred for shortcode embeds)
- fallback: `#root` (local dev / standalone preview)

Example shortcode output container:

```html
<div id="pixact-calculator-root"></div>
```

## Development

```bash
npm install
npm run dev
```

## Production build

```bash
npm run build
```

Generated assets can be enqueued in WordPress and rendered where your shortcode outputs the mount element.

## WordPress Plugin Admin UX

In WordPress admin, open `Pixact Calculator` to edit:

- all step titles/subtitles/explanations
- project type labels/descriptions/badges/base prices
- complexity labels/descriptions/multipliers
- timeline labels/descriptions/multipliers
- add-on labels/descriptions/fixed prices

These values are localized into `window.PixactCalculator.calculatorConfig` and used by the React app at runtime.
