<?php
/**
 * ServEasy Service Provider Dashboard (Hostinger Multi-Domain Edition)
 * Communicates securely with the Central Backend Engine (provider-dashboard-api.php) via JWT.
 * Zero local MySQL credentials required on this standalone Provider domain!
 */
session_start();
header('Content-Type: text/html; charset=utf-8');

// Check if dealer is logged in
if (!isset($_SESSION['dealer']) || empty($_SESSION['dealer']['id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/common/ApiBridge.php';

$dealer = $_SESSION['dealer'];
$dealer_id = (int)$dealer['id'];

// Determine active tab from URL query parameter
$open_tab = $_GET['tab'] ?? '';
$valid_tabs = ['dashboard', 'products', 'orders', 'wallet', 'profile', 'verification'];
if (!in_array($open_tab, $valid_tabs, true)) {
    $open_tab = 'dashboard';
}

// 1. Proxy all AJAX Actions transparently to Central Backend API
$action = $_REQUEST['ajax_action'] ?? $_REQUEST['action'] ?? null;
if ($action !== null) {
    $proxyRes = \Frontend\ApiBridge::proxyRequest('provider-dashboard-api.php');
    if ($proxyRes['http_code'] === 401) {
        unset($_SESSION['dealer'], $_SESSION['access_token']);
    }
    if ($action === 'save_provider_profile' && $proxyRes['http_code'] >= 200 && $proxyRes['http_code'] < 300) {
        $decoded = json_decode($proxyRes['body'], true);
        if (!empty($decoded['status']) && $decoded['status'] === 'success') {
            if (!empty($_POST['full_name'])) $_SESSION['dealer']['name'] = trim($_POST['full_name']);
            if (!empty($_POST['email'])) $_SESSION['dealer']['email'] = trim($_POST['email']);
            if (!empty($_POST['phone'])) $_SESSION['dealer']['phone'] = trim($_POST['phone']);
        }
    }
    http_response_code($proxyRes['http_code']);
    header('Content-Type: application/json; charset=utf-8');
    echo $proxyRes['body'];
    exit();
}

// 2. Fetch Initial Dashboard State from Central Backend API
$initRes = \Frontend\ApiBridge::callApi('provider-dashboard-api.php?action=init_dashboard');

if (empty($initRes) || ($initRes['status'] ?? '') !== 'success') {
    // If session token expired or missing, redirect cleanly to login.php
    if (empty($_SESSION['access_token'])) {
        unset($_SESSION['dealer'], $_SESSION['access_token']);
        header("Location: login.php");
        exit();
    }
    $fallbackMsg = $initRes['message'] ?? 'Unable to reach Central Backend API. Please log in again.';
    unset($_SESSION['dealer'], $_SESSION['access_token']);
    header("Location: login.php?error=" . urlencode($fallbackMsg));
    exit();
}

if (!empty($initRes['dealer']) && is_array($initRes['dealer'])) {
    $dealer = array_merge($dealer, $initRes['dealer']);
    $_SESSION['dealer'] = $dealer;
}

$profile_progress    = $initRes['profile_progress'] ?? [
    'percentage' => 0, 'is_100' => false, 'pillars' => [], 'missing' => [],
    'phone' => '', 'email' => '', 'address' => '', 'id_status' => 'not_submitted',
    'ver_record' => null, 'prov_record' => []
];
$ver_record          = $initRes['ver_record'] ?? null;
$prov_record         = $initRes['prov_record'] ?? [];
$verification_status = $initRes['verification_status'] ?? 'not_submitted';
$created_at          = $initRes['created_at'] ?? 'N/A';
$platformFeePercent  = (float)($initRes['platformFeePercent'] ?? 10.0);
$platformFeeType     = (string)($initRes['platformFeeType'] ?? 'percentage');
$platformFeeAmount   = (float)($initRes['platformFeeAmount'] ?? 10.0);
$categories          = $initRes['categories'] ?? [];
$provider_branches   = $initRes['provider_branches'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <script src="./config.js?v=20261118v2"></script>
  <script src="./serveasy-api-bridge.js?v=20261118v2"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Service Provider Dashboard | ServEasy</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito:400,600,700,800&display=swap" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/notify.css">
    <!-- Chart.js for Deskboard Analytics & Donut Gauge -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Deskboard Modern Dashboard Theme -->
    <link rel="stylesheet" href="css/deskboard.css?v=<?= time(); ?>">
    <style>
        :root {
            --emerald: #05c279;
            --emerald-light: #d1fae5;
            --emerald-dark: #038a56;
            --mint-cream: #f0fdf4;
        }

        /* Toggle Switch for Active Status */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
            cursor: pointer;
        }
        .toggle-switch.disabled {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #ccc;
            border-radius: 28px;
            transition: background 0.35s ease, box-shadow 0.35s ease;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.35s ease, box-shadow 0.35s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.18);
        }
        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            box-shadow: 0 0 8px rgba(67, 233, 123, 0.4);
        }
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(24px);
            box-shadow: 0 2px 8px rgba(67, 233, 123, 0.35);
        }
        .toggle-label-text {
            display: block;
            margin-top: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .toggle-label-text.online { color: #05c279; }
        .toggle-label-text.offline { color: #999; }

    </style>
    <script>
        (function() {
            var urlParams = new URLSearchParams(window.location.search);
            var queryTheme = urlParams.get('theme');
            var savedTheme = queryTheme || localStorage.getItem('deskboard_theme') || 'light';
            if (queryTheme) {
                try { localStorage.setItem('deskboard_theme', queryTheme); } catch(e) {}
            }
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>

<body id="page-top">
    <div id="wrapper">
        <!-- Deskboard Modern Sidebar -->
        <aside class="deskboard-sidebar" id="deskboardSidebar">
            <!-- Brand -->
            <div class="sidebar-brand-box">
                <a href="#" onclick="switchTab('dashboard', this); return false;" class="brand-info">
                    <?php
                    $logoPath = __DIR__ . '/img/logo.png';
                    if (file_exists($logoPath)): ?>
                        <img src="img/logo.png?t=<?= time() ?>" alt="ServEasy Logo" class="sidebar-brand-logo">
                    <?php else: ?>
                        <div class="brand-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="brand-text">
                            <span class="brand-title">ServEasy</span>
                            <span class="brand-subtitle">Provider Portal</span>
                        </div>
                    <?php endif; ?>
                </a>
                <button type="button" class="sidebar-minimize-btn" id="sidebarMinimizeBtn" title="Minimize Sidebar">
                    <i class="fas fa-chevron-left" id="sidebarMinimizeIcon"></i>
                </button>
            </div>

            <!-- Scrollable Navigation -->
            <div class="sidebar-menu-scroll">
                <div class="menu-section-label">MAIN MENU</div>
                <ul class="sidebar-nav-list">
                    <li class="nav-item <?= $open_tab === 'dashboard' ? 'active' : '' ?>">
                        <a class="nav-link" href="#" onclick="switchTab('dashboard', this); return false;">
                            <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                            <span class="nav-label">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item <?= $open_tab === 'products' ? 'active' : '' ?>">
                        <a class="nav-link" href="#" onclick="switchTab('products', this); return false;">
                            <span class="nav-icon"><i class="fas fa-box"></i></span>
                            <span class="nav-label">My Services</span>
                        </a>
                    </li>
                    <li class="nav-item <?= $open_tab === 'orders' ? 'active' : '' ?>">
                        <a class="nav-link" href="#" onclick="switchTab('orders', this); return false;">
                            <span class="nav-icon"><i class="fas fa-shopping-cart"></i></span>
                            <span class="nav-label">My Bookings</span>
                        </a>
                    </li>
                    <li class="nav-item <?= $open_tab === 'wallet' ? 'active' : '' ?>">
                        <a class="nav-link" href="index.php?tab=wallet" onclick="switchTab('wallet', this); return false;">
                            <span class="nav-icon"><i class="fas fa-wallet"></i></span>
                            <span class="nav-label">My Wallet & Payouts</span>
                        </a>
                    </li>
                    <li class="nav-item <?= $open_tab === 'profile' ? 'active' : '' ?>">
                        <a class="nav-link" href="#" onclick="switchTab('profile', this); return false;">
                            <span class="nav-icon"><i class="fas fa-user"></i></span>
                            <span class="nav-label">My Profile</span>
                        </a>
                    </li>
                    <li class="nav-item <?= $open_tab === 'verification' ? 'active' : '' ?>">
                        <a class="nav-link" href="#" onclick="switchTab('verification', this); return false;">
                            <span class="nav-icon"><i class="fas fa-id-card"></i></span>
                            <span class="nav-label">ID Verification</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Footer: Log Out -->
            <div class="sidebar-footer">
                <a href="logout.php" class="sidebar-logout-btn" data-toggle="modal" data-target="#logoutModal" onclick="confirmLogout(event)">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                    <span class="nav-label">Logout</span>
                </a>
            </div>
        </aside>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Deskboard Topbar Header -->
                <header class="deskboard-header">
                    <div class="header-welcome d-flex align-items-center">
                        <button id="sidebarMobileToggle" class="mobile-toggle-btn d-lg-none mr-3" type="button">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h2 class="welcome-heading">Welcome Back, <?= htmlspecialchars($dealer['full_name'] ?? $dealer['name'] ?? 'Provider') ?> 👋</h2>
                    </div>

                    <div class="header-actions">
                        <!-- Global Search Input -->
                        <div class="header-search-wrap">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="deskboardGlobalSearch" class="search-input" placeholder="Search services or bookings...">
                        </div>

                        <div class="header-divider"></div>

                        <!-- Dark Mode & Light Mode Theme Toggle -->
                        <button type="button" class="header-icon-btn" id="deskboardThemeToggle" title="Toggle Light / Dark Mode">
                            <i class="fas fa-moon" id="themeToggleIcon"></i>
                        </button>

                        <!-- Notifications Bell Button with Dot Badge -->
                        <button type="button" class="header-icon-btn with-badge" title="Bookings" onclick="switchTab('orders', null)">
                            <i class="far fa-bell"></i>
                            <span class="dot-badge"></span>
                        </button>

                        <!-- User Profile Pill & Dropdown -->
                        <div class="header-user-dropdown dropdown">
                            <div class="user-pill dropdown-toggle" id="userPillDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?= htmlspecialchars($dealer['full_name'] ?? $dealer['name'] ?? 'Provider') ?>">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($dealer['full_name'] ?? $dealer['name'] ?? 'Provider') ?>&background=4361EE&color=fff&rounded=true&bold=true" alt="Avatar" class="user-avatar" id="headerUserAvatar">
                                <i class="fas fa-chevron-down user-caret"></i>
                            </div>
                            <div class="dropdown-menu dropdown-menu-right shadow">
                                <a class="dropdown-item" href="#" onclick="switchTab('profile', null); return false;">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> My Profile
                                </a>
                                <a class="dropdown-item" href="#" onclick="switchTab('verification', null); return false;">
                                    <i class="fas fa-id-card fa-sm fa-fw mr-2 text-gray-400"></i> ID Verification
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php" data-toggle="modal" data-target="#logoutModal" onclick="confirmLogout(event)">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Deskboard Container -->
                <div class="deskboard-container">

                    <!-- Profile Completion Progress Banner -->
                    <div id="profileCompletionBanner">
                        <?php if (!$profile_progress['is_100']): ?>
                            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; border-left: 5px solid #f6c23e !important; background: var(--db-card-bg);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap: 16px;">
                                        <div class="d-flex align-items-center flex-grow-1 mr-lg-3 mb-2 mb-md-0">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 banner-shield-icon flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                                <i class="fas fa-shield-alt"></i>
                                            </div>
                                            <div>
                                                <h5 class="font-weight-bold text-gray-800 mb-1">
                                                    Profile Completion: <span class="text-warning font-weight-bold" id="bannerProgressPercent"><?= $profile_progress['percentage'] ?>%</span>
                                                    <span class="badge badge-warning ml-2 font-weight-bold" style="font-size: 0.75rem; border-radius: 20px;">100% & Admin ID Approval Required</span>
                                                </h5>
                                                <div class="text-muted small">
                                                    You must complete 100% of your profile (<strong>Phone number, Email, Address, and Government ID Proof</strong>) and have your ID Proof <strong>approved by Admin or Super Admin</strong> before adding services. Added services are reviewed and accepted by Admin or Super Admin before going LIVE.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="my-1 flex-shrink-0">
                                            <button class="btn btn-warning btn-sm font-weight-bold px-3 py-2 shadow-sm" onclick="openProfileCompletionModal()" style="border-radius: 8px;">
                                                <i class="fas fa-tasks mr-2"></i> Complete Profile Now
                                            </button>
                                        </div>
                                    </div>
                                    <div class="progress mb-3" style="height: 10px; border-radius: 6px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated <?= $profile_progress['percentage'] < 50 ? 'bg-danger' : ($profile_progress['percentage'] < 100 ? 'bg-warning' : 'bg-success') ?>"
                                             id="bannerProgressBar"
                                             role="progressbar" style="width: <?= $profile_progress['percentage'] ?>%;" aria-valuenow="<?= $profile_progress['percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap" id="bannerPillarsContainer" style="gap: 10px;">
                                        <?php foreach ($profile_progress['pillars'] as $key => $pillar): ?>
                                            <?php
                                                $p_done = $pillar['done'];
                                                $p_status = $pillar['status'] ?? '';
                                                if ($key === 'id_proof') {
                                                    if ($p_done || $p_status === 'approved') {
                                                        $pill_border = '#1cc88a';
                                                        $pill_icon = 'check-circle text-success';
                                                        $pill_badge_class = 'success';
                                                        $pill_badge_text = 'Approved';
                                                    } elseif ($p_status === 'pending' || $p_status === 'under_review') {
                                                        $pill_border = '#f6c23e';
                                                        $pill_icon = 'clock text-warning';
                                                        $pill_badge_class = 'warning';
                                                        $pill_badge_text = 'Under Review';
                                                    } elseif ($p_status === 'rejected') {
                                                        $pill_border = '#e74a3b';
                                                        $pill_icon = 'times-circle text-danger';
                                                        $pill_badge_class = 'danger';
                                                        $pill_badge_text = 'Rejected';
                                                    } else {
                                                        $pill_border = '#e74a3b';
                                                        $pill_icon = 'times-circle text-danger';
                                                        $pill_badge_class = 'danger';
                                                        $pill_badge_text = 'Missing';
                                                    }
                                                } else {
                                                    $pill_border = $p_done ? '#1cc88a' : '#e74a3b';
                                                    $pill_icon = $p_done ? 'check-circle text-success' : 'times-circle text-danger';
                                                    $pill_badge_class = $p_done ? 'success' : 'danger';
                                                    $pill_badge_text = $p_done ? 'Done' : 'Missing';
                                                }
                                            ?>
                                            <div class="d-flex align-items-center px-3 py-2 bg-light rounded" style="border: 1px solid <?= $pill_border ?>; font-size: 0.8rem; cursor: pointer;" onclick="switchTab('<?= $pillar['action_tab'] ?>', null)">
                                                <i class="fas fa-<?= $pill_icon ?> mr-2"></i>
                                                <span class="font-weight-bold mr-2 text-gray-800"><?= $pillar['name'] ?>:</span>
                                                <span class="badge badge-<?= $pill_badge_class ?>"><?= $pill_badge_text ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success border-left-success d-flex align-items-center justify-content-between flex-wrap shadow-sm mb-4"
                                 style="border-radius: 12px; border-left: 5px solid #05c279 !important; padding: 14px 20px; gap: 16px;">
                                <div class="d-flex align-items-center flex-grow-1 mr-lg-3">
                                    <div class="mr-3 flex-shrink-0" style="font-size: 1.8rem; color: #05c279;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div>
                                        <strong class="text-gray-800">Profile 100% Complete!</strong>
                                        <div class="text-muted small">Your Phone Number, Email, Address, and ID Proof are all verified. You are eligible to add new services! Once submitted, each service will go LIVE after acceptance by an Admin or Super Admin.</div>
                                    </div>
                                </div>
                                <button class="btn btn-emerald btn-sm font-weight-bold px-3 py-2 shadow-sm my-1 flex-shrink-0" onclick="switchTab('products', null); showAddProductForm();" style="border-radius: 8px;">
                                    <i class="fas fa-plus-circle mr-1"></i> Add Service
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Dashboard Tab (Deskboard UI Matching Reference Mockup) -->
                    <div id="tab-dashboard" class="tab-pane <?= $open_tab === 'dashboard' ? 'active' : '' ?>">
                        <!-- 3 KPI Stat Cards Row -->
                        <div class="deskboard-stats-grid">
                            <!-- Card 1: Total Services -->
                            <div class="stat-card accent-purple">
                                <div class="stat-card-main">
                                    <div class="stat-meta">
                                        <span class="stat-title">Total Services</span>
                                        <span class="stat-val" id="stat-products">0</span>
                                    </div>
                                    <div class="stat-icon-badge purple">
                                        <i class="fas fa-box"></i>
                                    </div>
                                </div>
                                <div class="stat-subtitle text-muted small">
                                    <i class="fas fa-layer-group mr-1 text-primary"></i> Active Offerings
                                </div>
                            </div>

                            <!-- Card 2: Bookings Received -->
                            <div class="stat-card accent-indigo">
                                <div class="stat-card-main">
                                    <div class="stat-meta">
                                        <span class="stat-title">Bookings Received</span>
                                        <span class="stat-val" id="stat-orders">0</span>
                                    </div>
                                    <div class="stat-icon-badge indigo">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                </div>
                                <div class="stat-subtitle text-muted small">
                                    <i class="fas fa-calendar-check mr-1 text-info"></i> Total Received
                                </div>
                            </div>

                            <!-- Card 3: Total Revenue -->
                            <div class="stat-card accent-teal">
                                <div class="stat-card-main">
                                    <div class="stat-meta">
                                        <span class="stat-title">Total Revenue</span>
                                        <span class="stat-val">₹<span id="stat-sales">0.00</span></span>
                                    </div>
                                    <div class="stat-icon-badge teal">
                                        <i class="fas fa-rupee-sign"></i>
                                    </div>
                                </div>
                                <div class="stat-subtitle text-muted small">
                                    <i class="fas fa-check-circle mr-1 text-success"></i> Completed Orders
                                </div>
                            </div>
                        </div>

                        <!-- Recent Bookings Table Card (Matching reference mockup aesthetic) -->
                        <div class="deskboard-card recent-invoices-card">
                            <div class="deskboard-card-header">
                                <h3 class="card-title">Recent Bookings</h3>
                                <div class="header-controls d-flex align-items-center" style="gap: 8px;">
                                    <button type="button" class="filter-pill-btn" onclick="switchTab('orders', null); if(typeof filterOrdersByStatus === 'function') filterOrdersByStatus('completed'); return false;" title="View all completed bookings">
                                        <i class="fas fa-flag-checkered mr-1.5 text-emerald"></i> Completed Orders
                                    </button>
                                    <button type="button" class="filter-pill-btn" onclick="switchTab('orders', null); if(typeof filterOrdersByStatus === 'function') filterOrdersByStatus('all'); return false;">
                                        <i class="fas fa-list mr-1.5"></i> View All Bookings
                                    </button>
                                </div>
                            </div>
                            <div class="recent-invoices-table-wrap">
                                <table class="deskboard-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Customer Name</th>
                                            <th>Service</th>
                                            <th>Schedule Date & Time</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                            <th style="text-align: center; min-width: 175px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="overviewRecentBookingsBody">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading recent bookings...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Content Pages (Included from pages/) -->
                    <?php include __DIR__ . '/pages/products.php'; ?>
                    <?php include __DIR__ . '/pages/orders.php'; ?>
                    <?php include __DIR__ . '/pages/wallet.php'; ?>
                    <?php include __DIR__ . '/pages/profile.php'; ?>
                    <?php include __DIR__ . '/pages/verification.php'; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Profile Completion & Verification Required Modal -->
    <div class="modal fade" id="profileIncompleteModal" tabindex="-1" role="dialog" aria-labelledby="profileIncompleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);">
                    <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="profileIncompleteModalLabel">
                        <i class="fas fa-shield-alt mr-2"></i> 100% Profile & Admin ID Approval Required
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="mb-2" style="font-size: 2.8rem; color: #f6c23e;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-1">Add Service is Currently Locked</h5>
                        <p class="text-muted small mx-auto mb-0" style="max-width: 580px;">
                            To ensure platform trust and customer safety, your profile must reach <strong>100% completion</strong> and your <strong>Government ID Proof must be approved by an Admin or Super Admin</strong> before you can add services. Without ID verification approval, you cannot add services.
                        </p>
                    </div>

                    <!-- Progress Bar in Modal -->
                    <div class="p-3 rounded mb-4 modal-progress-box" style="border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="font-weight-bold text-gray-800 small text-uppercase">Profile Completion</span>
                            <span class="font-weight-bold h6 mb-0 text-emerald" id="modalProgressPercentText"><?= $profile_progress['percentage'] ?>%</span>
                        </div>
                        <div class="progress" style="height: 12px; border-radius: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated <?= $profile_progress['percentage'] < 50 ? 'bg-danger' : ($profile_progress['percentage'] < 100 ? 'bg-warning' : 'bg-success') ?>"
                                 id="modalProgressBarEl" role="progressbar"
                                 style="width: <?= $profile_progress['percentage'] ?>%;"
                                 aria-valuenow="<?= $profile_progress['percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- 4 Pillars Checklist Cards -->
                    <div class="row" id="modalPillarsList">
                        <!-- Populated dynamically via JS -->
                    </div>

                    <!-- Notice about Admin Approval -->
                    <div class="alert alert-info border-left-info d-flex align-items-center mb-0 mt-3 py-2 px-3" style="border-radius: 8px; border-left: 4px solid #36b9cc !important;">
                        <i class="fas fa-info-circle mr-3 fa-lg text-info"></i>
                        <div class="small text-gray-700">
                            <strong>Verification Rule:</strong> Without ID verification approved by Admin or Super Admin, service providers cannot add services. Once approved and 100% complete, services you submit will go <strong>LIVE</strong> after review and acceptance by an Admin or Super Admin.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 d-flex justify-content-between p-3 bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold px-4" data-dismiss="modal" style="border-radius: 8px; font-size: 0.85rem;">Cancel</button>
                    <button type="button" class="btn btn-emerald font-weight-bold px-4" id="btnModalGoAction" onclick="modalGoToNextMissing()" style="border-radius: 8px; font-size: 0.85rem;">
                        <i class="fas fa-arrow-right mr-1"></i> Complete Missing Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Company Modal -->
    <div class="modal fade" id="registerCompanyModal" tabindex="-1" role="dialog" aria-labelledby="registerCompanyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);">
                    <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="registerCompanyModalLabel">
                        <i class="fas fa-building mr-2"></i> Register Company / Organization
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-3">
                        <div class="mb-2" style="font-size: 2.6rem; color: var(--emerald);">
                            <i class="fas fa-city"></i>
                        </div>
                        <h5 class="font-weight-bold text-gray-800 mb-1">Add Branches with a Company</h5>
                        <p class="text-muted small mx-auto mb-0" style="max-width: 440px;">
                            Normal service providers operate as individual providers without a company name. To create and manage branch locations, please register a company name for your business.
                        </p>
                    </div>

                    <form id="registerCompanyForm" onsubmit="submitRegisterCompany(event)">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-gray-800 small mb-1">Company / Organization Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light border-right-0" style="border-radius: 8px 0 0 8px;"><i class="fas fa-briefcase text-muted"></i></span>
                                </div>
                                <input type="text" id="reg-company-name-input" class="form-control" placeholder="e.g. Apex Electrical Solutions Pvt. Ltd." required style="border-radius: 0 8px 8px 0; font-size: 0.95rem;">
                            </div>
                            <small class="form-text text-muted">Once registered, your company name will be visible on your profile and branch locations will be unlocked.</small>
                        </div>

                        <div class="modal-footer border-top-0 px-0 pb-0 pt-2 d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary font-weight-bold px-4" data-dismiss="modal" style="border-radius: 8px; font-size: 0.85rem;">Cancel</button>
                            <button type="submit" class="btn btn-emerald font-weight-bold px-4 shadow-sm" id="btnSubmitCompanyReg" style="border-radius: 8px; font-size: 0.85rem;">
                                <i class="fas fa-check mr-1"></i> Register Company & Unlock Branches
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.25);">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);">
                    <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt mr-2"></i> Ready to Leave?
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3" style="font-size: 3rem; color: #e74a3b;">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <h5 class="font-weight-bold text-gray-800 mb-2">Confirm Logout</h5>
                    <p class="text-muted small mb-0">Select <strong>"Logout"</strong> below if you are ready to end your current session.</p>
                </div>
                <div class="modal-footer border-top-0 d-flex justify-content-between p-3 bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold px-4" data-dismiss="modal" style="border-radius: 8px; font-size: 0.85rem;">Cancel</button>
                    <a class="btn btn-danger font-weight-bold px-4 shadow-sm" href="logout.php" style="border-radius: 8px; font-size: 0.85rem;">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

        <!-- Payout / Withdrawal Request Modal -->
    <div class="modal fade" id="requestPayoutModal" tabindex="-1" role="dialog" aria-labelledby="requestPayoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                <div class="modal-header modal-header-emerald py-3 px-4">
                    <div class="d-flex align-items-center">
                        <div class="modal-header-icon-box rounded-circle p-2 d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(4px);">
                            <i class="fas fa-hand-holding-usd text-white" style="font-size: 1.2rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold text-white mb-0" id="requestPayoutModalLabel">Withdraw Funds</h5>
                            <div class="text-white-50 small">Transfer available earnings to Bank / UPI</div>
                        </div>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formRequestPayout" onsubmit="submitPayoutRequest(event)">
                    <div class="modal-body p-4">
                        <div class="p-3 mb-3 rounded shadow-sm border payout-balance-box">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small text-uppercase font-weight-bold">Available for Withdrawal</span>
                                <span class="badge badge-success px-2 py-1">Instant Settlement</span>
                            </div>
                            <div class="h3 font-weight-bold text-emerald mb-0 mt-1" id="modal-available-balance">₹0.00</div>
                        </div>
                        <div id="payoutBalanceWarning" class="alert alert-warning py-2 px-3 small mb-3" style="display: none; border-radius: 8px;">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Minimum withdrawal is ₹100.00. You need at least ₹100.00 in available balance to submit a payout request.
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-gray-800 small text-uppercase">Payout Amount (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text font-weight-bold">₹</span>
                                </div>
                                <input type="number" step="0.01" min="100" id="payoutWithdrawAmount" class="form-control" placeholder="Minimum ₹100.00" required>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <small class="text-muted">Minimum withdrawal: ₹100.00</small>
                                <div id="payoutCalcFeedback" class="small"></div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-gray-800 small text-uppercase">Transfer Destination</label>
                            <div class="p-3 rounded border payout-destination-box">
                                <div class="custom-control custom-radio mb-2">
                                    <input type="radio" id="destBankRadio" name="payout_destination" class="custom-control-input" value="bank" checked>
                                    <label class="custom-control-label font-weight-bold text-gray-800" for="destBankRadio">
                                        <i class="fas fa-university text-primary mr-1"></i> <span id="modal-dest-bank-label">Linked Bank Account</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="destUpiRadio" name="payout_destination" class="custom-control-input" value="upi">
                                    <label class="custom-control-label font-weight-bold text-gray-800" for="destUpiRadio">
                                        <i class="fas fa-qrcode text-success mr-1"></i> <span id="modal-dest-upi-label">UPI ID</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-2">
                            <label class="font-weight-bold text-gray-800 small text-uppercase">Notes / Reason (Optional)</label>
                            <textarea id="payoutNotes" class="form-control" rows="2" placeholder="e.g. Weekly earnings payout"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 px-4 py-3">
                        <button type="button" class="btn btn-light font-weight-bold px-3" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="submit" class="btn btn-emerald font-weight-bold px-4 shadow-sm" id="btnSubmitPayout" style="border-radius: 8px;">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Bank & UPI Details Modal -->
    <div class="modal fade" id="updateBankDetailsModal" tabindex="-1" role="dialog" aria-labelledby="updateBankDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                <div class="modal-header modal-header-emerald py-3 px-4">
                    <div class="d-flex align-items-center">
                        <div class="modal-header-icon-box rounded-circle p-2 d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(4px);">
                            <i class="fas fa-money-check-alt text-white" style="font-size: 1.2rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold text-white mb-0" id="updateBankDetailsModalLabel">Settlement Methods</h5>
                            <div class="text-white-50 small">Link your Bank Account or UPI ID separately</div>
                        </div>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <!-- Nav Segmented Tabs -->
                <div class="px-4 pt-3 pb-0 modal-tabs-header border-bottom">
                    <ul class="nav nav-pills nav-fill settlement-pills-nav" id="settlementMethodTabs" role="tablist" style="padding: 4px; border-radius: 10px;">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold py-2" id="tab-btn-bank" data-toggle="pill" href="#tab-pane-bank" role="tab" style="border-radius: 8px; font-size: 0.85rem;">
                                <i class="fas fa-university mr-2"></i> Bank Account
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold py-2" id="tab-btn-upi" data-toggle="pill" href="#tab-pane-upi" role="tab" style="border-radius: 8px; font-size: 0.85rem;">
                                <i class="fas fa-qrcode mr-2"></i> UPI ID
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="settlementMethodTabContent">
                    <!-- Tab 1: Bank Account Form -->
                    <div class="tab-pane fade show active" id="tab-pane-bank" role="tabpanel">
                        <form id="formBankAccount" onsubmit="submitBankAccount(event)">
                            <div class="modal-body p-4">
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-gray-800 small text-uppercase">Account Holder Name <span class="text-danger">*</span></label>
                                    <input type="text" id="bankHolderName" class="form-control" placeholder="Name as per bank records" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-gray-800 small text-uppercase">Bank Name <span class="text-danger">*</span></label>
                                    <input type="text" id="bankNameInput" class="form-control" placeholder="e.g. HDFC Bank, SBI, ICICI" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-gray-800 small text-uppercase">Bank Account Number <span class="text-danger">*</span></label>
                                    <input type="password" id="bankAccountNumber" class="form-control" placeholder="Enter account number" required autocomplete="off">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-gray-800 small text-uppercase">Confirm Account Number <span class="text-danger">*</span></label>
                                    <input type="text" id="bankAccountNumberConfirm" class="form-control" placeholder="Re-enter account number" required autocomplete="off">
                                    <div id="bankAccMatchIndicator" class="mt-1"></div>
                                </div>
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-gray-800 small text-uppercase">IFSC Code <span class="text-danger">*</span></label>
                                    <input type="text" id="bankIfscCode" class="form-control text-uppercase" placeholder="e.g. HDFC0001234" maxlength="11" required>
                                    <div id="bankIfscIndicator" class="mt-1"></div>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 px-4 py-3">
                                <button type="button" class="btn btn-light font-weight-bold px-3" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                                <button type="submit" class="btn btn-emerald font-weight-bold px-4 shadow-sm" id="btnSubmitBankDetails" style="border-radius: 8px;">
                                    <i class="fas fa-save mr-2"></i> Save Bank Account
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab 2: UPI ID Form -->
                    <div class="tab-pane fade" id="tab-pane-upi" role="tabpanel">
                        <form id="formUpiDetails" onsubmit="submitUpiDetails(event)">
                            <div class="modal-body p-4">
                                <div class="p-3 rounded border mb-3 settlement-info-banner">
                                    <div class="d-flex align-items-center text-primary mb-2">
                                        <i class="fas fa-bolt mr-2"></i>
                                        <span class="font-weight-bold small text-uppercase">Instant 24/7 UPI Settlements</span>
                                    </div>
                                    <p class="text-muted small mb-0">Link any UPI Virtual Payment Address (VPA) from Google Pay, PhonePe, Paytm, BHIM, or any banking app.</p>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-gray-800 small text-uppercase">UPI ID (VPA) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-at text-muted"></i></span>
                                        </div>
                                        <input type="text" id="bankUpiId" class="form-control" placeholder="e.g. 9876543210@paytm, name@okhdfcbank" required autocomplete="off">
                                    </div>
                                    <div id="upiFormatIndicator" class="mt-1"></div>
                                    <small class="text-muted mt-1 d-block">Examples: 9876543210@paytm, provider@okhdfcbank, mobile@ybl</small>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 px-4 py-3">
                                <button type="button" class="btn btn-light font-weight-bold px-3" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                                <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm" id="btnSubmitUpiDetails" style="border-radius: 8px;">
                                    <i class="fas fa-save mr-2"></i> Save UPI ID
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Quick View Modal -->
    <div class="modal fade" id="bookingQuickViewModal" tabindex="-1" role="dialog" aria-labelledby="bookingQuickViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
                <div class="modal-header modal-header-emerald py-3 px-4">
                    <div class="d-flex align-items-center">
                        <div class="modal-header-icon-box rounded-circle p-2 d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(4px);">
                            <i class="fas fa-file-invoice text-white" style="font-size: 1.2rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold text-white mb-0" id="bookingQuickViewModalLabel">
                                Booking Details
                            </h5>
                            <div class="text-white-50 small" id="qv-booking-no-subtitle">Order Reference</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center" style="gap: 10px;">
                        <span id="qv-status-badge" class="badge px-3 py-2 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px; border-radius: 20px;"></span>
                        <button type="button" class="close text-white ml-2" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
                <div class="modal-body p-4" style="max-height: calc(85vh - 120px); overflow-y: auto;">
                    <!-- Loading State -->
                    <div id="qv-loading-spinner" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin text-emerald fa-3x mb-3" style="color: #10b981;"></i>
                        <p class="text-muted font-weight-bold">Loading booking details...</p>
                    </div>

                    <!-- Content Container -->
                    <div id="qv-content-container" style="display: none;">
                        <!-- Key Highlights Ribbon -->
                        <div class="row mb-3">
                            <div class="col-md-4 mb-2">
                                <div class="p-3 bg-white rounded shadow-sm border-left border-success" style="border-left-width: 4px !important;">
                                    <div class="text-muted small text-uppercase font-weight-bold">Service Amount</div>
                                    <div class="h4 font-weight-bold text-dark mb-0" id="qv-total-amount">₹0.00</div>
                                    <span class="badge badge-light border mt-1" id="qv-payment-status">UNPAID</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="p-3 bg-white rounded shadow-sm border-left border-primary" style="border-left-width: 4px !important;">
                                    <div class="text-muted small text-uppercase font-weight-bold">Schedule</div>
                                    <div class="font-weight-bold text-dark mb-0" id="qv-schedule-datetime">-</div>
                                    <span class="small text-muted" id="qv-location-type-badge"><i class="fas fa-home mr-1"></i>Doorstep</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="p-3 bg-white rounded shadow-sm border-left border-info" style="border-left-width: 4px !important;">
                                    <div class="text-muted small text-uppercase font-weight-bold">Execution Time</div>
                                    <div class="font-weight-bold text-dark mb-0" id="qv-duration-display">-</div>
                                    <span class="small text-muted" id="qv-service-duration-est">Est: 60 mins</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Left Column: Service & Pricing -->
                            <div class="col-md-6 mb-3">
                                <div class="card border-0 shadow-sm rounded-lg h-100">
                                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                        <i class="fas fa-concierge-bell text-emerald mr-2" style="color: #10b981;"></i>
                                        <h6 class="font-weight-bold text-gray-800 mb-0">Service Information</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="mb-3 d-flex align-items-start">
                                            <div class="rounded p-2 bg-light mr-3 text-center" style="width: 48px; height: 48px;">
                                                <i class="fas fa-box fa-lg text-emerald" style="color: #10b981; line-height: 32px;"></i>
                                            </div>
                                            <div>
                                                <div class="font-weight-bold text-dark" id="qv-service-name" style="font-size: 1.05rem;">-</div>
                                                <div class="text-muted small" id="qv-package-name">Standard Service</div>
                                            </div>
                                        </div>

                                        <h6 class="text-uppercase text-muted font-weight-bold small mb-2">Service Pricing</h6>
                                        <div class="bg-light p-3 rounded mb-2">
                                            <div class="d-flex justify-content-between mb-1 small">
                                                <span class="text-muted">Service Base Amount:</span>
                                                <span class="font-weight-bold text-dark" id="qv-base-price">₹0.00</span>
                                            </div>
                                            <div id="qv-addons-list">
                                                <!-- Dynamic Addons -->
                                            </div>
                                            <div class="d-flex justify-content-between mb-1 small" id="qv-platform-charge-row" style="display: none !important;">
                                                <span class="text-muted" id="qv-fee-label">Platform Charge:</span>
                                                <span class="font-weight-bold text-info" id="qv-platform-charge">+₹0.00</span>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between font-weight-bold">
                                                <span>Total Service Amount:</span>
                                                <span class="text-emerald" id="qv-breakdown-total" style="color: #10b981;">₹0.00</span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center small text-muted px-1">
                                            <span>Payment Method:</span>
                                            <span class="font-weight-bold text-dark" id="qv-payment-method">Pay on Completion</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Customer & Vehicle -->
                            <div class="col-md-6 mb-3">
                                <div class="card border-0 shadow-sm rounded-lg h-100">
                                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                        <i class="fas fa-user-circle text-primary mr-2"></i>
                                        <h6 class="font-weight-bold text-gray-800 mb-0">Customer & Vehicle</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <!-- Customer Details -->
                                        <div class="mb-3">
                                            <div class="text-muted small text-uppercase font-weight-bold mb-1">Customer</div>
                                            <div class="font-weight-bold text-dark" id="qv-cust-name">-</div>
                                            <div class="small text-muted mt-1">
                                                <i class="fas fa-phone mr-1 text-muted"></i>
                                                <a href="#" id="qv-cust-phone-link" class="text-primary font-weight-bold">-</a>
                                            </div>
                                            <div class="small text-muted mt-1" id="qv-cust-email-row">
                                                <i class="fas fa-envelope mr-1 text-muted"></i>
                                                <span id="qv-cust-email">-</span>
                                            </div>
                                        </div>

                                        <hr class="my-2">

                                        <!-- Vehicle Details (Redesigned Automotive Card) -->
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="text-muted small text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">
                                                    <i class="fas fa-car-side text-primary mr-1"></i>Vehicle Details
                                                </div>
                                                <span class="badge badge-pill px-2 py-1 font-weight-bold" id="qv-vehicle-type-badge" style="background: rgba(59, 130, 246, 0.18); color: #38bdf8; font-size: 0.73rem; border: 1px solid rgba(59, 130, 246, 0.35);">
                                                    <i class="fas fa-motorcycle mr-1" id="qv-vehicle-type-icon"></i><span id="qv-vehicle-type-text">Bike</span>
                                                </span>
                                            </div>
                                            <div class="p-3 rounded bg-light border">
                                                <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 8px;">
                                                    <!-- Authenticated IND License Plate Frame -->
                                                    <div class="d-inline-flex align-items-center" style="background: #0b1120; border: 2px solid #334155; border-radius: 6px; padding: 4px 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                                                        <span style="background: #2563eb; color: #ffffff; font-size: 0.6rem; font-weight: 800; padding: 1px 4px; border-radius: 2px; margin-right: 7px; letter-spacing: 0.5px;">IND</span>
                                                        <span id="qv-vehicle-plate" style="color: #f8fafc !important; font-family: 'Courier New', monospace, Courier; font-weight: 800; font-size: 0.95rem; letter-spacing: 1.5px; text-transform: uppercase;">-</span>
                                                    </div>
                                                    <!-- Vehicle Color Tag with Swatch Dot -->
                                                    <div id="qv-vehicle-color-wrapper" class="d-inline-flex align-items-center px-2 py-1 rounded" style="background: rgba(15, 23, 42, 0.35); border: 1px solid rgba(255, 255, 255, 0.12); font-size: 0.78rem; font-weight: 600;">
                                                        <span id="qv-vehicle-color-dot" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #64748b; margin-right: 6px; border: 1px solid rgba(255, 255, 255, 0.3);"></span>
                                                        <span id="qv-vehicle-color-text" class="text-muted">Color: -</span>
                                                    </div>
                                                </div>
                                                <!-- De-duplicated Vehicle Brand & Model Header -->
                                                <div class="mt-2 pt-2 border-top" style="border-color: rgba(255, 255, 255, 0.1) !important;">
                                                    <div class="text-xs text-muted text-uppercase" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.5px;">Brand &amp; Model</div>
                                                    <div class="font-weight-bold" id="qv-vehicle-model" style="font-size: 1rem; line-height: 1.35; margin-top: 2px;">-</div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="my-2">

                                        <!-- Location / Address -->
                                        <div>
                                            <div class="text-muted small text-uppercase font-weight-bold mb-1">Service Address</div>
                                            <div class="small text-dark" id="qv-address"><i class="fas fa-map-marker-alt text-danger mr-1"></i>-</div>
                                            <div class="small text-muted mt-1" id="qv-landmark-pincode">-</div>
                                            <div id="qv-coords-wrapper" class="mt-2" style="display: none;">
                                                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                                    <span class="badge badge-info px-2 py-1" id="qv-coords-badge" style="font-size: 0.75rem; letter-spacing: 0.5px;"><i class="fas fa-crosshairs mr-1"></i><span id="qv-coords-text">Pin: -</span></span>
                                                    <a href="#" target="_blank" id="qv-nav-btn" class="btn btn-sm btn-success px-2 py-1 font-weight-bold shadow-sm" style="font-size: 0.78rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-directions"></i><span>Navigate with Google Maps</span></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Details: Notes & Timestamps -->
                        <div class="card border-0 shadow-sm rounded-lg mb-2">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <i class="fas fa-info-circle text-info mr-2"></i>
                                <h6 class="font-weight-bold text-gray-800 mb-0">Booking Execution & Notes</h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <div class="small text-muted font-weight-bold mb-1">Customer Notes / Instructions:</div>
                                        <div class="p-2 bg-light rounded text-muted small" id="qv-notes" style="min-height: 48px;">
                                            None provided.
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <div class="small text-muted font-weight-bold mb-1">Audit Timestamps:</div>
                                        <ul class="list-unstyled small mb-0">
                                            <li class="mb-1"><span class="text-muted">Booked At:</span> <span class="font-weight-bold text-dark" id="qv-created-at">-</span></li>
                                            <li class="mb-1"><span class="text-muted">Started At:</span> <span class="font-weight-bold text-dark" id="qv-started-at">-</span></li>
                                            <li class="mb-1"><span class="text-muted">Completed At:</span> <span class="font-weight-bold text-dark" id="qv-completed-at">-</span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                        <!-- Customer Verified Review Card -->
                        <div class="card border-0 shadow-sm rounded-lg mb-2" id="qv-review-container" style="display: none;">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                                <i class="fas fa-star text-warning mr-2"></i>
                                <h6 class="font-weight-bold text-gray-800 mb-0">Customer Verified Review</h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-2" id="qv-review-stars">
                                    <!-- Dynamic star icons -->
                                </div>
                                <div class="p-2 bg-light rounded text-dark font-italic small" id="qv-review-comment">
                                    -
                                </div>
                            </div>
                        </div>
                <div class="modal-footer py-2 px-4 bg-white d-flex justify-content-between align-items-center">
                    <div id="qv-status-actions" class="d-flex align-items-center" style="gap: 8px;">
                        <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold px-3" id="qv-delete-btn" style="display: none; border-radius: 8px;">
                        <i class="fas fa-trash-alt mr-1"></i> Delete Booking
                    </button>
                    </div>
                    <div class="ml-auto">
                        <button type="button" class="btn btn-secondary btn-sm font-weight-bold px-4" data-dismiss="modal" style="border-radius: 8px;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collect Payment via Dynamic UPI QR Code Modal -->
    <div class="modal fade" id="bookingQrModal" tabindex="-1" role="dialog" aria-labelledby="bookingQrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header py-3 px-4 text-white" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle p-2 bg-white text-info mr-3 shadow-sm" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-qrcode fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold text-white mb-0" id="bookingQrModalLabel">Customer Payment QR</h5>
                            <span class="small text-white-50" id="qr-modal-booking-ref">Ref: #0000</span>
                        </div>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 text-center bg-white">
                    <p class="text-muted small mb-2">Ask the customer to scan with any UPI App:</p>
                    <div class="d-flex justify-content-center align-items-center mb-3" style="gap: 8px; flex-wrap: wrap;">
                        <span class="badge badge-light border px-2 py-1"><i class="fab fa-google-pay text-primary mr-1"></i>GPay</span>
                        <span class="badge badge-light border px-2 py-1"><i class="fas fa-mobile-alt text-purple mr-1"></i>PhonePe</span>
                        <span class="badge badge-light border px-2 py-1"><i class="fas fa-wallet text-info mr-1"></i>Paytm</span>
                        <span class="badge badge-light border px-2 py-1"><i class="fas fa-university text-success mr-1"></i>BHIM UPI</span>
                    </div>

                    <div class="p-3 bg-light rounded-lg d-inline-block border shadow-sm mb-3" style="border-radius: 12px;">
                        <img id="qr-modal-image" src="" alt="Payment QR Code" style="width: 220px; height: 220px; border-radius: 8px; display: block;" />
                    </div>

                    <div class="mb-2">
                        <div class="text-muted small font-weight-bold text-uppercase">Total Amount to Collect</div>
                        <div class="font-weight-bold text-dark" style="font-size: 1.8rem;" id="qr-modal-amount">₹0.00</div>
                    </div>
                    <div class="small text-muted mb-3" id="qr-modal-customer-name">Customer: -</div>

                    <div class="alert alert-info py-2 px-3 small rounded mb-0 text-left">
                        <i class="fas fa-info-circle mr-1"></i> Once the customer completes payment on their phone, click <strong>Confirm Payment Received</strong> below to immediately record payment and credit your account.
                    </div>
                </div>
                <div class="modal-footer py-2 px-4 bg-light d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold px-3" data-dismiss="modal" style="border-radius: 8px;">
                        Close
                    </button>
                    <button type="button" class="btn btn-success btn-sm font-weight-bold px-3 shadow-sm" id="qr-modal-confirm-paid-btn" style="border-radius: 8px;">
                        <i class="fas fa-check-circle mr-1"></i> Confirm Payment Received
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="js/notify.js"></script>
    <!-- SweetAlert2 for modern interactive action prompts & modals -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        var globalWalletData = null;

        $(document).ready(function () {
            // Determine active tab from URL query parameter, hash, or localStorage, defaulting to PHP-determined open_tab
            let urlParams = new URLSearchParams(window.location.search);
            let activeTab = urlParams.get('tab') || window.location.hash.substring(1);
            if (!activeTab || !['dashboard', 'products', 'orders', 'wallet', 'profile', 'verification'].includes(activeTab)) {
                activeTab = localStorage.getItem('activeTab') || '<?= $open_tab ?>';
            }
            switchTab(activeTab, null, false);

            // If URL parameters do not contain tab, sync it
            if (!urlParams.has('tab')) {
                let newUrl = window.location.pathname + '?tab=' + activeTab;
                history.replaceState({ tab: activeTab }, '', newUrl);
            }

            // Listen for popstate events to handle browser back/forward buttons
            window.addEventListener('popstate', function (event) {
                let tab = 'dashboard';
                if (event.state && event.state.tab) {
                    tab = event.state.tab;
                } else {
                    let params = new URLSearchParams(window.location.search);
                    tab = params.get('tab') || localStorage.getItem('activeTab') || 'dashboard';
                }
                if (['dashboard', 'products', 'orders', 'wallet', 'profile', 'verification'].includes(tab)) {
                    switchTab(tab, null, false);
                }
            });

            // Auto-expand map when trying to edit/add address
            $(document).on('focus click', '#edit-location-address', function() {
                let section = $('#profileMapSection');
                if (!section.is(':visible')) {
                    toggleProfileMap();
                }
            });

            // Verification Form Submit Handler
            $('#verificationForm').on('submit', function (e) {
                e.preventDefault();
                let formData = new FormData(this);

                let submitBtn = $(this).find('button[type="submit"]');
                submitBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...').prop('disabled', true);

                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function (res) {
                         if (res.status === 'success') {
                             Notify.success(res.message);
                             window.location.reload();
                         } else {
                             Notify.error(res.message);
                             submitBtn.html('Submit Documents').prop('disabled', false);
                         }
                     },
                     error: function () {
                         Notify.error('An error occurred during upload. Please try again.');
                         submitBtn.html('Submit Documents').prop('disabled', false);
                     }
                });
            });

            // Add Product Form Submit Handler
            $('#addProductForm').on('submit', function (e) {
                e.preventDefault();
                let formData = new FormData(this);

                let saveBtn = $(this).find('button[type="submit"]');
                saveBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...').prop('disabled', true);

                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function (res) {
                         if (res.status === 'success') {
                             Notify.success(res.message);
                             showProductsList();
                         } else {
                             Notify.error(res.message);
                             saveBtn.html('<i class="fas fa-save mr-2"></i>Save Product').prop('disabled', false);
                         }
                     },
                     error: function () {
                         Notify.error('An error occurred during submission. Please try again.');
                         saveBtn.html('<i class="fas fa-save mr-2"></i>Save Product').prop('disabled', false);
                     }
                });
            });
        });

        let currentProfileProgress = <?= json_encode($profile_progress) ?>;

        function renderModalPillars(progress) {
            if (!progress || !progress.pillars) return;
            let html = '';
            const icons = {
                phone: 'fa-phone-alt',
                email: 'fa-envelope',
                address: 'fa-map-marker-alt',
                id_proof: 'fa-id-card'
            };
            
            for (let key in progress.pillars) {
                let p = progress.pillars[key];
                let isDone = p.done;
                let bgBadge = isDone ? 'badge-success' : 'badge-danger';
                let badgeText = isDone ? 'Completed' : 'Missing';
                let iconClass = icons[key] || 'fa-check';
                let statusClass = isDone ? 'status-done' : 'status-missing';
                let currentVal = p.val || p.value || '';
                let valDisplay = currentVal ? `<div class="text-truncate text-muted" style="font-size:0.75rem; max-width: 220px;">${$('<div>').text(currentVal).html()}</div>` : `<div class="text-danger small font-italic">Not provided yet</div>`;
                let actionBtnText = isDone ? 'Edit' : 'Add Now';
                let actionBtnClass = isDone ? 'btn-outline-secondary' : 'btn-emerald';

                if (key === 'id_proof') {
                    let st = p.status || 'not_submitted';
                    if (isDone || st === 'approved') {
                        badgeText = 'Approved';
                        bgBadge = 'badge-success';
                        statusClass = 'status-done';
                        iconClass = 'fa-check-circle';
                        actionBtnText = 'Verified';
                        actionBtnClass = 'btn-outline-success';
                        valDisplay = `<div class="text-success small font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Approved by Admin</div><div class="text-muted" style="font-size:0.75rem;">${$('<div>').text(currentVal).html()}</div>`;
                    } else if (st === 'pending' || st === 'under_review') {
                        badgeText = 'Under Review';
                        bgBadge = 'badge-warning';
                        statusClass = 'status-review';
                        iconClass = 'fa-clock';
                        actionBtnText = 'View Status';
                        actionBtnClass = 'btn-warning';
                        valDisplay = `<div class="text-warning small font-weight-bold"><i class="fas fa-clock mr-1"></i>Submitted — Pending Admin Approval</div><div class="text-muted" style="font-size:0.75rem;">${currentVal ? $('<div>').text(currentVal).html() : 'Verification in progress'}</div>`;
                    } else if (st === 'rejected') {
                        badgeText = 'Rejected';
                        bgBadge = 'badge-danger';
                        statusClass = 'status-missing';
                        iconClass = 'fa-times-circle';
                        actionBtnText = 'Resubmit ID';
                        actionBtnClass = 'btn-danger';
                        valDisplay = `<div class="text-danger small font-weight-bold"><i class="fas fa-times-circle mr-1"></i>Rejected by Admin — Resubmit Required</div>`;
                    } else {
                        badgeText = 'Missing';
                        bgBadge = 'badge-danger';
                        statusClass = 'status-missing';
                        iconClass = 'fa-id-card';
                        actionBtnText = 'Upload ID Proof';
                        actionBtnClass = 'btn-emerald';
                        valDisplay = `<div class="text-danger small font-italic"><i class="fas fa-exclamation-circle mr-1"></i>Government ID Proof not uploaded</div>`;
                    }
                }

                html += `
                <div class="col-md-6 mb-3">
                    <div class="p-3 rounded h-100 d-flex flex-column justify-content-between modal-pillar-card ${statusClass}">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mr-2 modal-pillar-icon" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                    <i class="fas ${iconClass}"></i>
                                </div>
                                <span class="font-weight-bold text-gray-800 small">${p.name} (25%)</span>
                            </div>
                            <span class="badge ${bgBadge} px-2 py-1" style="font-size: 0.75rem;">${badgeText}</span>
                        </div>
                        <div class="mb-2">
                            ${valDisplay}
                        </div>
                        <div>
                            <button type="button" class="btn ${actionBtnClass} btn-xs py-1 px-3 w-100 font-weight-bold" style="font-size: 0.75rem; border-radius: 6px;" onclick="goToPillarTab('${p.action_tab}')">
                                ${actionBtnText} <i class="fas fa-chevron-right ml-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
                `;
            }
            $('#modalPillarsList').html(html);
        }

        function openProfileCompletionModal() {
            if (!currentProfileProgress) return;
            let pct = currentProfileProgress.percentage || 0;
            $('#modalProgressPercentText').text(pct + '%');
            let pBar = $('#modalProgressBarEl');
            pBar.css('width', pct + '%').attr('aria-valuenow', pct);
            pBar.removeClass('bg-danger bg-warning bg-success');
            if (pct < 50) pBar.addClass('bg-danger');
            else if (pct < 100) pBar.addClass('bg-warning');
            else pBar.addClass('bg-success');

            renderModalPillars(currentProfileProgress);
            $('#profileIncompleteModal').modal('show');
        }

        function goToPillarTab(tabName) {
            $('#profileIncompleteModal').modal('hide');
            switchTab(tabName, null);
        }

        function modalGoToNextMissing() {
            $('#profileIncompleteModal').modal('hide');
            if (!currentProfileProgress || !currentProfileProgress.pillars) {
                switchTab('profile', null);
                return;
            }
            for (let key in currentProfileProgress.pillars) {
                let p = currentProfileProgress.pillars[key];
                if (!p.done) {
                    switchTab(p.action_tab, null);
                    return;
                }
            }
            switchTab('profile', null);
        }

        function checkProfileBeforeAdd(event) {
            if (event) event.preventDefault();
            if (!currentProfileProgress || !currentProfileProgress.is_100) {
                openProfileCompletionModal();
                return false;
            }
            showAddProductForm();
            return true;
        }

        function checkVerificationBeforeAdd(event) {
            return checkProfileBeforeAdd(event);
        }

        function renderBannerPillars(pillars) {
            if (!pillars) return '';
            let html = '';
            for (let key in pillars) {
                let pillar = pillars[key];
                let isDone = pillar.done;
                let borderCol = isDone ? '#1cc88a' : '#e74a3b';
                let iconClass = isDone ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                let badgeClass = isDone ? 'badge-success' : 'badge-danger';
                let badgeText = isDone ? (key === 'id_proof' ? 'Approved' : 'Done') : 'Missing';

                if (key === 'id_proof') {
                    let st = pillar.status || '';
                    if (isDone || st === 'approved') {
                        borderCol = '#1cc88a';
                        iconClass = 'fa-check-circle text-success';
                        badgeClass = 'badge-success';
                        badgeText = 'Approved';
                    } else if (st === 'pending' || st === 'under_review') {
                        borderCol = '#f6c23e';
                        iconClass = 'fa-clock text-warning';
                        badgeClass = 'badge-warning';
                        badgeText = 'Under Review';
                    } else if (st === 'rejected') {
                        borderCol = '#e74a3b';
                        iconClass = 'fa-times-circle text-danger';
                        badgeClass = 'badge-danger';
                        badgeText = 'Rejected';
                    } else {
                        borderCol = '#e74a3b';
                        iconClass = 'fa-times-circle text-danger';
                        badgeClass = 'badge-danger';
                        badgeText = 'Missing';
                    }
                }

                html += `
                    <div class="d-flex align-items-center px-3 py-2 bg-light rounded" style="border: 1px solid ${borderCol}; font-size: 0.8rem; cursor: pointer;" onclick="switchTab('${pillar.action_tab}', null)">
                        <i class="fas ${iconClass} mr-2"></i>
                        <span class="font-weight-bold mr-2 text-gray-800">${pillar.name}:</span>
                        <span class="badge ${badgeClass}">${badgeText}</span>
                    </div>
                `;
            }
            return html;
        }

        function updateProfileUI(progress) {
            if (!progress) return;
            currentProfileProgress = progress;

            if (progress.is_100) {
                let liveAlertHtml = `
                    <div class="alert alert-success border-left-success d-flex align-items-center justify-content-between flex-wrap shadow-sm mb-4"
                         style="border-radius: 12px; border-left: 5px solid #05c279 !important; padding: 14px 20px; gap: 16px;">
                        <div class="d-flex align-items-center flex-grow-1 mr-lg-3">
                            <div class="mr-3 flex-shrink-0" style="font-size: 1.8rem; color: #05c279;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <strong class="text-gray-800">Profile 100% Complete!</strong>
                                <div class="text-muted small">Your Phone Number, Email, Address, and Government ID Proof are all verified and approved! You are eligible to add new services! Once submitted, each service will go LIVE after review and acceptance by an Admin or Super Admin.</div>
                            </div>
                        </div>
                        <button class="btn btn-emerald btn-sm font-weight-bold px-3 py-2 shadow-sm my-1 flex-shrink-0" onclick="switchTab('products', null); showAddProductForm();" style="border-radius: 8px;">
                            <i class="fas fa-plus-circle mr-1"></i> Add Service
                        </button>
                    </div>
                `;
                $('#profileCompletionBanner').html(liveAlertHtml);
                $('#servicesProfileLockAlert').hide();
                $('#btnAddServiceBtn').removeClass('btn-secondary').addClass('btn-emerald').html('<i class="fas fa-plus mr-2"></i> Add Service');
            } else {
                if ($('#bannerProgressPercent').length === 0 || $('#bannerPillarsContainer').length === 0) {
                    let bannerHtml = `
                        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; border-left: 5px solid #f6c23e !important; background: var(--db-card-bg);">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap: 16px;">
                                    <div class="d-flex align-items-center flex-grow-1 mr-lg-3 mb-2 mb-md-0">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 banner-shield-icon flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-weight-bold text-gray-800 mb-1">
                                                Profile Completion: <span class="text-warning font-weight-bold" id="bannerProgressPercent">${progress.percentage}%</span>
                                                <span class="badge badge-warning ml-2 font-weight-bold" style="font-size: 0.75rem; border-radius: 20px;">100% & Admin ID Approval Required</span>
                                            </h5>
                                            <div class="text-muted small">
                                                You must complete 100% of your profile (<strong>Phone number, Email, Address, and Government ID Proof</strong>) and have your ID Proof <strong>approved by Admin or Super Admin</strong> before adding services. Added services are reviewed and accepted by Admin or Super Admin before going LIVE.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="my-1 flex-shrink-0">
                                        <button class="btn btn-warning btn-sm font-weight-bold px-3 py-2 shadow-sm" onclick="openProfileCompletionModal()" style="border-radius: 8px;">
                                            <i class="fas fa-tasks mr-2"></i> Complete Profile Now
                                        </button>
                                    </div>
                                </div>
                                <div class="progress mb-3" style="height: 10px; border-radius: 6px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated ${progress.percentage < 50 ? 'bg-danger' : (progress.percentage < 100 ? 'bg-warning' : 'bg-success')}"
                                         id="bannerProgressBar"
                                         role="progressbar" style="width: ${progress.percentage}%;" aria-valuenow="${progress.percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex align-items-center flex-wrap" id="bannerPillarsContainer" style="gap: 10px;">
                                    ${renderBannerPillars(progress.pillars)}
                                </div>
                            </div>
                        </div>
                    `;
                    $('#profileCompletionBanner').html(bannerHtml);
                } else {
                    $('#bannerProgressPercent').text(progress.percentage + '%');
                    let bBar = $('#bannerProgressBar');
                    bBar.css('width', progress.percentage + '%').attr('aria-valuenow', progress.percentage);
                    bBar.removeClass('bg-danger bg-warning bg-success');
                    if (progress.percentage < 50) bBar.addClass('bg-danger');
                    else if (progress.percentage < 100) bBar.addClass('bg-warning');
                    else bBar.addClass('bg-success');

                    $('#bannerPillarsContainer').html(renderBannerPillars(progress.pillars));
                }

                $('#servicesProfileLockAlert').show();
                $('#servicesLockPercent').text(progress.percentage + '%');
                $('#servicesLockText').html('You must complete 100% of your profile (Phone, Email, Address, and ID Proof) and have your Government ID Proof <strong>approved by Admin or Super Admin</strong> before adding services.<br>Missing / Incomplete: <strong>' + (progress.missing ? progress.missing.join(', ') : '') + '</strong>');
                $('#btnAddServiceBtn').removeClass('btn-emerald').addClass('btn-secondary').html('<i class="fas fa-lock mr-2"></i> Add Service');
            }

            // Also update modal progress elements
            renderModalPillars(progress);
            $('#modalProgressPercentText').text(progress.percentage + '%');
            let mBar = $('#modalProgressBarEl');
            mBar.css('width', progress.percentage + '%').attr('aria-valuenow', progress.percentage);
            mBar.removeClass('bg-danger bg-warning bg-success');
            if (progress.percentage < 50) mBar.addClass('bg-danger');
            else if (progress.percentage < 100) mBar.addClass('bg-warning');
            else mBar.addClass('bg-success');
        }

        function refreshProfileProgress(callback) {
            $.get('index.php?ajax_action=fetch_profile_progress', function(res) {
                let progressData = (res && res.data) ? res.data : res;
                if (progressData && progressData.pillars) {
                    updateProfileUI(progressData);
                    if (typeof callback === 'function') callback(progressData);
                }
            }, 'json');
        }

        function addAddonRow(name = '', price = '') {
            let container = $('#addons-container');
            let rowId = 'addon-row-' + Date.now() + Math.floor(Math.random() * 1000);
            let rowHtml = `
                <div class="d-flex align-items-center mb-2" id="${rowId}" style="gap: 8px;">
                    <input type="text" name="addon_name[]" class="form-control form-control-sm addon-name-input" placeholder="Service name (e.g. Interior Polish)..." required style="border-radius: 6px; font-size: 0.85rem; flex: 2;">
                    <div class="input-group input-group-sm" style="flex: 1; border-radius: 6px; overflow: hidden;">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">₹</span>
                        </div>
                        <input type="number" name="addon_price[]" step="0.01" min="0.00" class="form-control form-control-sm addon-price-input" placeholder="Price..." required style="border-radius: 6px; font-size: 0.85rem;">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('${rowId}').remove()" style="border-radius: 6px; padding: 0.25rem 0.5rem; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.append(rowHtml);
            let newRow = $('#' + rowId);
            newRow.find('.addon-name-input').val(name);
            newRow.find('.addon-price-input').val(price);
        }

        function updateServiceModeOptions() {
            let catSelect = $('#productCategorySelect');
            let selectedText = catSelect.find('option:selected').text().trim().toLowerCase();
            let catId = parseInt(catSelect.val()) || 0;
            let modeSelect = $('#productServiceModeSelect, select[name="service_mode"]');
            let onlineOpt = modeSelect.find('option[value="online"]');

            let isWash = (catId === 1 || catId === 2 || catId === 41 || selectedText.includes('bike wash') || selectedText.includes('car wash') || selectedText.includes('wash'));

            if (isWash) {
                modeSelect.val('offline');
                onlineOpt.prop('disabled', true).hide();
            } else {
                onlineOpt.prop('disabled', false).show();
            }
        }

        function showAddProductForm() {
            if (!currentProfileProgress || !currentProfileProgress.is_100) {
                openProfileCompletionModal();
                return;
            }
            $('#productsListContainer').hide();
            $('#addProductContainer').show();
            $('#addProductContainer h1').text('Add New Service');
            $('#addProductForm input[name="ajax_action"]').val('add_product');
            $('#addProductForm input[name="id"]').remove();
            
            // Clear form
            $('#addProductForm')[0].reset();
            $('#addons-container').empty();
            $('#product-range-display').text('10 km');
            
            // Auto generate SKU for convenience
            let uniqueSku = 'ORG-SP-' + '<?= $dealer_id ?>' + '-' + Math.floor(Date.now() / 1000);
            $('#productSkuInput').val(uniqueSku);

            // Fetch subcategories if dropdown is empty or needs refresh
            if ($('#productCategorySelect option').length <= 1) {
                $.get('index.php?ajax_action=fetch_categories', function(categories) {
                    let html = '<option value="" disabled selected>Select subcategory...</option>';
                    categories.forEach(cat => {
                        html += `<option value="${cat.id}">${cat.category_name}</option>`;
                    });
                    $('#productCategorySelect').html(html);
                    updateServiceModeOptions();
                });
            } else {
                $('#productCategorySelect').val('');
                updateServiceModeOptions();
            }

            // Branch selection visibility based on available branches
            if ($('#productBranchSelect option').length > 0) {
                $('#branchSelectionRow, #branchSelectionRowHr').show();
            } else {
                $('#branchSelectionRow, #branchSelectionRowHr').hide();
            }
        }
        
        function showProductsList() {
            $('#addProductContainer').hide();
            $('#productsListContainer').show();
            loadProducts();
        }

        let providerEventSource = null;

        function playNotificationChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.12); // A5
                gain.gain.setValueAtTime(0.25, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.45);
            } catch (e) {
                // AudioContext not allowed or unsupported
            }
        }

        function initProviderSSE() {
            if (providerEventSource) {
                try { providerEventSource.close(); } catch (e) {}
                providerEventSource = null;
            }

            if (!window.EventSource) {
                // Fallback for older browsers without SSE
                setInterval(loadOrdersSilent, 15000);
                return;
            }

            const sseUrl = (window.serveasyApiUrl ? window.serveasyApiUrl(`sse-events.php?channel=provider&provider_id=<?= (int)$dealer_id ?>`) : `../../backend/api/sse-events.php?channel=provider&provider_id=<?= (int)$dealer_id ?>`);
            providerEventSource = new EventSource(sseUrl);

            // New booking created by customer -> instantly alert & update UI
            providerEventSource.addEventListener('booking.created', function(e) {
                try {
                    const data = JSON.parse(e.data);
                    const bookingKey = 'notified_booking_' + (data.booking_id || data.booking_number);

                    // Only alert and chime once per booking within the session, and only if still pending
                    if (!sessionStorage.getItem(bookingKey) && (!data.status || data.status === 'pending')) {
                        sessionStorage.setItem(bookingKey, '1');
                        playNotificationChime();
                        if (typeof Notify !== 'undefined' && Notify.info) {
                            Notify.info('New service booking request received!', { title: 'New Booking Alert' });
                        }
                        // Flash notification bell badge
                        $('.dot-badge').addClass('pulse-badge');
                    }
                    loadOrdersSilent();
                    loadStats();
                } catch (err) {
                    console.warn('SSE booking.created error', err);
                }
            });

            // Booking status updated -> refresh orders view & stats
            providerEventSource.addEventListener('booking.updated', function(e) {
                try {
                    loadOrdersSilent();
                    loadStats();
                } catch (err) {
                    console.warn('SSE booking.updated error', err);
                }
            });

            providerEventSource.onerror = function(err) {
                console.debug('Provider SSE cycling or reconnecting...', err);
            };
        }

        // Window focus synchronization & light background heartbeat (20s)
        window.addEventListener('focus', function() {
            loadOrdersSilent();
            if ($('#tab-dashboard').hasClass('active')) {
                loadStats();
            }
        });
        setInterval(function() {
            loadOrdersSilent();
        }, 20000);

        function switchTab(tabName, el, shouldPushState = true) {
            $('.nav-item').removeClass('active');
            if (el) {
                $(el).closest('.nav-item').addClass('active');
            } else {
                $('.nav-link[onclick*="' + tabName + '"]').closest('.nav-item').addClass('active');
            }
            $('.deskboard-container > .tab-pane').removeClass('active');
            $('#tab-' + tabName).addClass('active');

            if (tabName === 'dashboard') loadStats();
            if (tabName === 'products') loadProducts();
            if (tabName === 'orders') loadOrders();
            if (tabName === 'wallet') loadWallet();
            if (tabName === 'profile') loadProfile();

            // Save in localStorage so reloading holds it
            localStorage.setItem('activeTab', tabName);

            // Update URL search params to preserve state on reload
            if (shouldPushState) {
                let newUrl = window.location.pathname + '?tab=' + tabName;
                history.pushState({ tab: tabName }, '', newUrl);
            }
        }

        $(document).ready(function() {
            initProviderSSE();
            loadStats();
            loadOrdersSilent();

            // Support initial filter via URL (e.g. ?tab=orders&filter=completed)
            const urlParams = new URLSearchParams(window.location.search);
            const initialFilter = urlParams.get('filter') || urlParams.get('order_filter');
            if (initialFilter) {
                currentOrderFilter = initialFilter.toLowerCase().trim();
                $('.booking-filter-btn').removeClass('active');
                $(`.booking-filter-btn[data-filter="${currentOrderFilter}"]`).addClass('active');
            }

            // Initial check for branch row visibility and service mode
            if ($('#productBranchSelect option').length > 0) {
                $('#branchSelectionRow, #branchSelectionRowHr').show();
            } else {
                $('#branchSelectionRow, #branchSelectionRowHr').hide();
            }
            updateServiceModeOptions();

            // Automatically select Offline service mode and disable Online mode when Bike Wash or Car Wash is chosen
            $(document).on('change', '#productCategorySelect', function() {
                updateServiceModeOptions();
            });

            // Prevent selecting Online mode if Bike Wash or Car Wash is chosen
            $(document).on('change', '#productServiceModeSelect, select[name="service_mode"]', function() {
                let catSelect = $('#productCategorySelect');
                let selectedText = catSelect.find('option:selected').text().trim().toLowerCase();
                let catId = parseInt(catSelect.val()) || 0;
                let isWash = (catId === 1 || catId === 2 || catId === 41 || selectedText.includes('bike wash') || selectedText.includes('car wash') || selectedText.includes('wash'));
                if (isWash && $(this).val() === 'online') {
                    $(this).val('offline');
                    if (typeof Notify !== 'undefined' && Notify.warning) {
                        Notify.warning('Online mode cannot be selected for wash services.');
                    }
                }
            });

            // Theme Toggle Logic (Light / Dark Mode)
            function applyTheme(theme) {
                if (theme === 'dark') {
                    $('html').attr('data-theme', 'dark');
                    $('#themeToggleIcon').removeClass('fa-moon').addClass('fa-sun');
                    $('#deskboardThemeToggle').attr('title', 'Switch to Light Mode');
                } else {
                    $('html').removeAttr('data-theme');
                    $('#themeToggleIcon').removeClass('fa-sun').addClass('fa-moon');
                    $('#deskboardThemeToggle').attr('title', 'Switch to Dark Mode');
                }
            }

            var activeTheme = localStorage.getItem('deskboard_theme') || 'light';
            applyTheme(activeTheme);

            $('#deskboardThemeToggle').on('click', function(e) {
                e.preventDefault();
                activeTheme = (activeTheme === 'dark') ? 'light' : 'dark';
                localStorage.setItem('deskboard_theme', activeTheme);
                applyTheme(activeTheme);
            });

            // Sidebar toggle handlers
            $('#sidebarMinimizeBtn').on('click', function(e) {
                e.preventDefault();
                var isMin = $('#deskboardSidebar').toggleClass('minimized').hasClass('minimized');
                if (isMin) {
                    $('#sidebarMinimizeIcon').removeClass('fa-chevron-left').addClass('fa-chevron-right');
                    $(this).attr('title', 'Expand Sidebar');
                } else {
                    $('#sidebarMinimizeIcon').removeClass('fa-chevron-right').addClass('fa-chevron-left');
                    $(this).attr('title', 'Minimize Sidebar');
                }
            });

            $('#sidebarMobileToggle').on('click', function(e) {
                e.preventDefault();
                $('#deskboardSidebar').toggleClass('mobile-open');
            });

            // Close mobile sidebar when clicking outside
            $(document).on('click', function(e) {
                if ($(window).width() <= 991) {
                    if (!$(e.target).closest('#deskboardSidebar, #sidebarMobileToggle').length) {
                        $('#deskboardSidebar').removeClass('mobile-open');
                    }
                }
            });

            // Header global search filtering
            $('#deskboardGlobalSearch').on('keyup', function() {
                let q = $(this).val().toLowerCase();
                if ($('#tab-products').hasClass('active')) {
                    $('#productsList tr').filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
                    });
                } else if ($('#tab-orders').hasClass('active')) {
                    $('#ordersList tr').filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
                    });
                }
            });
        });

        // Format elapsed duration between started_at and completed_at (or now)
        function formatBookingDuration(startedAt, completedAt, fallbackStart) {
            const startVal = startedAt || fallbackStart;
            if (!startVal) return 'Completed';
            const cleanStart = String(startVal).replace(/-/g, '/');
            const startMs = new Date(cleanStart).getTime();
            if (isNaN(startMs)) return 'Completed';

            let endMs = Date.now();
            if (completedAt) {
                const cleanEnd = String(completedAt).replace(/-/g, '/');
                const parsedEnd = new Date(cleanEnd).getTime();
                if (!isNaN(parsedEnd)) {
                    endMs = parsedEnd;
                }
            }

            let diffSecs = Math.max(0, Math.floor((endMs - startMs) / 1000));
            const hours = Math.floor(diffSecs / 3600);
            diffSecs %= 3600;
            const mins = Math.floor(diffSecs / 60);
            const secs = diffSecs % 60;

            if (hours > 0) {
                return `${hours}h ${mins}m ${secs}s`;
            } else if (mins > 0) {
                return `${mins}m ${secs}s`;
            } else {
                return `${secs}s`;
            }
        }

        // Live ticking clock for in_progress jobs
        function updateJobLiveClocks() {
            $('.job-clock-badge').each(function() {
                const startTimeStr = $(this).data('start-time');
                if (!startTimeStr) return;
                const cleanStart = String(startTimeStr).replace(/-/g, '/');
                const startMs = new Date(cleanStart).getTime();
                if (isNaN(startMs)) return;

                const nowMs = Date.now();
                let diffSecs = Math.max(0, Math.floor((nowMs - startMs) / 1000));

                const hours = Math.floor(diffSecs / 3600);
                diffSecs %= 3600;
                const mins = Math.floor(diffSecs / 60);
                const secs = diffSecs % 60;

                const pad = (n) => String(n).padStart(2, '0');
                const display = `${pad(hours)}:${pad(mins)}:${pad(secs)}`;
                $(this).find('.timer-tick').text(display);
            });
        }

        // Run ticking clock every second
        setInterval(updateJobLiveClocks, 1000);

        // Helper to get pure Provider Service Amount (excludes platform convenience fee)
        function getProviderAmount(b) {
            if (!b) return 0;
            if (typeof b.provider_amount !== 'undefined' && b.provider_amount !== null && !isNaN(parseFloat(b.provider_amount)) && parseFloat(b.provider_amount) > 0) {
                return parseFloat(b.provider_amount);
            }
            let baseP = parseFloat(b.package_price || b.service_price || b.base_price || 0);
            let extrasTotal = 0;
            if (b.extras) {
                try {
                    let extrasArr = typeof b.extras === 'string' ? JSON.parse(b.extras) : b.extras;
                    if (Array.isArray(extrasArr) && extrasArr.length > 0) {
                        extrasArr.forEach(item => {
                            extrasTotal += parseFloat(item.price || 0);
                        });
                    }
                } catch (e) {}
            }
            let provAmt = baseP + extrasTotal;
            if (provAmt > 0) {
                return provAmt;
            }
            return parseFloat(b.total_amount || b.order_total || 0);
        }

        // Render Overview Recent Bookings with real dynamic data
        function renderOverviewRecentBookings(bookings) {
            const tbody = $('#overviewRecentBookingsBody');
            if (!tbody.length) return;

            // Defensive filter: Only active/actionable bookings (exclude completed & cancelled/rejected)
            const activeBookings = (bookings || []).filter(b => {
                const st = (b.status || '').toLowerCase().trim();
                return !['completed', 'cancelled', 'rejected'].includes(st);
            });

            let html = '';
            if (activeBookings && activeBookings.length > 0) {
                activeBookings.forEach((b, idx) => {
                    let statusClass = 'status-pending';
                    let statusText = 'Pending';
                    const st = (b.status || '').toLowerCase().trim();
                    if (st === 'in_progress' || st === 'accepted') {
                        statusClass = 'status-progress';
                        statusText = st === 'in_progress' ? 'In Progress' : 'Accepted';
                    } else if (st === 'rejected' || st === 'cancelled') {
                        statusClass = 'status-overdue';
                        statusText = st === 'rejected' ? 'Rejected' : 'Cancelled';
                    }

                    // Build Action Buttons
                    // Quick View is ALWAYS available for EVERY booking in orders tab
                    let actionsHtml = `<button class="btn btn-outline-info btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="showBookingQuickView(${b.id})" title="Quick View Booking Details"><i class="fas fa-eye"></i> Quick View</button>`;
                    if (st === 'pending') {
                        actionsHtml += `<button class="btn btn-primary btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px; background:#4361ee; border-color:#4361ee;" onclick="updateBookingStatus(${b.id}, 'in_progress')" title="Accept & Start in 1 Click"><i class="fas fa-bolt"></i> Accept & Start</button>`;
                        actionsHtml += `<button class="btn btn-success btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="updateBookingStatus(${b.id}, 'accepted')"><i class="fas fa-check"></i> Accept</button>`;
                        actionsHtml += `<button class="btn btn-danger btn-xs py-1 px-2 font-weight-bold" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="updateBookingStatus(${b.id}, 'rejected')"><i class="fas fa-times"></i> Reject</button>`;
                    } else if (st === 'accepted') {
                        actionsHtml += `<button class="btn btn-primary btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="updateBookingStatus(${b.id}, 'in_progress')"><i class="fas fa-play"></i> Start Job</button>`;
                        actionsHtml += `<button class="btn btn-success btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="updateBookingStatus(${b.id}, 'completed')" title="Complete & Settle in 1 Click"><i class="fas fa-flag-checkered"></i> Complete & Settle</button>`;
                        actionsHtml += `<button class="btn btn-outline-danger btn-xs py-1 px-2 font-weight-bold" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="updateBookingStatus(${b.id}, 'cancelled')"><i class="fas fa-times-circle"></i> Cancel</button>`;
                    } else if (st === 'in_progress') {
                        actionsHtml += `<button class="btn btn-success btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="updateBookingStatus(${b.id}, 'completed')" title="Complete & Settle in 1 Click"><i class="fas fa-flag-checkered"></i> Complete & Settle</button>`;
                        actionsHtml += `<button class="btn btn-outline-danger btn-xs py-1 px-2 font-weight-bold" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="updateBookingStatus(${b.id}, 'cancelled')"><i class="fas fa-ban"></i> Cancel</button>`;
                    } else {
                        actionsHtml += `<span class="text-muted small">-</span>`;
                    }

                    // Duration / Live Clock Badge (live ticking for in-progress only; completed duration badge omitted)
                    let timerHtml = '';
                    if (st === 'in_progress') {
                        const startTimeVal = b.started_at || b.updated_at || b.created_at || '';
                        timerHtml = `
                            <div class="job-clock-badge mt-1 d-inline-flex align-items-center" data-start-time="${startTimeVal}" style="gap:5px; font-size:0.75rem; color:#f59e0b; font-weight:700; background:rgba(245, 158, 11, 0.12); border:1px solid rgba(245, 158, 11, 0.3); border-radius:20px; padding:3px 10px; width:fit-content;" title="Job Clock Ticking">
                                <i class="fas fa-stopwatch fa-spin" style="animation-duration: 3s;"></i>
                                <span class="timer-tick" style="font-family: 'Courier New', monospace; letter-spacing: 0.5px;">00:00:00</span>
                            </div>`;
                    }

                    const dateFormatted = b.schedule_date
                        ? (b.schedule_date + (b.schedule_time ? ' ' + b.schedule_time.substring(0, 5) : ''))
                        : 'N/A';
                    const custName = b.customer_name || 'Customer';
                    const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(custName)}&background=EEF2FF&color=4361EE&rounded=true&bold=true`;

                    html += `<tr>
                        <td class="tb-id">${idx + 1}</td>
                        <td>
                            <div class="tb-customer" style="cursor: pointer;" onclick="showBookingQuickView(${b.id})" title="Click to Quick View">
                                <img src="${avatarUrl}" class="tb-avatar" alt="${custName}">
                                <span class="tb-customer-name font-weight-bold text-primary">${custName}</span>
                            </div>
                        </td>
                        <td>
                            <div class="tb-item" style="cursor: pointer;" onclick="showBookingQuickView(${b.id})" title="Click to Quick View">
                                <span class="tb-item-thumb"><i class="fas fa-box"></i></span>
                                <span>${b.service_name || 'Service'}</span>
                            </div>
                        </td>
                        <td class="tb-date">${dateFormatted}</td>
                        <td>
                            <div><span class="deskboard-status-pill ${statusClass}">${statusText}</span></div>
                            ${timerHtml}
                        </td>
                        <td class="tb-price">₹${Number(getProviderAmount(b)).toFixed(2)}</td>
                        <td>
                            <div class="d-flex align-items-center justify-content-center" style="gap:4px;">
                                ${actionsHtml}
                            </div>
                        </td>
                    </tr>`;
                });
            } else {
                html = `<tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="d-flex flex-column align-items-center justify-content-center" style="gap:10px;">
                            <div style="width:50px; height:50px; border-radius:50%; background:rgba(16, 185, 129, 0.12); display:flex; align-items:center; justify-content:center; color:#10b981; font-size:1.4rem;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="font-weight-bold" style="font-size:0.95rem;">No Active Bookings In Progress</div>
                            <div class="text-muted small">Completed bookings are archived under your <a href="javascript:void(0)" onclick="switchTab('orders', null); if(typeof filterOrdersByStatus === 'function') filterOrdersByStatus('completed');" class="text-primary font-weight-bold"><i class="fas fa-flag-checkered mr-1 text-emerald"></i>Completed Bookings</a> filter.</div>
                        </div>
                    </td>
                </tr>`;
            }
            tbody.html(html);
            updateJobLiveClocks();
        }

        function loadStats() {
            $.get('index.php?ajax_action=fetch_stats', function (res) {
                $('#stat-products').text(res.products || 0);
                $('#stat-orders').text(res.orders || 0);
                $('#stat-sales').text(res.sales || '0.00');
                renderOverviewRecentBookings(res.recent_bookings || []);
            });
        }

        function loadProducts() {
            if ($('#productsList tr').length === 0 || $('#productsList').text().includes('Loading services')) {
                $('#productsList').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading services...</td></tr>');
            }
            $.get('index.php?ajax_action=fetch_products', function (res) {
                let html = '';
                if (res.length === 0) {
                    html = '<tr><td colspan="6" class="text-center py-4 text-muted">No services found.</td></tr>';
                } else {
                    res.forEach(p => {
                        let approvalBadge = '';
                        if (p.approval_status === 'approved') {
                            approvalBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Approved (LIVE)</span>';
                        } else if (p.approval_status === 'rejected') {
                            approvalBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Rejected</span>';
                        } else {
                            approvalBadge = '<span class="badge badge-warning px-2 py-1"><i class="fas fa-clock mr-1"></i>Pending Approval</span>';
                        }
                        
                        let isActive = (p.is_active == 1);
                        let isApproved = (p.approval_status === 'approved');
                        
                        let activeToggle = '';
                        if (isApproved) {
                            let toggleChecked = isActive ? 'checked' : '';
                            let statusText = isActive ? 'Online (LIVE)' : 'Offline';
                            let statusClass = isActive ? 'online' : 'offline';
                            activeToggle = `<div style="text-align:center;"><label class="toggle-switch"><input type="checkbox" ${toggleChecked} onchange="toggleProductStatus(${p.id}, this.checked ? 1 : 0)"><span class="toggle-slider"></span></label><span class="toggle-label-text ${statusClass}">${statusText}</span></div>`;
                        } else if (p.approval_status === 'rejected') {
                            activeToggle = `<div style="text-align:center;"><span class="toggle-label-text text-danger">Rejected</span></div>`;
                        } else {
                            // Waiting approval: do not show toggle switch button
                            activeToggle = `<div style="text-align:center;"><span class="toggle-label-text offline">Waiting Approval</span></div>`;
                        }

                        let branchHtml = p.branches ? `<div class="small text-muted mt-1 d-flex align-items-center"><i class="fas fa-map-marker-alt text-muted mr-1"></i>${p.branches}</div>` : '';

                        html += `<tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="id-badge mr-2">#${p.id}</span>
                                    <div>
                                        <div class="font-weight-bold text-dark">${p.name}</div>
                                        ${branchHtml}
                                    </div>
                                </div>
                            </td>
                            <td class="font-weight-bold text-emerald" style="font-size: 1.05rem;">₹${p.price || '0.00'}</td>
                            <td>
                                <div class="small text-nowrap d-flex align-items-center" style="gap: 8px;">
                                    <span><i class="far fa-clock text-muted mr-1"></i>${p.qty_in_stock || 0} mins</span>
                                    <span class="text-muted">•</span>
                                    <span><i class="fas fa-route text-muted mr-1"></i>${p.service_range_km ? parseFloat(p.service_range_km).toFixed(0) + ' km' : '10 km'}</span>
                                </div>
                            </td>
                            <td>${approvalBadge}</td>
                            <td>${activeToggle}</td>
                            <td class="text-right" style="white-space: nowrap;">
                                <button class="btn btn-outline-emerald btn-sm mr-1" style="border-radius:6px; color:var(--emerald); border:1px solid var(--emerald); padding: 5px 12px;" onclick="editProduct(${p.id})"><i class="fas fa-edit mr-1"></i>Edit</button>
                                <button class="btn btn-outline-danger btn-sm" style="border-radius:6px; padding: 5px 12px;" onclick="deleteProduct(${p.id})"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                            </td>
                        </tr>`;
                    });
                }
                $('#productsList').html(html);
            });
        }

        function editProduct(id) {
            $.get('index.php?ajax_action=fetch_product&id=' + id, function(res) {
                if (res.status === 'success') {
                    let p = res.data;
                    $('#productsListContainer').hide();
                    $('#addProductContainer').show();
                    $('#addProductContainer h1').text('Edit Service');
                    $('#addProductForm input[name="ajax_action"]').val('edit_product');
                    
                    if ($('#addProductForm input[name="id"]').length === 0) {
                        $('#addProductForm').append('<input type="hidden" name="id" value="">');
                    }
                    $('#addProductForm input[name="id"]').val(p.id);
                    
                    $('#addProductForm input[name="name"]').val(p.name);
                    $('#addProductForm textarea[name="description"]').val(p.description);

                    let worksFormatted = '';
                    if (p.works_list) {
                        try {
                            let parsed = typeof p.works_list === 'string' ? JSON.parse(p.works_list) : p.works_list;
                            if (Array.isArray(parsed)) {
                                worksFormatted = parsed.join('\n');
                            } else {
                                worksFormatted = String(p.works_list);
                            }
                        } catch (e) {
                            worksFormatted = String(p.works_list);
                        }
                    }
                    $('#addProductForm textarea[name="works_list"]').val(worksFormatted);
                    $('#addProductForm input[name="price"]').val(p.price);
                    $('#addProductForm input[name="qty_in_stock"]').val(p.qty_in_stock);
                    $('#addProductForm select[name="service_mode"]').val(p.service_mode || 'offline');

                    let range = parseFloat(p.service_range_km) || 10;
                    $('#addProductForm input[name="service_range_km"]').val(range);
                    $('#product-range-display').text(range + ' km');

                    // Set branch selection
                    if ($('#productBranchSelect option').length > 0) {
                        $('#branchSelectionRow, #branchSelectionRowHr').show();
                        if (p.branch_ids) {
                            $('#productBranchSelect').val(p.branch_ids);
                        }
                    } else {
                        $('#branchSelectionRow, #branchSelectionRowHr').hide();
                    }

                    // Populate addons
                    let addonsContainer = $('#addons-container');
                    addonsContainer.empty();
                    if (p.addons && p.addons.length > 0) {
                        p.addons.forEach(addon => {
                            addAddonRow(addon.name, addon.price);
                        });
                    }
                    
                    if ($('#productCategorySelect option').length <= 1) {
                        $.get('index.php?ajax_action=fetch_categories', function(categories) {
                            let html = '<option value="" disabled>Select subcategory...</option>';
                            categories.forEach(cat => {
                                html += `<option value="${cat.id}">${cat.category_name}</option>`;
                            });
                            $('#productCategorySelect').html(html).val(p.category_id);
                            updateServiceModeOptions();
                        });
                    } else {
                        $('#productCategorySelect').val(p.category_id);
                        updateServiceModeOptions();
                    }
                } else {
                    alert(res.message);
                }
            });
        }

        async function deleteProduct(id) {
            const confirmed = await Notify.confirm('Are you sure you want to delete this service?', { type: 'danger', title: 'Delete Service' });
            if (confirmed) {
                $.post('index.php', { ajax_action: 'delete_product', id: id }, function(res) {
                    if (res.status === 'success') {
                        Notify.success(res.message);
                        loadProducts();
                    } else {
                        Notify.error(res.message);
                    }
                }, 'json').fail(function() {
                    Notify.error('Server error occurred.');
                });
            }
        }

        function toggleProductStatus(id, isActive) {
            $.post('index.php', {
                ajax_action: 'toggle_product_status',
                id: id,
                is_active: isActive
            }, function(res) {
                if(res.status === 'success') {
                    loadProducts();
                } else {
                    Notify.error('Error: ' + res.message);
                    console.error('Failed to change active status:', res.message);
                    loadProducts(); // revert the toggle visually
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                alert('Server error occurred.');
                console.error('Server error during toggle product status:', textStatus, errorThrown);
                loadProducts(); // revert the toggle visually
            });
        }

        function toggleServiceMode(id, mode) {
            $.post('index.php', {
                ajax_action: 'toggle_service_mode',
                id: id,
                service_mode: mode
            }, function(res) {
                if(res.status === 'success') {
                    loadProducts();
                } else {
                    alert(res.message);
                }
            }, 'json').fail(function() {
                alert('Server error occurred.');
            });
        }

        let allOrdersData = [];
        let currentOrderFilter = 'all';

        function filterOrdersByStatus(filter, btn) {
            currentOrderFilter = (filter || 'all').toLowerCase().trim();
            $('.booking-filter-btn').removeClass('active');
            if (btn) {
                $(btn).addClass('active');
            } else {
                $(`.booking-filter-btn[data-filter="${currentOrderFilter}"]`).addClass('active');
            }
            renderOrdersTable();
        }
        window.filterOrdersByStatus = filterOrdersByStatus;

        function updateOrderFilterCounts(list) {
            if (!Array.isArray(list)) list = [];
            const counts = {
                all: list.length,
                pending: 0,
                accepted: 0,
                in_progress: 0,
                completed: 0,
                cancelled: 0
            };
            list.forEach(o => {
                const st = (o.status_name || '').toLowerCase().trim();
                if (st === 'pending') counts.pending++;
                else if (st === 'accepted') counts.accepted++;
                else if (st === 'in_progress') counts.in_progress++;
                else if (st === 'completed') counts.completed++;
                else if (st === 'cancelled' || st === 'rejected') counts.cancelled++;
            });
            $('#count-all').text(counts.all);
            $('#count-pending').text(counts.pending);
            $('#count-accepted').text(counts.accepted);
            $('#count-in_progress').text(counts.in_progress);
            $('#count-completed').text(counts.completed);
            $('#count-cancelled').text(counts.cancelled);
        }
        window.updateOrderFilterCounts = updateOrderFilterCounts;

        function getBookingStatusPriority(status) {
            const st = (status || '').toLowerCase().trim();
            if (st === 'in_progress') return 1;
            if (st === 'pending') return 2;
            if (st === 'accepted') return 3;
            if (st === 'completed') return 4;
            if (st === 'cancelled' || st === 'rejected') return 5;
            return 6;
        }

        function renderOrdersTable() {
            const list = allOrdersData || [];
            updateOrderFilterCounts(list);

            let filtered = list;
            if (currentOrderFilter === 'pending') {
                filtered = list.filter(o => (o.status_name || '').toLowerCase().trim() === 'pending');
            } else if (currentOrderFilter === 'accepted') {
                filtered = list.filter(o => (o.status_name || '').toLowerCase().trim() === 'accepted');
            } else if (currentOrderFilter === 'in_progress') {
                filtered = list.filter(o => (o.status_name || '').toLowerCase().trim() === 'in_progress');
            } else if (currentOrderFilter === 'completed') {
                filtered = list.filter(o => (o.status_name || '').toLowerCase().trim() === 'completed');
            } else if (currentOrderFilter === 'cancelled') {
                filtered = list.filter(o => {
                    const st = (o.status_name || '').toLowerCase().trim();
                    return st === 'cancelled' || st === 'rejected';
                });
            }

            // Status hierarchy sorting: In Progress (in process) -> Pending -> Accepted -> Completed -> Cancelled
            filtered = [...filtered].sort((a, b) => {
                const pA = getBookingStatusPriority(a.status_name);
                const pB = getBookingStatusPriority(b.status_name);
                if (pA !== pB) return pA - pB;
                return (Number(b.id) || 0) - (Number(a.id) || 0);
            });

            $('#ordersList').html(buildOrdersHtml(filtered, currentOrderFilter));
            updateJobLiveClocks();
        }
        window.renderOrdersTable = renderOrdersTable;

        function buildOrdersHtml(res, filterType = 'all') {
            let html = '';
            if (!res || res.length === 0) {
                let msg = 'No bookings found.';
                let icon = 'fas fa-calendar-times';
                let iconColor = 'text-muted';
                if (filterType === 'completed') {
                    msg = 'No completed bookings found. When you complete an order, it will appear here.';
                    icon = 'fas fa-flag-checkered';
                    iconColor = 'text-emerald';
                } else if (filterType === 'pending') {
                    msg = 'No pending booking requests awaiting acceptance.';
                    icon = 'fas fa-clock';
                    iconColor = 'text-warning';
                } else if (filterType === 'accepted') {
                    msg = 'No accepted bookings awaiting service start.';
                    icon = 'fas fa-check-circle';
                    iconColor = 'text-info';
                } else if (filterType === 'in_progress') {
                    msg = 'No service jobs currently in progress.';
                    icon = 'fas fa-spinner fa-spin';
                    iconColor = 'text-primary';
                } else if (filterType === 'cancelled') {
                    msg = 'No cancelled or rejected bookings.';
                    icon = 'fas fa-ban';
                    iconColor = 'text-danger';
                }
                html = `<tr><td colspan="7" class="text-center py-5 text-muted">
                    <div class="d-flex flex-column align-items-center justify-content-center" style="gap:8px;">
                        <i class="${icon} fa-2x ${iconColor} mb-1"></i>
                        <span class="font-weight-bold">${msg}</span>
                    </div>
                </td></tr>`;
            } else {
                res.forEach(o => {
                    let statusLower = (o.status_name || '').toLowerCase().trim();
                    let badgeClass = 'secondary';
                    if (statusLower === 'pending') badgeClass = 'warning';
                    else if (statusLower === 'accepted') badgeClass = 'info';
                    else if (statusLower === 'in_progress') badgeClass = 'primary';
                    else if (statusLower === 'completed') badgeClass = 'success';
                    else if (statusLower === 'rejected') badgeClass = 'danger';
                    else if (statusLower === 'cancelled') badgeClass = 'dark';

                    // Build Action Buttons
                    // Quick View is ALWAYS available for EVERY booking in orders tab
                    let actionsHtml = `<button class="btn btn-outline-info btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="showBookingQuickView(${o.id})" title="Quick View Booking Details"><i class="fas fa-eye"></i> Quick View</button>`;
                    if (statusLower === 'pending') {
                        actionsHtml += `<button class="btn btn-primary btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px; background:#4361ee; border-color:#4361ee;" onclick="updateBookingStatus(${o.id}, 'in_progress')" title="Accept & Start in 1 Click"><i class="fas fa-bolt mr-1"></i>Accept & Start</button>`;
                        actionsHtml += `<button class="btn btn-success btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px;" onclick="updateBookingStatus(${o.id}, 'accepted')"><i class="fas fa-check mr-1"></i>Accept</button>`;
                        actionsHtml += `<button class="btn btn-danger btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px;" onclick="updateBookingStatus(${o.id}, 'rejected')"><i class="fas fa-times mr-1"></i>Reject</button>`;
                    } else if (statusLower === 'accepted') {
                        actionsHtml += `<button class="btn btn-primary btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px;" onclick="updateBookingStatus(${o.id}, 'in_progress')"><i class="fas fa-play mr-1"></i>Start Job</button>`;
                        actionsHtml += `<button class="btn btn-success btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px;" onclick="updateBookingStatus(${o.id}, 'completed')" title="Complete & Settle in 1 Click"><i class="fas fa-flag-checkered mr-1"></i>Complete & Settle</button>`;
                        actionsHtml += `<button class="btn btn-outline-danger btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px;" onclick="updateBookingStatus(${o.id}, 'cancelled')"><i class="fas fa-times-circle mr-1"></i>Cancel</button>`;
                    } else if (statusLower === 'in_progress') {
                        actionsHtml += `<button class="btn btn-success btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px;" onclick="updateBookingStatus(${o.id}, 'completed')" title="Complete & Settle in 1 Click"><i class="fas fa-flag-checkered mr-1"></i>Complete & Settle</button>`;
                        actionsHtml += `<button class="btn btn-outline-danger btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.7rem; border-radius:4px;" onclick="updateBookingStatus(${o.id}, 'cancelled')"><i class="fas fa-ban mr-1"></i>Cancel</button>`;
                    } else if (statusLower === 'completed' || statusLower === 'rejected' || statusLower === 'cancelled') {
                        if (statusLower === 'completed' && String(o.payment_status || '').toUpperCase() !== 'PAID') {
                            actionsHtml += `<button class="btn btn-info btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="showBookingQrModal(${o.id}, '${o.booking_number}', ${getProviderAmount(o)}, '${encodeURIComponent(o.customer_name || 'Customer')}');"><i class="fas fa-qrcode"></i> QR Code</button>`;
                            actionsHtml += `<button class="btn btn-success btn-xs py-1 px-2 font-weight-bold mr-1" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="markBookingPaid(${o.id}, '${o.booking_number}', ${getProviderAmount(o)})"><i class="fas fa-money-bill-wave"></i> Mark Paid</button>`;
                        }
                        actionsHtml += `<button class="btn btn-outline-danger btn-xs py-1 px-2 font-weight-bold" style="font-size:0.75rem; border-radius:4px; display:inline-flex; align-items:center; gap:4px;" onclick="deleteBooking(${o.id})"><i class="fas fa-trash-alt"></i> Delete</button>`;
                    } else {
                        actionsHtml += `<span class="text-muted small">None</span>`;
                    }

                    // Duration / Live Clock Badge (live ticking for in-progress only; completed duration badge omitted)
                    let timerHtml = '';
                    if (statusLower === 'in_progress') {
                        const startTimeVal = o.started_at || o.order_date || '';
                        timerHtml = `
                            <div class="job-clock-badge mt-1 d-inline-flex align-items-center" data-start-time="${startTimeVal}" style="gap:5px; font-size:0.75rem; color:#3b82f6; font-weight:700; background:rgba(59, 130, 246, 0.12); border:1px solid rgba(59, 130, 246, 0.3); border-radius:20px; padding:3px 10px; width:fit-content;" title="Job Clock Ticking">
                                <i class="fas fa-stopwatch fa-spin" style="animation-duration: 3s;"></i>
                                <span class="timer-tick" style="font-family: 'Courier New', monospace; letter-spacing: 0.5px;">00:00:00</span>
                            </div>`;
                    }

                    let scheduleTimeFormatted = o.schedule_time ? o.schedule_time.substring(0, 5) : 'N/A';
                    let tBrand = (o.vehicle_brand || '').trim();
                    let tModel = (o.vehicle_model || '').trim();
                    let tName = '';
                    if (tBrand && tModel) {
                        if (tBrand.toLowerCase() === tModel.toLowerCase()) {
                            tName = tBrand;
                        } else if (tModel.toLowerCase().startsWith(tBrand.toLowerCase())) {
                            tName = tModel;
                        } else if (tBrand.toLowerCase().startsWith(tModel.toLowerCase())) {
                            tName = tBrand;
                        } else {
                            tName = `${tBrand} ${tModel}`;
                        }
                    } else {
                        tName = tBrand || tModel || '';
                    }
                    let vehicleInfo = [tName, o.vehicle_reg_no].filter(Boolean).join(' ') || 'N/A';

                    let extrasHtml = '';
                    if (o.extras) {
                        try {
                            let extrasArr = JSON.parse(o.extras);
                            if (Array.isArray(extrasArr) && extrasArr.length > 0) {
                                let list = extrasArr.map(e => `${e.name} (+₹${parseFloat(e.price).toFixed(2)})`).join(', ');
                                extrasHtml = `<div class="text-emerald font-weight-bold" style="font-size:0.75rem; margin-top:2px;"><i class="fas fa-plus-circle mr-1"></i>Add-ons: ${list}</div>`;
                            }
                        } catch (e) {}
                    }

                    html += `<tr>
                        <td><div class="font-weight-bold text-gray-800" style="font-size:0.8rem; cursor: pointer;" onclick="showBookingQuickView(${o.id})" title="Click to Quick View"><a href="javascript:void(0)" class="text-primary font-weight-bold">${o.booking_number}</a></div></td>
                        <td><div style="cursor: pointer;" onclick="showBookingQuickView(${o.id})" title="Click to Quick View"><div class="font-weight-bold text-dark">${o.customer_name || 'Customer'}</div><small class="text-muted font-weight-bold">${o.customer_phone || 'N/A'}</small></div></td>
                        <td><div class="font-weight-bold" style="font-size:0.8rem;">${o.service_name}</div><small class="text-muted">${vehicleInfo}</small>${extrasHtml}</td>
                        <td><div>${new Date(o.order_date).toLocaleDateString()}</div><small class="text-muted font-weight-bold">${scheduleTimeFormatted}</small></td>
                        <td class="font-weight-bold text-gray-800">₹${Number(getProviderAmount(o)).toFixed(2)}</td>
                        <td>
                            <div><span class="badge badge-${badgeClass} px-2 py-1" style="text-transform: uppercase; font-size:0.65rem;">${o.status_name}</span></div>
                            ${timerHtml}
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                ${actionsHtml}
                            </div>
                        </td>
                    </tr>`;
                });
            }
            return html;
        }

        function loadOrders() {
            if ($('#ordersList tr').length === 0 || $('#ordersList').text().includes('Loading bookings')) {
                $('#ordersList').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Loading bookings...</td></tr>');
            }
            $.get('index.php?ajax_action=fetch_orders', function (res) {
                allOrdersData = Array.isArray(res) ? res : [];
                renderOrdersTable();
            });
        }

        function loadOrdersSilent() {
            $.get('index.php?ajax_action=fetch_orders', function (res) {
                allOrdersData = Array.isArray(res) ? res : [];
                renderOrdersTable();
                if ($('#tab-dashboard').hasClass('active')) {
                    loadStats();
                }
            });
        }

        async function updateBookingStatus(id, status, reason) {
            const rawStatus = (status || '').toLowerCase().trim();
            const isCancellation = (rawStatus === 'rejected' || rawStatus === 'cancelled' || rawStatus === 'cancel' || rawStatus === 'reject' || rawStatus === 'decline');

            // Interactive confirmation dialogs
            if (isCancellation) {
                const targetStatus = (rawStatus === 'rejected' || rawStatus === 'reject' || rawStatus === 'decline') ? 'rejected' : 'cancelled';
                const actionTitle = targetStatus === 'rejected' ? 'Decline Booking Request' : 'Cancel Booking';
                const confirmBtnText = targetStatus === 'rejected' ? '<i class="fas fa-times-circle mr-1"></i> Decline Booking' : '<i class="fas fa-ban mr-1"></i> Cancel Booking';

                if (!reason) {
                    if (typeof Swal !== 'undefined') {
                        const { value: formValues, isConfirmed } = await Swal.fire({
                            title: actionTitle,
                            html: `
                                <p class="text-muted small mb-3">Please specify a reason for this cancellation/rejection:</p>
                                <select id="swal-reject-preset" class="form-control mb-2" style="border-radius:8px; font-size:0.9rem;" onchange="if(this.value && this.value !== 'Other') { document.getElementById('swal-reject-reason').value = this.value; }">
                                    <option value="">-- Select Common Reason --</option>
                                    <option value="Customer requested cancellation">Customer requested cancellation</option>
                                    <option value="Customer unreachable / phone switched off">Customer unreachable / phone switched off</option>
                                    <option value="Provider fully booked at requested time">Provider fully booked at requested time</option>
                                    <option value="Customer address outside operational area">Customer address outside operational area</option>
                                    <option value="Equipment / staff temporarily unavailable">Equipment / staff unavailable</option>
                                    <option value="Vehicle breakdown / emergency">Vehicle breakdown / emergency</option>
                                    <option value="Other">Other (enter custom reason below)</option>
                                </select>
                                <textarea id="swal-reject-reason" class="form-control" rows="3" placeholder="Enter reason for cancelling/declining..." style="border-radius:8px; font-size:0.9rem;"></textarea>
                            `,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#ef4444',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: confirmBtnText,
                            cancelButtonText: 'Keep Booking',
                            preConfirm: () => {
                                const val = document.getElementById('swal-reject-reason').value.trim();
                                if (!val) {
                                    Swal.showValidationMessage('Please provide a reason');
                                    return false;
                                }
                                return val;
                            }
                        });
                        if (!isConfirmed) return;
                        reason = formValues;
                    } else if (typeof Notify !== 'undefined' && Notify.prompt) {
                        const promptReason = await Notify.prompt(`Please enter a reason for ${targetStatus === 'rejected' ? 'declining' : 'cancelling'} this booking:`);
                        if (promptReason === null) return;
                        reason = (promptReason || '').trim() || `${targetStatus === 'rejected' ? 'Declined' : 'Cancelled'} by service provider`;
                    } else {
                        const promptReason = prompt(`Please enter a reason for ${targetStatus === 'rejected' ? 'declining' : 'cancelling'} this booking:`);
                        if (promptReason === null) return;
                        reason = promptReason.trim() || `${targetStatus === 'rejected' ? 'Declined' : 'Cancelled'} by service provider`;
                    }
                }
                status = targetStatus;
            } else if (status === 'accepted') {
                let ok = false;
                if (typeof Swal !== 'undefined') {
                    const res = await Swal.fire({
                        title: 'Accept Booking?',
                        text: 'Confirm and accept this booking order? The customer will be immediately notified.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-check mr-1"></i> Yes, Accept',
                        cancelButtonText: 'Cancel'
                    });
                    ok = res.isConfirmed;
                } else if (typeof Notify !== 'undefined' && Notify.confirm) {
                    ok = await Notify.confirm('Are you sure you want to accept this booking?', { title: 'Accept Booking', confirmText: 'Accept' });
                } else {
                    ok = confirm('Are you sure you want to accept this booking?');
                }
                if (!ok) return;
            } else if (status === 'in_progress') {
                let ok = false;
                if (typeof Swal !== 'undefined') {
                    const res = await Swal.fire({
                        title: 'Start Service Job?',
                        text: 'Start working on this service now? This will begin the live job timer.',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonColor: '#3b82f6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-play mr-1"></i> Start Job',
                        cancelButtonText: 'Cancel'
                    });
                    ok = res.isConfirmed;
                } else {
                    ok = confirm('Start this job now?');
                }
                if (!ok) return;
            } else if (status === 'completed') {
                let ok = false;
                if (typeof Swal !== 'undefined') {
                    const res = await Swal.fire({
                        title: 'Complete Service Job?',
                        text: 'Mark this service job as completed? The customer will receive completion updates.',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-flag-checkered mr-1"></i> Complete Job',
                        cancelButtonText: 'Cancel'
                    });
                    ok = res.isConfirmed;
                } else {
                    ok = confirm('Mark this service job as completed?');
                }
                if (!ok) return;
            }

            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {
                    ajax_action: 'update_booking_status',
                    action: 'update_booking_status',
                    id: id,
                    status: status,
                    reason: reason || ''
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Status Updated',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else if (typeof Notify !== 'undefined' && Notify.success) {
                            Notify.success(res.message);
                        } else {
                            alert(res.message);
                        }
                        if (typeof loadOrders === 'function') loadOrders();
                        if (typeof loadStats === 'function') loadStats();
                        if (typeof fetchLiveBookings === 'function') fetchLiveBookings();
                        if (typeof checkServiceChargeDues === 'function') checkServiceChargeDues();
                    } else {
                        const msg = res.message || '';
                        if (msg.includes('OUTSTANDING_SERVICE_CHARGE')) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Cannot Accept Booking',
                                    html: `<p>${msg.replace('OUTSTANDING_SERVICE_CHARGE:', '').trim()}</p><p class="text-muted small">Please pay your site service charge to accept this job.</p>`,
                                    showCancelButton: true,
                                    confirmButtonColor: '#f59e0b',
                                    confirmButtonText: '<i class="fas fa-wallet mr-1"></i> Pay Service Charge Now',
                                    cancelButtonText: 'Later'
                                }).then((r) => {
                                    if (r.isConfirmed && typeof openPayDuesModal === 'function') {
                                        openPayDuesModal();
                                    }
                                });
                            } else if (typeof Notify !== 'undefined' && Notify.warning) {
                                Notify.warning(msg);
                            } else {
                                alert(msg);
                            }
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'error', title: 'Action Failed', text: msg });
                            } else if (typeof Notify !== 'undefined' && Notify.error) {
                                Notify.error(msg);
                            } else {
                                alert(msg);
                            }
                        }
                    }
                },
                error: function (xhr, statusText, errorThrown) {
                    console.error('[updateBookingStatus Error]', xhr.responseText || errorThrown);
                    const errMsg = 'An error occurred while updating booking status.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Communication Error', text: errMsg });
                    } else if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(errMsg);
                    } else {
                        alert(errMsg);
                    }
                }
            });
        }
        window.updateBookingStatus = updateBookingStatus;

        function showBookingQuickView(id) {
            $('#bookingQuickViewModal').modal('show');
            $('#qv-loading-spinner').show();
            $('#qv-content-container').hide();

            $.get('index.php?ajax_action=fetch_booking_details&id=' + id, function(res) {
                if (res.status === 'success' && res.data) {
                    populateQuickViewModal(res.data);
                    $('#qv-loading-spinner').hide();
                    $('#qv-content-container').show();
                } else {
                    $('#qv-loading-spinner').hide();
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(res.message || 'Unable to load booking details.');
                    } else {
                        alert(res.message || 'Unable to load booking details.');
                    }
                    $('#bookingQuickViewModal').modal('hide');
                }
            }, 'json').fail(function() {
                $('#qv-loading-spinner').hide();
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Network or server error fetching booking details.');
                } else {
                    alert('Network or server error fetching booking details.');
                }
                $('#bookingQuickViewModal').modal('hide');
            });
        }

        function populateQuickViewModal(b) {
            $('#qv-booking-no-subtitle').text('Booking #' + (b.booking_number || b.id));

            // Status Badge
            const st = (b.status || '').toLowerCase();
            let badgeClass = 'badge-secondary';
            let statusLabel = st.toUpperCase();
            if (st === 'completed') badgeClass = 'badge-success';
            else if (st === 'in_progress') badgeClass = 'badge-primary';
            else if (st === 'accepted') badgeClass = 'badge-info';
            else if (st === 'pending') badgeClass = 'badge-warning';
            else if (st === 'rejected' || st === 'cancelled') badgeClass = 'badge-danger';

            $('#qv-status-badge').attr('class', 'badge px-3 py-2 text-uppercase ' + badgeClass).text(statusLabel);

            // Highlights
            $('#qv-total-amount').text('₹' + Number(getProviderAmount(b)).toFixed(2));

            const pStatus = (b.payment_status || 'UNPAID').toUpperCase();
            $('#qv-payment-status').text(pStatus)
                .removeClass('badge-success badge-warning badge-light')
                .addClass(pStatus === 'PAID' ? 'badge-success' : 'badge-warning');

            const dateFormatted = b.schedule_date
                ? (b.schedule_date + (b.schedule_time ? ' at ' + b.schedule_time.substring(0, 5) : ''))
                : 'N/A';
            $('#qv-schedule-datetime').text(dateFormatted);

            const locType = (b.location_type || 'doorstep').toLowerCase();
            $('#qv-location-type-badge').html(
                locType === 'service_center'
                    ? '<i class="fas fa-warehouse mr-1 text-primary"></i>Service Center'
                    : '<i class="fas fa-home mr-1 text-success"></i>Doorstep Service'
            );

            const durationText = formatBookingDuration(b.started_at, b.completed_at, b.created_at);
            $('#qv-duration-display').text(durationText || 'N/A');
            $('#qv-service-duration-est').text('Est Duration: ' + (b.service_duration ? b.service_duration + ' mins' : '60 mins'));

            // Service Info
            $('#qv-service-name').text(b.service_name || 'Vehicle Wash Service');
            $('#qv-package-name').text(b.package_name ? 'Package: ' + b.package_name : 'Standard Service');

            let baseP = parseFloat(b.package_price || b.service_price || 0);

            // Addons
            let addonsTotal = 0;
            let addonsHtml = '';
            if (b.extras) {
                try {
                    let extrasArr = typeof b.extras === 'string' ? JSON.parse(b.extras) : b.extras;
                    if (Array.isArray(extrasArr) && extrasArr.length > 0) {
                        extrasArr.forEach(item => {
                            const itemPrice = parseFloat(item.price) || 0;
                            addonsTotal += itemPrice;
                            addonsHtml += `<div class="d-flex justify-content-between mb-1 small">
                                <span class="text-muted"><i class="fas fa-plus mr-1 text-emerald"></i>${item.name}</span>
                                <span class="font-weight-bold text-dark">+₹${itemPrice.toFixed(2)}</span>
                            </div>`;
                        });
                    }
                } catch (e) {}
            }

            const totalAmt = parseFloat(b.total_amount || b.order_total || 0);
            let providerSetAmount = (typeof b.provider_amount !== 'undefined' && b.provider_amount !== null && Number(b.provider_amount) > 0)
                ? Number(b.provider_amount)
                : (baseP + addonsTotal);
            if (providerSetAmount <= 0) {
                providerSetAmount = totalAmt;
                baseP = totalAmt;
            }

            $('#qv-total-amount').text('₹' + providerSetAmount.toFixed(2));
            $('#qv-base-price').text('₹' + (baseP || providerSetAmount).toFixed(2));
            $('#qv-addons-list').html(addonsHtml);
            $('#qv-platform-charge-row').hide();
            $('#qv-breakdown-total').text('₹' + providerSetAmount.toFixed(2));
            const rawPm = (b.payment_method || '').toUpperCase();
            let pmDisplay = 'Pay on Completion';
            if (rawPm === 'ONLINE' || rawPm === 'RAZORPAY') {
                pmDisplay = 'Online (Razorpay / UPI)';
            } else if (rawPm === 'COD' || rawPm === 'CASH') {
                pmDisplay = 'Cash on Delivery (COD)';
            } else if (rawPm && rawPm !== 'NOT_SELECTED' && rawPm !== 'PENDING' && rawPm !== 'PAY ON COMPLETION') {
                pmDisplay = rawPm;
            }
            $('#qv-payment-method').text(pmDisplay);

            // Customer
            $('#qv-cust-name').text(b.customer_name || 'Customer');
            const phone = b.customer_phone || '';
            if (phone) {
                $('#qv-cust-phone-link').text(phone).attr('href', 'tel:' + phone);
            } else {
                $('#qv-cust-phone-link').text('N/A').removeAttr('href');
            }
            if (b.customer_email) {
                $('#qv-cust-email-row').show();
                $('#qv-cust-email').text(b.customer_email);
            } else {
                $('#qv-cust-email-row').hide();
            }

            // Vehicle Details (De-duplicated & Enhanced)
            const regNo = (b.vehicle_reg_no || 'NO REG').toUpperCase().trim();
            $('#qv-vehicle-plate').text(regNo);

            // Deduplicate Brand and Model
            const brand = (b.vehicle_brand || '').trim();
            const model = (b.vehicle_model || '').trim();
            let vModel = '';
            if (brand && model) {
                if (brand.toLowerCase() === model.toLowerCase()) {
                    vModel = brand;
                } else if (model.toLowerCase().startsWith(brand.toLowerCase())) {
                    vModel = model;
                } else if (brand.toLowerCase().startsWith(model.toLowerCase())) {
                    vModel = brand;
                } else {
                    vModel = `${brand} ${model}`;
                }
            } else {
                vModel = brand || model || 'Vehicle Details N/A';
            }
            $('#qv-vehicle-model').text(vModel);

            // Vehicle Type & Dynamic Icon
            const vType = (b.vehicle_type || 'Vehicle').trim();
            $('#qv-vehicle-type-text').text(vType);
            const vTypeLower = vType.toLowerCase();
            let iconClass = 'fas fa-car';
            if (vTypeLower.includes('bike') || vTypeLower.includes('motorcycle') || vTypeLower.includes('two wheeler')) {
                iconClass = 'fas fa-motorcycle';
            } else if (vTypeLower.includes('scooter') || vTypeLower.includes('moped') || vTypeLower.includes('activa')) {
                iconClass = 'fas fa-motorcycle';
            } else if (vTypeLower.includes('suv') || vTypeLower.includes('muv') || vTypeLower.includes('van') || vTypeLower.includes('pickup')) {
                iconClass = 'fas fa-truck-pickup';
            } else if (vTypeLower.includes('luxury') || vTypeLower.includes('sports')) {
                iconClass = 'fas fa-gem';
            }
            $('#qv-vehicle-type-icon').attr('class', iconClass + ' mr-1');

            // Vehicle Color & Swatch Dot
            const vColor = (b.vehicle_color || '').trim();
            if (vColor && vColor !== 'N/A' && vColor !== 'null') {
                $('#qv-vehicle-color-wrapper').show();
                $('#qv-vehicle-color-text').text(`Color: ${vColor}`);
                // Color swatch styling
                let swatchBg = '#64748b';
                const lowerCol = vColor.toLowerCase();
                const colorMap = {
                    'black': '#0f172a',
                    'white': '#ffffff',
                    'red': '#ef4444',
                    'blue': '#3b82f6',
                    'silver': '#cbd5e1',
                    'grey': '#64748b',
                    'gray': '#64748b',
                    'green': '#10b981',
                    'yellow': '#eab308',
                    'orange': '#f97316',
                    'brown': '#78350f',
                    'gold': '#d97706',
                    'purple': '#a855f7'
                };
                for (const [colKey, hexVal] of Object.entries(colorMap)) {
                    if (lowerCol.includes(colKey)) {
                        swatchBg = hexVal;
                        break;
                    }
                }
                $('#qv-vehicle-color-dot').css('background', swatchBg);
            } else {
                $('#qv-vehicle-color-wrapper').hide();
            }

            // Address
            $('#qv-address').html('<i class="fas fa-map-marker-alt text-danger mr-1"></i>' + $('<div>').text(b.address || 'Address not provided').html());
            const extraLoc = [b.landmark ? 'Landmark: ' + b.landmark : null, b.pincode ? 'Pincode: ' + b.pincode : null].filter(Boolean).join(' | ');
            $('#qv-landmark-pincode').text(extraLoc || '');

            const lat = parseFloat(b.latitude);
            const lng = parseFloat(b.longitude);
            if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                $('#qv-coords-text').text(`Pin: ${lat.toFixed(5)}, ${lng.toFixed(5)}`);
                $('#qv-nav-btn')
                    .attr('href', `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`)
                    .html('<i class="fas fa-directions mr-1"></i>Navigate with Google Maps')
                    .show();
                $('#qv-coords-wrapper').show();
            } else if (b.address) {
                const query = encodeURIComponent(`${b.address} ${b.pincode || ''}`.trim());
                $('#qv-coords-text').text('Address Search');
                $('#qv-nav-btn')
                    .attr('href', `https://www.google.com/maps/search/?api=1&query=${query}`)
                    .html('<i class="fas fa-map-marked-alt mr-1"></i>Search Location in Maps')
                    .show();
                $('#qv-coords-wrapper').show();
            } else {
                $('#qv-coords-wrapper').hide();
            }

            // Notes & Audits
            $('#qv-notes').text(b.notes || 'No customer notes provided.');
            $('#qv-created-at').text(b.created_at || 'N/A');
            $('#qv-started-at').text(b.started_at || 'Not started');
            $('#qv-completed-at').text(b.completed_at || 'Not completed');

            // Customer Review & Rating
            if (b.review_rating) {
                let starsHtml = '';
                const rNum = parseInt(b.review_rating, 10) || 5;
                for (let i = 1; i <= 5; i++) {
                    starsHtml += `<i class="fas fa-star ${i <= rNum ? 'text-warning' : 'text-muted'} mr-1"></i>`;
                }
                starsHtml += `<span class="ml-2 font-weight-bold text-dark">${rNum}.0 / 5.0</span>`;
                $('#qv-review-stars').html(starsHtml);
                $('#qv-review-comment').text(b.review_comment ? `"${b.review_comment}"` : 'Customer left verified rating without comment.');
                $('#qv-review-container').show();
            } else {
                $('#qv-review-container').hide();
            }

            // Contextual status action buttons inside modal footer
            let modalActionsHtml = '';
            if (st === 'pending') {
                modalActionsHtml += `<button type="button" class="btn btn-primary btn-sm font-weight-bold px-3 mr-2" style="border-radius: 8px; background:#4361ee; border-color:#4361ee;" onclick="updateBookingStatus(${b.id}, 'in_progress'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-bolt mr-1"></i> Accept & Start</button>`;
                modalActionsHtml += `<button type="button" class="btn btn-success btn-sm font-weight-bold px-3 mr-2" style="border-radius: 8px;" onclick="updateBookingStatus(${b.id}, 'accepted'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-check mr-1"></i> Accept Booking</button>`;
                modalActionsHtml += `<button type="button" class="btn btn-danger btn-sm font-weight-bold px-3" style="border-radius: 8px;" onclick="updateBookingStatus(${b.id}, 'rejected'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-times mr-1"></i> Reject Booking</button>`;
            } else if (st === 'accepted') {
                modalActionsHtml += `<button type="button" class="btn btn-primary btn-sm font-weight-bold px-3 mr-2" style="border-radius: 8px;" onclick="updateBookingStatus(${b.id}, 'in_progress'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-play mr-1"></i> Start Job</button>`;
                modalActionsHtml += `<button type="button" class="btn btn-success btn-sm font-weight-bold px-3 mr-2" style="border-radius: 8px;" onclick="updateBookingStatus(${b.id}, 'completed'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-flag-checkered mr-1"></i> Complete & Settle</button>`;
                modalActionsHtml += `<button type="button" class="btn btn-outline-danger btn-sm font-weight-bold px-3" style="border-radius: 8px;" onclick="updateBookingStatus(${b.id}, 'cancelled'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-times-circle mr-1"></i> Cancel Booking</button>`;
            } else if (st === 'in_progress') {
                modalActionsHtml += `<button type="button" class="btn btn-success btn-sm font-weight-bold px-3 mr-2" style="border-radius: 8px;" onclick="updateBookingStatus(${b.id}, 'completed'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-flag-checkered mr-1"></i> Complete & Settle</button>`;
                modalActionsHtml += `<button type="button" class="btn btn-outline-danger btn-sm font-weight-bold px-3" style="border-radius: 8px;" onclick="updateBookingStatus(${b.id}, 'cancelled'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-ban mr-1"></i> Cancel Job</button>`;
            } else if (st === 'completed' || st === 'rejected' || st === 'cancelled') {
                if (st === 'completed' && String(b.payment_status || '').toUpperCase() !== 'PAID') {
                    modalActionsHtml += `<button type="button" class="btn btn-info btn-sm font-weight-bold px-3 mr-2" style="border-radius: 8px;" onclick="showBookingQrModal(${b.id}, '${b.booking_number}', ${providerSetAmount}, '${encodeURIComponent(b.customer_name || 'Customer')}'); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-qrcode mr-1"></i> Show Payment QR</button>`;
                    modalActionsHtml += `<button type="button" class="btn btn-success btn-sm font-weight-bold px-3 mr-2" style="border-radius: 8px;" onclick="markBookingPaid(${b.id}, '${b.booking_number}', ${providerSetAmount}); $('#bookingQuickViewModal').modal('hide');"><i class="fas fa-money-bill-wave mr-1"></i> Mark Paid (Cash)</button>`;
                }
                modalActionsHtml += `<button type="button" class="btn btn-outline-danger btn-sm font-weight-bold px-3" style="border-radius: 8px;" onclick="deleteBooking(${b.id})"><i class="fas fa-trash-alt mr-1"></i> Delete Booking</button>`;
            }
            $('#qv-status-actions').html(modalActionsHtml);
        }

        async function deleteBooking(id) {
            let confirmed = false;
            if (typeof Notify !== 'undefined' && Notify.confirm) {
                confirmed = await Notify.confirm('Are you sure you want to permanently delete this booking record? This action cannot be undone.', {
                    title: 'Delete Booking',
                    type: 'danger'
                });
            } else {
                confirmed = window.confirm('Are you sure you want to permanently delete this booking record? This action cannot be undone.');
            }

            if (!confirmed) return;

            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {
                    ajax_action: 'delete_booking',
                    id: id
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        if (typeof Notify !== 'undefined' && Notify.success) {
                            Notify.success(res.message);
                        } else {
                            alert(res.message);
                        }
                        $('#bookingQuickViewModal').modal('hide');
                        loadOrders();
                        loadStats();
                    } else {
                        if (typeof Notify !== 'undefined' && Notify.error) {
                            Notify.error(res.message || 'Failed to delete booking.');
                        } else {
                            alert(res.message || 'Failed to delete booking.');
                        }
                    }
                },
                error: function () {
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error('A server or network error occurred while deleting booking.');
                    } else {
                        alert('A server or network error occurred while deleting booking.');
                    }
                }
            });
        }

        window.providerHasCompany = false;
        window.currentCompanyName = '';

        function loadProfile() {
            $.get('index.php?ajax_action=fetch_provider_profile', function (res) {
                if (!res) return;

                let companyName = (res.company_name || '').trim();
                let hasCompany = (companyName !== '');
                window.providerHasCompany = hasCompany;
                window.currentCompanyName = companyName;

                let rawName = (res.full_name || '').trim();
                let fullName = (rawName.toLowerCase() === 'service provider') ? '' : rawName;

                // ── Left card ────────────────────────────────────────────────
                if (hasCompany) {
                    $('#prof-name').text(companyName);
                    $('#prof-company-display').html('<i class="fas fa-building mr-1 text-emerald"></i>' + $('<div>').text(companyName).html());
                    $('#prof-type-badge-container').html('<span class="badge badge-info px-3 py-2 rounded-pill"><i class="fas fa-building mr-1"></i>Company Provider</span>');
                } else {
                    let displayName = fullName || 'Service Provider';
                    $('#prof-name').text(displayName);
                    $('#prof-company-display').html('<i class="fas fa-user mr-1 text-muted"></i>Individual Provider');
                    $('#prof-type-badge-container').html('<span class="badge badge-secondary px-3 py-2 rounded-pill"><i class="fas fa-user-tag mr-1"></i>Individual Provider</span>');
                }

                let statusText = (res.status || 'active').toUpperCase();
                $('#prof-status-badge').text(statusText)
                    .removeClass('badge-success badge-danger badge-secondary')
                    .addClass(res.status === 'active' ? 'badge-success' : 'badge-danger');
                $('#prof-status').text(statusText)
                    .removeClass('badge-success badge-danger badge-secondary')
                    .addClass(res.status === 'active' ? 'badge-success' : 'badge-danger');

                // ── Verified status badge ────────────────────────────────────
                let vs = res.verified_status || 'pending';
                let vsBadge = 'badge-secondary';
                let vsLabel = vs.replace('_', ' ').toUpperCase();
                if (vs === 'approved')     { vsBadge = 'badge-success'; }
                else if (vs === 'rejected') { vsBadge = 'badge-danger'; }
                else if (vs === 'under_review') { vsBadge = 'badge-warning'; }
                else { vsBadge = 'badge-secondary'; }
                $('#prof-verified-badge').text(vsLabel)
                    .removeClass('badge-success badge-danger badge-warning badge-secondary')
                    .addClass(vsBadge);

                // ── Editable account details ──────────────────────────────────
                $('#edit-full-name').val(fullName);
                $('#edit-email').val(res.email || '');
                $('#edit-phone').val(res.phone || '');

                if (hasCompany) {
                    $('#edit-company-name').val(companyName);
                    $('#company-name-row, #company-name-hr').show();
                    $('#company-register-prompt-row').hide();
                } else {
                    $('#edit-company-name').val('');
                    $('#company-name-row, #company-name-hr').hide();
                    $('#company-register-prompt-row').show();
                }

                // ── Location & range ──────────────────────────────────────────
                let finalAddr = (res.location_address && res.location_address.trim()) ? res.location_address : '';
                let finalLm   = (res.landmark && res.landmark.trim()) ? res.landmark : '';
                let finalPin  = (res.pincode && res.pincode.trim()) ? res.pincode : '';
                let finalLat  = (res.latitude && parseFloat(res.latitude) !== 0) ? res.latitude : '';
                let finalLng  = (res.longitude && parseFloat(res.longitude) !== 0) ? res.longitude : '';

                $('#edit-location-address').val(finalAddr);
                $('#edit-landmark').val(finalLm);
                $('#edit-pincode').val(finalPin);
                $('#edit-latitude').val(finalLat);
                $('#edit-longitude').val(finalLng);
                if (finalLat && finalLng) {
                    $('#lat-lng-display').text('📍 ' + parseFloat(finalLat).toFixed(5) + ', ' + parseFloat(finalLng).toFixed(5));
                } else {
                    $('#lat-lng-display').text('');
                }
                
                // Ensure profile map section is hidden by default on profile load
                $('#profileMapSection').hide();
                $('#toggleProfileMapBtn').html('<i class="fas fa-map-marked-alt mr-1"></i> Pin on Map');

                // ── Branches Card: Gate branches on company ───────────────────
                if (hasCompany) {
                    $('#branchCompanyLockAlert').hide();
                    $('#btnRegisterCompanyForBranch').hide();
                    $('#btnAddBranchBtn').show();
                    $('#branchCompanyBadge').show();
                    $('#branchCompanyNameText').text(companyName);
                } else {
                    $('#branchCompanyLockAlert').show();
                    $('#btnRegisterCompanyForBranch').show();
                    $('#btnAddBranchBtn').hide();
                    $('#branchCompanyBadge').hide();
                    $('#addBranchForm').hide();
                }

                // ── Branches ──────────────────────────────────────────────────
                window.currentProviderBranches = res.branches || [];
                renderBranches(res.branches || []);
                
                let bSelect = $('#productBranchSelect');
                bSelect.empty();
                if(window.currentProviderBranches.length > 0) {
                    $('#branchSelectionRow, #branchSelectionRowHr').show();
                    window.currentProviderBranches.forEach(b => {
                        bSelect.append(new Option(b.branch_name, b.id));
                    });
                } else {
                    $('#branchSelectionRow, #branchSelectionRowHr').hide();
                }

                // Sync profile progress banner and pillars
                if (res.progress) {
                    updateProfileUI(res.progress);
                }
            });
        }

        function saveProviderProfile() {
            let fullName = ($('#edit-full-name').val() || '').trim();
            let companyName = ($('#edit-company-name').val() || '').trim();
            let email = ($('#edit-email').val() || '').trim();
            let phone = ($('#edit-phone').val() || '').trim();
            let locationAddress = ($('#edit-location-address').val() || '').trim();
            let landmark = ($('#edit-landmark').val() || '').trim();
            let pincode = ($('#edit-pincode').val() || '').trim();
            let latitude = $('#edit-latitude').val() || '';
            let longitude = $('#edit-longitude').val() || '';

            let data = {
                ajax_action: 'save_provider_profile',
                full_name:        fullName,
                company_name:     companyName,
                email:            email,
                phone:            phone,
                location_address: locationAddress,
                landmark:         landmark,
                pincode:          pincode,
                latitude:         latitude,
                longitude:        longitude
            };

            $.post('index.php', data, function(res) {
                if (typeof res === 'string') {
                    try { res = JSON.parse(res); } catch(e) {}
                }

                if (res && res.status === 'success') {
                    let prog = res.profile_progress || res.progress;
                    if (prog) {
                        updateProfileUI(prog);
                    } else {
                        refreshProfileProgress();
                    }

                    loadProfile();

                    Notify.success(res.message || 'Profile saved successfully!');

                    // Brief visual confirmation
                    let btn = $('button[onclick="saveProviderProfile()"]');
                    btn.html('<i class="fas fa-check mr-2"></i>Saved!').css('background','#1cc88a');
                    setTimeout(function() {
                        btn.html('<i class="fas fa-save mr-2"></i>Save Profile').css('background','');
                    }, 2000);
                } else {
                    Notify.error('Error: ' + ((res && res.message) ? res.message : 'Could not save profile.'));
                }
            }, 'json').fail(function() {
                Notify.error('Server error. Please try again.');
            });
        }

        function toggleProfileMap() {
            let section = $('#profileMapSection');
            let btn = $('#toggleProfileMapBtn');
            if (section.is(':visible')) {
                section.slideUp(200);
                btn.html('<i class="fas fa-map-marked-alt mr-1"></i> Pin on Map');
            } else {
                section.slideDown(200, function() {
                    let lat = parseFloat($('#edit-latitude').val());
                    let lng = parseFloat($('#edit-longitude').val());
                    if (isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) {
                        initProfileMap(20.5937, 78.9629, 5);
                    } else {
                        initProfileMap(lat, lng, 16);
                    }
                    if (profileMap) {
                        setTimeout(() => { profileMap.invalidateSize(); }, 150);
                    }
                });
                btn.html('<i class="fas fa-eye-slash mr-1"></i> Hide Map');
            }
        }

        let profileMap = null;
        let profileMapMarker = null;
        let branchMap = null;
        let branchMapMarker = null;

        function normalizeLocationQuery(raw) {
            if (!raw) return '';
            let q = raw.trim();
            q = q.replace(/\bchinarpark\b/gi, 'chinar park')
                 .replace(/\bchinar\s*park\b/gi, 'chinar park')
                 .replace(/\bnewtown\b/gi, 'new town')
                 .replace(/\bsaltlake\b/gi, 'salt lake')
                 .replace(/\bcitycentre\b/gi, 'city centre')
                 .replace(/\bviproad\b/gi, 'vip road')
                 .replace(/\becospace\b/gi, 'eco space')
                 .replace(/\brajarhatgopalpur\b/gi, 'rajarhat gopalpur');
            return q;
        }

        function parseGoogleMapsInput(text) {
            if (!text || typeof text !== 'string') return null;
            text = text.trim();

            // 1. Direct coordinates: "22.62359, 88.44104" or "22.62359,88.44104" or "22.62359 88.44104"
            let coordMatch = text.match(/^(-?\d{1,2}\.\d+)[,\s]+(-?\d{1,3}\.\d+)$/);
            if (coordMatch) {
                const lat = parseFloat(coordMatch[1]);
                const lng = parseFloat(coordMatch[2]);
                if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    return { lat, lng };
                }
            }

            // 2. Google Maps URL @coords: e.g. https://www.google.com/maps/@22.62359,88.44104,17z or /place/.../@22.62359,88.44104
            let atMatch = text.match(/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/);
            if (atMatch) {
                const lat = parseFloat(atMatch[1]);
                const lng = parseFloat(atMatch[2]);
                if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    return { lat, lng };
                }
            }

            // 3. Query params: ?q=22.62359,88.44104 or &query=22.62359,88.44104 or ll=22.62359,88.44104
            let qMatch = text.match(/[?&](?:q|query|ll|destination|center)=(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/i);
            if (qMatch) {
                const lat = parseFloat(qMatch[1]);
                const lng = parseFloat(qMatch[2]);
                if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    return { lat, lng };
                }
            }

            return null;
        }

        function openGoogleMapsToPickLocation(targetType) {
            let lat = null;
            let lng = null;
            if (targetType === 'profile') {
                const currLat = parseFloat($('#edit-latitude').val());
                const currLng = parseFloat($('#edit-longitude').val());
                if (!isNaN(currLat) && !isNaN(currLng) && currLat !== 0) {
                    lat = currLat;
                    lng = currLng;
                }
            } else if (targetType === 'branch') {
                const currLat = parseFloat($('#branch-latitude').val());
                const currLng = parseFloat($('#branch-longitude').val());
                if (!isNaN(currLat) && !isNaN(currLng) && currLat !== 0) {
                    lat = currLat;
                    lng = currLng;
                }
            }

            const gmapsUrl = (lat && lng) ? `https://www.google.com/maps/@${lat},${lng},16z` : `https://www.google.com/maps`;
            window.open(gmapsUrl, '_blank');

            if (typeof Notify !== 'undefined' && Notify.info) {
                Notify.info('📍 On Google Maps: Right-click your location and click coordinates to copy, then paste them into the search box!', { single: true });
            }
        }

        function initProfileMap(lat, lng, zoom) {
            const hasCoords = (lat !== undefined && lng !== undefined && !isNaN(lat) && !isNaN(lng) && !(lat === 20.5937 && lng === 78.9629) && !(lat === 0 && lng === 0));
            const initialLat = (lat !== undefined && !isNaN(lat)) ? lat : 20.5937;
            const initialLng = (lng !== undefined && !isNaN(lng)) ? lng : 78.9629;
            const initialZoom = zoom || (hasCoords ? 16 : 5);

            if (!profileMap) {
                profileMap = L.map('profileMapContainer', { zoomControl: true }).setView([initialLat, initialLng], initialZoom);
                
                // Google Maps Roadmap tile layer (Crisp road network, landmarks, shops)
                const googleRoads = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
                    attribution: '© Google Maps',
                    maxZoom: 20
                });
                const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                });

                googleRoads.addTo(profileMap);
                L.control.layers({ "Google Maps": googleRoads, "OpenStreetMap": osmLayer }, null, { position: 'topright' }).addTo(profileMap);

                profileMapMarker = L.marker([initialLat, initialLng], {draggable: true}).addTo(profileMap);

                profileMapMarker.on('dragend', function (e) {
                    let pos = e.target.getLatLng();
                    updateProfileLocation(pos.lat, pos.lng);
                });

                profileMap.on('click', function(e) {
                    profileMapMarker.setLatLng(e.latlng);
                    updateProfileLocation(e.latlng.lat, e.latlng.lng);
                });
            } else {
                profileMap.setView([initialLat, initialLng], initialZoom);
                if (profileMapMarker) {
                    profileMapMarker.setLatLng([initialLat, initialLng]);
                }
            }
            setTimeout(() => { profileMap.invalidateSize(); }, 200);
        }

        function initBranchMap(lat, lng) {
            if (!branchMap) {
                branchMap = L.map('branchMapContainer', { zoomControl: true }).setView([20.5937, 78.9629], 5);
                
                const googleRoads = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
                    attribution: '© Google Maps',
                    maxZoom: 20
                });
                const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                });

                googleRoads.addTo(branchMap);
                L.control.layers({ "Google Maps": googleRoads, "OpenStreetMap": osmLayer }, null, { position: 'topright' }).addTo(branchMap);

                branchMapMarker = L.marker([20.5937, 78.9629], {draggable: true}).addTo(branchMap);

                branchMapMarker.on('dragend', function (e) {
                    let pos = e.target.getLatLng();
                    updateBranchLocation(pos.lat, pos.lng);
                });

                branchMap.on('click', function(e) {
                    branchMapMarker.setLatLng(e.latlng);
                    updateBranchLocation(e.latlng.lat, e.latlng.lng);
                });
            }

            if (lat && lng) {
                branchMap.setView([lat, lng], 15);
                branchMapMarker.setLatLng([lat, lng]);
            } else {
                branchMap.setView([20.5937, 78.9629], 5);
            }
            setTimeout(() => { branchMap.invalidateSize(); }, 300);
        }

        function formatFusionAddress(bdc, nom, lat, lng, arc) {
            const a = nom && nom.address ? nom.address : {};
            const arcAddr = (arc && arc.address) ? arc.address : (arc || {});

            // 1. Specific spot / building / apartment / POI
            let spot = arcAddr.PlaceName || '';
            if (!spot && (arcAddr.Addr_type === 'POI' || arcAddr.Addr_type === 'PointAddress')) {
                spot = arcAddr.ShortLabel || arcAddr.Address || '';
            }
            if (!spot) {
                spot = a.building || a.house_name || a.amenity || a.shop || a.office || a.historic || a.leisure || a.tourism || '';
            }
            const houseNo = a.house_number || arcAddr.AddNum || '';

            // 2. Road / Street
            let road = a.road || a.pedestrian || a.street || a.residential || '';
            if (!road && arcAddr.Address && arcAddr.Address !== spot) {
                road = arcAddr.Address;
            }
            if (road === spot) road = '';

            // 3. Lane / Gali / Sector / Colony / Block
            const sector = arcAddr.Sector || arcAddr.Neighborhood || arcAddr.Block || '';
            let laneGali = sector;
            if (laneGali === road || laneGali === spot) laneGali = '';

            // 4. Suburb / Local Area (from Nominatim or BigDataCloud locality or ArcGIS District)
            let suburb = a.suburb || a.neighbourhood || a.subdistrict || a.village || a.hamlet || (bdc && bdc.locality ? bdc.locality : '') || arcAddr.District || '';
            if (suburb === laneGali || suburb === road || suburb === spot) suburb = '';

            // 5. City / Town
            let city = (bdc && bdc.city) || a.city || a.town || a.municipality || 'Kolkata';
            if (city.toLowerCase().indexOf('greater kolkata') !== -1) {
                city = 'Kolkata';
            }
            if (city === suburb || city === laneGali) city = '';

            // 6. State
            const state = (bdc && bdc.principalSubdivision) || a.state || arcAddr.Region || 'West Bengal';

            // 7. Pincode
            let pincode = a.postcode || arcAddr.Postal || (bdc && bdc.postcode) || '';
            if (!pincode && nom && nom.display_name) {
                const m = String(nom.display_name).match(/\b([1-9][0-9]{5})\b/);
                if (m) pincode = m[1];
            }

            // Assemble comprehensive clean doorstep parts (building, houseNo, road, lane/gali, suburb, city, state)
            const rawParts = [spot, houseNo, road, laneGali, suburb, city, state].filter(function(v) {
                return v && String(v).trim().length > 0;
            });

            const uniqueParts = [];
            rawParts.forEach(function(p) {
                const s = String(p).trim();
                if (s && !uniqueParts.some(function(u) { return u.toLowerCase() === s.toLowerCase(); })) {
                    uniqueParts.push(s);
                }
            });

            let address = uniqueParts.join(', ');

            // Clean up administrative noise anywhere in the string
            address = address
                .replace(/,\s*(Greater\s*Kolkata|Barrackpur\s*[-–]\s*II|Barrackpore\s*[-–]?\s*[I|1|2|II]+|North\s*24\s*Parganas|South\s*24\s*Parganas|India)\b/gi, '')
                .replace(/\b(Greater\s*Kolkata|Barrackpur\s*[-–]\s*II|Barrackpore\s*[-–]?\s*[I|1|2|II]+|North\s*24\s*Parganas|South\s*24\s*Parganas|India),\s*/gi, '')
                .replace(/,\s*India$/gi, '')
                .replace(/\s*,\s*,+/g, ', ')
                .trim()
                .replace(/^,+|,+$/g, '')
                .trim();

            // Landmark: best candidate for recognizable local doorstep reference
            let landmark = '';
            if (spot && laneGali && spot !== laneGali) {
                landmark = `Near ${spot}, ${laneGali}`;
            } else if (spot && road && spot !== road) {
                landmark = `Near ${spot}, ${road}`;
            } else if (spot) {
                landmark = `Near ${spot}`;
            } else if (laneGali) {
                landmark = `Near ${laneGali}`;
            } else if (road && suburb && road !== suburb) {
                landmark = `Near ${road}, ${suburb}`;
            } else if (road) {
                landmark = `Near ${road}`;
            } else if (suburb) {
                landmark = `Near ${suburb}`;
            }

            return {
                address: address || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                landmark: landmark || '',
                pincode: pincode || '',
                city: city || 'Kolkata',
                state: state || 'West Bengal'
            };
        }

        function formatDoorstepAddress(data) {
            return formatFusionAddress(null, data, parseFloat($('#edit-latitude').val()) || 0, parseFloat($('#edit-longitude').val()) || 0);
        }

        function reverseGeocodeCoords(lat, lng, callback) {
            let bdcData = null;
            let nomData = null;
            let arcData = null;
            let done = false;

            function finish() {
                if (done) return;
                done = true;
                callback(formatFusionAddress(bdcData, nomData, lat, lng, arcData));
            }

            // 1. Query ArcGIS World Geocoder (Granular building, sector, gali, lane, road, pincode)
            $.ajax({
                url: `https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/reverseGeocode?location=${lng},${lat}&f=pjson`,
                dataType: 'json',
                timeout: 4500,
                success: function(arc) {
                    arcData = (arc && arc.address) ? arc.address : arc;
                    if (nomData && bdcData) finish();
                },
                error: function() {
                    if (nomData && bdcData) finish();
                }
            });

            // 2. Query BigDataCloud Client Reverse Geocoder
            $.ajax({
                url: `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`,
                dataType: 'json',
                timeout: 4500,
                success: function(bdc) {
                    bdcData = bdc;
                    if (arcData && nomData) finish();
                },
                error: function() {
                    if (arcData && nomData) finish();
                }
            });

            // 3. Query OSM Nominatim for granular doorstep details
            $.ajax({
                url: `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`,
                headers: { 'Accept-Language': 'en' },
                dataType: 'json',
                timeout: 4500,
                success: function(nom) {
                    nomData = nom;
                    finish();
                },
                error: function() {
                    if (arcData || bdcData) finish();
                }
            });

            // Guarantee execution even if slow network
            setTimeout(() => { finish(); }, 4500);
        }

        function updateProfileLocation(lat, lng) {
            $('#edit-latitude').val(lat.toFixed(7));
            $('#edit-longitude').val(lng.toFixed(7));
            $('#lat-lng-display').text('📍 ' + lat.toFixed(7) + ', ' + lng.toFixed(7));
            
            reverseGeocodeCoords(lat, lng, function(res) {
                if (res.address) $('#edit-location-address').val(res.address);
                if (res.landmark) $('#edit-landmark').val(res.landmark);
                if (res.pincode) $('#edit-pincode').val(res.pincode);
                if (profileMapMarker) {
                    profileMapMarker.bindPopup(`<b>📍 ${res.landmark || 'Pinned Location'}</b><br><small>${res.address}</small>`).openPopup();
                }
            });
        }

        function updateBranchLocation(lat, lng) {
            $('#branch-latitude').val(lat.toFixed(7));
            $('#branch-longitude').val(lng.toFixed(7));
            
            reverseGeocodeCoords(lat, lng, function(res) {
                if (res.address) $('#branch-address').val(res.address);
                if (res.pincode) $('#branch-pincode').val(res.pincode);
                if (branchMapMarker) {
                    branchMapMarker.bindPopup(`<b>📍 ${res.landmark || 'Branch Location'}</b><br><small>${res.address}</small>`).openPopup();
                }
            });
        }

        function applyCoordsToProfile(lat, lng, label) {
            if (!profileMap) {
                initProfileMap(lat, lng);
            } else {
                profileMap.setView([lat, lng], 17);
                if (profileMapMarker) {
                    profileMapMarker.setLatLng([lat, lng]);
                }
                setTimeout(() => { profileMap.invalidateSize(); }, 200);
            }

            $('#edit-latitude').val(lat.toFixed(7));
            $('#edit-longitude').val(lng.toFixed(7));
            $('#lat-lng-display').text('📍 ' + lat.toFixed(7) + ', ' + lng.toFixed(7));

            reverseGeocodeCoords(lat, lng, function(res) {
                if (res.address) $('#edit-location-address').val(res.address);
                if (res.landmark) $('#edit-landmark').val(res.landmark);
                if (res.pincode) $('#edit-pincode').val(res.pincode);
                if (profileMapMarker) {
                    profileMapMarker.bindPopup(`<b>📍 ${label || res.landmark || 'Pinned Location'}</b><br><small>${res.address}</small>`).openPopup();
                }
            });

            if (typeof Notify !== 'undefined' && Notify.success) {
                Notify.success(`📍 Location set: ${lat.toFixed(5)}, ${lng.toFixed(5)}!`, { single: true });
            }
        }

        function applyCoordsToBranch(lat, lng, label) {
            if (!branchMap) {
                initBranchMap(lat, lng);
            } else {
                branchMap.setView([lat, lng], 17);
                if (branchMapMarker) {
                    branchMapMarker.setLatLng([lat, lng]);
                }
                setTimeout(() => { branchMap.invalidateSize(); }, 200);
            }

            $('#branch-latitude').val(lat.toFixed(7));
            $('#branch-longitude').val(lng.toFixed(7));

            reverseGeocodeCoords(lat, lng, function(res) {
                if (res.address) $('#branch-address').val(res.address);
                if (res.pincode) $('#branch-pincode').val(res.pincode);
                if (branchMapMarker) {
                    branchMapMarker.bindPopup(`<b>📍 ${label || res.landmark || 'Branch Location'}</b><br><small>${res.address}</small>`).openPopup();
                }
            });

            if (typeof Notify !== 'undefined' && Notify.success) {
                Notify.success(`📍 Branch location set: ${lat.toFixed(5)}, ${lng.toFixed(5)}!`, { single: true });
            }
        }

        function geocodeAndPinAddress(rawQuery) {
            if (!rawQuery || !rawQuery.trim()) return;
            if (!$('#profileMapSection').is(':visible')) {
                toggleProfileMap();
            }
            const query = normalizeLocationQuery(rawQuery.trim());
            const parsed = parseGoogleMapsInput(query);
            if (parsed) {
                applyCoordsToProfile(parsed.lat, parsed.lng, 'Pinned Location');
                return;
            }
            $.ajax({
                url: `https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&countrycodes=in&q=${encodeURIComponent(query)}`,
                headers: { 'Accept-Language': 'en' },
                dataType: 'json',
                success: function(results) {
                    if (results && results.length > 0) {
                        applySearchResultToProfile(results[0]);
                    }
                }
            });
        }

        function searchProfileMapLocation() {
            const input = $('#profile-map-search-input');
            const rawQuery = input.val().trim();
            const query = normalizeLocationQuery(rawQuery);
            const btn = $('#btn-search-profile-map');
            const origHtml = btn.length ? btn.html() : '';

            if (!rawQuery) {
                if (typeof Notify !== 'undefined' && Notify.warning) {
                    Notify.warning('Please enter an area, landmark or paste Google Maps link/coords.', { single: true });
                } else {
                    alert('Please enter an area, landmark or paste Google Maps link/coords.');
                }
                input.focus();
                return;
            }

            // Check if user pasted direct coordinates or a Google Maps URL
            const parsed = parseGoogleMapsInput(rawQuery);
            if (parsed) {
                applyCoordsToProfile(parsed.lat, parsed.lng, 'Google Maps Pinned Location');
                return;
            }

            if (btn.length) {
                btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Searching...').prop('disabled', true);
            }

            // Universal India-wide search without hardcoded viewbox or regional restriction
            const searchUrlIndia = `https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&countrycodes=in&q=${encodeURIComponent(query)}`;

            $.ajax({
                url: searchUrlIndia,
                headers: { 'Accept-Language': 'en' },
                dataType: 'json',
                success: function(results) {
                    if (results && results.length > 0) {
                        if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                        applySearchResultToProfile(results[0]);
                        return;
                    }
                    // Global fallback search
                    $.ajax({
                        url: `https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&q=${encodeURIComponent(query)}`,
                        headers: { 'Accept-Language': 'en' },
                        dataType: 'json',
                        success: function(fbResults) {
                            if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                            if (!fbResults || fbResults.length === 0) {
                                if (typeof Notify !== 'undefined' && Notify.error) {
                                    Notify.error(`Location "${rawQuery}" not found. Try pasting Google Maps link or coordinates.`, { single: true });
                                } else {
                                    alert(`Location "${rawQuery}" not found on map.`);
                                }
                                return;
                            }
                            applySearchResultToProfile(fbResults[0]);
                        },
                        error: function() {
                            if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                            if (typeof Notify !== 'undefined' && Notify.error) {
                                Notify.error('Error connecting to map search service.', { single: true });
                            }
                        }
                    });
                },
                error: function() {
                    if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error('Error connecting to map search service.', { single: true });
                    }
                }
            });
        }

        function applySearchResultToProfile(place) {
            const lat = parseFloat(place.lat);
            const lng = parseFloat(place.lon);

            if (!profileMap) {
                initProfileMap(lat, lng);
            } else {
                profileMap.setView([lat, lng], 16);
                if (profileMapMarker) {
                    profileMapMarker.setLatLng([lat, lng]);
                }
                setTimeout(() => { profileMap.invalidateSize(); }, 200);
            }

            $('#edit-latitude').val(lat.toFixed(7));
            $('#edit-longitude').val(lng.toFixed(7));
            $('#lat-lng-display').text('📍 ' + lat.toFixed(7) + ', ' + lng.toFixed(7));

            const addr = place.display_name || '';
            const a = place.address || {};
            const pin = a.postcode || '';
            const landmark = place.name || a.suburb || a.neighbourhood || a.road || '';

            $('#edit-location-address').val(addr);
            if (pin) $('#edit-pincode').val(pin);
            if (landmark && !$('#edit-landmark').val()) $('#edit-landmark').val(landmark);

            if (profileMapMarker) {
                profileMapMarker.bindPopup(`<b>📍 ${place.name || 'Pinned Location'}</b><br><small>${addr}</small>`).openPopup();
            }

            // Exactly ONE notification
            if (typeof Notify !== 'undefined' && Notify.success) {
                Notify.success(`📍 Location set: ${place.name || addr.split(',')[0]}!`, { single: true });
            }
        }

        function searchBranchMapLocation() {
            const input = $('#branch-map-search-input');
            const rawQuery = input.val().trim();
            const query = normalizeLocationQuery(rawQuery);
            const btn = $('#btn-search-branch-map');
            const origHtml = btn.length ? btn.html() : '';

            if (!rawQuery) {
                if (typeof Notify !== 'undefined' && Notify.warning) {
                    Notify.warning('Please enter a branch area, landmark or paste Google Maps link/coords.', { single: true });
                }
                input.focus();
                return;
            }

            const parsed = parseGoogleMapsInput(rawQuery);
            if (parsed) {
                applyCoordsToBranch(parsed.lat, parsed.lng, 'Google Maps Branch Location');
                return;
            }

            if (btn.length) {
                btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Searching...').prop('disabled', true);
            }

            const searchUrlIndia = `https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&countrycodes=in&q=${encodeURIComponent(query)}`;

            $.ajax({
                url: searchUrlIndia,
                headers: { 'Accept-Language': 'en' },
                dataType: 'json',
                success: function(results) {
                    if (results && results.length > 0) {
                        if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                        applySearchResultToBranch(results[0]);
                        return;
                    }
                    $.ajax({
                        url: `https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&q=${encodeURIComponent(query)}`,
                        headers: { 'Accept-Language': 'en' },
                        dataType: 'json',
                        success: function(fbResults) {
                            if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                            if (fbResults && fbResults.length > 0) {
                                applySearchResultToBranch(fbResults[0]);
                            } else {
                                if (typeof Notify !== 'undefined' && Notify.error) {
                                    Notify.error(`Location "${rawQuery}" not found. Try pasting Google Maps link or coordinates.`, { single: true });
                                }
                            }
                        },
                        error: function() {
                            if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                            if (typeof Notify !== 'undefined' && Notify.error) {
                                Notify.error('Error connecting to map search service.', { single: true });
                            }
                        }
                    });
                },
                error: function() {
                    if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error('Error connecting to map search service.', { single: true });
                    }
                }
            });
        }

        function applySearchResultToBranch(place) {
            const lat = parseFloat(place.lat);
            const lng = parseFloat(place.lon);

            if (!branchMap) {
                initBranchMap(lat, lng);
            } else {
                branchMap.setView([lat, lng], 16);
                if (branchMapMarker) {
                    branchMapMarker.setLatLng([lat, lng]);
                }
                setTimeout(() => { branchMap.invalidateSize(); }, 200);
            }

            $('#branch-latitude').val(lat.toFixed(7));
            $('#branch-longitude').val(lng.toFixed(7));

            const addr = place.display_name || '';
            const a = place.address || {};
            const pin = a.postcode || '';
            const landmark = place.name || a.suburb || a.neighbourhood || a.road || '';

            $('#branch-address').val(addr);
            if (pin) $('#branch-pincode').val(pin);
            if (landmark && !$('#branch-landmark').val()) $('#branch-landmark').val(landmark);

            if (branchMapMarker) {
                branchMapMarker.bindPopup(`<b>📍 ${place.name || 'Branch Location'}</b><br><small>${addr}</small>`).openPopup();
            }

            // Exactly ONE notification
            if (typeof Notify !== 'undefined' && Notify.success) {
                Notify.success(`📍 Branch location set: ${place.name || addr.split(',')[0]}!`, { single: true });
            }
        }

        function useProfileLiveLocation() {
            const btn = $('#btn-use-live-gps');
            const origHtml = btn.length ? btn.html() : '';
            if (btn.length) {
                btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Locating...').prop('disabled', true);
            }

            function fallbackToBdcClientGeo() {
                $.ajax({
                    url: 'https://api.bigdatacloud.net/data/reverse-geocode-client?localityLanguage=en',
                    dataType: 'json',
                    timeout: 5000,
                    success: function(bdc) {
                        if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                        let lat = parseFloat(bdc.latitude) || 22.6100;
                        let lng = parseFloat(bdc.longitude) || 88.4400;

                        if (!profileMap) {
                            initProfileMap(lat, lng, 16);
                        } else {
                            profileMap.setView([lat, lng], 16);
                            if (profileMapMarker) {
                                profileMapMarker.setLatLng([lat, lng]);
                            }
                            setTimeout(() => { profileMap.invalidateSize(); }, 200);
                        }

                        $('#edit-latitude').val(lat.toFixed(7));
                        $('#edit-longitude').val(lng.toFixed(7));
                        $('#lat-lng-display').text('📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5));
                        updateProfileLocation(lat, lng);

                        if (typeof Notify !== 'undefined' && Notify.success) {
                            Notify.success('📍 Live network location detected and set!', { single: true });
                        }
                    },
                    error: function() {
                        if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                        if (typeof Notify !== 'undefined' && Notify.error) {
                            Notify.error('Could not access device location. Please search your area or paste Google Maps coordinates.', { single: true });
                        }
                    }
                });
            }

            if (!navigator.geolocation) {
                fallbackToBdcClientGeo();
                return;
            }

            navigator.geolocation.getCurrentPosition(function(pos) {
                if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                let lat = pos.coords.latitude;
                let lng = pos.coords.longitude;

                if (!profileMap) {
                    initProfileMap(lat, lng, 16);
                } else {
                    profileMap.setView([lat, lng], 16);
                    if (profileMapMarker) {
                        profileMapMarker.setLatLng([lat, lng]);
                    }
                    setTimeout(() => { profileMap.invalidateSize(); }, 200);
                }

                $('#edit-latitude').val(lat.toFixed(7));
                $('#edit-longitude').val(lng.toFixed(7));
                $('#lat-lng-display').text('📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5));
                updateProfileLocation(lat, lng);

                if (typeof Notify !== 'undefined' && Notify.success) {
                    Notify.success('📍 Live location detected and set!', { single: true });
                }
            }, function(err) {
                // If browser GPS fails or is denied, smoothly fallback to BigDataCloud
                fallbackToBdcClientGeo();
            }, { enableHighAccuracy: true, timeout: 6000, maximumAge: 10000 });
        }

        function useBranchLiveLocation() {
            const btn = $('#btn-use-branch-live-gps');
            const origHtml = btn.length ? btn.html() : '';
            if (btn.length) {
                btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Locating...').prop('disabled', true);
            }

            function fallbackBranchBdcClientGeo() {
                $.ajax({
                    url: 'https://api.bigdatacloud.net/data/reverse-geocode-client?localityLanguage=en',
                    dataType: 'json',
                    timeout: 5000,
                    success: function(bdc) {
                        if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                        let lat = parseFloat(bdc.latitude) || 22.6100;
                        let lng = parseFloat(bdc.longitude) || 88.4400;

                        if (!branchMap) {
                            initBranchMap(lat, lng);
                        } else {
                            branchMap.setView([lat, lng], 16);
                            if (branchMapMarker) {
                                branchMapMarker.setLatLng([lat, lng]);
                            }
                            setTimeout(() => { branchMap.invalidateSize(); }, 200);
                        }

                        $('#branch-latitude').val(lat.toFixed(7));
                        $('#branch-longitude').val(lng.toFixed(7));
                        updateBranchLocation(lat, lng);

                        if (typeof Notify !== 'undefined' && Notify.success) {
                            Notify.success('📍 Branch live network location set successfully!', { single: true });
                        }
                    },
                    error: function() {
                        if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                        if (typeof Notify !== 'undefined' && Notify.error) {
                            Notify.error('Could not access device location. Please search your area or paste Google Maps coordinates.', { single: true });
                        }
                    }
                });
            }

            if (!navigator.geolocation) {
                fallbackBranchBdcClientGeo();
                return;
            }

            navigator.geolocation.getCurrentPosition(function(pos) {
                if (btn.length) { btn.html(origHtml).prop('disabled', false); }
                let lat = pos.coords.latitude;
                let lng = pos.coords.longitude;

                if (!branchMap) {
                    initBranchMap(lat, lng);
                } else {
                    branchMap.setView([lat, lng], 16);
                    if (branchMapMarker) {
                        branchMapMarker.setLatLng([lat, lng]);
                    }
                    setTimeout(() => { branchMap.invalidateSize(); }, 200);
                }

                $('#branch-latitude').val(lat.toFixed(7));
                $('#branch-longitude').val(lng.toFixed(7));
                updateBranchLocation(lat, lng);

                if (typeof Notify !== 'undefined' && Notify.success) {
                    Notify.success('📍 Branch live location set successfully!', { single: true });
                }
            }, function(err) {
                fallbackBranchBdcClientGeo();
            }, { enableHighAccuracy: true, timeout: 6000, maximumAge: 10000 });
        }

        let postCompanyRegisterAction = null;

        function openRegisterCompanyModal(actionAfter) {
            postCompanyRegisterAction = actionAfter || null;
            let currentVal = $('#edit-company-name').val() || window.currentCompanyName || '';
            $('#reg-company-name-input').val(currentVal);
            $('#registerCompanyModal').modal('show');
            setTimeout(function() {
                $('#reg-company-name-input').focus();
            }, 400);
        }

        function submitRegisterCompany(e) {
            if (e) e.preventDefault();
            let companyName = $('#reg-company-name-input').val().trim();
            if (!companyName) {
                Notify.warning('Please enter a company name.');
                return;
            }
            let btn = $('#btnSubmitCompanyReg');
            let origHtml = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Registering...').prop('disabled', true);

            $.post('index.php', {
                ajax_action: 'register_company',
                company_name: companyName
            }, function(res) {
                btn.html(origHtml).prop('disabled', false);
                if (res.status === 'success') {
                    $('#registerCompanyModal').modal('hide');
                    Notify.success(res.message || 'Company registered successfully!');
                    window.providerHasCompany = true;
                    window.currentCompanyName = companyName;
                    loadProfile();
                    if (postCompanyRegisterAction === 'open_add_branch') {
                        setTimeout(function() {
                            toggleAddBranch();
                        }, 500);
                    }
                } else {
                    Notify.error('Error: ' + (res.message || 'Could not register company.'));
                }
            }, 'json').fail(function() {
                btn.html(origHtml).prop('disabled', false);
                Notify.error('Server error. Please try again.');
            });
        }

        function toggleAddBranch() {
            if (!window.providerHasCompany) {
                Notify.warning('You must register a company before adding branch locations.');
                openRegisterCompanyModal('open_add_branch');
                return;
            }
            let form = $('#addBranchForm');
            if (form.is(':visible')) {
                form.slideUp(200);
                // Clear fields
                $('#branch-name, #branch-address, #branch-landmark, #branch-phone, #branch-pincode').val('');
                $('#branch-latitude, #branch-longitude').val('');
            } else {
                form.slideDown(200, function() {
                    initBranchMap();
                });
                $('#branch-name').focus();
            }
        }

        function submitAddBranch() {
            if (!window.providerHasCompany) {
                Notify.warning('You must register a company before adding branch locations.');
                openRegisterCompanyModal('open_add_branch');
                return;
            }
            let branchName = $('#branch-name').val().trim();
            if (!branchName) {
                Notify.warning('Branch name is required.');
                return;
            }
            $.post('index.php', {
                ajax_action:  'add_branch',
                branch_name:  branchName,
                address:      $('#branch-address').val().trim(),
                phone:        $('#branch-phone').val().trim(),
                latitude:     $('#branch-latitude').val(),
                longitude:    $('#branch-longitude').val()
            }, function(res) {
                if (res.status === 'success') {
                    toggleAddBranch();
                    loadProfile(); // reload branches
                    Notify.success(res.message || 'Branch added successfully!');
                } else {
                    Notify.error('Error: ' + (res.message || 'Could not add branch.'));
                    if (res.requires_company) {
                        openRegisterCompanyModal('open_add_branch');
                    }
                }
            }, 'json').fail(function() {
                Notify.error('Server error. Could not add branch.');
            });
        }

        async function deleteBranch(id) {
            const confirmed = await Notify.confirm('Delete this branch?', { type: 'danger', title: 'Confirm Delete' });
            if (!confirmed) return;
            $.post('index.php', {
                ajax_action: 'delete_branch',
                branch_id: id
            }, function(res) {
                if (res.status === 'success') {
                    loadProfile();
                } else {
                    Notify.error('Error: ' + (res.message || 'Could not delete branch.'));
                }
            });
        }

        function renderBranches(branches) {
            let html = '';
            if (branches.length === 0) {
                html = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-code-branch mr-2"></i>No branches added yet.</td></tr>';
            } else {
                branches.forEach(function(b) {
                    let coords = (b.latitude && b.longitude)
                        ? `<small class="text-muted">${parseFloat(b.latitude).toFixed(4)}, ${parseFloat(b.longitude).toFixed(4)}</small>`
                        : '<span class="text-muted small">N/A</span>';
                    html += `<tr>
                        <td><span class="id-badge">#${b.id}</span></td>
                        <td><div class="font-weight-bold">${b.branch_name}</div></td>
                        <td>${b.address || '<span class="text-muted small">N/A</span>'}</td>
                        <td>${b.phone || '<span class="text-muted small">N/A</span>'}</td>
                        <td>${coords}</td>
                        <td>
                            <button class="btn btn-danger btn-xs py-1 px-2 font-weight-bold" style="font-size:0.7rem; border-radius:4px;" onclick="deleteBranch(${b.id})">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#branchesList').html(html);
        }

        // Logout Confirmation Alert / Modal Handler
        function confirmLogout(event) {
            if (event) event.preventDefault();
            if (typeof $ !== 'undefined' && $('#logoutModal').length) {
                $('#logoutModal').modal('show');
            } else if (typeof Notify !== 'undefined' && Notify.confirm) {
                Notify.confirm('Are you sure you want to end your session and log out of the Provider Portal?', {
                    title: 'Confirm Logout',
                    confirmText: 'Yes, Logout',
                    cancelText: 'Stay Logged In',
                    type: 'danger'
                }).then(function(confirmed) {
                    if (confirmed) {
                        window.location.href = 'logout.php';
                    }
                });
            } else if (window.confirm('Are you sure you want to end your session and log out?')) {
                window.location.href = 'logout.php';
            }
        }

    </script>


    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        /* ==========================================================
           Service Provider Wallet & Financial Management System
           ========================================================== */
        // globalWalletData declared at top

        function loadWallet() {
            $('#walletTxnsList').html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Loading transactions...</td></tr>');
            $('#walletPayoutsList').html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i> Loading payout requests...</td></tr>');

            $.get('index.php?ajax_action=fetch_wallet_data', function(res) {
                if (res.status === 'success') {
                    globalWalletData = res;
                    renderWalletData(res);
                } else {
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(res.message || 'Failed to load wallet data.');
                    } else {
                        alert(res.message || 'Failed to load wallet data.');
                    }
                }
            }, 'json').fail(function() {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Error connecting to wallet service.');
                }
            });
        }

        function renderWalletData(data) {
            const wallet = data.wallet || {};
            const bank = data.bank || {};
            const txns = data.transactions || [];
            const payouts = data.payouts || [];

            // 1. Update KPI numbers
            const avail = parseFloat(wallet.available_balance || 0);
            const pending = parseFloat(wallet.pending_clearance || 0);
            const earned = parseFloat(wallet.total_earned || 0);
            const withdrawn = parseFloat(wallet.total_withdrawn || 0);

            $('#wallet-available').text(avail.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#wallet-pending').text(pending.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#wallet-earned').text(earned.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#wallet-withdrawn').text(withdrawn.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#modal-available-balance').text('₹' + avail.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

            // Update Dynamic Platform Fee column header & KPI note
            const feePercent = data.platform_fee_percent !== undefined ? parseFloat(data.platform_fee_percent) : 10;
            const feeType = data.platform_fee_type || 'percentage';
            const feeAmount = parseFloat(data.platform_fee_amount || 0);
            const feeFormattedPct = (Number.isInteger(feePercent) ? feePercent.toString() : feePercent.toFixed(2).replace(/\.?0+$/, '')) + '%';
            const feeLabel = (feeType === 'percentage') ? feeFormattedPct : ('₹' + feeAmount.toFixed(2));

            $('#th-wallet-platform-fee').text('Platform Fee (' + feeLabel + ')');
            $('#wallet-fee-note').text('After ' + feeLabel + ' Platform Fee');

            // 2. Update Bank Account Card
            if (bank && bank.account_number) {
                const maskedAcc = '•••• •••• ' + bank.account_number.slice(-4);
                $('#wallet-bank-name').text(bank.bank_name ? (bank.bank_name + ' (' + bank.account_holder_name + ')') : bank.account_holder_name);
                $('#wallet-bank-badge').removeClass('badge-secondary badge-danger').addClass('badge-success').text(bank.is_verified ? 'Verified & Active' : 'Active');
                $('#wallet-bank-details').html(`<i class="fas fa-credit-card mr-1 text-muted"></i> A/C: <strong>${maskedAcc}</strong> &nbsp;|&nbsp; <i class="fas fa-code-branch mr-1 text-muted"></i> IFSC: <strong>${bank.ifsc_code}</strong>`);
                $('#btnEditBankText').text('Edit Bank Account');
                $('#modal-dest-bank-label').text(`${bank.bank_name || 'Bank'} (${maskedAcc})`);
            } else {
                $('#wallet-bank-name').text('No Bank Account Linked');
                $('#wallet-bank-badge').removeClass('badge-success').addClass('badge-secondary').text('Not Configured');
                $('#wallet-bank-details').text('Please link your bank account details for direct bank transfers.');
                $('#btnEditBankText').text('Link Bank Account');
                $('#modal-dest-bank-label').text('No Bank Account Linked');
            }

            // 2b. Update UPI ID Card
            if (bank && bank.upi_id) {
                $('#wallet-upi-title').text(bank.upi_id);
                $('#wallet-upi-badge').removeClass('badge-secondary badge-danger').addClass('badge-success').text('Active');
                $('#wallet-upi-details').html(`<i class="fas fa-check-circle mr-1 text-success"></i> Instant UPI payouts enabled for <strong>${bank.upi_id}</strong>`);
                $('#btnEditUpiText').text('Edit UPI ID');
                $('#modal-dest-upi-label').text(`UPI ID (${bank.upi_id})`);
            } else {
                $('#wallet-upi-title').text('No UPI ID Linked');
                $('#wallet-upi-badge').removeClass('badge-success').addClass('badge-secondary').text('Not Configured');
                $('#wallet-upi-details').text('Please link your UPI ID for instant 24/7 payout transfers.');
                $('#btnEditUpiText').text('Link UPI ID');
                $('#modal-dest-upi-label').text('UPI ID (Not configured)');
            }

            // 3. Render Transactions
            $('#count-txns').text(txns.length);
            if (txns.length === 0) {
                $('#walletTxnsList').html('<tr><td colspan="9" class="text-center py-5 text-muted"><i class="fas fa-receipt fa-2x mb-2 d-block text-gray-300"></i>No payment transactions found. Transactions will appear here once customers book services.</td></tr>');
            } else {
                let txHtml = '';
                txns.forEach(t => {
                    const gross = parseFloat(t.gross_amount || 0).toFixed(2);
                    const fee = parseFloat(t.platform_commission || 0).toFixed(2);
                    const net = parseFloat(t.provider_net_amount || 0).toFixed(2);
                    const bookingNo = t.booking_number || ('#' + t.booking_id);
                    const sName = t.service_name || 'Vehicle Service';
                    const cName = t.customer_name || 'Customer';

                    let statusBadge = '<span class="badge badge-warning px-2 py-1">INITIATED</span>';
                    if (t.status === 'SUCCESS') {
                        statusBadge = '<span class="badge badge-success px-2 py-1" style="background: #10b981; color: #fff;"><i class="fas fa-check-circle mr-1"></i>SUCCESS</span>';
                    } else if (t.status === 'HOLD') {
                        statusBadge = '<span class="badge badge-info px-2 py-1"><i class="fas fa-shield-alt mr-1"></i>IN ESCROW</span>';
                    } else if (t.status === 'FAILED') {
                        statusBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>FAILED</span>';
                    } else if (t.status === 'REFUNDED') {
                        statusBadge = '<span class="badge badge-secondary px-2 py-1">REFUNDED</span>';
                    }

                    const dateStr = t.created_at ? new Date(t.created_at.replace(' ', 'T')).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '-';

                    txHtml += `
                        <tr>
                            <td><strong class="text-gray-800" style="font-family: monospace;">${t.transaction_ref}</strong></td>
                            <td>
                                <div class="font-weight-bold text-gray-800">${bookingNo}</div>
                                <small class="text-muted">${sName}</small>
                            </td>
                            <td>
                                <div class="font-weight-bold text-gray-800">${cName}</div>
                                <small class="text-muted">${t.customer_phone || ''}</small>
                            </td>
                            <td><span class="font-weight-bold text-gray-800">₹${gross}</span></td>
                            <td><span class="text-danger small font-weight-bold">-₹${fee}</span></td>
                            <td><strong class="text-emerald" style="color: #05C279; font-size: 0.95rem;">+₹${net}</strong></td>
                            <td><span class="badge badge-light border"><i class="fas fa-credit-card mr-1 text-primary"></i>${t.payment_gateway || 'RAZORPAY'}</span></td>
                            <td>${statusBadge}</td>
                            <td><small class="text-muted font-weight-bold">${dateStr}</small></td>
                        </tr>
                    `;
                });
                $('#walletTxnsList').html(txHtml);
            }

            // 4. Render Payout Requests
            $('#count-payouts').text(payouts.length);
            if (payouts.length === 0) {
                $('#walletPayoutsList').html('<tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-paper-plane fa-2x mb-2 d-block text-gray-300"></i>No payout requests yet. Click "Withdraw Funds" to request a transfer to your bank.</td></tr>');
            } else {
                let poHtml = '';
                payouts.forEach(p => {
                    const amt = parseFloat(p.amount || 0).toFixed(2);
                    const st = (p.status || 'PENDING').toUpperCase();
                    let stBadge = '<span class="badge badge-warning px-2 py-1"><i class="fas fa-clock mr-1"></i>PENDING</span>';
                    if (st === 'PROCESSED' || st === 'COMPLETED' || st === 'APPROVED') {
                        stBadge = '<span class="badge badge-success px-2 py-1" style="background: #10b981; color: #fff;"><i class="fas fa-check-circle mr-1"></i>' + st + '</span>';
                    } else if (st === 'PROCESSING') {
                        stBadge = '<span class="badge badge-primary px-2 py-1"><i class="fas fa-spinner fa-spin mr-1"></i>PROCESSING</span>';
                    } else if (st === 'REJECTED') {
                        stBadge = '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times mr-1"></i>REJECTED</span>';
                    }

                    const reqDate = p.created_at ? new Date(p.created_at.replace(' ', 'T')).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
                    const procDate = p.processed_at ? new Date(p.processed_at.replace(' ', 'T')).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '<span class="text-muted">Pending</span>';

                    poHtml += `
                        <tr>
                            <td><strong class="text-gray-800" style="font-family: monospace;">${p.payout_ref}</strong></td>
                            <td><strong class="text-dark" style="font-size: 1rem;">₹${amt}</strong></td>
                            <td>${stBadge}</td>
                            <td>${p.utr_number ? `<strong class="text-success font-monospace">${p.utr_number}</strong>` : '<span class="text-muted small">Awaiting Admin UTR</span>'}</td>
                            <td><small class="text-muted">${p.admin_notes || 'Withdrawal request'}</small></td>
                            <td><small class="text-muted font-weight-bold">${reqDate}</small></td>
                            <td><small class="font-weight-bold">${procDate}</small></td>
                        </tr>
                    `;
                });
                $('#walletPayoutsList').html(poHtml);
            }
        }

        function switchWalletView(view) {
            if (view === 'txns') {
                $('#btnShowTxns').addClass('active btn-light').removeClass('btn-white');
                $('#btnShowPayouts').removeClass('active');
                $('#walletTxnsContainer').show();
                $('#walletPayoutsContainer').hide();
            } else {
                $('#btnShowPayouts').addClass('active btn-light').removeClass('btn-white');
                $('#btnShowTxns').removeClass('active');
                $('#walletPayoutsContainer').show();
                $('#walletTxnsContainer').hide();
            }
        }

        function filterWalletTable() {
            const query = ($('#walletSearchInput').val() || '').toLowerCase();
            $('#walletTxnsList tr').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) > -1);
            });
            $('#walletPayoutsList tr').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) > -1);
            });
        }

        function checkWithdrawalEligibility(showAlert) {
            if (typeof showAlert === 'undefined') showAlert = true;

            if (!window.globalWalletData) {
                if (showAlert) {
                    if (typeof Notify !== 'undefined' && Notify.warning) {
                        Notify.warning('Wallet information is synchronizing. Please wait a moment and try again.', { title: 'Synchronizing' });
                    } else {
                        alert('Wallet information is synchronizing. Please wait a moment and try again.');
                    }
                }
                return { eligible: false, reason: 'loading' };
            }

            var wallet = window.globalWalletData.wallet || {};
            var bank = window.globalWalletData.bank || {};
            var avail = parseFloat(wallet.available_balance || 0);

            // Condition 1: Linked settlement details (Bank Account or UPI)
            var hasBank = Boolean(bank.account_number && bank.account_number.toString().trim() !== '');
            var hasUpi = Boolean(bank.upi_id && bank.upi_id.toString().trim() !== '');

            if (!hasBank && !hasUpi) {
                if (showAlert) {
                    if (typeof Notify !== 'undefined' && Notify.warning) {
                        Notify.warning('Please link and verify your Bank Account or UPI details first before requesting a withdrawal. We have opened the settlement form for you.', { title: 'Bank Account Required' });
                    } else {
                        alert('Please link and verify your Bank Account or UPI details first before requesting a withdrawal. We have opened the settlement form for you.');
                    }
                    setTimeout(function() {
                        openBankDetailsModal('bank');
                    }, 300);
                }
                return { eligible: false, reason: 'no_bank' };
            }

            // Condition 2: Zero or negative balance
            if (avail <= 0) {
                if (showAlert) {
                    if (typeof Notify !== 'undefined' && Notify.warning) {
                        Notify.warning('Withdrawal condition not met: Your available wallet balance is ₹0.00. Earnings will unlock automatically once active bookings/orders are marked completed.', { title: 'Zero Balance' });
                    } else {
                        alert('Withdrawal condition not met: Your available wallet balance is ₹0.00. Earnings will unlock automatically once active bookings/orders are marked completed.');
                    }
                }
                return { eligible: false, reason: 'zero_balance' };
            }

            // Condition 3: Minimum threshold limit (₹100.00)
            if (avail < 100) {
                var balFmt = '₹' + avail.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (showAlert) {
                    if (typeof Notify !== 'undefined' && Notify.warning) {
                        Notify.warning('Withdrawal condition not met: Minimum withdrawal threshold is ₹100.00. Your current available balance is ' + balFmt + '.', { title: 'Threshold Not Met' });
                    } else {
                        alert('Withdrawal condition not met: Minimum withdrawal threshold is ₹100.00. Your current available balance is ' + balFmt + '.');
                    }
                }
                return { eligible: false, reason: 'below_threshold', balance: avail };
            }

            return { eligible: true, balance: avail, bank: bank, wallet: wallet };
        }

        function openPayoutModal() {
            if (!window.globalWalletData) {
                if (typeof Notify !== 'undefined' && Notify.info) {
                    Notify.info('Fetching your latest wallet and settlement details...', { title: 'Synchronizing' });
                }
                $.get('index.php?ajax_action=fetch_wallet_data', function(res) {
                    if (res.status === 'success') {
                        globalWalletData = res;
                        renderWalletData(res);
                        openPayoutModal();
                    } else {
                        if (typeof Notify !== 'undefined' && Notify.error) {
                            Notify.error(res.message || 'Failed to load wallet data.');
                        }
                    }
                }, 'json');
                return;
            }

            var check = checkWithdrawalEligibility(true);
            if (!check.eligible) {
                // Prevent modal from opening when conditions are not covered
                return;
            }

            var avail = check.balance;
            var bank = check.bank;

            $('#modal-available-balance').text('₹' + avail.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#payoutWithdrawAmount').val('').attr('max', avail);
            $('#payoutNotes').val('');
            $('#payoutCalcFeedback').html('');
            $('#payoutBalanceWarning').hide();
            $('#btnSubmitPayout').prop('disabled', false);

            if (bank && bank.account_number) {
                var masked = '•••• •••• ' + bank.account_number.slice(-4);
                $('#modal-dest-bank-label').text(`${bank.bank_name || 'Bank'} (${masked})`);
                $('#destBankRadio').prop('disabled', false).prop('checked', true);
            } else {
                $('#modal-dest-bank-label').text('No Bank Account Linked');
                $('#destBankRadio').prop('disabled', true);
            }

            if (bank && bank.upi_id) {
                $('#modal-dest-upi-label').text(`UPI ID (${bank.upi_id})`);
                if (!bank.account_number) {
                    $('#destUpiRadio').prop('checked', true);
                }
                $('#destUpiRadio').prop('disabled', false);
            } else {
                $('#modal-dest-upi-label').text('UPI ID (Not configured)');
                $('#destUpiRadio').prop('disabled', true);
            }

            $('#requestPayoutModal').modal('show');
        }

        function submitPayoutRequest(e) {
            e.preventDefault();
            const amount = parseFloat($('#payoutWithdrawAmount').val() || 0);
            const notes = $('#payoutNotes').val();
            const avail = parseFloat((globalWalletData && globalWalletData.wallet && globalWalletData.wallet.available_balance) || 0);

            if (amount < 100) {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Minimum withdrawal amount is ₹100.00.');
                }
                return;
            }
            if (amount > avail) {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error(`Requested amount exceeds available balance of ₹${avail.toFixed(2)}.`);
                }
                return;
            }

            const btn = $('#btnSubmitPayout');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...');

            const dest = $('input[name="payout_destination"]:checked').val() || 'bank';
            $.post('index.php', {
                ajax_action: 'request_payout',
                amount: amount,
                destination: dest,
                notes: notes
            }, function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-2"></i> Submit Request');
                if (res.status === 'success') {
                    $('#requestPayoutModal').modal('hide');
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        alert(res.message);
                    }
                    loadWallet();
                } else {
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(res.message || 'Failed to submit payout request.');
                    } else {
                        alert(res.message || 'Failed to submit payout request.');
                    }
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-2"></i> Submit Request');
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Network error while requesting payout.');
                }
            });
        }

        function openBankDetailsModal(targetTab) {
            targetTab = targetTab || 'bank';

            // Explicitly sync the nav pill tabs and their tab-panes inside #updateBankDetailsModal
            $('#settlementMethodTabs .nav-link').removeClass('active');
            $('#settlementMethodTabContent .tab-pane').removeClass('show active');

            if (targetTab === 'upi') {
                $('#tab-btn-upi').addClass('active');
                $('#tab-pane-upi').addClass('show active');
                if (typeof $.fn.tab === 'function') {
                    $('#tab-btn-upi').tab('show');
                }
            } else {
                $('#tab-btn-bank').addClass('active');
                $('#tab-pane-bank').addClass('show active');
                if (typeof $.fn.tab === 'function') {
                    $('#tab-btn-bank').tab('show');
                }
            }

            if (window.globalWalletData && window.globalWalletData.bank) {
                const b = window.globalWalletData.bank;
                $('#bankHolderName').val(b.account_holder_name || '');
                $('#bankNameInput').val(b.bank_name || '');
                $('#bankAccountNumber').val(b.account_number || '');
                $('#bankAccountNumberConfirm').val(b.account_number || '');
                $('#bankIfscCode').val(b.ifsc_code || '');
                $('#bankUpiId').val(b.upi_id || '');
                $('#bankAccountNumberConfirm').trigger('input');
                $('#bankIfscCode').trigger('input');
                $('#bankUpiId').trigger('input');
            } else {
                if ($('#formBankAccount').length && $('#formBankAccount')[0] && typeof $('#formBankAccount')[0].reset === 'function') {
                    $('#formBankAccount')[0].reset();
                }
                if ($('#formUpiDetails').length && $('#formUpiDetails')[0] && typeof $('#formUpiDetails')[0].reset === 'function') {
                    $('#formUpiDetails')[0].reset();
                }
                $('#bankAccMatchIndicator').html('');
                $('#bankIfscIndicator').html('');
                $('#upiFormatIndicator').html('');
                $('#bankAccountNumberConfirm').css('border-color', '');
                $('#bankIfscCode').css('border-color', '');
                $('#bankUpiId').css('border-color', '');
            }
            $('#updateBankDetailsModal').modal('show');
        }

        function submitBankAccount(e) {
            e.preventDefault();
            const holder = $('#bankHolderName').val().trim();
            const bankName = $('#bankNameInput').val().trim();
            const accNum = $('#bankAccountNumber').val().trim();
            const accNumConfirm = $('#bankAccountNumberConfirm').val().trim();
            const ifsc = $('#bankIfscCode').val().trim().toUpperCase();

            if (!holder || !bankName || !accNum || !ifsc) {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Please fill in all required bank fields.');
                }
                return;
            }
            if (accNum !== accNumConfirm) {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Bank account numbers do not match.');
                }
                return;
            }
            if (ifsc.length !== 11) {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('IFSC code must be exactly 11 characters.');
                }
                return;
            }

            const btn = $('#btnSubmitBankDetails');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');

            $.post('index.php', {
                ajax_action: 'save_bank_details',
                save_type: 'bank',
                account_holder_name: holder,
                bank_name: bankName,
                account_number: accNum,
                account_number_confirm: accNumConfirm,
                ifsc_code: ifsc
            }, function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save Bank Account');
                if (res.status === 'success') {
                    $('#updateBankDetailsModal').modal('hide');
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        alert(res.message);
                    }
                    loadWallet();
                } else {
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(res.message || 'Failed to save bank details.');
                    } else {
                        alert(res.message || 'Failed to save bank details.');
                    }
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save Bank Account');
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Network error while saving bank details.');
                }
            });
        }

        function submitUpiDetails(e) {
            e.preventDefault();
            const upi = $('#bankUpiId').val().trim().toLowerCase();

            if (!upi || !upi.includes('@') || upi.length < 5) {
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Please enter a valid UPI ID containing "@" (e.g. mobile@upi).');
                }
                return;
            }

            const btn = $('#btnSubmitUpiDetails');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...');

            $.post('index.php', {
                ajax_action: 'save_bank_details',
                save_type: 'upi',
                upi_id: upi
            }, function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save UPI ID');
                if (res.status === 'success') {
                    $('#updateBankDetailsModal').modal('hide');
                    if (typeof Notify !== 'undefined' && Notify.success) {
                        Notify.success(res.message);
                    } else {
                        alert(res.message);
                    }
                    loadWallet();
                } else {
                    if (typeof Notify !== 'undefined' && Notify.error) {
                        Notify.error(res.message || 'Failed to link UPI ID.');
                    } else {
                        alert(res.message || 'Failed to link UPI ID.');
                    }
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Save UPI ID');
                if (typeof Notify !== 'undefined' && Notify.error) {
                    Notify.error('Network error while linking UPI ID.');
                }
            });
        }

        function submitBankDetails(e) {
            if ($('#tab-pane-upi').hasClass('active')) {
                submitUpiDetails(e);
            } else {
                submitBankAccount(e);
            }
        }

        /* Live Form Feedback & Bootstrap Modal Event Hooks */
        $(document).on('input', '#payoutWithdrawAmount', function() {
            var avail = 0;
            if (window.globalWalletData && window.globalWalletData.wallet) {
                avail = parseFloat(window.globalWalletData.wallet.available_balance || 0);
            }
            var val = parseFloat($(this).val() || 0);
            var rem = avail - val;
            if (val > avail) {
                $('#payoutCalcFeedback').html('<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Exceeds available balance!</span>');
                $('#btnSubmitPayout').prop('disabled', true);
            } else if (val < 100) {
                $('#payoutCalcFeedback').html('<span class="text-warning font-weight-bold"><i class="fas fa-info-circle mr-1"></i> Min ₹100.00</span>');
                $('#btnSubmitPayout').prop('disabled', true);
            } else {
                $('#payoutCalcFeedback').html('<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Remaining: ₹' + rem.toFixed(2) + '</span>');
                $('#btnSubmitPayout').prop('disabled', false);
            }
        });

        $(document).on('input', '#bankAccountNumber, #bankAccountNumberConfirm', function() {
            var a1 = $('#bankAccountNumber').val().trim();
            var a2 = $('#bankAccountNumberConfirm').val().trim();
            if (!a2) {
                $('#bankAccMatchIndicator').html('');
                $('#bankAccountNumberConfirm').css('border-color', '');
            } else if (a1 === a2) {
                $('#bankAccMatchIndicator').html('<span class="text-success small font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Account numbers match</span>');
                $('#bankAccountNumberConfirm').css('border-color', '#10b981');
            } else {
                $('#bankAccMatchIndicator').html('<span class="text-danger small font-weight-bold"><i class="fas fa-times-circle mr-1"></i> Account numbers do not match</span>');
                $('#bankAccountNumberConfirm').css('border-color', '#ef4444');
            }
        });

        $(document).on('input', '#bankIfscCode', function() {
            var ifsc = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, '');
            $(this).val(ifsc);
            if (ifsc.length === 11) {
                $('#bankIfscIndicator').html('<span class="text-success small font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Valid 11-digit IFSC format</span>');
                $(this).css('border-color', '#10b981');
            } else if (ifsc.length > 0) {
                $('#bankIfscIndicator').html('<span class="text-muted small">Must be 11 characters (' + ifsc.length + '/11)</span>');
                $(this).css('border-color', '');
            } else {
                $('#bankIfscIndicator').html('');
                $(this).css('border-color', '');
            }
        });

        $(document).on('show.bs.modal', '#requestPayoutModal', function(e) {
            var check = checkWithdrawalEligibility(false);
            if (!check.eligible) {
                e.preventDefault();
                return false;
            }
            var avail = check.balance;
            var bank = check.bank;
            
            $('#modal-available-balance').text('₹' + avail.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#payoutWithdrawAmount').val('').attr('max', avail);
            $('#payoutNotes').val('');
            $('#payoutCalcFeedback').html('');

            if (bank && bank.account_number) {
                var masked = '•••• •••• ' + bank.account_number.slice(-4);
                $('#modal-dest-bank-label').text(`${bank.bank_name || 'Bank'} (${masked})`);
                $('#destBankRadio').prop('disabled', false).prop('checked', true);
            } else {
                $('#modal-dest-bank-label').text('No Bank Account Linked');
                $('#destBankRadio').prop('disabled', true);
            }

            if (bank && bank.upi_id) {
                $('#modal-dest-upi-label').text(`UPI ID (${bank.upi_id})`);
                if (!bank.account_number) {
                    $('#destUpiRadio').prop('checked', true);
                }
                $('#destUpiRadio').prop('disabled', false);
            } else {
                $('#modal-dest-upi-label').text('UPI ID (Not configured)');
                $('#destUpiRadio').prop('disabled', true);
            }

            $('#btnSubmitPayout').prop('disabled', false);
            $('#payoutBalanceWarning').hide();
        });

        $(document).on('show.bs.modal', '#updateBankDetailsModal', function() {
            if (!$('#settlementMethodTabContent .tab-pane.active').length) {
                $('#tab-btn-bank').addClass('active');
                $('#tab-pane-bank').addClass('show active');
            }
            if (window.globalWalletData && window.globalWalletData.bank) {
                var b = window.globalWalletData.bank;
                $('#bankHolderName').val(b.account_holder_name || '');
                $('#bankNameInput').val(b.bank_name || '');
                $('#bankAccountNumber').val(b.account_number || '');
                $('#bankAccountNumberConfirm').val(b.account_number || '');
                $('#bankIfscCode').val(b.ifsc_code || '');
                $('#bankUpiId').val(b.upi_id || '');
            }
            $('#bankAccMatchIndicator').html('');
            $('#bankIfscIndicator').html('');
            $('#bankAccountNumberConfirm').css('border-color', '');
            $('#bankIfscCode').css('border-color', '');
        });

        // Ensure clicking tab pills directly reliably switches tab panes
        $(document).on('click', '#settlementMethodTabs a[data-toggle="pill"]', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            $('#settlementMethodTabs .nav-link').removeClass('active');
            $(this).addClass('active');
            $('#settlementMethodTabContent .tab-pane').removeClass('show active');
            $(target).addClass('show active');
        });

        $(document).on('input', '#bankUpiId', function() {
            var upi = $(this).val().trim().toLowerCase();
            if (upi.length > 4 && upi.includes('@') && !upi.endsWith('@')) {
                $('#upiFormatIndicator').html('<span class="text-success small font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Valid UPI ID format</span>');
                $(this).css('border-color', '#10b981');
            } else if (upi.length > 0) {
                $('#upiFormatIndicator').html('<span class="text-muted small">Must include "@" handle (e.g. name@bank)</span>');
                $(this).css('border-color', '');
            } else {
                $('#upiFormatIndicator').html('');
                $(this).css('border-color', '');
            }
        });

        // Explicit global window bindings
        window.loadWallet = loadWallet;
        window.renderWalletData = renderWalletData;
        window.switchWalletView = switchWalletView;
        window.filterWalletTable = filterWalletTable;
        window.checkWithdrawalEligibility = checkWithdrawalEligibility;
        window.openPayoutModal = openPayoutModal;
        window.submitPayoutRequest = submitPayoutRequest;
        window.openBankDetailsModal = openBankDetailsModal;
        window.submitBankDetails = submitBankDetails;
        window.submitBankAccount = submitBankAccount;
        window.submitUpiDetails = submitUpiDetails;

    </script>
<script>

    // =========================================================================
    // SITE SERVICE CHARGE & OFFLINE CASH PAYMENT HANDLERS
    // =========================================================================
    let currentProviderDues = 0;
    let currentWalletBalance = 0;

    window.showBookingQrModal = function(id, bookingNumber, amount, customerNameEnc) {
        const custName = customerNameEnc ? decodeURIComponent(customerNameEnc) : 'Customer';
        const amtNum = parseFloat(amount || 0).toFixed(2);
        $('#qr-modal-booking-ref').text('Ref: #' + bookingNumber);
        $('#qr-modal-amount').text('₹' + amtNum);
        $('#qr-modal-customer-name').text('Customer: ' + custName);

        const upiString = `upi://pay?pa=serveasy@upi&pn=ServEasy%20Marketplace&am=${amtNum}&tr=${encodeURIComponent(bookingNumber)}&tn=Booking%20Payment%20${encodeURIComponent(bookingNumber)}&cu=INR`;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(upiString)}`;
        $('#qr-modal-image').attr('src', qrUrl);

        $('#qr-modal-confirm-paid-btn').off('click').on('click', function() {
            $('#bookingQrModal').modal('hide');
            markBookingPaid(id, bookingNumber, amount);
        });

        $('#bookingQrModal').modal('show');
    };

    window.markBookingPaid = function(id, ref, amount) {
        Swal.fire({
            title: 'Confirm Cash Payment',
            html: `Did you collect <strong>₹${parseFloat(amount).toFixed(2)}</strong> in cash from the customer for booking <strong>#${ref}</strong>?<br><br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> This marks the booking as PAID and incurs <strong>₹20.00</strong> site service charge due.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Yes, Cash Collected',
            cancelButtonText: 'Cancel'
        }).then((res) => {
            if (res.isConfirmed) {
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { ajax_action: 'mark_booking_paid', action: 'mark_booking_paid', id: id },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Payment Marked as PAID!',
                                text: resp.message,
                                confirmButtonColor: '#0084ff'
                            });
                            if (typeof fetchLiveBookings === 'function') fetchLiveBookings();
                            if (typeof loadOrders === 'function') loadOrders();
                            checkServiceChargeDues();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Action Failed', text: resp.message || 'Could not update payment status.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Server communication failed.' });
                    }
                });
            }
        });
    };

    window.checkServiceChargeDues = function() {
        $.ajax({
            url: 'index.php?ajax_action=get_service_charges&action=get_service_charges',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    currentProviderDues = parseFloat(res.total_due || 0);
                    currentWalletBalance = parseFloat(res.available_balance || 0);
                    updateDuesBanner(currentProviderDues, currentWalletBalance);
                }
            }
        });
    };

    function updateDuesBanner(due, balance) {
        const bannerContainer = document.getElementById('providerDuesAlertBanner');
        if (!bannerContainer) return;

        if (due > 0) {
            bannerContainer.innerHTML = `
                <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 mb-3 shadow-sm" style="border-radius:10px; border-left: 5px solid #f59e0b; background: rgba(245, 158, 11, 0.12); color: #d97706;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x mr-3 text-warning"></i>
                        <div>
                            <strong style="font-size: 1rem;">Outstanding Site Service Charge: ₹${due.toFixed(2)}</strong>
                            <p class="mb-0 small" style="color: #92400e;">You have collected offline cash. Site service charge must be paid before you can accept new bookings.</p>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-warning font-weight-bold px-3 py-2 shadow-sm" style="border-radius:8px;" onclick="openPayDuesModal(${due}, ${balance})">
                            <i class="fas fa-wallet mr-1"></i> Pay Service Charge
                        </button>
                    </div>
                </div>
            `;
            bannerContainer.style.display = 'block';
        } else {
            bannerContainer.innerHTML = '';
            bannerContainer.style.display = 'none';
        }
    }

    window.openPayDuesModal = function(due, balance) {
        due = due !== undefined ? due : currentProviderDues;
        balance = balance !== undefined ? balance : currentWalletBalance;

        const canPayWallet = balance >= due;
        const walletText = `Pay from Wallet Balance (Available: ₹${balance.toFixed(2)})`;

        Swal.fire({
            title: '<i class="fas fa-receipt text-warning mr-2"></i> Pay Site Service Charge',
            html: `
                <div class="text-left py-2">
                    <p class="mb-2">Total Outstanding Dues: <strong class="text-danger font-size-lg">₹${due.toFixed(2)}</strong></p>
                    <p class="small text-muted mb-3">Clearing your dues immediately restores your ability to accept new customer service requests.</p>
                    
                    <div class="custom-control custom-radio mb-2 p-2 border rounded ${canPayWallet ? '' : 'text-muted'}" style="background: rgba(0,0,0,0.02);">
                        <input type="radio" id="payMethodWallet" name="duePayMethod" class="custom-control-input" value="wallet" ${canPayWallet ? 'checked' : 'disabled'}>
                        <label class="custom-control-label font-weight-bold" for="payMethodWallet">
                            <i class="fas fa-wallet text-primary mr-1"></i> ${walletText}
                            ${!canPayWallet ? '<br><small class="text-danger font-weight-normal">Insufficient wallet balance</small>' : ''}
                        </label>
                    </div>

                    <div class="custom-control custom-radio p-2 border rounded" style="background: rgba(0,0,0,0.02);">
                        <input type="radio" id="payMethodOnline" name="duePayMethod" class="custom-control-input" value="online" ${!canPayWallet ? 'checked' : ''}>
                        <label class="custom-control-label font-weight-bold" for="payMethodOnline">
                            <i class="fas fa-credit-card text-success mr-1"></i> Pay Online (UPI / NetBanking / Cards)
                        </label>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-lock mr-1"></i> Pay ₹' + due.toFixed(2),
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const selectedMethod = $('input[name="duePayMethod"]:checked').val() || 'online';
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: { ajax_action: 'pay_service_charges', action: 'pay_service_charges', payment_method: selectedMethod },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Service Charge Cleared! 🎉',
                                text: res.message,
                                confirmButtonColor: '#0084ff'
                            });
                            checkServiceChargeDues();
                            if (typeof fetchLiveBookings === 'function') fetchLiveBookings();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Payment Failed', text: res.message });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not process service charge payment.' });
                    }
                });
            }
        });
    };

    $(document).ready(function() {
        checkServiceChargeDues();
        var urlParams = new URLSearchParams(window.location.search);
        var modalParam = urlParams.get('modal');
        if (modalParam === 'bank' || modalParam === 'upi') {
            setTimeout(function() { openBankDetailsModal(modalParam); }, 300);
        } else if (modalParam === 'payout') {
            setTimeout(function() { openPayoutModal(); }, 300);
        }
    });

</script>
</body>

</html>
