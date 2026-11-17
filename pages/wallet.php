                    <!-- Wallet Tab -->
                    <div id="tab-wallet" class="tab-pane <?= $open_tab === 'wallet' ? 'active' : '' ?>">
                        <div class="d-sm-flex align-items-center justify-content-between flex-wrap mb-4" style="gap: 16px;">
                            <div>
                                <h1 class="h3 mb-0 text-gray-800 font-weight-bold">My Wallet & Financial Overview</h1>
                                <small class="text-muted">Monitor earnings, escrow clearance from ongoing jobs, and bank payout requests.</small>
                            </div>
                            <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                                <button type="button" class="btn btn-emerald btn-sm font-weight-bold px-3 py-2 shadow-sm" onclick="openPayoutModal()" style="border-radius: 8px;">
                                    <i class="fas fa-hand-holding-usd mr-2"></i> Withdraw Funds
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold px-3 py-2 shadow-sm wallet-top-btn" onclick="openBankDetailsModal('bank')" style="border-radius: 8px;">
                                    <i class="fas fa-university mr-2"></i> Bank Account
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold px-3 py-2 shadow-sm wallet-top-btn" onclick="openBankDetailsModal('upi')" style="border-radius: 8px;">
                                    <i class="fas fa-qrcode mr-2"></i> UPI ID
                                </button>
                            </div>
                        </div>

                        <!-- Wallet Metric KPI Cards Grid -->
                        <div class="deskboard-stats-grid mb-4">
                            <!-- Card 1: Available Balance -->
                            <div class="stat-card accent-teal">
                                <div class="stat-card-main">
                                    <div class="stat-meta">
                                        <span class="stat-title">Available Balance</span>
                                        <span class="stat-val">₹<span id="wallet-available">0.00</span></span>
                                    </div>
                                    <div class="stat-icon-badge teal">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                </div>
                                <div class="stat-subtitle text-muted small d-flex align-items-center justify-content-between">
                                    <span><i class="fas fa-check-circle mr-1 text-success"></i> Ready for Payout</span>
                                    <a href="#" onclick="openPayoutModal(); return false;" class="text-success font-weight-bold">Withdraw &rarr;</a>
                                </div>
                            </div>

                            <!-- Card 2: Escrow / Pending Clearance -->
                            <div class="stat-card accent-purple">
                                <div class="stat-card-main">
                                    <div class="stat-meta">
                                        <span class="stat-title">In Escrow (Pending)</span>
                                        <span class="stat-val">₹<span id="wallet-pending">0.00</span></span>
                                    </div>
                                    <div class="stat-icon-badge purple">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                </div>
                                <div class="stat-subtitle text-muted small">
                                    <i class="fas fa-lock mr-1 text-primary"></i> Unlocked when orders complete
                                </div>
                            </div>

                            <!-- Card 3: Total Net Earned -->
                            <div class="stat-card accent-indigo">
                                <div class="stat-card-main">
                                    <div class="stat-meta">
                                        <span class="stat-title">Total Net Earned</span>
                                        <span class="stat-val">₹<span id="wallet-earned">0.00</span></span>
                                    </div>
                                    <div class="stat-icon-badge indigo">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                </div>
                                <div class="stat-subtitle text-muted small">
                                    <i class="fas fa-percentage mr-1 text-info"></i> <span id="wallet-fee-note">After <?= htmlspecialchars(isset($platformFeePercent) ? (is_numeric($platformFeePercent) && floor($platformFeePercent) == $platformFeePercent ? (int)$platformFeePercent : $platformFeePercent) . '%' : '10%') ?> Platform Fee</span>
                                </div>
                            </div>

                            <!-- Card 4: Total Withdrawn -->
                            <div class="stat-card accent-sky">
                                <div class="stat-card-main">
                                    <div class="stat-meta">
                                        <span class="stat-title">Total Withdrawn</span>
                                        <span class="stat-val">₹<span id="wallet-withdrawn">0.00</span></span>
                                    </div>
                                    <div class="stat-icon-badge sky">
                                        <i class="fas fa-money-check-alt"></i>
                                    </div>
                                </div>
                                <div class="stat-subtitle text-muted small">
                                    <i class="fas fa-history mr-1 text-primary"></i> Disbursed to Bank/UPI
                                </div>
                            </div>
                        </div>

                        <!-- Linked Settlement Account Strip (Separate Bank & UPI Cards) -->
                        <div class="row mb-4">
                            <!-- Settlement Card 1: Bank Account -->
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <div class="card shadow-sm border-0 h-100 settlement-card-bank">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 44px; height: 44px; font-size: 1.25rem;">
                                                        <i class="fas fa-university"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="font-weight-bold text-gray-800 mb-0" id="wallet-bank-name">No Bank Account Linked</h6>
                                                        <span class="text-muted small">Direct NEFT / IMPS Bank Transfer</span>
                                                    </div>
                                                </div>
                                                <span class="badge badge-secondary px-2 py-1 rounded-pill" id="wallet-bank-badge" style="font-size: 0.7rem;">Not Configured</span>
                                            </div>
                                            <div class="text-muted small mt-3 mb-3 p-2 rounded" id="wallet-bank-details" style="min-height: 42px; display: flex; align-items: center;">
                                                Please link your bank account details for direct bank transfers.
                                            </div>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-emerald font-weight-bold px-3 py-2 w-100" onclick="openBankDetailsModal('bank')" style="border-radius: 8px; border-color: #05C279; color: #05C279;">
                                                <i class="fas fa-university mr-1"></i> <span id="btnEditBankText">Link Bank Account</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Settlement Card 2: UPI ID -->
                            <div class="col-lg-6">
                                <div class="card shadow-sm border-0 h-100 settlement-card-upi">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 44px; height: 44px; font-size: 1.25rem;">
                                                        <i class="fas fa-qrcode"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="font-weight-bold text-gray-800 mb-0" id="wallet-upi-title">No UPI ID Linked</h6>
                                                        <span class="text-muted small">Instant Payout (GPay / PhonePe / Paytm)</span>
                                                    </div>
                                                </div>
                                                <span class="badge badge-secondary px-2 py-1 rounded-pill" id="wallet-upi-badge" style="font-size: 0.7rem;">Not Configured</span>
                                            </div>
                                            <div class="text-muted small mt-3 mb-3 p-2 rounded" id="wallet-upi-details" style="min-height: 42px; display: flex; align-items: center;">
                                                Please link your UPI ID for instant 24/7 payout transfers.
                                            </div>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold px-3 py-2 w-100" onclick="openBankDetailsModal('upi')" style="border-radius: 8px;">
                                                <i class="fas fa-qrcode mr-1"></i> <span id="btnEditUpiText">Link UPI ID</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transactions & Payouts Card -->
                        <div class="deskboard-card mb-4">
                            <div class="deskboard-card-header d-flex align-items-center justify-content-between flex-wrap" style="gap: 12px; padding: 20px 24px;">
                                <div class="d-flex align-items-center" style="gap: 12px;">
                                    <h3 class="card-title mb-0" style="font-size: 1.15rem; font-weight: 700; color: #1a202c;">Transaction History</h3>
                                    <div class="btn-group btn-group-sm" role="group" style="border-radius: 8px; overflow: hidden; border: 1px solid #edf2f7;">
                                        <button type="button" class="btn btn-light active font-weight-bold" id="btnShowTxns" onclick="switchWalletView('txns')">
                                            <i class="fas fa-receipt mr-1"></i> Customer Payments (<span id="count-txns">0</span>)
                                        </button>
                                        <button type="button" class="btn btn-light font-weight-bold" id="btnShowPayouts" onclick="switchWalletView('payouts')">
                                            <i class="fas fa-paper-plane mr-1"></i> Payout Requests (<span id="count-payouts">0</span>)
                                        </button>
                                    </div>
                                </div>
                                <div class="header-controls d-flex align-items-center" style="gap: 10px;">
                                    <div class="input-group input-group-sm" style="width: 240px;">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text border-right-0" style="border-radius: 8px 0 0 8px;"><i class="fas fa-search text-muted"></i></span>
                                        </div>
                                        <input type="text" id="walletSearchInput" class="form-control border-left-0" placeholder="Search ref, customer..." style="border-radius: 0 8px 8px 0;" onkeyup="filterWalletTable()">
                                    </div>
                                    <button class="btn btn-sm btn-light border" onclick="loadWallet()" title="Refresh Wallet Data" style="border-radius: 8px;">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Table Container 1: Customer Payments & Transactions -->
                            <div id="walletTxnsContainer" class="table-responsive p-0">
                                <table class="table green-table mb-0" id="walletTxnsTable">
                                    <thead>
                                        <tr>
                                            <th>Transaction Ref</th>
                                            <th>Booking / Service</th>
                                            <th>Customer</th>
                                            <th>Gross Amount</th>
                                            <th id="th-wallet-platform-fee">Platform Fee (<?= htmlspecialchars(isset($platformFeePercent) ? (is_numeric($platformFeePercent) && floor($platformFeePercent) == $platformFeePercent ? (int)$platformFeePercent : $platformFeePercent) . '%' : '10%') ?>)</th>
                                            <th>Your Net</th>
                                            <th>Payment Method</th>
                                            <th>Status</th>
                                            <th>Date & Time</th>
                                        </tr>
                                    </thead>
                                    <tbody id="walletTxnsList">
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin mr-2"></i> Loading transactions...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Table Container 2: Payout Requests -->
                            <div id="walletPayoutsContainer" class="table-responsive p-0" style="display: none;">
                                <table class="table green-table mb-0" id="walletPayoutsTable">
                                    <thead>
                                        <tr>
                                            <th>Payout Reference</th>
                                            <th>Requested Amount</th>
                                            <th>Status</th>
                                            <th>UTR / Bank Reference</th>
                                            <th>Notes / Remarks</th>
                                            <th>Requested Date</th>
                                            <th>Processed Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="walletPayoutsList">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin mr-2"></i> Loading payout requests...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

