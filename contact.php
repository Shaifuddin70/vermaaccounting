<?php include 'components/header.php'; ?>

<!-- Main Content -->
<main class="main-content">
  <!-- Page Header -->
  <section class="page-header">
    <div class="container">
      <h1>Contact Verma Accounting</h1>
      <p>Reach our Ontario tax and accounting team by phone, email, or the form below.</p>
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
              We're here to help with all your tax and accounting needs. Contact
              us today for a consultation.
            </p>
            <div class="contact-info">
              <a href="tel:613-318-6478">
                <div class="contact-item">
                  <i class="fas fa-phone"></i>
                  <div>
                    <h4>Phone</h4>
                    613-318-6478
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