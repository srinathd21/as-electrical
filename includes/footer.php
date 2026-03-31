<footer class="footer pt-5 pb-4 mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-5">
        <h3 class="text-white">AS <span class="text-warning">Electricals</span></h3>
        <p>Your trusted electrical partner in Tiruvannamalai — quality, safety, and innovation in every connection.</p>
      </div>
      <div class="col-md-3">
        <h5 class="text-white">Quick Links</h5>
        <ul class="list-unstyled">
          <li><a href="index.php" class="text-secondary text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-warning' : ''; ?>">Home</a></li>
          <li><a href="services.php" class="text-secondary text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'text-warning' : ''; ?>">Services</a></li>
          <li><a href="products.php" class="text-secondary text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'text-warning' : ''; ?>">Products</a></li>
          <li><a href="about.php" class="text-secondary text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'text-warning' : ''; ?>">About</a></li>
          <li><a href="contact.php" class="text-secondary text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'text-warning' : ''; ?>">Contact</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h5 class="text-white">Newsletter</h5>
        <p>Get updates on new arrivals & offers</p>
        <div class="input-group">
          <input type="email" class="form-control bg-dark text-white border-0" placeholder="Your email" id="newsEmail">
          <button class="btn btn-warning" id="subscribeBtn">Subscribe</button>
        </div>
        <small id="newsMsg" class="text-info"></small>
      </div>
    </div>
    <hr class="bg-secondary mt-4">
    <div class="text-center pt-3 small">© 2025 AS Electricals — All Rights Reserved. Designed for Tiruvannamalai.</div>
  </div>
</footer>

<!-- Floating WhatsApp Button -->
<a href="https://wa.me/918610786637" target="_blank" class="whatsapp-float">
  <i class="fab fa-whatsapp"></i>
</a>

<!-- Styles -->
<style>
.whatsapp-float {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background: #25D366;
  color: #fff;
  font-size: 26px;
  width: 55px;
  height: 55px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  text-decoration: none;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
  z-index: 999;
  transition: 0.3s ease;
}

.whatsapp-float:hover {
  background: #1ebe5d;
  transform: scale(1.1);
}
</style>

<!-- Font Awesome (if not already added) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">