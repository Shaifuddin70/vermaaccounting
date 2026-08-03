<?php include 'components/header.php'; ?>

<!-- Main Content -->
<main class="main-content">
  <!-- Page Header -->
  <section class="page-header">
    <div class="container">
      <h1>Contact Verma Accounting</h1>
      <p>Ontario tax and accounting support by phone, email, or the form below. Serving Nepean, Ottawa, and clients across Canada.</p>
    </div>
    <a class="appointment-button" href="" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1'});return false;"> <i class="fas fa-calendar-alt"></i>Book an Appointment</a>
  </section>
  <section class="cta-appointment-section">
    <div class="container">
      <div class="cta-appointment-content">
        <div class="cta-text justify-content-center">
          <span class="cta-prefix">Looking for help with</span>
          <span id="typing-text">Payroll?</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Local contact / NAP -->
  <section class="common-section contact-nap-section">
    <div class="container">
      <div class="section-header-modern">
        <h2>Visit, call, or book online</h2>
        <p>Clear contact details for Verma Accounting &amp; Financial Services in Ontario.</p>
      </div>
      <div class="contact-nap-grid">
        <div class="contact-nap-card">
          <h3>Phone</h3>
          <p>
            <a href="tel:+16133186478">+1 (613) 318-6478</a>
          </p>
          <p class="contact-nap-note">Call or WhatsApp for a free consultation.</p>
        </div>
        <div class="contact-nap-card">
          <h3>Email</h3>
          <p>
            <a href="mailto:info@vermaaccounting.ca">info@vermaaccounting.ca</a>
          </p>
          <p class="contact-nap-note">We typically reply within one business day.</p>
        </div>
        <div class="contact-nap-card">
          <h3>Location</h3>
          <p>
            <strong>Verma Accounting &amp; Financial Services</strong><br>
            Les Emmerson Drive<br>
            Nepean, ON K2J 7L6<br>
            Serving clients across Ontario and Canada
          </p>
        </div>
        <div class="contact-nap-card">
          <h3>Business hours</h3>
          <p>Monday – Sunday<br>9:00 AM – 6:00 PM (Eastern Time)</p>
          <p class="contact-nap-note">Remote appointments available province-wide.</p>
        </div>
      </div>
      <div class="contact-map-wrap">
        <iframe
          title="Verma Accounting — Les Emmerson Drive, Nepean, ON"
          src="https://maps.google.com/maps?q=Les%20Emmerson%20Drive%2C%20Nepean%2C%20ON%20K2J%207L6&z=15&output=embed"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          allowfullscreen></iframe>
      </div>
    </div>
  </section>

  <!-- Contact Form Section -->
  <section class="contact-form-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 order-lg-last mb-5 mb-lg-0">

          <h3>We are One Message Away!</h3>
          <form id="my-form" action="https://formspree.io/f/xldadjdq" method="POST">

            <input type="text" name="name" placeholder="Name" required />

            <input type="text" name="phone" placeholder="Phone" required />

            <input type="email" name="email" placeholder="Email" required />

            <select name="service" id="service-select" required>
              <option value="">Select a Service</option>
              <option value="Bookkeeping">Bookkeeping</option>
              <option value="Financial Accounting">Financial Accounting</option>
              <option value="Payroll">Payroll</option>
              <option value="Personal Tax Preparation">Personal Tax Preparation</option>
              <option value="Corporate Tax Services">Corporate Tax Services</option>
              <option value="Business Registration">Business Registration</option>
              <option value="Other">Other</option>
            </select>

            <textarea name="message" rows="4" placeholder="Message" required></textarea>
            <button id="my-form-button">Submit</button>
            <p id="my-form-status"></p>
          </form>
        </div>
        <div class="col-lg-6">
          <div class="contact-form-content scroll-animate-left">
            <h2>Get In Touch</h2>
            <p class="contact-description">
              We're here to help with all your tax and accounting needs across Ontario.
              Contact us today for a consultation by phone, email, or the form.
            </p>
            <div class="contact-info">
              <a href="tel:+16133186478">
                <div class="contact-item">
                  <i class="fas fa-phone"></i>
                  <div>
                    <h4>Phone</h4>
                    +1 (613) 318-6478
                  </div>
                </div>
              </a>
              <a href="mailto:info@vermaaccounting.ca">
                <div class="contact-item">
                  <i class="fas fa-envelope"></i>
                  <div>
                    <h4>Email</h4>
                    info@vermaaccounting.ca
                  </div>
                </div>
              </a>
              <a href="" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1'});return false;">
                <div class="contact-item">
                  <i class="fas fa-calendar-alt"></i>
                  <div>
                    <h4>Book an Appointment</h4>
                    <span>Schedule a meeting with us</span>
                  </div>
                </div>
              </a>
              <div class="contact-item">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                  <h4>Service area</h4>
                  <span>Les Emmerson Drive, Nepean, ON K2J 7L6 · Ottawa · Canada-wide</span>
                </div>
              </div>
              <div class="contact-item">
                <i class="fas fa-clock"></i>
                <div>
                  <h4>Business Hours</h4>
                  <span>Mon - Sun: 9:00 AM - 6:00 PM</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>
</main>

<?php include 'components/footer.php'; ?>

<script>
  // Scroll Animation System
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-in');
        // Stop observing once animated
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  // Initialize scroll animations when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    // Observe all elements with scroll animation classes
    const animatedElements = document.querySelectorAll('.scroll-animate, .scroll-animate-left, .scroll-animate-right, .scroll-animate-scale');
    animatedElements.forEach(el => {
      observer.observe(el);
    });

    // Add stagger effect to business hours cards
    const hoursCards = document.querySelectorAll('.hours-card');
    hoursCards.forEach((card, index) => {
      if (index > 0) {
        card.classList.add(`scroll-animate-delay-${Math.min(index, 5)}`);
      }
    });
  });
</script>
