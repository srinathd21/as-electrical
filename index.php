<!DOCTYPE html>
<html lang="en">
<?php include('includes/head.php');?>
<body>
<style>
  
</style>
<!-- Navigation Bar -->
<?php include('includes/nav.php');?>

<!-- Hero Section -->
<section id="home" class="hero">
  <div class="container position-relative">
    <div class="row align-items-center g-5">
      <div class="col-lg-6" data-aos="fade-right" data-aos-duration="800">
        <div class="hero-content">
          <div class="hero-badge"><i class="fas fa-bolt me-1"></i> Trusted since 2012</div>
          <h1 class="text-white mb-3">Powering Tiruvannamalai with <span class="text-warning">Premium Electricals</span></h1>
          <p class="text-white-50 lead">Your one-stop destination for quality wires, switches, lighting, and professional electrical services. Safety & innovation at your doorstep.</p>
          <div class="hero-stats">
            <div class="stat-item"><h3>5000+</h3><p class="text-white-50">Happy Clients</p></div>
            <div class="stat-item"><h3>1200+</h3><p class="text-white-50">Projects Done</p></div>
            <div class="stat-item"><h3>24/7</h3><p class="text-white-50">Emergency Support</p></div>
          </div>
          <div class="mt-4 d-flex gap-3 flex-wrap">
            <a href="#contact" class="btn btn-electric px-4 py-2"><i class="fas fa-tools me-2"></i>Request Service</a>
            <a href="#products" class="btn btn-outline-light px-4 py-2 rounded-pill"><i class="fas fa-store me-2"></i>Explore Products</a>
          </div>
        </div>
      </div>
      <div class="col-lg-6 text-center" data-aos="fade-left" data-aos-delay="200">
        <div class="hero-img">
          <img src="https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?w=500&auto=format" alt="Electrician working" class="img-fluid rounded-4 shadow-lg" style="max-width: 320px; border-radius: 32px;">
          <div class="mt-3 d-flex justify-content-center gap-2">
            <span class="badge bg-dark bg-opacity-50 p-2 rounded-pill"><i class="fas fa-plug"></i> Wires</span>
            <span class="badge bg-dark bg-opacity-50 p-2 rounded-pill"><i class="fas fa-lightbulb"></i> Smart Lights</span>
            <span class="badge bg-dark bg-opacity-50 p-2 rounded-pill"><i class="fas fa-charging-station"></i> Stabilizers</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Services Section -->
<section id="services" class="py-5 py-md-6">
  <div class="container py-5">
    <div class="text-center mb-5" data-aos="fade-up">
      <span class="text-uppercase text-warning fw-bold">Our expertise</span>
      <h2 class="section-title">Professional Electrical Services</h2>
      <p class="text-secondary w-75 mx-auto">From residential wiring to industrial panel installation – we deliver excellence with safety.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-6 col-lg-3" data-aos="zoom-in" data-aos-delay="100">
        <div class="service-card">
          <div class="icon-circle"><i class="fas fa-home"></i></div>
          <h4>House Wiring</h4>
          <p>Modern concealed & modular wiring with high-quality copper wires and safety standards.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3" data-aos="zoom-in" data-aos-delay="200">
        <div class="service-card">
          <div class="icon-circle"><i class="fas fa-solar-panel"></i></div>
          <h4>Solar Solutions</h4>
          <p>On-grid & off-grid solar installations to reduce electricity bills and go green.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3" data-aos="zoom-in" data-aos-delay="300">
        <div class="service-card">
          <div class="icon-circle"><i class="fas fa-microchip"></i></div>
          <h4>Home Automation</h4>
          <p>Smart switches, IoT lighting, and energy monitoring for modern homes.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-3" data-aos="zoom-in" data-aos-delay="400">
        <div class="service-card">
          <div class="icon-circle"><i class="fas fa-bolt"></i></div>
          <h4>Repair & Maintenance</h4>
          <p>24/7 emergency repairs, fuse box upgrades, and complete electrical audits.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Products Section - using reliable online image library -->
<section id="products" class="py-5" style="background: #fef9f1;">
  <div class="container py-4">
    <div class="text-center mb-5" data-aos="fade-up">
      <span class="text-uppercase text-warning fw-bold">Top Brands</span>
      <h2 class="section-title">Premium Electrical Products</h2>
      <p class="text-secondary">Genuine products from Havells, Anchor, Polycab, Philips & more at best prices.</p>
    </div>
    <div class="row g-4">
      <!-- Product 1 - Wires -->
      <div class="col-sm-6 col-lg-3" data-aos="flip-left" data-aos-delay="100">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/2976/2976285.png" alt="copper wire roll" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5 class="mt-3">Polycab Wires</h5>
          <p>FR & HRFR flame retardant, 100% copper</p>
          <div class="price">₹1,299 / Roll</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn">Enquire</button>
        </div>
      </div>
      
      <!-- Product 2 - Switches -->
      <div class="col-sm-6 col-lg-3" data-aos="flip-left" data-aos-delay="200">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3215/3215903.png" alt="modern electrical switch" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Modular Switches</h5>
          <p>Anchor Roma & Havells Crabtree, premium finish</p>
          <div class="price">₹349 / Set</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn">Shop now</button>
        </div>
      </div>
      
      <!-- Product 3 - LED Lights -->
      <div class="col-sm-6 col-lg-3" data-aos="flip-left" data-aos-delay="300">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/1040/1040220.png" alt="LED light bulb" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Philips LED Lights</h5>
          <p>Energy efficient, 5 years warranty, warm/cool</p>
          <div class="price">₹199 / Unit</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn">Buy now</button>
        </div>
      </div>
      
      <!-- Product 4 - Ceiling Fans -->
      <div class="col-sm-6 col-lg-3" data-aos="flip-left" data-aos-delay="400">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163408.png" alt="ceiling fan" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Ceiling Fans & BLDC</h5>
          <p>Orient, Crompton, energy saving fans</p>
          <div class="price">₹2,499 / piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn">View deals</button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- About Section with Unsplash image of shop or electrician team -->
<section id="about" class="py-5">
  <div class="container py-4">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 order-lg-1" data-aos="fade-right">
        <img src="https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?w=500&auto=format" class="img-fluid rounded-4 shadow" alt="Electrical Shop Interior" style="border-radius: 2rem;">
      </div>
      <div class="col-lg-6" data-aos="fade-left">
        <span class="badge bg-warning bg-opacity-25 text-warning p-2 px-3 rounded-pill">Our story</span>
        <h2 class="mt-2 fw-bold display-6">Best Electrical Shop in <span class="text-warning">Tiruvannamalai</span></h2>
        <p class="text-secondary mt-3">AS Electricals is a family-owned business serving the Annamalaiyar Nagar community with integrity and quality electrical products & services for over a decade. We combine traditional values with modern technology to ensure every home and industry gets safe and durable electrical solutions.</p>
        <ul class="feature-list list-unstyled mt-4">
          <li><i class="fas fa-check-circle"></i> 100% genuine branded products</li>
          <li><i class="fas fa-check-circle"></i> Licensed & experienced electricians</li>
          <li><i class="fas fa-check-circle"></i> Free site inspection & consultation</li>
          <li><i class="fas fa-check-circle"></i> 24-hour emergency support across Tiruvannamalai</li>
        </ul>
        <div class="mt-4">
          <div class="d-flex gap-4">
            <div><i class="fas fa-map-marker-alt text-warning"></i> <strong>Near Bus Stand, Tiruvannamalai</strong></div>
            <div><i class="fas fa-phone-alt text-warning"></i> +91 98765 43210</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Testimonials Section -->
<section class="py-5 bg-light">
  <div class="container py-4">
    <div class="text-center mb-5" data-aos="fade-up">
      <h2 class="section-title">What Our Customers Say</h2>
      <p>Trusted by homeowners, builders, and businesses across Tiruvannamalai</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="testimonial-card">
          <div class="rating mb-2"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p>"Excellent service! They did complete wiring for our new home. Very professional and affordable. Highly recommend AS Electricals."</p>
          <h6 class="mt-3">- Senthil Kumar</h6>
          <small class="text-muted">Homeowner, Tiruvannamalai</small>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="testimonial-card">
          <div class="rating mb-2"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
          <p>"Best shop for genuine electrical products. The solar installation team was punctual and did a clean job. My electricity bill reduced by 40%!"</p>
          <h6 class="mt-3">- Geetha Rani</h6>
          <small class="text-muted">Small Business Owner</small>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
        <div class="testimonial-card">
          <div class="rating mb-2"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p>"Fast emergency service! They fixed the main line fault within an hour. Professional team and reasonable pricing. Thank you AS Electricals."</p>
          <h6 class="mt-3">- Ramesh Babu</h6>
          <small class="text-muted">Hotel Manager</small>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Contact Section -->
<section id="contact" class="py-5">
  <div class="container py-4">
    <div class="row g-5">
      <div class="col-lg-5" data-aos="fade-right">
        <div class="contact-info-card h-100">
          <h3 class="fw-bold">Get in touch</h3>
          <p class="text-secondary">Visit our shop or call us for any electrical needs — we're here to help 24/7.</p>
          <div class="mt-4">
            <div class="d-flex gap-3 mb-4 align-items-center">
              <div class="contact-icon"><i class="fas fa-map-marker-alt fa-lg text-warning"></i></div>
              <div><strong>Showroom Address</strong><br>#45, Car Street, Near Annamalaiyar Temple, Tiruvannamalai, Tamil Nadu - 606601</div>
            </div>
            <div class="d-flex gap-3 mb-4 align-items-center">
              <div class="contact-icon"><i class="fas fa-phone-alt fa-lg text-warning"></i></div>
              <div><strong>Phone / Whatsapp</strong><br>+91 98765 43210 , +91 98765 43211</div>
            </div>
            <div class="d-flex gap-3 mb-4 align-items-center">
              <div class="contact-icon"><i class="fas fa-envelope fa-lg text-warning"></i></div>
              <div><strong>Email</strong><br>support@aselectricals.com</div>
            </div>
            <div class="d-flex gap-3">
              <div class="contact-icon"><i class="fas fa-clock fa-lg text-warning"></i></div>
              <div><strong>Business Hours</strong><br>Mon - Sat: 9:00 AM – 8:30 PM | Sunday: 10:00 AM – 1:00 PM</div>
            </div>
          </div>
          <div class="social-icons mt-5">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-whatsapp"></i></a>
            <a href="#"><i class="fab fa-google"></i></a>
          </div>
        </div>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <div class="bg-white p-4 p-lg-5 rounded-4 shadow-sm">
          <h3 class="fw-bold">Request a free consultation</h3>
          <p class="text-secondary">Fill the form and our expert will reach you within 24 hours.</p>
          <form id="contactForm">
            <div class="row g-3">
              <div class="col-md-6"><input type="text" class="form-control" placeholder="Full Name" required id="nameInput"></div>
              <div class="col-md-6"><input type="tel" class="form-control" placeholder="Phone Number" required id="phoneInput"></div>
              <div class="col-12"><input type="email" class="form-control" placeholder="Email Address" id="emailInput"></div>
              <div class="col-12"><select class="form-select" id="serviceSelect"><option>Select Service: House Wiring</option><option>Solar Installation</option><option>Product Purchase</option><option>Repair & Maintenance</option></select></div>
              <div class="col-12"><textarea class="form-control" rows="3" placeholder="Tell us about your requirement..." id="msgInput"></textarea></div>
              <div class="col-12"><button type="submit" class="btn btn-submit text-white">Send Message <i class="fas fa-paper-plane ms-2"></i></button></div>
              <div class="col-12"><div id="formFeedback" class="small text-success fw-semibold"></div></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<?php include('includes/footer.php');?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({
    duration: 800,
    once: true,
    offset: 80
  });
  // Smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // Simple form feedback
  const contactForm = document.getElementById('contactForm');
  const feedbackDiv = document.getElementById('formFeedback');
  if(contactForm) {
    contactForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const name = document.getElementById('nameInput')?.value || '';
      if(name.trim() === '') {
        feedbackDiv.innerHTML = '⚠️ Please enter your name.';
        feedbackDiv.classList.add('text-danger');
        return;
      }
      feedbackDiv.innerHTML = '✅ Thanks! Our team will contact you shortly.';
      feedbackDiv.classList.remove('text-danger');
      feedbackDiv.classList.add('text-success');
      contactForm.reset();
      setTimeout(() => { feedbackDiv.innerHTML = ''; }, 4000);
    });
  }

  // Newsletter subscription mock
  const subBtn = document.getElementById('subscribeBtn');
  const newsMsg = document.getElementById('newsMsg');
  if(subBtn) {
    subBtn.addEventListener('click', function() {
      const emailField = document.getElementById('newsEmail');
      const email = emailField?.value || '';
      if(email.includes('@') && email.includes('.')) {
        newsMsg.innerHTML = '🎉 Subscribed successfully!';
        newsMsg.classList.add('text-success');
        emailField.value = '';
        setTimeout(() => { newsMsg.innerHTML = ''; }, 3000);
      } else {
        newsMsg.innerHTML = '❌ Please enter a valid email.';
        newsMsg.classList.add('text-danger');
        setTimeout(() => { newsMsg.innerHTML = ''; }, 2500);
      }
    });
  }

  // Enquire buttons alert
  const enquireBtns = document.querySelectorAll('.enquire-btn');
  enquireBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      alert('📞 Thank you for your interest! Call us at +91 98765 43210 for best prices and availability.');
    });
  });
</script>
</body>
</html>