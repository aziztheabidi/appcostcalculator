# Pixact Cost Calculator

Production-ready React + WordPress calculator flow:

`User -> React calculator -> WordPress REST -> lead stored + email sent`

## Stack

- React + TypeScript + Vite + Tailwind (UI only)
- WordPress plugin (single backend)
- No external backend services

## Project Structure

```text
pixact-cost-calculator/
  pixact-cost-calculator.php
  includes/
    rest-api.php
    post-type.php
    email.php
  assets/
    calculator/
      assets/
        index.js
        index.css
        LeadCaptureForm.js
```

## Run React Locally

```bash
npm install
npm run dev
```

The app mounts to `#root` in local mode and automatically uses mock submission mode when `window.PixactCalculator` is not present.

## Build React

```bash
npm run build
```

This outputs:

- `dist/assets/index.js`
- `dist/assets/index.css`
- `dist/assets/LeadCaptureForm.js`

## Move Build into WordPress Plugin

Copy build artifacts into:

- `wordpress-plugin/pixact-cost-calculator/assets/calculator/assets/index.js`
- `wordpress-plugin/pixact-cost-calculator/assets/calculator/assets/index.css`
- `wordpress-plugin/pixact-cost-calculator/assets/calculator/assets/LeadCaptureForm.js`

## Install Plugin

1. Copy `wordpress-plugin/pixact-cost-calculator` into `wp-content/plugins/`
2. Activate **Pixact Cost Calculator** from WordPress admin
3. Add shortcode on a page:

```text
[pixact_cost_calculator]
```

## Shortcode + Frontend Bootstrapping

The shortcode renders:

```html
<div id="pixact-calculator-root"></div>
```

Assets load only when shortcode is present. WordPress localizes:

```js
window.PixactCalculator = {
  restUrl: "https://example.com/wp-json/pixact/v1/",
  nonce: "wp_rest_nonce",
  siteUrl: "https://example.com/"
}
```

## Lead API

- Endpoint: `POST /wp-json/pixact/v1/lead`
- Requires `X-WP-Nonce`
- Validates required fields: `name`, `email`
- Sanitizes all input
- Anti-spam checks:
  - honeypot field must be empty
  - submission timing check

## Lead Storage

Leads are stored in custom post type:

- `calculator_lead`

Saved meta:

- `name`
- `email`
- `phone`
- `answers` (JSON string)
- `estimate_min`
- `estimate_max`
- `timeline`
- `complexity`

## Email Notifications (SMTP-Compatible)

On successful lead submission, plugin uses `wp_mail()` and sends:

- name
- email
- phone
- estimate range
- timeline
- complexity
- full answers JSON

Because it uses `wp_mail()`, WordPress SMTP plugins can handle delivery.

## Security Notes

- Nonce verification with `wp_verify_nonce`
- Input sanitization (`sanitize_text_field`, `sanitize_email`)
- No direct SQL queries
- No exposed API keys

## UI/UX Notes

- Config-driven steps (no hardcoded step flow in components)
- Conversational multi-step layout
- Sticky estimate panel on desktop
- Bottom estimate bar on mobile
- Accessible form labels and keyboard-friendly controls
- Scoped calculator styles to avoid WP theme conflicts
