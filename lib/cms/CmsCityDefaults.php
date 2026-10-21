<?php

declare(strict_types=1);

/**
 * Default content for city pages. A city page stores only what was typed into it; every empty field
 * (title, H1, summary, SEO title, meta description, body) is filled from these templates when the page
 * is shown, so changing a default updates every city page that has not been customised.
 */
final class CmsCityDefaults
{
    public const SETTING_KEY = 'cms_city_defaults';

    /** Field => label, in editor order. */
    public const FIELDS = [
        'title' => 'Title',
        'h1' => 'Page heading (H1)',
        'excerpt' => 'Summary',
        'seo_title' => 'SEO title',
        'meta_description' => 'Meta description',
        'body_html' => 'Body',
        'faqs' => 'FAQs',
    ];

    public const PLACEHOLDERS = [
        '{city}' => 'City name',
        '{region}' => 'Province / region',
        '{country}' => 'Country',
        '{service}' => 'Primary service (the page\'s own, or the default below)',
        '{phone}' => 'Phone on the page, or the company phone',
        '{phone_link}' => 'tel: link for {phone}, for href="…"',
        '{email}' => 'Email on the page, or the company email',
        '{email_link}' => 'mailto: link for {email}, for href="…"',
        '{site_name}' => 'Site name',
        '{h1}' => 'Page heading (body only)',
        '{summary}' => 'Summary (body only)',
    ];

    private static ?array $cache = null;

    /** @var array<int, array> city rows by content id, per request */
    private static array $cities = [];

    public static function defaults(): array
    {
        return [
            'service' => 'Accounting',
            'title' => 'Accountant in {city}, {region}',
            'h1' => 'Accountant in {city}: {service}, Tax & Bookkeeping Services',
            'excerpt' => 'Looking for a trusted accountant in {city}? Verma Accounting provides bookkeeping, payroll, personal tax, corporate tax and business registration for small businesses, self-employed professionals, corporations and families in {city} and the surrounding area.',
            'seo_title' => 'Accountant in {city}, {region} | Tax & Bookkeeping',
            'meta_description' => 'Accountant in {city}, {region} for small businesses, self-employed and families. Bookkeeping, payroll, personal & corporate tax. Free consultation.',
            'body_html' => <<<'HTML'
<section class="common-section">
<div class="container">
<div class="row align-items-center">
<div class="col-lg-6 order-lg-last">
<div class="img-box scroll-animate-right"><img src="/images/rishab-verma-1.jpg" alt="Rishab Verma, accountant serving {city}" width="800" height="600" loading="lazy" decoding="async" /></div>
</div>
<div class="col-lg-6">
<h1 class="section-title">{h1}</h1>
<p>{summary}</p>
<div class="cta-buttons left">
<a class="cta-button primary" href="/contact">Get Free Consultation</a>
<a class="cta-button orange" href="{phone_link}"><i class="fas fa-phone"></i> {phone}</a>
</div>
</div>
</div>
</div>
</section>

<section class="cta-appointment-section">
<div class="container">
<div class="cta-appointment-content">
<div class="cta-text">
<span class="cta-prefix">Looking for help with</span>
<span id="typing-text">{service}?</span>
</div>
<div class="cta-action">
<a class="appointment-button" href="https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1" target="_blank" rel="noopener"><i class="fas fa-calendar-alt"></i> Book an Appointment</a>
</div>
</div>
</div>
</section>

<section class="common-section">
<div class="container">
<div class="row align-items-center">
<div class="col-lg-6">
<div class="img-box scroll-animate-left"><img src="/images/tax-discussion.jpg" alt="Tax review meeting in {city}" width="800" height="600" loading="lazy" decoding="async" /></div>
</div>
<div class="col-lg-6">
<h2 class="section-title">Your local accounting and tax partner in {city}, {region}</h2>
<p>Whether you run a small business, work for yourself or simply want your personal tax return done right, Verma Accounting gives {city} clients one dependable team for <strong>bookkeeping, payroll, tax preparation and financial reporting</strong>. Our certified accountants and tax professionals bring 7+ years of experience with Canadian accounting standards and CRA requirements.</p>
<p>We work online with secure cloud software such as QuickBooks, Xero and FreshBooks, so you can share documents, review reports and get answers from anywhere in {city} without driving to an office. You get plain-language advice, reminders before CRA deadlines and a clear quote before any work begins.</p>
</div>
</div>
</div>
</section>

<section class="other-services-section common-section">
<div class="container">
<div class="scroll-animate">
<h2 class="section-title text-center">Accounting services in {city}</h2>
<p class="text-center mb-5 mx-auto" style="max-width:720px">Everything business owners, self-employed professionals and households in {city} need to stay organised, compliant with the CRA and ready for tax season.</p>
</div>
<div class="row mt-4">
<div class="col-lg-4 col-md-6 mb-5">
<div class="services scroll-animate-scale">
<a class="services-thumbnail" href="/bookkeeping"><img class="thumb" src="/images/book-keeping.jpg" alt="Bookkeeping services in {city}" /><strong class="services-title">Bookkeeping in {city}</strong></a>
<p class="services-description">Monthly bank and credit card reconciliations, categorised expenses and clear reports, so your books are accurate and ready for tax time.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-5">
<div class="services scroll-animate-scale">
<a class="services-thumbnail" href="/personal-tax"><img class="thumb" src="/images/personal-tax.jpg" alt="Personal tax preparation in {city}" /><strong class="services-title">Personal Tax Returns</strong></a>
<p class="services-description">Income tax preparation and e-filing for employees, families, retirees, newcomers and the self-employed, with every credit and deduction you qualify for.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-5">
<div class="services scroll-animate-scale">
<a class="services-thumbnail" href="/corporate-tax"><img class="thumb" src="/images/corporate-tax.jpg" alt="Corporate tax services in {city}" /><strong class="services-title">Corporate Tax (T2)</strong></a>
<p class="services-description">T2 corporate tax returns, year-end filings and tax planning for incorporated businesses in {city}, focused on protecting your profits.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-5">
<div class="services scroll-animate-scale">
<a class="services-thumbnail" href="/payroll"><img class="thumb" src="/images/payroll.jpg" alt="Payroll services in {city}" /><strong class="services-title">Payroll Services</strong></a>
<p class="services-description">Accurate pay runs, source deductions, CRA remittances and year-end T4 slips, so your employees are paid correctly and on time.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-5">
<div class="services scroll-animate-scale">
<a class="services-thumbnail" href="/accounting"><img class="thumb" src="/images/financial-accounting.jpg" alt="Financial accounting in {city}" /><strong class="services-title">Financial Accounting</strong></a>
<p class="services-description">Balance sheets, income statements and financial reports that show how your business is really doing and support loans and planning.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-5">
<div class="services scroll-animate-scale">
<a class="services-thumbnail" href="/business-registration"><img class="thumb" src="/images/business-registration.jpg" alt="Business registration in {city}" /><strong class="services-title">Business Registration</strong></a>
<p class="services-description">Sole proprietorship or incorporation, CRA business number, GST/HST and payroll accounts: we help {city} startups set up correctly from day one.</p>
</div>
</div>
</div>
</div>
</section>

<section class="common-section">
<div class="container">
<div class="scroll-animate">
<h2 class="section-title text-center">Who we help in {city}</h2>
<p class="text-center mb-5 mx-auto" style="max-width:720px">Our {city} clients range from first-time filers to growing companies. If you are one of the people below, we can take accounting and tax off your plate.</p>
</div>
<div class="row">
<div class="col-lg-4 col-md-6 mb-4">
<div class="feature-item scroll-animate">
<h3><i class="fas fa-store"></i> Small businesses &amp; startups</h3>
<p>Retail shops, restaurants, e-commerce stores, trades and service businesses in {city} that need reliable bookkeeping, payroll, GST/HST filing and year-end accounting.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-4">
<div class="feature-item scroll-animate">
<h3><i class="fas fa-user-tie"></i> Self-employed &amp; contractors</h3>
<p>Sole proprietors, freelancers, consultants, contractors and gig workers who want their self-employment income, expenses and HST handled correctly.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-4">
<div class="feature-item scroll-animate">
<h3><i class="fas fa-building"></i> Incorporated businesses</h3>
<p>Owner-managed corporations in {city} that need T2 corporate tax returns, financial statements, payroll and advice on salary versus dividends.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-4">
<div class="feature-item scroll-animate">
<h3><i class="fas fa-briefcase-medical"></i> Professionals</h3>
<p>Medical and health professionals, real estate agents, lawyers, engineers and IT consultants who want tax-efficient planning around a busy practice.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-4">
<div class="feature-item scroll-animate">
<h3><i class="fas fa-users"></i> Individuals, families &amp; retirees</h3>
<p>Employees, families, students, seniors and landlords with rental income who want an accurate personal tax return and every benefit and credit they are owed.</p>
</div>
</div>
<div class="col-lg-4 col-md-6 mb-4">
<div class="feature-item scroll-animate">
<h3><i class="fas fa-globe-americas"></i> Newcomers to Canada</h3>
<p>New immigrants, international students and work-permit holders in {city} filing their first Canadian tax return or registering a new business.</p>
</div>
</div>
</div>
</div>
</section>

<section class="common-section">
<div class="container">
<div class="row align-items-center">
<div class="col-lg-6 order-lg-last">
<div class="img-box scroll-animate-right"><img src="/images/happy-client.jpg" alt="Client in {city}" width="800" height="600" loading="lazy" decoding="async" /></div>
</div>
<div class="col-lg-6">
<h2 class="section-title">Why {city} clients choose Verma Accounting</h2>
<ul class="ul-list">
<li><strong>Certified, experienced team:</strong> 7+ years of Canadian accounting, bookkeeping and tax experience.</li>
<li><strong>One accountant all year:</strong> bookkeeping, payroll and tax handled together, so nothing falls through the cracks at year-end.</li>
<li><strong>CRA-ready records:</strong> organised, documented books that make filing simple and give you peace of mind if the CRA has questions.</li>
<li><strong>Maximum refunds, minimum tax:</strong> we look for every deduction and credit you are entitled to.</li>
<li><strong>Online and flexible:</strong> secure document sharing and phone or video meetings that fit your schedule in {city}.</li>
<li><strong>Clear, upfront pricing:</strong> a free consultation and a clear quote before any work begins.</li>
</ul>
</div>
</div>
</div>
</section>

<section class="common-section text-center">
<div class="container">
<h2 class="section-title mb-5">How to get started</h2>
<div class="service-features">
<div class="feature-item">
<h3>1. Book a free consultation</h3>
<p>Call <a href="{phone_link}">{phone}</a> or <a href="/contact">send us a message</a> and tell us about your business or tax situation in {city}.</p>
</div>
<div class="feature-item">
<h3>2. Share your documents securely</h3>
<p>Upload receipts, slips and statements online through our <a href="/submit-documents">secure document portal</a>. No office visit needed.</p>
</div>
<div class="feature-item">
<h3>3. We file and keep you on track</h3>
<p>We prepare and file your returns, keep your books current and remind you before every CRA deadline.</p>
</div>
</div>
</div>
</section>

{{service_areas_nearby}}

<section class="contact-form-section">
<div class="container">
<div class="row align-items-center">
<div class="col-lg-6">
<div class="contact-form-content scroll-animate-left">
<h2>Talk to an accountant serving {city}</h2>
<p>Get expert help with bookkeeping, payroll, personal tax and corporate tax in {city}, {region}. Reach out today for a free, no-obligation consultation. See all <a href="/service-area">service areas</a> we cover.</p>
<div class="contact-info">
<div class="contact-item">
<i class="fas fa-phone"></i>
<div>
<h3>Call Us</h3>
<a href="{phone_link}">{phone}</a>
</div>
</div>
<div class="contact-item">
<i class="fas fa-envelope"></i>
<div>
<h3>Email Us</h3>
<a href="{email_link}">{email}</a>
</div>
</div>
</div>
</div>
</div>
<div class="col-lg-6">
<h3>We are One Message Away!</h3>
{{contact_form}}
</div>
</div>
</div>
</section>
HTML,
            'faqs' => <<<'TEXT'
How do I find a good accountant in {city}?
Look for an accountant who knows Canadian tax rules, works with businesses and individuals like you, and explains things clearly. Verma Accounting serves {city} clients online, offers a free consultation and gives you a clear quote before any work begins.

Do you prepare personal tax returns for {city} residents?
Yes. We prepare and e-file personal income tax returns for employees, families, students, retirees, newcomers and self-employed people in {city}, and we make sure you claim every credit and deduction you qualify for.

Can you handle bookkeeping and payroll for my small business in {city}?
Yes. We offer monthly bookkeeping, bank reconciliations, payroll processing, source deductions, CRA remittances and T4 slips for small businesses and startups in {city}.

Do you file corporate tax (T2) returns for {city} corporations?
Yes. We prepare T2 corporate tax returns, year-end financial statements and tax planning for incorporated businesses in {city}, including advice on paying yourself through salary or dividends.

Do I need to visit your office?
No. We work with {city} clients online. You can upload documents securely and meet with us by phone or video call at a time that suits you.

How much does an accountant cost in {city}?
It depends on the services you need and the volume of transactions. Book a free consultation and we will review your situation and give you a clear, upfront quote.
TEXT,
        ];
    }

    /**
     * Default FAQs for a city: blocks separated by a blank line, question on the first line, answer below.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function faqs(array $city): array
    {
        $out = [];
        foreach (preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", (string) (self::get()['faqs'] ?? '')))) ?: [] as $block) {
            $lines = explode("\n", trim($block), 2);
            $question = self::fillText(trim($lines[0]), $city);
            $answer = self::fillText(trim($lines[1] ?? ''), $city);
            if ($question !== '' && $answer !== '') {
                $out[] = ['question' => $question, 'answer' => $answer];
            }
        }
        return $out;
    }

    private static function hasFaqs(array $content): bool
    {
        $faqs = $content['faqs'] ?? json_decode((string) ($content['faqs_json'] ?? ''), true);
        foreach (is_array($faqs) ? $faqs : [] as $faq) {
            if (trim((string) ($faq['question'] ?? '')) !== '' && trim((string) ($faq['answer'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    public static function get(): array
    {
        if (self::$cache === null) {
            $stored = [];
            try {
                $stored = (new SettingsRepository())->get(self::SETTING_KEY, []);
            } catch (Throwable) {
                $stored = [];
            }
            self::$cache = array_merge(self::defaults(), is_array($stored) ? array_intersect_key($stored, self::defaults()) : []);
        }
        return self::$cache;
    }

    /** @return list<string> errors */
    public static function save(array $input): array
    {
        $next = self::get();
        foreach (array_keys(self::defaults()) as $key) {
            if (array_key_exists($key, $input)) {
                $next[$key] = trim(str_replace("\r\n", "\n", (string) $input[$key]));
            }
        }
        $errors = [];
        if ($next['title'] === '' || !str_contains($next['title'], '{city}')) {
            $errors[] = 'The default title must include {city} so every city page has its own title.';
        }
        foreach (['title', 'h1', 'seo_title'] as $key) {
            if (mb_strlen($next[$key]) > 500) {
                $errors[] = self::FIELDS[$key] . ' is too long.';
            }
        }
        if (preg_match('/<\?(php|=)?/i', $next['body_html'])) {
            $errors[] = 'PHP code is not allowed in the default body.';
        }
        if (mb_strlen($next['faqs']) > 20000) {
            $errors[] = 'The default FAQs are too long.';
        }
        if ($errors) {
            return $errors;
        }
        $next['body_html'] = CmsHtml::sanitize($next['body_html']);
        $next['faqs'] = strip_tags($next['faqs']);
        (new SettingsRepository())->set(self::SETTING_KEY, $next);
        self::$cache = null;
        CmsCache::flush();
        ActivityLog::record('cms.city_defaults_saved', 'cms_city_defaults', null, []);
        return [];
    }

    /** Forget cached settings and city rows (after saves in the same request, e.g. tests). */
    public static function reset(): void
    {
        self::$cache = null;
        self::$cities = [];
    }

    private static function city(array $content): array
    {
        $id = (int) ($content['id'] ?? 0);
        if ($id <= 0) {
            return [];
        }
        return self::$cities[$id] ??= (new CmsContentRepository())->city($id);
    }

    /** @return array<string, string> placeholder => plain value */
    public static function vars(array $city): array
    {
        $config = self::get();
        $phone = trim((string) ($city['phone'] ?? ''));
        if ($phone === '') {
            $phone = CmsCompany::phone();
        }
        $phoneLink = CmsCompany::phoneHref();
        if ($phone !== '') {
            $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';
            if ($digits !== '') {
                $phoneLink = 'tel:' . $digits;
            }
        }
        // Never leave href="{phone_link}" unresolved (browsers treat it as /service-area/{phone_link}).
        if ($phoneLink === '') {
            $phoneLink = '/contact';
        }
        $email = trim((string) ($city['email'] ?? ''));
        if ($email === '') {
            $email = CmsCompany::email();
        }
        $emailLink = $email !== '' ? 'mailto:' . $email : '/contact';
        return [
            '{city}' => (string) ($city['city'] ?? ''),
            '{region}' => (string) ($city['region'] ?? ''),
            '{country}' => (string) ($city['country'] ?? ''),
            '{service}' => trim((string) ($city['primary_service'] ?? '')) ?: (string) $config['service'],
            '{phone}' => $phone,
            '{phone_link}' => $phoneLink,
            '{email}' => $email,
            '{email_link}' => $emailLink,
            '{site_name}' => (string) CmsSiteConfig::value('site_name'),
        ];
    }

    /**
     * One default field filled in for a city. Body values are HTML-escaped; empty separators are tidied.
     * In the body, {h1} and {summary} are the page's heading and summary ($page overrides the defaults).
     *
     * @param array{h1?: string, summary?: string} $page
     */
    public static function fill(string $field, array $city, array $page = []): string
    {
        $template = (string) (self::get()[$field] ?? '');
        if ($template === '') {
            return '';
        }
        if ($field === 'faqs') {
            return '';
        }
        if ($field === 'body_html') {
            $vars = self::vars($city);
            $vars['{h1}'] = $page['h1'] ?? (self::fill('h1', $city) ?: self::fill('title', $city));
            $vars['{summary}'] = $page['summary'] ?? self::fill('excerpt', $city);
            $html = strtr(self::dropEmpty($template, $vars), array_map(static fn ($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $vars));
            return (string) preg_replace('#<(p|h1)(\s[^>]*)?>\s*</\1>\s*#', '', $html);
        }
        return self::fillText($template, $city);
    }

    private static function fillText(string $template, array $city): string
    {
        $vars = self::vars($city);
        $text = strtr(self::dropEmpty($template, $vars), $vars);
        $text = (string) preg_replace(['/\s*,\s*(?=,|$|\s[|\-–]|\))/u', '/\(\s*\)/', '/\s{2,}/'], ['', '', ' '], $text);
        return trim($text, " ,");
    }

    /** Remove placeholders with no value together with the comma that joins them (", {region}"). */
    private static function dropEmpty(string $template, array $vars): string
    {
        foreach ($vars as $placeholder => $value) {
            if ($value === '') {
                $template = (string) preg_replace('/\s*,\s*' . preg_quote($placeholder, '/') . '|' . preg_quote($placeholder, '/') . '\s*,\s*/u', '', $template);
                // Also strip bare empty tokens so href="{phone_link}" cannot survive into HTML.
                $template = str_replace($placeholder, '', $template);
            }
        }
        return $template;
    }

    /**
     * Fields whose default is not used because the page has its own text for the field they follow:
     * the H1 and SEO title follow a custom title, the meta description follows a custom summary.
     */
    private static function follows(string $field, array $content): bool
    {
        $source = match ($field) {
            'h1', 'seo_title' => 'title',
            'meta_description' => 'excerpt',
            default => null,
        };
        if ($source === null || trim((string) ($content[$source] ?? '')) === '') {
            return false;
        }
        return empty($content['city_defaults_applied']) || !in_array($source, (array) ($content['city_defaults'] ?? []), true);
    }

    public static function bodyIsEmpty(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html, '<img><iframe><video><embed>'), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{a0}") === '';
    }

    /**
     * Fields of a city page that would use the default.
     *
     * @return list<string>
     */
    public static function defaultedFields(array $content, ?array $seo = null): array
    {
        if (($content['type'] ?? '') !== 'city') {
            return [];
        }
        $out = [];
        foreach (['title', 'h1', 'excerpt'] as $field) {
            if (trim((string) ($content[$field] ?? '')) === '' && !self::follows($field, $content)) {
                $out[] = $field;
            }
        }
        if ($seo !== null) {
            foreach (['seo_title', 'meta_description'] as $field) {
                if (trim((string) ($seo[$field] ?? '')) === '' && !self::follows($field, $content)) {
                    $out[] = $field;
                }
            }
        }
        if (self::bodyIsEmpty((string) ($content['body_html'] ?? ''))) {
            $out[] = 'body_html';
        }
        if (!self::hasFaqs($content) && trim((string) self::get()['faqs']) !== '') {
            $out[] = 'faqs';
        }
        return $out;
    }

    /**
     * Fill the empty fields of a city page from the defaults. Other types are returned unchanged.
     * Adds 'city_defaults' => list of fields that were filled.
     */
    public static function apply(array $content, ?array $city = null): array
    {
        if (($content['type'] ?? '') !== 'city' || !empty($content['city_defaults_applied'])) {
            return $content;
        }
        $city ??= self::city($content);
        $used = [];
        $own = $content;
        foreach (['title', 'h1', 'excerpt'] as $field) {
            if (trim((string) ($content[$field] ?? '')) === '' && !self::follows($field, $own)) {
                $value = self::fill($field, $city);
                if ($value !== '') {
                    $content[$field] = $value;
                    $used[] = $field;
                }
            }
        }
        if (trim((string) ($content['title'] ?? '')) === '') {
            $content['title'] = (string) ($city['city'] ?? '') ?: 'City page';
        }
        if (self::bodyIsEmpty((string) ($content['body_html'] ?? ''))) {
            $body = self::fill('body_html', $city, [
                'h1' => trim((string) ($content['h1'] ?? '')) ?: (string) $content['title'],
                'summary' => trim((string) ($content['excerpt'] ?? '')),
            ]);
            if ($body !== '') {
                $content['body_html'] = $body;
                $used[] = 'body_html';
            }
        }
        if (!self::hasFaqs($content)) {
            $faqs = self::faqs($city);
            if ($faqs !== []) {
                $content['faqs'] = $faqs;
                $used[] = 'faqs';
            }
        }
        $content['city_defaults'] = $used;
        $content['city_defaults_applied'] = true;
        return $content;
    }

    /** Fill an empty SEO title and meta description of a city page. */
    public static function applySeo(array $content, array $seo, ?array $city = null): array
    {
        if (($content['type'] ?? '') !== 'city') {
            return $seo;
        }
        $city ??= self::city($content);
        foreach (['seo_title', 'meta_description'] as $field) {
            if (trim((string) ($seo[$field] ?? '')) === '' && !self::follows($field, $content)) {
                $seo[$field] = self::fill($field, $city);
            }
        }
        return $seo;
    }

    /** Admin label for a city page: its own title, or the default title for its city. */
    public static function label(array $content): string
    {
        $title = trim((string) ($content['title'] ?? ''));
        if ($title !== '' || ($content['type'] ?? '') !== 'city') {
            return $title;
        }
        return (string) self::apply($content)['title'];
    }

    /** How many city pages use the default for each field. @return array{total: int, fields: array<string, int>} */
    public static function usage(): array
    {
        $repo = new CmsContentRepository();
        $counts = array_fill_keys(array_keys(self::FIELDS), 0);
        $total = 0;
        foreach ($repo->search(['type' => 'city'], 1000) as $row) {
            if (in_array($row['status'], ['archived'], true)) {
                continue;
            }
            $total++;
            foreach (self::defaultedFields($row, $repo->seo((int) $row['id'])) as $field) {
                $counts[$field]++;
            }
        }
        return ['total' => $total, 'fields' => $counts];
    }
}
