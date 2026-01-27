<?php
// Other Services Component
// Usage: include 'components/other-services.php'; 
// Pass $current_service to exclude from the list

$all_services = [
  'bookkeeping' => [
    'title' => 'Bookkeeping',
    'url' => 'bookkeeping',
    'image' => 'images/book-keeping.jpg',
    'description' => 'Maintain organized ledgers and reconciliations, tracking every transaction and categorizing expenses to provide clear, accurate reports.'
  ],
  'accounting' => [
    'title' => 'Financial Accounting',
    'url' => 'accounting',
    'image' => 'images/financial-accounting.jpg',
    'description' => 'Deliver transparent financial statements, including balance sheets and income statements, offering insights into business performance.'
  ],
  'payroll' => [
    'title' => 'Payroll Services',
    'url' => 'payroll',
    'image' => 'images/payroll.jpg',
    'description' => 'Ensure accurate payroll processing, deductions, and T4 filings, complying with Canadian regulations while keeping employee payments timely.'
  ],
  'personal-tax' => [
    'title' => 'Personal Tax Services',
    'url' => 'personal-tax',
    'image' => 'images/personal-tax.jpg',
    'description' => 'Prepare personal tax returns with full CRA compliance, maximizing eligible deductions and credits to ensure accurate filings and optimized refunds.'
  ],
  'corporate-tax' => [
    'title' => 'Corporate Tax Services',
    'url' => 'corporate-tax',
    'image' => 'images/corporate-tax.jpg',
    'description' => 'Provide corporate tax planning and filing that aligns with regulations, protects profits, and identifies opportunities for credits and savings.'
  ],
  'business-registration' => [
    'title' => 'Business Registration',
    'url' => 'business-registration',
    'image' => 'images/business-registration.jpg',
    'description' => 'Assist with CRA business number, GST/HST registration, and compliance steps to ensure your business is correctly registered and ready to operate.'
  ],
  'loan' => [
    'title' => 'Business Loans',
    'url' => 'loan',
    'image' => 'images/business-loan.jpg',
    'description' => 'Help startups, small businesses, and established companies secure fast, flexible, and affordable business financing across Canada.'
  ]
];

// Filter out current service
$other_services = [];
foreach ($all_services as $key => $service) {
  if ($key !== $current_service) {
    $other_services[$key] = $service;
  }
}
?>

<section class="other-services-section common-section">
  <div class="container">
    <div class="scroll-animate">
      <h2 class="section-title text-center">Other Services We Provide</h2>
      <p class="text-center">Explore our complete range of accounting and financial services designed to support your business needs.</p>
    </div>
    <div class="row mt-4">
      <?php foreach ($other_services as $key => $service): ?>
        <div class="col-lg-4 col-md-6 mb-5">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="<?php echo $service['url']; ?>">
              <img class="thumb" src="<?php echo $service['image']; ?>" alt="<?php echo $service['title']; ?> services" />
              <strong class="services-title"><?php echo $service['title']; ?></strong>
            </a>
            <p class="services-description">
              <?php echo $service['description']; ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>