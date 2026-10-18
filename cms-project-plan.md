# Project Specification: SEO-First CMS Module for an Existing PHP/MySQL CRM

## 1. Project Objective

Build a production-ready, SEO-focused Content Management System inside my existing PHP and MySQL CRM.

The module must allow administrators to manage the public website from the CRM without manually editing PHP files for individual pages.

The CMS must support:

- Regular website pages and landing pages.
- Articles and blog posts.
- Location and city landing pages.
- Hierarchical folders and nested URLs.
- WYSIWYG and raw HTML editing.
- Comprehensive on-page and technical SEO controls.
- Centralized website header, footer, CSS, JavaScript and head configuration.
- Existing website page migration.
- Redirects and URL history.
- Dynamic XML sitemaps and robots.txt management.
- SEO auditing and optional search performance integrations.
- Drafts, publishing, scheduling, revisions and previews.

This must be a fully functional system, not a static UI prototype.

## 2. First Task: Inspect the Existing CRM

Before writing or modifying code, inspect the entire existing project.

Identify:

- PHP version and application architecture.
- Database connection and query conventions.
- Existing authentication and session handling.
- User roles and permission checks.
- Existing admin navigation and layout.
- CSS framework, JavaScript libraries and notification system.
- URL rewriting and routing configuration.
- Existing public website pages and templates.
- Database schema and migration conventions.
- File upload structure and permissions.
- Existing APIs, forms and CRM functionality that must remain unchanged.
- Current deployment environment and server limitations.

Reuse existing components whenever possible.

Do not create a separate authentication system, database connection, admin layout or duplicate session handling if the CRM already provides these.

Do not overwrite unrelated CRM functionality.

Before making changes, create a concise implementation plan and list the files and database objects that will be affected. Then proceed with implementation.

## 3. Technology Requirements

Use the existing PHP and MySQL stack.

Prefer:

- PDO or the project's existing database abstraction.
- The existing Bootstrap version and frontend libraries.
- AJAX/fetch for appropriate admin interactions.
- The existing toast or notification system.
- A mature WYSIWYG editor such as TinyMCE or CKEditor, subject to licensing and deployment requirements.
- Server-rendered HTML for public pages.
- Existing authentication, authorization and logging infrastructure.

Use prepared statements, CSRF protection, output escaping, secure uploads, transactions and server-side validation.

Do not introduce a new frontend framework unless the existing architecture requires it.

## 4. Content Types

Implement three content types:

### A. Regular Pages

Support homepages, about pages, service pages, landing pages, custom HTML pages, FAQs and other standard website pages.

### B. Articles and Blogs

Support articles, categories, tags, author information, featured images, excerpts, publication dates, related articles and article-specific structured data.

### C. City Pages

Support city, region, country, location-specific services, local business details where applicable, phone, address, map links, FAQs, unique content, nearby locations and location-appropriate schema.

Each content type should have an appropriate editing interface while sharing a common content storage, rendering, routing and SEO architecture.

## 5. Hierarchical Folder and URL Management

Build a hierarchical content tree.

Requirements:

- Create, edit, reorder, move, archive and delete folders.
- Nest pages under folders or parent pages.
- Support drag-and-drop ordering where practical.
- Provide expand/collapse navigation and breadcrumbs.
- Preview the complete public URL before saving or publishing.
- Support folder landing pages such as /blogs/.
- Support nested paths such as /blogs/seo-guide/.
- Keep organizational hierarchy separate from the public URL.
- Detect slug and normalized URL conflicts.
- Prevent circular parent relationships.
- Preserve public URLs when moving content unless the administrator explicitly changes the URL.
- Maintain URL history and redirect records.

Normalize URL paths consistently, including trailing slashes, case rules, percent encoding and reserved characters.

Ensure that every active public URL resolves to at most one content record.

## 6. Content Editor

Create a professional content editor with separate tabs or sections for:

1. Basic information.
2. Body content.
3. SEO settings.
4. Social sharing.
5. Images and media.
6. Structured data.
7. Publishing.
8. Revision history.

Fields must include:

- Internal name.
- Visible page title and H1.
- Slug and parent.
- Full URL preview.
- Content type.
- WYSIWYG editor.
- Raw HTML source mode.
- Excerpt.
- Featured image and alt text.
- Author.
- Categories and tags where applicable.
- Page template.
- Language.
- Publication status.
- Scheduled publish and optional expiry date.
- Creation, update and publication timestamps.
- Internal notes.
- SEO metadata.
- Social metadata.
- Canonical URL.
- Robots directives.

Support autosave, unsaved-change warnings, preview, revisions, revision comparison and restoration.

Implement secure preview links for unpublished content.

Ensure that switching between WYSIWYG and source mode preserves valid content.

Do not allow untrusted users to inject arbitrary scripts or server-side PHP.

## 7. Publishing Workflow

Implement the following states as appropriate:

- Draft.
- In review.
- Scheduled.
- Published.
- Unpublished.
- Archived.

Support scheduling through a reliable server cron job or task scheduler.

Draft and unpublished pages must not be accessible as normal public pages.

Use real HTTP status codes for inaccessible or unpublished URLs, and ensure the behavior does not expose private content.

When publishing or updating a page, update routing, sitemap eligibility, cache state and relevant metadata consistently.

Use transactions for operations that affect multiple database records.

## 8. Complete SEO Metadata System

Every content record must support the following SEO controls.

### Title and Description

- Custom SEO title.
- Custom meta description.
- Configurable fallback behavior.
- Live character counts.
- Search-result preview.
- Missing and duplicate metadata warnings.
- Optional global title suffix.
- Separate social title and description.

### Indexing and Robots

- Indexing: inherit default, index, noindex.
- Link following: inherit default, follow, nofollow.
- Snippet directive.
- Image preview directive.
- Video preview directive.
- Optional supported robots directives.
- Effective directives preview.
- Sitemap inclusion control.
- Warnings for accidental noindex.
- Conflict detection between HTML meta directives and HTTP headers.

Do not assume that index/follow guarantees indexing.

Do not use robots.txt blocking as a substitute for noindex.

### Canonical URLs

- Automatic self-canonical.
- Custom canonical.
- Explicit canonical override.
- Absolute URL validation.
- Canonical conflict detection.
- Redirect suggestions after URL changes.
- URL history.

### Social Metadata

- Open Graph title.
- Open Graph description.
- Open Graph image and alt text.
- Open Graph type.
- Twitter/X card type.
- Optional social title and description overrides.
- Social preview.

### Image SEO

- Alt text.
- Image dimensions.
- Descriptive filenames.
- Optional captions and credits.
- Responsive images where supported.
- Lazy loading for appropriate below-the-fold images.
- Optimized featured image delivery.
- Missing-alt and oversized-image warnings.

### Structured Data

Support validated JSON-LD templates for applicable types:

- WebSite.
- Organization.
- LocalBusiness where appropriate.
- WebPage.
- Article and BlogPosting.
- BreadcrumbList.
- Service.
- Person.
- Eligible FAQPage markup.
- Other relevant Schema.org types where justified.

Allow approved schema templates, per-page configuration, validation and disabling individual schema types.

Do not fabricate reviews, ratings, business information or structured data.

## 9. Centralized Site Configuration

Create a dedicated Site Configuration panel.

It must manage:

- Site name.
- Public base URL.
- Site language.
- Header HTML.
- Footer HTML.
- Global head markup.
- Global CSS.
- Global JavaScript.
- Approved head scripts.
- Body-start scripts.
- Body-end scripts.
- Verification meta tags.
- Analytics integrations.
- Default SEO metadata.
- Default robots settings.
- Sitemap configuration.
- 404 page template.
- Approved shared assets.
- Configuration revision history.

The public renderer must assemble a complete HTML document from these global settings and the individual page's body content.

Rendering order:

1. Document declaration and HTML attributes.
2. Head metadata and SEO directives.
3. Canonical URL and social metadata.
4. Structured data.
5. Shared CSS and approved head assets.
6. Shared header.
7. Page body.
8. Shared footer.
9. Approved body-end scripts.
10. Closing document tags.

Prevent duplicate titles, canonical tags, meta descriptions and conflicting dependencies.

Global configuration should be versioned, previewable and reversible.

Restrict arbitrary code configuration to trusted administrators. Do not execute PHP code stored in the database.

Do not allow global scripts or markup to bypass security controls.

## 10. City Page Management

Build a dedicated city page editor.

Support:

- City, region and country.
- Primary service and related services.
- Local contact details when applicable.
- Address and business hours where accurate.
- Map URL or approved embed.
- Location images.
- Unique local introduction and content.
- Relevant FAQs.
- Nearby areas and related cities.
- City-specific calls to action.
- Local SEO metadata.
- Canonical URL.
- Breadcrumbs.
- Suitable structured data.

Allow template-driven draft generation but require editorial review before publishing automatically generated pages.

Implement a configurable duplicate-content similarity check.

Warn when city pages contain only a city-name substitution and little unique value.

Do not automatically publish large numbers of thin or near-duplicate landing pages.

## 11. Database Design

Design a normalized schema based on the existing CRM database.

Consider the following logical entities:

- cms_contents.
- cms_folders.
- cms_content_revisions.
- cms_categories.
- cms_tags.
- cms_content_tags.
- cms_authors or integration with existing CRM users.
- cms_media.
- cms_content_media.
- cms_seo_metadata.
- cms_redirects.
- cms_url_history.
- cms_site_config.
- cms_templates.
- cms_city_data.
- cms_content_links.
- cms_audit_logs.
- cms_settings.

These are logical responsibilities, not a mandate to create unnecessary tables.

Document the final schema and relationships before implementation.

Use indexes for public paths, content status, type, publication dates and frequently queried fields.

Implement collision-safe URL uniqueness.

Use transactions when publishing, renaming, moving or deleting content.

Use reversible database migrations compatible with the existing project conventions.

Never drop or modify unrelated CRM tables without explicit justification.

## 12. Public Website Rendering

Build or integrate a routing layer that resolves database-managed content using clean URLs.

Requirements:

- / resolves to the configured homepage.
- Nested URLs resolve to their intended content records.
- Published pages return HTTP 200.
- Unknown URLs return HTTP 404.
- Permanently replaced URLs use appropriate redirects.
- Temporary redirects use appropriate temporary status codes.
- Unpublished content is not exposed.
- Duplicate URL variants are handled consistently.
- Existing CRM routes and API endpoints continue working.
- Public content is rendered as valid HTML.
- Metadata and structured data are safely escaped and serialized.
- Cache invalidation occurs when relevant content or configuration changes.
- Public routes use the configured canonical host and URL rules.

Do not redirect all missing pages to the homepage.

Do not break existing static assets, forms, payment integrations or CRM functionality.

## 13. XML Sitemap and Robots.txt

Generate dynamic XML sitemaps from eligible published URLs.

Requirements:

- Exclude drafts, unpublished pages, redirects, duplicate canonicals and noindex pages by default.
- Support separate sitemap files for different content types when useful.
- Generate a sitemap index for larger sites.
- Use accurate lastmod timestamps.
- Validate generated XML.
- Preview sitemap contents.
- Use absolute canonical public URLs.
- Configure sitemap inclusion rules.
- Provide a safe robots.txt editor and preview.
- Warn about accidental crawl blocking.
- Do not treat sitemap inclusion as a guarantee of indexing.

## 14. Redirect and URL Management

Implement:

- 301 redirects.
- 302 and other appropriate temporary redirects.
- Old-to-new URL mapping.
- CSV import/export.
- Bulk redirects.
- Redirect hit logging where practical.
- Redirect loop detection.
- Redirect chain detection.
- Invalid destination validation.
- URL change history.
- Automatic redirect suggestions when a published URL changes.
- Protection against open redirects.

Do not overwrite an existing redirect without warning.

Preserve valuable legacy URLs wherever practical.

## 15. Website Migration Manager

Create a dedicated migration workflow for existing public pages.

Features:

- CSV URL inventory import.
- CSV redirect import/export.
- Existing-site crawling.
- Extraction of title, description, canonical, H1, content and internal links where available.
- HTML import.
- Metadata import.
- Image mapping.
- Folder and content-type assignment.
- Duplicate detection.
- Old-versus-new metadata comparison.
- Dry-run mode.
- URL mapping.
- Redirect validation.
- Broken-link report.
- Sitemap comparison.
- Rollback plan.
- Prelaunch and post-launch reports.

Preserve existing URLs wherever practical.

If a URL changes, create and validate the appropriate redirect.

Do not assume a content import alone preserves SEO.

Use staging and backups before the final production migration.

## 16. SEO Audit Dashboard

Create an actionable SEO dashboard.

Audit for:

- Missing or duplicate titles and descriptions.
- Missing H1.
- Heading hierarchy problems.
- Missing canonical.
- Invalid or conflicting robots directives.
- Accidental noindex.
- Duplicate or conflicting canonical URLs.
- Broken internal links.
- Orphan pages.
- Broken images.
- Missing image alt text.
- Redirect chains and loops.
- Invalid structured data.
- Published URLs missing from the sitemap.
- Sitemap URLs returning errors.
- Near-duplicate city pages.
- Thin content warnings.
- Missing breadcrumbs.
- Invalid or inconsistent public URLs.

Support individual page audits, bulk audits, filters, severity levels and CSV export.

Use configurable thresholds.

Do not claim that an internal SEO score predicts Google rankings.

## 17. Optional Integrations

Keep external integrations optional.

Consider:

- Google Search Console.
- Google Analytics 4.
- PageSpeed Insights.
- External website crawlers.
- CRM webhooks and reporting APIs.

Use documented APIs, secure credential storage, appropriate permissions and error handling.

Show the date and source of imported metrics.

The CMS must continue working without any external API credentials.

## 18. Admin UI Requirements

Integrate with the existing CRM layout.

Provide:

- CMS dashboard.
- All Content.
- Regular Pages.
- Articles and Blogs.
- City Pages.
- Folder Manager.
- Media Library.
- SEO Manager.
- Redirect Manager.
- Site Configuration.
- Website Migration.
- Revision History.
- Audit Logs.

Use the existing Bootstrap version and notification components.

Implement server-side pagination, search, filters and sorting.

Use AJAX for suitable actions, including autosave and inline updates, without requiring full-page reloads unnecessarily.

Include loading states, validation messages, empty states, confirmation dialogs and error handling.

Ensure responsive and accessible controls.

## 19. Security Requirements

- Reuse existing authentication and authorization.
- Apply least-privilege permissions.
- Use prepared SQL statements.
- Validate all input server-side.
- Use CSRF protection.
- Escape all output in its correct context.
- Sanitize WYSIWYG HTML.
- Restrict arbitrary HTML and JavaScript configuration.
- Validate uploads and generate safe filenames.
- Prevent path traversal and stored XSS.
- Protect preview links.
- Secure credentials and tokens.
- Record sensitive administrative actions.
- Prevent open redirects.
- Protect against SQL injection and unauthorized publishing.
- Avoid exposing unpublished content through sitemaps, APIs or direct routes.

## 20. Performance and Reliability

- Index frequently queried database fields.
- Paginate large admin lists.
- Cache public rendering where appropriate.
- Invalidate caches correctly.
- Optimize media delivery.
- Avoid unnecessary database queries.
- Handle large imports in manageable batches.
- Use database transactions for multi-record changes.
- Log actionable errors.
- Avoid exposing sensitive data in logs.
- Maintain compatibility with the deployed PHP and MySQL versions.

## 21. Testing Requirements

Create tests for:

- URL routing.
- URL collision prevention.
- Content creation and editing.
- Folder hierarchy.
- Publication and scheduling.
- SEO metadata inheritance and overrides.
- Canonical URL generation.
- Robots directive generation.
- Sitemap eligibility.
- Redirect behavior and loops.
- HTML escaping and sanitization.
- Permissions.
- File uploads.
- Revision restoration.
- Migration imports.
- Cache invalidation.
- HTTP status codes.
- Existing CRM regression behavior.

Test the final rendered HTML, not only database values.

## 22. Required Implementation Phases

### Phase 1: Inspection and Architecture

Inspect the CRM, document its architecture, map existing database conventions and propose the CMS schema and routing strategy.

### Phase 2: Database and Foundation

Implement migrations, permissions, shared services, content models, routing infrastructure and audit logging.

### Phase 3: Core Content Management

Implement regular pages, articles, folders, media management, WYSIWYG/source mode and revision history.

### Phase 4: SEO Engine

Implement metadata, canonical URLs, robots directives, social metadata, structured data and validation.

### Phase 5: Site Configuration and Public Renderer

Implement global header/footer, CSS/JavaScript configuration, HTML rendering, clean URLs, error pages and cache invalidation.

### Phase 6: City Pages

Implement location-specific fields, templates, content validation and local SEO.

### Phase 7: Technical SEO

Implement sitemap generation, robots.txt management, redirects and SEO audits.

### Phase 8: Migration

Implement inventory, import/export, URL mapping, redirect validation and migration reports.

### Phase 9: Integrations and Advanced Reporting

Add optional Search Console, analytics, PageSpeed and reporting integrations.

### Phase 10: Testing and Deployment

Run security, functional, SEO and regression tests. Document installation, migrations, cron jobs, permissions, backups and deployment.

## 23. Rules for Cursor During Development

1. Inspect the current code before creating new files.
2. Do not replace existing application architecture unnecessarily.
3. Do not implement placeholder buttons or nonfunctional settings.
4. Do not silently omit features specified in this plan.
5. Do not invent database columns without documenting the schema.
6. Do not introduce incompatible PHP dependencies.
7. Do not expose unpublished pages or unsafe custom scripts.
8. Do not publish generated content automatically without the configured workflow.
9. Test each phase before continuing.
10. Keep a project checklist with completed, pending and blocked items.
11. Update documentation when schema or architecture changes.
12. Prefer small, reviewable changes over rewriting the entire project.
13. Preserve existing CRM functionality and data.
14. Report any requirement that conflicts with the existing architecture before choosing an alternative.
15. Never claim a feature is complete until its UI, backend, persistence and relevant tests work.

## 24. Definition of Done

The module is complete only when:

- Admins can create, edit, preview, publish and unpublish all three content types.
- Nested URLs and folder organization work correctly.
- Every page's effective SEO configuration is visible and validated.
- Site-wide HTML, CSS and scripts can be managed centrally.
- Public pages render valid, server-generated SEO metadata.
- Canonicals, robots directives, redirects and sitemaps work correctly.
- Existing pages can be migrated with URL preservation and redirect mapping.
- Revision history and rollback work.
- City pages support meaningful local content.
- Permissions and security controls are enforced server-side.
- All important functions persist to the database.
- Existing CRM functions remain intact.
- Automated tests and a deployment guide are available.

Begin with Phase 1. Inspect the project and report the proposed architecture, database schema, affected files and implementation sequence before modifying code.