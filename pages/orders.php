                    <!-- Orders Tab -->
                    <div id="tab-orders" class="tab-pane <?= $open_tab === 'orders' ? 'active' : '' ?>">
                        <style>
                            .booking-filter-bar {
                                display: flex;
                                flex-wrap: wrap;
                                align-items: center;
                                gap: 10px;
                                margin-bottom: 22px;
                            }
                            .booking-filter-btn {
                                display: inline-flex;
                                align-items: center;
                                gap: 7px;
                                padding: 7px 16px;
                                border-radius: 30px;
                                font-size: 0.82rem;
                                font-weight: 600;
                                cursor: pointer;
                                border: 1px solid rgba(255, 255, 255, 0.12);
                                background: rgba(255, 255, 255, 0.05);
                                color: #94a3b8;
                                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                                outline: none !important;
                                user-select: none;
                            }
                            .booking-filter-btn:hover {
                                background: rgba(255, 255, 255, 0.1);
                                color: #f8fafc;
                                border-color: rgba(255, 255, 255, 0.22);
                                transform: translateY(-1px);
                            }
                            .booking-filter-btn.active {
                                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
                                color: #ffffff !important;
                                border-color: #60a5fa !important;
                                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
                            }
                            .booking-filter-btn.active.filter-completed {
                                background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
                                border-color: #34d399 !important;
                                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
                            }
                            .booking-filter-btn .filter-count {
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                min-width: 20px;
                                height: 20px;
                                padding: 0 6px;
                                font-size: 0.72rem;
                                font-weight: 700;
                                border-radius: 10px;
                                background: rgba(0, 0, 0, 0.25);
                                color: inherit;
                            }
                            .booking-filter-btn.active .filter-count {
                                background: rgba(255, 255, 255, 0.28);
                                color: #ffffff;
                            }
                            /* Light Mode Compatibility */
                            body:not([data-theme="dark"]) .booking-filter-btn {
                                border-color: #e2e8f0;
                                background: #ffffff;
                                color: #64748b;
                                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                            }
                            body:not([data-theme="dark"]) .booking-filter-btn:hover {
                                background: #f8fafc;
                                color: #1e293b;
                                border-color: #cbd5e1;
                            }
                            body:not([data-theme="dark"]) .booking-filter-btn.active {
                                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
                                color: #ffffff !important;
                                border-color: #2563eb !important;
                            }
                            body:not([data-theme="dark"]) .booking-filter-btn.active.filter-completed {
                                background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
                                color: #ffffff !important;
                                border-color: #10b981 !important;
                            }
                            body:not([data-theme="dark"]) .booking-filter-btn .filter-count {
                                background: #f1f5f9;
                                color: #475569;
                            }
                            body:not([data-theme="dark"]) .booking-filter-btn.active .filter-count {
                                background: rgba(255, 255, 255, 0.3);
                                color: #ffffff;
                            }
                        </style>

                        <div class="d-sm-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h1 class="h3 mb-1 text-gray-800 font-weight-bold">My Bookings</h1>
                                <p class="text-muted small mb-0">Track live bookings and access all archived completed or past orders.</p>
                            </div>
                        </div>

                        <!-- Status Filter Capsule Pills -->
                        <div class="booking-filter-bar">
                            <button type="button" class="booking-filter-btn active" data-filter="all" onclick="filterOrdersByStatus('all', this)">
                                <i class="fas fa-layer-group"></i> All Bookings
                                <span class="filter-count" id="count-all">0</span>
                            </button>
                            <button type="button" class="booking-filter-btn" data-filter="in_progress" onclick="filterOrdersByStatus('in_progress', this)">
                                <i class="fas fa-spinner fa-spin text-primary"></i> In Progress
                                <span class="filter-count" id="count-in_progress">0</span>
                            </button>
                            <button type="button" class="booking-filter-btn" data-filter="pending" onclick="filterOrdersByStatus('pending', this)">
                                <i class="fas fa-clock text-warning"></i> Pending
                                <span class="filter-count" id="count-pending">0</span>
                            </button>
                            <button type="button" class="booking-filter-btn" data-filter="accepted" onclick="filterOrdersByStatus('accepted', this)">
                                <i class="fas fa-check-circle text-info"></i> Accepted
                                <span class="filter-count" id="count-accepted">0</span>
                            </button>
                            <button type="button" class="booking-filter-btn filter-completed" data-filter="completed" onclick="filterOrdersByStatus('completed', this)">
                                <i class="fas fa-flag-checkered text-success"></i> Completed
                                <span class="filter-count" id="count-completed">0</span>
                            </button>
                            <button type="button" class="booking-filter-btn" data-filter="cancelled" onclick="filterOrdersByStatus('cancelled', this)">
                                <i class="fas fa-times-circle text-danger"></i> Cancelled
                                <span class="filter-count" id="count-cancelled">0</span>
                            </button>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table green-table mb-0" id="ordersTable">
                                        <thead>
                                            <tr>
                                                <th>Booking No.</th>
                                                <th>Customer</th>
                                                <th>Service</th>
                                                <th>Date & Time</th>
                                                <th>Total Price</th>
                                                <th>Status</th>
                                                <th style="text-align: center; min-width: 175px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ordersList">
                                            <!-- Data via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
