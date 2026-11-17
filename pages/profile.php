                    <!-- Profile Tab -->
                    <div id="tab-profile" class="tab-pane <?= $open_tab === 'profile' ? 'active' : '' ?>">
                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Account Profile</h1>
                        </div>

                        <!-- Account Details Card (editable) -->
                        <div class="row">
                            <!-- Left: Avatar / Quick Info -->
                            <div class="col-lg-4 mb-4">
                                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                                    <div class="card-body text-center" style="padding: 50px;">
                                        <div class="position-relative d-inline-block mb-3">
                                            <img class="img-profile rounded-circle p-1" src="../Super%20Admin/img/undraw_profile.svg"
                                                style="width: 120px; height: 120px; background-color: var(--mint-cream); border: 2px dashed var(--emerald);">
                                        </div>
                                        <h5 class="font-weight-bold text-gray-800 mb-1" id="prof-name">...</h5>
                                        <p class="text-muted small mb-1" id="prof-company-display"><i class="fas fa-user mr-1"></i>Individual Provider</p>
                                        <div class="mb-4" id="prof-type-badge-container">
                                            <span class="badge badge-secondary px-3 py-2 rounded-pill"><i class="fas fa-user-tag mr-1"></i>Individual Provider</span>
                                        </div>
                                        <div class="pt-3 border-top w-100">
                                            <div class="row text-center text-gray-700">
                                                <div class="col-4 border-right">
                                                    <h6 class="font-weight-bold mb-0 text-emerald">#<?= htmlspecialchars($dealer_id) ?></h6>
                                                    <small class="text-muted font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase;">ID</small>
                                                </div>
                                                <div class="col-4 border-right">
                                                    <h6 class="font-weight-bold mb-0 text-emerald">
                                                        <span class="badge badge-success px-2 py-1 rounded-pill" id="prof-status-badge" style="font-size:.65rem;">...</span>
                                                    </h6>
                                                    <small class="text-muted font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase;">Status</small>
                                                </div>
                                                <div class="col-4">
                                                    <h6 class="font-weight-bold mb-0 text-emerald" style="font-size:.85rem;">
                                                        <?= !empty($created_at) ? date('M y', strtotime($created_at)) : 'N/A' ?>
                                                    </h6>
                                                    <small class="text-muted font-weight-bold" style="font-size: 0.7rem; text-transform: uppercase;">Since</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Editable Account Details & Location Details -->
                            <div class="col-lg-8 mb-4">
                                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                                    <div class="card-header bg-white border-bottom-0 d-flex align-items-center justify-content-between" style="padding: 30px 50px 20px 50px; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                                        <h6 class="m-0 font-weight-bold text-emerald" style="font-size: 1.1rem;">Account & Location Details</h6>
                                        <button class="btn btn-emerald btn-sm px-4 font-weight-bold" style="border-radius: 6px;" onclick="saveProviderProfile()">
                                            <i class="fas fa-save mr-2"></i>Save Profile
                                        </button>
                                    </div>
                                    <div class="card-body" style="padding: 0 50px 30px 50px;">

                                        <!-- Full Name -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Full Name:</div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <input type="text" id="edit-full-name" class="form-control form-control-sm" placeholder="Enter your name" style="border-radius: 6px;">
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Company Name (Hidden by default for individual/normal providers) -->
                                        <div class="row align-items-center" id="company-name-row" style="padding: 14px 0; display: none;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">
                                                Company Name:
                                                <span class="badge badge-success ml-1 font-weight-normal" style="font-size: 0.65rem;">Registered</span>
                                            </div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <input type="text" id="edit-company-name" class="form-control form-control-sm" placeholder="e.g. Apex Electricals Pvt. Ltd." style="border-radius: 6px;">
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0" id="company-name-hr" style="display: none;">

                                        <!-- Email -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Email Address:</div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <input type="email" id="edit-email" class="form-control form-control-sm" placeholder="email@example.com" style="border-radius: 6px;">
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Phone -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Phone Number:</div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <input type="text" id="edit-phone" class="form-control form-control-sm" placeholder="+91 XXXXX XXXXX" style="border-radius: 6px;">
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Location Subheader & Map Pin Button -->
                                        <div class="row align-items-center" style="padding: 18px 0 10px 0;">
                                            <div class="col-12 d-flex align-items-center justify-content-between">
                                                <div class="font-weight-bold text-emerald" style="font-size: 0.95rem;">
                                                    <i class="fas fa-map-marker-alt mr-2"></i>Location Details
                                                </div>
                                                <button type="button" class="btn btn-sm font-weight-bold px-3 py-1" id="toggleProfileMapBtn" onclick="toggleProfileMap()" style="border-radius: 6px; font-size: 0.75rem; background: var(--emerald); color: #fff; border: none; cursor: pointer;">
                                                    <i class="fas fa-map-marked-alt mr-1"></i> Pin on Map
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Inline Map Section -->
                                        <div id="profileMapSection" class="mb-3 position-relative" style="display: none;">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <button type="button" id="btn-use-live-gps" class="btn btn-sm font-weight-bold px-3 shadow-sm" style="border-radius: 6px; background-color: #10b981; border-color: #10b981; color: white;" onclick="useProfileLiveLocation()" title="Pin current location">
                                                    <i class="fas fa-crosshairs mr-1"></i> Pin Current Location
                                                </button>
                                                <span class="small text-muted"><i class="fas fa-thumbtack text-danger mr-1"></i> Drag pin or click map to set exact spot</span>
                                            </div>
                                            <div id="profileMapContainer" style="height: 320px; width: 100%; border-radius: 10px; z-index: 1; border: 1px solid #e3e6f0;"></div>
                                            <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                                                <span><i class="fas fa-info-circle mr-1"></i> Doorstep location pinned on map</span>
                                                <span id="lat-lng-display" class="font-weight-bold text-dark font-monospace"></span>
                                            </div>
                                        </div>

                                        <!-- Full Address -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Full Address: <span class="text-danger">*</span></div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <input type="text" id="edit-location-address" class="form-control form-control-sm" placeholder="Enter street address, area or building name (press Enter to pin on map)..." style="border-radius: 6px;" onkeydown="if(event.key==='Enter'){event.preventDefault();geocodeAndPinAddress(this.value);}">
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Landmark -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Landmark: <span class="text-danger">*</span></div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <input type="text" id="edit-landmark" class="form-control form-control-sm" placeholder="Enter nearby landmark..." style="border-radius: 6px;">
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Pincode -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Pincode: <span class="text-danger">*</span></div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <input type="text" id="edit-pincode" class="form-control form-control-sm" placeholder="Enter 6-digit pincode..." style="border-radius: 6px;">
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="edit-latitude">
                                        <input type="hidden" id="edit-longitude">
                                        <div class="mb-2 text-right" style="display:none;">
                                            <small class="text-muted"><span id="lat-lng-display"></span></small>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Status Subheader -->
                                        <div class="row align-items-center" style="padding: 18px 0 10px 0;">
                                            <div class="col-12">
                                                <div class="font-weight-bold text-muted small" style="text-transform: uppercase; letter-spacing: 0.5px;">
                                                    <i class="fas fa-shield-alt mr-1 text-emerald"></i>Account & Verification Status
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Role (read-only) -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Role:</div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <span class="badge badge-emerald px-3 py-1 rounded-pill">Service Provider</span>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Verified Status (read-only, from serveasy-providers) -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Verified Status:</div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <span id="prof-verified-badge" class="badge px-3 py-1 rounded-pill badge-secondary">...</span>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- Account Status (read-only) -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">Account Status:</div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <span class="badge badge-success px-3 py-1 rounded-pill" id="prof-status">...</span>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="m-0">

                                        <!-- ID Verification (read-only) -->
                                        <div class="row align-items-center" style="padding: 14px 0;">
                                            <div class="col-sm-4 font-weight-bold text-gray-800">ID Verification:</div>
                                            <div class="col-sm-8 d-flex justify-content-center">
                                                <div class="text-left w-100 pl-4">
                                                    <?php if ($verification_status === 'approved'): ?>
                                                        <span class="badge badge-success px-3 py-1 rounded-pill">APPROVED</span>
                                                    <?php elseif (!empty($ver_record) && ($verification_status === 'pending' || $verification_status === 'under_review')): ?>
                                                        <span class="badge badge-warning px-3 py-1 rounded-pill">UNDER REVIEW</span>
                                                    <?php elseif ($verification_status === 'rejected'): ?>
                                                        <span class="badge badge-danger px-3 py-1 rounded-pill mr-2">REJECTED</span>
                                                        <button type="button" class="btn btn-outline-emerald btn-xs py-1 px-3 font-weight-bold" style="font-size: 0.75rem; border-radius: 20px; border-width: 1px;" onclick="switchTab('verification', null)">Resubmit Docs</button>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary px-3 py-1 rounded-pill mr-2">NOT SUBMITTED</span>
                                                        <button type="button" class="btn btn-outline-emerald btn-xs py-1 px-3 font-weight-bold" style="font-size: 0.75rem; border-radius: 20px; border-width: 1px;" onclick="switchTab('verification', null)">Verify ID Now</button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Individual Provider Notice & Register Company Prompt -->
                                        <div id="company-register-prompt-row" class="rounded p-3 mt-3 company-prompt-box" style="display: none;">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 12px;">
                                                <div class="d-flex align-items-center">
                                                    <div class="mr-3 text-secondary" style="font-size: 1.4rem;">
                                                        <i class="fas fa-user-circle"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-weight-bold text-gray-800 small mb-0">Operating as Individual Provider</div>
                                                        <small class="text-muted">Company name is not displayed for individual providers. Want to add branches? Register a company name.</small>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-emerald btn-sm font-weight-bold px-3 py-1 shadow-sm" onclick="openRegisterCompanyModal()" style="border-radius: 6px;">
                                                    <i class="fas fa-building mr-1"></i> Register Company
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Bottom Save Button -->
                                        <div class="d-flex justify-content-end mt-4 pt-2">
                                            <button class="btn btn-emerald btn-sm px-4 py-2 font-weight-bold shadow-sm" style="border-radius: 6px;" onclick="saveProviderProfile()">
                                                <i class="fas fa-save mr-2"></i>Save Profile
                                            </button>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Branches Card -->
                        <div class="row">
                            <div class="col-lg-12 mb-4">
                                <div class="card shadow-sm border-0" style="border-radius: 12px;">
                                    <div class="card-header bg-white border-bottom-0 d-flex align-items-center justify-content-between" style="padding: 30px 50px 20px 50px; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                                        <div class="d-flex align-items-center" style="gap: 10px;">
                                            <h6 class="m-0 font-weight-bold text-emerald" style="font-size: 1.1rem;"><i class="fas fa-code-branch mr-2"></i>Branch Locations</h6>
                                            <span id="branchCompanyBadge" class="badge badge-info px-2 py-1" style="display: none; font-size: 0.75rem;"><i class="fas fa-building mr-1"></i><span id="branchCompanyNameText"></span></span>
                                        </div>
                                        <div>
                                            <button class="btn btn-emerald btn-sm px-4 font-weight-bold" id="btnAddBranchBtn" style="border-radius: 6px;" onclick="toggleAddBranch()">
                                                <i class="fas fa-plus mr-2"></i>Add Branch
                                            </button>
                                            <button class="btn btn-outline-warning btn-sm px-3 font-weight-bold" id="btnRegisterCompanyForBranch" style="border-radius: 6px; display: none;" onclick="openRegisterCompanyModal('open_add_branch')">
                                                <i class="fas fa-lock mr-1"></i> Register Company to Add Branches
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body" style="padding: 0 50px 30px 50px;">

                                        <!-- Lock Banner when Company Not Registered -->
                                        <div id="branchCompanyLockAlert" class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap p-3 mb-4" style="display: none; border-radius: 8px; border-left: 4px solid #f6c23e !important; gap: 16px;">
                                            <div class="d-flex align-items-center flex-grow-1 mr-lg-3">
                                                <div class="mr-3 text-warning flex-shrink-0" style="font-size: 1.5rem;">
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                                <div>
                                                    <h6 class="font-weight-bold mb-1 text-gray-800">Company Registration Required to Add Branches</h6>
                                                    <p class="mb-0 small text-muted">Normal service providers do not have a company name and cannot add branches. You must register a company first to create and manage branch locations.</p>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-warning btn-sm font-weight-bold px-3 shadow-sm my-1 flex-shrink-0" onclick="openRegisterCompanyModal('open_add_branch')" style="border-radius: 6px;">
                                                <i class="fas fa-building mr-1"></i> Register Company Now
                                            </button>
                                        </div>

                                        <!-- Add Branch Form (hidden by default) -->
                                        <div id="addBranchForm" class="add-branch-panel" style="display:none; border-radius: 10px; padding: 24px; margin-bottom: 20px;">
                                            <h6 class="font-weight-bold text-emerald mb-3"><i class="fas fa-plus-circle mr-2"></i>New Branch Details</h6>
                                            
                                            <div class="row">
                                                <div class="col-md-6 form-group mb-3">
                                                    <label class="small font-weight-bold text-gray-800">Branch Name <span class="text-danger">*</span></label>
                                                    <input type="text" id="branch-name" class="form-control" placeholder="e.g. North Branch" style="border-radius: 6px;">
                                                </div>
                                                <div class="col-md-6 form-group mb-3">
                                                    <label class="small font-weight-bold text-gray-800">Phone</label>
                                                    <input type="text" id="branch-phone" class="form-control" placeholder="+91 XXXXX XXXXX" style="border-radius: 6px;">
                                                </div>
                                            </div>

                                            <div class="mb-3 position-relative">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <button type="button" id="btn-use-branch-live-gps" class="btn btn-sm font-weight-bold px-3 shadow-sm" style="border-radius: 6px; background-color: #10b981; border-color: #10b981; color: white;" onclick="useBranchLiveLocation()" title="Pin current branch location">
                                                        <i class="fas fa-crosshairs mr-1"></i> Pin Current Location
                                                    </button>
                                                    <span class="small text-muted"><i class="fas fa-thumbtack text-danger mr-1"></i> Drag pin to fine-tune</span>
                                                </div>
                                                <div id="branchMapContainer" style="height: 250px; width: 100%; border-radius: 12px; z-index: 1; border: 1px solid #e3e6f0;"></div>
                                                <div class="text-center mt-2 small text-muted">
                                                    <i class="fas fa-thumbtack text-danger"></i> Drag the pin to fine-tune your exact branch location
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-12 form-group mb-3">
                                                    <label class="small font-weight-bold text-gray-800">Full Address</label>
                                                    <input type="text" id="branch-address" class="form-control" placeholder="Enter branch street address, area or building name..." style="border-radius: 6px;">
                                                </div>
                                                <div class="col-md-6 form-group mb-3">
                                                    <label class="small font-weight-bold text-gray-800">Landmark</label>
                                                    <input type="text" id="branch-landmark" class="form-control" placeholder="Enter nearby landmark..." style="border-radius: 6px;">
                                                </div>
                                                <div class="col-md-6 form-group mb-3">
                                                    <label class="small font-weight-bold text-gray-800">Pincode</label>
                                                    <input type="text" id="branch-pincode" class="form-control" placeholder="Enter 6-digit pincode..." style="border-radius: 6px;">
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center mt-1" style="gap: 8px;">
                                                <button class="btn btn-emerald btn-sm px-4 font-weight-bold" style="border-radius: 6px;" onclick="submitAddBranch()">
                                                    <i class="fas fa-check mr-1"></i> Save Branch
                                                </button>
                                                <button class="btn btn-light btn-sm px-4 font-weight-bold" style="border-radius: 6px;" onclick="toggleAddBranch()">Cancel</button>
                                                <input type="hidden" id="branch-latitude">
                                                <input type="hidden" id="branch-longitude">
                                            </div>
                                        </div>

                                        <!-- Branches Table -->
                                        <div class="table-responsive mt-2">
                                            <table class="table green-table mb-0" id="branchesTable">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Branch Name</th>
                                                        <th>Address</th>
                                                        <th>Phone</th>
                                                        <th>Coordinates</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="branchesList">
                                                    <tr><td colspan="6" class="text-center py-3 text-muted">Loading branches...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
