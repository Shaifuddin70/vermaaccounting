<?php
declare(strict_types=1);

/**
 * Apply Verma-SEO-page-plan.md briefs to existing CMS industry/location pages.
 * Safe to re-run: matches by exact path, updates mapped fields only.
 *
 * Usage: php scripts/apply-seo-industry-briefs.php
 */

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$section = static function (string $heading, string $body) use ($e): string {
    return '<h2>' . $e($heading) . '</h2><p>' . $e($body) . '</p>';
};

$example = static function (string $heading, string $body) use ($e): string {
    return '<h2>' . $e($heading) . '</h2>'
        . '<p><em>Illustrative example.</em> ' . $e($body) . '</p>';
};

$checklist = static function (string $heading, array $items) use ($e): string {
    $html = '<h2>' . $e($heading) . '</h2><ul>';
    foreach ($items as $item) {
        $html .= '<li>' . $e($item) . '</li>';
    }
    return $html . '</ul>';
};

$links = static function (array $items) use ($e): string {
    $html = '<h2>Explore next</h2><ul>';
    foreach ($items as $item) {
        $html .= '<li><a href="' . $e($item['url']) . '">' . $e($item['label']) . '</a></li>';
    }
    return $html . '</ul>';
};

$cta = static function (string $label) use ($e): string {
    return '<p><a class="cta-button primary" href="/contact">' . $e($label) . '</a></p>';
};

$faqs = static function (array $pairs): array {
    $out = [];
    foreach ($pairs as [$q, $a]) {
        $out[] = ['question' => $q, 'answer' => $a];
    }
    return $out;
};

$pages = [];

$pages['/industries/trucking-transportation'] = [
    'title' => 'Trucking bookkeeping & tax for Ontario carriers',
    'h1' => 'Trucking bookkeeping & tax for Ontario carriers',
    'seo_title' => 'Trucking bookkeeping & tax for Ontario carriers | Verma Accounting',
    'meta_description' => 'See how to match settlement statements to deposits, track costs by truck and prepare driver payment records for year-end.',
    'excerpt' => 'Match settlements to deposits, track costs by truck, and get year-end payment records ready.',
    'sort_order' => 1,
    'featured_image_url' => '/images/industries/trucking.jpg',
    'featured_alt' => 'Truck on the highway',
    'faqs' => $faqs([
        ['What if the carrier deducts fuel before paying me?', 'Record the gross freight, the fuel deduction, and the net deposit as separate lines. Keep the settlement so each piece is easy to explain.'],
        ['Can I track two trucks separately?', 'Yes. Give each unit its own costs for fuel, repairs, and financing so you can see which truck earns more.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Owner-operators and small fleets need books that match settlement statements to bank deposits. We help Ontario carriers keep fuel, repairs, and driver pay clear before year-end.</p>'
        . $section('Read an owner-operator settlement', 'Start with gross freight, then list advances, carrier deductions, and the net deposit. Each line should match a receipt or statement before you book it.')
        . $section('Track fuel, repairs and financing by unit', 'Assign fuel cards, shop invoices, and lease or loan payments to the truck they belong to. Monthly reports then show cost per unit, not one mixed pile.')
        . $section('Review driver and carrier payments before year-end', 'Before filing season, review draws, settlements, and contractor or payroll payments so T4 or contractor records are ready.')
        . $example('Settlement walkthrough', 'Imagine a settlement with $4,800 gross freight, a $350 fuel advance, $120 in carrier fees, and a $4,330 deposit. Book the gross, the deductions, and the deposit separately so the bank line matches.')
        . $checklist('Bring to your first meeting', ['Settlement statement', 'Fuel card report', 'Unit list'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'Payroll services for Ontario businesses', 'url' => '/ontario/payroll-services'],
        ])
        . $cta('Discuss my trucking books')
        . '</div>',
];

$pages['/industries/real-estate-landlords'] = [
    'title' => 'Rental property accounting for Ottawa landlords',
    'h1' => 'Rental property accounting for Ottawa landlords',
    'seo_title' => 'Rental property accounting for Ottawa landlords | Verma Accounting',
    'meta_description' => 'Organize rent, mortgage interest and renovation invoices by property. See what to bring for a rental income tax appointment.',
    'excerpt' => 'Organize rent, mortgage interest, and renovation invoices by property.',
    'sort_order' => 2,
    'featured_image_url' => '/images/industries/landlords.jpg',
    'featured_alt' => 'Residential house exterior',
    'faqs' => $faqs([
        ['Which mortgage statement do you need?', 'The annual mortgage statement that shows interest paid for the year. Keep monthly statements too if interest is not clear on the annual page.'],
        ['How should co-owners organize shared expenses?', 'Keep one folder per property and note each owner’s share. Shared invoices should show the split you agreed on.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Landlords need rent, mortgages, and repairs sorted by address. We help Ottawa and Ontario property owners keep each door clear for tax time.</p>'
        . $section('Build a ledger for each address', 'Track rent, deposits, repairs, insurance, and property tax by property so each door stands on its own.')
        . $section('Separate mortgage payments and rental expenses', 'Split principal and interest when the statement allows. Keep operating costs apart from loan payments.')
        . $section('Document renovations and ownership changes', 'Save improvement invoices with dates and scope. Note ownership changes so year-end reporting stays accurate.')
        . $example('Roof invoice path', 'A $9,200 roof invoice goes in the property folder, then into the books as an improvement or repair based on the facts. Your preparer needs the invoice, date, and what work was done.')
        . $checklist('Bring to your first meeting', ['Rent roll', 'Mortgage annual statement', 'Improvement invoices'])
        . $links([
            ['label' => 'Personal tax preparation for Ottawa individuals and families', 'url' => '/industries/individuals-and-families'],
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
        ])
        . $cta('Discuss my rental properties')
        . '</div>',
];

$pages['/industries/construction-contractors'] = [
    'title' => 'Construction accounting for Ottawa contractors',
    'h1' => 'Construction accounting for Ottawa contractors',
    'seo_title' => 'Construction accounting for Ottawa contractors | Verma Accounting',
    'meta_description' => 'Track job costs, progress invoices, holdbacks and subcontractor payments. See how one project flows through your books.',
    'excerpt' => 'Track job costs, progress invoices, holdbacks, and subcontractor payments.',
    'sort_order' => 3,
    'featured_image_url' => '/images/industries/construction.jpg',
    'featured_alt' => 'Construction workers on a job site',
    'faqs' => $faqs([
        ['Why does billed revenue differ from cash received?', 'Progress invoices can include holdbacks. Cash often arrives later, so billed and collected amounts will not match every month.'],
        ['Which subcontractor details should I collect?', 'Legal name, address, GST/HST number when used, and payment totals. Clear records make year-end reporting easier.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Contractors need to see which jobs make money after labour, materials, holdbacks, and HST. We help Ottawa construction firms keep project books that support bids and tax filing.</p>'
        . $section('Set up cost codes from the estimate', 'Carry estimate codes into the books so labour, materials, and subs stay tied to each job.')
        . $section('Match progress draws and holdbacks', 'Track each draw and the holdback still unpaid so GST/HST and cash reports stay honest.')
        . $section('Prepare subcontractor records for reporting review', 'Keep payment lists ready for year-end review, including who was paid and for which jobs.')
        . $example('Progress draw with holdback', 'A $50,000 draw with a 10% holdback means $45,000 may be collectible now and $5,000 waits. A job-cost report then compares budget to actual labour and materials.')
        . $checklist('Bring to your first meeting', ['Estimate', 'Progress invoice', 'Subcontractor ledger'])
        . $links([
            ['label' => 'Bookkeeping for Ottawa trades and home services', 'url' => '/industries/trades-and-home-services'],
            ['label' => 'GST/HST filing services in Ottawa', 'url' => '/ottawa/gst-hst-filing'],
        ])
        . $cta('Review my project accounting')
        . '</div>',
];

$pages['/industries/restaurants-food-services'] = [
    'title' => 'Bookkeeping for Ottawa restaurants and cafés',
    'h1' => 'Bookkeeping for Ottawa restaurants and cafés',
    'seo_title' => 'Bookkeeping for Ottawa restaurants and cafés | Verma Accounting',
    'meta_description' => 'Match POS sales, tips and delivery payouts to bank deposits. See the records that explain a restaurant’s daily sales and food costs.',
    'excerpt' => 'Match POS sales, tips, and delivery payouts to bank deposits.',
    'sort_order' => 4,
    'featured_image_url' => '/images/industries/restaurants.jpg',
    'featured_alt' => 'Restaurant dining room ready for guests',
    'faqs' => $faqs([
        ['Why is my delivery deposit lower than menu sales?', 'Platforms take commissions and fees before paying you. Match the order report to the payout so the gap is explained.'],
        ['How do I document tips handled through payroll?', 'Keep the tip policy, daily tip totals, and payroll tip lines together so pay and sales stay in sync.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Restaurants move cash, cards, tips, and delivery apps every day. We help Ottawa food businesses match those sales to deposits and watch food cost beside payroll.</p>'
        . $section('Reconcile one day of POS activity', 'Close the day with cash, card, and tip totals, then match them to deposits and payroll tip lines.')
        . $section('Match delivery-platform commissions and refunds', 'Bring Uber Eats, DoorDash, or similar payouts into the books with fees and refunds shown.')
        . $section('Read food cost alongside payroll', 'Review food purchases with labour so you see margin, not just the bank balance.')
        . $example('Daily close', 'Card sales $2,400, tips $310, processor fees $48, and a Monday deposit for the weekend batch. The undeposited total on Sunday is expected until the batch lands.')
        . $checklist('Bring to your first meeting', ['POS close', 'Delivery statement', 'Tip policy'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'Payroll services for Ontario businesses', 'url' => '/ontario/payroll-services'],
        ])
        . $cta('Discuss my restaurant books')
        . '</div>',
];

$pages['/industries/cleaning-businesses'] = [
    'title' => 'Bookkeeping for Ottawa cleaning businesses',
    'h1' => 'Bookkeeping for Ottawa cleaning businesses',
    'seo_title' => 'Bookkeeping for Ottawa cleaning businesses | Verma Accounting',
    'meta_description' => 'Track recurring cleaning contracts, crew costs and unpaid invoices. See what to gather before your first bookkeeping meeting.',
    'excerpt' => 'Track recurring contracts, crew costs, and unpaid invoices.',
    'sort_order' => 5,
    'featured_image_url' => '/images/industries/cleaning.jpg',
    'featured_alt' => 'Cleaning supplies and mop on a floor',
    'faqs' => $faqs([
        ['How do I track supplies shared across sites?', 'Keep a simple supply account, then assign larger site-specific purchases to that client when you can.'],
        ['What records help review a crew member’s status?', 'Contracts, timesheets, invoices, and how you pay them. Clear records support employee vs contractor review.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Cleaning companies grow on recurring routes and steady crews. We help Ottawa cleaning owners track contracts, supplies, and pay so margins stay visible.</p>'
        . $section('Invoice recurring contracts and one-off cleans', 'Set recurring invoices for monthly clients and separate one-off jobs so cash flow is easy to read.')
        . $section('Assign crew time and supplies by client', 'When travel and labour differ by site, assign those costs so you know which contracts are worth keeping.')
        . $section('Review worker arrangements and payment records', 'Keep contractor and employee payments clear, then add payroll when you hire staff.')
        . $example('Two contracts, different margins', 'Two clients both pay $800 a month. One needs 6 labour hours nearby; the other needs 10 hours plus long travel. The fee looks the same; the margin does not.')
        . $checklist('Bring to your first meeting', ['Client schedule', 'Timesheets', 'Supply receipts'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'Payroll services for Ontario businesses', 'url' => '/ontario/payroll-services'],
        ])
        . $cta('Review my cleaning contracts')
        . '</div>',
];

$pages['/industries/self-employed-gig-workers'] = [
    'title' => 'Tax help for Ottawa freelancers and gig workers',
    'h1' => 'Tax help for Ottawa freelancers and gig workers',
    'seo_title' => 'Tax help for Ottawa freelancers and gig workers | Verma Accounting',
    'meta_description' => 'Organize platform earnings, tips, fees and vehicle logs for personal tax filing. Explore the different GST/HST questions for delivery and rideshare work.',
    'excerpt' => 'Organize platform earnings, tips, fees, and vehicle logs for tax filing.',
    'sort_order' => 6,
    'featured_image_url' => '/images/industries/gig-workers.jpg',
    'featured_alt' => 'Driver behind the wheel of a car',
    'faqs' => $faqs([
        ['Do I report tips paid outside the app?', 'Yes, keep a simple log of cash tips. They are still income even when the app does not show them.'],
        ['What if I drive passengers and deliver meals?', 'Keep separate summaries by activity. Registration and expense questions can differ, so clear splits help.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Freelancers and gig workers need clear records of app pay, fees, tips, and vehicle use. We help Ottawa self-employed people get ready for personal tax filing.</p>'
        . $section('Reconcile earnings across apps', 'Match annual summaries and weekly payouts so gross earnings, tips, and fees are complete.')
        . $section('Document business use of a vehicle', 'Keep a mileage log and repair receipts. Separate personal and business use.')
        . $section('Separate rideshare and delivery registration questions', 'Different activities can raise different GST/HST questions. Keep the streams clear and follow current CRA guidance for your case.')
        . $example('Mixed-app week', 'A week with rideshare and food delivery may show higher gross earnings than the bank deposit after fees. Reconcile each app summary to the payout before you file.')
        . $checklist('Bring to your first meeting', ['Annual app summaries', 'Mileage log', 'Expense receipts'])
        . $links([
            ['label' => 'Personal tax preparation for Ottawa individuals and families', 'url' => '/industries/individuals-and-families'],
            ['label' => 'GST/HST filing services in Ottawa', 'url' => '/ottawa/gst-hst-filing'],
        ])
        . $cta('Discuss my self-employed return')
        . '</div>',
];

$pages['/industries/professional-services'] = [
    'title' => 'Accounting for Ottawa consultants and service firms',
    'h1' => 'Accounting for Ottawa consultants and service firms',
    'seo_title' => 'Accounting for Ottawa consultants and service firms | Verma Accounting',
    'meta_description' => 'Follow retainers, unbilled work and overdue client invoices through a consulting firm’s monthly reports and tax preparation.',
    'excerpt' => 'Follow retainers, unbilled work, and overdue invoices through monthly reports.',
    'sort_order' => 7,
    'featured_image_url' => '/images/industries/professional.jpg',
    'featured_alt' => 'Professionals meeting in an office',
    'faqs' => $faqs([
        ['How should I flag an unearned retainer?', 'Keep it as a liability until you earn it, then move it to revenue when the work is done or billed.'],
        ['What should I bring if one client provides most of my revenue?', 'Bring that client’s contract, invoice list, and payment history so concentration risk and receivables are clear.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Consultants and service firms need books that fit retainers, invoices, and owner pay. We help Ottawa professionals see unpaid work and plan for tax.</p>'
        . $section('Trace retainers to invoices', 'Connect deposits and retainers to later invoices so unearned amounts stay visible.')
        . $section('Review receivables by client', 'Age unpaid invoices monthly so collection stays proactive.')
        . $section('Connect owner withdrawals to year-end records', 'Track salary, dividends, and draws so personal and corporate filing stay aligned.')
        . $example('Staged billing', 'A consultant bills 40% on start, 40% on draft, and 20% on delivery. The invoice list shows earned work; the cash forecast shows when money actually arrives.')
        . $checklist('Bring to your first meeting', ['Contracts', 'Receivables list', 'Owner transaction history'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'Corporate tax accountant in Ottawa', 'url' => '/ottawa/corporate-tax-accountant'],
        ])
        . $cta('Discuss my consulting practice')
        . '</div>',
];

$pages['/industries/retail-ecommerce'] = [
    'title' => 'Bookkeeping for Ottawa retailers and online stores',
    'h1' => 'Bookkeeping for Ottawa retailers and online stores',
    'seo_title' => 'Bookkeeping for Ottawa retailers and online stores | Verma Accounting',
    'meta_description' => 'Reconcile store and online sales, marketplace fees, refunds and inventory so your sales reports explain the cash in your bank.',
    'excerpt' => 'Reconcile store and online sales, fees, refunds, and inventory.',
    'sort_order' => 8,
    'featured_image_url' => '/images/industries/retail.jpg',
    'featured_alt' => 'Clothing racks in a retail shop',
    'faqs' => $faqs([
        ['Which marketplace files do you need?', 'Order exports, payout reports, and fee summaries. Refund reports help when money moves in a later period.'],
        ['Why can a refund affect a later payout?', 'A refund can settle after the original order payout. That is why order date and payout date often differ.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Shops and online sellers need sales, fees, refunds, and inventory to explain the cash in the bank. We help Ottawa retailers keep every channel clear.</p>'
        . $section('Trace a marketplace payout', 'Match orders, fees, and refunds to each payout so revenue is complete.')
        . $section('Match online and in-store inventory', 'Connect purchases and stock counts to cost of goods so margins make sense.')
        . $section('Review sales-tax settings against actual orders', 'Check GST/HST collected against real orders and your filing period.')
        . $example('Order, refund, and payout', 'An order ships in March, a partial refund posts in April, and fees come out of the April payout. Keep the order export and payout report so both months stay clear.')
        . $checklist('Bring to your first meeting', ['Order export', 'Payout report', 'Stock count'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'GST/HST filing services in Ottawa', 'url' => '/ottawa/gst-hst-filing'],
        ])
        . $cta('Review my sales channels')
        . '</div>',
];

$pages['/industries/startups-corporations'] = [
    'title' => 'Accounting for Ottawa startups and new corporations',
    'h1' => 'Accounting for Ottawa startups and new corporations',
    'seo_title' => 'Accounting for Ottawa startups and new corporations | Verma Accounting',
    'meta_description' => 'Plan the first accounting year after incorporation, from founder-paid expenses and opening balances to payroll and the first corporate tax handoff.',
    'excerpt' => 'Plan the first accounting year after incorporation.',
    'sort_order' => 9,
    'featured_image_url' => '/images/industries/startups.jpg',
    'featured_alt' => 'Startup team working together at a table',
    'faqs' => $faqs([
        ['What if I paid startup costs personally?', 'Keep receipts and note the dates. We can record founder-paid costs properly once the company books are open.'],
        ['What if my new corporation has no sales yet?', 'You still need opening balances, expense tracking, and a plan for first filings. Quiet months still need clean records.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>New corporations need a clean first year: opening balances, founder costs, payroll when you hire, and a clear handoff to corporate tax.</p>'
        . $section('Record the founder’s first transactions', 'Capture incorporation costs and personal payments made for the company with receipts and dates.')
        . $section('Set up the first monthly close', 'Reconcile the corporate bank account and review a simple profit and cash summary each month.')
        . $section('Map the first year-end handoff', 'List what the T2 preparer will need so year-end is planned, not rushed.')
        . $example('Software paid before the company account opens', 'A founder pays $79 for software on a personal card before the corporate bank is ready. Keep the receipt and note it as a founder-paid company cost to record later.')
        . $checklist('Bring to your first meeting', ['Incorporation papers', 'Receipts', 'Bank opening statement'])
        . $links([
            ['label' => 'Small business accountant in Ottawa', 'url' => '/ottawa/small-business-accountant'],
            ['label' => 'Corporate tax accountant in Ottawa', 'url' => '/ottawa/corporate-tax-accountant'],
        ])
        . $cta('Plan my first accounting year')
        . '</div>',
];

$pages['/industries/real-estate-professionals'] = [
    'title' => 'Accounting for Ottawa real estate agents',
    'h1' => 'Accounting for Ottawa real estate agents',
    'seo_title' => 'Accounting for Ottawa real estate agents | Verma Accounting',
    'meta_description' => 'Match brokerage commission statements to deposits, organize selling expenses and prepare records for a sole proprietor or incorporated agent.',
    'excerpt' => 'Match commission statements to deposits and organize selling expenses.',
    'sort_order' => 10,
    'featured_image_url' => '/images/industries/realtors.jpg',
    'featured_alt' => 'Homes on a residential street',
    'faqs' => $faqs([
        ['How do I record desk fees withheld from commission?', 'Show gross commission, then the desk fee or split as a deduction, then the net deposit. Keep the brokerage statement.'],
        ['What changes if my commission is paid to my corporation?', 'Income and expenses belong on the company books. Owner pay then follows salary or dividend planning with your tax preparer.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Agents need commission statements, splits, fees, and selling costs to match bank deposits. We help Ottawa realtors keep sole-proprietor or corporate records clear.</p>'
        . $section('Read a brokerage commission statement', 'Confirm gross commission, splits, desk fees, and the net deposit against the deal agreement.')
        . $section('Track listing and vehicle costs', 'Keep marketing, staging, and vehicle logs with support that is easy to explain.')
        . $section('Prepare separate records for an incorporated agent', 'If commissions are paid to a corporation, keep company books separate from personal spending.')
        . $example('Commission statement', 'A statement may show $12,000 gross, a brokerage split, a desk fee, and a $7,800 deposit. Confirm each figure against the agreement before booking.')
        . $checklist('Bring to your first meeting', ['Brokerage statements', 'Expense receipts', 'Vehicle log'])
        . $links([
            ['label' => 'GST/HST filing services in Ottawa', 'url' => '/ottawa/gst-hst-filing'],
            ['label' => 'Corporate tax accountant in Ottawa', 'url' => '/ottawa/corporate-tax-accountant'],
        ])
        . $cta('Review my commission records')
        . '</div>',
];

$pages['/industries/trades-and-home-services'] = [
    'title' => 'Bookkeeping for Ottawa trades and home services',
    'h1' => 'Bookkeeping for Ottawa trades and home services',
    'seo_title' => 'Bookkeeping for Ottawa trades and home services | Verma Accounting',
    'meta_description' => 'See what each service call earns after parts, labour and travel. Organize deposits and field payments for an electrical, plumbing or HVAC business.',
    'excerpt' => 'See what each service call earns after parts, labour, and travel.',
    'sort_order' => 11,
    'featured_image_url' => '/images/industries/trades.jpg',
    'featured_alt' => 'Technician working with tools',
    'faqs' => $faqs([
        ['Where do customer deposits appear?', 'Deposits sit as a liability until the job is done, then move to revenue. Keep the work order linked to the deposit.'],
        ['How do I record parts taken from van stock?', 'Track van inventory and assign parts to the job when used so cost of the call stays accurate.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Trades and home-service teams need each call to show parts, labour, travel, and collection. We help Ottawa electrical, plumbing, and HVAC businesses keep field work clear in the books.</p>'
        . $section('Follow a service call from estimate to collection', 'Link the quote, work order, invoice, and payment so nothing is left hanging.')
        . $section('Track parts and technician time per visit', 'Assign van stock and labour hours to the job so you see true call profit.')
        . $section('Reconcile field payments and customer deposits', 'Match card terminals, cash, and deposits to invoices after every busy week.')
        . $example('Quoted repair vs warranty callback', 'A quoted repair may include travel, two labour hours, and $85 in parts. A warranty callback may have labour and travel only. A one-job worksheet makes the difference obvious.')
        . $checklist('Bring to your first meeting', ['Work orders', 'Parts receipts', 'Field payment export'])
        . $links([
            ['label' => 'Construction accounting for Ottawa contractors', 'url' => '/industries/construction-contractors'],
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
        ])
        . $cta('Review my service-call books')
        . '</div>',
];

$pages['/industries/healthcare-and-clinics'] = [
    'title' => 'Accounting for Ottawa healthcare clinics',
    'h1' => 'Accounting for Ottawa healthcare clinics',
    'seo_title' => 'Accounting for Ottawa healthcare clinics | Verma Accounting',
    'meta_description' => 'Reconcile clinic billings, insurer deposits and practitioner splits. Identify the service details your accountant needs for a GST/HST review.',
    'excerpt' => 'Reconcile clinic billings, insurer deposits, and practitioner splits.',
    'sort_order' => 12,
    'featured_image_url' => '/images/industries/healthcare.jpg',
    'featured_alt' => 'Clinic hallway and medical office',
    'faqs' => $faqs([
        ['How do we reconcile a deposit covering multiple visits?', 'Use the payer report to split the deposit across visits or practitioners, then match the total to the bank.'],
        ['What details matter when a clinic sells products?', 'Separate treatment revenue from retail product sales. Mixed supplies can affect GST/HST review.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Clinics need billings, insurer deposits, and practitioner splits to line up. We help Ottawa healthcare practices keep service and retail streams clear for reporting.</p>'
        . $section('Match insurer batches to billings', 'Reconcile payer deposits to visit or batch reports without using patient names in working papers shared outside care systems.')
        . $section('Record practitioner agreements and splits', 'Track each practitioner’s share from the agreement so pay and revenue stay aligned.')
        . $section('Review tax treatment by service', 'List service types and product sales so GST/HST questions can be reviewed with current rules.')
        . $example('Treatment and retail totals', 'A fictional month might show treatment billings in one total and retail products in another. Keep those streams separate before any tax review.')
        . $checklist('Bring to your first meeting', ['De-identified billing summary', 'Payer reports', 'Practitioner contracts'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'GST/HST filing services in Ottawa', 'url' => '/ottawa/gst-hst-filing'],
        ])
        . $cta('Discuss my clinic accounting')
        . '</div>',
];

$pages['/industries/manufacturing-and-wholesale'] = [
    'title' => 'Accounting for Ontario manufacturers and wholesalers',
    'h1' => 'Accounting for Ontario manufacturers and wholesalers',
    'seo_title' => 'Accounting for Ontario manufacturers and wholesalers | Verma Accounting',
    'meta_description' => 'Track inventory from purchase to production to sale. See how freight, work in progress and stock adjustments affect your margin reports.',
    'excerpt' => 'Track inventory from purchase to production to sale.',
    'sort_order' => 13,
    'featured_image_url' => '/images/industries/manufacturing.jpg',
    'featured_alt' => 'Factory floor with industrial equipment',
    'faqs' => $faqs([
        ['What should we count at year-end?', 'Raw materials, work in progress, and finished goods, with notes for damaged or obsolete stock.'],
        ['How do we document stock written off during production?', 'Record quantity, reason, date, and approval. Tie write-offs to the batch or job when possible.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Makers and wholesalers need inventory that moves cleanly from purchase to production to sale. We help Ontario operations see how freight and stock changes affect margin.</p>'
        . $section('Map raw materials and work in progress', 'Track materials as they enter production so unfinished work is not invisible.')
        . $section('Reconcile physical counts with inventory records', 'Compare counts to the system and clear differences with a simple adjustment log.')
        . $section('Review landed costs and wholesale margins', 'Include freight and duties when they belong in unit cost, then review wholesale margins monthly.')
        . $example('Batch cost worksheet', 'A sample batch may include supplier cost, allocated freight, and a few damaged units. The unit-cost worksheet should show how those pieces change finished-goods cost.')
        . $checklist('Bring to your first meeting', ['Inventory count', 'Purchase orders', 'Freight invoices'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'Corporate tax accountant in Ottawa', 'url' => '/ottawa/corporate-tax-accountant'],
        ])
        . $cta('Review my inventory accounting')
        . '</div>',
];

$pages['/industries/non-profits-and-associations'] = [
    'title' => 'Bookkeeping for Ottawa non-profits and associations',
    'h1' => 'Bookkeeping for Ottawa non-profits and associations',
    'seo_title' => 'Bookkeeping for Ottawa non-profits and associations | Verma Accounting',
    'meta_description' => 'Organize grants, dues and program spending for board reports. See the records that help determine your organization’s annual filing needs.',
    'excerpt' => 'Organize grants, dues, and program spending for board reports.',
    'sort_order' => 14,
    'featured_image_url' => '/images/industries/nonprofit.jpg',
    'featured_alt' => 'Volunteers packing donations together',
    'faqs' => $faqs([
        ['How can the board see unspent grant money?', 'Track restricted funds by program and show remaining balances on the board package each period.'],
        ['Which records should an incoming treasurer request?', 'Prior filings, bank access, grant agreements, the approved budget, and the latest reconciled statements.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Associations need grants, dues, and program spending that board members can trust. We help Ottawa non-profits keep restricted funds clear and filing needs visible.</p>'
        . $section('Track restricted funding by program', 'Tag grant money to its purpose so unspent balances stay visible.')
        . $section('Reconcile dues and event receipts', 'Match membership and event income to deposits every month.')
        . $section('Prepare a board package and identify filing obligations', 'Give directors a simple budget-to-actual view and list which filings may apply based on your status.')
        . $example('Restricted grant beside dues', 'A restricted grant for a youth program sits beside unrestricted dues in a sample budget report. Charity registration and other non-profit forms depend on your legal status—confirm before filing.')
        . $checklist('Bring to your first meeting', ['Grant agreements', 'Prior filings', 'Approved budget'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'Payroll services for Ontario businesses', 'url' => '/ontario/payroll-services'],
        ])
        . $cta('Discuss our association’s books')
        . '</div>',
];

$pages['/industries/individuals-and-families'] = [
    'title' => 'Personal tax preparation for Ottawa individuals and families',
    'h1' => 'Personal tax preparation for Ottawa individuals and families',
    'seo_title' => 'Personal tax preparation for Ottawa individuals and families | Verma Accounting',
    'meta_description' => 'Prepare for tax season after a move, a new child or a change in income. Get a document checklist for your household’s appointment.',
    'excerpt' => 'Get a document checklist for your household tax appointment.',
    'sort_order' => 15,
    'featured_image_url' => '/images/industries/personal-tax.jpg',
    'featured_alt' => 'Person reviewing tax papers at a desk',
    'faqs' => $faqs([
        ['What should my partner and I gather separately?', 'Each person should gather their own slips and receipts, then share household items like childcare or moving documents once.'],
        ['What if a slip arrives after I submit my documents?', 'Send it as soon as it arrives. We can update the return before filing when timing allows.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Families need a clear checklist after a move, a new child, or a change in income. We help Ottawa households get documents ready for personal tax preparation.</p>'
        . $section('Choose a checklist by life event', 'Moving, a new dependant, or new income each add a few documents. Start with the event that changed this year.')
        . $section('Gather slips and receipts for each filer', 'Collect T4s and other slips, then add childcare, medical, or moving receipts that apply.')
        . $section('Submit documents and review the return', 'Upload through our intake process, then review the draft before filing.')
        . $example('Household checklist', 'Employment income slips, childcare receipts, and a change of address cover a common Ottawa household year. Side businesses or rental income belong on their own pages and checklists.')
        . $checklist('Bring to your first meeting', ['Prior assessment', 'Income slips', 'Childcare receipts'])
        . $links([
            ['label' => 'Rental property accounting for Ottawa landlords', 'url' => '/industries/real-estate-landlords'],
            ['label' => 'Tax help for Ottawa freelancers and gig workers', 'url' => '/industries/self-employed-gig-workers'],
        ])
        . $cta('Prepare for my tax appointment')
        . '</div>',
];

$pages['/ottawa/small-business-accountant'] = [
    'title' => 'Small business accountant in Ottawa',
    'h1' => 'Small business accountant in Ottawa',
    'seo_title' => 'Small business accountant in Ottawa | Verma Accounting',
    'meta_description' => 'Choose the right accounting support for your Ottawa business. Compare monthly books, payroll, GST/HST and corporate year-end work before booking.',
    'excerpt' => 'Choose the right mix of monthly books, payroll, GST/HST, and year-end help.',
    'sort_order' => 1,
    'featured_image_url' => '/images/industries/small-business.jpg',
    'featured_alt' => 'Small business owners reviewing plans together',
    'faqs' => $faqs([
        ['Can I keep my own books and get year-end help?', 'Yes. Many owners keep day-to-day records and ask us to review and file at year-end.'],
        ['What affects the scope of an ongoing engagement?', 'Transaction volume, payroll, sales channels, catch-up work, and whether you need monthly reports or filing support only.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Ottawa owners often need help choosing between monthly books, payroll, GST/HST, and corporate year-end work. This page helps you pick the right starting point.</p>'
        . $section('Choose help by business stage', 'Sole proprietors, first-time employers, and established corporations need different first steps.')
        . $section('Decide what to handle each month', 'Some owners only need year-end help. Others need monthly closes, payroll, and sales-tax filings.')
        . $section('Prepare for your first accounting meeting', 'Bring structure details, recent statements, and upcoming due dates so the first call is useful.')
        . $example('Decision table', 'A sole proprietor may start with bookkeeping and personal tax. A new employer adds payroll. An established corporation often needs monthly books plus T2 planning.')
        . $checklist('Bring to your first meeting', ['Business structure', 'Latest statements', 'Upcoming due dates'])
        . $links([
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
            ['label' => 'Corporate tax accountant in Ottawa', 'url' => '/ottawa/corporate-tax-accountant'],
            ['label' => 'GST/HST filing services in Ottawa', 'url' => '/ottawa/gst-hst-filing'],
            ['label' => 'Payroll services for Ontario businesses', 'url' => '/ontario/payroll-services'],
        ])
        . $cta('Discuss my business accounting')
        . '</div>',
];

$pages['/ottawa/bookkeeping-services'] = [
    'title' => 'Monthly bookkeeping services in Ottawa',
    'h1' => 'Monthly bookkeeping services in Ottawa',
    'seo_title' => 'Monthly bookkeeping services in Ottawa | Verma Accounting',
    'meta_description' => 'Find out what goes into a monthly close, how catch-up work is scoped and which records to bring for an Ottawa bookkeeping quote.',
    'excerpt' => 'See what a monthly close includes and what affects a bookkeeping quote.',
    'sort_order' => 2,
    'featured_image_url' => '/images/industries/bookkeeping.jpg',
    'featured_alt' => 'Bookkeeper working with a calculator and ledger',
    'faqs' => $faqs([
        ['Can you start halfway through my fiscal year?', 'Yes. We begin from the last clean month when possible, then plan catch-up for earlier periods if needed.'],
        ['What will you need from my previous bookkeeper?', 'The last reconciled month, chart of accounts, and login or export access for your software and bank feeds.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Ottawa businesses looking for bookkeeping usually want to know what a monthly close includes, how catch-up works, and what drives a quote.</p>'
        . $section('What a monthly close includes', 'Bank and card reconciliations, categorized transactions, and a simple profit summary you can use.')
        . $section('How catch-up bookkeeping starts', 'We find the last clean month, estimate the backlog, and clear periods in order so filing dates stay in view.')
        . $section('What affects your bookkeeping quote', 'Account count, transaction volume, backlog, payroll, and sales channels all change the work.')
        . $example('Monthly handoff', 'A sample handoff may include reconciled accounts, a short list of open questions, and a profit-and-loss report. Quote inputs include account count, volume, backlog, and channels.')
        . $checklist('Bring to your first meeting', ['Bank and card statements', 'Last reconciled month', 'Software name'])
        . $links([
            ['label' => 'Bookkeeping for Ottawa restaurants and cafés', 'url' => '/industries/restaurants-food-services'],
            ['label' => 'Bookkeeping for Ottawa retailers and online stores', 'url' => '/industries/retail-ecommerce'],
            ['label' => 'Small business accountant in Ottawa', 'url' => '/ottawa/small-business-accountant'],
        ])
        . $cta('Request a bookkeeping quote')
        . '</div>',
];

$pages['/ottawa/corporate-tax-accountant'] = [
    'title' => 'Corporate tax accountant in Ottawa',
    'h1' => 'Corporate tax accountant in Ottawa',
    'seo_title' => 'Corporate tax accountant in Ottawa | Verma Accounting',
    'meta_description' => 'Prepare your Ottawa corporation’s T2 handoff. Review the year-end checklist, owner transactions and the steps from draft return to filing confirmation.',
    'excerpt' => 'Prepare the T2 handoff, owner transactions, and filing steps.',
    'sort_order' => 3,
    'featured_image_url' => '/images/industries/corporate-tax.jpg',
    'featured_alt' => 'Business person reviewing corporate tax documents',
    'faqs' => $faqs([
        ['Can you prepare a T2 from my bookkeeper’s file?', 'Yes, when the file is complete. We review the trial balance, owner transactions, and supporting schedules before drafting.'],
        ['Which prior-year documents help when switching accountants?', 'The prior T2, notice of assessment, and fixed-asset details make the handoff faster and safer.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Ottawa corporations need a clear year-end package before T2 work starts. We help owners gather records, review shareholder items, and understand filing and payment timing.</p>'
        . $section('Gather the year-end package', 'Trial balance, bank reconciliations, asset additions, and owner transaction details come first.')
        . $section('Review shareholder and balance-sheet items', 'Check loans, dividends, salary, and unusual balances before the return is drafted.')
        . $section('Separate filing, payment and instalment dates', 'Know which date is for filing and which is for paying. Instalments are planned from the finished return.')
        . $example('Year-end handoff path', 'After books are closed, we prepare a draft return for review, file when approved, and share confirmation. Exact due dates depend on your year-end and should be confirmed for your corporation.')
        . $checklist('Bring to your first meeting', ['Trial balance', 'Prior T2 and assessment', 'Asset additions'])
        . $links([
            ['label' => 'Accounting for Ottawa startups and new corporations', 'url' => '/industries/startups-corporations'],
            ['label' => 'Accounting for Ottawa consultants and service firms', 'url' => '/industries/professional-services'],
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
        ])
        . $cta('Discuss my corporate year-end')
        . '</div>',
];

$pages['/ottawa/gst-hst-filing'] = [
    'title' => 'GST/HST filing services in Ottawa',
    'h1' => 'GST/HST filing services in Ottawa',
    'seo_title' => 'GST/HST filing services in Ottawa | Verma Accounting',
    'meta_description' => 'Prepare a GST/HST return from your sales records, expense invoices and CRA reporting period. Get help identifying gaps before filing.',
    'excerpt' => 'Prepare GST/HST returns from sales records and expense invoices.',
    'sort_order' => 4,
    'featured_image_url' => '/images/industries/gst-hst.jpg',
    'featured_alt' => 'Person filling out a tax form with a pen',
    'faqs' => $faqs([
        ['What if my books disagree with the last return?', 'Bring both. We reconcile sales-tax reports to the prior return and list gaps before the next filing.'],
        ['What should I bring for an overdue period?', 'CRA notices, sales reports, purchase invoices with tax, and the last return you filed.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Ottawa businesses need GST/HST returns that match sales records and expense invoices for the right CRA period. We help find gaps before you file.</p>'
        . $section('Confirm the reporting period and account', 'Start with the CRA period and account details so the return covers the right dates.')
        . $section('Reconcile sales tax and supporting invoices', 'Match tax collected and credits claimed to invoices and reports.')
        . $section('Review the return and arrange payment', 'Check the draft, then file and plan remittance timing.')
        . $example('One sale and one purchase', 'A sales invoice with HST collected and a purchase invoice with eligible tax paid both feed the reconciliation. Missing invoices, mixed supplies, and adjustments need individual review.')
        . $checklist('Bring to your first meeting', ['CRA period notice', 'Last return', 'Sales and purchase tax reports'])
        . $links([
            ['label' => 'Bookkeeping for Ottawa retailers and online stores', 'url' => '/industries/retail-ecommerce'],
            ['label' => 'Accounting for Ottawa healthcare clinics', 'url' => '/industries/healthcare-and-clinics'],
            ['label' => 'Monthly bookkeeping services in Ottawa', 'url' => '/ottawa/bookkeeping-services'],
        ])
        . $cta('Discuss my GST/HST filing')
        . '</div>',
];

$pages['/ontario/payroll-services'] = [
    'title' => 'Payroll services for Ontario businesses',
    'h1' => 'Payroll services for Ontario businesses',
    'seo_title' => 'Payroll services for Ontario businesses | Verma Accounting',
    'meta_description' => 'Plan employee onboarding, pay runs, source deductions and year-end slips. See what an Ontario employer needs to provide when switching payroll services.',
    'excerpt' => 'Plan onboarding, pay runs, remittances, and year-end slips.',
    'sort_order' => 5,
    'featured_image_url' => '/images/industries/payroll.jpg',
    'featured_alt' => 'Payroll paperwork and calculator on a desk',
    'faqs' => $faqs([
        ['Can I switch payroll providers midyear?', 'Yes. You will need year-to-date totals, employee details, and remittance history so the next run stays accurate.'],
        ['Who approves hours and verifies year-to-date balances?', 'You approve hours and pay. We help reconcile remittances and keep year-to-date figures ready for slips.'],
    ]),
    'body' => '<div class="container industry-cms">'
        . '<p>Ontario employers need payroll that stays on time from hiring through remittances and year-end slips. We help with setup, pay runs, and switching providers midyear.</p>'
        . $section('Set up a new employer or transfer payroll', 'Collect employee details, pay calendars, and year-to-date totals before the first run.')
        . $section('Approve each pay run and reconcile remittances', 'Review hours, pay, and CRA remittances so deductions match what was withheld.')
        . $section('Prepare for employee changes and year-end slips', 'Handle new hires, ROEs, and T4 preparation from the same records.')
        . $example('Midyear switch timeline', 'Week 1 gather year-to-date totals and authorization. Week 2 approve the first pay. Week 3 reconcile the first remittance. Confirm who owns each step before you switch.')
        . $checklist('Bring to your first meeting', ['Last payroll register', 'Remittance statement', 'Pay calendar'])
        . $links([
            ['label' => 'Bookkeeping for Ottawa restaurants and cafés', 'url' => '/industries/restaurants-food-services'],
            ['label' => 'Bookkeeping for Ottawa cleaning businesses', 'url' => '/industries/cleaning-businesses'],
            ['label' => 'Small business accountant in Ottawa', 'url' => '/ottawa/small-business-accountant'],
        ])
        . $cta('Discuss my Ontario payroll')
        . '</div>',
];

$repo = new CmsContentRepository();
$updated = 0;
$missing = [];
$unchanged = 0;

foreach ($pages as $path => $data) {
    $content = $repo->findByPath($path);
    if ($content === null) {
        $missing[] = $path;
        continue;
    }
    $id = (int) $content['id'];
    $repo->update($id, [
        'title' => $data['title'],
        'h1' => $data['h1'],
        'body_html' => $data['body'],
        'body_mode' => 'sanitized',
        'excerpt' => $data['excerpt'],
        'featured_image_url' => $data['featured_image_url'],
        'featured_alt' => $data['featured_alt'],
        'faqs_json' => json_encode($data['faqs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'sort_order' => (int) $data['sort_order'],
        'template' => 'industry',
        'status' => 'published',
    ]);
    $repo->saveSeo($id, [
        'seo_title' => $data['seo_title'],
        'meta_description' => $data['meta_description'],
        'robots_index' => 'index',
        'robots_follow' => 'follow',
        'sitemap_include' => 1,
        'canonical_mode' => 'auto',
    ]);
    $updated++;
    echo "updated {$id} {$path}\n";
}

CmsCache::flush();
echo "updated={$updated} missing=" . count($missing) . " unchanged={$unchanged}\n";
if ($missing) {
    echo "MISSING:\n" . implode("\n", $missing) . "\n";
    exit(1);
}
