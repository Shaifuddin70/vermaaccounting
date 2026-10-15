<?php
require_once __DIR__ . '/lib/bootstrap.php';
$breadcrumbTitle = 'Editorial Policy';
include __DIR__ . '/components/header.php';
?>

<main class="main-content">
  <section class="page-header">
    <div class="container">
      <h1>Editorial Policy</h1>
      <p>How we write, review, and update the information on this website</p>
    </div>
  </section>

  <section class="legal-page-section">
    <div class="container">
      <article class="legal-content">
        <p class="legal-updated">Last updated: <time datetime="2026-10-16">October 16, 2026</time></p>

        <p>
          Our clients rely on us for accurate numbers, and they should be able to rely on what we publish too. This
          policy explains how Verma Accounting &amp; Financial Services creates the service pages, blog articles,
          guides, and resources on vermaaccounting.ca.
        </p>

        <h2>Who writes our content</h2>
        <p>
          Content is written or drafted by our team and reviewed by a Verma Accounting professional with hands-on
          experience in Canadian personal tax, corporate tax, bookkeeping, and payroll. Each article shows the author
          or reviewer and the date it was published or last updated.
        </p>

        <h2>Sources we use</h2>
        <ul>
          <li>Official government sources first, such as the Canada Revenue Agency (CRA), the Government of Canada, and the Government of Ontario</li>
          <li>Legislation, CRA guides, forms, and published rates and thresholds</li>
          <li>Our own experience preparing returns and keeping books for Canadian individuals and businesses</li>
        </ul>
        <p>Where a figure or rule comes from an outside source, we link to it so you can check it yourself.</p>

        <h2>Review and accuracy</h2>
        <p>
          Before publishing, a reviewer checks every article for technical accuracy, clarity, and whether it still matches
          current CRA rules. We avoid promising specific outcomes, because every tax situation is different.
        </p>

        <h2>Keeping content up to date</h2>
        <p>
          Tax rates, deadlines, and limits change every year. We review key pages before each tax season and whenever
          rules change. When we make a meaningful update, we change the “last updated” date on the page.
        </p>

        <h2>Corrections</h2>
        <p>
          If you spot an error, please tell us at
          <a href="<?= e(company_email_href()) ?>"><?= e(company_email()) ?></a>.
          We review every report and correct confirmed mistakes promptly.
        </p>

        <h2>Independence</h2>
        <p>
          We do not accept payment to publish or recommend products. Software we mention, such as the accounting tools
          we work with, is included because we use it with clients, not because of paid placement.
        </p>

        <h2>Not personal advice</h2>
        <p>
          Our content is general information, not tax, legal, or financial advice for your situation. See our
          <a href="/terms">Terms of Service</a>, or <a href="/contact">contact us</a> to talk about your own needs.
        </p>
      </article>
    </div>
  </section>
</main>

<?php include __DIR__ . '/components/footer.php'; ?>
