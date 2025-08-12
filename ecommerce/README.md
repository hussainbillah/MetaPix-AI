# ShopLite (Static E-commerce)

A dependency-free static e-commerce storefront you can open directly in a browser.

## Features
- Product catalog (from `products.json`)
- Search and category filtering
- Product detail modal
- Cart with localStorage persistence
- Simple checkout simulation

## How to use
- Open `ecommerce/index.html` in your browser (via any static server or just double-click).
- Products are defined in `ecommerce/products.json`.
- Styling uses Tailwind via CDN.

## Customize
- Edit `products.json` to change products.
- Update branding in `index.html` (title, header, favicon emoji).
- Adjust behavior/styles in `app.js` and `styles.css`.

## Notes
- Checkout is simulated and does not charge real payments.
- Shipping is free over $100; otherwise $6.99.