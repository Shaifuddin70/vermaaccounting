<?php
if (!function_exists('asset')) {
    require_once __DIR__ . '/../lib/helpers.php';
}
?>
 <!-- Footer Placeholder -->
 <div id="footer-placeholder"></div>

 <!-- Footer -->
 <footer class="main-footer">
   <div class="footer-top">
     <div class="footer-container">
       <!-- Company Info -->
       <div class="footer-section footer-brand">
         <div class="footer-logo">
           <img src="<?= asset('images/verma-accounting-footer-logo.png') ?>" alt="Verma Accounting" />
         </div>
         <p class="footer-description">
           Professional accounting and tax services for individuals and businesses across Canada. Expert guidance you can trust.
         </p>
         <div class="footer-social">
           <a href="https://www.facebook.com/profile.php?id=61570236688782" target="_blank" rel="noopener" aria-label="Facebook">
             <i class="fab fa-facebook-f"></i>
           </a>
           <a href="https://www.instagram.com/vermaaccounting.ca/" target="_blank" rel="noopener" aria-label="Instagram">
             <i class="fab fa-instagram"></i>
           </a>
           <a href="https://wa.me/16133186478" target="_blank" rel="noopener" aria-label="WhatsApp">
             <i class="fab fa-whatsapp"></i>
           </a>
         </div>
       </div>

       <!-- Quick Links -->
       <div class="footer-section">
         <h3 class="footer-heading">Quick Links</h3>
         <ul class="footer-links">
           <li><a href="/"><i class="fas fa-angle-right"></i> Home</a></li>
           <li><a href="/services"><i class="fas fa-angle-right"></i> Services</a></li>
           <li><a href="/resources"><i class="fas fa-angle-right"></i> Resources</a></li>
           <li><a href="/contact"><i class="fas fa-angle-right"></i> Contact</a></li>
           <li><a href="/about"><i class="fas fa-angle-right"></i> About Us</a></li>
           <li><a href="/privacy"><i class="fas fa-angle-right"></i> Privacy Policy</a></li>
         </ul>
       </div>

       <!-- Our Services -->
       <div class="footer-section">
         <h3 class="footer-heading">Our Services</h3>
         <ul class="footer-links">
           <li><a href="/accounting"><i class="fas fa-angle-right"></i> Accounting</a></li>
           <li><a href="/bookkeeping"><i class="fas fa-angle-right"></i> Bookkeeping</a></li>
           <li><a href="/payroll"><i class="fas fa-angle-right"></i> Payroll</a></li>
           <li><a href="/personal-tax"><i class="fas fa-angle-right"></i> Personal Tax</a></li>
           <li><a href="/corporate-tax"><i class="fas fa-angle-right"></i> Corporate Tax</a></li>
           <li><a href="/business-registration"><i class="fas fa-angle-right"></i> Business Registration</a></li>
            <li><a href="https://owningottawa.com/" target="_blank" rel="noopener"><i class="fas fa-angle-right"></i> Real Estate</a></li>
           <li><a href="/loan"><i class="fas fa-angle-right"></i> Business Loans</a></li>
         </ul>
       </div>

       <!-- Contact Info -->
       <div class="footer-section">
         <h3 class="footer-heading">Get In Touch</h3>
         <ul class="footer-contact">
           <li>
             <i class="fas fa-clock"></i>
             <div>
               <strong>Business Hours</strong>
               <span>Mon - Sun: 9:00 AM - 6:00 PM</span>
             </div>
           </li>
           <li>
             <i class="fas fa-envelope"></i>
             <div>
               <strong>Email Us</strong>
               <a href="mailto:info@vermaaccounting.ca">info@vermaaccounting.ca</a>
             </div>
           </li>
           <li>
             <i class="fas fa-phone"></i>
             <div>
               <strong>Call Us</strong>
               <a href="tel:613-318-6478">+1 (613) 318-6478</a>
             </div>
           </li>
         </ul>
       </div>
     </div>
   </div>

   <!-- Bottom Footer -->
   <div class="footer-bottom">
     <div class="footer-bottom-container">
       <div class="copyright">
         <p>© 2026 Verma Accounting & Financial Services. All rights reserved.</p>
       </div>
     </div>
   </div>
 </footer>
 <!-- Floating Social FAB -->
 <div class="social-fab" id="socialFab" aria-label="Open social options">
   <button class="social-fab-toggle" id="socialFabToggle" aria-expanded="false">
     <i class="fas fa-comments"></i>
   </button>
   <div class="fab-actions" id="socialFabActions" aria-hidden="true">
     <a class="fab-action fab-whatsapp" href="https://wa.me/16133186478" target="_blank" rel="noopener" aria-label="WhatsApp">
       <i class="fab fa-whatsapp"></i>
     </a>
     <a class="fab-action fab-facebook" href="https://www.facebook.com/profile.php?id=61570236688782" target="_blank" rel="noopener" aria-label="Facebook">
       <i class="fab fa-facebook-f"></i>
     </a>
     <a class="fab-action fab-instagram" href="https://www.instagram.com/vermaaccounting.ca/" target="_blank" rel="noopener" aria-label="Instagram">
       <i class="fab fa-instagram"></i>
     </a>
   </div>

 </div>

 <!-- Scroll Up Button -->
 <button class="scroll-up-btn" id="scrollUpBtn" aria-label="Scroll to top" title="Scroll to top">
   <i class="fas fa-chevron-up"></i>
 </button>

 <script>
   (function() {
     const fab = document.getElementById('socialFab');
     const toggle = document.getElementById('socialFabToggle');
     const actions = document.getElementById('socialFabActions');
     if (!fab || !toggle || !actions) return;

     function openFab() {
       fab.classList.add('open');
       toggle.setAttribute('aria-expanded', 'true');
       actions.setAttribute('aria-hidden', 'false');
     }

     function closeFab() {
       fab.classList.remove('open');
       toggle.setAttribute('aria-expanded', 'false');
       actions.setAttribute('aria-hidden', 'true');
     }

     toggle.addEventListener('click', function(e) {
       e.stopPropagation();
       if (fab.classList.contains('open')) {
         closeFab();
       } else {
         openFab();
       }
     });

     // Close on outside click
     document.addEventListener('click', function(e) {
       if (!fab.contains(e.target)) {
         closeFab();
       }
     });

     // Close on ESC
     document.addEventListener('keydown', function(e) {
       if (e.key === 'Escape') closeFab();
     });

     // Close after clicking an action
     actions.querySelectorAll('.fab-action').forEach(function(a) {
       a.addEventListener('click', function() {
         closeFab();
       });
     });
   })();

   // Scroll Up Button
   (function() {
     const scrollUpBtn = document.getElementById('scrollUpBtn');
     if (!scrollUpBtn) return;

     function toggleScrollUp() {
       if (window.scrollY > 300) {
         scrollUpBtn.classList.add('visible');
       } else {
         scrollUpBtn.classList.remove('visible');
       }
     }

     function scrollToTop() {
       window.scrollTo({ top: 0, behavior: 'smooth' });
     }

     window.addEventListener('scroll', toggleScrollUp, { passive: true });
     scrollUpBtn.addEventListener('click', scrollToTop);
     toggleScrollUp(); // Initial check
   })();
 </script>
 <script>
   var form = document.getElementById("my-form");

   async function handleSubmit(event) {
     event.preventDefault();
     var status = document.getElementById("my-form-status");
     var data = new FormData(event.target);
     fetch(event.target.action, {
       method: form.method,
       body: data,
       headers: {
         'Accept': 'application/json'
       }
     }).then(response => {
       if (response.ok) {
         status.innerHTML = "Thanks for your submission!";
         form.reset()
       } else {
         response.json().then(data => {
           if (Object.hasOwn(data, 'errors')) {
             status.innerHTML = data["errors"].map(error => error["message"]).join(", ")
           } else {
             status.innerHTML = "Oops! There was a problem submitting your form"
           }
         })
       }
     }).catch(error => {
       status.innerHTML = "Oops! There was a problem submitting your form"
     });
   }
   form.addEventListener("submit", handleSubmit)
 </script>
 <script>
   function toggleFAQ(button) {
     const faqItem = button.parentElement;
     const answer = faqItem.querySelector('.faq-answer');
     const icon = button.querySelector('i');

     // Close all other FAQ items
     document.querySelectorAll('.faq-item').forEach(item => {
       if (item !== faqItem) {
         item.classList.remove('active');
         item.querySelector('.faq-answer').style.maxHeight = '0';
         item.querySelector('.faq-question i').style.transform = 'rotate(0deg)';
       }
     });

     // Toggle current FAQ item
     faqItem.classList.toggle('active');

     if (faqItem.classList.contains('active')) {
       answer.style.maxHeight = answer.scrollHeight + 'px';
       icon.style.transform = 'rotate(180deg)';
     } else {
       answer.style.maxHeight = '0';
       icon.style.transform = 'rotate(0deg)';
     }
   }

   // Partner slider functionality
   let currentPartnerSlide = 1;
   const totalPartnerSlides = 6;
   let isManualControl = false;

   function currentSlide(n) {
     isManualControl = true;
     showPartnerSlide(currentPartnerSlide = n);

     // Resume auto-sliding after 3 seconds of manual control
     setTimeout(() => {
       isManualControl = false;
     }, 3000);
   }

   function showPartnerSlide(n) {
     const track = document.getElementById('partnerTrack');
     const dots = document.querySelectorAll('.dot');

     // Update active dot
     dots.forEach(dot => dot.classList.remove('active'));
     dots[n - 1].classList.add('active');

     // Calculate the translateX value
     // Each slide moves by the width of one partner item (200px + 30px gap = 230px)
     const slideWidth = 230;
     const translateX = -(n - 1) * slideWidth;

     // Apply the transformation
     track.style.transform = `translateX(${translateX}px)`;

     // If manual control, pause the CSS animation temporarily
     if (isManualControl) {
       track.style.animation = 'none';
     } else {
       // Resume the continuous loop animation
       track.style.animation = 'slideRightToLeft 20s linear infinite';
     }
   }

   // Auto-advance slides every 3 seconds (only when not in manual control)
   setInterval(() => {
     if (!isManualControl) {
       currentPartnerSlide = currentPartnerSlide >= totalPartnerSlides ? 1 : currentPartnerSlide + 1;
       showPartnerSlide(currentPartnerSlide);
     }
   }, 3000);

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

   // Hero Slider Functionality
   let currentSlideIndex = 0;
   const slides = document.querySelectorAll('.hero-slide');
   const dots = document.querySelectorAll('.hero-slider-dots .dot');
   let slideInterval;

   function showSlide(index) {
     // Remove active class from all slides and dots
     slides.forEach(slide => slide.classList.remove('active'));
     dots.forEach(dot => dot.classList.remove('active'));

     // Add active class to current slide and dot
     if (slides[index]) {
       slides[index].classList.add('active');
     }
     if (dots[index]) {
       dots[index].classList.add('active');
     }

     currentSlideIndex = index;
   }

   function nextSlide() {
     const nextIndex = (currentSlideIndex + 1) % slides.length;
     showSlide(nextIndex);
   }

   function startSlideShow() {
     slideInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
   }

   function stopSlideShow() {
     clearInterval(slideInterval);
   }

   // Global function for dot navigation
   window.currentSlide = function(index) {
     showSlide(index - 1); // Convert to 0-based index
     stopSlideShow();
     startSlideShow(); // Restart the auto-slide
   };

   // Initialize scroll animations when DOM is loaded
   document.addEventListener('DOMContentLoaded', function() {
     // Observe all elements with scroll animation classes
     const animatedElements = document.querySelectorAll('.scroll-animate, .scroll-animate-left, .scroll-animate-right, .scroll-animate-scale');
     animatedElements.forEach(el => {
       observer.observe(el);
     });

     // Add stagger effect to service cards
     const serviceCards = document.querySelectorAll('.services');
     serviceCards.forEach((card, index) => {
       if (index > 0) {
         card.classList.add(`scroll-animate-delay-${Math.min(index, 5)}`);
       }
     });

     // Add stagger effect to FAQ items
     const faqItems = document.querySelectorAll('.faq-item');
     faqItems.forEach((item, index) => {
       if (index > 0) {
         item.classList.add(`scroll-animate-delay-${Math.min(index, 5)}`);
       }
     });

     // Animated counters for industry stats
     const statNumbers = document.querySelectorAll('.stat-number');
     const statsObserver = new IntersectionObserver((entries) => {
       entries.forEach(entry => {
         if (entry.isIntersecting) {
           const target = parseInt(entry.target.getAttribute('data-target'));
           animateCounter(entry.target, target);
           statsObserver.unobserve(entry.target);
         }
       });
     }, {
       threshold: 0.5
     });

     statNumbers.forEach(stat => {
       statsObserver.observe(stat);
     });

     // Initialize hero slider
     if (slides.length > 0) {
       showSlide(0); // Show first slide
       startSlideShow(); // Start auto-slide
     }
   });

   // Counter animation function
   function animateCounter(element, target) {
     let current = 0;
     const increment = target / 100; // Adjust speed by changing divisor
     const timer = setInterval(() => {
       current += increment;
       if (current >= target) {
         current = target;
         clearInterval(timer);
       }

       // Format number based on target value
       if (target >= 1000000) {
         element.textContent = formatNumber(Math.floor(current));
       } else {
         element.textContent = Math.floor(current);
       }
     }, 20); // Adjust speed by changing interval
   }

   // Format large numbers
   function formatNumber(num) {
     if (num >= 1000000) {
       return (num / 1000000).toFixed(1) + 'M';
     } else if (num >= 1000) {
       return (num / 1000).toFixed(1) + 'K';
     }
     return num.toString();
   }

   // Typing Animation for CTA Section
   function initTypingAnimation() {
     const typingElement = document.getElementById('typing-text');
     if (!typingElement) return;

     const texts = [
       'Audits?',
       'Tax Planning?',
       'Bookkeeping?',
       'Payroll?',
       'Business Registration?',
       'Financial Consulting?'
     ];

     let textIndex = 0;
     let charIndex = 0;
     let isDeleting = false;
     let typeSpeed = 100; // milliseconds
     let deleteSpeed = 50;
     let pauseTime = 2000; // pause between texts

     function typeText() {
       const currentText = texts[textIndex];

       if (isDeleting) {
         typingElement.textContent = currentText.substring(0, charIndex - 1);
         charIndex--;
         typeSpeed = deleteSpeed;
       } else {
         typingElement.textContent = currentText.substring(0, charIndex + 1);
         charIndex++;
         typeSpeed = 100;
       }

       if (!isDeleting && charIndex === currentText.length) {
         typeSpeed = pauseTime;
         isDeleting = true;
       } else if (isDeleting && charIndex === 0) {
         isDeleting = false;
         textIndex = (textIndex + 1) % texts.length;
         typeSpeed = 500; // pause before starting new text
       }

       setTimeout(typeText, typeSpeed);
     }

     // Start the typing animation
     typeText();
   }

   // Initialize typing animation when page loads
   document.addEventListener('DOMContentLoaded', function() {
     // Add a small delay to ensure the element is rendered
     setTimeout(initTypingAnimation, 1000);
   });
 </script>
 <!-- Bootstrap JavaScript CDN -->
 <script
   src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
   integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
   crossorigin="anonymous"></script>

 <!-- JavaScript -->
 <script src="<?= asset('components/js/navigation.js') ?>?v=68"></script>
 <script src="https://elfsightcdn.com/platform.js" async></script>
 </body>

 </html>