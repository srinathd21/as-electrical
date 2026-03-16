<script>
// ==================== GLOBAL STATE ====================
let CART = [];
let PRODUCTS = <?php echo json_encode($jsProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let BARCODE_MAP = <?php echo json_encode($barcodeMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let CUSTOMERS = [];
let REFERRALS = [];
let LOYALTY_SETTINGS = <?php echo json_encode($loyalty_settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let CUSTOMER_POINTS = {
    available_points: 0,
    total_points_earned: 0,
    total_points_redeemed: 0
};

let GLOBAL_PRICE_TYPE = 'retail';
let GST_TYPE = 'gst';
let ACTIVE_PAYMENT_METHODS = new Set(['cash']);
let SELECTED_REFERRAL_ID = null;
let CURRENT_CUSTOMER_ID = null;
let CURRENT_CUSTOMER_NAME = 'Walk-in Customer';
let LOYALTY_POINTS_DISCOUNT = 0;
let POINTS_USED = 0;
let PENDING_CONFIRMATION = null;
let IS_INITIALIZED = false;
let CURRENT_PRODUCT = null;
let CURRENT_UNIT_IS_SECONDARY = false;
let CURRENT_CATEGORY = 0;

// Constants from PHP
const SHOP_ID = <?= $shop_id ?>;
const WAREHOUSE_ID = <?= $warehouse_id ?>;
const BUSINESS_ID = <?= $business_id ?>;
const USER_ID = <?= $user_id ?>;

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Touch POS: Initializing...');
    try {
        initializeApp();
        setupEventListeners();
        loadInitialData();
    } catch (error) {
        console.error('Touch POS: Initialization failed:', error);
        showToast('System initialization failed. Please refresh the page.', 'error');
    }
});

function initializeApp() {
    console.log('Touch POS: Initializing...');
    
    try {
        // Set today's date
        const today = new Date().toISOString().split('T')[0];
        if (!document.getElementById('invoice-date')) {
            // Create invoice date input if it doesn't exist
            const dateControl = document.createElement('input');
            dateControl.type = 'date';
            dateControl.id = 'invoice-date';
            dateControl.value = today;
            dateControl.style.padding = '6px';
            dateControl.style.border = '1px solid #ddd';
            dateControl.style.borderRadius = '4px';
            dateControl.style.fontSize = '12px';
            document.querySelector('.advanced-controls .control-group:last-child').appendChild(dateControl);
        }
        
        // Generate invoice number
        generateInvoiceNumber();
        
        // Initialize UI
        initializeTouchUI();
        renderCart();
        updateBillingSummary();
        
        IS_INITIALIZED = true;
        console.log('Touch POS: Application initialized successfully');
        
    } catch (error) {
        console.error('Touch POS: Application initialization error:', error);
        showToast(`Initialization error: ${error.message}. Some features may not work.`, 'error');
    }
}

function initializeTouchUI() {
    // Make buttons more touch-friendly
    document.querySelectorAll('button').forEach(btn => {
        btn.style.minHeight = '44px';
        btn.style.minWidth = '44px';
    });
    
    // Initialize category display
    updateCategoryDisplay();
}

async function loadInitialData() {
    console.log('Touch POS: Loading initial data...');
    
    try {
        // Load customers in background
        setTimeout(() => {
            loadCustomers().catch(() => console.warn('Customers load failed'));
        }, 1000);
        
        console.log(`Touch POS: Ready with ${PRODUCTS.length} products loaded locally`);
        showToast('System ready!', 'success');
        
    } catch (error) {
        console.error('Touch POS: Initial data loading failed:', error);
        showToast('Could not load initial data. Please check connection.', 'error');
    }
}

// ==================== CUSTOMER MODAL FUNCTIONS ====================
function showCustomerModal() {
    try {
        const modalBody = document.getElementById('customer-modal-body');
        
        // Simple customer modal for now
        modalBody.innerHTML = `
            <div class="customer-input-group">
                <label class="form-label">Customer Name</label>
                <input type="text" class="form-control" id="customer-name" 
                       placeholder="Enter customer name" value="${CURRENT_CUSTOMER_NAME}">
            </div>
            <div class="customer-input-group">
                <label class="form-label">Phone Number</label>
                <input type="text" class="form-control" id="customer-phone-display" 
                       placeholder="Enter phone number">
            </div>
            <div class="customer-input-group">
                <label class="form-label">Address</label>
                <textarea class="form-control" id="customer-address" 
                          placeholder="Enter address" rows="2"></textarea>
            </div>
            <div class="customer-input-group">
                <label class="form-label">GSTIN (Optional)</label>
                <input type="text" class="form-control" id="customer-gstin" 
                       placeholder="Enter GSTIN">
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeModal('customer')">Cancel</button>
                <button class="btn btn-primary" onclick="saveCustomerDetails()">Save</button>
            </div>
        `;
        
        document.getElementById('customer-modal').style.display = 'block';
        
    } catch (error) {
        console.error('Error showing customer modal:', error);
        showToast('Error loading customer modal', 'error');
    }
}

function saveCustomerDetails() {
    try {
        const name = document.getElementById('customer-name').value.trim();
        const phone = document.getElementById('customer-phone-display').value.trim();
        
        if (!name) {
            showToast('Please enter customer name', 'warning');
            return;
        }
        
        CURRENT_CUSTOMER_NAME = name;
        document.getElementById('customer-display').textContent = name;
        
        // For now, we'll just save locally. In production, this should save to database.
        showToast(`Customer set to: ${name}`, 'success');
        closeModal('customer');
        
    } catch (error) {
        console.error('Error saving customer details:', error);
        showToast('Error saving customer details', 'error');
    }
}

// ==================== PRODUCT FUNCTIONS ====================
function findProductById(id) {
    if (!id || isNaN(id)) {
        console.warn('Touch POS: Invalid product ID:', id);
        return null;
    }
    
    const product = PRODUCTS.find(p => p.id == id);
    if (!product) {
        console.warn('Touch POS: Product not found with ID:', id);
        return null;
    }
    
    // Ensure stock values are properly set
    product.shop_stock_primary = parseFloat(product.shop_stock) || 0;
    if (product.secondary_unit && product.sec_unit_conversion) {
        const conversion = parseFloat(product.sec_unit_conversion) || 1;
        product.shop_stock_secondary = product.shop_stock_primary * conversion;
    } else {
        product.shop_stock_secondary = 0;
    }
    
    return product;
}

function findProductByBarcode(code) {
    if (!code || typeof code !== 'string') {
        console.warn('Touch POS: Invalid barcode:', code);
        return null;
    }
    
    const cleanCode = String(code).trim();
    
    // Check barcode map first
    const prodId = BARCODE_MAP[cleanCode];
    if (prodId) {
        const product = findProductById(prodId);
        if (product) return product;
    }
    
    // Fallback search
    return PRODUCTS.find(p => 
        (p.barcode && p.barcode === cleanCode) || 
        (p.code && p.code === cleanCode)
    );
}

function showProductModal(product) {
    try {
        CURRENT_PRODUCT = product;
        CURRENT_UNIT_IS_SECONDARY = false;
        
        const price = GLOBAL_PRICE_TYPE === 'wholesale' ? 
            parseFloat(product.wholesale_price) : parseFloat(product.retail_price);
        const mrp = parseFloat(product.mrp) || 0;
        
        // Create modal HTML
        const modalHTML = `
            <div class="modal" id="product-quantity-modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title">Add ${escapeHtml(product.name)}</div>
                        <button class="modal-close" onclick="closeModal('product-quantity')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                            <div style="font-weight: bold; font-size: 14px;">${escapeHtml(product.name)}</div>
                            <div style="font-size: 12px; color: #666;">Code: ${escapeHtml(product.code)}</div>
                            <div style="font-size: 12px; color: #666;">Price: ₹${price.toFixed(2)} (${GLOBAL_PRICE_TYPE})</div>
                            ${mrp > 0 ? `<div style="font-size: 12px; color: #666;">MRP: ₹${mrp.toFixed(2)}</div>` : ''}
                            <div style="font-size: 12px; color: #666;">Stock: ${product.shop_stock_primary} ${product.unit_of_measure || 'PCS'}</div>
                            ${product.secondary_unit ? `<div style="font-size: 12px; color: #666;">Secondary Unit: ${product.secondary_unit} (${product.sec_unit_conversion} per primary)</div>` : ''}
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="modal-quantity" value="1" min="0.01" step="0.01">
                            ${product.secondary_unit ? `
                                <select class="form-control" id="modal-unit" onchange="toggleUnitConversion()" style="margin-top: 5px;">
                                    <option value="primary">${product.unit_of_measure || 'PCS'}</option>
                                    <option value="secondary">${product.secondary_unit}</option>
                                </select>
                            ` : ''}
                        </div>
                        
                        <div class="numpad" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin: 15px 0;">
                            <button class="num-btn" data-num="1">1</button>
                            <button class="num-btn" data-num="2">2</button>
                            <button class="num-btn" data-num="3">3</button>
                            <button class="num-btn" data-num="4">4</button>
                            <button class="num-btn" data-num="5">5</button>
                            <button class="num-btn" data-num="6">6</button>
                            <button class="num-btn" data-num="7">7</button>
                            <button class="num-btn" data-num="8">8</button>
                            <button class="num-btn" data-num="9">9</button>
                            <button class="num-btn" data-num="0">0</button>
                            <button class="num-btn" id="modal-backspace">⌫</button>
                            <button class="num-btn" id="modal-clear">C</button>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button class="btn btn-secondary" onclick="closeModal('product-quantity')" style="flex: 1;">Cancel</button>
                            <button class="btn btn-primary" onclick="addProductToCart()" style="flex: 1;">Add to Cart</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('product-quantity-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Setup numpad
        setTimeout(() => {
            document.querySelectorAll('.num-btn[data-num]').forEach(btn => {
                btn.onclick = function() {
                    const num = this.getAttribute('data-num');
                    const input = document.getElementById('modal-quantity');
                    input.value = input.value === '0' ? num : input.value + num;
                };
            });
            
            document.getElementById('modal-backspace').onclick = function() {
                const input = document.getElementById('modal-quantity');
                input.value = input.value.slice(0, -1) || '1';
            };
            
            document.getElementById('modal-clear').onclick = function() {
                document.getElementById('modal-quantity').value = '1';
            };
        }, 100);
        
    } catch (error) {
        console.error('Error showing product modal:', error);
        showToast('Error showing product details', 'error');
    }
}

function toggleUnitConversion() {
    try {
        if (!CURRENT_PRODUCT) return;
        
        const unitSelect = document.getElementById('modal-unit');
        const qtyInput = document.getElementById('modal-quantity');
        
        if (!unitSelect || !qtyInput) return;
        
        if (unitSelect.value === 'secondary') {
            CURRENT_UNIT_IS_SECONDARY = true;
            // Convert quantity to secondary units
            const conversion = parseFloat(CURRENT_PRODUCT.sec_unit_conversion) || 1;
            qtyInput.value = (parseFloat(qtyInput.value) * conversion).toFixed(2);
        } else {
            CURRENT_UNIT_IS_SECONDARY = false;
            // Convert quantity to primary units
            const conversion = parseFloat(CURRENT_PRODUCT.sec_unit_conversion) || 1;
            qtyInput.value = (parseFloat(qtyInput.value) / conversion).toFixed(2);
        }
        
    } catch (error) {
        console.error('Error toggling unit conversion:', error);
    }
}

// ==================== CART FUNCTIONS ====================
function addProductToCart() {
    try {
        if (!CURRENT_PRODUCT) {
            showToast('Please select a product first', 'warning');
            return;
        }
        
        const product = CURRENT_PRODUCT;
        const qtyInput = document.getElementById('modal-quantity');
        let qty = parseFloat(qtyInput.value) || 1;
        
        if (qty <= 0) {
            showToast('Please enter a valid quantity (greater than 0)', 'warning');
            return;
        }
        
        if (isNaN(qty)) {
            showToast('Invalid quantity entered. Please enter a number.', 'error');
            qtyInput.value = '1';
            return;
        }
        
        // Check stock
        const shopStockPrimary = parseFloat(product.shop_stock_primary) || 0;
        const secUnitConversion = parseFloat(product.sec_unit_conversion) || 1;
        
        if (CURRENT_UNIT_IS_SECONDARY && secUnitConversion > 0) {
            // Convert secondary quantity to primary units
            const qtyInPrimary = qty / secUnitConversion;
            
            if (qtyInPrimary > shopStockPrimary) {
                const availableSecondary = Math.floor(shopStockPrimary * secUnitConversion);
                showToast(
                    `Insufficient stock! Available: ${shopStockPrimary} ${product.unit_of_measure} (≈${availableSecondary} ${product.secondary_unit})`,
                    'warning'
                );
                return;
            }
        } else {
            if (qty > shopStockPrimary) {
                showToast(
                    `Insufficient stock! Available: ${shopStockPrimary} ${product.unit_of_measure}`,
                    'warning'
                );
                return;
            }
        }
        
        // Get price based on price type
        let price = 0;
        if (GLOBAL_PRICE_TYPE === 'wholesale') {
            price = parseFloat(product.wholesale_price) || parseFloat(product.retail_price) || 0;
        } else {
            price = parseFloat(product.retail_price) || 0;
        }
        
        // For secondary units, calculate price
        let finalPrice = price;
        let quantityInPrimary = qty;
        
        if (CURRENT_UNIT_IS_SECONDARY && secUnitConversion > 0) {
            quantityInPrimary = qty / secUnitConversion;
            
            // Apply extra charge for secondary units
            const extraCharge = parseFloat(product.sec_unit_extra_charge) || 0;
            const priceType = product.sec_unit_price_type || 'fixed';
            
            if (priceType === 'percentage') {
                const extraAmount = price * (extraCharge / 100);
                finalPrice = (price + extraAmount) / secUnitConversion;
            } else {
                finalPrice = (price + extraCharge) / secUnitConversion;
            }
            finalPrice = parseFloat(finalPrice.toFixed(2));
        } else {
            finalPrice = Math.round(price);
        }
        
        // Create cart item
        const cartItem = {
            id: `${product.id}-${CURRENT_UNIT_IS_SECONDARY ? 'secondary' : 'primary'}-${finalPrice}`,
            product_id: product.id,
            name: product.name,
            code: product.code || product.id.toString(),
            mrp: parseFloat(product.mrp) || 0,
            base_price: price,
            price: finalPrice,
            price_type: GLOBAL_PRICE_TYPE,
            quantity: qty,
            unit: CURRENT_UNIT_IS_SECONDARY ? product.secondary_unit : (product.unit_of_measure || 'PCS'),
            is_secondary_unit: CURRENT_UNIT_IS_SECONDARY,
            discount_value: 0,
            discount_type: 'percentage',
            discount_amount: 0,
            shop_stock: shopStockPrimary,
            hsn_code: product.hsn_code || '',
            cgst_rate: parseFloat(product.cgst_rate) || 0,
            sgst_rate: parseFloat(product.sgst_rate) || 0,
            igst_rate: parseFloat(product.igst_rate) || 0,
            referral_enabled: product.referral_enabled || 0,
            referral_type: product.referral_type || 'percentage',
            referral_value: parseFloat(product.referral_value) || 0,
            referral_commission: 0,
            secondary_unit: product.secondary_unit || '',
            sec_unit_conversion: secUnitConversion,
            stock_price: parseFloat(product.stock_price) || 0,
            retail_price: parseFloat(product.retail_price) || 0,
            wholesale_price: parseFloat(product.wholesale_price) || 0,
            unit_of_measure: product.unit_of_measure || 'PCS',
            quantity_in_primary: quantityInPrimary,
            added_at: new Date().toISOString(),
            total: finalPrice * qty,
            category_name: product.category || '',
            subcategory_name: product.subcategory || ''
        };
        
        // Check if item already exists in cart
        const existingIndex = CART.findIndex(item => 
            item.product_id === cartItem.product_id && 
            item.unit === cartItem.unit &&
            Math.abs(item.price - cartItem.price) < 0.01 &&
            item.is_secondary_unit === cartItem.is_secondary_unit
        );
        
        if (existingIndex >= 0) {
            // Update quantity
            const newQty = CART[existingIndex].quantity + qty;
            const newQtyInPrimary = CART[existingIndex].quantity_in_primary + quantityInPrimary;
            
            // Check stock again
            if (newQtyInPrimary > shopStockPrimary) {
                const availableQty = CURRENT_UNIT_IS_SECONDARY ? 
                    Math.floor((shopStockPrimary - CART[existingIndex].quantity_in_primary) * secUnitConversion) :
                    (shopStockPrimary - CART[existingIndex].quantity_in_primary);
                
                showToast(
                    `Insufficient stock for additional quantity. Available: ${Math.floor(availableQty)} ${cartItem.unit}`,
                    'warning'
                );
                return;
            }
            
            CART[existingIndex].quantity = newQty;
            CART[existingIndex].quantity_in_primary = newQtyInPrimary;
            CART[existingIndex].total = CART[existingIndex].price * newQty;
            
            showToast(`${product.name} quantity updated to ${newQty} ${cartItem.unit}`, 'info');
            
        } else {
            // Add new item
            CART.push(cartItem);
            showToast(`${product.name} added to cart`, 'success');
        }
        
        // Close modal and update UI
        closeModal('product-quantity');
        renderCart();
        updateBillingSummary();
        updateButtonStates();
        
    } catch (error) {
        console.error('Error adding product to cart:', error);
        showToast('Error adding product to cart. Please try again.', 'error');
    }
}

function renderCart() {
    try {
        const container = document.getElementById('cart-items');
        const cartCount = document.getElementById('cart-count');
        
        if (!container) {
            console.error('Cart container not found');
            return;
        }
        
        cartCount.textContent = `${CART.length} items`;
        
        if (CART.length === 0) {
            container.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart fa-2x text-muted mb-3"></i>
                    <p class="text-muted">No items in cart</p>
                    <p class="text-muted" style="font-size: 13px;">Tap products to add</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        CART.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            
            // Calculate primary unit equivalent
            let primaryUnitDisplay = '';
            if (item.is_secondary_unit && item.sec_unit_conversion > 0) {
                const primaryQty = item.quantity / item.sec_unit_conversion;
                primaryUnitDisplay = `${primaryQty.toFixed(2)} ${item.unit_of_measure}`;
            }
            
            html += `
                <div class="cart-item">
                    <div class="cart-item-header">
                        <div class="cart-item-title">
                            <strong>${escapeHtml(item.name)}</strong>
                            <span class="badge ${item.price_type === 'wholesale' ? 'badge-wh' : 'badge-gst'}">
                                ${item.price_type === 'wholesale' ? 'Wholesale' : 'Retail'}
                            </span>
                            ${item.is_secondary_unit ? `<span class="badge secondary">${item.secondary_unit}</span>` : ''}
                        </div>
                        <button class="cart-item-remove" onclick="removeCartItem(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="cart-item-body">
                        <div class="cart-item-info">
                            <div>Code: ${escapeHtml(item.code)}</div>
                            <div>Price: ₹${item.price.toFixed(item.is_secondary_unit ? 2 : 0)}/${item.unit}</div>
                            ${primaryUnitDisplay ? `<div>Primary: ${primaryUnitDisplay}</div>` : ''}
                            ${item.mrp > 0 ? `<div>MRP: ₹${item.mrp.toFixed(2)}</div>` : ''}
                        </div>
                        
                        <div class="cart-item-controls">
                            <div class="qty-control-group">
                                <button class="qty-btn" onclick="updateCartItemQuantity(${index}, -0.5)">-½</button>
                                <button class="qty-btn" onclick="updateCartItemQuantity(${index}, -1)">-1</button>
                                <div class="qty-display">${item.is_secondary_unit ? item.quantity.toFixed(2) : Math.round(item.quantity)}</div>
                                <button class="qty-btn" onclick="updateCartItemQuantity(${index}, 1)">+1</button>
                                <button class="qty-btn" onclick="updateCartItemQuantity(${index}, 0.5)">+½</button>
                            </div>
                            
                            <div class="discount-control-group">
                                <input type="number" class="discount-input" 
                                       value="${item.discount_value || 0}" 
                                       onchange="updateCartItemDiscount(${index}, this.value)"
                                       placeholder="Disc" step="0.01" min="0">
                                <select class="discount-type" 
                                        onchange="updateCartItemDiscountType(${index}, this.value)">
                                    <option value="percentage" ${item.discount_type === 'percentage' ? 'selected' : ''}>%</option>
                                    <option value="fixed" ${item.discount_type === 'fixed' ? 'selected' : ''}>₹</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cart-item-footer">
                        <div class="cart-item-total">
                            Total: <strong>₹${item.is_secondary_unit ? itemTotal.toFixed(2) : Math.round(itemTotal)}</strong>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error rendering cart:', error);
        showToast('Error displaying cart. Please refresh page.', 'error');
    }
}

function updateCartItemQuantity(index, change) {
    try {
        if (!CART[index]) return;
        
        const item = CART[index];
        const product = findProductById(item.product_id);
        
        if (!product) return;
        
        const newQty = item.quantity + change;
        
        if (newQty <= 0) {
            removeCartItem(index);
            return;
        }
        
        // Check stock
        const shopStockPrimary = parseFloat(product.shop_stock_primary) || 0;
        const secUnitConversion = parseFloat(item.sec_unit_conversion) || 1;
        
        if (item.is_secondary_unit && secUnitConversion > 0) {
            const newQtyInPrimary = newQty / secUnitConversion;
            
            // Check total quantity in cart
            let totalQtyInCart = newQtyInPrimary;
            CART.forEach((cartItem, idx) => {
                if (idx !== index && cartItem.product_id === item.product_id) {
                    totalQtyInCart += cartItem.quantity_in_primary;
                }
            });
            
            if (totalQtyInCart > shopStockPrimary) {
                const availableForThisItem = shopStockPrimary;
                CART.forEach((cartItem, idx) => {
                    if (idx !== index && cartItem.product_id === item.product_id) {
                        availableForThisItem -= cartItem.quantity_in_primary;
                    }
                });
                
                const availableSecondary = Math.floor(availableForThisItem * secUnitConversion);
                showToast(
                    `Insufficient stock. Available: ${availableSecondary} ${item.secondary_unit}`,
                    'warning'
                );
                return;
            }
            
            CART[index].quantity = newQty;
            CART[index].quantity_in_primary = newQtyInPrimary;
            
        } else {
            // Check total quantity in cart
            let totalQtyInCart = newQty;
            CART.forEach((cartItem, idx) => {
                if (idx !== index && cartItem.product_id === item.product_id) {
                    totalQtyInCart += cartItem.quantity;
                }
            });
            
            if (totalQtyInCart > shopStockPrimary) {
                const availableForThisItem = shopStockPrimary;
                CART.forEach((cartItem, idx) => {
                    if (idx !== index && cartItem.product_id === item.product_id) {
                        availableForThisItem -= cartItem.quantity;
                    }
                });
                
                showToast(
                    `Insufficient stock. Available: ${availableForThisItem} ${item.unit_of_measure}`,
                    'warning'
                );
                return;
            }
            
            CART[index].quantity = newQty;
            CART[index].quantity_in_primary = newQty;
        }
        
        CART[index].total = CART[index].price * CART[index].quantity;
        
        renderCart();
        updateBillingSummary();
        
    } catch (error) {
        console.error('Error updating cart item quantity:', error);
        showToast('Error updating quantity. Please try again.', 'error');
    }
}

function updateCartItemDiscount(index, value) {
    try {
        if (!CART[index]) return;
        
        const discount = parseFloat(value) || 0;
        CART[index].discount_value = discount;
        
        // Recalculate item price
        updateCartItemPrice(index);
        
        renderCart();
        updateBillingSummary();
        
    } catch (error) {
        console.error('Error updating cart item discount:', error);
        showToast('Error updating discount. Please try again.', 'error');
    }
}

function updateCartItemDiscountType(index, type) {
    try {
        if (!CART[index]) return;
        
        CART[index].discount_type = type;
        
        // Recalculate item price
        updateCartItemPrice(index);
        
        renderCart();
        updateBillingSummary();
        
    } catch (error) {
        console.error('Error updating cart item discount type:', error);
        showToast('Error updating discount type. Please try again.', 'error');
    }
}

function updateCartItemPrice(index) {
    try {
        const item = CART[index];
        if (!item) return;
        
        const product = findProductById(item.product_id);
        if (!product) return;
        
        // Get base price
        let basePrice = 0;
        if (item.price_type === 'wholesale') {
            basePrice = parseFloat(product.wholesale_price) || parseFloat(product.retail_price) || 0;
        } else {
            basePrice = parseFloat(product.retail_price) || 0;
        }
        
        // Apply discount to base price
        let finalPrice = basePrice;
        let discountAmount = 0;
        
        if (item.discount_type === 'percentage' && item.discount_value > 0) {
            discountAmount = basePrice * (item.discount_value / 100);
            finalPrice = basePrice - discountAmount;
        } else if (item.discount_type === 'fixed' && item.discount_value > 0) {
            discountAmount = item.discount_value;
            finalPrice = basePrice - discountAmount;
        }
        
        if (finalPrice < 0) finalPrice = 0;
        
        // For secondary units, apply conversion
        if (item.is_secondary_unit && item.sec_unit_conversion > 0) {
            const conversion = parseFloat(item.sec_unit_conversion) || 1;
            const extraCharge = parseFloat(product.sec_unit_extra_charge) || 0;
            const priceType = product.sec_unit_price_type || 'fixed';
            
            if (priceType === 'percentage') {
                const extraAmount = finalPrice * (extraCharge / 100);
                finalPrice = (finalPrice + extraAmount) / conversion;
            } else {
                finalPrice = (finalPrice + extraCharge) / conversion;
            }
            finalPrice = parseFloat(finalPrice.toFixed(2));
        } else {
            finalPrice = Math.round(finalPrice);
        }
        
        // Update item
        item.base_price = basePrice;
        item.price = finalPrice;
        item.discount_amount = discountAmount;
        item.total = finalPrice * item.quantity;
        
    } catch (error) {
        console.error('Error updating cart item price:', error);
    }
}

function removeCartItem(index) {
    try {
        if (index >= 0 && index < CART.length) {
            const itemName = CART[index].name;
            CART.splice(index, 1);
            renderCart();
            updateBillingSummary();
            updateButtonStates();
            showToast(`${itemName} removed from cart`, 'info');
        }
    } catch (error) {
        console.error('Error removing cart item:', error);
        showToast('Error removing item. Please try again.', 'error');
    }
}

function clearCart() {
    if (CART.length === 0) {
        showToast('Cart is already empty', 'info');
        return;
    }
    
    showConfirmation(
        'Clear Cart',
        `Are you sure you want to clear all ${CART.length} items from the cart? This action cannot be undone.`,
        function() {
            CART = [];
            renderCart();
            updateBillingSummary();
            updateButtonStates();
            showToast('Cart cleared successfully', 'success');
        }
    );
}

// ==================== CALCULATION FUNCTIONS ====================
function calculateItemGST(item) {
    try {
        if (GST_TYPE === 'non-gst' || ((item.cgst_rate || 0) + (item.sgst_rate || 0) + (item.igst_rate || 0)) <= 0) {
            return { 
                taxable: item.price * item.quantity, 
                cgst: 0, 
                sgst: 0, 
                igst: 0, 
                total: 0 
            };
        }
        
        const itemTotal = item.price * item.quantity;
        const totalGSTRate = (item.cgst_rate || 0) + (item.sgst_rate || 0) + (item.igst_rate || 0);
        
        // GST is included in the price
        const taxableValue = itemTotal / (1 + (totalGSTRate / 100));
        const gstAmount = itemTotal - taxableValue;
        
        // Distribute GST among components
        let cgst = 0, sgst = 0, igst = 0;
        if (totalGSTRate > 0) {
            cgst = gstAmount * (item.cgst_rate / totalGSTRate);
            sgst = gstAmount * (item.sgst_rate / totalGSTRate);
            igst = gstAmount * (item.igst_rate / totalGSTRate);
        }
        
        return {
            taxable: taxableValue,
            cgst: cgst,
            sgst: sgst,
            igst: igst,
            total: gstAmount
        };
    } catch (error) {
        console.error('Error calculating item GST:', error);
        return { taxable: 0, cgst: 0, sgst: 0, igst: 0, total: 0 };
    }
}

function calculateItemReferralCommission(item) {
    try {
        if (!item.referral_enabled || !SELECTED_REFERRAL_ID) {
            return 0;
        }
        
        const itemTotal = item.price * item.quantity;
        
        if (item.referral_type === 'percentage') {
            return itemTotal * (item.referral_value / 100);
        } else {
            return item.referral_value * item.quantity;
        }
    } catch (error) {
        console.error('Error calculating referral commission:', error);
        return 0;
    }
}

function calculateTotals() {
    try {
        let subtotal = 0;
        let totalItemDiscount = 0;
        let totalTaxable = 0;
        let totalCGST = 0;
        let totalSGST = 0;
        let totalIGST = 0;
        let totalReferralCommission = 0;
        
        CART.forEach(item => {
            const itemTotal = item.price * item.quantity;
            const itemGST = calculateItemGST(item);
            const itemReferralCommission = calculateItemReferralCommission(item);
            
            subtotal += itemTotal;
            totalTaxable += itemGST.taxable || 0;
            totalCGST += itemGST.cgst;
            totalSGST += itemGST.sgst;
            totalIGST += itemGST.igst;
            totalReferralCommission += itemReferralCommission;
            
            // Calculate item discount
            const product = findProductById(item.product_id);
            if (product && item.discount_value > 0) {
                let basePrice = item.price_type === 'wholesale' ? 
                    parseFloat(product.wholesale_price) : parseFloat(product.retail_price);
                
                if (item.discount_type === 'percentage') {
                    totalItemDiscount += (basePrice * (item.discount_value / 100)) * item.quantity;
                } else {
                    totalItemDiscount += item.discount_value * item.quantity;
                }
            }
        });
        
        const subtotalAfterItems = subtotal - totalItemDiscount;
        
        // Overall discount
        const overallDiscVal = parseFloat(document.getElementById('overall-discount-value').value) || 0;
        const overallDiscType = document.getElementById('overall-discount-type').value;
        let overallDiscount = 0;
        
        if (overallDiscType === 'percentage') {
            overallDiscount = subtotalAfterItems * (overallDiscVal / 100);
        } else {
            overallDiscount = Math.min(overallDiscVal, subtotalAfterItems);
        }
        
        const totalBeforePoints = Math.max(0, subtotalAfterItems - overallDiscount);
        
        // Loyalty points discount
        const pointsDiscount = LOYALTY_POINTS_DISCOUNT > totalBeforePoints ? 
            totalBeforePoints : LOYALTY_POINTS_DISCOUNT;
        
        // GST total
        const totalGST = GST_TYPE === 'gst' ? (totalCGST + totalSGST + totalIGST) : 0;
        
        // Grand total
        const grandTotal = Math.max(0, totalBeforePoints - pointsDiscount + totalGST);
        
        return {
            subtotal: parseFloat(subtotal.toFixed(2)),
            totalItemDiscount: parseFloat(totalItemDiscount.toFixed(2)),
            overallDiscount: parseFloat(overallDiscount.toFixed(2)),
            pointsDiscount: parseFloat(pointsDiscount.toFixed(2)),
            totalTaxable: parseFloat(totalTaxable.toFixed(2)),
            totalCGST: parseFloat(totalCGST.toFixed(2)),
            totalSGST: parseFloat(totalSGST.toFixed(2)),
            totalIGST: parseFloat(totalIGST.toFixed(2)),
            totalGST: parseFloat(totalGST.toFixed(2)),
            totalReferralCommission: parseFloat(totalReferralCommission.toFixed(2)),
            grandTotal: parseFloat(grandTotal.toFixed(2))
        };
        
    } catch (error) {
        console.error('Error calculating totals:', error);
        return {
            subtotal: 0,
            totalItemDiscount: 0,
            overallDiscount: 0,
            pointsDiscount: 0,
            totalTaxable: 0,
            totalCGST: 0,
            totalSGST: 0,
            totalIGST: 0,
            totalGST: 0,
            totalReferralCommission: 0,
            grandTotal: 0
        };
    }
}

function updateBillingSummary() {
    try {
        const totals = calculateTotals();
        
        // Update displays
        document.getElementById('subtotal').textContent = `₹${totals.subtotal.toFixed(2)}`;
        document.getElementById('item-discount').textContent = `-₹${totals.totalItemDiscount.toFixed(2)}`;
        document.getElementById('overall-discount-amount').textContent = `-₹${totals.overallDiscount.toFixed(2)}`;
        document.getElementById('gst-amount').textContent = `₹${totals.totalGST.toFixed(2)}`;
        document.getElementById('total').textContent = `₹${totals.grandTotal.toFixed(2)}`;
        
        // Show/hide loyalty points discount
        const loyaltyRow = document.getElementById('loyalty-discount-row');
        if (totals.pointsDiscount > 0) {
            loyaltyRow.style.display = 'flex';
            document.getElementById('loyalty-discount').textContent = `-₹${totals.pointsDiscount.toFixed(2)}`;
        } else {
            loyaltyRow.style.display = 'none';
        }
        
        // Show/hide referral commission
        const referralRow = document.getElementById('referral-commission-row');
        if (totals.totalReferralCommission > 0) {
            referralRow.style.display = 'flex';
            document.getElementById('referral-commission').textContent = `₹${totals.totalReferralCommission.toFixed(2)}`;
        } else {
            referralRow.style.display = 'none';
        }
        
        // Show/hide GST based on bill type
        document.getElementById('gst-row').style.display = GST_TYPE === 'gst' ? 'flex' : 'none';
        
        // Update payment summary
        updatePaymentSummary();
        
    } catch (error) {
        console.error('Error updating billing summary:', error);
        showToast('Error updating bill summary. Please refresh page.', 'error');
    }
}

// ==================== INVOICE FUNCTIONS ====================
function generateInvoiceNumber() {
    try {
        const prefix = GST_TYPE === 'gst' ? 'INV' : 'INVNG';
        const now = new Date();
        const year = now.getFullYear();
        const month = (now.getMonth() + 1).toString().padStart(2, '0');
        const day = now.getDate().toString().padStart(2, '0');
        const timestamp = Date.now().toString().slice(-4);
        
        // Generate temporary invoice number
        const tempNumber = `${prefix}${year}${month}${day}-${timestamp}`;
        
        // Update invoice display
        const invoiceDisplay = document.getElementById('invoice-display');
        if (invoiceDisplay) {
            invoiceDisplay.textContent = tempNumber;
        }
        
        return tempNumber;
    } catch (error) {
        console.error('Error generating invoice number:', error);
        return 'INV-ERROR-' + Date.now();
    }
}

// ==================== PAYMENT FUNCTIONS ====================
function showPaymentModal() {
    try {
        const totals = calculateTotals();
        if (totals.grandTotal <= 0) {
            showToast('Add items to cart first', 'warning');
            return;
        }
        
        // Create payment modal
        const modalHTML = `
            <div class="modal" id="payment-modal" style="display: block;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title">Process Payment</div>
                        <button class="modal-close" onclick="closeModal('payment')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="font-size: 12px; color: #666;">Grand Total</div>
                            <div style="font-size: 24px; font-weight: bold; color: #4CAF50;">₹${totals.grandTotal.toFixed(2)}</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cash Amount</label>
                            <input type="number" class="form-control" id="cashAmountModal" value="${totals.grandTotal}" min="0" step="0.01" oninput="updateModalPaymentSummary()">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">UPI Amount</label>
                            <input type="number" class="form-control" id="upiAmountModal" value="0" min="0" step="0.01" oninput="updateModalPaymentSummary()">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Bank Amount</label>
                            <input type="number" class="form-control" id="bankAmountModal" value="0" min="0" step="0.01" oninput="updateModalPaymentSummary()">
                        </div>
                        
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Total Paid:</span>
                                <span id="paymentTotalPaid" style="font-weight: bold;">₹0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Pending:</span>
                                <span id="paymentPending" style="font-weight: bold; color: #f44336;">₹${totals.grandTotal.toFixed(2)}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Change Due:</span>
                                <span id="paymentChange" style="font-weight: bold; color: #4CAF50;">₹0.00</span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button class="btn btn-secondary" onclick="closeModal('payment')" style="flex: 1;">Cancel</button>
                            <button class="btn btn-warning" onclick="autoFillPayment()" style="flex: 1;">Auto Fill</button>
                            <button class="btn btn-success" id="completePaymentBtn" onclick="completePayment()" style="flex: 1;" disabled>Complete Payment</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('payment-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Update payment summary
        updateModalPaymentSummary();
        
    } catch (error) {
        console.error('Error showing payment modal:', error);
        showToast('Error opening payment screen', 'error');
    }
}

function updateModalPaymentSummary() {
    try {
        const cashAmount = parseFloat(document.getElementById('cashAmountModal').value) || 0;
        const upiAmount = parseFloat(document.getElementById('upiAmountModal').value) || 0;
        const bankAmount = parseFloat(document.getElementById('bankAmountModal').value) || 0;
        
        const totalPaid = cashAmount + upiAmount + bankAmount;
        const totals = calculateTotals();
        const pending = Math.max(0, totals.grandTotal - totalPaid);
        const change = Math.max(0, totalPaid - totals.grandTotal);
        
        // Update display
        document.getElementById('paymentTotalPaid').textContent = totalPaid.toFixed(2);
        document.getElementById('paymentPending').textContent = pending.toFixed(2);
        document.getElementById('paymentChange').textContent = change.toFixed(2);
        
        // Enable/disable complete payment button
        const completeBtn = document.getElementById('completePaymentBtn');
        if (completeBtn) {
            if (pending === 0 && totalPaid > 0) {
                completeBtn.disabled = false;
            } else {
                completeBtn.disabled = true;
            }
        }
        
    } catch (error) {
        console.error('Error updating modal payment summary:', error);
    }
}

function autoFillPayment() {
    try {
        const totals = calculateTotals();
        const cashAmount = parseFloat(document.getElementById('cashAmountModal').value) || 0;
        const upiAmount = parseFloat(document.getElementById('upiAmountModal').value) || 0;
        const bankAmount = parseFloat(document.getElementById('bankAmountModal').value) || 0;
        
        const totalPaid = cashAmount + upiAmount + bankAmount;
        const remaining = totals.grandTotal - totalPaid;
        
        if (remaining <= 0) {
            showToast('Payment already complete', 'info');
            return;
        }
        
        // Fill in cash amount
        document.getElementById('cashAmountModal').value = (cashAmount + remaining).toFixed(2);
        updateModalPaymentSummary();
        
        showToast(`Added ₹${remaining.toFixed(2)} to cash payment`, 'info');
        
    } catch (error) {
        console.error('Error auto-filling payment:', error);
        showToast('Error auto-filling payment', 'error');
    }
}

function completePayment() {
    try {
        // Transfer payment amounts from modal to main form
        const cashAmount = parseFloat(document.getElementById('cashAmountModal').value) || 0;
        const upiAmount = parseFloat(document.getElementById('upiAmountModal').value) || 0;
        const bankAmount = parseFloat(document.getElementById('bankAmountModal').value) || 0;
        
        // Update main payment form
        document.getElementById('cash-amount').value = cashAmount;
        document.getElementById('upi-amount').value = upiAmount;
        document.getElementById('bank-amount').value = bankAmount;
        
        // Update active payment methods
        ACTIVE_PAYMENT_METHODS.clear();
        if (cashAmount > 0) ACTIVE_PAYMENT_METHODS.add('cash');
        if (upiAmount > 0) ACTIVE_PAYMENT_METHODS.add('upi');
        if (bankAmount > 0) ACTIVE_PAYMENT_METHODS.add('bank');
        
        // Update checkboxes
        document.querySelectorAll('input[name="payment-method"]').forEach(checkbox => {
            checkbox.checked = ACTIVE_PAYMENT_METHODS.has(checkbox.value);
            // Show/hide payment input cards
            const cardId = `${checkbox.value}-input-card`;
            const cardElement = document.getElementById(cardId);
            if (cardElement) {
                cardElement.style.display = checkbox.checked ? 'block' : 'none';
            }
        });
        
        // Update payment summary
        updatePaymentSummary();
        
        // Close modal
        closeModal('payment');
        
        showToast('Payment completed successfully', 'success');
        
    } catch (error) {
        console.error('Error completing payment:', error);
        showToast('Error completing payment', 'error');
    }
}

function updatePaymentSummary() {
    try {
        const totals = calculateTotals();
        const grandTotal = totals.grandTotal;
        
        // Get payment amounts
        const cashAmount = ACTIVE_PAYMENT_METHODS.has('cash') ? parseFloat(document.getElementById('cash-amount').value) || 0 : 0;
        const upiAmount = ACTIVE_PAYMENT_METHODS.has('upi') ? parseFloat(document.getElementById('upi-amount').value) || 0 : 0;
        const bankAmount = ACTIVE_PAYMENT_METHODS.has('bank') ? parseFloat(document.getElementById('bank-amount').value) || 0 : 0;
        const chequeAmount = ACTIVE_PAYMENT_METHODS.has('cheque') ? parseFloat(document.getElementById('cheque-amount').value) || 0 : 0;
        
        const totalPaid = cashAmount + upiAmount + bankAmount + chequeAmount;
        const changeGiven = totalPaid > grandTotal ? totalPaid - grandTotal : 0;
        const pendingAmount = totalPaid < grandTotal ? grandTotal - totalPaid : 0;
        
        // Update displays
        document.getElementById('total-paid').textContent = `₹${totalPaid.toFixed(2)}`;
        document.getElementById('change-given').textContent = `₹${changeGiven.toFixed(2)}`;
        document.getElementById('pending-amount').textContent = `₹${pendingAmount.toFixed(2)}`;
        
        // Update generate bill button state
        updateGenerateBillButton(pendingAmount, totalPaid);
        
    } catch (error) {
        console.error('Error updating payment summary:', error);
        showToast('Error updating payment summary. Please check amounts.', 'error');
    }
}

function updateGenerateBillButton(pendingAmount, totalPaid) {
    try {
        const generateBillBtn = document.getElementById('btnGenerateBill');
        if (!generateBillBtn) return;
        
        if (pendingAmount === 0 && totalPaid > 0) {
            generateBillBtn.disabled = false;
            generateBillBtn.title = 'Click to generate and save bill';
            generateBillBtn.classList.remove('btn-secondary');
            generateBillBtn.classList.add('btn-primary');
        } else if (pendingAmount > 0) {
            generateBillBtn.disabled = true;
            generateBillBtn.title = `Cannot generate bill. Pending amount: ₹${pendingAmount.toFixed(2)}`;
            generateBillBtn.classList.remove('btn-primary');
            generateBillBtn.classList.add('btn-secondary');
        } else {
            generateBillBtn.disabled = true;
            generateBillBtn.title = 'Please enter payment amounts';
            generateBillBtn.classList.remove('btn-primary');
            generateBillBtn.classList.add('btn-secondary');
        }
    } catch (error) {
        console.error('Error updating generate bill button:', error);
    }
}

// ==================== REFERRAL MODAL FUNCTIONS ====================
function showReferralModal() {
    try {
        const modalBody = document.getElementById('referral-modal-body');
        
        // Simple referral modal for now
        modalBody.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <p>Referral feature is not yet implemented.</p>
                <p class="text-muted">This feature will be available in a future update.</p>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeModal('referral')">Close</button>
            </div>
        `;
        
        document.getElementById('referral-modal').style.display = 'block';
        
    } catch (error) {
        console.error('Error showing referral modal:', error);
        showToast('Error loading referral modal', 'error');
    }
}

// ==================== LOYALTY MODAL FUNCTIONS ====================
function showLoyaltyModal() {
    try {
        const modalBody = document.getElementById('loyalty-modal-body');
        
        // Simple loyalty modal for now
        modalBody.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <p>Loyalty points feature is not yet implemented.</p>
                <p class="text-muted">This feature will be available in a future update.</p>
            </div>
            <div class="modal-footer" style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeModal('loyalty')">Close</button>
            </div>
        `;
        
        document.getElementById('loyalty-modal').style.display = 'block';
        
    } catch (error) {
        console.error('Error showing loyalty modal:', error);
        showToast('Error loading loyalty modal', 'error');
    }
}

// ==================== PRODUCT CATEGORY FUNCTIONS ====================
function updateCategoryDisplay() {
    try {
        const categoryTitle = CURRENT_CATEGORY === 0 ? 'All Products' : 
            `Category ${CURRENT_CATEGORY}`;
        
        document.getElementById('current-category').textContent = categoryTitle;
        filterProducts();
        
    } catch (error) {
        console.error('Error updating category display:', error);
    }
}

function filterProducts() {
    try {
        const searchTerm = document.getElementById('search-box').value.toLowerCase();
        const productGrid = document.getElementById('product-grid');
        
        if (!productGrid) return;
        
        // Clear existing products
        productGrid.innerHTML = '';
        
        // Filter products
        const filteredProducts = PRODUCTS.filter(product => {
            const inCategory = CURRENT_CATEGORY === 0 || 
                             product.category_id == CURRENT_CATEGORY;
            
            const matchesSearch = !searchTerm || 
                                product.name.toLowerCase().includes(searchTerm) ||
                                (product.code && product.code.toLowerCase().includes(searchTerm)) ||
                                (product.barcode && product.barcode.toLowerCase().includes(searchTerm));
            
            return inCategory && matchesSearch;
        });
        
        // Display products
        filteredProducts.forEach(product => {
            const productCard = createProductCard(product);
            productGrid.appendChild(productCard);
        });
        
    } catch (error) {
        console.error('Error filtering products:', error);
    }
}

function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.dataset.productId = product.id;
    
    const price = GLOBAL_PRICE_TYPE === 'wholesale' ? 
        parseFloat(product.wholesale_price) : parseFloat(product.retail_price);
    
    const stockBadge = product.shop_stock > 0 ? 
        `<span class="stock-badge ${product.shop_stock < 10 ? 'low-stock' : 'shop-stock'}">${Math.round(product.shop_stock)}</span>` :
        `<span class="stock-badge out-of-stock">Out</span>`;
    
    card.innerHTML = `
        <div class="product-card-inner" onclick="showProductModal(findProductById(${product.id}))">
            <div class="product-card-header">
                <div class="product-name">${escapeHtml(product.name)}</div>
                ${stockBadge}
            </div>
            <div class="product-card-body">
                <div class="product-code">${escapeHtml(product.code || 'P' + product.id.toString().padStart(6, '0'))}</div>
                <div class="product-price">₹${Math.round(price)}</div>
            </div>
            <div class="product-card-footer">
                <small class="text-muted">${product.unit_of_measure || 'PCS'}</small>
                ${product.secondary_unit ? `<small class="text-muted"> / ${product.secondary_unit}</small>` : ''}
            </div>
        </div>
    `;
    
    // Add touch effects
    card.addEventListener('touchstart', function() {
        this.style.transform = 'scale(0.98)';
        this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
    });
    
    card.addEventListener('touchend', function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '';
    });
    
    card.addEventListener('touchcancel', function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '';
    });
    
    return card;
}

// ==================== UTILITY FUNCTIONS ====================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function closeModal(modalType) {
    try {
        const modal = document.getElementById(`${modalType}-modal`);
        if (modal) {
            modal.style.display = 'none';
        }
        
        // Special case for product quantity modal
        if (modalType === 'product-quantity') {
            const productModal = document.getElementById('product-quantity-modal');
            if (productModal) {
                productModal.remove();
            }
        }
        
        // Special case for payment modal
        if (modalType === 'payment') {
            const paymentModal = document.getElementById('payment-modal');
            if (paymentModal) {
                paymentModal.remove();
            }
        }
    } catch (error) {
        console.error(`Error closing ${modalType} modal:`, error);
    }
}

function showToast(message, type = 'info') {
    try {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            // Create toast container if it doesn't exist
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.position = 'fixed';
            container.style.bottom = '20px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            toastContainer = container;
        }
        
        const toastId = 'toast-' + Date.now();
        const iconMap = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        };
        
        const icon = iconMap[type] || iconMap['info'];
        const toastClass = `toast toast-${type}`;
        
        const toastHTML = `
            <div id="${toastId}" class="${toastClass}">
                <i class="${icon}"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.remove();
            }
        }, 3000);
        
    } catch (error) {
        console.error('Error showing toast:', error);
    }
}

function showConfirmation(title, message, callback) {
    try {
        document.getElementById('confirmationTitle').textContent = title;
        document.getElementById('confirmationMessage').textContent = message;
        PENDING_CONFIRMATION = callback;
        
        document.getElementById('confirmation-modal').style.display = 'block';
        
    } catch (error) {
        console.error('Error showing confirmation modal:', error);
        showToast('Error showing confirmation. Please try again.', 'error');
    }
}

function executePendingConfirmation() {
    try {
        if (PENDING_CONFIRMATION && typeof PENDING_CONFIRMATION === 'function') {
            PENDING_CONFIRMATION();
        }
        
        PENDING_CONFIRMATION = null;
        closeModal('confirmation');
        
    } catch (error) {
        console.error('Error executing confirmation:', error);
        showToast('Error executing action. Please try again.', 'error');
    }
}

function updateButtonStates() {
    try {
        const cartCount = CART.length;
        const hasCartItems = cartCount > 0;
        const totals = calculateTotals();
        const hasGrandTotal = totals.grandTotal > 0;
        
        // Payment button
        const paymentBtn = document.getElementById('paymentBtn');
        if (paymentBtn) {
            paymentBtn.disabled = !hasCartItems;
            paymentBtn.title = hasCartItems ? 'Process payment' : 'Add items to cart first';
        }
        
        // Clear cart button
        const clearBtn = document.getElementById('clearCartBtn');
        if (clearBtn) {
            clearBtn.disabled = !hasCartItems;
            clearBtn.title = hasCartItems ? `Clear ${cartCount} items` : 'Cart is empty';
        }
        
        // Generate bill button
        const generateBtn = document.getElementById('btnGenerateBill');
        if (generateBtn) {
            generateBtn.disabled = !hasGrandTotal;
            generateBtn.title = hasGrandTotal ? 'Generate bill' : 'Process payment first';
        }
        
        // Print button
        const printBtn = document.getElementById('btnPrintBill');
        if (printBtn) {
            printBtn.disabled = !hasGrandTotal;
            printBtn.title = hasGrandTotal ? 'Print bill' : 'Process payment first';
        }
        
    } catch (error) {
        console.error('Error updating button states:', error);
    }
}

async function loadCustomers() {
    console.log('Touch POS: Loading customers...');
    
    try {
        // This would typically fetch from an API
        // For now, we'll just log and return empty
        console.log('Touch POS: Customer loading not implemented yet');
        return true;
        
    } catch (error) {
        console.error('Touch POS: Customer loading failed:', error);
        return false;
    }
}

// ==================== EVENT LISTENERS SETUP ====================
function setupEventListeners() {
    console.log('Touch POS: Setting up event listeners...');
    
    try {
        // Barcode input
        document.getElementById('barcode-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcode = this.value.trim();
                if (barcode) {
                    const product = findProductByBarcode(barcode);
                    if (product) {
                        showProductModal(product);
                    } else {
                        showToast(`Product not found for barcode: ${barcode}`, 'warning');
                    }
                    this.value = '';
                }
            }
        });
        
        // Search box
        document.getElementById('search-box').addEventListener('input', function() {
            filterProducts();
        });
        
        // Invoice type change
        document.getElementById('invoice-type').addEventListener('change', function() {
            GST_TYPE = this.value;
            generateInvoiceNumber();
            updateBillingSummary();
        });
        
        // Price type change
        document.getElementById('price-type').addEventListener('change', function() {
            GLOBAL_PRICE_TYPE = this.value;
            filterProducts(); // Refresh product display with new prices
            updateBillingSummary();
        });
        
        // Overall discount input
        document.getElementById('overall-discount-value').addEventListener('input', function() {
            updateBillingSummary();
        });
        
        document.getElementById('overall-discount-type').addEventListener('change', function() {
            updateBillingSummary();
        });
        
        // Payment method checkboxes
        document.querySelectorAll('input[name="payment-method"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const method = this.value;
                const cardId = `${method}-input-card`;
                const cardElement = document.getElementById(cardId);
                
                if (this.checked) {
                    ACTIVE_PAYMENT_METHODS.add(method);
                    if (cardElement) {
                        cardElement.style.display = 'block';
                    }
                } else {
                    ACTIVE_PAYMENT_METHODS.delete(method);
                    if (cardElement) {
                        cardElement.style.display = 'none';
                        // Clear amount
                        const amountInput = cardElement.querySelector('input[type="number"]');
                        if (amountInput) {
                            amountInput.value = '0';
                        }
                    }
                }
                updatePaymentSummary();
            });
        });
        
        // Payment amount inputs
        document.getElementById('cash-amount').addEventListener('input', updatePaymentSummary);
        document.getElementById('upi-amount').addEventListener('input', updatePaymentSummary);
        document.getElementById('bank-amount').addEventListener('input', updatePaymentSummary);
        document.getElementById('cheque-amount').addEventListener('input', updatePaymentSummary);
        
        // Category buttons
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.category-btn').forEach(b => {
                    b.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                CURRENT_CATEGORY = parseInt(this.dataset.category) || 0;
                updateCategoryDisplay();
            });
        });
        
        // Site select change
        document.getElementById('site-select').addEventListener('change', function() {
            const shopId = this.value;
            const shopName = this.options[this.selectedIndex].text;
            showToast(`Switched to ${shopName}. Please note: Cart will be cleared.`, 'warning');
            
            // In a real implementation, you would reload products for the new shop
            // For now, just clear the cart
            clearCart();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // F1 for help
            if (e.key === 'F1') {
                e.preventDefault();
                showToast('Touch POS: Use barcode scanner or click products to add', 'info');
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
            
            // Ctrl+Enter to generate bill
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                const generateBtn = document.getElementById('btnGenerateBill');
                if (generateBtn && !generateBtn.disabled) {
                    generateBtn.click();
                }
            }
        });
        
        // Form reset warning
        window.addEventListener('beforeunload', function(e) {
            if (CART.length > 0) {
                e.preventDefault();
                e.returnValue = 'You have unsaved items in your cart. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
        
        console.log('Touch POS: Event listeners setup complete');
        
    } catch (error) {
        console.error('Touch POS: Error setting up event listeners:', error);
        showToast('Some controls may not work properly. Please refresh.', 'warning');
    }
}

// ==================== BILL GENERATION ====================
async function generateBill() {
    try {
        console.log('Touch POS: Generating bill...');
        
        // Validate cart
        if (CART.length === 0) {
            showToast('Please add items to cart first', 'warning');
            return;
        }
        
        // Validate customer
        if (!CURRENT_CUSTOMER_NAME) {
            showToast('Please select a customer first', 'warning');
            showCustomerModal();
            return;
        }
        
        // Validate payment
        const totals = calculateTotals();
        const cashAmount = parseFloat(document.getElementById('cash-amount').value) || 0;
        const upiAmount = parseFloat(document.getElementById('upi-amount').value) || 0;
        const bankAmount = parseFloat(document.getElementById('bank-amount').value) || 0;
        const chequeAmount = parseFloat(document.getElementById('cheque-amount').value) || 0;
        
        const totalPaid = cashAmount + upiAmount + bankAmount + chequeAmount;
        
        if (totalPaid === 0) {
            showToast('Please enter payment amounts', 'warning');
            showPaymentModal();
            return;
        }
        
        if (totalPaid < totals.grandTotal) {
            const pending = totals.grandTotal - totalPaid;
            showToast(`Insufficient payment. Pending: ₹${pending.toFixed(2)}`, 'warning');
            return;
        }
        
        // Generate invoice data
        const invoiceNumber = document.getElementById('invoice-display').textContent;
        const invoiceDate = document.getElementById('invoice-date') ? 
            document.getElementById('invoice-date').value : new Date().toISOString().split('T')[0];
        
        const invoiceData = {
            customer_name: CURRENT_CUSTOMER_NAME,
            customer_phone: document.getElementById('customer-phone-display') ? 
                document.getElementById('customer-phone-display').value : '',
            invoice_number: invoiceNumber,
            invoice_type: GST_TYPE,
            date: invoiceDate,
            price_type: GLOBAL_PRICE_TYPE,
            referral_id: SELECTED_REFERRAL_ID,
            points_used: POINTS_USED,
            points_discount: totals.pointsDiscount,
            subtotal: totals.subtotal,
            discount: document.getElementById('overall-discount-value').value,
            discount_type: document.getElementById('overall-discount-type').value,
            overall_discount: totals.overallDiscount,
            total_gst: totals.totalGST,
            grand_total: totals.grandTotal,
            referral_commission: totals.totalReferralCommission,
            items: CART,
            payment_method: Array.from(ACTIVE_PAYMENT_METHODS).join('+'),
            payment_details: {
                cash: cashAmount,
                upi: upiAmount,
                bank: bankAmount,
                cheque: chequeAmount,
                totalPaid: totalPaid
            },
            shop_id: SHOP_ID,
            business_id: BUSINESS_ID,
            user_id: USER_ID
        };
        
        console.log('Invoice data to save:', invoiceData);
        
        // Show loading
        const generateBtn = document.getElementById('btnGenerateBill');
        const originalText = generateBtn.innerHTML;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        generateBtn.disabled = true;
        
        try {
            // In a real implementation, you would send this to your API
            // For now, we'll simulate a successful save
            setTimeout(() => {
                showToast(`Invoice #${invoiceNumber} saved successfully!`, 'success');
                
                // Clear cart and reset form
                resetForm();
                
                // Restore button state
                generateBtn.innerHTML = originalText;
                updateButtonStates();
            }, 1000);
            
        } catch (error) {
            console.error('Invoice save error:', error);
            showToast(`Failed to save invoice: ${error.message}`, 'error');
            
            // Restore button state
            generateBtn.innerHTML = originalText;
            updateButtonStates();
        }
        
    } catch (error) {
        console.error('Error generating bill:', error);
        showToast(`Error generating bill: ${error.message}`, 'error');
    }
}

function printBill() {
    try {
        const totals = calculateTotals();
        if (totals.grandTotal <= 0) {
            showToast('Please generate bill first', 'warning');
            return;
        }
        
        // Create print content
        const invoiceNumber = document.getElementById('invoice-display').textContent;
        const invoiceDate = document.getElementById('invoice-date') ? 
            document.getElementById('invoice-date').value : new Date().toLocaleDateString();
        
        let itemsHTML = '';
        CART.forEach((item, index) => {
            itemsHTML += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(item.name)}</td>
                    <td>${item.quantity} ${item.unit}</td>
                    <td>₹${item.price.toFixed(2)}</td>
                    <td>₹${(item.price * item.quantity).toFixed(2)}</td>
                </tr>
            `;
        });
        
        
        
        // Open print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        showToast('Print window opened', 'info');
        
    } catch (error) {
        console.error('Error printing bill:', error);
        showToast(`Error printing bill: ${error.message}`, 'error');
    }
}

// ==================== FORM RESET ====================
function resetForm() {
    try {
        console.log('Touch POS: Resetting form...');
        
        // Clear cart
        CART = [];
        renderCart();
        
        // Reset customer
        CURRENT_CUSTOMER_NAME = 'Walk-in Customer';
        document.getElementById('customer-display').textContent = 'Walk-in Customer';
        
        // Reset referral
        SELECTED_REFERRAL_ID = null;
        document.getElementById('referral-display').textContent = 'None';
        
        // Reset loyalty points
        LOYALTY_POINTS_DISCOUNT = 0;
        POINTS_USED = 0;
        
        // Reset payment
        ACTIVE_PAYMENT_METHODS = new Set(['cash']);
        document.querySelectorAll('input[name="payment-method"]').forEach(checkbox => {
            checkbox.checked = checkbox.value === 'cash';
        });
        
        document.getElementById('cash-amount').value = '0';
        document.getElementById('upi-amount').value = '0';
        document.getElementById('bank-amount').value = '0';
        document.getElementById('cheque-amount').value = '0';
        
        // Show/hide payment input cards
        document.getElementById('cash-input-card').style.display = 'block';
        document.getElementById('upi-input-card').style.display = 'none';
        document.getElementById('bank-input-card').style.display = 'none';
        document.getElementById('cheque-input-card').style.display = 'none';
        
        // Reset discount
        document.getElementById('overall-discount-value').value = '0';
        document.getElementById('overall-discount-type').value = 'percentage';
        
        // Reset category
        CURRENT_CATEGORY = 0;
        updateCategoryDisplay();
        
        // Clear search
        document.getElementById('search-box').value = '';
        
        // Generate new invoice number
        generateInvoiceNumber();
        
        // Update UI
        updateBillingSummary();
        updateButtonStates();
        
        // Focus on search
        setTimeout(() => {
            document.getElementById('search-box').focus();
        }, 500);
        
        showToast('Form reset. Ready for next sale!', 'success');
        
    } catch (error) {
        console.error('Error resetting form:', error);
        showToast('Error resetting form. Please refresh page.', 'error');
    }
}

// ==================== PLACEHOLDER FUNCTIONS ====================
function applyPriceToAll() {
    showToast('This feature is not yet implemented', 'info');
}

function showQuotationModal() {
    showToast('Quotation feature is not yet implemented', 'info');
}

function showHoldModal() {
    showToast('Hold feature is not yet implemented', 'info');
}

function showProfitModal() {
    showToast('Profit analysis feature is not yet implemented', 'info');
}

function showQuotationListModal() {
    showToast('Quotation list feature is not yet implemented', 'info');
}

function showHoldListModal() {
    showToast('Hold list feature is not yet implemented', 'info');
}

function showProductDetails(productId) {
    const product = findProductById(productId);
    if (product) {
        showToast(`Details for: ${product.name}`, 'info');
    }
}

// ==================== EXPOSE FUNCTIONS TO GLOBAL SCOPE ====================
window.updateCartItemQuantity = updateCartItemQuantity;
window.updateCartItemDiscount = updateCartItemDiscount;
window.updateCartItemDiscountType = updateCartItemDiscountType;
window.removeCartItem = removeCartItem;
window.findProductById = findProductById;
window.showProductModal = showProductModal;
window.showCustomerModal = showCustomerModal;
window.showReferralModal = showReferralModal;
window.showLoyaltyModal = showLoyaltyModal;
window.showPaymentModal = showPaymentModal;
window.toggleUnitConversion = toggleUnitConversion;
window.closeModal = closeModal;
window.saveCustomerDetails = saveCustomerDetails;
window.autoFillPayment = autoFillPayment;
window.completePayment = completePayment;
window.updateModalPaymentSummary = updateModalPaymentSummary;
window.generateBill = generateBill;
window.printBill = printBill;
window.clearCart = clearCart;
window.resetForm = resetForm;
window.executePendingConfirmation = executePendingConfirmation;

// Initial filter of products
filterProducts();
console.log('Touch POS: Script loaded successfully');
</script>