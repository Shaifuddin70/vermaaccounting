<?php
// Shared site footer markup (used by hand-written pages and the CMS renderer).
$footerSocial = CmsCompany::socialLinks();
$footerAddress = CmsCompany::addressLine();
?>
 <!-- Footer Placeholder -->
 <div id="footer-placeholder"></div>

 <!-- Footer -->
 <footer class="main-footer">
   <div class="footer-wrap">
     <!-- Call to action -->
     <div class="footer-cta">
       <div class="footer-cta-text">
         <h2>Need help with your books or taxes?</h2>
         <p>Book a free consultation with our accounting team.</p>
       </div>
       <div class="footer-cta-actions">
         <a class="footer-btn footer-btn--primary" href="/contact">Get Free Consultation</a>
         <a class="footer-btn footer-btn--ghost" href="<?= e(company_phone_href()) ?>"><i class="fas fa-phone"></i> <?= e(company_phone()) ?></a>
       </div>
     </div>

     <div class="footer-grid">
       <!-- Company Info -->
       <div class="footer-col footer-brand">
         <a class="footer-logo" href="/" aria-label="Verma Accounting home">
           <img src="<?= asset('images/verma-accounting-footer-logo-440.webp') ?>" alt="Verma Accounting" width="440" height="102" loading="lazy" decoding="async" />
         </a>
         <p class="footer-description">
           Professional accounting and tax services for individuals and businesses across Canada. Expert guidance you can trust.
         </p>
         <?php if ($footerSocial !== []): ?>
         <div class="footer-social">
           <?php foreach ($footerSocial as $social): ?>
           <a href="<?= e($social['url']) ?>" target="_blank" rel="noopener" aria-label="Verma Accounting on <?= e($social['label']) ?>"><i class="<?= e($social['icon']) ?>" aria-hidden="true"></i></a>
           <?php endforeach; ?>
         </div>
         <?php endif; ?>
       </div>

       <!-- Quick Links -->
       <nav class="footer-col" aria-label="Quick links">
         <h3 class="footer-heading">Quick Links</h3>
         <ul class="footer-links">
           <li><a href="/">Home</a></li>
           <li><a href="/services">Services</a></li>
           <li><a href="/resources">Resources</a></li>
           <li><a href="/blog">Blog</a></li>
           <li><a href="/about">About Us</a></li>
           <li><a href="/service-areas">Service Areas</a></li>
           <li><a href="/contact">Contact</a></li>
         </ul>
       </nav>

       <!-- Our Services -->
       <nav class="footer-col" aria-label="Our services">
         <h3 class="footer-heading">Our Services</h3>
         <ul class="footer-links">
           <li><a href="/accounting">Accounting</a></li>
           <li><a href="/bookkeeping">Bookkeeping</a></li>
           <li><a href="/payroll">Payroll</a></li>
           <li><a href="/personal-tax">Personal Tax</a></li>
           <li><a href="/corporate-tax">Corporate Tax</a></li>
           <li><a href="/business-registration">Business Registration</a></li>
           <li><a href="/loan">Business Loans</a></li>
           <li><a href="https://owningottawa.com/" target="_blank" rel="noopener">Real Estate</a></li>
         </ul>
       </nav>

       <!-- Contact Info -->
       <div class="footer-col">
         <h3 class="footer-heading">Get In Touch</h3>
         <ul class="footer-contact">
           <?php if ($footerAddress !== ''): ?><li><i class="fas fa-map-marker-alt"></i><span><?= e($footerAddress) ?></span></li><?php endif; ?>
           <?php if (CmsCompany::hours() !== ''): ?><li><i class="fas fa-clock"></i><span><?= e(CmsCompany::hours()) ?></span></li><?php endif; ?>
           <li><i class="fas fa-envelope"></i><a href="<?= e(company_email_href()) ?>"><?= e(company_email()) ?></a></li>
           <li><i class="fas fa-phone"></i><a href="<?= e(company_phone_href()) ?>"><?= e(company_phone()) ?></a></li>
         </ul>
       </div>
     </div>
   </div>

   <!-- Bottom Footer -->
   <div class="footer-bottom">
     <div class="footer-wrap footer-bottom-inner">
       <p>© <?= date('Y') ?> Verma Accounting &amp; Financial Services. All rights reserved.</p>
       <ul class="footer-legal">
         <li><a href="/privacy">Privacy Policy</a></li>
         <li><a href="/terms">Terms of Service</a></li>
         <li><a href="/editorial-policy">Editorial Policy</a></li>
         <li><a href="/contact">Contact Us</a></li>
       </ul>
     </div>
     <p class="footer-wrap footer-disclaimer">
       Disclaimer: The information on this website is general in nature and is not tax, legal, or financial advice.
       Tax rules change and every situation is different, so please <a href="/contact">contact a Verma Accounting professional</a>
       before acting on it.
     </p>
   </div>
 </footer>
 <?php if ($footerSocial !== []): ?>
 <!-- Floating Social FAB -->
 <div class="social-fab" id="socialFab">
   <button class="social-fab-toggle" id="socialFabToggle" aria-expanded="false" aria-controls="socialFabActions" aria-label="Open social media links">
     <i class="fas fa-comments" aria-hidden="true"></i>
   </button>
   <div class="fab-actions" id="socialFabActions" aria-hidden="true">
     <?php foreach ($footerSocial as $social): ?>
     <a class="fab-action fab-<?= e($social['key']) ?>" href="<?= e($social['url']) ?>" target="_blank" rel="noopener" aria-label="Verma Accounting on <?= e($social['label']) ?>">
       <i class="<?= e($social['icon']) ?>" aria-hidden="true"></i>
     </a>
     <?php endforeach; ?>
   </div>
 </div>
 <?php endif; ?>

 <!-- Scroll Up Button -->
 <button class="scroll-up-btn" id="scrollUpBtn" aria-label="Scroll to top" title="Scroll to top">
   <i class="fas fa-chevron-up"></i>
 </button>

 <?php include __DIR__ . '/engagement-modal.php'; ?>

 <script src="<?= e(asset_versioned('components/js/site-footer.js')) ?>"></script>
