<?php
$preloadImage = [
  'href' => '/images/hero-bg-2.webp',
  'srcset' => '/images/hero-bg-2-768.webp 768w, /images/hero-bg-2-1280.webp 1280w, /images/hero-bg-2.webp 1920w',
  'sizes' => '100vw',
];
include 'components/header.php';
$homeReview = seo_home_review();
?>
<!-- Main Content -->
<main class="main-content">
  <!-- Hero Section -->
  <section class="hero-section">
    <!-- Background Slider -->
    <div class="hero-slider">
      <div class="hero-slide active">
        <?= site_image('/images/hero-bg-2.jpg', 'Accountant reviewing financial paperwork at a desk', ['class' => 'hero-slide-image', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'sync']) ?>
      </div>
      <div class="hero-slide">
        <?= site_image('/images/hero-bg-3.jpg', 'Business owner going over monthly figures', ['class' => 'hero-slide-image', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'low']) ?>
      </div>
      <div class="hero-slide">
        <?= site_image('/images/hero-bg-4.jpg', 'Team planning finances together', ['class' => 'hero-slide-image', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'low']) ?>
      </div>
    </div>

    <div class="hero-container">
      <div class="hero-content">
        <!-- Left Content -->
        <div class="hero-text scroll-animate">
          <div class="hero-badge">
            <i class="fas fa-award" aria-hidden="true"></i>
            <span>Trusted by 10,000+ Clients</span>
          </div>

          <h1 class="hero-title">
            <span class="title-highlight">Accounting Done Right,</span>
            <br>Every Time
          </h1>

          <p class="hero-description">
            One Goal: Perfect Numbers, Every Time. We deliver accurate bookkeeping, payroll, tax, and business
            registration help for people and companies across Canada.
          </p>

          <!-- Trust Indicators -->

          <div class="rating-display">
            <div class="stars" role="img" aria-label="Rated 5 out of 5">
              <i class="fas fa-star" aria-hidden="true"></i>
              <i class="fas fa-star" aria-hidden="true"></i>
              <i class="fas fa-star" aria-hidden="true"></i>
              <i class="fas fa-star" aria-hidden="true"></i>
              <i class="fas fa-star" aria-hidden="true"></i>
            </div>
            <span class="rating-text"><span>5</span>/5.0</span>
          </div>


          <!-- CTA Buttons -->
          <div class="hero-cta">
            <a href="<?= e(site_cta_url()) ?>" class="cta-button primary">
              <span><?= e(site_cta_hero_label()) ?></span>
              <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
            <a href="/services" class="cta-button secondary">
              <i class="fas fa-calculator" aria-hidden="true"></i>
              <span>Explore Our Services</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA Appointment Section -->
  <section class="cta-appointment-section">
    <div class="container">
      <div class="cta-appointment-content">
        <div class="cta-text">
          <span class="cta-prefix">Looking for help with</span>
          <span id="typing-text">Audits?</span>
        </div>

        <div class="cta-action">
          <a class="appointment-button" href="https://calendly.com/vermaaccounting-info/30min" target="_blank" rel="noopener" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1'});return false;"> <i class="fas fa-calendar-alt" aria-hidden="true"></i>Book an Appointment</a>
        </div>
      </div>
    </div>
  </section>

  <section class="partner-section">
    <div class="container">
      <div class="section-header scroll-animate animate-in">
        <h2>Our Partners</h2>
      </div>
      <div class="partner-grid">
        <?php
          $partners = [
            '/images/FreshBooks_logo_2020.svg.png' => 'FreshBooks',
            '/images/quickbooks.png' => 'QuickBooks',
            '/images/Sage_Group_logo_2022.svg.png' => 'Sage',
            '/images/Wave_logo_RGB.png' => 'Wave',
            '/images/xero_logo_icon_167949.png' => 'Xero',
            '/images/Zoho-Books-logo.png' => 'Zoho Books',
          ];
        ?>
        <div class="partner-track" id="partnerTrack">
          <?php foreach ($partners as $logo => $name): ?>
          <div class="partner-item">
            <?= site_image($logo, $name . ' logo') ?>
          </div>
          <?php endforeach; ?>
          <!-- Duplicate for seamless loop -->
          <?php foreach ($partners as $logo => $name): ?>
          <div class="partner-item" aria-hidden="true">
            <?= site_image($logo, $name . ' logo') ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="partner-dots">
          <?php for ($i = 1; $i <= count($partners); $i++): ?>
          <button type="button" class="dot<?= $i === 1 ? ' active' : '' ?>" aria-label="Show partner <?= $i ?> of <?= count($partners) ?>"<?= $i === 1 ? ' aria-current="true"' : '' ?>></button>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </section>


  <section class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 order-lg-last">
          <div class="img-box scroll-animate-right">
            <?= site_image('/images/rishab-verma-1.jpg', 'Rishab Verma of Verma Accounting') ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-left">
            <h2 class="section-title">Global-Ready Financial Management</h2>
            <p>
              Precision, compliance, and clarity for businesses and individuals
              across Canada and beyond. We review every number with care, so you
              can make decisions with confidence.
            </p>
            <div class="cta-buttons left">
              <a href="/contact" class="cta-button primary">
                Get Free Consultation
              </a>
              <a href="<?= e(company_phone_href()) ?>" class="cta-button orange">
                <i class="fas fa-phone" aria-hidden="true"></i>
                <?= e(company_phone()) ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div class="py-5">
    <div class="container scroll-animate-left">
      <div class="widget-container ">
        <div class="elfsight-app-a9eff633-ffca-4ea6-bbac-14d16ba7f88f"></div>
      </div>
    </div>
  </div>
  <section class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="img-box scroll-animate-left">
            <?= site_image('/images/happy-client.jpg', 'Happy client shaking hands after a consultation') ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h2 class="section-title">
              Financial Management Made Simple with Verma Accounting
            </h2>
            <p>
              Strong businesses run on clear, precise records. We make money
              management simple and dependable, from bookkeeping and payroll to
              CRA business registration and year-end filing. We check every
              figure with care, guided by
              <i><b>one goal: perfect numbers, every time.</b></i>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="services-section text-center">
    <div class="container">
      <div class="scroll-animate">
        <h2 class="section-title">
          Complete Accounting Services for Individuals &amp; Businesses
        </h2>
        <p>
          Each service is built to bring accuracy, compliance, and clarity to
          your finances.
        </p>
      </div>
      <div class="row mt-4">
        <div class="col-lg-4 col-md-6 mb-5">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/bookkeeping">
              <?= site_image('/images/book-keeping.jpg', 'Bookkeeper reconciling a ledger', ['class' => 'thumb', 'sizes' => '(min-width: 992px) 350px, (min-width: 768px) 50vw, 100vw']) ?>
              <strong class="services-title">Bookkeeping</strong>
            </a>
            <p class="services-description">
              Organized ledgers and monthly reconciliations. We track and sort
              every transaction, so your reports are clear and ready for audits,
              filing season, and smart decisions.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/accounting">
              <?= site_image('/images/financial-accounting.jpg', 'Financial statements on a desk', ['class' => 'thumb', 'sizes' => '(min-width: 992px) 350px, (min-width: 768px) 50vw, 100vw']) ?>
              <strong class="services-title">Financial Accounting</strong>
            </a>
            <p class="services-description">
              Clear financial statements, including balance sheets and income
              statements. See how your business is doing and where it can grow.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/payroll">
              <?= site_image('/images/payroll.jpg', 'Payroll calculations and pay stubs', ['class' => 'thumb', 'sizes' => '(min-width: 992px) 350px, (min-width: 768px) 50vw, 100vw']) ?>
              <strong class="services-title">Payroll</strong>
            </a>
            <p class="services-description">
              Accurate pay runs, deductions, and T4 slips that follow Canadian
              rules. Your team gets paid on time and your records stay precise.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5 mb-lg-0">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/personal-tax">
              <?= site_image('/images/personal-tax.jpg', 'Preparing a personal income return', ['class' => 'thumb', 'sizes' => '(min-width: 992px) 350px, (min-width: 768px) 50vw, 100vw']) ?>
              <strong class="services-title">Personal Tax</strong>
            </a>
            <p class="services-description">
              Personal returns filed in line with CRA rules. We claim every
              deduction and credit you qualify for to get you the best refund.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5 mb-md-0">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/corporate-tax">
              <?= site_image('/images/corporate-tax.jpg', 'Reviewing a corporate T2 return', ['class' => 'thumb', 'sizes' => '(min-width: 992px) 350px, (min-width: 768px) 50vw, 100vw']) ?>
              <strong class="services-title">Corporate Tax</strong>
            </a>
            <p class="services-description">
              Corporate planning and filing that follows the rules, protects
              your profits, and finds credits and savings.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6">
          <div class="services scroll-animate-scale">
            <a
              class="services-thumbnail"
              href="/business-registration">
              <?= site_image('/images/business-registration.jpg', 'Registering a new Canadian business', ['class' => 'thumb', 'sizes' => '(min-width: 992px) 350px, (min-width: 768px) 50vw, 100vw']) ?>
              <strong class="services-title">Business Registration</strong>
            </a>
            <p class="services-description">
              Help with your CRA business number, GST/HST account, and the
              other setup steps, so your business is ready to operate.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="py-70">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 order-lg-last">
          <div class="img-box scroll-animate-right">
            <video class="canada-svg" width="800" height="550" autoplay muted loop playsinline preload="none" poster="/images/canada-poster.webp" aria-label="Animated map of major Canadian cities we serve">
              <source src="/images/canada.webm" type="video/webm">
              <source src="/images/canada.mp4" type="video/mp4">
              <track kind="captions" src="/images/canada.vtt" srclang="en" label="English">
            </video>
          </div>
        </div>
        <div class="col-lg-6 ">
          <div class="scroll-animate-left">
            <h2 class="section-title">We Serve Clients Across Canada</h2>
            <p>We proudly work with clients in Canada's major cities. From coast to coast, we help businesses and individuals keep their finances in order.</p>
            <div class="location-features">
              <div class="location-feature">
                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                <span>Nationwide Coverage</span>
              </div>
              <div class="location-feature">
                <i class="fas fa-users" aria-hidden="true"></i>
                <span>Local Expertise</span>
              </div>
              <div class="location-feature">
                <i class="fas fa-clock" aria-hidden="true"></i>
                <span>Flexible Scheduling</span>
              </div>
            </div>
            <p>Whether you're in Toronto, Vancouver, Montreal, or anywhere else, our team is ready to support your local needs.</p>
            <div class="cta-action">
              <a href="/contact" class="appointment-button">
                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                Book Appointment
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Industry Stats Section -->
  <section class="industry-stats-section">
    <div class="container">
      <div class="stats-header scroll-animate">
        <h2>We are Best in the Industry</h2>
        <p>Our expertise and commitment to excellence set us apart.</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card scroll-animate-scale">
          <div class="stat-icon">
            <i class="fas fa-award" aria-hidden="true"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="7">0 </div>
            <span class="stat-number-icon">+</span>
          </div>
          <div class="stat-label">Years Experience</div>
          <div class="stat-description">
            More than 7 years of expert guidance and support to help you reach your financial goals.
          </div>
        </div>

        <div class="stat-card scroll-animate-scale">
          <div class="stat-icon">
            <i class="fas fa-file-invoice" aria-hidden="true"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="100">0 </div>
            <span class="stat-number-icon">+</span>
          </div>
          <div class="stat-label">Audits Cleared</div>
          <div class="stat-description">
            We have cleared more than 100 audits. Our careful approach gives you peace of mind.
          </div>
        </div>

        <div class="stat-card scroll-animate-scale">
          <div class="stat-icon">
            <i class="fas fa-dollar-sign" aria-hidden="true"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="1000000">0</div>
            <span class="stat-number-icon">+</span>
          </div>
          <div class="stat-label">In Client Savings</div>
          <div class="stat-description">
            Our clients save an average of 30% on fees compared to industry standards.
          </div>
        </div>

        <div class="stat-card scroll-animate-scale">
          <div class="stat-icon">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="43">0 </div>
            <span class="stat-number-icon">%</span>
          </div>
          <div class="stat-label">Growth in Revenue</div>
          <div class="stat-description">
            With our advice and planning, clients have seen an average 43% growth in revenue.
          </div>
        </div>
      </div>

      <div class="stats-cta scroll-animate">
        <a href="/contact" class="cta-button">
          Get Free Consultation
          <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    </div>
  </section>

  <section class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="img-box scroll-animate-left">
            <?= site_image('/images/business-growth.jpg', 'Chart showing business growth') ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h2 class="section-title">
              Solutions for Every Stage of Growth
            </h2>
            <p>
              We support freelancers, entrepreneurs, and corporations with
              reliable bookkeeping, payroll, and filing. Secure cloud systems keep
              your records organized, easy to reach, and CRA-compliant.
            </p>
            <strong>
              <p>Key Benefits:</p>
            </strong>
            <ul class="ul-list">
              <li>Accurate bookkeeping and reconciliations</li>
              <li>Transparent financial statements</li>
              <li>Payroll processing and T4 filings</li>
              <li>Personal and corporate tax planning</li>
              <li>Secure cloud-based software</li>
              <li>Business registration and GST/HST setup</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 order-lg-last">
          <div class="img-box scroll-animate-right">
            <?= site_image('/images/perfect-numbers.jpg', 'Calculator and checked financial report') ?>
          </div>
        </div>
        <div class="col-lg-6 ">
          <div class="scroll-animate-left">
            <h2 class="section-title">One Goal: Perfect Numbers, Every Time</h2>
            <p>
              Accuracy defines our approach. Our team pairs modern tools with
              professional judgment, so every number tells the right story. We
              keep your documents organized in secure cloud systems. Each return,
              statement, and report is checked before it reaches you.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 ">
          <div class="img-box scroll-animate-left">
            <?= site_image('/images/smart-calculation.jpg', 'Working on figures with a laptop and calculator') ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h2 class="section-title">
              Smart, Secure &amp; Cloud-Based Solutions
            </h2>
            <p>
              We use trusted software and encrypted cloud platforms to protect
              your data and make teamwork easy. Real-time updates give you a clear
              picture of your finances, anytime and anywhere. Whether you're in
              Ottawa or elsewhere in Canada, our online systems keep things
              secure, efficient, and stress-free.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 order-lg-last">
          <div class="img-box scroll-animate-right">
            <?= site_image('/images/tax-discussion.jpg', 'Client meeting with an advisor') ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-left">
            <h3 class="section-title">
              A Clear Process for Financial Confidence
            </h3>

            <ul class="ul-list">
              <li>
                <strong>Consultation:</strong> We review your goals and current
                setup.
              </li>
              <li>
                <strong>Setup &amp; Review:</strong> We organize your books and
                systems for compliance.
              </li>
              <li>
                <strong>Ongoing Support:</strong> You get regular updates and
                insights to guide smarter decisions.
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 ">
          <div class="img-box scroll-animate-left">
            <?= site_image('/images/expert-help.jpg', 'Advisor explaining a return to a client') ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h3 class="section-title">
              Simplify Tax Season with Expert Preparation
            </h3>

            <p>
              We prepare personal and corporate returns on time and in line with
              CRA rules. We check every line, claim the deductions you are owed,
              and keep your risk low, so filing season feels easy.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 order-lg-last">
          <div class="img-box scroll-animate-right">
            <?= site_image('/images/about-us.jpg', 'The Verma Accounting team') ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-left">
            <h2>About Us</h2>
            <p>
              At Verma Accounting, expertise, integrity, and precision guide all
              our work. We give clear advice and practical solutions that help
              people and businesses across Canada make informed decisions.
            </p>
            <p>
              We stay current with regulations and use modern technology, so you
              get reliable, timely information that supports growth.
            </p>
            <div class="home-byline">
              <?= site_image('/images/rishab-verma-avatar.webp', $homeReview['name']) ?>
              <span>
                Reviewed by <a href="/about"><strong><?= e($homeReview['name']) ?></strong></a>, <?= e($homeReview['role']) ?>.
                Last updated <time datetime="<?= e($homeReview['date']) ?>"><?= e(date('F j, Y', strtotime($homeReview['date']))) ?></time>.
              </span>
            </div>
            <a href="<?= e(company_phone_href()) ?>" class="cta-button orange">
              <i class="fas fa-phone" aria-hidden="true"></i>
              <?= e(company_phone()) ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- FAQ Section -->
  <section class="faq-section common-section">
    <div class="container">
      <div class="section-header scroll-animate">
        <h2>Frequently Asked Questions</h2>
        <p class="section-subtitle">
          Common questions about working with us.
        </p>
      </div>

      <?php foreach (seo_home_faqs() as $faq): ?>
      <div class="faq-item scroll-animate">
        <button type="button" class="faq-question" aria-expanded="false" onclick="toggleFAQ(this)">
          <span><?= e($faq['question']) ?></span>
          <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="faq-answer">
          <p><?= e($faq['answer']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Contact Form Section -->
  <section class="contact-form-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 mb-5 mb-lg-0">
          <div class="contact-form-content scroll-animate-left">
            <h2>Ready to Make Your Numbers Work for You?</h2>
            <p>
              Take the guesswork out of your finances. Partner with Verma Accounting
              for bookkeeping, payroll, tax, and registration support anywhere in Canada.
            </p>
            <div class="contact-info">
              <a href="<?= e(company_phone_href()) ?>">
                <div class="contact-item">
                  <i class="fas fa-phone" aria-hidden="true"></i>
                  <div>
                    <h3>Call Us</h3>
                    <?= e(company_phone()) ?>
                  </div>
                </div>
              </a>
              <a href="<?= e(company_email_href()) ?>">
                <div class="contact-item">
                  <i class="fas fa-envelope" aria-hidden="true"></i>
                  <div>
                    <h3>Email Us</h3>
                    <?= e(company_email()) ?>
                  </div>
                </div>
              </a>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <h3>We are One Message Away!</h3>
          <?php $includeService = false; include __DIR__ . '/components/site-inquiry-form.php'; ?>
        </div>
      </div>
    </div>
  </section>

</main>


<?php include 'components/footer.php'; ?>
