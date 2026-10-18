# Deploying the website CMS

This covers the first deployment of the `content-module` branch to production (PHP 8.1+, MySQL, LiteSpeed with `.htaccess`). Later deployments are ordinary file uploads.

## Before you start

1. Back up the production database and the site folder.
2. Check PHP has these extensions: `pdo_mysql`, `curl`, `dom`, `gd` (with WebP support if you upload WebP images), `mbstring`, `openssl`, `fileinfo`.
3. Run the tests locally and make sure they pass:

   ```bash
   php tests/run.php
   php tests/http-smoke.php http://vermaaccounting.host
   ```

## Step 1: upload everything except the new rewrites

The blog posts currently live in the `blogs` table (from the old Uplift sync). After the new rewrite rules go live, `/blog/{slug}` is served by the CMS, so the posts must be imported into the CMS first or every article URL returns 404.

Upload all changed and new files **except** these two, which stay at their old versions for now:

- `.htaccess`
- `blog-post.php`

Uploading both of them early creates a redirect loop on article URLs (the old rewrite sends `/blog/x` to `blog-post.php`, and the new `blog-post.php` redirects back to `/blog/x`).

Include these folders, which are new:

- `lib/cms/`, `lib/vendor/htmlpurifier/` (HTMLPurifier)
- `admin/vendor/tinymce/` (TinyMCE, GPL)
- `admin/cms*.php`, `admin/includes/cms-common.php`, `admin/includes/media-picker.php`, `admin/css/cms-admin.css`, `admin/js/cms-admin.js`, `admin/js/cms-editor.js`
- `cms.php`, `css/cms.css`, `components/site-nav.php`, `components/site-footer.php`, `components/site-footer-scripts.php`

Delete `scripts/sync-blogs.php` from the server and remove its cron job if one exists. The blog is no longer synced from Uplift.

The CMS tables are created automatically on the first request (`CmsSchema::ensure()`).

## Step 2: folders and permissions

Create these folders and make them writable by the PHP user (usually `755` for folders owned by that user, or `775` if PHP runs as a group member):

- `media/` — uploaded images. The first upload writes `media/.htaccess`, which stops scripts from running there.
- `data/cache/` — the page cache (`data/cache/cms/`) and HTMLPurifier cache (`data/cache/htmlpurifier/`).
- `css/` and the `.css` files in it — only if you want to edit stylesheets from Website → Stylesheets. The folder must be writable as well as the files, because a save writes a temporary file and then swaps it in.

Stylesheet edits and new stylesheets made in the admin live only on the server. Uploading the `css/` folder in a later deployment replaces them. Before deploying, open Website → Stylesheets and download any file that was edited there (its history shows who changed it), and copy the change into the codebase. If a deployment does overwrite an edit, the next save in the admin records the deployed file as a "Changed outside the admin" revision, and the earlier edit can still be compared and restored from the history.

`data/` must stay blocked from the web; the `.htaccess` rules already deny it.

## Step 3: site configuration

Log in to the admin and open **Website → Site configuration**:

1. Set **Public base URL** to `https://vermaaccounting.ca` (no trailing slash). Canonical URLs, the sitemap, robots.txt and social tags all use it.
2. Check the defaults on the **SEO defaults** tab and save.
3. Give team members the CMS capabilities they need in **Team** (see `docs/cms-schema.md`).

## Step 4: import the blog

Open **Website → Website overview**. The blog callout shows how many posts are waiting. Click **Import blog posts** (leave “Download featured images into the media library” ticked). This:

- creates the `/blog` index page and the Blog folder,
- imports every post as an article at `/blog/{slug}`, keeping its publish date, categories, tags, SEO title and description,
- keeps unpublished posts as drafts.

Running it again only imports posts that are not in the CMS yet.

Spot-check a few articles in **Website → Blog articles** before continuing.

## Step 5: deploy the new rewrites

Now upload the new `.htaccess` and `blog-post.php`. Then check:

```bash
php tests/http-smoke.php https://vermaaccounting.ca /blog /blog/<an-article-slug>
```

LiteSpeed reads `.htaccess` directly; if the host caches rewrite rules, restart LiteSpeed or touch `.htaccess` from the control panel.

For Nginx (local ServBay), the same rules are in `nginx.servbay.conf`, included from the site's server block. Reload Nginx after changing it. Keep the two files in sync.

## Step 6: cron

Scheduled publishing and expiry run from the existing campaign cron job. `scripts/send-campaign-batch.php` also calls `cms_process_schedule()` and the AI blog generator (`cms_run_blog_ai_cron()`). If the campaign cron is not set up yet, add it (every 5 minutes is enough):

```cron
*/5 * * * * /usr/local/bin/php /home/<account>/public_html/scripts/send-campaign-batch.php >/dev/null 2>&1
```

Use the PHP CLI path your host gives you. The Website overview also has a **Run scheduled publishing now** button.

### AI blog (ChatGPT)

1. Copy the `openai` and `blog_ai` keys from `config.example.php` into `config.local.php` and set a real `openai.api_key`.
2. Open **Website → AI blog**. Use **Generate and publish** for a one-off post, or paste topics into the cron queue (one per line).
3. Cron publishes the next queued topic when `blog_ai.enabled` is true, an API key is set, the queue is not empty, and `min_hours_between` has elapsed. Standalone: `php scripts/generate-blog.php`.

## Step 7: Search Console

1. Submit `https://vermaaccounting.ca/sitemap.xml` in Google Search Console. It is a sitemap index that lists one sitemap per content type.
2. Check `https://vermaaccounting.ca/robots.txt`. It is generated from **Website → Sitemap & robots** and includes the sitemap line.

Optional: connect Search Console, GA4 and PageSpeed for the **Search data** screen by adding credentials to `config.local.php` (the screen shows the exact keys). The CMS works without them.

## Moving hand-written pages into the CMS

This is optional and gradual. The hand-written PHP pages keep working, and they stay in the sitemap until a CMS page replaces them.

1. **Website → Migration → Add hand-written pages and old sitemap**, then **Crawl next batch** until every URL is crawled.
2. Set each URL's plan and import the ones you want as drafts. Nothing goes live on import.
3. Review each draft against **Old vs new**, then publish it from its editor. A published CMS page at the same URL replaces the hand-written page immediately.
4. For the homepage, publish the imported `/home` page, then choose **Use as homepage** in its editor.
5. If any URL changed, click **Create redirects for changed URLs** on the **Launch report** tab, then run **Check the live site**.
6. **Roll back migration** unpublishes every migrated page and removes the migration redirects, so the hand-written pages show again. Blog articles are not affected.

Only delete a hand-written `.php` file after its CMS page has been live and verified for a while.

## Rolling back the whole deployment

Restore the site files from the backup taken before step 1. The database can stay as it is: the CMS only adds its own `cms_*` tables and a few `cms_*` keys in `app_settings`, and the blog import reads the old `blogs` table without changing it.
