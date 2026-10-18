# CMS delivery checklist

Branch: `content-module`. Status as of 13 October 2026. Nothing has been committed, merged or deployed.

How it was verified:

- `php tests/run.php`: 36 tests against a temporary SQLite database, all passing. They assert on rendered HTML and HTTP status codes, not only on stored values.
- `php tests/http-smoke.php http://vermaaccounting.host`: read-only HTTP checks against the local site, all passing.
- Admin write flows (save, publish, redirects, migration, site configuration, sitemap settings) were exercised through a local request harness on a SQLite copy. Every admin screen was opened in the browser for a visual check.

Legend: **Done**, **Pending** (work left for you or for later), **Blocked** (needs something outside the code).

## 1. Foundation and routing

- **Done:** CMS tables created automatically (`CmsSchema`), Website permissions and sidebar section, dashboard with real counts.
- **Done:** Path rules: normalisation, reserved paths, collisions, folder cycles. Root files that are not pages (`config.local.php`, endpoints) are never treated as pages.
- **Done:** `cms.php` front controller: redirects first, 301 for non-canonical variants (case, trailing slash, `.php`), real 404 and 410 with the site design, drafts hidden except through preview tokens.
- **Done:** Rewrites in `.htaccess` and `nginx.servbay.conf`: existing files and hand-written pages first, then the CMS; `/database/`, `*.md`, `*.conf` and config files denied.
- **Done:** A published CMS page at a hand-written page's URL takes over that URL (through `components/header.php`).

## 2. Editor and folders

- **Done:** Folder tree with expand/collapse, drag reorder, move, archive, landing pages, SEO defaults, categories and tags.
- **Done:** Content lists for all content, pages, articles and city pages, with server-side search, filters, sorting and pagination.
- **Done:** Bulk actions on content lists (`cms.publish`): tick rows or select all on the page, then Publish, Move to draft, Unpublish, Archive or Delete permanently, with a confirmation. Items already in the target state are skipped; the homepage cannot be bulk-deleted; generated city drafts still need review in the editor before publishing.
- **Done:** Editor tabs: Basic, Body, SEO, Social, Media, Structured data, Publishing, Revisions, and Location for city pages.
- **Done:** TinyMCE (GPL) with HTML mode, HTMLPurifier on save, raw HTML only with `cms.code`, autosave every 30 seconds, unsaved-changes warning, revocable preview links.
- **Done:** Revisions with a field comparison, a side-by-side body diff, and restore.
- **Done:** Workflow (draft, review, scheduled, published, unpublished, archived) with publish permission checks; scheduling runs from the campaign cron.
- **Done:** Media library with validated uploads, random names, dimensions, alt text, captions, `srcset` variants, a picker in the editor and a usage check before delete.
- **Done:** City page editor and bulk draft generator (never auto-published) with near-duplicate warnings.
- **Done:** City page defaults (City pages → Default content, `cms.publish`): title, H1, summary, SEO title, meta description and body templates with `{city}`, `{region}`, `{country}`, `{service}`, `{phone}`, `{email}`, `{site_name}`, plus `{h1}` and `{summary}` in the body. A city page only needs a city name; each empty field shows the default for that city when rendered, so editing a default updates every page still using it. Typing into a field on a page overrides it for that page (a custom title also replaces the default H1 and SEO title; a custom summary replaces the default meta description). "Add city pages" creates pages from city names only (drafts or published, `prefix/city` URLs).
- **Done:** No page layouts. Pages and city pages render only their body inside `<main class="main-content">`, like the hand-written pages, so the body supplies its own H1 and sections (the audit flags a missing H1). Articles keep the article layout; a page can tick "Show the latest blog articles" to become the blog home. The editor shows the defaults as placeholders and offers to copy the default body in; the list marks pages on default content; the audit reports them as info instead of duplicates.
- **Done:** Blog importer, available from the Website overview. The Uplift sync script and admin screens are removed; the `blogs` table is kept as a backup.
- **Changed from plan:** `blog.php` was not turned into a redirect shim. It keeps showing the old list until the CMS `/blog` page is published, and the CMS then takes over the URL automatically. This avoids an empty blog between deployment and import.
- **Done:** Dry run of the blog import on a throwaway copy of the 72 local posts: all 72 imported as published articles with no errors, the articles sitemap is valid, and `/blog` and the article pages return 200. The audit then shows only suggestions (66 titles over 60 characters, 4 descriptions outside the recommended length).
- **Pending:** Run the blog import for real on production (and locally if you want the CMS blog there). Follow the order in `docs/cms-deployment.md`.

## 3. SEO engine

- **Done:** One effective-SEO resolver (page, then folder, then site) for title, description, robots, canonical, social tags and `X-Robots-Tag`.
- **Done:** JSON-LD for WebSite, Organization/LocalBusiness, WebPage, Article/BlogPosting, BreadcrumbList, Service, Person and FAQPage (FAQPage only when FAQs are visible); safe escaping; per-type switches; custom JSON-LD validated (no fake reviews).
- **Done:** Editor counters, search and social previews, an effective-settings panel and live warnings (length, H1, heading order, alt text, vague links, noindex, canonical, URL changes).
- **Done:** The renderer outputs exactly one title, description and canonical, in the specified order.

## 4. Site configuration

- **Done:** Site name, base URL, language, SEO defaults, header and footer markup with live placeholders (`{{site_header}}`, `{{submit_menu}}` and others), head markup, scripts, shared CSS and JS, verification tags, GA4 ID, and the 404 page.
- **Done:** Code fields need `cms.code`; SEO fields need `cms.seo`. PHP and duplicate SEO tags are rejected.
- **Done:** Revisions with preview (renders unsaved settings without saving), comparison and restore.
- **Done:** Page cache in `data/cache/cms/`, cleared on any content, configuration, redirect, media text or form change.
- **Done:** Reusable blocks (Website → Blocks, `cms.code`): `{{block:token}}` renders the block's code in content bodies, the header, the footer and other blocks; `cms_block('token')` in hand-written pages. Insert-block menu in the editor, usage list, revisions with compare and restore, disable without deleting. Unknown or disabled tokens render nothing publicly, show a note in previews, add a save notice and an SEO audit error. Loops between blocks are cut off.
- **Done:** Stylesheet editor (Website → Stylesheets, `cms.code`) for the files in `css/`. Saves are checked for unbalanced braces, unclosed comments or quotes, and unsafe code; a file changed on the server since it was opened is never overwritten; files are replaced atomically; every save keeps a revision with compare, restore and download. New stylesheets can be created there; each can load on every page (hand-written and CMS, after the main stylesheets) or not at all, and only files created there can be deleted.
- **Done:** Stylesheet links carry `?v=<file modified time>` instead of hand-maintained numbers, on hand-written pages, form pages and CMS pages.
- **Pending:** Set the public base URL to `https://vermaaccounting.ca` on production (the configuration page warns while it is empty).
- **Pending:** On production, make `css/` writable by PHP if stylesheets will be edited from the admin.

## 5. Redirects and migration

- **Done:** Redirect manager: 301, 302, 307, 308 and 410; search, filters, hit counts; CSV import and export; validation for loops, chains, missing destinations and overwrites, with a confirm step for warnings; no open redirects (external targets need `cms.code`); a one-click chain fixer and a URL tester.
- **Done:** Migration manager: inventory from the hand-written pages, the old `sitemap.xml`, CSV or single URLs; batch crawling; per-URL plan, target URL, type and folder; import as drafts with optional image copying; old-versus-new comparison; pre-launch and live launch reports with broken links and sitemap differences; redirect creation once pages are published; full rollback.
- **Pending:** The hand-written pages have not been migrated. This is optional and gradual; the guide is in `docs/cms-deployment.md`.
- **Pending:** After crawling on production, review the broken-link list on the Launch report and fix any relative links in the hand-written pages as part of their migration.

## 6. Sitemap, audits and integrations

- **Done:** `sitemap.xml` index with one sitemap per type. It lists only published, indexable, self-canonical URLs with real last-modified dates, plus hand-written pages until they are replaced. Each section can be switched off, and paths can be excluded.
- **Done:** robots.txt editor with live preview, a URL tester, and warnings (site-wide blocks, blocked important or published pages, missing sitemap line) that must be confirmed before saving.
- **Done:** SEO audit with errors, warnings and suggestions; filters, search and CSV export; per-check counts; an internal-link table from `cms_content_links` (orphans and weakly linked pages); broken links and images; thin and near-duplicate city pages; redirect and sitemap problems.
- **Done:** Search data screen for Search Console, GA4 and PageSpeed, with source and fetch time on every number, and a list of pages with many impressions but few clicks. Everything works without credentials.
- **Blocked:** Connecting Google services needs a PageSpeed API key and a Google service account with access to Search Console and GA4, added to `config.local.php`.
- **Pending:** Submit the sitemap in Search Console after deployment, and run `tests/http-smoke.php` against production.

## Production notes

See `docs/cms-deployment.md` for the full order. In short:

1. Upload everything except `.htaccess` and `blog-post.php`.
2. Make `media/` and `data/cache/` writable (and `css/` for the stylesheet editor).
3. Set the base URL.
4. Import the blog.
5. Upload `.htaccess` and `blog-post.php`.
6. Confirm the cron job.
7. Submit the sitemap.
