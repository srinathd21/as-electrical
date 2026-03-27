<!-- Secondary Unit Conversion Modal -->
    <div class="modal fade" id="secondaryUnitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0">Convert to Secondary Unit</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="mb-2">
                        <p class="mb-1" style="font-size: 0.7rem;">Convert <strong id="convertProductName"></strong> from primary unit to secondary unit</p>
                        <div class="secondary-unit-info mb-2">
                            <small class="text-muted" id="conversionRateInfo"></small>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1" style="font-size: 0.7rem;">Quantity in Secondary Unit (<span id="secondaryUnitLabel"></span>)</label>
                        <input type="number" id="secondaryUnitQty" class="form-control form-control-sm" 
                               step="0.01" min="0.01" value="1">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1" style="font-size: 0.7rem;">Price Calculation</label>
                        <div class="alert alert-light p-1 mb-0">
                            <small>
                                Base Price: ₹<span id="basePrice"></span><br>
                                Extra Charge: <span id="extraChargeInfo"></span><br>
                                Total Price: <strong>₹<span id="calculatedPrice"></span></strong>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="confirmSecondaryUnit">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loyalty Points Details Modal -->
    <div class="modal fade points-details-modal" id="pointsDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0">Loyalty Points</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="loyalty-compact mb-2">
                        <div class="flex-grow-1">
                            <div class="points-label">AVAILABLE POINTS</div>
                            <div class="points-value" id="modalPointsValue">0</div>
                        </div>
                        <button class="btn-apply-points" id="btnShowPointsDetailsModal">
                            <i class="fas fa-star me-1"></i> Apply
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm points-summary-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Points</th>
                                    <th class="text-end">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total Points Earned</td>
                                    <td class="text-end" id="modalTotalEarned">0</td>
                                    <td class="text-end">-</td>
                                </tr>
                                <tr>
                                    <td>Total Points Redeemed</td>
                                    <td class="text-end" id="modalTotalRedeemed">0</td>
                                    <td class="text-end">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Available Points</strong></td>
                                    <td class="text-end"><strong id="modalAvailablePoints">0</strong></td>
                                    <td class="text-end"><strong>₹<span id="modalPointsCashValue">0.00</span></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mb-2" id="pointsRedeemSection" style="display: none;">
                        <label class="form-label mb-1" style="font-size: 0.7rem;">Points to Redeem</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="pointsToRedeem" class="form-control" 
                                   value="0" min="0" step="1">
                            <button class="btn btn-outline-primary" type="button" id="btnUseMaxPoints">
                                Max
                            </button>
                        </div>
                        <small class="text-muted" style="font-size: 0.65rem;">Each point = ₹<?= $loyalty_settings['redeem_value_per_point'] ?> discount</small>
                        <div class="mt-1">
                            <small><strong>Discount:</strong> ₹<span id="modalPointsDiscount">0.00</span></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnApplyPointsDiscount" style="display: none;">Apply Discount</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0" id="confirmationTitle"></h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <p id="confirmationMessage" class="mb-0" style="font-size: 0.8rem;"></p>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotation Modal -->
    <div class="modal fade" id="quotationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0">Save Quotation</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="mb-2">
                        <label class="form-label mb-1">Quotation #</label>
                        <input type="text" id="quotationNumber" class="form-control form-control-sm"
                            value="<?= $quotation_number ?>" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Valid Until</label>
                        <input type="date" id="quotationValidUntil" class="form-control form-control-sm"
                            value="<?= date('Y-m-d', strtotime('+15 days')) ?>" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Notes (Optional)</label>
                        <textarea id="quotationNotes" class="form-control form-control-sm" rows="2"
                            placeholder="Add notes..."></textarea>
                    </div>
                    <div class="alert alert-info p-1 mb-0">
                        <small><i class="fas fa-info-circle me-1"></i> Quotation can be converted to invoice
                            later</small>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="saveQuotationBtn">Save Quotation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotation List Modal -->
    <div class="modal fade" id="quotationListModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0">Quotations</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2 modal-body-scrollable">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm" id="quotationListTable">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%">Date</th>
                                    <th width="15%">Quotation #</th>
                                    <th width="20%">Customer</th>
                                    <th width="10%">Items</th>
                                    <th width="10%">Amount</th>
                                    <th width="10%">Status</th>
                                    <th width="15%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="quotationListBody">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center py-3" id="emptyQuotations" style="display: none;">
                        <i class="fas fa-file-alt fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No quotations found</p>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hold Invoice Modal -->
    <div class="modal fade hold-invoice-modal" id="holdInvoiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0">Hold Invoice</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="mb-2">
                        <label class="form-label mb-1">Reference Note (Optional)</label>
                        <input type="text" id="holdReference" class="form-control form-control-sm"
                            placeholder="e.g., Customer name, phone, or reason">
                        <small class="text-muted">Max 100 characters</small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Expires After</label>
                        <select id="holdExpiry" class="form-select form-select-sm">
                            <option value="24">24 hours</option>
                            <option value="48" selected>48 hours</option>
                            <option value="72">72 hours</option>
                            <option value="168">7 days</option>
                            <option value="720">30 days</option>
                        </select>
                        <small class="text-muted">Held invoices will be auto-deleted after expiry</small>
                    </div>
                    <div class="alert alert-info p-1">
                        <small><i class="fas fa-info-circle me-1"></i>
                        Cart items will be reserved. You can restore this invoice later from the Hold List.</small>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="confirmHold">Save Hold</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hold List Modal -->
    <div class="modal fade hold-list-modal" id="holdListModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0">Held Invoices</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2 modal-body-scrollable">
                    <div class="table-responsive">
                        <table class="table table-hover hold-list-table">
                            <thead>
                                <tr>
                                    <th width="10%">#</th>
                                    <th width="25%">Time</th>
                                    <th width="30%">Reference</th>
                                    <th width="15%">Items</th>
                                    <th width="15%">Amount</th>
                                    <th width="15%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="holdListBody">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center" id="emptyHoldList" style="display: none;">
                        <div class="py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted">No held invoices</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <!-- Update the product details modal to include new fields -->
<div class="modal fade product-details-modal" id="productDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Product Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body modal-body-scrollable">
                <div class="product-details-section">
                    <h6>Basic Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <span id="modalProductName"></span></p>
                            <p><strong>Code:</strong> <span id="modalProductCode"></span></p>
                            <p><strong>HSN Code:</strong> <span id="modalProductHSN"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Category:</strong> <span id="modalCategory"></span></p>
                            <p><strong>Subcategory:</strong> <span id="modalSubcategory"></span></p>
                            <p><strong>Primary Unit:</strong> <span id="modalPrimaryUnit"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="product-details-section">
                    <h6>Pricing Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>MRP:</strong> <span id="modalMRP"></span></p>
                            <p><strong>Retail Price:</strong> <span id="modalRetailPrice"></span></p>
                            <p><strong>Retail Discount from MRP:</strong> <span id="modalRetailDiscountMRP"></span></p>
                            <p><strong>Wholesale Price:</strong> <span id="modalWholesalePrice"></span></p>
                            <p><strong>Wholesale Discount from MRP:</strong> <span id="modalWholesaleDiscountMRP"></span></p>
                            <p><strong>Cost Price:</strong> <span id="modalCostPrice"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Discount Type:</strong> <span id="modalDiscountType"></span></p>
                            <p><strong>Discount Value:</strong> <span id="modalDiscountValue"></span></p>
                            <p><strong>Retail Price Type:</strong> <span id="modalRetailPriceType"></span></p>
                            <p><strong>Retail Price Value:</strong> <span id="modalRetailPriceValue"></span></p>
                            <p><strong>Wholesale Price Type:</strong> <span id="modalWholesalePriceType"></span></p>
                            <p><strong>Wholesale Price Value:</strong> <span id="modalWholesalePriceValue"></span></p>
                        </div>
                    </div>
                </div>
                
                <!-- Keep existing sections for GST, Stock, Secondary Unit, Referral -->
                <div class="product-details-section">
                    <h6>Stock Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <span id="modalStockStatus"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Shop Stock:</strong> <span id="modalShopStock"></span></p>
                            <p><strong>Warehouse Stock:</strong> <span id="modalWarehouseStock"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="product-details-section" id="modalGSTSection">
                    <h6>GST Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Total GST Rate:</strong> <span id="modalGSTRate"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>CGST:</strong> <span id="modalCGST"></span></p>
                            <p><strong>SGST:</strong> <span id="modalSGST"></span></p>
                            <p><strong>IGST:</strong> <span id="modalIGST"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="product-details-section" id="modalSecondaryUnitSection" style="display: none;">
                    <h6>Secondary Unit Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Secondary Unit:</strong> <span id="modalSecondaryUnit"></span></p>
                            <p><strong>Conversion Rate:</strong> <span id="modalConversionRate"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Extra Charge Type:</strong> <span id="modalExtraChargeType"></span></p>
                            <p><strong>Extra Charge:</strong> <span id="modalExtraCharge"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="product-details-section" id="modalReferralSection" style="display: none;">
                    <h6>Referral Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Referral Enabled:</strong> <span id="modalReferralEnabled"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Referral Type:</strong> <span id="modalReferralType"></span></p>
                            <p><strong>Referral Value:</strong> <span id="modalReferralValue"></span></p>
                            <p><strong>Commission per Unit:</strong> <span id="modalCommissionPerUnit"></span></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <!-- Profit Analysis Modal -->
    <div class="modal fade profit-analysis-modal" id="profitAnalysisModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header p-2">
                    <h6 class="modal-title mb-0">Profit Analysis</h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2 modal-body-scrollable">
                    <div id="profitAnalysisContent">
                        <!-- Profit breakdown will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>