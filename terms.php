<?php
require_once __DIR__ . '/lib/bootstrap.php';
$breadcrumbTitle = 'Terms of Service';
include __DIR__ . '/components/header.php';
?>

<main class="main-content">
  <section class="page-header">
    <div class="container">
      <h1>Terms of Service</h1>
      <p>The terms that apply when you use the Verma Accounting &amp; Financial Services website</p>
    </div>
  </section>

  <section class="legal-page-section">
    <div class="container">
      <article class="legal-content">
        <p class="legal-updated">Last updated: <time datetime="2026-10-16">October 16, 2026</time></p>

        <p>
          These Terms of Service (“Terms”) govern your use of vermaaccounting.ca (the “website”), operated by
          Verma Accounting &amp; Financial Services (“Verma Accounting,” “we,” “us,” or “our”). By using the website,
          you agree to these Terms. If you do not agree, please do not use the website.
        </p>

        <h2>Information only, not professional advice</h2>
        <p>
          Articles, guides, checklists, calculators, and other content on this website are general information about
          Canadian tax, accounting, payroll, and business topics. They are not tax, legal, or financial advice for your
          situation. Tax rules change often, and the right answer depends on your facts. Please speak with us before
          acting on anything you read here.
        </p>

        <h2>No client relationship until engaged</h2>
        <p>
          Using the website, sending a message, submitting a form, or booking a consultation does not by itself create
          an accountant–client relationship. That relationship starts only when we confirm in writing that we will
          provide services to you, usually through an engagement letter that sets out the scope of work and fees.
        </p>

        <h2>Forms, uploads, and appointments</h2>
        <ul>
          <li>You agree to give accurate information and to upload only documents you have the right to share.</li>
          <li>Do not upload anything unlawful, harmful, or unrelated to the services you are requesting.</li>
          <li>Appointment times booked online are confirmed by the scheduling tool and may be rescheduled by either party.</li>
          <li>We handle personal information as described in our <a href="/privacy">Privacy Policy</a>.</li>
        </ul>

        <h2>Acceptable use</h2>
        <p>
          You agree not to misuse the website. This includes trying to gain unauthorized access, interfering with its
          operation, sending spam through our forms, or scraping content in a way that burdens our systems.
        </p>

        <h2>Intellectual property</h2>
        <p>
          The website’s text, graphics, logos, and design belong to Verma Accounting or its licensors. You may view and
          share pages for personal, non-commercial use. Please do not copy or republish our content without permission.
          Third-party names and logos (for example, accounting software partners) belong to their owners.
        </p>

        <h2>Third-party links and tools</h2>
        <p>
          The website links to and uses third-party services, such as scheduling, reviews, and government websites. We
          are not responsible for their content, availability, or privacy practices.
        </p>

        <h2>Limitation of liability</h2>
        <p>
          We work to keep the website accurate and available, but it is provided “as is.” To the extent permitted by law,
          Verma Accounting is not liable for any loss arising from your use of, or reliance on, the website or its
          content. This does not limit our responsibilities under a signed engagement for professional services.
        </p>

        <h2>Changes to these Terms</h2>
        <p>
          We may update these Terms from time to time. Changes take effect when posted on this page with a new
          “Last updated” date.
        </p>

        <h2>Governing law</h2>
        <p>
          These Terms are governed by the laws of the Province of Ontario and the federal laws of Canada that apply there.
        </p>

        <h2>Contact</h2>
        <p>
          Questions about these Terms? Email
          <a href="<?= e(company_email_href()) ?>"><?= e(company_email()) ?></a>,
          call <a href="<?= e(company_phone_href()) ?>"><?= e(company_phone()) ?></a>,
          or visit our <a href="/contact">Contact page</a>.
        </p>
      </article>
    </div>
  </section>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
