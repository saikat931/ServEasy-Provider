                    <!-- Verification Tab -->
                    <div id="tab-verification" class="tab-pane <?= $open_tab === 'verification' ? 'active' : '' ?>">
                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Identity Verification</h1>
                        </div>

                        <div class="row">
                            <div class="col-lg-12 mb-4">
                                <?php if ($verification_status === 'approved'): ?>
                                    <div class="card border-left-success py-4 px-4 text-center shadow-sm"
                                        style="border-left: 0.25rem solid #1cc88a !important; border-radius: 12px;">
                                        <div style="font-size: 3.5rem; color: #1cc88a;" class="mb-3">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <h4 class="font-weight-bold text-gray-800">Your Identity has been Verified!</h4>
                                        <p class="text-muted">Thank you. Both your Primary ID and Extra Document have been approved by Admin.</p>
                                        <div class="mx-auto mt-3 p-3 bg-light text-left id-summary-box border"
                                            style="max-width: 460px; border-radius: 10px;">
                                            <?php if (!empty($ver_record['id_type'])): ?>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="small font-weight-bold text-gray-700"><i class="fas fa-id-card text-primary mr-1"></i> Primary ID:</span>
                                                    <span class="small font-weight-bold text-gray-900"><?= strtoupper(str_replace('_', ' ', $ver_record['id_type'])) ?> (******<?= htmlspecialchars(substr($ver_record['id_number'], -4)) ?>)</span>
                                                </div>
                                                <?php if (!empty($ver_record['extra_document_type'])): ?>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="small font-weight-bold text-gray-700"><i class="fas fa-file-invoice text-info mr-1"></i> Extra Document:</span>
                                                        <span class="small font-weight-bold text-gray-900"><?= strtoupper(str_replace('_', ' ', $ver_record['extra_document_type'])) ?> (******<?= htmlspecialchars(substr($ver_record['extra_document_number'], -4)) ?>)</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between pt-2 border-top">
                                                    <span class="small text-muted">Verified At:</span>
                                                    <span class="small text-muted font-weight-bold"><?= htmlspecialchars($ver_record['verified_at'] ?? $ver_record['updated_at']) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="small"><strong>Status:</strong> Approved directly by Administrator</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif (!empty($ver_record) && ($verification_status === 'pending' || $verification_status === 'under_review')): ?>
                                    <div class="card border-left-warning py-4 px-4 text-center shadow-sm"
                                        style="border-left: 0.25rem solid #f6c23e !important; border-radius: 12px;">
                                        <div style="font-size: 3.5rem; color: #f6c23e;" class="mb-3">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h4 class="font-weight-bold text-gray-800">Verification Under Review</h4>
                                        <p class="text-muted">We are currently reviewing your primary ID and extra documents. This process usually takes 24-48 hours.</p>
                                        <div class="mx-auto mt-3 p-3 bg-light text-left id-summary-box border"
                                            style="max-width: 460px; border-radius: 10px;">
                                            <?php if (!empty($ver_record['id_type'])): ?>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="small font-weight-bold text-gray-700"><i class="fas fa-id-card text-primary mr-1"></i> Primary ID:</span>
                                                    <span class="small font-weight-bold text-gray-900"><?= strtoupper(str_replace('_', ' ', $ver_record['id_type'])) ?> (******<?= htmlspecialchars(substr($ver_record['id_number'], -4)) ?>)</span>
                                                </div>
                                                <?php if (!empty($ver_record['extra_document_type'])): ?>
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="small font-weight-bold text-gray-700"><i class="fas fa-file-invoice text-info mr-1"></i> Extra Document:</span>
                                                        <span class="small font-weight-bold text-gray-900"><?= strtoupper(str_replace('_', ' ', $ver_record['extra_document_type'])) ?> (******<?= htmlspecialchars(substr($ver_record['extra_document_number'], -4)) ?>)</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between pt-2 border-top">
                                                    <span class="small text-muted">Submitted At:</span>
                                                    <span class="small text-muted font-weight-bold"><?= htmlspecialchars($ver_record['created_at']) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="small"><strong>Status:</strong> Pending Admin Review</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php if ($verification_status === 'rejected'): ?>
                                        <div class="alert alert-danger border-left-danger mb-4 shadow-sm"
                                            style="border-radius: 10px !important; border-left: 5px solid #e74a3b !important;">
                                            <i class="fas fa-times-circle mr-2"></i> <strong>Verification Rejected:</strong>
                                            <?= htmlspecialchars(($ver_record['rejection_reason'] ?? '') ?: 'Documents did not meet criteria.') ?>.
                                            Please resubmit both primary and extra document images below.
                                        </div>
                                    <?php endif; ?>

                                    <!-- Verification Submission Card -->
                                    <div class="card py-4 px-4 shadow-sm border-0" style="border-radius: 14px;">
                                        <div class="alert alert-info border-left-info d-flex align-items-center mb-4"
                                            style="border-radius: 10px; border-left: 5px solid #36b9cc !important; background: rgba(54, 185, 204, 0.08);">
                                            <i class="fas fa-shield-alt text-info fa-2x mr-3 flex-shrink-0"></i>
                                            <div>
                                                <h6 class="font-weight-bold text-gray-800 mb-1">Two-Document Identity Verification Required</h6>
                                                <div class="small text-muted">
                                                    For regulatory and security compliance, service providers must provide both a <strong>Primary Government ID</strong> and an <strong>Extra Supporting Document</strong> (e.g. PAN Card, Trade License, GST, or Electricity Bill).
                                                </div>
                                            </div>
                                        </div>

                                        <form id="verificationForm" enctype="multipart/form-data">
                                            <input type="hidden" name="ajax_action" value="submit_verification">

                                            <!-- SECTION 1: PRIMARY IDENTITY DOCUMENT -->
                                            <div class="p-3 mb-4 rounded border" style="background: rgba(78, 115, 223, 0.03); border-color: rgba(78, 115, 223, 0.25) !important;">
                                                <h6 class="font-weight-bold text-gray-800 mb-3 d-flex align-items-center">
                                                    <span class="badge badge-primary mr-2" style="background:#4e73df; font-size: 0.8rem; padding: 5px 8px;">
                                                        <i class="fas fa-id-card mr-1"></i> PRIMARY ID
                                                    </span>
                                                    Primary Identification Document <span class="text-danger ml-1">*</span>
                                                </h6>

                                                <div class="row">
                                                    <div class="col-md-6 form-group">
                                                        <label class="small font-weight-bold text-gray-800">Primary Document Type <span class="text-danger">*</span></label>
                                                        <select name="id_type" class="form-control" required style="border-radius: 8px;">
                                                            <option value="aadhaar">Aadhaar Card</option>
                                                            <option value="driving_license">Driving License</option>
                                                            <option value="passport">Passport</option>
                                                            <option value="voter_id">Voter ID</option>
                                                            <option value="other">Other Gov ID</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 form-group">
                                                        <label class="small font-weight-bold text-gray-800">Primary Document Number <span class="text-danger">*</span></label>
                                                        <input type="text" name="id_number" class="form-control"
                                                            placeholder="Enter primary ID number..." required style="border-radius: 8px;">
                                                    </div>
                                                </div>

                                                <div class="row mt-2">
                                                    <div class="col-md-4 form-group">
                                                        <label class="small font-weight-bold text-gray-800">ID Front Photo <span class="text-danger">*</span></label>
                                                        <input type="file" name="id_front_image" class="form-control-file border p-2 bg-white rounded"
                                                            accept="image/*" required style="font-size: 0.85rem;">
                                                        <small class="form-text text-muted">Clear front side picture</small>
                                                    </div>
                                                    <div class="col-md-4 form-group">
                                                        <label class="small font-weight-bold text-gray-800">ID Back Photo <span class="text-danger">*</span></label>
                                                        <input type="file" name="id_back_image" class="form-control-file border p-2 bg-white rounded"
                                                            accept="image/*" required style="font-size: 0.85rem;">
                                                        <small class="form-text text-muted">Clear back side picture</small>
                                                    </div>
                                                    <div class="col-md-4 form-group">
                                                        <label class="small font-weight-bold text-gray-800">Selfie holding ID <span class="text-danger">*</span></label>
                                                        <input type="file" name="selfie_image" class="form-control-file border p-2 bg-white rounded"
                                                            accept="image/*" required style="font-size: 0.85rem;">
                                                        <small class="form-text text-muted">Face clearly visible with ID</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- SECTION 2: EXTRA SUPPORTING DOCUMENT -->
                                            <div class="p-3 mb-4 rounded border" style="background: rgba(54, 185, 204, 0.03); border-color: rgba(54, 185, 204, 0.3) !important;">
                                                <h6 class="font-weight-bold text-gray-800 mb-2 d-flex align-items-center">
                                                    <span class="badge badge-info mr-2" style="background:#36b9cc; font-size: 0.8rem; padding: 5px 8px;">
                                                        <i class="fas fa-file-invoice mr-1"></i> EXTRA ID
                                                    </span>
                                                    Extra Supporting Document <span class="text-danger ml-1">*</span>
                                                </h6>
                                                <p class="small text-muted mb-3">Provide a secondary official document to confirm your trade, tax, or address credentials.</p>

                                                <div class="row">
                                                    <div class="col-md-6 form-group">
                                                        <label class="small font-weight-bold text-gray-800">Extra Document Type <span class="text-danger">*</span></label>
                                                        <select name="extra_document_type" class="form-control" required style="border-radius: 8px;">
                                                            <option value="pan_card">PAN Card</option>
                                                            <option value="trade_license">Trade / Business License</option>
                                                            <option value="gst_certificate">GST Registration Certificate</option>
                                                            <option value="utility_bill">Electricity / Utility Bill (Address Proof)</option>
                                                            <option value="police_clearance">Police Clearance Certificate</option>
                                                            <option value="other">Other Official Document</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 form-group">
                                                        <label class="small font-weight-bold text-gray-800">Extra Document Number <span class="text-danger">*</span></label>
                                                        <input type="text" name="extra_document_number" class="form-control"
                                                            placeholder="Enter PAN, license, or consumer account number..." required style="border-radius: 8px;">
                                                    </div>
                                                </div>

                                                <div class="row mt-2">
                                                    <div class="col-md-12 form-group">
                                                        <label class="small font-weight-bold text-gray-800">Upload Extra Document Photo or PDF <span class="text-danger">*</span></label>
                                                        <input type="file" name="extra_document_image" class="form-control-file border p-2 bg-white rounded"
                                                            accept="image/*,application/pdf" required style="font-size: 0.85rem;">
                                                        <small class="form-text text-muted">Upload a clear photo or PDF document (Max 5MB)</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="text-right">
                                                <button type="submit" class="btn btn-emerald px-5 py-2 font-weight-bold shadow-sm" style="border-radius: 8px; font-size: 0.95rem;">
                                                    <i class="fas fa-shield-alt mr-2"></i> Submit Both Documents for Verification
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
