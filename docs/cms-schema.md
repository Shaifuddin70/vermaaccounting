# CMS data model

All tables are defined once in `lib/cms/CmsSchema.php`. The same definition produces MySQL (production) and SQLite (tests) DDL. `CmsSchema::ensure()` runs on bootstrap and creates missing tables when `CmsSchema::VERSION` is higher than the `cms_schema_version` setting. There are no destructive migrations; bump `VERSION` whenever a table or index is added.

Times are stored in UTC (`Y-m-d H:i:s`). Admin screens show them in the site time zone.

## Tables

| Table | Purpose | Key columns |
| --- | --- | --- |
| `cms_contents` | Pages, articles and city pages | `type` (page, article, city), `path` (unique), `status` (draft, review, scheduled, published, unpublished, archived), `publish_at`, `expire_at`, `published_at`, `body_html`, `body_mode` (sanitized or raw), `template` (`full-width` = body only; `article-index` = blog home; `article`), `folder_id`, `preview_token`, `faqs_json` |
| `cms_seo` | Per-content SEO, one row per content item | `seo_title`, `meta_description`, `robots_index`/`robots_follow` (inherit, index, noindex, follow, nofollow), `max_*` directives, `robots_extra`, `sitemap_include`, `canonical_mode` (auto or custom), `canonical_url`, Open Graph and Twitter fields, `schema_json` (disabled automatic types plus custom JSON-LD) |
| `cms_city_data` | Local details for city pages | `city`, `region`, `primary_service`, `related_services_json`, NAP fields, `hours_json`, map URLs, `nearby_json`, `cta_json`, `local_business` (emit LocalBusiness only when 1 and details are real) |
| `cms_folders` | Folder tree and URL prefixes | `parent_id`, `url_prefix`, `sort_order`, `landing_content_id`, `default_type`, `seo_defaults_json` (robots and preview defaults inherited by content) |
| `cms_terms`, `cms_content_terms` | Categories and tags | `kind` (category or tag), `slug` unique per kind |
| `cms_revisions` | Content history and autosaves | `snapshot_json`, `is_autosave`, `user_name`, `comment` |
| `cms_url_history` | Every URL change | `old_path`, `new_path`; used to suggest redirects and to find old links |
| `cms_redirects` | 301/302/307/308/410 rules | `source_path` (unique), `target`, `status_code`, `origin` (manual, auto, import, csv), `hits`, `last_hit_at` |
| `cms_media` | Media library | `path` under `/media/`, `width`, `height`, `bytes`, `alt`, `caption`, `credit`; resized copies (`name-480w.jpg` and so on, same format as the original) are written next to it for `srcset` |
| `cms_content_links` | Internal link graph | `content_id`, `target_path`, `is_internal`, `kind` (a or img), `anchor`; rebuilt on every save and used by the audit |
| `cms_site_config_revisions` | History of Site configuration | `config_json`, `comment`, `user_name` |
| `cms_stylesheet_revisions` | History of the stylesheets in `css/` edited from Website → Stylesheets | `file` (e.g. `styles.css`), `css`, `hash` (SHA-256), `comment`, `user_name`; the first save stores the original file, and a file changed on disk since the last save gets its own "Changed outside the admin" revision |
| `cms_blocks` | Reusable code blocks (Website → Blocks) | `token` (unique, fixed after creation), `name`, `description`, `code` (output verbatim), `status` (active, disabled). `{{block:token}}` in a content body, the site header or footer, or another block renders the code; a token alone in a paragraph replaces the paragraph. Hand-written pages use `<?= cms_block('token') ?>` |
| `cms_block_revisions` | History of each block | `block_id`, `name`, `code`, `status`, `comment`, `user_name` |
| `cms_migration_items` | Migration inventory | `source_path` (unique), `origin` (legacy, sitemap, csv, manual, blog), crawl results (`http_status`, `title`, `description`, `canonical`, `robots`, `h1`, `body_html`, `links_json`, `images_json`, `word_count`), plan (`action`, `target_path`, `content_type`, `folder_id`), `content_id` once imported |

## Settings in `app_settings`

| Key | Contents |
| --- | --- |
| `cms_site_config` | Site configuration: base URL, SEO defaults, header and footer markup, head and body scripts, shared CSS and JS, 404 page, homepage (`home_content_id`), sitemap toggles and exclusions, robots.txt, audit thresholds. Defaults are in `CmsSiteConfig::defaults()`. |
| `cms_schema_version` | Last applied `CmsSchema::VERSION` |
| `cms_city_defaults` | City page default content (`CmsCityDefaults`): `service`, `title` (must contain `{city}`), `h1`, `excerpt`, `seo_title`, `meta_description`, `body_html` (sanitised). Applied at render time to empty fields of city pages; nothing is copied into the pages |
| `cms_stylesheets_added` | Stylesheets created from Website → Stylesheets, in load order, each with `load` (linked on every page through `CmsSiteConfig::headExtras()`, after the main stylesheets and before Global CSS) |
| `cms_integration_data` | Last fetched Search Console, GA4 and PageSpeed results, each with source and fetch time |

Integration credentials are never stored in the database. They live in `config.local.php` under `'cms' => ['integrations' => [...]]` (see `lib/cms/CmsIntegrations.php`).

## Files on disk

| Path | Purpose | Web access |
| --- | --- | --- |
| `media/` | Uploaded images and generated variants | Public; scripts are denied |
| `data/cache/cms/` | Rendered page cache, flushed on any content, config, redirect or form change | Denied |
| `data/cache/htmlpurifier/` | HTMLPurifier definition cache | Denied |
| `css/*.css` | Site stylesheets, editable from Website → Stylesheets. Pages link them as `?v=<file modified time>`, so a save reaches visitors on their next page load | Public |

## Permissions

Capabilities are defined in `lib/permissions.php`. Any CMS capability implies `cms.view`, and `cms.publish` and `cms.code` imply `cms.edit`.

| Capability | Allows |
| --- | --- |
| `cms.view` | See the Website section, content lists, the audit and search data |
| `cms.edit` | Create and edit drafts, upload media, generate city drafts |
| `cms.publish` | Publish, schedule, unpublish, delete, restore revisions, import blogs, roll back a migration |
| `cms.seo` | Edit SEO defaults, sitemap and robots.txt, folder SEO defaults; refresh search data |
| `cms.redirects` | Manage redirects and the migration inventory |
| `cms.code` | Raw HTML bodies, custom JSON-LD, header and footer markup, scripts and styles, editing the stylesheet files in `css/`, creating and editing blocks (anyone with `cms.edit` can insert an existing block's token), external redirect targets, choosing the homepage |
