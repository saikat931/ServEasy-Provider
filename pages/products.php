                    <!-- Services Tab -->
                    <div id="tab-products" class="tab-pane <?= $open_tab === 'products' ? 'active' : '' ?>">
                        <div id="productsListContainer">
                            <div class="d-sm-flex align-items-center justify-content-between flex-wrap mb-4" style="gap: 16px;">
                                <div>
                                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">My Services</h1>
                                    <small class="text-muted">Services go <strong>LIVE</strong> once accepted by an Admin or Super Admin.</small>
                                </div>
                                <button class="btn <?= $profile_progress['is_100'] ? 'btn-emerald' : 'btn-secondary' ?> btn-sm shadow-sm my-1 flex-shrink-0"
                                    id="btnAddServiceBtn"
                                    onclick="checkProfileBeforeAdd(event)">
                                    <i class="fas fa-<?= $profile_progress['is_100'] ? 'plus' : 'lock' ?> mr-2"></i> Add Service
                                </button>
                            </div>

                            <div id="servicesProfileLockAlert" style="<?= $profile_progress['is_100'] ? 'display:none;' : '' ?>">
                                <div class="alert alert-warning border-left-warning d-flex align-items-center justify-content-between flex-wrap shadow-sm mb-4" style="border-radius: 10px; border-left: 5px solid #f6c23e !important; gap: 16px;">
                                    <div class="d-flex align-items-center flex-grow-1 mr-lg-3">
                                        <i class="fas fa-lock fa-2x mr-3 text-warning flex-shrink-0"></i>
                                        <div>
                                            <strong class="text-gray-800">Service Creation Locked: Profile <span id="servicesLockPercent"><?= $profile_progress['percentage'] ?>%</span> Complete</strong>
                                            <div class="small text-muted" id="servicesLockText">You must complete 100% of your profile (Phone, Email, Address, and ID Proof) and have your Government ID Proof <strong>approved by Admin or Super Admin</strong> before adding services.<br>Missing / Incomplete: <strong><?= implode(', ', $profile_progress['missing']) ?></strong></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-warning btn-sm font-weight-bold px-3 my-1 flex-shrink-0" onclick="openProfileCompletionModal()" style="border-radius: 6px;">
                                        <i class="fas fa-edit mr-1"></i> Complete Missing Items
                                    </button>
                                </div>
                            </div>
                            <div class="card shadow-sm mb-4">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table green-table mb-0" id="productsTable">
                                            <thead>
                                                <tr>
                                                    <th>Service</th>
                                                    <th>Price</th>
                                                    <th>Duration & Range</th>
                                                    <th>Admin Approval</th>
                                                    <th>Status</th>
                                                    <th class="text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="productsList">
                                                <!-- Data via AJAX -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Service Form Container -->
                        <div id="addProductContainer" style="display: none;">
                            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                                <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Add New Service</h1>
                                <button class="btn btn-light btn-sm shadow-sm px-3 border-0" onclick="showProductsList()" style="color: var(--emerald); font-weight: 700; border-radius: 6px;">
                                    <i class="fas fa-arrow-left mr-2"></i> Back to Services
                                </button>
                            </div>
                            
                            <div class="row">
                                <div class="col-lg-8 mx-auto mb-4">
                                    <div class="card shadow-sm border-0 mb-4 h-100" style="border-radius: 12px;">
                                        <form id="addProductForm" enctype="multipart/form-data">
                                            <input type="hidden" name="ajax_action" value="add_product">
                                            <input type="hidden" name="sku" id="productSkuInput" value="">
                                            
                                            <div class="card-header d-flex flex-row align-items-center justify-content-between bg-white border-bottom-0"
                                                style="padding: 50px 50px 30px 50px; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                                                <h6 class="m-0 font-weight-bold text-emerald" style="font-size: 1.1rem;">Service Details</h6>
                                                <button type="submit" class="btn btn-emerald btn-sm px-4 font-weight-bold" style="border-radius: 6px;">
                                                    <i class="fas fa-save mr-2"></i> Save Service
                                                </button>
                                            </div>
                                            
                                            <div class="card-body d-flex flex-column justify-content-between" style="padding: 0 50px 50px 50px;">
                                                <!-- Service Name -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Service Name:</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Enter service name..." required style="border-radius: 6px; font-size: 0.85rem;">
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">
                                                
                                                <!-- Description -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Description:</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <textarea name="description" class="form-control form-control-sm" rows="3" placeholder="Enter service description..." required style="border-radius: 6px; font-size: 0.85rem;"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">
                                                <!-- Works / Tasks Included (Checklist) -->
                                                <div class="row align-items-start" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">
                                                        Works List / Inclusions:
                                                        <small class="d-block text-muted font-weight-normal mt-1" style="font-size: 0.75rem;">Enter each work / task included on a new line or separated by commas.</small>
                                                    </div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <textarea name="works_list" id="productWorksListInput" class="form-control form-control-sm" rows="4" placeholder="e.g.&#10;Complete high-pressure foam wash&#10;Chassis & wheels degreasing&#10;Microfiber air dry & glass clean&#10;Tire dressing & rim gloss shine" style="border-radius: 6px; font-size: 0.85rem;"></textarea>
                                                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">These will appear as verified checklist items with green checkmarks during customer booking on Web and Phone.</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">
                                                
                                                <!-- Subcategory -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Subcategory:</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <select name="category_id" id="productCategorySelect" class="form-control form-control-sm" required style="border-radius: 6px; font-size: 0.85rem;">
                                                                <option value="" disabled selected>Select subcategory...</option>
                                                                <?php
                                                                $categories = is_array($categories ?? null) ? $categories : [];
                                                                foreach ($categories as $cat):
                                                                ?>
                                                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">

                                                <!-- Service Mode -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Service Mode:</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <select name="service_mode" id="productServiceModeSelect" class="form-control form-control-sm" required style="border-radius: 6px; font-size: 0.85rem;">
                                                                <option value="offline" selected>Offline</option>
                                                                <option value="online">Online</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">
                                                
                                                <?php
                                                // Branches provided by Central Backend API init_dashboard
                                                $provider_branches = is_array($provider_branches ?? null) ? $provider_branches : [];
                                                $has_branches = (!empty($provider_branches) && count($provider_branches) > 0);
                                                ?>
                                                <!-- Branches (Only shown if provider registered a company and has branches) -->
                                                <div class="row align-items-center" id="branchSelectionRow" style="padding: 14px 0; <?= $has_branches ? '' : 'display: none;' ?>">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Select Branch(es):</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <select name="branch_ids[]" id="productBranchSelect" class="form-control form-control-sm" multiple style="border-radius: 6px; font-size: 0.85rem;">
                                                                <?php foreach ($provider_branches as $b): ?>
                                                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <small class="text-muted d-block mt-1">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0" id="branchSelectionRowHr" style="<?= $has_branches ? '' : 'display: none;' ?>">
                                                
                                                <!-- Price -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Base Price (₹):</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <input type="number" name="price" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="0.00" required style="border-radius: 6px; font-size: 0.85rem;">
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">
                                                
                                                <!-- Duration -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Duration (minutes):</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <input type="number" name="qty_in_stock" min="1" class="form-control form-control-sm" placeholder="60" required style="border-radius: 6px; font-size: 0.85rem;">
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">
                                                
                                                <!-- Service Range Slider -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Service Range:</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <div class="d-flex align-items-center" style="gap: 16px;">
                                                                <input type="range" name="service_range_km" min="1" max="200" value="10" step="1"
                                                                    style="flex:1; accent-color: var(--emerald);"
                                                                    oninput="document.getElementById('product-range-display').textContent = this.value + ' km'">
                                                                <span id="product-range-display" class="font-weight-bold text-emerald" style="min-width: 60px; font-size: 1rem;">10 km</span>
                                                            </div>
                                                            <small class="text-muted" style="font-size:0.75rem;">Maximum distance you are willing to travel to serve this specific service.</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">

                                                <!-- Addons Section -->
                                                <div class="row align-items-start" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Additional Small Services:</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <div id="addons-container">
                                                                <!-- Dynamic addons rows go here -->
                                                            </div>
                                                            <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addAddonRow()" style="border-radius: 6px;">
                                                                <i class="fas fa-plus mr-1"></i> Add Small Service
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="m-0">
                                                
                                                <!-- Image Upload -->
                                                <div class="row align-items-center" style="padding: 14px 0;">
                                                    <div class="col-sm-4 font-weight-bold text-gray-800">Service Image (Optional):</div>
                                                    <div class="col-sm-8 d-flex justify-content-center">
                                                        <div class="text-left w-100 pl-4">
                                                            <input type="file" name="product_image" class="form-control-file" accept="image/*" style="font-size: 0.85rem;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
