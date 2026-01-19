Sitemap Accordion module

Install:
1. Copy `modules/sitemapaccordion` to your PrestaShop `modules/` directory (already in theme workspace).
2. In Back Office -> Modules, search for "Sitemap Accordion" and click Install.
3. Clear cache (Advanced Parameters -> Performance) or delete `var/cache/*`.

What it does:
- Injects JS/CSS to transform any `ul.tree` sitemap into an accordion with tree lines.
- No theme template modifications required.

Notes:
- The module registers the `header` hook to add assets.
- It automatically wraps existing sitemap markup to add toggles when installed.
