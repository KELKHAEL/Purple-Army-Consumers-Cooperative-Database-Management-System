<?php 
session_start();
include 'db.php'; 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert_title'] = "Invalid Access";
    $_SESSION['alert_message'] = "No valid Member ID was provided.";
    $_SESSION['alert_type'] = "error";
    header("Location: index.php");
    exit();
}

$member_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_result = $stmt->get_result();

if ($member_result->num_rows === 0) {
    $_SESSION['alert_title'] = "Record Not Found";
    $_SESSION['alert_message'] = "The requested member profile could not be found in the database.";
    $_SESSION['alert_type'] = "error";
    header("Location: index.php");
    exit();
}
$member = $member_result->fetch_assoc();
$stmt->close();

$stmt_ben = $conn->prepare("SELECT * FROM beneficiaries WHERE member_id = ?");
$stmt_ben->bind_param("i", $member_id);
$stmt_ben->execute();
$beneficiaries_result = $stmt_ben->get_result();
$beneficiaries = [];
while($b_row = $beneficiaries_result->fetch_assoc()) {
    $beneficiaries[] = $b_row;
}
$stmt_ben->close();

function fetchConfig($conn, $table, $default_fallback = []) {
    $data = [];
    try {
        $res = $conn->query("SELECT name FROM $table"); 
        if ($res && $res->num_rows > 0) {
            while($row = $res->fetch_assoc()) {
                $data[] = $row['name'];
            }
        } else {
            return $default_fallback; 
        }
    } catch (Exception $e) {
        return $default_fallback; 
    }
    return $data;
}

// Sort Occupations (Alphabetical, force 'Others' to bottom)
$raw_occupations = fetchConfig($conn, 'config_occupations', ['Private Employee', 'Gov\'t Employee', 'Self-Employed', 'Others']);
$others_arr = [];
$regs_arr = [];
foreach($raw_occupations as $o) {
    if(strtolower(trim($o)) === 'others') {
        $others_arr[] = $o;
    } else {
        $regs_arr[] = $o;
    }
}
natcasesort($regs_arr);
if(empty($others_arr)) $others_arr[] = 'Others';
$occupations = array_merge($regs_arr, $others_arr);

// Check if member has a custom "Others" occupation
$is_other_occ = !empty($member['occupation']) && !in_array($member['occupation'], $occupations);

// Sort Incomes (Mathematical Highest to Lowest)
$incomes = fetchConfig($conn, 'config_monthly_income', ['Below 5,000', '5,000 - 9,999', '10,000+']);
usort($incomes, function($a, $b) {
    preg_match_all('/\d+/', str_replace(',', '', $a), $ma);
    preg_match_all('/\d+/', str_replace(',', '', $b), $mb);
    $valA = !empty($ma[0]) ? (int)$ma[0][0] : 0;
    $valB = !empty($mb[0]) ? (int)$mb[0][0] : 0;
    
    if ($valA === $valB) {
        if (stripos($a, 'below') !== false) return 1;
        if (stripos($b, 'below') !== false) return -1;
    }
    return $valB <=> $valA;
});

$civil_statuses = fetchConfig($conn, 'config_civil_status', ['Single', 'Married', 'Widowed']);
sort($civil_statuses);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - <?= htmlspecialchars($member['last_name']) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    colors: { primary: '#6a1b9a', primaryDark: '#570591', }
                }
            }
        }
    </script>
    <style>
        .radio-card input:checked + div {
            background-color: #f3e8ff; 
            border-color: #6a1b9a;
            color: #570591;
            font-weight: 600;
        }
        form.uppercase-form input[type="text"],
        form.uppercase-form select,
        form.uppercase-form textarea,
        form.uppercase-form option,
        form.uppercase-form .radio-card div {
            text-transform: uppercase;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-hidden">

    <?php include 'cover_page.php'; ?>

    <div id="customAlertModal" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60 backdrop-blur-sm transition-opacity"></div>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all z-10 flex flex-col translate-y-4 opacity-0" id="customAlertBox">
            <div id="customAlertHeader" class="px-6 py-4 flex items-center gap-3 border-b">
                <i id="customAlertIcon" class="fas fa-exclamation-circle text-2xl"></i>
                <h3 id="customAlertTitle" class="text-lg font-bold tracking-tight">Alert</h3>
            </div>
            <div class="p-6 text-gray-600 text-sm leading-relaxed" id="customAlertMessage"></div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end">
                <button id="customAlertBtn" class="bg-primary hover:bg-primaryDark text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md">OK</button>
            </div>
        </div>
    </div>

    <div class="flex h-screen w-full">

        <div id="mobile-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden md:hidden transition-opacity" onclick="toggleSidebar()"></div>

        <aside id="sidebar" class="bg-white w-72 border-r border-gray-200 flex flex-col transition-transform transform -translate-x-full md:translate-x-0 fixed md:relative z-50 h-full shadow-lg md:shadow-none">
            <div class="p-6 flex items-center justify-center border-b border-gray-100 relative">
                
                <a href="#" onclick="showSplashScreen(); return false;" class="block">
                    <img src="img/purplearmy_logo-removebg.png" alt="Coop Logo" class="w-40 md:w-52 h-auto object-contain py-2 drop-shadow-sm transition-transform hover:scale-105">
                </a>

                <button class="absolute top-4 right-4 md:hidden text-gray-400 hover:text-gray-800" onclick="toggleSidebar()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4 flex flex-col gap-1">
                <a href="index.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
                    <i class="fas fa-users w-6"></i> MEMBERSHIP DIRECTORY
                </a>
                <a href="member_shares.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-hand-holding-usd w-6"></i> MEMBER SHARES
                </a>
                <a href="transactions.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-receipt w-6"></i> SALES & PURCHASE LOGS
                </a>
                <a href="inventory.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-boxes w-6"></i> INVENTORY
                </a>
                <a href="pos.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-shopping-cart w-6"></i> SELL / OUTSOURCE
                </a>
                <a href="outsourcing_report.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-chart-line w-6"></i> OUTSOURCING LOGS
                </a>
                <a href="database_management.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-database w-6"></i> DATABASE SETTINGS
                </a>
                <a href="activity_logs.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-clock-rotate-left w-6"></i> ACTIVITY LOGS
                </a>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
            
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 flex justify-between items-center z-10">
                <div class="flex items-center gap-4">
                    <button class="text-gray-500 focus:outline-none md:hidden hover:text-primary" onclick="toggleSidebar()">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Edit Membership Record</h1>
                </div>
                <a href="index.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm hidden sm:flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> CANCEL
                </a>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                
                <form action="process_edit_membership.php" method="POST" class="max-w-6xl mx-auto pb-12 uppercase-form">
                    
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6">
                        <h4 class="text-lg font-bold text-primary border-b border-gray-100 pb-3 mb-6"><i class="fas fa-user-circle mr-2"></i>Personal Information</h4>
                        
                        <div class="mb-6 w-full md:w-1/3">
                            <label class="block text-sm font-bold text-primary mb-1">Form ID (Optional)</label>
                            <input type="text" name="form_id" value="<?= htmlspecialchars($member['form_id'] ?? '') ?>" class="w-full rounded-md border border-purple-300 bg-purple-50 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name (Surname) <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($member['last_name']) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($member['first_name']) ?>" required class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                <input type="text" name="middle_name" value="<?= htmlspecialchars($member['middle_name'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?= htmlspecialchars($member['date_of_birth'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Birth Place</label>
                                <input type="text" name="birth_place" value="<?= htmlspecialchars($member['birth_place'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Civil Status</label>
                                <select name="civil_status" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all bg-white">
                                    <option value="">Select Status</option>
                                    <?php foreach($civil_statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= ($member['civil_status'] == $status) ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Religion</label>
                                <input type="text" name="religion" value="<?= htmlspecialchars($member['religion'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sex</label>
                                <select name="sex" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all bg-white">
                                    <option value="">Select Sex</option>
                                    <option value="MALE" <?= ($member['sex'] == 'MALE') ? 'selected' : '' ?>>Male</option>
                                    <option value="FEMALE" <?= ($member['sex'] == 'FEMALE') ? 'selected' : '' ?>>Female</option>
                                    <option value="RATHER NOT SAY" <?= ($member['sex'] == 'RATHER NOT SAY') ? 'selected' : '' ?>>Rather Not Say</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tribe</label>
                                <input type="text" name="tribe" value="<?= htmlspecialchars($member['tribe'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">SSS / GSIS No.</label>
                                <input type="text" name="sss_gsis_no" value="<?= htmlspecialchars($member['sss_gsis_no'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">TIN No.</label>
                                <input type="text" name="tin_no" value="<?= htmlspecialchars($member['tin_no'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>

                            <div class="md:col-span-2 lg:col-span-3 grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div class="md:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                                    <input type="text" name="postal_code" value="<?= htmlspecialchars($member['postal_code'] ?? '') ?>" maxlength="4" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Home Address</label>
                                    <input type="text" name="address" value="<?= htmlspecialchars($member['address'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                            </div>

                            <div class="md:col-span-2 lg:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Business / Office Address</label>
                                <input type="text" name="business_office_address" value="<?= htmlspecialchars($member['business_office_address'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                            </div>

                            <div class="md:col-span-2 lg:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Educational Attainment</label>
                                    <input type="text" name="educational_attainment" value="<?= htmlspecialchars($member['educational_attainment'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Present Employment / Business</label>
                                    <input type="text" name="present_employment_business" value="<?= htmlspecialchars($member['present_employment_business'] ?? '') ?>" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6">
                        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-6">
                            <h4 class="text-lg font-bold text-primary"><i class="fas fa-users-cog mr-2"></i>Beneficiaries <span class="text-sm font-normal text-gray-400 ml-2">(Max 20)</span></h4>
                            <button type="button" id="addBenBtn" class="bg-purple-100 text-primary hover:bg-purple-200 font-semibold py-1.5 px-3 rounded-md text-sm transition-colors shadow-sm"><i class="fas fa-plus mr-1"></i> Add</button>
                        </div>
                        
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap" id="beneficiaryTable">
                                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">Last Name</th>
                                        <th class="px-4 py-3 font-semibold">First Name</th>
                                        <th class="px-4 py-3 font-semibold">Middle Name</th>
                                        <th class="px-4 py-3 font-semibold">Date of Birth</th>
                                        <th class="px-4 py-3 font-semibold">Relationship</th>
                                        <th class="px-4 py-3 font-semibold text-center w-10">Remove</th>
                                    </tr>
                                </thead>
                                <tbody id="ben-tbody" class="divide-y divide-gray-100">
                                    <?php if(count($beneficiaries) > 0): ?>
                                        <?php foreach($beneficiaries as $ben): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <input type="hidden" name="existing_ben_ids[]" value="<?= $ben['beneficiary_id'] ?>">
                                                <td class="px-4 py-2"><input type="text" name="ben_last_name[]" value="<?= htmlspecialchars($ben['last_name']) ?>" required class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                                                <td class="px-4 py-2"><input type="text" name="ben_first_name[]" value="<?= htmlspecialchars($ben['first_name']) ?>" required class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                                                <td class="px-4 py-2"><input type="text" name="ben_middle_name[]" value="<?= htmlspecialchars($ben['middle_name'] ?? '') ?>" class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                                                <td class="px-4 py-2"><input type="date" name="ben_dob[]" value="<?= htmlspecialchars($ben['date_of_birth'] ?? '') ?>" class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border text-gray-600"></td>
                                                <td class="px-4 py-2"><input type="text" name="ben_rel[]" value="<?= htmlspecialchars($ben['relationship'] ?? '') ?>" required class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                                                <td class="px-4 py-2 text-center">
                                                    <button type="button" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 rounded-md p-1.5 transition-colors" title="Remove" onclick="removeRow(this)">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <div id="emptyBenState" class="p-6 text-center text-gray-400 text-sm" style="<?= count($beneficiaries) > 0 ? 'display:none;' : '' ?>">No beneficiaries added yet.</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8 mb-6">
                        <h4 class="text-lg font-bold text-primary border-b border-gray-100 pb-3 mb-6"><i class="fas fa-briefcase mr-2"></i>Occupation & Income</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Occupation Status</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <?php foreach($occupations as $occ): ?>
                                        <?php 
                                        $checked = '';
                                        if (strtolower($occ) === 'others' && $is_other_occ) {
                                            $checked = 'checked';
                                        } elseif ($member['occupation'] === $occ) {
                                            $checked = 'checked';
                                        }
                                        ?>
                                        <label class="radio-card cursor-pointer relative">
                                            <input type="radio" name="occupation" value="<?= htmlspecialchars($occ) ?>" class="peer sr-only occupation-radio" <?= $checked ?>>
                                            <div class="rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-600 hover:bg-gray-50 transition-all text-center">
                                                <?= htmlspecialchars($occ) ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div id="other_occ_container" class="<?= $is_other_occ ? '' : 'hidden' ?> mt-3">
                                    <input type="text" id="other_occ_input" value="<?= $is_other_occ ? htmlspecialchars($member['occupation']) : '' ?>" placeholder="Please specify your occupation" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Estimated Monthly Income</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <?php foreach($incomes as $inc): ?>
                                        <label class="radio-card cursor-pointer relative">
                                            <input type="radio" name="monthly_income" value="<?= htmlspecialchars($inc) ?>" class="peer sr-only" <?= ($member['monthly_income'] == $inc) ? 'checked' : '' ?>>
                                            <div class="rounded-md border border-gray-200 px-3 py-3 text-sm text-gray-600 hover:bg-gray-50 transition-all text-center">
                                                <?= htmlspecialchars($inc) ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="flex justify-end mt-8">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-transform transform hover:-translate-y-0.5 text-lg w-full md:w-auto">
                            <i class="fas fa-save mr-2"></i> UPDATE MEMBERSHIP RECORD
                        </button>
                    </div>

                </form>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        let alertRedirectUrl = null;

        function showCustomAlert(title, message, type = 'error', redirectUrl = null) {
            const modal = document.getElementById('customAlertModal');
            const box = document.getElementById('customAlertBox');
            const titleEl = document.getElementById('customAlertTitle');
            const msgEl = document.getElementById('customAlertMessage');
            const iconEl = document.getElementById('customAlertIcon');
            const headerEl = document.getElementById('customAlertHeader');
            const btnEl = document.getElementById('customAlertBtn');

            titleEl.innerText = title;
            msgEl.innerHTML = message;
            alertRedirectUrl = redirectUrl;

            if (type === 'success') {
                iconEl.className = 'fas fa-check-circle text-2xl text-green-500';
                headerEl.className = 'px-6 py-4 flex items-center gap-3 border-b bg-green-50 border-green-100';
                btnEl.className = 'bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md';
            } else if (type === 'info') {
                iconEl.className = 'fas fa-info-circle text-2xl text-blue-500';
                headerEl.className = 'px-6 py-4 flex items-center gap-3 border-b bg-blue-50 border-blue-100';
                btnEl.className = 'bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md';
            } else {
                iconEl.className = 'fas fa-exclamation-circle text-2xl text-red-500';
                headerEl.className = 'px-6 py-4 flex items-center gap-3 border-b bg-red-50 border-red-100';
                btnEl.className = 'bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-md';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => {
                box.classList.remove('translate-y-4', 'opacity-0');
                box.classList.add('translate-y-0', 'opacity-100');
            }, 10);
        }

        document.getElementById('customAlertBtn').addEventListener('click', function() {
            const modal = document.getElementById('customAlertModal');
            const box = document.getElementById('customAlertBox');
            
            box.classList.remove('translate-y-0', 'opacity-100');
            box.classList.add('translate-y-4', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                if (alertRedirectUrl) {
                    window.location.href = alertRedirectUrl;
                }
            }, 300);
        });

        // Catch Session Alerts
        <?php if (isset($_SESSION['alert_message'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showCustomAlert(
                    "<?= addslashes($_SESSION['alert_title']) ?>", 
                    "<?= addslashes($_SESSION['alert_message']) ?>", 
                    "<?= addslashes($_SESSION['alert_type']) ?>"
                );
            });
            <?php 
            unset($_SESSION['alert_title']);
            unset($_SESSION['alert_message']);
            unset($_SESSION['alert_type']);
            ?>
        <?php endif; ?>

        // Toggle Custom Occupation Input Box
        document.querySelectorAll('.occupation-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const otherContainer = document.getElementById('other_occ_container');
                const otherInput = document.getElementById('other_occ_input');
                if (this.value.toLowerCase() === 'others') {
                    otherContainer.classList.remove('hidden');
                    otherInput.setAttribute('required', 'required');
                } else {
                    otherContainer.classList.add('hidden');
                    otherInput.removeAttribute('required');
                }
            });
        });

        // Intercept Form Submit to override "Others" with custom text
        document.querySelector('form').addEventListener('submit', function(e) {
            const selectedOcc = document.querySelector('.occupation-radio:checked');
            if (selectedOcc && selectedOcc.value.toLowerCase() === 'others') {
                const otherInput = document.getElementById('other_occ_input');
                if(otherInput && otherInput.value.trim() !== '') {
                    selectedOcc.value = otherInput.value.trim().toUpperCase();
                }
            }
        });

        const addBtn = document.getElementById('addBenBtn');
        const tbody = document.getElementById('ben-tbody');
        const emptyState = document.getElementById('emptyBenState');
        let rowCount = <?= count($beneficiaries) ?>;

        addBtn.addEventListener('click', function() {
            if(rowCount >= 20) {
                showCustomAlert('Limit Reached', 'You can only add a maximum of 20 beneficiaries per member.', 'error');
                return;
            }
            
            emptyState.style.display = 'none';

            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 transition-colors";
            
            tr.innerHTML = `
                <input type="hidden" name="existing_ben_ids[]" value="new">
                <td class="px-4 py-2"><input type="text" name="ben_last_name[]" placeholder="Last Name" required class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                <td class="px-4 py-2"><input type="text" name="ben_first_name[]" placeholder="First Name" required class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                <td class="px-4 py-2"><input type="text" name="ben_middle_name[]" placeholder="M.I." class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                <td class="px-4 py-2"><input type="date" name="ben_dob[]" class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border text-gray-600"></td>
                <td class="px-4 py-2"><input type="text" name="ben_rel[]" placeholder="e.g. Spouse" required class="w-full rounded border-gray-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none border"></td>
                <td class="px-4 py-2 text-center">
                    <button type="button" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 rounded-md p-1.5 transition-colors" title="Remove" onclick="removeRow(this)">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            rowCount++;
        });

        function removeRow(btn) {
            btn.closest('tr').remove();
            rowCount--;
            if (rowCount === 0) {
                emptyState.style.display = 'block';
            }
        }
    </script>
</body>
</html>
