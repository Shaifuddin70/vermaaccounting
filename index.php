<?php include 'components/header.php'; ?>
<!-- Main Content -->
<main class="main-content">
  <!-- Hero Section -->
  <section class="hero-section">
    <!-- Background Slider -->
    <div class="hero-slider">
      <div class="hero-slide active">
        <img src="/images/hero-bg-2.jpg" alt="Hero Background 1" class="hero-slide-image">
      </div>
      <div class="hero-slide">
        <img src="/images/hero-bg-3.jpg" alt="Hero Background 2" class="hero-slide-image">
      </div>
      <div class="hero-slide">
        <img src="/images/hero-bg-4.jpg" alt="Hero Background 3" class="hero-slide-image">
      </div>
    </div>

    <div class="hero-container">
      <div class="hero-content">
        <!-- Left Content -->
        <div class="hero-text scroll-animate">
          <div class="hero-badge">
            <i class="fas fa-award"></i>
            <span>Trusted by 10000+ Clients</span>
          </div>

          <h1 class="hero-title">
            <span class="title-highlight">Accounting Done Right,</span>
            <br>Every Time
          </h1>

          <p class="hero-description">
            One Goal: Perfect Numbers, Every Time. Verma Accounting delivers accurate bookkeeping, payroll, tax, and business
            registration services for individuals and businesses across Canada.
          </p>

          <!-- Trust Indicators -->

          <div class="rating-display">
            <div class="stars">
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
              <i class="fas fa-star"></i>
            </div>
            <span class="rating-text"><span>5</span>/5.0</span>
          </div>


          <!-- CTA Buttons -->
          <div class="hero-cta">
            <a href="/contact" class="cta-button primary">

              <span>Get Free Consultation</span>
              <i class="fas fa-arrow-right"></i>
            </a>
            <a href="/services" class="cta-button secondary">
              <i class="fas fa-calculator"></i>
              <span>Explore Services</span>
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
          <a class="appointment-button" href="" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1'});return false;"> <i class="fas fa-calendar-alt"></i>Book an Appointment</a>
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
        <div class="partner-track" id="partnerTrack">
          <div class="partner-item">
            <img src="images/FreshBooks_logo_2020.svg.png" alt="FreshBooks" />
          </div>
          <div class="partner-item">
            <img src="images/quickbooks.png" alt="QuickBooks" />
          </div>
          <div class="partner-item">
            <img src="images/Sage_Group_logo_2022.svg.png" alt="Sage" />
          </div>
          <div class="partner-item">
            <img src="images/Wave_logo_RGB.png" alt="Wave" />
          </div>
          <div class="partner-item">
            <img src="images/xero_logo_icon_167949.png" alt="Xero" />
          </div>
          <div class="partner-item">
            <img src="images/Zoho-Books-logo.png" alt="Zoho Books" />
          </div>
          <!-- Duplicate for seamless loop -->
          <div class="partner-item">
            <img src="images/FreshBooks_logo_2020.svg.png" alt="FreshBooks" />
          </div>
          <div class="partner-item">
            <img src="images/quickbooks.png" alt="QuickBooks" />
          </div>
          <div class="partner-item">
            <img src="images/Sage_Group_logo_2022.svg.png" alt="Sage" />
          </div>
          <div class="partner-item">
            <img src="images/Wave_logo_RGB.png" alt="Wave" />
          </div>
          <div class="partner-item">
            <img src="images/xero_logo_icon_167949.png" alt="Xero" />
          </div>
          <div class="partner-item">
            <img src="images/Zoho-Books-logo.png" alt="Zoho Books" />
          </div>
        </div>
        <div class="partner-dots">
          <span class="dot active" onclick="currentSlide(1)"></span>
          <span class="dot" onclick="currentSlide(2)"></span>
          <span class="dot" onclick="currentSlide(3)"></span>
          <span class="dot" onclick="currentSlide(4)"></span>
          <span class="dot" onclick="currentSlide(5)"></span>
          <span class="dot" onclick="currentSlide(6)"></span>
        </div>
      </div>
    </div>
  </section>

  <div class="common-section bg-white">
    <div class="container scroll-animate-left">
      <div class="widget-container ">
        <div class="elfsight-app-a9eff633-ffca-4ea6-bbac-14d16ba7f88f"></div>
      </div>
    </div>
  </div>
  <section class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 order-lg-last">
          <div class="img-box scroll-animate-right">
            <img src="/images/calculation.jpg" alt="Tax calculation" />
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-left">
            <h2 class="section-title">Global-Ready Financial Management</h2>
            <p>
              Precision, compliance, and clarity for businesses and individuals
              across Canada and beyond. Every number is reviewed with care, giving
              you confidence in your financial decisions
            </p>
            <div class="cta-buttons left">
              <a href="/contact" class="cta-button primary">
                Get Free Consultation
              </a>
              <a href="tel:613-318-6478" class="cta-button orange">
                <i class="fas fa-phone"></i>
                613-318-6478
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="img-box scroll-animate-left">
            <img src="/images/happy-client.jpg" alt="Happy Client" />
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h2 class="section-title">
              Financial Management Made Simple with Verma Accounting
            </h2>
            <p>
              Strong businesses run on clarity, and that begins with precise
              accounting. At Verma Accounting, we make financial
              management straightforward and dependable—from bookkeeping and
              payroll to CRA business registration and tax filing. Every figure is
              reviewed with precision and purpose, guided by
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
          Every service is designed to bring accuracy, compliance, and clarity to
          your finances.
        </p>
      </div>
      <div class="row mt-4">
        <div class="col-lg-4 col-md-6 mb-5">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/services#bookkeeping">
              <img
                class="thumb"
                src="/images/book-keeping.jpg"
                alt="Bookkeeping services" />

              <strong class="services-title">Bookkeeping</strong>
            </a>
            <p class="services-description">
              Maintain organized ledgers and reconciliations, tracking every
              transaction and categorizing expenses to provide clear, accurate
              reports that support audits, tax preparation, and informed
              business decisions.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/services#accounting">
              <img
                class="thumb"
                src="/images/financial-accounting.jpg"
                alt="Financial Accounting services" />

              <strong class="services-title">Financial Accounting</strong>
            </a>
            <p class="services-description">
              Deliver transparent financial statements, including balance sheets
              and income statements, offering insights into business
              performance, financial health, and strategic growth opportunities.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/services#payroll">
              <img
                class="thumb"
                src="/images/payroll.jpg"
                alt="Payroll services" />

              <strong class="services-title">Payroll Services</strong>
            </a>
            <p class="services-description">
              Ensure accurate payroll processing, deductions, and T4 filings,
              complying with Canadian regulations while keeping employee
              payments timely and records precise.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5 mb-lg-0">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/services#personal-tax">
              <img
                class="thumb"
                src="/images/personal-tax.jpg"
                alt="Personal Tax services" />

              <strong class="services-title">Personal Tax Services</strong>
            </a>
            <p class="services-description">
              Prepare personal tax returns with full CRA compliance, maximizing
              eligible deductions and credits to ensure accurate filings and
              optimized refunds.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-5 mb-md-0">
          <div class="services scroll-animate-scale">
            <a class="services-thumbnail" href="/services#corporate-tax">
              <img
                class="thumb"
                src="/images/corporate-tax.jpg"
                alt="Corporate Tax services" />

              <strong class="services-title">Corporate Tax Services</strong>
            </a>
            <p class="services-description">
              Provide corporate tax planning and filing that aligns with
              regulations, protects profits, and identifies opportunities for
              credits and savings.
            </p>
          </div>
        </div>

        <div class="col-lg-4 col-md-6">
          <div class="services scroll-animate-scale">
            <a
              class="services-thumbnail"
              href="/services#business-registration">
              <img
                class="thumb"
                src="/images/business-registration.jpg"
                alt="Business Registration services" />

              <strong class="services-title">Business Registration</strong>
            </a>
            <p class="services-description">
              Assist with CRA business number, GST/HST registration, and
              compliance steps to ensure your business is correctly registered
              and ready to operate.
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
            <img src="/images/canada.gif" alt="Map of Major Canadian Cities Visited" class="canada-svg" loading="lazy" />
          </div>
        </div>
        <div class="col-lg-6 ">
          <div class="scroll-animate-left">
            <h2 class="section-title">We Serve Clients Across Canada</h2>
            <p>We proudly serve clients across Canada's major metropolitan areas. Our accounting expertise spans from coast to coast, providing comprehensive financial services to businesses and individuals in key Canadian cities.</p>
            <div class="location-features">
              <div class="location-feature">
                <i class="fas fa-map-marker-alt"></i>
                <span>Nationwide Coverage</span>
              </div>
              <div class="location-feature">
                <i class="fas fa-users"></i>
                <span>Local Expertise</span>
              </div>
              <div class="location-feature">
                <i class="fas fa-clock"></i>
                <span>Flexible Scheduling</span>
              </div>
            </div>
            <p>Whether you're in Toronto, Vancouver, Montreal, or any other major Canadian city, our team is ready to provide you with professional accounting services tailored to your local business needs.</p>
            <div class="cta-action">
              <a href="/contact" class="appointment-button">
                <i class="fas fa-calendar-alt"></i>
                Book Appointment
              </a>
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
            <i class="fas fa-award"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="7">0 </div>
            <span class="stat-number-icon">+</span>
          </div>
          <div class="stat-label">Years Experience</div>
          <div class="stat-description">
            More than 7+ years of experience providing expert guidance and support to help you achieve your financial goals.
          </div>
        </div>

        <div class="stat-card scroll-animate-scale">
          <div class="stat-icon">
            <i class="fas fa-file-invoice"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="100">0 </div>
            <span class="stat-number-icon">+</span>
          </div>
          <div class="stat-label">Audits Cleared</div>
          <div class="stat-description">
            We successfully cleared over 100+ audits. Our meticulous approach guarantees peace of mind and financial integrity.
          </div>
        </div>

        <div class="stat-card scroll-animate-scale">
          <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="1000000">0</div>
            <span class="stat-number-icon">+</span>
          </div>
          <div class="stat-label">In Tax Savings</div>
          <div class="stat-description">
            Our clients save an average of 30% on their accounting and tax services compared to industry standards.
          </div>
        </div>

        <div class="stat-card scroll-animate-scale">
          <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="stat-number-wrapper">
            <div class="stat-number" data-target="43">0 </div>
            <span class="stat-number-icon">%</span>
          </div>
          <div class="stat-label">Growth in Revenue</div>
          <div class="stat-description">
            With our financial advice and strategies, our clients have seen an average 43% growth in their business revenue.
          </div>
        </div>
      </div>

      <div class="stats-cta scroll-animate">
        <a href="/contact" class="cta-button">
          Get Free Consultation
          <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
  </section>

  <section class="common-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="img-box scroll-animate-left">
            <img src="/images/business-growth.jpg" alt="Business Growth" />
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h2 class="section-title">
              Accounting Solutions for Every Stage of Growth
            </h2>
            <p>
              We support freelancers, entrepreneurs, and corporations with
              reliable accounting, payroll, and tax services. Using secure
              cloud-based systems, we keep records organized, accessible, and
              fully CRA-compliant.
            </p>
            <strong>
              <p>Key Benefits:</p>
            </strong>
            <ul class="ul-list">
              <li>Accurate bookkeeping and reconciliations</li>
              <li>Transparent financial statements</li>
              <li>Payroll processing and T4 filings</li>
              <li>Personal and corporate tax planning</li>
              <li>Secure cloud-based accounting tools</li>
              <li>CRA-compliant business registration and GST/HST setup</li>
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
            <img src="/images/perfect-numbers.jpg" alt="Perfect numbers" />
          </div>
        </div>
        <div class="col-lg-6 ">
          <div class="scroll-animate-left">
            <h2 class="section-title">One Goal: Perfect Numbers, Every Time</h2>
            <p>
              Accuracy defines our approach. Our accountants blend advanced tools
              with professional insight to ensure every number tells the right
              story. Using secure, cloud-based systems, we keep documents
              organized and accessible while maintaining full CRA compliance. Each
              return, statement, and report is verified for precision before it
              reaches you.
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
            <img src="/images/smart-calculation.jpg" alt="Smart calculation" />
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h2 class="section-title">
              Smart, Secure & Cloud-Based Accounting Solutions
            </h2>
            <p>
              We use trusted accounting software and encrypted cloud platforms to
              protect your data and simplify collaboration. Real-time updates and
              transparent reporting give you a clear picture of your
              finances—anytime, anywhere. Whether you're in Ottawa or elsewhere in
              Canada, our online systems make accounting secure, efficient, and
              stress-free.
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
            <img src="/images/tax-discussion.jpg" alt="Tax discussion" />
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
                <strong> Setup & Review: </strong> Books and systems are organized
                for compliance.
              </li>
              <li>
                <strong>Ongoing Support:</strong> You receive consistent updates
                and insights to guide smarter financial decisions.
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
            <img src="/images/expert-help.jpg" alt="Expert help" />
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-right">
            <h3 class="section-title">
              Simplify Tax Season with Expert Preparation
            </h3>

            <p>
              We prepare personal and corporate tax returns with full CRA
              compliance, making sure of timely filings, accurate deductions, and
              minimized risk. Every line item is reviewed carefully to keep your
              filings accurate, minimize risk, and make tax season stress-free.
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
            <img src="/images/about-us.jpg" alt="About us" />
          </div>
        </div>
        <div class="col-lg-6">
          <div class="scroll-animate-left">
            <h2>About Us</h2>
            <p>
              At Verma Accounting, expertise, integrity, and
              precision define all our work. We provide clear guidance and
              practical solutions that help individuals and businesses across
              Canada make informed financial decisions at every step.
            </p>
            <p>
              By staying current with regulations and leveraging modern accounting
              technology, we provide reliable, timely information that supports
              growth.
            </p>
            <a href="tel:613-318-6478" class="cta-button orange">
              <i class="fas fa-phone"></i>
              613-318-6478
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
          Common questions about our accounting and bookkeeping services.
        </p>
      </div>

      <div class="faq-item scroll-animate">
        <button class="faq-question" onclick="toggleFAQ(this)">
          <span>Do you offer online or remote accounting services?</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
          <p>
            Yes. Using secure, cloud-based systems, we can manage your accounts,
            filings, and financial documents remotely, no matter where you are
            in Canada.
          </p>
        </div>
      </div>

      <div class="faq-item scroll-animate">
        <button class="faq-question" onclick="toggleFAQ(this)">
          <span>Can you help with both personal and corporate taxes?</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
          <p>
            Absolutely. We prepare and file personal and corporate tax returns
            with full CRA compliance while maximizing eligible deductions and
            credits.
          </p>
        </div>
      </div>

      <div class="faq-item scroll-animate">
        <button class="faq-question" onclick="toggleFAQ(this)">
          <span>Do you assist with business registration?</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
          <p>
            Yes. We handle CRA business numbers, GST/HST registration, payroll
            accounts, and other compliance steps to ensure your business is
            legally registered and ready to operate.
          </p>
        </div>
      </div>

      <div class="faq-item scroll-animate">
        <button class="faq-question" onclick="toggleFAQ(this)">
          <span>How do you ensure accuracy in accounting and tax filings?</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
          <p>
            Every ledger, statement, and return is double-checked. Our
            accountants combine professional expertise with advanced tools and
            secure systems to ensure precision and CRA compliance.
          </p>
        </div>
      </div>

      <div class="faq-item scroll-animate">
        <button class="faq-question" onclick="toggleFAQ(this)">
          <span>Will I receive ongoing support after tax filing or setup?</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
          <p>
            Yes. We provide continuous updates, insights, and guidance to help
            you make informed financial decisions throughout the year.
          </p>
        </div>
      </div>

      <div class="faq-item scroll-animate">
        <button class="faq-question" onclick="toggleFAQ(this)">
          <span>What makes your bookkeeping and accounting services
            different?</span>
          <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
          <p>
            Our approach blends accuracy, clarity, and compliance. Using
            cloud-based tools, we provide real-time access to organized records,
            detailed reports, and audit-ready financial statements.
          </p>
        </div>
      </div>
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
              Take the uncertainty out of accounting. Partner with Verma Accounting
              for bookkeeping, payroll, tax, and registration
              support anywhere in Canada.
            </p>
            <div class="contact-info">
              <a href="tel:613-318-6478">
                <div class="contact-item">
                  <i class="fas fa-phone"></i>
                  <div>
                    <h4>Call Us</h4>
                    613-318-6478
                  </div>
                </div>
              </a>
              <a href="mailto:info@vermaaccounting.ca">
                <div class="contact-item">
                  <i class="fas fa-envelope"></i>
                  <div>
                    <h4>Email Us</h4>
                    info@vermaaccounting.ca
                  </div>
                </div>
              </a>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <h3>We are One Message Away!</h3>
          <form id="my-form" action="https://formspree.io/f/xeopqjjr" method="POST">

            <input type="text" name="name" placeholder="Name" required />

            <input type="text" name="phone" placeholder="Phone" required />

            <input type="email" name="email" placeholder="Email" required />

            <textarea name="message" rows="4" placeholder="Message" required></textarea>
            <button id="my-form-button">Submit</button>
            <p id="my-form-status"></p>
          </form>
        </div>
      </div>
    </div>
  </section>

</main>


<?php include 'components/footer.php'; ?>