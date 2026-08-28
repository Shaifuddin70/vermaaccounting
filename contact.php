<?php include 'components/header.php'; ?>

<main class="main-content contact-page-main">
  <section class="contact-hero">
    <div class="container">
      <div class="contact-hero-inner">
        <span class="contact-hero-badge">We’re here to help</span>
        <h1>Contact Verma Accounting</h1>
        <p>
          Ontario tax and accounting support by phone, email, or the form below.
          Serving Nepean, Ottawa, and clients across Canada.
        </p>
        <div class="contact-hero-actions">
          <a href="tel:+16133186478" class="cta-button primary">
            <i class="fas fa-phone" aria-hidden="true"></i>
            +1 (613) 318-6478
          </a>
          <a
            href=""
            class="cta-button secondary"
            onclick="Calendly.initPopupWidget({url: 'https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1'});return false;">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            Book an appointment
          </a>
        </div>
      </div>
    </div>
  </section>

  <section class="contact-page">
    <div class="container">
      <div class="contact-page-grid">
        <div class="contact-page-form-card">
          <div class="contact-page-form-header">
            <h2>Send us a message</h2>
            <p class="contact-form-lead">Tell us what you need — we typically reply within one business day.</p>
          </div>
          <?php $includeService = true; $withLabels = true; include __DIR__ . '/components/site-inquiry-form.php'; ?>
        </div>

        <aside class="contact-page-aside">
          <div class="contact-page-info-list">
            <a href="tel:+16133186478" class="contact-page-info-item">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-phone"></i></span>
              <span class="contact-page-info-body">
                <strong>Phone</strong>
                <span>+1 (613) 318-6478</span>
                <small>Call or WhatsApp for a free consultation</small>
              </span>
            </a>

            <a href="mailto:info@vermaaccounting.ca" class="contact-page-info-item">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
              <span class="contact-page-info-body">
                <strong>Email</strong>
                <span>info@vermaaccounting.ca</span>
                <small>We reply within one business day</small>
              </span>
            </a>

            <div class="contact-page-info-item contact-page-info-item--static">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
              <span class="contact-page-info-body">
                <strong>Location</strong>
                <span>Les Emmerson Drive, Nepean, ON K2J 7L6</span>
                <small>Serving Ontario and Canada-wide</small>
              </span>
            </div>

            <div class="contact-page-info-item contact-page-info-item--static">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
              <span class="contact-page-info-body">
                <strong>Business hours</strong>
                <span>Monday – Sunday, 9:00 AM – 6:00 PM ET</span>
                <small>Remote appointments available province-wide</small>
              </span>
            </div>
          </div>

          <div class="contact-page-aside-card">
            <h3>Prefer to talk first?</h3>
            <p>Schedule a free 30-minute consultation and we’ll walk through your questions.</p>
            <a
              href=""
              class="cta-button orange"
              onclick="Calendly.initPopupWidget({url: 'https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1'});return false;">
              <i class="fas fa-calendar-check" aria-hidden="true"></i>
              Book on Calendly
            </a>
          </div>
        </aside>
      </div>

      <div class="contact-page-map">
        <div class="contact-page-map-header">
          <h2>Find us</h2>
          <p>Verma Accounting &amp; Financial Services — Les Emmerson Drive, Nepean, ON</p>
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
    </div>
  </section>
</main>

<?php include 'components/footer.php'; ?>
