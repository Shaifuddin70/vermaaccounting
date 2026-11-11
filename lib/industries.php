<?php

declare(strict_types=1);

/**
 * Industry SEO catalog: hub list, dedicated /industries/{slug} pages, and location landings.
 */
final class IndustryCatalog
{
    public const HUB_PATH = '/industries';

    /**
     * Full hub list. Entries with a slug get a dedicated page; others link to a related service.
     *
     * @return list<array{name: string, summary: string, slug?: string, service?: string, priority?: int, keyword?: string}>
     */
    public static function hubEntries(): array
    {
        return [
            ['name' => 'Trucking and transportation', 'summary' => 'We track fuel, truck costs, driver pay, and tax filings for carriers and owner-operators.', 'slug' => 'trucking-transportation', 'priority' => 1, 'keyword' => 'Trucking accountant in Ottawa', 'image' => '/images/industries/trucking.jpg', 'image_alt' => 'Semi truck on the highway'],
            ['name' => 'Real estate investors and landlords', 'summary' => 'We keep rent, repairs, and costs by property so tax time is easier.', 'slug' => 'real-estate-landlords', 'priority' => 2, 'keyword' => 'Rental property accountant in Ontario', 'image' => '/images/industries/landlords.jpg', 'image_alt' => 'Residential house exterior'],
            ['name' => 'Construction and contractors', 'summary' => 'We track jobs, materials, subcontractor pay, and HST so you know which projects make money.', 'slug' => 'construction-contractors', 'priority' => 3, 'keyword' => 'Construction bookkeeping services', 'image' => '/images/industries/construction.jpg', 'image_alt' => 'Construction workers on a job site'],
            ['name' => 'Restaurants and food services', 'summary' => 'We handle daily sales, tips, food costs, and staff pay for restaurants and cafés.', 'slug' => 'restaurants-food-services', 'priority' => 4, 'keyword' => 'Restaurant accountant in Ottawa', 'image' => '/images/industries/restaurants.jpg', 'image_alt' => 'Restaurant dining room ready for guests'],
            ['name' => 'Cleaning businesses', 'summary' => 'We set up invoices, track supplies, and keep pay records clear as you grow.', 'slug' => 'cleaning-businesses', 'priority' => 5, 'keyword' => 'Cleaning business bookkeeping', 'image' => '/images/industries/cleaning.jpg', 'image_alt' => 'Cleaning supplies and mop on a floor'],
            ['name' => 'Self-employed and gig workers', 'summary' => 'We help drivers and freelancers track income, claim costs, and file tax returns.', 'slug' => 'self-employed-gig-workers', 'priority' => 6, 'keyword' => 'Uber driver tax accountant', 'image' => '/images/industries/gig-workers.jpg', 'image_alt' => 'Driver behind the wheel of a car'],
            ['name' => 'Professional services', 'summary' => 'We help consultants and offices with billing, expenses, and small business tax.', 'slug' => 'professional-services', 'priority' => 7, 'keyword' => 'Small business accounting services', 'image' => '/images/industries/professional.jpg', 'image_alt' => 'Professionals meeting in an office'],
            ['name' => 'Retail and online stores', 'summary' => 'We match sales, fees, and inventory for shops and online sellers.', 'slug' => 'retail-ecommerce', 'priority' => 8, 'keyword' => 'Retail bookkeeping services', 'image' => '/images/industries/retail.jpg', 'image_alt' => 'Clothing racks in a retail shop'],
            ['name' => 'Startups and corporations', 'summary' => 'We help new companies set up books, payroll, and corporate tax filings.', 'slug' => 'startups-corporations', 'priority' => 9, 'keyword' => 'Corporate tax accountant in Ottawa', 'image' => '/images/industries/startups.jpg', 'image_alt' => 'Startup team working together at a table'],
            ['name' => 'Realtors and real estate agents', 'summary' => 'We track commissions, fees, and costs so agent tax filings stay clear.', 'slug' => 'real-estate-professionals', 'priority' => 10, 'keyword' => 'Realtor bookkeeping and tax services', 'image' => '/images/industries/realtors.jpg', 'image_alt' => 'Homes on a residential street'],
            ['name' => 'Trades and home services', 'summary' => 'See what each service call earns after parts, labour, and travel.', 'slug' => 'trades-and-home-services', 'priority' => 11, 'keyword' => 'Trades bookkeeping Ottawa', 'image' => '/images/industries/trades.jpg', 'image_alt' => 'Technician working with tools'],
            ['name' => 'Healthcare and clinics', 'summary' => 'Reconcile clinic billings, insurer deposits, and practitioner splits.', 'slug' => 'healthcare-and-clinics', 'priority' => 12, 'keyword' => 'Clinic bookkeeping Ottawa', 'image' => '/images/industries/healthcare.jpg', 'image_alt' => 'Clinic hallway and medical office'],
            ['name' => 'Manufacturing and wholesale', 'summary' => 'Track inventory from purchase to production to sale.', 'slug' => 'manufacturing-and-wholesale', 'priority' => 13, 'keyword' => 'Manufacturing accountant Ontario', 'image' => '/images/industries/manufacturing.jpg', 'image_alt' => 'Factory floor with industrial equipment'],
            ['name' => 'Non-profits and associations', 'summary' => 'Organize grants, dues, and program spending for board reports.', 'slug' => 'non-profits-and-associations', 'priority' => 14, 'keyword' => 'Non-profit bookkeeping Ottawa', 'image' => '/images/industries/nonprofit.jpg', 'image_alt' => 'Volunteers packing donations together'],
            ['name' => 'Individuals and families', 'summary' => 'Document checklists for Ottawa household tax appointments.', 'slug' => 'individuals-and-families', 'priority' => 15, 'keyword' => 'Personal tax accountant Ottawa families', 'image' => '/images/industries/personal-tax.jpg', 'image_alt' => 'Person reviewing tax papers at a desk'],
        ];
    }

    /** @return list<array{name: string, summary: string, slug: string, priority: int, keyword: string}> */
    public static function dedicated(): array
    {
        $out = [];
        foreach (self::hubEntries() as $row) {
            if (!empty($row['slug'])) {
                $out[] = $row;
            }
        }
        usort($out, static fn (array $a, array $b): int => ((int) ($a['priority'] ?? 99)) <=> ((int) ($b['priority'] ?? 99)));
        return $out;
    }

    public static function find(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        foreach (self::dedicated() as $row) {
            if ($row['slug'] === $slug) {
                return self::pageContent($slug) + $row;
            }
        }
        return null;
    }

    public static function path(string $slug): string
    {
        return self::HUB_PATH . '/' . ltrim($slug, '/');
    }

    /**
     * Ottawa / Ontario location SEO landings.
     *
     * @return list<array{city: string, slug: string, path: string, h1: string, title: string, description: string, keyword: string, intro: string, bullets: list<string>, related: list<array{label: string, url: string}>}>
     */
    public static function locations(): array
    {
        return [
            [
                'city' => 'ottawa',
                'slug' => 'small-business-accountant',
                'path' => '/ottawa/small-business-accountant',
                'h1' => 'Small Business Accountant in Ottawa',
                'title' => 'Small Business Accountant in Ottawa | Verma Accounting',
                'description' => 'Ottawa small business accountant for bookkeeping, GST/HST, payroll, and tax filing.',
                'hub_blurb' => 'Bookkeeping, tax, and payroll help for Ottawa small businesses.',
                'image' => '/images/industries/small-business.jpg',
                'image_alt' => 'Small business owners reviewing plans together',
                'keyword' => 'Small business accountant in Ottawa',
                'intro' => 'Looking for a small business accountant in Ottawa? We help local owners keep their books clear, meet CRA rules, and understand their numbers.',
                'bullets' => [
                    'Monthly bookkeeping and bank checks',
                    'GST/HST and tax filing support',
                    'Payroll when you hire staff',
                    'Plain advice for Ottawa and Ontario owners',
                ],
                'related' => [
                    ['label' => 'Accounting services', 'url' => '/accounting'],
                    ['label' => 'Bookkeeping', 'url' => '/bookkeeping'],
                    ['label' => 'Industries we serve', 'url' => self::HUB_PATH],
                    ['label' => 'Ottawa service area', 'url' => '/service-area/ottawa'],
                ],
            ],
            [
                'city' => 'ottawa',
                'slug' => 'bookkeeping-services',
                'path' => '/ottawa/bookkeeping-services',
                'h1' => 'Bookkeeping Services in Ottawa',
                'title' => 'Bookkeeping Services in Ottawa | Verma Accounting',
                'description' => 'Ottawa bookkeeping services with monthly bank checks and records ready for tax season.',
                'hub_blurb' => 'Monthly books and bank checks for Ottawa businesses.',
                'image' => '/images/industries/bookkeeping.jpg',
                'image_alt' => 'Bookkeeper working with a calculator and ledger',
                'keyword' => 'Bookkeeping services in Ottawa',
                'intro' => 'Our Ottawa bookkeeping services sort your sales and costs each month. You always know where the business stands before tax time.',
                'bullets' => [
                    'Cloud books with QuickBooks, Xero, or FreshBooks',
                    'Sales and bank accounts checked every month',
                    'Simple month-end reports you can share',
                    'Help for shops, trades, restaurants, and offices',
                ],
                'related' => [
                    ['label' => 'Bookkeeping services', 'url' => '/bookkeeping'],
                    ['label' => 'Cleaning business bookkeeping', 'url' => self::path('cleaning-businesses')],
                    ['label' => 'Retail & e-commerce', 'url' => self::path('retail-ecommerce')],
                    ['label' => 'Contact us', 'url' => '/contact'],
                ],
            ],
            [
                'city' => 'ottawa',
                'slug' => 'corporate-tax-accountant',
                'path' => '/ottawa/corporate-tax-accountant',
                'h1' => 'Corporate Tax Accountant in Ottawa',
                'title' => 'Corporate Tax Accountant in Ottawa | Verma Accounting',
                'description' => 'Ottawa corporate tax accountant for T2 filings and year-end planning for Ontario companies.',
                'hub_blurb' => 'T2 filings and year-end tax help for Ottawa corporations.',
                'image' => '/images/industries/corporate-tax.jpg',
                'image_alt' => 'Business person reviewing corporate tax documents',
                'keyword' => 'Corporate tax accountant in Ottawa',
                'intro' => 'Need a corporate tax accountant in Ottawa? We prepare T2 returns and help you plan payments so year-end is less stressful.',
                'bullets' => [
                    'T2 prep and e-filing support',
                    'Ontario corporate tax help',
                    'Year-end records ready for filing',
                    'Support for new and growing companies',
                ],
                'related' => [
                    ['label' => 'Corporate tax services', 'url' => '/corporate-tax'],
                    ['label' => 'Startups & corporations', 'url' => self::path('startups-corporations')],
                    ['label' => 'Business registration', 'url' => '/business-registration'],
                    ['label' => 'Book an appointment', 'url' => '/contact'],
                ],
            ],
            [
                'city' => 'ottawa',
                'slug' => 'gst-hst-filing',
                'path' => '/ottawa/gst-hst-filing',
                'h1' => 'GST/HST Filing Services in Ottawa',
                'title' => 'GST/HST Filing Services in Ottawa | Verma Accounting',
                'description' => 'GST/HST filing services in Ottawa for accurate returns and on-time CRA payments.',
                'hub_blurb' => 'GST/HST returns and payments for Ottawa businesses.',
                'image' => '/images/industries/gst-hst.jpg',
                'image_alt' => 'Person filling out a tax form with a pen',
                'keyword' => 'GST/HST filing services in Ottawa',
                'intro' => 'We prepare and file GST/HST returns for Ottawa businesses. We track credits and due dates so you do not miss a CRA deadline.',
                'bullets' => [
                    'Monthly, quarterly, or annual returns',
                    'Checks on credits and invoices',
                    'Help when you need to register',
                    'Works with your bookkeeping and tax files',
                ],
                'related' => [
                    ['label' => 'Accounting services', 'url' => '/accounting'],
                    ['label' => 'Bookkeeping', 'url' => '/bookkeeping'],
                    ['label' => 'Trucking accounting', 'url' => self::path('trucking-transportation')],
                    ['label' => 'Contact', 'url' => '/contact'],
                ],
            ],
            [
                'city' => 'ontario',
                'slug' => 'payroll-services',
                'path' => '/ontario/payroll-services',
                'h1' => 'Payroll Services for Ontario Businesses',
                'title' => 'Payroll Services for Ontario Businesses | Verma Accounting',
                'description' => 'Payroll services for Ontario businesses: pay runs, remittances, ROEs, and T4s.',
                'hub_blurb' => 'Pay runs, CRA remittances, ROEs, and T4s for Ontario employers.',
                'image' => '/images/industries/payroll.jpg',
                'image_alt' => 'Payroll paperwork and calculator on a desk',
                'keyword' => 'Payroll services for Ontario businesses',
                'intro' => 'Ontario employers need payroll that is on time and correct. We run pay, send CRA remittances, prepare ROEs, and handle year-end T4s.',
                'bullets' => [
                    'Ontario pay runs and remittances',
                    'New hires and Records of Employment',
                    'T4 and T4 Summary prep',
                    'Help for restaurants, construction, cleaning, and offices',
                ],
                'related' => [
                    ['label' => 'Payroll services', 'url' => '/payroll'],
                    ['label' => 'Construction bookkeeping', 'url' => self::path('construction-contractors')],
                    ['label' => 'Restaurants', 'url' => self::path('restaurants-food-services')],
                    ['label' => 'Service areas', 'url' => '/service-area'],
                ],
            ],
        ];
    }

    public static function findLocation(string $city, string $slug): ?array
    {
        $city = strtolower(trim($city));
        $slug = strtolower(trim($slug));
        foreach (self::locations() as $row) {
            if ($row['city'] === $city && $row['slug'] === $slug) {
                return $row;
            }
        }
        return null;
    }

    /** @return list<string> */
    public static function sitemapPaths(): array
    {
        $paths = [self::HUB_PATH];
        foreach (self::dedicated() as $row) {
            $paths[] = self::path($row['slug']);
        }
        foreach (self::locations() as $row) {
            $paths[] = $row['path'];
        }
        return $paths;
    }

    /**
     * @return array{h1: string, intro: string, sections: list<array{heading: string, body: string}>, services: list<array{label: string, url: string}>, faq: list<array{q: string, a: string}>}
     */
    public static function pageContent(string $slug): array
    {
        $sharedServices = [
            ['label' => 'Bookkeeping', 'url' => '/bookkeeping'],
            ['label' => 'Accounting', 'url' => '/accounting'],
            ['label' => 'Payroll', 'url' => '/payroll'],
            ['label' => 'Corporate tax', 'url' => '/corporate-tax'],
            ['label' => 'Personal tax', 'url' => '/personal-tax'],
            ['label' => 'Contact', 'url' => '/contact'],
        ];

        $pages = [
            'trucking-transportation' => [
                'h1' => 'Accounting for Trucking Companies',
                'intro' => 'Trucking books move fast—fuel, repairs, driver pay, and CRA payments. We help Ottawa and Ontario carriers keep it all clear.',
                'sections' => [
                    ['heading' => 'What trucking companies need', 'body' => 'Fuel, repairs, lease payments, driver pay, and GST/HST change every week. We set up simple categories and monthly reports so you can see how each truck is doing.'],
                    ['heading' => 'Owner-operators and fleets', 'body' => 'One truck or many, we help with personal or company books, driver payroll, and tax filing so year-end is not a rush.'],
                    ['heading' => 'Help in Ottawa and Ontario', 'body' => 'If you need a trucking accountant in Ottawa, you get a local team that knows Ontario HST and the records banks and brokers often ask for.'],
                ],
                'faq' => [
                    ['q' => 'Do you work with owner-operators?', 'a' => 'Yes. We help track costs, keep personal draws separate, and get ready for tax filing.'],
                    ['q' => 'Can you handle GST/HST for a carrier?', 'a' => 'Yes. We prepare GST/HST returns and keep the backup with your books.'],
                ],
            ],
            'real-estate-landlords' => [
                'h1' => 'Accounting for Real Estate Investors and Landlords',
                'intro' => 'Rental books should make rent, mortgages, and repairs easy to explain at tax time. We help Ontario landlords keep each property clear.',
                'sections' => [
                    ['heading' => 'Rental property bookkeeping', 'body' => 'We track rent, deposits, repairs, property tax, insurance, and mortgage interest by property. That makes T776 or company reports much easier.'],
                    ['heading' => 'More than one property', 'body' => 'As you add units, we keep each property separate, check the bank accounts, and give you simple monthly summaries.'],
                    ['heading' => 'Ontario landlord support', 'body' => 'From Ottawa rentals to homes across Ontario, we connect your books to personal or corporate tax so nothing gets lost.'],
                ],
                'faq' => [
                    ['q' => 'Do you help with T776?', 'a' => 'We organize rental income and costs so T776 is accurate. Filing can be part of your personal or corporate tax work with us.'],
                    ['q' => 'Can you handle short-term rentals?', 'a' => 'Yes. We set up categories for platform payouts, cleaning, supplies, and other short-term costs.'],
                ],
            ],
            'construction-contractors' => [
                'h1' => 'Accounting for Construction Companies',
                'intro' => 'Construction books should show which jobs make money, plus holdbacks and HST. We help contractors keep project records that support bids, payroll, and tax.',
                'sections' => [
                    ['heading' => 'Job costs you can trust', 'body' => 'Labour, materials, subcontractors, and equipment get tied to each job so you know which projects make money before year-end.'],
                    ['heading' => 'HST and progress invoices', 'body' => 'We track progress bills, holdbacks, and tax credits so your GST/HST returns match the work on site.'],
                    ['heading' => 'Trades and general contractors', 'body' => 'Home renovations or larger builds—we grow the books with your crew and keep CRA filings on track.'],
                ],
                'faq' => [
                    ['q' => 'Do you support subcontractors?', 'a' => 'Yes. We help keep employee and contractor pay records clear, including T5018 or payroll when needed.'],
                    ['q' => 'Can you work with my estimating software?', 'a' => 'Often yes. We can bring job codes into QuickBooks or Xero so field and office stay aligned.'],
                ],
            ],
            'restaurants-food-services' => [
                'h1' => 'Accounting for Restaurants',
                'intro' => 'Restaurants deal with cash, cards, delivery apps, tips, and food costs every day. We turn that into clear books for Ottawa and Ontario food businesses.',
                'sections' => [
                    ['heading' => 'Daily sales and tips', 'body' => 'We match POS, delivery apps, and tip pools so sales and payroll stay in sync.'],
                    ['heading' => 'Food costs', 'body' => 'Purchases and spoilage are tracked so you can watch food costs, not just the bank balance.'],
                    ['heading' => 'Payroll for restaurants', 'body' => 'Shift work, tips, and CRA remittances are handled so servers and kitchen staff are paid correctly.'],
                ],
                'faq' => [
                    ['q' => 'Do you work with cafés and food trucks?', 'a' => 'Yes. Same sales, food cost, and HST help—scaled to your size.'],
                    ['q' => 'Can you match Uber Eats or DoorDash?', 'a' => 'Yes. We match payouts to orders and fees so delivery sales do not confuse your books.'],
                ],
            ],
            'cleaning-businesses' => [
                'h1' => 'Bookkeeping for Cleaning Businesses',
                'intro' => 'Cleaning companies grow with regular clients and steady crews. We keep invoices, pay, and supply costs tidy so you can grow without losing track.',
                'sections' => [
                    ['heading' => 'Repeat clients and invoices', 'body' => 'We set up customer invoices and route income so you can see cash coming in each month.'],
                    ['heading' => 'Contractors vs employees', 'body' => 'Pay type matters. We help you record payments the right way and add payroll when you hire staff.'],
                    ['heading' => 'Supplies and vehicle costs', 'body' => 'Cleaning supplies, mileage, and equipment are sorted for tax and for pricing your jobs.'],
                ],
                'faq' => [
                    ['q' => 'I only have a few clients—do I still need a bookkeeper?', 'a' => 'Even small cleaning businesses do better with monthly bank checks before tax season piles up.'],
                    ['q' => 'Can you invoice my commercial clients?', 'a' => 'Yes. We can set up invoicing in your cloud software and help keep unpaid bills in view.'],
                ],
            ],
            'self-employed-gig-workers' => [
                'h1' => 'Accounting for Self-Employed Drivers and Gig Workers',
                'intro' => 'Uber drivers, delivery partners, and freelancers need clear cost tracking and tax filing. We keep it simple.',
                'sections' => [
                    ['heading' => 'Gig income and costs', 'body' => 'Platform pay, mileage, phone, and vehicle costs are sorted so tax time is easier.'],
                    ['heading' => 'Personal tax help', 'body' => 'We connect your records to personal tax so self-employment income is reported clearly on your return.'],
                    ['heading' => 'When to register for HST', 'body' => 'We watch earning levels and help you register when you need to.'],
                ],
                'faq' => [
                    ['q' => 'Do you only work with drivers?', 'a' => 'Drivers are a big part of our work, but we also help freelancers and other self-employed people in Ontario.'],
                    ['q' => 'Can you help if I have a day job and side gigs?', 'a' => 'Yes. We keep job income and side income separate so both are reported correctly.'],
                ],
            ],
            'professional-services' => [
                'h1' => 'Small Business Accounting for Professional Services',
                'intro' => 'Consultants, agencies, and offices need books that fit retainers, invoices, and owner pay—not a retail store setup.',
                'sections' => [
                    ['heading' => 'Billing and unpaid invoices', 'body' => 'We keep invoices and deposits clear so you know who still owes you.'],
                    ['heading' => 'Owner pay and tax', 'body' => 'Salary, dividends, and draws are tracked. If you incorporate, we keep personal and company filing in mind.'],
                    ['heading' => 'Simple monthly reports', 'body' => 'Easy profit and cash summaries help you plan hiring, marketing, and tax payments.'],
                ],
                'faq' => [
                    ['q' => 'Do you work with incorporated professionals?', 'a' => 'Yes. We help both sole proprietors and professional corporations.'],
                    ['q' => 'Can you connect to other office tools?', 'a' => 'If your tools can export data, we can bring sales into your books on a regular schedule.'],
                ],
            ],
            'retail-ecommerce' => [
                'h1' => 'Retail Bookkeeping Services',
                'intro' => 'Shops and online sellers need books that match POS, Shopify, payouts, and inventory—not a messy spreadsheet.',
                'sections' => [
                    ['heading' => 'Matching every sales channel', 'body' => 'We match card processors, marketplaces, and store sales so revenue is complete and fees are easy to see.'],
                    ['heading' => 'Inventory and cost of goods', 'body' => 'Purchases and cost of goods are tracked so your margins make sense for pricing and tax.'],
                    ['heading' => 'Sales tax ready', 'body' => 'GST/HST collected and credits stay lined up with your filing dates.'],
                ],
                'faq' => [
                    ['q' => 'Do you support Shopify stores?', 'a' => 'Yes. We match Shopify payouts, fees, and refunds into your books.'],
                    ['q' => 'What about in-store POS?', 'a' => 'We match daily sales so cash and cards both balance.'],
                ],
            ],
            'startups-corporations' => [
                'h1' => 'Corporate Tax and Accounting for Startups',
                'intro' => 'New companies need books, payroll, and T2 filings set up early. We help Ottawa and Ontario startups start clean.',
                'sections' => [
                    ['heading' => 'From start-up to first filings', 'body' => 'After you register, we help set up bookkeeping, GST/HST when needed, and first-year corporate tax.'],
                    ['heading' => 'Ready for banks and partners', 'body' => 'Clear monthly reports make bank talks and partner updates easier.'],
                    ['heading' => 'When you hire', 'body' => 'We add payroll, remittances, and year-end T4s to the same set of books.'],
                ],
                'faq' => [
                    ['q' => 'Do you help before we incorporate?', 'a' => 'Yes. We can talk through structure, then handle books and tax after you register.'],
                    ['q' => 'Can you work with Ontario founders who are not in Ottawa?', 'a' => 'Yes. Most work is online, with video or phone reviews.'],
                ],
            ],
            'real-estate-professionals' => [
                'h1' => 'Realtor Bookkeeping and Tax Services',
                'intro' => 'Agents need books that track commissions, splits, fees, and GST/HST without slowing deals.',
                'sections' => [
                    ['heading' => 'Commission income done right', 'body' => 'Deposits, referral fees, desk fees, and team splits are sorted so your income is clear at tax time.'],
                    ['heading' => 'Vehicle, marketing, and home office', 'body' => 'We help you keep records for common realtor costs that CRA may ask about.'],
                    ['heading' => 'Personal or company', 'body' => 'Whether you work in your own name or through a company, we match the books to the right tax path.'],
                ],
                'faq' => [
                    ['q' => 'Do you work with teams and brokerages?', 'a' => 'Yes. We can help individual agents or teams with clear split tracking.'],
                    ['q' => 'Can you handle GST/HST on commissions?', 'a' => 'Yes. We track taxable sales and credits for registered realtors.'],
                ],
            ],
        ];

        $base = $pages[$slug] ?? [
            'h1' => 'Industry accounting services',
            'intro' => 'Accounting and bookkeeping for Ontario businesses in your field.',
            'sections' => [],
            'faq' => [],
        ];
        $base['services'] = $sharedServices;
        return $base;
    }

    /** HTML body for a dedicated industry CMS page. */
    public static function industryBodyHtml(string $slug): string
    {
        $page = self::pageContent($slug);
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<link rel="stylesheet" href="/css/industries.css">';
        $html .= '<div class="industry-cms container">';
        $html .= '<p class="industry-body">' . $e((string) $page['intro']) . '</p>';
        foreach ($page['sections'] as $section) {
            $html .= '<h2>' . $e((string) $section['heading']) . '</h2>';
            $html .= '<p class="industry-body">' . $e((string) $section['body']) . '</p>';
        }
        if (!empty($page['services'])) {
            $html .= '<h2>Related services</h2><ul class="industry-service-links">';
            foreach ($page['services'] as $svc) {
                $html .= '<li><a href="' . $e((string) $svc['url']) . '">' . $e((string) $svc['label']) . '</a></li>';
            }
            $html .= '<li><a href="' . $e(self::HUB_PATH) . '">All industries</a></li></ul>';
        }
        $html .= '<p class="industries-intro" style="margin-top:2rem"><a class="cta-button primary" href="/contact">Get a free consultation</a></p>';
        $html .= '</div>';
        return $html;
    }

    /** @param array{h1: string, intro: string, bullets: list<string>, related: list<array{label: string, url: string}>} $loc */
    public static function locationBodyHtml(array $loc): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<link rel="stylesheet" href="/css/industries.css">';
        $html .= '<div class="industry-cms container">';
        $html .= '<p class="industry-body">' . $e((string) $loc['intro']) . '</p>';
        $html .= '<h2>How we help</h2><ul class="ul-list industry-bullets">';
        foreach ($loc['bullets'] as $bullet) {
            $html .= '<li>' . $e((string) $bullet) . '</li>';
        }
        $html .= '</ul><h2>Explore next</h2><ul class="industry-service-links">';
        foreach ($loc['related'] as $link) {
            $html .= '<li><a href="' . $e((string) $link['url']) . '">' . $e((string) $link['label']) . '</a></li>';
        }
        $html .= '</ul>';
        $html .= '<p class="industries-intro" style="margin-top:2rem"><a class="cta-button primary" href="/contact">Get in touch</a></p>';
        $html .= '</div>';
        return $html;
    }

    /** Short body for related-industry pages that point to a main service. */
    public static function relatedBodyHtml(string $name, string $summary, string $serviceUrl): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<link rel="stylesheet" href="/css/industries.css">'
            . '<div class="industry-cms container">'
            . '<p class="industry-body">' . $e($summary) . '</p>'
            . '<p class="industry-body">We set up bookkeeping, payroll, and tax around how ' . $e($name) . ' actually work.</p>'
            . '<p><a class="cta-button primary" href="' . $e($serviceUrl) . '">See related service</a> '
            . '<a class="cta-button secondary" href="/contact">Get a free consultation</a></p>'
            . '</div>';
    }

    /**
     * Links for homepage / listings. Prefers live CMS industry pages, falls back to the catalog.
     *
     * @return list<array{name: string, path: string, image: string, image_alt: string, summary: string}>
     */
    public static function homepageLinks(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $catalogByPath = [];
        foreach (self::dedicated() as $row) {
            $catalogByPath[self::path((string) $row['slug'])] = $row;
        }

        if (class_exists('CmsContentRepository') && class_exists('CmsFolderRepository')) {
            try {
                $repo = new CmsContentRepository();
                $hub = $repo->findByPath(self::HUB_PATH);
                if ($hub && CmsContentRepository::isLive($hub)) {
                    $folder = (new CmsFolderRepository())->forLanding((int) $hub['id']);
                    if ($folder) {
                        $children = $repo->published('page', 100, 0, (int) $folder['id']);
                        usort(
                            $children,
                            static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
                                ?: strcasecmp((string) $a['title'], (string) $b['title'])
                        );
                        $out = [];
                        foreach ($children as $child) {
                            if ((int) ($child['sort_order'] ?? 0) >= 100) {
                                continue; // keep homepage list to primary industries
                            }
                            $path = (string) $child['path'];
                            $fallback = $catalogByPath[$path] ?? [];
                            $image = trim((string) ($child['featured_image_url'] ?? ''));
                            if ($image === '') {
                                $image = (string) ($fallback['image'] ?? '/images/calculation.jpg');
                            }
                            $out[] = [
                                'name' => (string) $child['title'],
                                'path' => $path,
                                'image' => $image,
                                'image_alt' => trim((string) ($child['featured_alt'] ?? '')) ?: (string) ($fallback['image_alt'] ?? $child['title']),
                                'summary' => trim((string) ($child['excerpt'] ?? '')) ?: (string) ($fallback['summary'] ?? ''),
                            ];
                            if (count($out) >= $limit) {
                                break;
                            }
                        }
                        if ($out !== []) {
                            return $out;
                        }
                    }
                }
            } catch (Throwable $e) {
                // Fall through to the static catalog.
            }
        }

        $out = [];
        foreach (self::dedicated() as $row) {
            $out[] = [
                'name' => (string) $row['name'],
                'path' => self::path((string) $row['slug']),
                'image' => (string) ($row['image'] ?? '/images/calculation.jpg'),
                'image_alt' => (string) ($row['image_alt'] ?? $row['name']),
                'summary' => (string) ($row['summary'] ?? ''),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public static function hubBodyHtml(): string
    {
        return <<<'HTML'
<div class="container">
  <div class="row align-items-center">
    <div class="col-lg-6">
      <div class="img-box">
        <img src="/images/calculation.jpg" alt="Accountant reviewing business records" width="640" height="420" loading="lazy">
      </div>
    </div>
    <div class="col-lg-6">
      <h2 class="section-title">Why industry matters</h2>
      <p>A restaurant does not run like a trucking company. A landlord does not get paid like a realtor. When we know your line of work, we set up your books the right way from the start.</p>
      <p>That means fewer surprises at tax time, cleaner payroll, and reports you can actually use. If you do not see your field below, <a href="/contact">contact us</a>—we still help many other small businesses.</p>
    </div>
  </div>
</div>
HTML;
    }
}
