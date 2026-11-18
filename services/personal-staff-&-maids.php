<?php
session_start();

$dbConnPath = file_exists(__DIR__ . '/../../backend/config/db_connection.php')
    ? __DIR__ . '/../../backend/config/db_connection.php'
    : (file_exists(__DIR__ . '/../../../backend/config/db_connection.php') 
        ? __DIR__ . '/../../../backend/config/db_connection.php' 
        : (file_exists(__DIR__ . '/../common/db_connection.php') ? __DIR__ . '/../common/db_connection.php' : null));

$bootstrapPath = file_exists(__DIR__ . '/../../backend/api/bootstrap.php')
    ? __DIR__ . '/../../backend/api/bootstrap.php'
    : (file_exists(__DIR__ . '/../../../backend/api/bootstrap.php') 
        ? __DIR__ . '/../../../backend/api/bootstrap.php' 
        : null);

if ($dbConnPath) require_once $dbConnPath;
if ($bootstrapPath) require_once $bootstrapPath;

header('Content-Type: text/html; charset=utf-8');

// Authenticate dealer
if (!isset($_SESSION['dealer'])) {
    header("Location: ../login.php");
    exit();
}
$dealer = $_SESSION['dealer'];
$dealer_id = (int)$dealer['id'];

try {
    $pdo = Database::connect();

    // Fetch provider record from serveasy-providers
    $p_stmt = $pdo->prepare("SELECT id, verified_status FROM `serveasy-providers` WHERE id = ?");
    $p_stmt->execute([$dealer_id]);
    $provider_row = $p_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$provider_row) {
        $pdo->prepare("INSERT INTO `serveasy-providers` (id, is_active, verified_status) VALUES (?, 1, 'pending')")->execute([$dealer_id]);
        $provider_id = $dealer_id;
        $verified_status = 'pending';
    } else {
        $provider_id = (int)$provider_row['id'];
        $verified_status = $provider_row['verified_status'];
    }

    // Fetch Personal Staff & Maids subcategories
    $subcats_stmt = $pdo->prepare("
        SELECT sc.* FROM `serveasy-subcategories` sc
        JOIN `serveasy-categories` c ON sc.category_id = c.id
        WHERE c.slug = 'personal-staff-&-maids' AND sc.is_active = 1
        ORDER BY sc.name ASC
    ");
    $subcats_stmt->execute();
    $subcategories = $subcats_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch existing profile if any
    $prof_stmt = $pdo->prepare("SELECT * FROM `provider_profiles` WHERE provider_id = ?");
    $prof_stmt->execute([$provider_id]);
    $profile = $prof_stmt->fetch(PDO::FETCH_ASSOC);

    // Handle AJAX requests
    if (isset($_REQUEST['ajax_action'])) {
        $action = $_REQUEST['ajax_action'];
        header('Content-Type: application/json');

        if ($action === 'save_profile') {
            $status = $_POST['profile_status'] ?? 'Draft'; // Draft or Pending
            $full_name = trim($_POST['full_name'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $dob = $_POST['dob'] ?? null;
            $age = !empty($dob) ? date_diff(date_create($dob), date_create('today'))->y : null;
            $phone_number = trim($_POST['phone_number'] ?? '');
            $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $current_address = trim($_POST['current_address'] ?? '');
            $permanent_address = trim($_POST['permanent_address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $country = trim($_POST['country'] ?? 'India');
            $languages = isset($_POST['languages']) ? implode(', ', $_POST['languages']) : '';
            $about_me = trim($_POST['about_me'] ?? '');
            $years_exp = (int)($_POST['years_of_experience'] ?? 0);
            
            $work_types = isset($_POST['preferred_work_type']) ? implode(', ', $_POST['preferred_work_type']) : '';
            $working_hours = trim($_POST['working_hours'] ?? '');
            $available_from = $_POST['available_from'] ?? null;
            if (empty($available_from)) $available_from = null;

            $expected_salary = !empty($_POST['expected_salary']) ? (float)$_POST['expected_salary'] : null;
            $hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
            $daily_rate = !empty($_POST['daily_rate']) ? (float)$_POST['daily_rate'] : null;
            $monthly_rate = !empty($_POST['monthly_rate']) ? (float)$_POST['monthly_rate'] : null;
            $service_desc = trim($_POST['service_description'] ?? '');
            $travel_dist = !empty($_POST['travel_max_distance']) ? (float)$_POST['travel_max_distance'] : 10.00;

            if (empty($full_name)) {
                echo json_encode(['status' => 'error', 'message' => 'Full name is required.']);
                exit();
            }

            $pdo->beginTransaction();

            try {
                // Insert/Update profile
                $stmt = $pdo->prepare("
                    INSERT INTO `provider_profiles`
                        (provider_id, full_name, gender, dob, age, phone_number, whatsapp_number, email, current_address, permanent_address, city, state, pincode, country, languages_spoken, about_me, years_of_experience, preferred_work_type, working_hours, available_from, expected_salary, hourly_rate, daily_rate, monthly_rate, service_description, travel_max_distance, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        full_name = VALUES(full_name),
                        gender = VALUES(gender),
                        dob = VALUES(dob),
                        age = VALUES(age),
                        phone_number = VALUES(phone_number),
                        whatsapp_number = VALUES(whatsapp_number),
                        email = VALUES(email),
                        current_address = VALUES(current_address),
                        permanent_address = VALUES(permanent_address),
                        city = VALUES(city),
                        state = VALUES(state),
                        pincode = VALUES(pincode),
                        country = VALUES(country),
                        languages_spoken = VALUES(languages_spoken),
                        about_me = VALUES(about_me),
                        years_of_experience = VALUES(years_of_experience),
                        preferred_work_type = VALUES(preferred_work_type),
                        working_hours = VALUES(working_hours),
                        available_from = VALUES(available_from),
                        expected_salary = VALUES(expected_salary),
                        hourly_rate = VALUES(hourly_rate),
                        daily_rate = VALUES(daily_rate),
                        monthly_rate = VALUES(monthly_rate),
                        service_description = VALUES(service_description),
                        travel_max_distance = VALUES(travel_max_distance),
                        status = VALUES(status)
                ");
                $stmt->execute([
                    $provider_id, $full_name, $gender, $dob, $age, $phone_number, $whatsapp_number, $email, $current_address, $permanent_address, $city, $state, $pincode, $country, $languages, $about_me, $years_exp, $work_types, $working_hours, $available_from, $expected_salary, $hourly_rate, $daily_rate, $monthly_rate, $service_desc, $travel_dist, $status
                ]);

                // Get profile ID
                if ($profile) {
                    $profile_id = (int)$profile['id'];
                } else {
                    $profile_id = $pdo->lastInsertId();
                }

                // Subcategories (provider_services)
                $pdo->prepare("DELETE FROM `provider_services` WHERE profile_id = ?")->execute([$profile_id]);
                if (isset($_POST['subcategories']) && is_array($_POST['subcategories'])) {
                    $sub_stmt = $pdo->prepare("INSERT INTO `provider_services` (profile_id, subcategory_id) VALUES (?, ?)");
                    foreach ($_POST['subcategories'] as $subcat_id) {
                        $sub_stmt->execute([$profile_id, (int)$subcat_id]);
                    }
                }

                // Predefined Skills
                $pdo->prepare("DELETE FROM `provider_skills` WHERE profile_id = ?")->execute([$profile_id]);
                if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                    $sk_stmt = $pdo->prepare("INSERT INTO `provider_skills` (profile_id, skill_name) VALUES (?, ?)");
                    foreach ($_POST['skills'] as $skill_name) {
                        $sk_stmt->execute([$profile_id, trim($skill_name)]);
                    }
                }

                // Custom Skills
                $pdo->prepare("DELETE FROM `provider_custom_skills` WHERE profile_id = ?")->execute([$profile_id]);
                if (isset($_POST['custom_skills']) && is_array($_POST['custom_skills'])) {
                    $cust_sk_stmt = $pdo->prepare("INSERT INTO `provider_custom_skills` (profile_id, skill_name) VALUES (?, ?)");
                    foreach ($_POST['custom_skills'] as $skill_name) {
                        $skill_name = trim($skill_name);
                        if (!empty($skill_name)) {
                            $cust_sk_stmt->execute([$profile_id, $skill_name]);
                        }
                    }
                }

                // Experience Details
                $pdo->prepare("DELETE FROM `provider_experience` WHERE profile_id = ?")->execute([$profile_id]);
                if (isset($_POST['exp_employer']) && is_array($_POST['exp_employer'])) {
                    $exp_stmt = $pdo->prepare("INSERT INTO `provider_experience` (profile_id, employer_name, job_title, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)");
                    for ($i = 0; $i < count($_POST['exp_employer']); $i++) {
                        $emp = trim($_POST['exp_employer'][$i]);
                        $title = trim($_POST['exp_title'][$i] ?? '');
                        $start = $_POST['exp_start'][$i] ?? null;
                        $end = $_POST['exp_end'][$i] ?? null;
                        $desc = trim($_POST['exp_desc'][$i] ?? '');
                        if (!empty($emp)) {
                            $exp_stmt->execute([$profile_id, $emp, $title, empty($start)?null:$start, empty($end)?null:$end, $desc]);
                        }
                    }
                }

                // Working Areas (provider_service_areas)
                $pdo->prepare("DELETE FROM `provider_service_areas` WHERE profile_id = ?")->execute([$profile_id]);
                if (isset($_POST['area_pincodes']) && is_array($_POST['area_pincodes'])) {
                    $area_stmt = $pdo->prepare("INSERT INTO `provider_service_areas` (profile_id, state, city, area_name, pincode) VALUES (?, ?, ?, ?, ?)");
                    for ($i = 0; $i < count($_POST['area_pincodes']); $i++) {
                        $pin = trim($_POST['area_pincodes'][$i]);
                        $area = trim($_POST['area_names'][$i] ?? '');
                        $c_city = trim($_POST['area_cities'][$i] ?? $city);
                        $c_state = trim($_POST['area_states'][$i] ?? $state);
                        if (!empty($pin)) {
                            $area_stmt->execute([$profile_id, $c_state, $c_city, $area, $pin]);
                        }
                    }
                }

                // Availability Grid
                $pdo->prepare("DELETE FROM `provider_availability` WHERE profile_id = ?")->execute([$profile_id]);
                $avail_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $avail_slots = ['Morning', 'Afternoon', 'Evening', 'Night'];
                
                $avail_stmt = $pdo->prepare("INSERT INTO `provider_availability` (profile_id, day_of_week, time_slot, is_available, emergency_availability) VALUES (?, ?, ?, ?, ?)");
                
                $emergency = isset($_POST['emergency_availability']) ? 1 : 0;
                
                foreach ($avail_days as $day) {
                    foreach ($avail_slots as $slot) {
                        $is_avail = isset($_POST['avail'][$day][$slot]) ? 1 : 0;
                        $avail_stmt->execute([$profile_id, $day, $slot, $is_avail, $emergency]);
                    }
                }

                // File Upload Directory
                $upload_dir = __DIR__ . '/../../../uploads/maids/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Handle Profile Photo Upload
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
                    $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                    $filename = 'photo_' . $profile_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $filename)) {
                        $photo_path = 'uploads/maids/' . $filename;
                        $pdo->prepare("UPDATE `provider_profiles` SET profile_photo = ? WHERE id = ?")->execute([$photo_path, $profile_id]);
                    }
                }

                // Handle Documents Upload
                $doc_types = ['Aadhaar Card', 'PAN Card', 'Passport', 'Driving License', 'Police Verification Certificate', 'Medical Certificate', 'Address Proof', 'Experience Certificate', 'Resume', 'Selfie Verification'];
                $doc_stmt = $pdo->prepare("
                    INSERT INTO `provider_documents` (profile_id, document_type, file_path, verified)
                    VALUES (?, ?, ?, 0)
                    ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), verified = 0
                ");

                foreach ($doc_types as $doc_type) {
                    $input_name = str_replace(' ', '_', strtolower($doc_type));
                    if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === 0) {
                        $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                        $filename = $input_name . '_' . $profile_id . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $upload_dir . $filename)) {
                            $doc_path = 'uploads/maids/' . $filename;
                            // Check if document already exists
                            $chk = $pdo->prepare("SELECT id FROM `provider_documents` WHERE profile_id = ? AND document_type = ?");
                            $chk->execute([$profile_id, $doc_type]);
                            $doc_row = $chk->fetch();
                            if ($doc_row) {
                                $pdo->prepare("UPDATE `provider_documents` SET file_path = ?, verified = 0 WHERE id = ?")->execute([$doc_path, $doc_row['id']]);
                            } else {
                                $doc_stmt->execute([$profile_id, $doc_type, $doc_path]);
                            }
                        }
                    }
                }

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Profile saved successfully!']);
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
                exit();
            }
        }

        if ($action === 'delete_profile') {
            if ($profile) {
                $pdo->prepare("DELETE FROM `provider_profiles` WHERE id = ?")->execute([(int)$profile['id']]);
                echo json_encode(['status' => 'success', 'message' => 'Profile deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No profile found.']);
            }
            exit();
        }
    }

    // Load related items if profile exists
    $selected_subcats = [];
    $skills = [];
    $custom_skills = [];
    $experience = [];
    $documents = [];
    $areas = [];
    $availability = [];

    if ($profile) {
        $profile_id = (int)$profile['id'];

        $selected_subcats = $pdo->query("SELECT subcategory_id FROM `provider_services` WHERE profile_id = $profile_id")->fetchAll(PDO::FETCH_COLUMN);
        $skills = $pdo->query("SELECT skill_name FROM `provider_skills` WHERE profile_id = $profile_id")->fetchAll(PDO::FETCH_COLUMN);
        $custom_skills = $pdo->query("SELECT skill_name FROM `provider_custom_skills` WHERE profile_id = $profile_id")->fetchAll(PDO::FETCH_COLUMN);
        $experience = $pdo->query("SELECT * FROM `provider_experience` WHERE profile_id = $profile_id ORDER BY id ASC")->fetchAll();
        $documents = $pdo->query("SELECT * FROM `provider_documents` WHERE profile_id = $profile_id")->fetchAll(PDO::FETCH_ASSOC);
        $areas = $pdo->query("SELECT * FROM `provider_service_areas` WHERE profile_id = $profile_id ORDER BY id ASC")->fetchAll();
        
        $raw_avail = $pdo->query("SELECT * FROM `provider_availability` WHERE profile_id = $profile_id")->fetchAll();
        foreach ($raw_avail as $av) {
            $availability[$av['day_of_week']][$av['time_slot']] = $av['is_available'];
            $emergency_availability = $av['emergency_availability'];
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Personal Staff & Maids Profile | ServEasy</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,600,700,800" rel="stylesheet">
    <link href="../../Super%20Admin/css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/notify.css">
    <style>
        :root {
            --emerald: #05c279;
            --emerald-light: #d1fae5;
            --emerald-dark: #038a56;
            --mint-cream: #f0fdf4;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
            color: #4e73df;
        }

        .sidebar {
            background: var(--emerald);
            background-image: linear-gradient(180deg, var(--emerald) 10%, var(--emerald-dark) 100%);
        }

        .card {
            border: 1px solid #e3e6f0;
            border-radius: 12px !important;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
        }

        .text-emerald {
            color: var(--emerald) !important;
        }

        .bg-emerald {
            background-color: var(--emerald) !important;
        }

        .btn-emerald {
            background: var(--emerald);
            color: #fff;
            border: none;
            border-radius: 6px !important;
            font-weight: 700;
            padding: 0.5rem 1.5rem;
            transition: all 0.2s;
        }

        .btn-emerald:hover {
            background: var(--emerald-dark);
            color: #fff;
        }

        /* Form Steps */
        .step-progress-container {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 30px;
        }
        .step-progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 4px;
            background: #e3e6f0;
            transform: translateY(-50%);
            z-index: 1;
        }
        .step-progress-line-fill {
            position: absolute;
            top: 50%;
            left: 0;
            height: 4px;
            background: var(--emerald);
            transform: translateY(-50%);
            z-index: 2;
            width: 0%;
            transition: width 0.3s ease;
        }
        .step-indicator-node {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fff;
            border: 4px solid #e3e6f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #858796;
            position: relative;
            z-index: 3;
            transition: all 0.3s ease;
        }
        .step-indicator-node.active {
            border-color: var(--emerald);
            color: var(--emerald);
            box-shadow: 0 0 10px rgba(5, 194, 121, 0.3);
        }
        .step-indicator-node.completed {
            background: var(--emerald);
            border-color: var(--emerald);
            color: #fff;
        }

        .form-step-panel {
            display: none;
            animation: fadeIn 0.4s ease forwards;
        }
        .form-step-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .preview-label {
            font-weight: 700;
            color: #4e73df;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .preview-value {
            color: #2e59d9;
            font-size: 0.95rem;
            margin-bottom: 12px;
        }
    </style>
</head>

<body id="page-top">

    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="../index.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-leaf"></i>
                </div>
                <div class="sidebar-brand-name mx-3">ServEasy</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i><span>Main Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Maid Module</div>
            <li class="nav-item active">
                <a class="nav-link" href="">
                    <i class="fas fa-fw fa-people-carry"></i><span>Staff & Maids Profile</span>
                </a>
            </li>
        </ul>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow-sm">
                    <h5 class="m-0 font-weight-bold text-emerald"><i class="fas fa-users-cog mr-2"></i>Maids & Staff Management Portal</h5>
                </nav>

                <div class="container-fluid">
                    <?php if (!$profile && !isset($_GET['action'])): ?>
                        <!-- subcategory selection screen -->
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="card shadow-sm border-0 mb-4">
                                    <div class="card-header bg-white border-bottom-0 pt-4 text-center">
                                        <h4 class="font-weight-bold text-emerald">Register as Personal Staff & Maid</h4>
                                        <p class="text-muted">Select one or more services you specialize in to start building your profile.</p>
                                    </div>
                                    <div class="card-body px-5 pb-5">
                                        <form id="subcategoryForm">
                                            <div class="row">
                                                <?php foreach ($subcategories as $sub): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="custom-control custom-checkbox p-3 border rounded" style="cursor:pointer;" onclick="toggleCheckbox(this)">
                                                            <input type="checkbox" name="subcategories[]" value="<?= $sub['id'] ?>" class="custom-control-input subcat-chk" id="chk-<?= $sub['id'] ?>">
                                                            <label class="custom-control-label font-weight-bold text-gray-800 ml-2" for="chk-<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="text-center mt-4">
                                                <button type="button" class="btn btn-emerald btn-lg px-5 font-weight-bold" id="createProfileBtn" disabled onclick="startProfileCreation()">
                                                    <i class="fas fa-user-plus mr-2"></i>Create Profile
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif (isset($_GET['action']) && ($_GET['action'] === 'create' || $_GET['action'] === 'edit')): ?>
                        <!-- MULTI STEP PROFILE FORM -->
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <div class="card shadow-sm border-0 mb-5">
                                    <div class="card-header bg-white border-bottom-0 pt-4 px-5">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="font-weight-bold text-emerald"><i class="fas fa-id-badge mr-2"></i>Maids & Staff Profile Builder</h4>
                                            <a href="personal-staff-&-maids.php" class="btn btn-secondary btn-sm rounded"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
                                        </div>
                                        <div class="step-progress-container mt-4">
                                            <div class="step-progress-line">
                                                <div class="step-progress-line-fill" id="progressBarFill"></div>
                                            </div>
                                            <?php for($i=1; $i<=8; $i++): ?>
                                                <div class="step-indicator-node" id="step-node-<?= $i ?>"><?= $i ?></div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="text-center font-weight-bold text-emerald" id="stepNameIndicator">Step 1: Personal Information</div>
                                    </div>

                                    <form id="maidProfileForm" enctype="multipart/form-data" class="px-5 pb-5 pt-3">
                                        <!-- Keep selected subcategories from step 1 -->
                                        <?php 
                                        $initial_subcats = isset($_POST['subcategories']) ? $_POST['subcategories'] : $selected_subcats; 
                                        foreach ($initial_subcats as $s_cat): 
                                        ?>
                                            <input type="hidden" name="subcategories[]" value="<?= htmlspecialchars($s_cat) ?>">
                                        <?php endforeach; ?>
                                        
                                        <input type="hidden" name="profile_status" id="profileStatusField" value="Draft">

                                        <!-- STEP 1: PERSONAL INFORMATION -->
                                        <div class="form-step-panel active" id="step-panel-1">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-user-edit mr-2"></i>Personal Details</h5>
                                            <div class="row">
                                                <div class="col-md-4 text-center form-group mb-4">
                                                    <label class="small font-weight-bold text-gray-800 d-block">Profile Photo <span class="text-danger">*</span></label>
                                                    <div class="mb-3">
                                                        <img id="photoPreview" src="<?= !empty($profile['profile_photo']) ? '../../' . htmlspecialchars($profile['profile_photo']) : '../images/user-placeholder.jpg' ?>" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                                    </div>
                                                    <input type="file" name="profile_photo" id="profile_photo" class="d-none" accept="image/*" onchange="previewImage(this, 'photoPreview')">
                                                    <button type="button" class="btn btn-sm btn-outline-emerald font-weight-bold" onclick="document.getElementById('profile_photo').click()"><i class="fas fa-upload mr-1"></i> Upload Photo</button>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="row">
                                                        <div class="col-md-6 form-group">
                                                            <label class="small font-weight-bold text-gray-800">Full Name <span class="text-danger">*</span></label>
                                                            <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" placeholder="e.g. John Doe">
                                                        </div>
                                                        <div class="col-md-6 form-group">
                                                            <label class="small font-weight-bold text-gray-800">Gender <span class="text-danger">*</span></label>
                                                            <select name="gender" class="form-control" required>
                                                                <option value="">Select...</option>
                                                                <option value="Male" <?= ($profile['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                                                <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                                                <option value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 form-group">
                                                            <label class="small font-weight-bold text-gray-800">Date of Birth <span class="text-danger">*</span></label>
                                                            <input type="date" name="dob" id="dobField" class="form-control" required value="<?= htmlspecialchars($profile['dob'] ?? '') ?>" onchange="calculateAge()">
                                                        </div>
                                                        <div class="col-md-6 form-group">
                                                            <label class="small font-weight-bold text-gray-800">Age</label>
                                                            <input type="text" id="ageField" class="form-control" readonly value="<?= htmlspecialchars($profile['age'] ?? '') ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <div class="col-md-4 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Phone Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="phone_number" class="form-control" required value="<?= htmlspecialchars($profile['phone_number'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label class="small font-weight-bold text-gray-800">WhatsApp Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="whatsapp_number" class="form-control" required value="<?= htmlspecialchars($profile['whatsapp_number'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Email Address</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Current Address <span class="text-danger">*</span></label>
                                                    <textarea name="current_address" class="form-control" rows="3" required><?= htmlspecialchars($profile['current_address'] ?? '') ?></textarea>
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Permanent Address <span class="text-danger">*</span></label>
                                                    <textarea name="permanent_address" class="form-control" rows="3" required><?= htmlspecialchars($profile['permanent_address'] ?? '') ?></textarea>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-3 form-group">
                                                    <label class="small font-weight-bold text-gray-800">City <span class="text-danger">*</span></label>
                                                    <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label class="small font-weight-bold text-gray-800">State <span class="text-danger">*</span></label>
                                                    <input type="text" name="state" class="form-control" required value="<?= htmlspecialchars($profile['state'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label class="small font-weight-bold text-gray-800">PIN Code <span class="text-danger">*</span></label>
                                                    <input type="text" name="pincode" class="form-control" required value="<?= htmlspecialchars($profile['pincode'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-3 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Country</label>
                                                    <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($profile['country'] ?? 'India') ?>">
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="small font-weight-bold text-gray-800">Languages Spoken (Multiple)</label>
                                                <div class="d-flex flex-wrap" style="gap: 15px;">
                                                    <?php 
                                                    $langs = ['English', 'Hindi', 'Bengali', 'Tamil', 'Telugu', 'Marathi', 'Gujarati', 'Kannada', 'Malayalam'];
                                                    $existing_langs = array_map('trim', explode(',', $profile['languages_spoken'] ?? ''));
                                                    foreach ($langs as $l):
                                                    ?>
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" name="languages[]" value="<?= $l ?>" class="custom-control-input" id="lang-<?= $l ?>" <?= in_array($l, $existing_langs) ? 'checked' : '' ?>>
                                                            <label class="custom-control-label" for="lang-<?= $l ?>"><?= $l ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="small font-weight-bold text-gray-800">About Me <span class="text-danger">*</span></label>
                                                <textarea name="about_me" class="form-control" rows="4" required placeholder="Tell customers about yourself, your values, and work ethic..."><?= htmlspecialchars($profile['about_me'] ?? '') ?></textarea>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Years of Experience <span class="text-danger">*</span></label>
                                                    <input type="number" name="years_of_experience" class="form-control" required min="0" value="<?= htmlspecialchars($profile['years_of_experience'] ?? 0) ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- STEP 2: PROFESSIONAL INFORMATION -->
                                        <div class="form-step-panel" id="step-panel-2">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-briefcase mr-2"></i>Professional Details</h5>
                                            
                                            <div class="form-group">
                                                <label class="small font-weight-bold text-gray-800">Preferred Work Type (Select multiple)</label>
                                                <div class="d-flex flex-wrap" style="gap: 20px;">
                                                    <?php 
                                                    $w_types = ['Full Time', 'Part Time', 'Live-In', 'Live-Out', 'Temporary'];
                                                    $existing_types = array_map('trim', explode(',', $profile['preferred_work_type'] ?? ''));
                                                    foreach ($w_types as $wt):
                                                    ?>
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" name="preferred_work_type[]" value="<?= $wt ?>" class="custom-control-input" id="wt-<?= str_replace(' ', '', $wt) ?>" <?= in_array($wt, $existing_types) ? 'checked' : '' ?>>
                                                            <label class="custom-control-label" for="wt-<?= str_replace(' ', '', $wt) ?>"><?= $wt ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Working Hours Preferred (e.g. 9 AM - 6 PM)</label>
                                                    <input type="text" name="working_hours" class="form-control" value="<?= htmlspecialchars($profile['working_hours'] ?? '') ?>" placeholder="e.g. 8 Hours, Flexi-shift, Night shift">
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Available From Date</label>
                                                    <input type="date" name="available_from" class="form-control" value="<?= htmlspecialchars($profile['available_from'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <h6 class="font-weight-bold text-gray-800 mt-4 mb-3">Previous Employer / Work Experience History</h6>
                                            <div id="experienceList">
                                                <?php if (empty($experience)): ?>
                                                    <div class="row exp-row mb-3 p-3 border rounded bg-light">
                                                        <div class="col-md-4 form-group">
                                                            <label class="small font-weight-bold text-gray-700">Employer Name</label>
                                                            <input type="text" name="exp_employer[]" class="form-control form-control-sm" placeholder="Previous Employer">
                                                        </div>
                                                        <div class="col-md-4 form-group">
                                                            <label class="small font-weight-bold text-gray-700">Job Title / Duties</label>
                                                            <input type="text" name="exp_title[]" class="form-control form-control-sm" placeholder="e.g. House Cook">
                                                        </div>
                                                        <div class="col-md-2 form-group">
                                                            <label class="small font-weight-bold text-gray-700">Start Date</label>
                                                            <input type="date" name="exp_start[]" class="form-control form-control-sm">
                                                        </div>
                                                        <div class="col-md-2 form-group">
                                                            <label class="small font-weight-bold text-gray-700">End Date</label>
                                                            <input type="date" name="exp_end[]" class="form-control form-control-sm">
                                                        </div>
                                                        <div class="col-md-12 form-group mb-0">
                                                            <label class="small font-weight-bold text-gray-700">Details / Feedback Reference</label>
                                                            <input type="text" name="exp_desc[]" class="form-control form-control-sm" placeholder="Reference contacts, reasons for leaving...">
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($experience as $exp): ?>
                                                        <div class="row exp-row mb-3 p-3 border rounded bg-light">
                                                            <div class="col-md-4 form-group">
                                                                <label class="small font-weight-bold text-gray-700">Employer Name</label>
                                                                <input type="text" name="exp_employer[]" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['employer_name']) ?>">
                                                            </div>
                                                            <div class="col-md-4 form-group">
                                                                <label class="small font-weight-bold text-gray-700">Job Title / Duties</label>
                                                                <input type="text" name="exp_title[]" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['job_title'] ?? '') ?>">
                                                            </div>
                                                            <div class="col-md-2 form-group">
                                                                <label class="small font-weight-bold text-gray-700">Start Date</label>
                                                                <input type="date" name="exp_start[]" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['start_date'] ?? '') ?>">
                                                            </div>
                                                            <div class="col-md-2 form-group">
                                                                <label class="small font-weight-bold text-gray-700">End Date</label>
                                                                <input type="date" name="exp_end[]" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['end_date'] ?? '') ?>">
                                                            </div>
                                                            <div class="col-md-12 form-group mb-0">
                                                                <label class="small font-weight-bold text-gray-700">Details / Feedback Reference</label>
                                                                <input type="text" name="exp_desc[]" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['description'] ?? '') ?>">
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-emerald font-weight-bold mt-2" onclick="addExperienceRow()"><i class="fas fa-plus mr-1"></i> Add Another Work History</button>
                                        </div>

                                        <!-- STEP 3: SKILLS & EXTRA WORK ABILITIES -->
                                        <div class="form-step-panel" id="step-panel-3">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-magic mr-2"></i>Skills & Special Abilities</h5>
                                            
                                            <div class="form-group">
                                                <label class="small font-weight-bold text-gray-800 mb-3">Predefined Skills (Check all that apply)</label>
                                                <div class="row">
                                                    <?php 
                                                    $pre_skills = ['Cooking', 'Child Care', 'Baby Care', 'Elder Care', 'Patient Care', 'House Cleaning', 'Laundry', 'Ironing', 'Gardening', 'Pet Care', 'Driving', 'Security', 'First Aid'];
                                                    foreach ($pre_skills as $sk):
                                                    ?>
                                                        <div class="col-md-4 mb-2">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" name="skills[]" value="<?= $sk ?>" class="custom-control-input" id="sk-<?= str_replace(' ', '', $sk) ?>" <?= in_array($sk, $skills) ? 'checked' : '' ?>>
                                                                <label class="custom-control-label" for="sk-<?= str_replace(' ', '', $sk) ?>"><?= $sk ?></label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <hr class="my-4">

                                            <div class="form-group">
                                                <label class="small font-weight-bold text-gray-800 d-block mb-3">Custom / Extra Abilities</label>
                                                <div id="customSkillsList" class="d-flex flex-wrap" style="gap: 10px;">
                                                    <?php foreach ($custom_skills as $c_sk): ?>
                                                        <div class="badge badge-primary p-2 d-flex align-items-center" style="gap: 8px; font-size: 0.85rem; border-radius: 6px; background-color: var(--emerald);">
                                                            <span><?= htmlspecialchars($c_sk) ?></span>
                                                            <input type="hidden" name="custom_skills[]" value="<?= htmlspecialchars($c_sk) ?>">
                                                            <i class="fas fa-times-circle" style="cursor:pointer;" onclick="this.parentElement.remove()"></i>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div class="input-group mt-3" style="max-width: 400px;">
                                                    <input type="text" id="customSkillInput" class="form-control form-control-sm" placeholder="e.g. Sewing, Computer Knowledge, Plumbing">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-sm btn-emerald" onclick="addCustomSkill()"><i class="fas fa-plus"></i> Add Skill</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- STEP 4: DOCUMENTS UPLOAD -->
                                        <div class="form-step-panel" id="step-panel-4">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-file-invoice mr-2"></i>Documents Verification</h5>
                                            <p class="text-muted small mb-4">Please upload scanned copies/images of the following documents. Both JPEG/PNG images and PDF files are accepted.</p>

                                            <?php 
                                            $docs_list = [
                                                'Aadhaar Card' => true, 
                                                'PAN Card' => true, 
                                                'Passport' => false, 
                                                'Driving License' => false, 
                                                'Police Verification Certificate' => true, 
                                                'Medical Certificate' => true, 
                                                'Address Proof' => true, 
                                                'Experience Certificate' => false, 
                                                'Resume' => false, 
                                                'Selfie Verification' => true
                                            ];
                                            
                                            foreach ($docs_list as $doc_label => $is_required):
                                                $field_id = str_replace(' ', '_', strtolower($doc_label));
                                                // Find if document already uploaded
                                                $uploaded_doc = null;
                                                foreach ($documents as $doc) {
                                                    if ($doc['document_type'] === $doc_label) {
                                                        $uploaded_doc = $doc;
                                                        break;
                                                    }
                                                }
                                            ?>
                                                <div class="row align-items-center mb-3 py-2 border-bottom">
                                                    <div class="col-md-5">
                                                        <span class="font-weight-bold text-gray-800" style="font-size: 0.9rem;"><?= $doc_label ?></span>
                                                        <?= $is_required ? '<span class="text-danger">*</span>' : '<span class="text-muted small">(Optional)</span>' ?>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input type="file" name="<?= $field_id ?>" class="form-control-file form-control-sm" <?= $is_required && !$uploaded_doc ? '' : '' ?>>
                                                    </div>
                                                    <div class="col-md-3 text-right">
                                                        <?php if ($uploaded_doc): ?>
                                                            <a href="../../<?= htmlspecialchars($uploaded_doc['file_path']) ?>" target="_blank" class="btn btn-xs btn-outline-primary py-1 px-2 font-weight-bold" style="font-size: 0.75rem; border-radius: 4px;"><i class="fas fa-eye mr-1"></i> View Saved File</a>
                                                            <?php if ($uploaded_doc['verified']): ?>
                                                                <span class="badge badge-success ml-2"><i class="fas fa-check-circle"></i> Verified</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-warning ml-2"><i class="fas fa-clock"></i> Pending</span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-danger small font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i> Missing</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- STEP 5: SERVICE DETAILS & PRICING -->
                                        <div class="form-step-panel" id="step-panel-5">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-rupee-sign mr-2"></i>Service Details & Pricing</h5>
                                            
                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Expected Salary (Monthly base rate, in ₹)</label>
                                                    <input type="number" name="expected_salary" class="form-control" value="<?= htmlspecialchars($profile['expected_salary'] ?? '') ?>" placeholder="e.g. 12000">
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Expected Hourly Rate (in ₹)</label>
                                                    <input type="number" name="hourly_rate" class="form-control" value="<?= htmlspecialchars($profile['hourly_rate'] ?? '') ?>" placeholder="e.g. 150">
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Expected Daily Rate (in ₹)</label>
                                                    <input type="number" name="daily_rate" class="form-control" value="<?= htmlspecialchars($profile['daily_rate'] ?? '') ?>" placeholder="e.g. 800">
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Expected Monthly Rate (Contract rate, in ₹)</label>
                                                    <input type="number" name="monthly_rate" class="form-control" value="<?= htmlspecialchars($profile['monthly_rate'] ?? '') ?>" placeholder="e.g. 15000">
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="small font-weight-bold text-gray-800">Service Description / Pricing Details <span class="text-danger">*</span></label>
                                                <textarea name="service_description" class="form-control" rows="5" required placeholder="Provide clear descriptions of what is included in your services and any additional charges (e.g. festivals, extra hours)..."><?= htmlspecialchars($profile['service_description'] ?? '') ?></textarea>
                                            </div>
                                        </div>

                                        <!-- STEP 6: WORKING AREA -->
                                        <div class="form-step-panel" id="step-panel-6">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-map-marker-alt mr-2"></i>Service Working Area</h5>
                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label class="small font-weight-bold text-gray-800">Maximum Travel Distance (in KM) <span class="text-danger">*</span></label>
                                                    <input type="number" name="travel_max_distance" class="form-control" required value="<?= htmlspecialchars($profile['travel_max_distance'] ?? 10) ?>" min="1">
                                                </div>
                                            </div>

                                            <h6 class="font-weight-bold text-gray-800 mt-4 mb-3">Service Areas (List areas and pincodes you serve)</h6>
                                            <div id="serviceAreasList">
                                                <?php if (empty($areas)): ?>
                                                    <div class="row area-row mb-2">
                                                        <div class="col-md-3 form-group">
                                                            <input type="text" name="area_states[]" class="form-control form-control-sm" placeholder="State" value="<?= htmlspecialchars($profile['state'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-3 form-group">
                                                            <input type="text" name="area_cities[]" class="form-control form-control-sm" placeholder="City" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-4 form-group">
                                                            <input type="text" name="area_names[]" class="form-control form-control-sm" placeholder="Area Name/Neighborhood">
                                                        </div>
                                                        <div class="col-md-2 form-group">
                                                            <input type="text" name="area_pincodes[]" class="form-control form-control-sm" placeholder="Pincode">
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($areas as $ar): ?>
                                                        <div class="row area-row mb-2">
                                                            <div class="col-md-3 form-group">
                                                                <input type="text" name="area_states[]" class="form-control form-control-sm" placeholder="State" value="<?= htmlspecialchars($ar['state']) ?>">
                                                            </div>
                                                            <div class="col-md-3 form-group">
                                                                <input type="text" name="area_cities[]" class="form-control form-control-sm" placeholder="City" value="<?= htmlspecialchars($ar['city']) ?>">
                                                            </div>
                                                            <div class="col-md-4 form-group">
                                                                <input type="text" name="area_names[]" class="form-control form-control-sm" placeholder="Area Name/Neighborhood" value="<?= htmlspecialchars($ar['area_name']) ?>">
                                                            </div>
                                                            <div class="col-md-2 form-group">
                                                                <input type="text" name="area_pincodes[]" class="form-control form-control-sm" placeholder="Pincode" value="<?= htmlspecialchars($ar['pincode']) ?>">
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-emerald font-weight-bold" onclick="addAreaRow()"><i class="fas fa-plus mr-1"></i> Add Service Area</button>
                                        </div>

                                        <!-- STEP 7: AVAILABILITY GRID -->
                                        <div class="form-step-panel" id="step-panel-7">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-calendar-alt mr-2"></i>Weekly Working Availability</h5>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Day</th>
                                                            <th>Morning<br><small class="text-muted">(6 AM - 12 PM)</small></th>
                                                            <th>Afternoon<br><small class="text-muted">(12 PM - 5 PM)</small></th>
                                                            <th>Evening<br><small class="text-muted">(5 PM - 9 PM)</small></th>
                                                            <th>Night<br><small class="text-muted">(9 PM - 6 AM)</small></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $avail_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                                        $avail_slots = ['Morning', 'Afternoon', 'Evening', 'Night'];
                                                        foreach ($avail_days as $day):
                                                        ?>
                                                            <tr>
                                                                <td class="font-weight-bold align-middle"><?= $day ?></td>
                                                                <?php foreach ($avail_slots as $slot): ?>
                                                                    <td class="align-middle">
                                                                        <div class="custom-control custom-checkbox d-inline-block">
                                                                            <input type="checkbox" name="avail[<?= $day ?>][<?= $slot ?>]" value="1" class="custom-control-input" id="av-<?= $day ?>-<?= $slot ?>" <?= isset($availability[$day][$slot]) && $availability[$day][$slot] ? 'checked' : '' ?>>
                                                                            <label class="custom-control-label" for="av-<?= $day ?>-<?= $slot ?>"></label>
                                                                        </div>
                                                                    </td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="mt-4 p-3 border rounded bg-light">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" name="emergency_availability" class="custom-control-input" id="emergencyAvail" value="1" <?= ($emergency_availability ?? 0) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label font-weight-bold text-danger" for="emergencyAvail">Enable Emergency Availability (Available 24/7 for urgent staffing requirements)</label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- STEP 8: FINAL PREVIEW REVIEW -->
                                        <div class="form-step-panel" id="step-panel-8">
                                            <h5 class="font-weight-bold text-emerald mb-4"><i class="fas fa-eye mr-2"></i>Review Profile & Submit</h5>
                                            
                                            <div class="row bg-light p-4 rounded border mb-4">
                                                <div class="col-md-3 text-center mb-3">
                                                    <img id="reviewPhoto" src="" class="img-thumbnail rounded-circle" style="width:120px; height:120px; object-fit:cover;">
                                                </div>
                                                <div class="col-md-9">
                                                    <h4 class="font-weight-bold text-emerald mb-1" id="revName">-</h4>
                                                    <p class="text-muted mb-2"><i class="fas fa-envelope mr-1"></i> <span id="revEmail">-</span> | <i class="fas fa-phone mr-1"></i> <span id="revPhone">-</span></p>
                                                    <p class="text-muted"><i class="fas fa-map-marker-alt mr-1"></i> <span id="revAddr">-</span></p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="preview-label">Gender / Date of Birth</div>
                                                    <div class="preview-value" id="revGenderDob">-</div>

                                                    <div class="preview-label">Years of Experience</div>
                                                    <div class="preview-value" id="revExp">-</div>

                                                    <div class="preview-label">Expected Monthly / Hourly Rate</div>
                                                    <div class="preview-value" id="revRates">-</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="preview-label">Preferred Work Type</div>
                                                    <div class="preview-value" id="revWorkType">-</div>

                                                    <div class="preview-label">Working Hours Preference</div>
                                                    <div class="preview-value" id="revHours">-</div>
                                                    
                                                    <div class="preview-label">Languages Spoken</div>
                                                    <div class="preview-value" id="revLangs">-</div>
                                                </div>
                                            </div>
                                            
                                            <div class="preview-label mt-2">Services & Subcategories</div>
                                            <div class="preview-value" id="revServices">-</div>

                                            <div class="preview-label">About Me</div>
                                            <div class="preview-value" id="revAbout">-</div>
                                        </div>

                                        <!-- Navigation Buttons -->
                                        <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                                            <button type="button" class="btn btn-secondary px-4 font-weight-bold" id="prevBtn" onclick="navigateStep(-1)" disabled>
                                                <i class="fas fa-chevron-left mr-2"></i>Previous
                                            </button>
                                            
                                            <div>
                                                <button type="button" class="btn btn-outline-emerald px-4 font-weight-bold mr-2" onclick="saveAsDraft()">
                                                    <i class="fas fa-save mr-2"></i>Save Draft
                                                </button>
                                                <button type="button" class="btn btn-emerald px-4 font-weight-bold" id="nextBtn" onclick="navigateStep(1)">
                                                    Next<i class="fas fa-chevron-right ml-2"></i>
                                                </button>
                                            </div>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- PROVIDER DASHBOARD VIEW -->
                        <div class="row justify-content-center">
                            <div class="col-lg-9">
                                <div class="card shadow-sm border-0 mb-4">
                                    <div class="card-header bg-white border-bottom-0 pt-4 px-5">
                                        <h4 class="font-weight-bold text-emerald"><i class="fas fa-briefcase mr-2"></i>Personal Staff & Maids Dashboard</h4>
                                    </div>
                                    <div class="card-body px-5 pb-5 pt-2">
                                        <div class="row align-items-center p-4 border rounded mb-4" style="background-color: var(--mint-cream);">
                                            <div class="col-md-3 text-center mb-3 mb-md-0">
                                                <img src="<?= !empty($profile['profile_photo']) ? '../../' . htmlspecialchars($profile['profile_photo']) : '../images/user-placeholder.jpg' ?>" class="img-thumbnail rounded-circle" style="width:130px; height:130px; object-fit:cover; border: 4px solid var(--emerald);">
                                            </div>
                                            <div class="col-md-9">
                                                <h4 class="font-weight-bold text-gray-800 mb-1"><?= htmlspecialchars($profile['full_name']) ?></h4>
                                                <div class="d-flex align-items-center flex-wrap mb-2" style="gap: 10px;">
                                                    <?php 
                                                    $st = $profile['status']; 
                                                    $badge = 'badge-secondary';
                                                    if ($st === 'Approved') $badge = 'badge-success';
                                                    elseif ($st === 'Pending') $badge = 'badge-warning';
                                                    elseif ($st === 'Rejected') $badge = 'badge-danger';
                                                    ?>
                                                    <span class="badge <?= $badge ?> px-3 py-1 font-weight-bold" style="font-size:0.8rem;"><?= $st ?></span>
                                                    
                                                    <?php if ($verified_status === 'approved'): ?>
                                                        <span class="badge badge-success px-2 py-1 font-weight-bold"><i class="fas fa-user-check"></i> Identity Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary px-2 py-1"><i class="fas fa-user-clock"></i> ID Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <p class="text-muted mb-2">Completion status of profile details:</p>
                                                <!-- calculate completion percentage -->
                                                <?php 
                                                $comp = 0;
                                                if (!empty($profile['full_name'])) $comp += 15;
                                                if (!empty($profile['profile_photo'])) $comp += 15;
                                                if (!empty($profile['dob'])) $comp += 10;
                                                if (!empty($skills)) $comp += 15;
                                                if (!empty($documents)) $comp += 15;
                                                if (!empty($profile['expected_salary'])) $comp += 15;
                                                if (!empty($areas)) $comp += 15;
                                                $comp = min(100, $comp);
                                                ?>
                                                <div class="progress mb-2" style="height: 15px; border-radius:10px;">
                                                    <div class="progress-bar bg-success font-weight-bold" role="progressbar" style="width: <?= $comp ?>%; font-size: 0.75rem;" aria-valuenow="<?= $comp ?>" aria-valuemin="0" aria-valuemax="100"><?= $comp ?>% Complete</div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($profile['status'] === 'Rejected'): ?>
                                            <div class="alert alert-danger mb-4" style="border-radius:10px;">
                                                <h6 class="font-weight-bold mb-1"><i class="fas fa-times-circle mr-1"></i> Profile Rejection Feedback</h6>
                                                <p class="mb-0 text-gray-800 small"><?= htmlspecialchars($profile['rejection_reason'] ?: 'No details specified.') ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="row text-center mt-4">
                                            <div class="col-md-4 mb-3">
                                                <a href="personal-staff-&-maids.php?action=edit" class="btn btn-emerald btn-block py-3 font-weight-bold shadow-sm">
                                                    <i class="fas fa-edit mr-2"></i>Edit Profile
                                                </a>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <a href="../../frontend-web/pages/services/personal-staff-&-maids.php?profile_id=<?= $profile['id'] ?>" target="_blank" class="btn btn-outline-primary btn-block py-3 font-weight-bold shadow-sm">
                                                    <i class="fas fa-external-link-alt mr-2"></i>View Public Profile
                                                </a>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <button type="button" onclick="confirmDeleteProfile()" class="btn btn-outline-danger btn-block py-3 font-weight-bold shadow-sm">
                                                    <i class="fas fa-trash-alt mr-2"></i>Delete Profile
                                                </button>
                                            </div>
                                        </div>

                                        <hr class="my-4">

                                        <h5 class="font-weight-bold text-gray-800 mb-3"><i class="fas fa-magic mr-1"></i> Quick Management Info</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="border rounded p-3 bg-light">
                                                    <div class="font-weight-bold text-emerald mb-1">Services Specialized In:</div>
                                                    <div class="small">
                                                        <?php 
                                                        $my_subs = $pdo->query("SELECT name FROM `serveasy-subcategories` WHERE id IN (" . implode(',', $selected_subcats) . ")")->fetchAll(PDO::FETCH_COLUMN);
                                                        echo htmlspecialchars(implode(', ', $my_subs));
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="border rounded p-3 bg-light">
                                                    <div class="font-weight-bold text-emerald mb-1">Uploaded Verification Documents:</div>
                                                    <div class="small text-muted">
                                                        Total uploaded: <?= count($documents) ?> document(s)
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Core Scripts -->
    <script src="../../Super%20Admin/vendor/jquery/jquery.min.js"></script>
    <script src="../../Super%20Admin/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notify.js"></script>

    <script>
        function toggleCheckbox(wrapper) {
            let chk = wrapper.querySelector('.subcat-chk');
            chk.checked = !chk.checked;
            
            // Highlight card
            if (chk.checked) {
                wrapper.classList.add('bg-emerald-light', 'border-success');
            } else {
                wrapper.classList.remove('bg-emerald-light', 'border-success');
            }
            
            // Check if at least one checkbox is selected
            let chks = document.querySelectorAll('.subcat-chk:checked');
            document.getElementById('createProfileBtn').disabled = (chks.length === 0);
        }

        function startProfileCreation() {
            // submit subcategory list to action page
            let form = document.getElementById('subcategoryForm');
            let data = $(form).serialize();
            
            // Navigate to create page with parameters
            let url = 'personal-staff-&-maids.php?action=create';
            let params = new URLSearchParams(data);
            
            // Append subcategories in url to pre-select
            window.location = url + '&' + params.toString();
        }

        let currentStep = 1;
        const totalSteps = 8;

        const stepNames = [
            "Step 1: Personal Information",
            "Step 2: Professional Information",
            "Step 3: Skills & Extra Work Abilities",
            "Step 4: Documents Upload",
            "Step 5: Service & Pricing Details",
            "Step 6: Working Area Selection",
            "Step 7: Weekly Availability Grid",
            "Step 8: Final Review & Submit"
        ];

        function navigateStep(dir) {
            // Validate current step before proceeding
            if (dir === 1 && !validateCurrentStep()) {
                return;
            }

            // Hide current panel
            $('#step-panel-' + currentStep).removeClass('active');
            
            // Increment
            currentStep += dir;
            if (currentStep < 1) currentStep = 1;
            if (currentStep > totalSteps) currentStep = totalSteps;

            // Show new panel
            $('#step-panel-' + currentStep).addClass('active');

            // Update indicators
            updateProgress();
            
            // Smooth scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateProgress() {
            // Fill line
            let pct = ((currentStep - 1) / (totalSteps - 1)) * 100;
            $('#progressBarFill').css('width', pct + '%');

            // Nodes styling
            for (let i = 1; i <= totalSteps; i++) {
                let node = $('#step-node-' + i);
                node.removeClass('active completed');
                if (i < currentStep) {
                    node.addClass('completed');
                } else if (i === currentStep) {
                    node.addClass('active');
                }
            }

            // Step Name
            $('#stepNameIndicator').text(stepNames[currentStep - 1]);

            // Nav buttons
            $('#prevBtn').prop('disabled', currentStep === 1);
            
            if (currentStep === totalSteps) {
                $('#nextBtn').html('<i class="fas fa-check mr-2"></i> Submit Profile').removeClass('btn-emerald').addClass('btn-success');
                prepareFinalReview();
            } else {
                $('#nextBtn').html('Next <i class="fas fa-chevron-right ml-2"></i>').removeClass('btn-success').addClass('btn-emerald');
            }
        }

        function validateCurrentStep() {
            // Basic required check
            let currentPanel = $('#step-panel-' + currentStep);
            let valid = true;
            
            currentPanel.find('input[required], select[required], textarea[required]').each(function() {
                if ($(this).val() === '') {
                    valid = false;
                    $(this).addClass('border-danger');
                    Notify.warning($(this).prev('label').text().replace('*', '').trim() + ' is required.');
                } else {
                    $(this).removeClass('border-danger');
                }
            });

            return valid;
        }

        function calculateAge() {
            let dob = $('#dobField').val();
            if (dob) {
                let birthday = new Date(dob);
                let ageDifMs = Date.now() - birthday.getTime();
                let ageDate = new Date(ageDifMs);
                let calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);
                $('#ageField').val(calculatedAge);
            }
        }

        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#' + previewId).attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function addExperienceRow() {
            let html = `
                <div class="row exp-row mb-3 p-3 border rounded bg-light">
                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold text-gray-700">Employer Name</label>
                        <input type="text" name="exp_employer[]" class="form-control form-control-sm" placeholder="Employer Name">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold text-gray-700">Job Title / Duties</label>
                        <input type="text" name="exp_title[]" class="form-control form-control-sm" placeholder="e.g. House Cook">
                    </div>
                    <div class="col-md-2 form-group">
                        <label class="small font-weight-bold text-gray-700">Start Date</label>
                        <input type="date" name="exp_start[]" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2 form-group">
                        <label class="small font-weight-bold text-gray-700">End Date</label>
                        <input type="date" name="exp_end[]" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-12 form-group mb-0 d-flex justify-content-between align-items-center" style="gap:15px;">
                        <div class="flex-grow-1">
                            <label class="small font-weight-bold text-gray-700">Details / Feedback Reference</label>
                            <input type="text" name="exp_desc[]" class="form-control form-control-sm" placeholder="Reference contacts...">
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold mt-4" onclick="this.closest('.exp-row').remove()"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
            $('#experienceList').append(html);
        }

        function addAreaRow() {
            let html = `
                <div class="row area-row mb-2">
                    <div class="col-md-3 form-group">
                        <input type="text" name="area_states[]" class="form-control form-control-sm" placeholder="State" value="${$('input[name="state"]').val() || ''}">
                    </div>
                    <div class="col-md-3 form-group">
                        <input type="text" name="area_cities[]" class="form-control form-control-sm" placeholder="City" value="${$('input[name="city"]').val() || ''}">
                    </div>
                    <div class="col-md-4 form-group">
                        <input type="text" name="area_names[]" class="form-control form-control-sm" placeholder="Area Name/Neighborhood">
                    </div>
                    <div class="col-md-2 form-group d-flex align-items-center" style="gap:10px;">
                        <input type="text" name="area_pincodes[]" class="form-control form-control-sm" placeholder="Pincode">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.area-row').remove()"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
            $('#serviceAreasList').append(html);
        }

        function addCustomSkill() {
            let val = $('#customSkillInput').val().trim();
            if (val === '') return;
            
            let html = `
                <div class="badge badge-primary p-2 d-flex align-items-center" style="gap: 8px; font-size: 0.85rem; border-radius: 6px; background-color: var(--emerald);">
                    <span>${val}</span>
                    <input type="hidden" name="custom_skills[]" value="${val}">
                    <i class="fas fa-times-circle" style="cursor:pointer;" onclick="this.parentElement.remove()"></i>
                </div>
            `;
            $('#customSkillsList').append(html);
            $('#customSkillInput').val('');
        }

        function prepareFinalReview() {
            $('#reviewPhoto').attr('src', $('#photoPreview').attr('src'));
            $('#revName').text($('input[name="full_name"]').val());
            $('#revEmail').text($('input[name="email"]').val() || 'N/A');
            $('#revPhone').text($('input[name="phone_number"]').val());
            
            let addr = $('textarea[name="current_address"]').val() + ', ' + $('input[name="city"]').val() + ', ' + $('input[name="state"]').val() + ' - ' + $('input[name="pincode"]').val();
            $('#revAddr').text(addr);
            
            $('#revGenderDob').text($('select[name="gender"]').val() + ' | DOB: ' + $('input[name="dob"]').val());
            $('#revExp').text($('input[name="years_of_experience"]').val() + ' Years');
            
            let types = [];
            $('input[name="preferred_work_type[]"]:checked').each(function() {
                types.push($(this).val());
            });
            $('#revWorkType').text(types.join(', ') || 'None selected');
            $('#revHours').text($('input[name="working_hours"]').val() || 'Flexible');
            
            let lang = [];
            $('input[name="languages[]"]:checked').each(function() {
                lang.push($(this).val());
            });
            $('#revLangs').text(lang.join(', ') || 'N/A');

            let rates = 'Monthly base: ₹' + ($('input[name="expected_salary"]').val() || '0');
            if ($('input[name="hourly_rate"]').val()) rates += ' | Hourly: ₹' + $('input[name="hourly_rate"]').val();
            if ($('input[name="daily_rate"]').val()) rates += ' | Daily: ₹' + $('input[name="daily_rate"]').val();
            $('#revRates').text(rates);

            $('#revAbout').text($('textarea[name="about_me"]').val());
            
            // Show list of selected services in reviews
            let subcats = [];
            // Get checked subcategories or hidden subcategories
            $('input[name="subcategories[]"]').each(function() {
                let val = $(this).val();
                let subcatObj = <?= json_encode($subcategories) ?>;
                let matched = subcatObj.find(s => s.id == val);
                if (matched) subcats.push(matched.name);
            });
            $('#revServices').text(subcats.join(', '));
        }

        function saveAsDraft() {
            submitProfileData('Draft');
        }

        // On last step, clicking next means submitting
        $('#nextBtn').on('click', function() {
            if (currentStep === totalSteps) {
                submitProfileData('Pending');
            }
        });

        function submitProfileData(statusVal) {
            $('#profileStatusField').value = statusVal;
            
            let form = document.getElementById('maidProfileForm');
            let formData = new FormData(form);
            formData.append('ajax_action', 'save_profile');
            formData.append('profile_status', statusVal);

            let subBtn = statusVal === 'Draft' ? 'Save Draft' : 'Submit Profile';
            Notify.info('Saving profile details...', { title: subBtn });

            $.ajax({
                url: 'personal-staff-&-maids.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(res) {
                    if (res.status === 'success') {
                        Notify.success(res.message);
                        setTimeout(function() {
                            window.location = 'personal-staff-&-maids.php';
                        }, 1500);
                    } else {
                        Notify.error(res.message);
                    }
                },
                error: function() {
                    Notify.error('Server connection error.');
                }
            });
        }

        function confirmDeleteProfile() {
            Notify.confirm('Are you absolutely sure you want to delete your profile? All profile data, experience, skills, and documents will be permanently removed.', {
                type: 'danger',
                title: 'Delete Profile'
            }).then(function(confirmed) {
                if (confirmed) {
                    $.post('personal-staff-&-maids.php', { ajax_action: 'delete_profile' }, function(res) {
                        if (res.status === 'success') {
                            Notify.success(res.message);
                            setTimeout(function() {
                                window.location = 'personal-staff-&-maids.php';
                            }, 1500);
                        } else {
                            Notify.error(res.message);
                        }
                    }, 'json');
                }
            });
        }

        // Initial age calculation
        $(document).ready(function() {
            calculateAge();
            if (window.location.search.includes('action=')) {
                updateProgress();
            }
        });
    </script>
</body>

</html>
