<?php include('includes/head.php'); ?>
<body>

<!-- Navigation Bar -->
<?php include('includes/nav.php'); ?>

<!-- Page Header / Hero (Contact specific) -->
<section class="hero" style="padding: 100px 0 80px; background: linear-gradient(135deg, #0f172a 0%, #1e2a3a 100%);">
  <div class="container position-relative">
    <div class="row align-items-center">
      <div class="col-lg-7" data-aos="fade-right" data-aos-duration="800">
        <div class="hero-content">
          <div class="hero-badge"><i class="fas fa-headset me-1"></i> 24/7 Support</div>
          <h1 class="text-white mb-3">Get in Touch With <span class="text-warning">AS Electricals</span></h1>
          <p class="text-white-50 lead">Have questions about our products or services? Need emergency electrical support? We're just a call or message away. Our team is ready to assist you.</p>
          <div class="mt-4 d-flex gap-3 flex-wrap">
            <a href="#contact-form" class="btn btn-electric px-4 py-2"><i class="fas fa-paper-plane me-2"></i>Send Message</a>
            <a href="tel:+918610786637" class="btn btn-outline-light px-4 py-2 rounded-pill"><i class="fas fa-phone-alt me-2"></i>Call Now</a>
          </div>
        </div>
      </div>
      <div class="col-lg-5 text-center" data-aos="fade-left" data-aos-delay="200">
        <img src="https://images.unsplash.com/photo-1557426272-fc759fdf7a8d?w=500&auto=format" alt="Customer Support" class="img-fluid rounded-4 shadow-lg" style="max-width: 320px; border-radius: 32px;">
      </div>
    </div>
  </div>
</section>

<!-- Contact Info & Form Section -->
<section id="contact-form" class="py-5">
  <div class="container py-4">
    <div class="row g-5">
      <!-- Contact Information Column -->
      <div class="col-lg-5" data-aos="fade-right">
        <div class="contact-info-card h-100">
          <h3 class="fw-bold">Contact Information</h3>
          <p class="text-secondary">Reach out to us through any of these channels. We typically respond within 2 hours during business hours.</p>
          
          <div class="mt-4">
            <div class="d-flex gap-3 mb-4 align-items-center">
              <div class="contact-icon"><i class="fas fa-map-marker-alt fa-lg text-warning"></i></div>
              <div><strong>Showroom Address</strong><br>S.F.NO.169/1A7A, Girivalam Road, Adiannamalai,
Tiruvannamalai, Tamil Nadu, 606604</div>
            </div>
            
            <div class="d-flex gap-3 mb-4 align-items-center">
              <div class="contact-icon"><i class="fas fa-phone-alt fa-lg text-warning"></i></div>
              <div><strong>Phone / Whatsapp</strong><br><a href="tel:+918610786637" class="text-decoration-none text-dark">+91 86107 86637</a></div>
            </div>
            
            <div class="d-flex gap-3 mb-4 align-items-center">
              <div class="contact-icon"><i class="fas fa-envelope fa-lg text-warning"></i></div>
              <div><strong>Email</strong><br><a href="mailto:aselectricalshari@gmail.com" class="text-decoration-none">aselectricalshari@gmail.com</a></div>
            </div>
            
            <div class="d-flex gap-3 mb-4 align-items-center">
              <div class="contact-icon"><i class="fas fa-clock fa-lg text-warning"></i></div>
              <div><strong>Business Hours</strong><br>Monday - Sunday: 9:00 AM – 9:00 PM</div>
            </div>
          </div>
          
          <div class="social-icons mt-4">
            <h6 class="mb-3">Connect With Us</h6>
            <a href="https://www.facebook.com/share/14UXcQVhJPa/" class="me-3"><i class="fab fa-facebook-f fa-lg"></i></a>
            <a href="https://www.instagram.com/tvm25electricals?utm_source=qr&igsh=MWxxb3ZyeWY1MzNjaw==" class="me-3"><i class="fab fa-instagram fa-lg"></i></a>
            
          </div>
          
          <!-- Quick Response Badge -->
          <div class="mt-4 p-3 bg-light rounded-3">
            <div class="d-flex align-items-center gap-3">
              <i class="fas fa-tachometer-alt fa-2x text-warning"></i>
              <div>
                <strong>Quick Response Guarantee</strong>
                <p class="small text-secondary mb-0">We respond to all enquiries within 2 hours during business hours.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Contact Form Column -->
      <div class="col-lg-7" data-aos="fade-left">
        <div class="bg-white p-4 p-lg-5 rounded-4 shadow-sm">
          <h3 class="fw-bold">Send Us a Message</h3>
          <p class="text-secondary">Fill out the form below and our team will get back to you shortly.</p>
          
          <form id="contactForm" method="POST" action="">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" placeholder="Enter your full name" required id="nameInput">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" placeholder="Enter your mobile number" required id="phoneInput">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" class="form-control" placeholder="Enter your email" id="emailInput">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Subject</label>
                <select class="form-select" id="subjectSelect">
                  <option value="General Enquiry">General Enquiry</option>
                  <option value="Service Request">Service Request</option>
                  <option value="Product Purchase">Product Purchase</option>
                  <option value="Solar Installation">Solar Installation</option>
                  <option value="Repair & Maintenance">Repair & Maintenance</option>
                  <option value="Bulk Order / Trade">Bulk Order / Trade</option>
                  <option value="Emergency Support">Emergency Support</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Your Message <span class="text-danger">*</span></label>
                <textarea class="form-control" rows="4" placeholder="Tell us about your requirement, project details, or any questions..." id="msgInput" style="border-radius: 20px;"></textarea>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="agreeCheck" required>
                  <label class="form-check-label small text-secondary" for="agreeCheck">
                    I agree to the terms and privacy policy. AS Electricals may contact me regarding my enquiry.
                  </label>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-submit text-white"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
              </div>
              <div class="col-12"><div id="formFeedback" class="small fw-semibold"></div></div>
            </div>
          </form>
          
          <div class="mt-4 pt-3 border-top">
            <div class="d-flex gap-3 justify-content-center flex-wrap">
              <a href="tel:+918610786637" class="btn btn-outline-warning rounded-pill px-4"><i class="fas fa-phone-alt me-2"></i>Call Us</a>
              <a href="https://wa.me/918610786637" target="_blank" class="btn btn-outline-success rounded-pill px-4"><i class="fab fa-whatsapp me-2"></i>WhatsApp</a>
              <a href="mailto:aselectricalshari@gmail.com" class="btn btn-outline-danger rounded-pill px-4"><i class="fas fa-envelope me-2"></i>Email Us</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Google Map Location Section -->
<section class="py-5 bg-light">
  <div class="container py-4">
    <div class="text-center mb-5" data-aos="fade-up">
      <span class="text-uppercase text-warning fw-bold">Visit Our Store</span>
      <h2 class="section-title">Find Us Here</h2>
      <p class="text-secondary">Located in the heart of Tiruvannamalai, near the famous Annamalaiyar Temple</p>
    </div>
    <div class="row g-4">
      <div class="col-lg-8 mx-auto" data-aos="fade-up">
        <div class="rounded-4 overflow-hidden shadow">
          <!-- Google Maps Embed - Tiruvannamalai location -->
          <iframe 
            src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3898.9362842257488!2d79.03549497506448!3d12.252591088000603!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2zMTLCsDE1JzA5LjMiTiA3OcKwMDInMTcuMSJF!5e0!3m2!1sen!2sin!4v1774975070654!5m2!1sen!2sin" 
            width="100%" 
            height="400" 
            style="border:0;" 
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="no-referrer-when-downgrade">
          </iframe>
        </div>
        <div class="mt-3 text-center">
          <p class="mb-0"><i class="fas fa-location-dot text-warning me-2"></i>S.F.NO.169/1A7A, Girivalam Road, Adiannamalai,
Tiruvannamalai, Tamil Nadu, 606604</p>
          <p class="small text-secondary mt-2">📍 Easy access from the main bus stand. Ample parking available for customers.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Emergency Support Banner -->
<section class="py-4" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
  <div class="container">
    <div class="row align-items-center text-center text-md-start">
      <div class="col-md-8" data-aos="fade-right">
        <h3 class="text-white mb-2"><i class="fas fa-bolt me-2"></i> Electrical Emergency?</h3>
        <p class="text-white-50 mb-0">Available 24/7 for urgent electrical repairs, short circuits, and power failures.</p>
      </div>
      <div class="col-md-4 text-center text-md-end mt-3 mt-md-0" data-aos="fade-left">
        <a href="tel:+919876543210" class="btn btn-light btn-lg rounded-pill px-4 fw-bold"><i class="fas fa-phone-alt text-warning me-2"></i>Call Emergency</a>
      </div>
    </div>
  </div>
</section>

<!-- Frequently Asked Questions -->
<section class="py-5">
  <div class="container py-4">
    <div class="text-center mb-5" data-aos="fade-up">
      <span class="text-uppercase text-warning fw-bold">Quick Help</span>
      <h2 class="section-title">Frequently Asked Questions</h2>
      <p class="text-secondary">Find quick answers to common questions</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
        <div class="bg-white p-4 rounded-4 shadow-sm h-100">
          <div class="d-flex gap-3">
            <i class="fas fa-clock fa-2x text-warning"></i>
            <div>
              <h5 class="fw-bold">What are your service hours?</h5>
              <p class="text-secondary mb-0">We're open Monday to Saturday from 9:00 AM to 9:00 PM. Emergency services are available 24/7.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
        <div class="bg-white p-4 rounded-4 shadow-sm h-100">
          <div class="d-flex gap-3">
            <i class="fas fa-truck fa-2x text-warning"></i>
            <div>
              <h5 class="fw-bold">Do you offer free site visits?</h5>
              <p class="text-secondary mb-0">Yes, we provide free site inspection and consultation for all electrical projects. No obligation quotes are provided.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="300">
        <div class="bg-white p-4 rounded-4 shadow-sm h-100">
          <div class="d-flex gap-3">
            <i class="fas fa-shield-alt fa-2x text-warning"></i>
            <div>
              <h5 class="fw-bold">Do you provide warranty?</h5>
              <p class="text-secondary mb-0">All our products come with manufacturer warranty. Our electrical work also comes with a service warranty for your peace of mind.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="400">
        <div class="bg-white p-4 rounded-4 shadow-sm h-100">
          <div class="d-flex gap-3">
            <i class="fas fa-credit-card fa-2x text-warning"></i>
            <div>
              <h5 class="fw-bold">What payment methods do you accept?</h5>
              <p class="text-secondary mb-0">We accept cash, UPI, credit/debit cards, and bank transfers. EMI options available for larger purchases.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<?php include('includes/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  // AOS Init
  AOS.init({
    duration: 800,
    once: true,
    offset: 80
  });

  // Smooth scrolling
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // ✅ CONTACT FORM → WHATSAPP
  const contactForm = document.getElementById('contactForm');
  const feedbackDiv = document.getElementById('formFeedback');

  if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
      e.preventDefault();

      const name = document.getElementById('nameInput')?.value || '';
      const phone = document.getElementById('phoneInput')?.value || '';
      const email = document.getElementById('emailInput')?.value || '';
      const subject = document.getElementById('subjectSelect')?.value || '';
      const msg = document.getElementById('msgInput')?.value || '';
      const agree = document.getElementById('agreeCheck')?.checked || false;

      // Validation
      if (name.trim() === '') {
        showError('⚠️ Please enter your name.');
        return;
      }

      if (phone.trim() === '') {
        showError('⚠️ Please enter your phone number.');
        return;
      }

      if (msg.trim() === '') {
        showError('⚠️ Please enter your message.');
        return;
      }

      if (!agree) {
        showError('⚠️ Please agree to the terms to proceed.');
        return;
      }

      // WhatsApp message
      const whatsappMessage = `Hello AS Electricals 👋

📌 *New Contact Enquiry*

👤 Name: ${name}
📞 Phone: ${phone}
📧 Email: ${email}
📋 Subject: ${subject}

📝 Message:
${msg}`;

      const encodedMessage = encodeURIComponent(whatsappMessage);
      const whatsappURL = `https://wa.me/918610786637?text=${encodedMessage}`;

      // Success message
      feedbackDiv.innerHTML = '✅ Redirecting to WhatsApp...';
      feedbackDiv.classList.remove('text-danger');
      feedbackDiv.classList.add('text-success');

      // Open WhatsApp
      setTimeout(() => {
        window.open(whatsappURL, '_blank');
      }, 800);

      // Reset form
      contactForm.reset();

      setTimeout(() => {
        feedbackDiv.innerHTML = '';
      }, 4000);
    });
  }

  // Helper function
  function showError(message) {
    feedbackDiv.innerHTML = message;
    feedbackDiv.classList.add('text-danger');
    feedbackDiv.classList.remove('text-success');
  }

  // ✅ NEWSLETTER
  const subBtn = document.getElementById('subscribeBtn');
  const newsMsg = document.getElementById('newsMsg');

  if (subBtn) {
    subBtn.addEventListener('click', function() {
      const emailField = document.getElementById('newsEmail');
      const email = emailField?.value || '';

      if (email.includes('@') && email.includes('.')) {
        newsMsg.innerHTML = '🎉 Subscribed successfully!';
        newsMsg.classList.remove('text-danger');
        newsMsg.classList.add('text-success');
        emailField.value = '';

        setTimeout(() => {
          newsMsg.innerHTML = '';
        }, 3000);
      } else {
        newsMsg.innerHTML = '❌ Please enter a valid email.';
        newsMsg.classList.remove('text-success');
        newsMsg.classList.add('text-danger');

        setTimeout(() => {
          newsMsg.innerHTML = '';
        }, 2500);
      }
    });
  }

  // ✅ OPTIONAL: Make WhatsApp button smarter (if used anywhere)
  document.querySelectorAll('a[href*="wa.me"]').forEach(btn => {
    btn.addEventListener('click', function() {
      console.log('WhatsApp clicked');
    });
  });
</script>
</body>
</html>