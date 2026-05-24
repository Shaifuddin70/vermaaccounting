<?php
require_once __DIR__ . '/lib/bootstrap.php';
include 'components/header.php';
?>
<!-- Main Content -->
<main class="main-content">
    <!-- Modern Hero Section -->
    <section class="resources-hero-modern">
        <div class="hero-background-pattern"></div>
        <div class="resources-hero-container">
            <div class="resources-hero-content scroll-animate">

                <h1 class="resources-hero-title">
                    Everything You Need for
                    <span class="gradient-text">Tax Filing</span>
                </h1>
                <p class="resources-hero-description">
                    Comprehensive document checklists tailored to your employment type and income sources.
                    Select your category below to access your personalized document requirements.
                </p>
                <div class="cta-buttons">
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
    </section>

    <?php
    // Embed custom forms with shortcode: [form slug="your-form-slug" height="720"]
    // Only published forms appear. Create the form in Admin → Form Builder first.
    $resourcesFormEmbed = process_form_shortcodes('[form slug="resources-upload" height="720"]');
    if (str_contains($resourcesFormEmbed, 'form-embed')):
    ?>
    <section class="common-section" id="resources-online-forms">
        <div class="container">
            <div class="section-header-modern">
                <h2>Submit Documents Online</h2>
                <p>Secure forms for uploading tax documents and client information</p>
            </div>
            <?= $resourcesFormEmbed ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Modern Resources Cards Section -->
    <section class="common-section">
        <div class="container">
            <div class="section-header-modern">
                <h2>Select Your Category</h2>
                <p>Choose the category that best describes your situation to view required documents</p>
            </div>
            <div class="resources-grid-modern">
                <!-- NewComer Card -->
                <a href="#newcomer-documents" class="resource-card-modern scroll-animate-scale">
                    <div class="card-icon-wrapper">
                        <div class="resource-card-icon">
                            <i class="fas fa-passport"></i>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="resource-card-title">NewComer</h3>
                        <p class="resource-card-description">
                            Documents for newcomers to Canada
                        </p>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>
                <!-- Student Card -->
                <a href="#student-documents" class="resource-card-modern scroll-animate-scale">
                    <div class="card-icon-wrapper">
                        <div class="resource-card-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="resource-card-title">Student</h3>
                        <p class="resource-card-description">
                            Tax filing documents for students
                        </p>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>
                <!-- Employee Card -->
                <a href="#employee-documents" class="resource-card-modern scroll-animate-scale">
                    <div class="card-icon-wrapper">
                        <div class="resource-card-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="resource-card-title">Employee</h3>
                        <p class="resource-card-description">
                            View required documents for employees filing their taxes
                        </p>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>

                <!-- Self-Employed Card -->
                <a href="#self-employed-documents" class="resource-card-modern scroll-animate-scale">
                    <div class="card-icon-wrapper">
                        <div class="resource-card-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="resource-card-title">Self-Employed</h3>
                        <p class="resource-card-description">
                            Documents for self-employed individuals and freelancers
                        </p>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>

                <!-- Driver Card -->
                <a href="#driver-documents" class="resource-card-modern scroll-animate-scale">
                    <div class="card-icon-wrapper">
                        <div class="resource-card-icon">
                            <i class="fas fa-car"></i>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="resource-card-title">Driver</h3>
                        <p class="resource-card-description">
                            Documents for drivers and transportation workers
                        </p>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>

                <!-- Rental Income Card -->
                <a href="#rental-income-documents" class="resource-card-modern scroll-animate-scale">
                    <div class="card-icon-wrapper">
                        <div class="resource-card-icon">
                            <i class="fas fa-home"></i>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="resource-card-title">Rental Income</h3>
                        <p class="resource-card-description">
                            Documents required for rental income reporting
                        </p>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>

                <!-- Sold a Property Card -->
                <a href="#property-sale-documents" class="resource-card-modern scroll-animate-scale">
                    <div class="card-icon-wrapper">
                        <div class="resource-card-icon">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="resource-card-title">Property Sale</h3>
                        <p class="resource-card-description">
                            Documents for property sales transactions
                        </p>
                        <div class="card-arrow">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </a>



            </div>
        </div>
    </section>

    <!-- NewComer Documents Section -->
    <section class="common-section" id="newcomer-documents">
        <div class="container">
            <div class="resource-content-wrapper scroll-animate">
                <div class="resource-header">
                    <div class="resource-icon-inline">
                        <i class="fas fa-passport"></i>
                    </div>
                    <div class="resource-title-wrapper">
                        <h2 class="section-title">Newcomer to Canada - Document Checklist</h2>
                        <p class="resource-detail-subtitle">Documents to prepare before your tax appointment</p>
                    </div>
                </div>
                <strong>Personal & Income Information</strong>
                <ul class="resource-detail-list count-md-2">
                    <li>Date you arrived in Canada</li>
                    <li>Foreign income and foreign taxes paid</li>
                    <li>All T4 and other Canadian tax slips</li>
                    <li>Self-employment income and expenses (if applicable)</li>
                    <li>Form T2200 (Conditions of Employment) if you worked from home</li>
                    <li>Tuition Form T2202A (if you attended school)</li>
                    <li>Medical expenses (Jan-Dec)</li>
                    <li>Charitable and/or political donations</li>
                    <li>RRSP or Spousal RRSP contributions</li>
                    <li>Childcare or day-care receipts</li>
                    <li>Spousal support paid or received</li>
                    <li>Spouse's foreign income (if non-resident)</li>
                </ul>

                <div class="resource-detail-note">
                    <strong>If You Trade Investments</strong>
                    <ul class="resource-detail-list">
                        <li>T5008 (Securities Transactions) for capital gains/losses</li>
                        <li>Summary of interest paid to broker or credit line</li>
                    </ul>
                </div>
                <div class="resource-detail-note">
                    <strong>Common Forms & Documents</strong>
                    <ul class="resource-detail-list">
                        <li>Prior-year Notice of Assessment (NOA)</li>
                        <li>CRA correspondence or installment summaries</li>
                        <li>RRSP, donation, tuition, childcare, and medical receipts</li>
                        <li>Proof of foreign taxes paid or income earned abroad</li>
                    </ul>
                </div>
                <a href="resources-pdf/New-Comer.pdf" class="resource-download-button" download>
                    <i class="fas fa-download"></i> Download Documents
                </a>
            </div>
        </div>
    </section>

    <!-- Student Documents Section -->
    <section class="common-section" id="student-documents">
        <div class="container">
            <div class="resource-content-wrapper scroll-animate">
                <div class="resource-header">
                    <div class="resource-icon-inline">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="resource-title-wrapper">
                        <h2 class="section-title">Student - Document Checklist</h2>
                        <p class="resource-detail-subtitle">Documents to prepare before your tax appointment</p>
                    </div>
                </div>

                <strong>Education & Income Information</strong>
                <ul class="resource-detail-list count-md-2">
                    <li>T4 and other employment slips</li>
                    <li>T4A (scholarships or bursaries)</li>
                    <li>Tuition Form T2202A</li>
                    <li>Receipts for eligible medical expenses</li>
                    <li>RRSP or Spousal RRSP contributions</li>
                    <li>Rent or property tax receipts</li>
                    <li>Donation receipts</li>
                    <li>Childcare expenses</li>
                    <li>Spousal support records</li>
                    <li>Foreign income (if any)</li>
                </ul>

                <div class="resource-detail-note">
                    <strong>If You Trade Investments</strong>
                    <ul class="resource-detail-list">
                        <li>T5008 (Securities Transactions)</li>
                        <li>Broker interest summary</li>
                    </ul>
                </div>
                <a href="resources-pdf/Student.pdf" class="resource-download-button" download>
                    <i class="fas fa-download"></i> Download Documents
                </a>
            </div>
        </div>
    </section>

    <!-- Employee Documents Section -->
    <section class="common-section" id="employee-documents">
        <div class="container">
            <div class="resource-content-wrapper scroll-animate">
                <div class="resource-header">
                    <div class="resource-icon-inline">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="resource-title-wrapper">
                        <h2 class="section-title">Employed (Including Commission Income) - Document Checklist</h2>
                        <p class="resource-detail-subtitle">Documents to prepare before your tax appointment</p>
                    </div>
                </div>

                <strong>Employment Information</strong>
                <ul class="resource-detail-list count-md-2">
                    <li>T4 slips from all employers</li>
                    <li>Form T2200 (Conditions of Employment) for work-from-home claims</li>
                    <li>List of employment expenses supported by T2200</li>
                    <li>Medical expense receipts (net of insurance)</li>
                    <li>RRSP and donation receipts</li>
                    <li>Rent or property tax details</li>
                    <li>Childcare or day-care receipts</li>
                    <li>Spousal support documents</li>
                    <li>Tuition Form T2202A (if applicable)</li>
                    <li>Foreign income records</li>
                </ul>

                <div class="resource-detail-note">
                    <strong>If You Trade Investments</strong>
                    <ul class="resource-detail-list">
                        <li>T5008 (Securities Transactions)</li>
                        <li>Broker or credit interest summary</li>
                    </ul>
                </div>
                <a href="resources-pdf/Employed.pdf" class="resource-download-button" download>
                    <i class="fas fa-download"></i> Download Documents
                </a>
            </div>
        </div>
    </section>

    <!-- Self-Employed Documents Section -->
    <section class="common-section" id="self-employed-documents">
        <div class="container">
            <div class="resource-content-wrapper scroll-animate">
                <div class="resource-header">
                    <div class="resource-icon-inline">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="resource-title-wrapper">
                        <h2 class="section-title">Self-Employed / Business Income - Document Checklist</h2>
                        <p class="resource-detail-subtitle">Documents to prepare before your tax appointment</p>
                    </div>
                </div>

                <strong>Income & Expenses</strong>
                <ul class="resource-detail-list count-md-2">
                    <li>Annual sales summary or invoices</li>
                    <li>T4 slips (for other income)</li>
                    <li>Business expenses:
                        <ul class="sub-ul-list">
                            <li> Materials, tools, equipment purchased</li>
                            <li> Contractor or subcontractor payments</li>
                            <li> Employee wages and benefits</li>
                            <li> Vehicle logs, fuel, repairs, insurance</li>
                            <li> Phone, internet, and office rent</li>
                            <li> Advertising, insurance, storage costs</li>
                        </ul>
                    </li>
                    <li>RRSP, donation, medical, and childcare receipts</li>
                    <li>Foreign income (if applicable)</li>
                </ul>

                <div class="resource-detail-note">
                    <strong>If You Trade Investments</strong>
                    <ul class="resource-detail-list">
                        <li>T5008 (Securities Transactions)</li>
                        <li>Broker interest summary</li>
                    </ul>
                </div>
                <a href="resources-pdf/Self-Employed-Business-Income.pdf" class="resource-download-button" download>
                    <i class="fas fa-download"></i> Download Documents
                </a>
            </div>
        </div>
    </section>

    <!-- Driver Documents Section -->
    <section class="common-section" id="driver-documents">
        <div class="container">
            <div class="resource-content-wrapper scroll-animate">
                <div class="resource-header">
                    <div class="resource-icon-inline">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="resource-title-wrapper">
                        <h2 class="section-title">Rideshare Driver (Uber, Lyft, Taxi) - Document Checklist</h2>
                        <p class="resource-detail-subtitle">Documents to prepare before your tax appointment</p>
                    </div>
                </div>

                <strong>Business Income & Expenses</strong>
                <ul class="resource-detail-list count-md-2">
                    <li>Annual earnings summary from Uber, Lyft, or taxi company</li>
                    <li>Any T4 employment slips</li>
                    <li>Vehicle and business expenses:
                        <ul class="sub-ul-list">
                            <li> Vehicle purchase or lease documents</li>
                            <li> Uber/Lyft income and fee statement</li>
                            <li> Fuel, maintenance, and repair bills</li>
                            <li> Phone and internet bills</li>
                            <li> Vehicle insurance</li>
                        </ul>
                    </li>
                    </li>
                    <li>GST/HST number and access code (if registered)</li>
                    <li>RRSP, donation, medical, and childcare receipts</li>
                    <li>Spousal support records</li>
                </ul>

                <div class="resource-detail-note">
                    <strong>If You Trade Investments</strong>
                    <ul class="resource-detail-list">
                        <li>T5008 (Securities Transactions)</li>
                        <li>Broker interest summary</li>
                    </ul>
                </div>
                <a href="resources-pdf/Rideshare-Driver-Uber-Lyft-Taxi.pdf" class="resource-download-button" download>
                    <i class="fas fa-download"></i> Download Documents
                </a>
            </div>
        </div>
    </section>

    <!-- Rental Income Documents Section -->
    <section class="common-section" id="rental-income-documents">
        <div class="container">
            <div class="resource-content-wrapper scroll-animate">
                <div class="resource-header">
                    <div class="resource-icon-inline">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="resource-title-wrapper">
                        <h2 class="section-title">Rental Income - Document Checklist</h2>
                        <p class="resource-detail-subtitle">Documents to prepare before your tax appointment</p>
                    </div>
                </div>
                <strong>Property Information</strong>
                <ul class="resource-detail-list count-md-2">
                    <li>Full address and ownership details</li>
                    <li>Statement of adjustment (for new purchases)</li>
                    <li>Total rent collected this year</li>
                    <li>Appliance purchases or capital improvements</li>
                    <li>Property expenses:
                        <ul class="sub-ul-list">
                            <li> Property tax and insurance</li>
                            <li> Mortgage interest only</li>
                            <li> Repairs and maintenance</li>
                            <li> Real estate commissions and management fees</li>
                            <li> Condo fees (if applicable)</li>
                            <li> Utilities (if paid by landlord)</li>
                            <li> Advertising or travel expenses</li>
                        </ul>
                    </li>

                </ul>

                <div class="resource-detail-note">
                    <strong>If You Trade Investments</strong>
                    <ul class="resource-detail-list">
                        <li>T5008 (Securities Transactions)</li>
                        <li>Broker interest summary</li>
                    </ul>
                </div>
                <a href="resources-pdf/Rental-Income.pdf" class="resource-download-button" download>
                    <i class="fas fa-download"></i> Download Documents
                </a>
            </div>
        </div>
    </section>

    <!-- Property Sale Documents Section -->
    <section class="common-section" id="property-sale-documents">
        <div class="container">
            <div class="resource-content-wrapper scroll-animate">
                <div class="resource-header">
                    <div class="resource-icon-inline">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="resource-title-wrapper">
                        <h2 class="section-title">Sale of Property (Residential or Commercial) - Document Checklist</h2>
                        <p class="resource-detail-subtitle">Documents to prepare before your tax appointment</p>
                    </div>
                </div>

                <strong>Property Sale Information</strong>
                <ul class="resource-detail-list count-md-2">
                    <li>Statement of adjustment for purchase and sale</li>
                    <li>Financing or mortgage cost details</li>
                    <li>Renovation and improvement receipts</li>
                    <li>Real estate commission statement</li>
                    <li>Utilities paid while vacant</li>
                    <li>If rented part-year:
                        <ul class="sub-ul-list">
                            <li> Rent collected</li>
                            <li> Property tax, insurance, and interest</li>
                            <li> Repairs, management fees, commissions</li>
                            <li> Condo fees and utilities</li>
                            <li> Advertising and travel expenses</li>
                        </ul>
                    </li>
                </ul>
                <ul class="resource-detail-list">
                    </li>

                    <div class="resource-detail-note">
                        <strong>If You Trade Investments</strong>
                        <ul class="resource-detail-list">
                            <li>T5008 (Securities Transactions)</li>
                            <li>Broker interest summary</li>
                        </ul>
                    </div>
                    <a href="resources-pdf/residential-or-commercial-sale-of-property.pdf" class="resource-download-button" download>
                        <i class="fas fa-download"></i> Download Documents
                    </a>
            </div>
        </div>
    </section>
</main>

<?php include 'components/footer.php'; ?>