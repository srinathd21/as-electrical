<?php include('includes/head.php'); ?>
<body>

<!-- Navigation Bar -->
<?php include('includes/nav.php'); ?>

<!-- Page Header / Hero (Products specific) -->
<section class="hero" style="padding: 100px 0 80px; background: linear-gradient(135deg, #0f172a 0%, #1e2a3a 100%);">
  <div class="container position-relative">
    <div class="row align-items-center">
      <div class="col-lg-7" data-aos="fade-right" data-aos-duration="800">
        <div class="hero-content">
          <div class="hero-badge"><i class="fas fa-store me-1"></i> Shop with Confidence</div>
          <h1 class="text-white mb-3">Premium <span class="text-warning">Electrical Products</span> at Best Prices</h1>
          <p class="text-white-50 lead">Genuine branded products from Havells, Polycab, Anchor, Philips, Orient, and more. Quality assured with warranty on all items.</p>
          <div class="mt-4 d-flex gap-3 flex-wrap">
            <a href="#product-categories" class="btn btn-electric px-4 py-2"><i class="fas fa-search me-2"></i>Browse Categories</a>
            <a href="contact.php" class="btn btn-outline-light px-4 py-2 rounded-pill"><i class="fas fa-truck me-2"></i>Bulk Orders</a>
          </div>
        </div>
      </div>
      <div class="col-lg-5 text-center" data-aos="fade-left" data-aos-delay="200">
        <img src="https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=500&auto=format" alt="Electrical Products" class="img-fluid rounded-4 shadow-lg" style="max-width: 320px; border-radius: 32px;">
      </div>
    </div>
  </div>
</section>

<!-- Product Categories Navigation -->
<section id="product-categories" class="py-4 bg-light">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-center gap-2" data-aos="fade-up">
      <button class="btn btn-outline-warning rounded-pill active filter-btn" data-filter="all">All Products</button>
      <button class="btn btn-outline-warning rounded-pill filter-btn" data-filter="wires">Wires & Cables</button>
      <button class="btn btn-outline-warning rounded-pill filter-btn" data-filter="switches">Switches & Sockets</button>
      <button class="btn btn-outline-warning rounded-pill filter-btn" data-filter="lights">Lights & Lamps</button>
      <button class="btn btn-outline-warning rounded-pill filter-btn" data-filter="fans">Fans & Appliances</button>
      <button class="btn btn-outline-warning rounded-pill filter-btn" data-filter="solar">Solar Products</button>
      <button class="btn btn-outline-warning rounded-pill filter-btn" data-filter="safety">Safety & Protection</button>
    </div>
  </div>
</section>

<!-- Products Grid Section -->
<section class="py-5" style="background: #fefefe;">
  <div class="container py-4">
    <div class="text-center mb-5" data-aos="fade-up">
      <span class="text-uppercase text-warning fw-bold">Top Quality</span>
      <h2 class="section-title">Our Electrical Product Range</h2>
      <p class="text-secondary w-75 mx-auto">100% genuine products with manufacturer warranty. Competitive prices for retail and bulk purchases.</p>
    </div>
    
    <div class="row g-4" id="products-grid">
      <!-- Wires & Cables Category -->
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="wires">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/2976/2976285.png" alt="Polycab Wire" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5 class="mt-3">Polycab HRFR Wire</h5>
          <p>1.5 sq mm to 10 sq mm, 100% copper, flame retardant</p>
          <div class="price">₹1,199 / Roll</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Polycab HRFR Wire">Enquire Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="wires">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/2976/2976285.png" alt="Havells Wire" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Havells FR Wire</h5>
          <p>Flexible copper wire, ISI marked, lifetime performance</p>
          <div class="price">₹1,450 / Roll</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Havells FR Wire">Enquire Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="wires">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/2976/2976285.png" alt="Finolex Wire" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Finolex Industrial Wire</h5>
          <p>Heavy duty, suitable for industrial & commercial use</p>
          <div class="price">₹2,299 / Roll</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Finolex Industrial Wire">Enquire Now</button>
        </div>
      </div>
      
      <!-- Switches & Sockets -->
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="switches">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3215/3215903.png" alt="Modular Switches" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Anchor Roma Switches</h5>
          <p>Modular switches, 1M to 6M, premium finish, 2 year warranty</p>
          <div class="price">₹349 / Set</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Anchor Roma Switches">Shop Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="switches">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3215/3215903.png" alt="Havells Switches" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Havells Crabtree</h5>
          <p>Premium modular range, anti-microbial, smooth operation</p>
          <div class="price">₹459 / Set</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Havells Crabtree">Shop Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="switches">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3402/3402799.png" alt="Smart Switches" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Smart Wi-Fi Switches</h5>
          <p>Voice control, app enabled, home automation ready</p>
          <div class="price">₹1,299 / Piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Smart Wi-Fi Switches">Shop Now</button>
        </div>
      </div>
      
      <!-- Lights & Lamps -->
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="lights">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/1040/1040220.png" alt="LED Bulb" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Philips LED Bulb</h5>
          <p>9W / 12W / 15W, energy efficient, 2 year warranty</p>
          <div class="price">₹99 / Unit</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Philips LED Bulb">Buy Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="lights">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/1040/1040220.png" alt="LED Tube Light" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Havells LED Batten</h5>
          <p>20W, 4ft, integrated driver, sleek design</p>
          <div class="price">₹399 / Piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Havells LED Batten">Buy Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="lights">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/1040/1040220.png" alt="Panel Light" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>LED Panel Light</h5>
          <p>12W-36W, round/square, for false ceilings</p>
          <div class="price">₹649 / Piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="LED Panel Light">Buy Now</button>
        </div>
      </div>
      
      <!-- Fans & Appliances -->
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="fans">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163408.png" alt="Ceiling Fan" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Orient Ceiling Fan</h5>
          <p>1200mm, high air delivery, energy efficient</p>
          <div class="price">₹2,199 / Piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Orient Ceiling Fan">View Deal</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="fans">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163408.png" alt="BLDC Fan" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Atomberg BLDC Fan</h5>
          <p>Super energy saving, remote control, silent operation</p>
          <div class="price">₹3,999 / Piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Atomberg BLDC Fan">View Deal</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="fans">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163408.png" alt="Exhaust Fan" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Havells Exhaust Fan</h5>
          <p>6 inch, ball bearing, for kitchen & bathroom</p>
          <div class="price">₹1,499 / Piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Havells Exhaust Fan">View Deal</button>
        </div>
      </div>
      
      <!-- Solar Products -->
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="solar">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163403.png" alt="Solar Panel" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Solar Panel 540W</h5>
          <p>Mono perc, 25 years performance warranty</p>
          <div class="price">₹12,999 / Unit</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Solar Panel 540W">Enquire</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="solar">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163403.png" alt="Solar Inverter" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Solar PCU Inverter</h5>
          <p>3kVA - 10kVA, pure sine wave, grid interactive</p>
          <div class="price">₹18,500 / Unit</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Solar PCU Inverter">Enquire</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="solar">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/2818/2818113.png" alt="Solar Battery" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Solar Battery 150Ah</h5>
          <p>Tubular battery, long backup, 5 year warranty</p>
          <div class="price">₹13,999 / Unit</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Solar Battery 150Ah">Enquire</button>
        </div>
      </div>
      
      <!-- Safety & Protection -->
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="safety">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163398.png" alt="MCB" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>MCB / RCCB</h5>
          <p>Havells / Siemens, 6A to 63A, ISI certified</p>
          <div class="price">₹299 / Piece</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="MCB / RCCB">Shop Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="safety">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163390.png" alt="Stabilizer" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Voltage Stabilizer</h5>
          <p>For AC, refrigerator, 1kVA to 5kVA capacity</p>
          <div class="price">₹2,499 / Unit</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Voltage Stabilizer">Shop Now</button>
        </div>
      </div>
      
      <div class="col-sm-6 col-md-4 col-lg-3 product-item" data-category="safety">
        <div class="product-card text-center">
          <img src="https://cdn-icons-png.flaticon.com/512/3163/3163400.png" alt="Distribution Box" class="img-fluid" style="height: 120px; object-fit: contain;">
          <h5>Distribution Box</h5>
          <p>4 way to 16 way, metal/plastic, branded</p>
          <div class="price">₹599 / Unit</div>
          <button class="btn btn-sm btn-outline-warning mt-2 rounded-pill enquire-btn" data-product="Distribution Box">Shop Now</button>
        </div>
      </div>
    </div>
    
    <!-- Bulk Order Note -->
    <div class="text-center mt-5" data-aos="fade-up">
      <div class="bg-warning bg-opacity-10 p-4 rounded-4">
        <h4 class="mb-2"><i class="fas fa-truck-fast text-warning me-2"></i> Bulk Orders & Trade Enquiries</h4>
        <p class="mb-3">Special discounts for contractors, builders, and bulk purchases. Get competitive rates on wholesale quantities.</p>
        <a href="contact.php" class="btn btn-electric rounded-pill px-4">Request Bulk Quote →</a>
      </div>
    </div>
  </div>
</section>

<!-- Featured Brands Section -->
<section class="py-5 bg-light">
  <div class="container py-4">
    <div class="text-center mb-5" data-aos="fade-up">
      <h2 class="section-title">Brands We Carry</h2>
      <p class="text-secondary">Authorized dealers of India's most trusted electrical brands</p>
    </div>
    <div class="row g-4 text-center">
      <div class="col-4 col-md-2" data-aos="zoom-in"><div class="bg-white p-3 rounded-3 shadow-sm"><i class="fas fa-bolt fa-3x text-warning mb-2"></i><h6 class="mb-0">Havells</h6></div></div>
      <div class="col-4 col-md-2" data-aos="zoom-in" data-aos-delay="100"><div class="bg-white p-3 rounded-3 shadow-sm"><i class="fas fa-plug fa-3x text-warning mb-2"></i><h6 class="mb-0">Polycab</h6></div></div>
      <div class="col-4 col-md-2" data-aos="zoom-in" data-aos-delay="200"><div class="bg-white p-3 rounded-3 shadow-sm"><i class="fas fa-lightbulb fa-3x text-warning mb-2"></i><h6 class="mb-0">Philips</h6></div></div>
      <div class="col-4 col-md-2" data-aos="zoom-in" data-aos-delay="300"><div class="bg-white p-3 rounded-3 shadow-sm"><i class="fas fa-fan fa-3x text-warning mb-2"></i><h6 class="mb-0">Orient</h6></div></div>
      <div class="col-4 col-md-2" data-aos="zoom-in" data-aos-delay="400"><div class="bg-white p-3 rounded-3 shadow-sm"><i class="fas fa-charging-station fa-3x text-warning mb-2"></i><h6 class="mb-0">Anchor</h6></div></div>
      <div class="col-4 col-md-2" data-aos="zoom-in" data-aos-delay="500"><div class="bg-white p-3 rounded-3 shadow-sm"><i class="fas fa-solar-panel fa-3x text-warning mb-2"></i><h6 class="mb-0">Luminous</h6></div></div>
    </div>
  </div>
</section>

<!-- Call to Action -->
<section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
  <div class="container py-4 text-center">
    <h2 class="text-white mb-3" data-aos="fade-up">Can't Find What You're Looking For?</h2>
    <p class="text-white-50 mb-4" data-aos="fade-up">Contact us and we'll arrange any specific electrical product for you at the best price.</p>
    <a href="contact.php" class="btn btn-electric btn-lg px-5 py-3" data-aos="zoom-in"><i class="fas fa-envelope me-2"></i>Get Product Quote</a>
  </div>
</section>

<!-- Footer -->
<?php include('includes/footer.php'); ?>

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

  // Product Category Filter
  const filterBtns = document.querySelectorAll('.filter-btn');
  const productItems = document.querySelectorAll('.product-item');
  
  filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      // Update active state
      filterBtns.forEach(b => b.classList.remove('active', 'btn-warning'));
      filterBtns.forEach(b => b.classList.add('btn-outline-warning'));
      this.classList.remove('btn-outline-warning');
      this.classList.add('btn-warning', 'active');
      
      const filterValue = this.getAttribute('data-filter');
      
      productItems.forEach(item => {
        if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
          item.style.display = 'block';
          setTimeout(() => { item.style.opacity = '1'; }, 50);
          item.style.opacity = '0';
          setTimeout(() => { item.style.opacity = '1'; }, 100);
        } else {
          item.style.display = 'none';
        }
      });
    });
  });

  // Enquire buttons alert
  const enquireBtns = document.querySelectorAll('.enquire-btn');
  enquireBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      const productName = btn.getAttribute('data-product') || 'this product';
      alert(`📞 Thank you for your interest in ${productName}!\n\nCall us at +91 98765 43210 or visit our shop for best prices and availability.`);
    });
  });

  // Newsletter subscription (if elements exist in footer)
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
</script>
</body>
</html>