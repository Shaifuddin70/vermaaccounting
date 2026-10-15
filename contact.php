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
          <a href="<?= e(company_phone_href()) ?>" class="cta-button primary">
            <i class="fas fa-phone" aria-hidden="true"></i>
            <?= e(company_phone()) ?>
          </a>
          <a
            href="https://calendly.com/vermaaccounting-info/30min"
            target="_blank"
            rel="noopener"
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
            <a href="<?= e(company_phone_href()) ?>" class="contact-page-info-item">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-phone"></i></span>
              <span class="contact-page-info-body">
                <strong>Phone</strong>
                <span><?= e(company_phone()) ?></span>
                <small>Call or WhatsApp for a free consultation</small>
              </span>
            </a>

            <a href="<?= e(company_email_href()) ?>" class="contact-page-info-item">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
              <span class="contact-page-info-body">
                <strong>Email</strong>
                <span><?= e(company_email()) ?></span>
                <small>We reply within one business day</small>
              </span>
            </a>

            <?php if (CmsCompany::addressLine() !== ''): ?>
            <div class="contact-page-info-item contact-page-info-item--static">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
              <span class="contact-page-info-body">
                <strong>Location</strong>
                <span><?= e(CmsCompany::addressLine()) ?></span>
                <small>Serving Ontario and Canada-wide</small>
              </span>
            </div>
            <?php endif; ?>

            <?php if (CmsCompany::hours() !== ''): ?>
            <div class="contact-page-info-item contact-page-info-item--static">
              <span class="contact-page-info-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
              <span class="contact-page-info-body">
                <strong>Business hours</strong>
                <span><?= e(CmsCompany::hours()) ?></span>
                <small>Remote appointments available province-wide</small>
              </span>
            </div>
            <?php endif; ?>
          </div>

          <div class="contact-page-aside-card">
            <h3>Prefer to talk first?</h3>
            <p>Schedule a free 30-minute consultation and we’ll walk through your questions.</p>
            <a
              href="https://calendly.com/vermaaccounting-info/30min"
              target="_blank"
              rel="noopener"
              class="cta-button orange"
              onclick="Calendly.initPopupWidget({url: 'https://calendly.com/vermaaccounting-info/30min?hide_gdpr_banner=1'});return false;">
              <i class="fas fa-calendar-check" aria-hidden="true"></i>
              Book on Calendly
            </a>
          </div>
        </aside>
      </div>

      <?php if (CmsCompany::addressLine() !== ''): ?>
      <div class="contact-page-map">
        <div class="contact-page-map-header">
          <h2>Find us</h2>
          <p>Verma Accounting &amp; Financial Services — <?= e(CmsCompany::addressLine()) ?></p>
        </div>
        <div class="contact-map-wrap">
          <iframe
            title="Verma Accounting — <?= e(CmsCompany::addressLine()) ?>"
            src="https://maps.google.com/maps?q=<?= e(rawurlencode(CmsCompany::addressLine())) ?>&z=15&output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen></iframe>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include 'components/footer.php'; ?>
