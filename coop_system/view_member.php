<?php 
session_start();
include 'db.php'; 

// Fetch the member ID from the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert_title'] = "Invalid Access";
    $_SESSION['alert_message'] = "No valid Member ID was provided.";
    $_SESSION['alert_type'] = "error";
    header("Location: index.php");
    exit();
}

$member_id = (int)$_GET['id'];

// 1. Fetch Member Data (same fields imported by import_excel.php)
$stmt = $conn->prepare("SELECT member_id, form_id, first_name, middle_name, last_name, date_of_birth, birth_place, civil_status, religion, sex, tribe, sss_gsis_no, tin_no, postal_code, address, business_office_address, educational_attainment, present_employment_business, occupation, monthly_income FROM members WHERE member_id = ?");
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

// 2. Fetch Beneficiaries Data (same fields imported by import_excel.php)
$stmt_ben = $conn->prepare("SELECT beneficiary_id, member_id, first_name, middle_name, last_name, date_of_birth, relationship FROM beneficiaries WHERE member_id = ?");
$stmt_ben->bind_param("i", $member_id);
$stmt_ben->execute();
$beneficiaries_result = $stmt_ben->get_result();
$beneficiaries = [];
while($b_row = $beneficiaries_result->fetch_assoc()) {
    $beneficiaries[] = $b_row;
}
$stmt_ben->close();

// 3. Fetch Transactions Data
$member_transactions = [];
try {
    $stmt_t = $conn->prepare("SELECT * FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC");
    if ($stmt_t) {
        $stmt_t->bind_param("i", $member_id);
        $stmt_t->execute();
        $trans_result = $stmt_t->get_result();
        while($t_row = $trans_result->fetch_assoc()) {
            $member_transactions[] = $t_row;
        }
        $stmt_t->close();
    }
} catch (Exception $e) { /* Table might not be upgraded yet */ }

// Formatted Data
$formatted_id = !empty($member['form_id']) ? htmlspecialchars($member['form_id']) : '';
$dob = !empty($member['date_of_birth']) ? date('F d, Y', strtotime($member['date_of_birth'])) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Member - <?= htmlspecialchars($member['last_name']) ?></title>
    
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
        .a4-paper {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 15mm;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            font-family: Arial, sans-serif;
            color: #000;
            position: relative;
        }

        .coop-header-container { 
            position: relative; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #000; 
            min-height: 110px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .coop-header-logo { position: absolute; left: 0; top: 0; width: 85px; height: auto; }
        .coop-header-text { text-align: center; }
        .coop-header-text h2 { margin: auto; font-family: Arial, sans-serif; font-size: 15px; font-weight: 800; text-transform: uppercase; }
        .coop-header-text h5 { margin: 2px 0; font-size: 11px; font-weight: normal; }
        .photo-box { position: absolute; right: 0; top: 0; width: 1in; height: 1in; border: 1px solid #000; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #555; text-align: center; }
        
        .title-block { text-align: center; margin-top: 10px; margin-bottom: 15px; position: relative; }
        .form-no-text { position: absolute; left: 0; top: 0; font-weight: bold; font-size: 14px; text-align: left; }
        .form-id-display { text-decoration: underline; font-weight: normal; display: inline-block; min-width: 80px; }
        .title-block h3 { margin: 0; font-size: 20px; letter-spacing: 1px; text-decoration: underline; }

        .section-header { background-color: #570591; color: white; padding: 5px 10px; font-size: 14px; font-weight: bold; text-transform: uppercase; margin-top: 20px;}
        .form-row { display: flex; border-left: 1px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; page-break-inside: avoid; }
        .form-row:first-of-type { border-top: 1px solid #000; }
        .form-col { flex: 1; padding: 5px 8px; border-right: 1px solid #000; display: flex; flex-direction: column; }
        .form-col:last-child { border-right: none; }
        .field-label { font-size: 10px; color: #555; text-transform: uppercase; margin-bottom: 3px; }
        .field-value { font-size: 14px; font-weight: bold; text-transform: uppercase; min-height: 18px;}

        .paper-table { width: 100%; border-collapse: collapse; margin-top: 5px; border: 1px solid #000;}
        .paper-table th, .paper-table td { border: 1px solid #000; padding: 6px; text-align: left; font-size: 12px; }
        .paper-table th { background-color: #f0f0f0; text-transform: uppercase; font-size: 10px; }
        .paper-table td strong { text-transform: uppercase; }
        .paper-table tr { page-break-inside: avoid; }
        #transactionHistoryList .transaction-card .text-gray-700.font-mono {
            text-transform: uppercase;
        }

        .certification-section { margin-top: 40px; font-size: 14px; page-break-inside: avoid; }
        .certification-text { text-indent: 40px; line-height: 1.6; margin-bottom: 40px; }
        .signature-wrapper { display: flex; justify-content: flex-end; }
        .sig-box { width: 300px; text-align: center; }
        .sig-line { border-bottom: 1px solid #000; height: 20px; margin-bottom: 5px; }
        .sig-label { font-size: 12px; font-weight: bold; text-transform: uppercase; }

        .history-mobile-backdrop {
            display: none;
        }

        .transaction-history-panel.mobile-expanded {
            position: fixed !important;
            inset: 0 !important;
            z-index: 80 !important;
            width: 100vw !important;
            height: 100vh !important;
            max-height: none !important;
            margin: 0 !important;
            border-radius: 0 !important;
            overflow-y: auto !important;
            display: flex !important;
            flex-direction: column !important;
            background: white !important;
        }

        .transaction-history-panel.mobile-expanded .history-mobile-backdrop {
            display: block;
        }

        .transaction-history-panel.mobile-expanded #transactionHistoryList {
            flex: 0 0 auto !important;
            max-height: none !important;
            overflow: visible !important;
        }

        .transaction-history-panel.mobile-expanded .history-expand-trigger {
            display: none !important;
        }

        .transaction-history-panel.mobile-expanded .history-collapse-trigger {
            display: inline-flex !important;
        }

        @media (min-width: 1280px) {
            .transaction-history-panel {
                max-height: none !important;
                height: auto !important;
                position: static !important;
                display: flex !important;
                flex-direction: column !important;
            }

            #transactionHistoryList {
                overflow: visible !important;
                max-height: none !important;
                flex: 0 0 auto !important;
            }
        }

        @media (max-width: 767px) {
            html, body {
                height: auto !important;
                min-height: 100% !important;
                overflow-y: auto !important;
            }

            .flex.h-screen.w-full {
                height: auto !important;
                min-height: 100vh !important;
            }

            .main-content {
                height: auto !important;
                min-height: 100vh !important;
                overflow: visible !important;
            }

            .scroll-wrapper {
                overflow: visible !important;
                min-height: 0 !important;
            }

            .a4-paper {
                width: 100% !important;
                min-height: 0 !important;
                padding: 10mm !important;
            }

            .overflow-x-auto {
                overflow-x: auto !important;
            }

            .transaction-history-panel {
                max-height: none !important;
            }
        }

        /* HIDE UI ELEMENTS & PREVENT BLANK PAGES WHEN PRINTING */
        @media print {
            @page { size: A4 portrait; margin: 15mm; }

            body, html { background: white !important; margin: 0 !important; padding: 0 !important; height: auto !important; }
            
            /* THIS RULE ENSURES THE TRANSACTION HISTORY WIDGET WILL NOT BE PRINTED */
            .no-print { display: none !important; }
            
            .h-screen { height: auto !important; min-height: auto !important; }
            .main-content, .scroll-wrapper { 
                padding: 0 !important; 
                margin: 0 !important; 
                overflow: visible !important; 
                height: auto !important; 
                min-height: auto !important;
                display: block !important; 
            }
            
            .a4-paper { 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                min-height: 0 !important; 
                box-shadow: none !important; 
                border: none !important; 
            }

            .section-header { margin-top: 15px; font-size: 12px; padding: 4px 8px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .form-col { padding: 4px 6px; }
            .field-value { font-size: 12px; min-height: 14px; }
            .paper-table th, .paper-table td { padding: 4px; font-size: 11px; }
            .certification-section { margin-top: 30px; font-size: 13px; }
            .certification-text { margin-bottom: 30px; }
        }
    </style>
</head>
    <body class="bg-gray-100 text-gray-800 font-sans antialiased overflow-x-hidden">

    <?php include 'cover_page.php'; ?>

    <div id="history-mobile-backdrop" class="history-mobile-backdrop fixed inset-0 bg-black bg-opacity-50 no-print" style="z-index: 70;" onclick="toggleTransactionHistoryMobile(false)"></div>

    <div class="flex h-screen w-full">

        <div id="mobile-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden md:hidden transition-opacity no-print" onclick="toggleSidebar()"></div>

        <aside id="sidebar" class="bg-white w-72 border-r border-gray-200 flex flex-col transition-transform transform -translate-x-full md:translate-x-0 fixed md:relative z-50 h-full shadow-lg md:shadow-none no-print">
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

        <main class="main-content flex-1 flex flex-col h-screen overflow-hidden relative w-full min-h-0">
            
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 flex justify-between items-center z-10 no-print">
                <div class="flex items-center gap-4">
                    <button class="text-gray-500 focus:outline-none md:hidden hover:text-primary" onclick="toggleSidebar()">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">View Record</h1>
                </div>
                
                <div class="flex gap-3">
                    <a href="index.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm hidden sm:flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> BACK
                    </a>
                    <button onclick="window.print()" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm flex items-center">
                        <i class="fas fa-print mr-2"></i> PRINT FORM
                    </button>
                </div>
            </header>

            <div class="scroll-wrapper flex-1 overflow-auto min-h-0 p-4 md:p-8 bg-gray-200 block scroll-smooth">
                
                <div class="flex flex-col xl:flex-row gap-8 justify-center items-start w-full max-w-[1400px] mx-auto">
                    
                    <div class="overflow-x-auto w-full xl:w-auto flex justify-center print:w-full">
                        <div class="a4-paper m-0 shrink-0">
                            
                            <div class="coop-header-container">
                                <img src="img/purplearmy_logo-removebg.png" alt="Purple Army Logo" class="coop-header-logo">
                                
                                <div class="coop-header-text">
                                    <h2>PURPLE ARMY CONSUMERS COOPERATIVE</h2>
                                    <h5>428 A Soriano Highway, Amaya II, Tanza, Cavite</h5>
                                    <h5>purplearmycooperative@gmail.com</h5>
                                    <h5>09338243704/09569447343</h5>
                                </div>
                                
                                <div class="photo-box">1" x 1"<br>Photo</div>
                            </div>

                            <div class="title-block">
                                <div class="form-no-text">Form No. <span class="form-id-display"><?= $formatted_id ?></span></div>
                                <h3>MEMBERSHIP PROFILE</h3>
                            </div>

                            <div class="section-header">I. Personal Information</div>
                            <div class="form-row" style="border-top: 1px solid #000;">
                                <div class="form-col"><span class="field-label">Last Name (Surname)</span><span class="field-value"><?= htmlspecialchars($member['last_name']) ?></span></div>
                                <div class="form-col"><span class="field-label">First Name</span><span class="field-value"><?= htmlspecialchars($member['first_name']) ?></span></div>
                                <div class="form-col"><span class="field-label">Middle Name</span><span class="field-value"><?= htmlspecialchars($member['middle_name'] ?? '') ?></span></div>
                            </div>
                            <div class="form-row">
                                <div class="form-col"><span class="field-label">Date of Birth</span><span class="field-value"><?= $dob ?></span></div>
                                <div class="form-col" style="flex: 1.5;"><span class="field-label">Birth Place</span><span class="field-value"><?= htmlspecialchars($member['birth_place'] ?? '') ?></span></div>
                                <div class="form-col"><span class="field-label">Civil Status</span><span class="field-value"><?= htmlspecialchars($member['civil_status'] ?? '') ?></span></div>
                            </div>
                            <div class="form-row">
                                <div class="form-col"><span class="field-label">Religion</span><span class="field-value"><?= htmlspecialchars($member['religion'] ?? '') ?></span></div>
                                <div class="form-col"><span class="field-label">Sex</span><span class="field-value"><?= htmlspecialchars($member['sex'] ?? '') ?></span></div>
                                <div class="form-col"><span class="field-label">Tribe</span><span class="field-value"><?= htmlspecialchars($member['tribe'] ?? '') ?></span></div>
                            </div>
                            <div class="form-row">
                                <div class="form-col"><span class="field-label">SSS / GSIS No.</span><span class="field-value"><?= htmlspecialchars($member['sss_gsis_no'] ?? '') ?></span></div>
                                <div class="form-col"><span class="field-label">TIN No.</span><span class="field-value"><?= htmlspecialchars($member['tin_no'] ?? '') ?></span></div>
                                <div class="form-col"><span class="field-label">Postal Code</span><span class="field-value"><?= htmlspecialchars($member['postal_code'] ?? '') ?></span></div>
                            </div>
                            <div class="form-row">
                                <div class="form-col"><span class="field-label">Address</span><span class="field-value"><?= htmlspecialchars($member['address'] ?? '') ?></span></div>
                            </div>
                            <div class="form-row">
                                <div class="form-col"><span class="field-label">Business / Office Address</span><span class="field-value"><?= htmlspecialchars($member['business_office_address'] ?? '') ?></span></div>
                            </div>
                            <div class="form-row">
                                <div class="form-col"><span class="field-label">Educational Attainment</span><span class="field-value"><?= htmlspecialchars($member['educational_attainment'] ?? '') ?></span></div>
                                <div class="form-col"><span class="field-label">Present Employment / Business Activities</span><span class="field-value"><?= htmlspecialchars($member['present_employment_business'] ?? '') ?></span></div>
                            </div>

                            <div class="section-header">II. Beneficiaries</div>
                            <table class="paper-table">
                                <thead>
                                    <tr>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>M.I.</th>
                                        <th>Date of Birth</th>
                                        <th>Relationship</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($beneficiaries) > 0): ?>
                                        <?php foreach($beneficiaries as $ben): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($ben['last_name']) ?></strong></td>
                                                <td><strong><?= htmlspecialchars($ben['first_name']) ?></strong></td>
                                                <td><strong><?= htmlspecialchars($ben['middle_name'] ?? '') ?></strong></td>
                                                <td><strong><?= !empty($ben['date_of_birth']) ? date('M d, Y', strtotime($ben['date_of_birth'])) : '' ?></strong></td>
                                                <td><strong><?= htmlspecialchars($ben['relationship'] ?? '') ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" style="text-align: center; color: #888;">No beneficiaries listed.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <div class="section-header">III. Occupation & Income</div>
                            <div class="form-row" style="border-top: 1px solid #000;">
                                <div class="form-col"><span class="field-label">Occupation</span><span class="field-value"><?= htmlspecialchars($member['occupation'] ?? '') ?></span></div>
                                <div class="form-col"><span class="field-label">Monthly Income</span><span class="field-value"><?= htmlspecialchars($member['monthly_income'] ?? '') ?></span></div>
                            </div>

                            <div class="certification-section">
                                <p class="certification-text">
                                    I hereby certify that the above information is true and correct, signed this ____ day of __________________ , _______.
                                </p>
                                <div class="signature-wrapper">
                                    <div class="sig-box">
                                        <div class="sig-line"></div>
                                        <div class="sig-label">Signature over printed name</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div id="transaction-history-panel" class="transaction-history-panel w-full xl:w-[450px] shrink-0 bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden no-print mb-12 min-h-0 xl:flex xl:flex-col">
                        
                        <?php
                            // Calculate Totals for this specific member
                            $member_total_shares = 0;
                            $member_total_fees = 0;
                            foreach($member_transactions as $mt) {
                                $stat = strtolower($mt['payment_status'] ?? '');
                                if ($stat === 'completed' || strpos($stat, 'paid') !== false) {
                                    $type = strtolower($mt['transaction_type'] ?? '');
                                    if (strpos($type, 'share') !== false) {
                                        $member_total_shares += (float)$mt['amount'];
                                    } elseif (strpos($type, 'fee') !== false) {
                                        $member_total_fees += (float)$mt['amount'];
                                    }
                                }
                            }
                        ?>

                        <div class="bg-primary text-white p-5 flex justify-between items-center shadow-md relative z-20">
                            <div>
                                <h4 class="font-bold text-lg"><i class="fas fa-receipt mr-2"></i> Transaction History</h4>
                                <p class="text-purple-200 text-xs mt-1 uppercase"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="bg-purple-800 text-white px-3 py-1 rounded-full text-xs font-bold border border-purple-600 shadow-inner"><?= count($member_transactions) ?> Records</span>
                                <button type="button" class="history-expand-trigger bg-white/10 hover:bg-white/20 text-white px-3 py-1 rounded-full text-xs font-bold border border-white/20 transition-colors" onclick="toggleTransactionHistoryMobile(true)">
                                    View All
                                </button>
                                <button type="button" class="history-collapse-trigger hidden bg-white/10 hover:bg-white/20 text-white px-3 py-1 rounded-full text-xs font-bold border border-white/20 transition-colors" onclick="toggleTransactionHistoryMobile(false)">
                                    Close
                                </button>
                            </div>
                        </div>

                        <div class="bg-purple-50 p-4 border-b border-purple-100 flex justify-between items-center relative z-10 shadow-sm">
                            <div class="text-center w-1/2 border-r border-purple-200">
                                <div class="text-[10px] text-purple-600 font-bold uppercase tracking-wider mb-1"><i class="fas fa-chart-pie mr-1"></i> Total Shares</div>
                                <div class="text-lg font-black text-green-600">₱<?= number_format($member_total_shares, 2) ?></div>
                            </div>
                            <div class="text-center w-1/2">
                                <div class="text-[10px] text-purple-600 font-bold uppercase tracking-wider mb-1"><i class="fas fa-id-card mr-1"></i> Total Fees</div>
                                <div class="text-lg font-black text-blue-600">₱<?= number_format($member_total_fees, 2) ?></div>
                            </div>
                        </div>

                        <div id="transactionHistoryList" class="p-5 flex-none min-h-0 overflow-visible overscroll-contain flex flex-col gap-4 bg-gray-50 pb-8">
                            <?php if (count($member_transactions) > 0): ?>
                                <?php foreach($member_transactions as $trans): ?>
                                    <?php 
                                        $status = !empty($trans['payment_status']) ? htmlspecialchars($trans['payment_status']) : 'COMPLETED';
                                        if (stripos($status, 'paid') !== false || stripos($status, 'completed') !== false) {
                                            $stat_badge = "<span class='bg-green-100 text-green-800 px-2 py-0.5 rounded text-[10px] font-bold uppercase border border-green-200'>$status</span>";
                                        } elseif (stripos($status, 'downpayment') !== false) {
                                            $stat_badge = "<span class='bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded text-[10px] font-bold uppercase border border-yellow-200'>$status</span>";
                                        } else {
                                            $stat_badge = "<span class='bg-red-100 text-red-800 px-2 py-0.5 rounded text-[10px] font-bold uppercase border border-red-200'>$status</span>";
                                        }

                                        // Determine visual styling based on the transaction type
                                        $t_type = $trans['transaction_type'] ?? 'PURCHASE';
                                        if (stripos($t_type, 'share') !== false) {
                                            $type_icon = "<i class='fas fa-chart-pie text-green-600 mr-1'></i> <span class='text-green-700 font-bold uppercase tracking-wider text-[10px]'>Share Capital</span>";
                                        } elseif (stripos($t_type, 'fee') !== false) {
                                            $type_icon = "<i class='fas fa-id-card text-blue-600 mr-1'></i> <span class='text-blue-700 font-bold uppercase tracking-wider text-[10px]'>Membership Fee</span>";
                                        } else {
                                            $type_icon = "<i class='fas fa-shopping-bag text-primary mr-1'></i> <span class='text-primary font-bold uppercase tracking-wider text-[10px]'>Purchase</span>";
                                        }
                                    ?>
                                    <div class="transaction-card bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden" data-transaction-date="<?= htmlspecialchars($trans['transaction_date']) ?>">
                                        <div class="flex justify-between items-start mb-3 pb-3 border-b border-gray-100">
                                            <div>
                                                <div class="text-xs text-gray-500 font-bold uppercase mb-1"><i class="far fa-calendar-alt mr-1"></i> <?= date('M d, Y', strtotime($trans['transaction_date'])) ?></div>
                                                <div class="flex items-center gap-2">
                                                    <?= $type_icon ?>
                                                    <span class="text-gray-300">|</span>
                                                    <span class="text-xs font-mono text-gray-500">REF: <?= htmlspecialchars($trans['invoice_no'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                            <?= $stat_badge ?>
                                        </div>
                                        
                                        <div class="text-xs text-gray-700 font-mono leading-relaxed mb-4 bg-gray-50 p-2.5 rounded border border-gray-100 break-words whitespace-normal">
                                            <?= nl2br(htmlspecialchars($trans['items_details'] ?? $t_type)) ?>
                                        </div>
                                        
                                        <div class="flex justify-between items-end">
                                            <div class="text-[11px] text-gray-500 leading-tight">
                                                <?php if(stripos($t_type, 'purchase') !== false || $trans['remaining_balance'] > 0): ?>
                                                    Downpayment: ₱<?= number_format($trans['downpayment'] ?? 0, 2) ?><br>
                                                    <span class="<?= ($trans['remaining_balance'] > 0) ? 'text-red-500 font-bold' : 'text-gray-500' ?>">Balance: ₱<?= number_format($trans['remaining_balance'] ?? 0, 2) ?></span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 italic"><i class="fas fa-check text-green-400 mr-1"></i>Fully Paid</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-lg font-black text-gray-900">
                                                ₱<?= number_format($trans['amount'], 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-12 flex flex-col items-center justify-center opacity-50">
                                    <i class="fas fa-folder-open text-4xl text-gray-400 mb-3"></i>
                                    <p class="text-sm text-gray-500 font-medium">No recorded transactions found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
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

        function toggleTransactionGroup(panelId, buttonEl) {
            const panel = document.getElementById(panelId);
            if (!panel) return;

            const icon = buttonEl ? buttonEl.querySelector('i.fas') : null;
            const isHidden = panel.classList.contains('hidden');
            panel.classList.toggle('hidden');

            if (icon) {
                icon.classList.toggle('fa-chevron-down', isHidden);
                icon.classList.toggle('fa-chevron-right', !isHidden);
            }
        }

        function toggleTransactionHistoryMobile(forceOpen) {
            const panel = document.getElementById('transaction-history-panel');
            const backdrop = document.getElementById('history-mobile-backdrop');
            if (!panel || !backdrop) return;

            const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !panel.classList.contains('mobile-expanded');

            panel.classList.toggle('mobile-expanded', shouldOpen);
            backdrop.style.display = shouldOpen ? 'block' : 'none';
            document.body.style.overflow = shouldOpen ? 'hidden' : '';

            if (shouldOpen) {
                panel.scrollTop = 0;
                window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const list = document.getElementById('transactionHistoryList');
            if (!list) return;

            const cards = Array.from(list.querySelectorAll('.transaction-card'));
            if (!cards.length) return;

            const groups = new Map();
            cards.forEach(card => {
                const dateKey = card.dataset.transactionDate || '';
                if (!groups.has(dateKey)) {
                    groups.set(dateKey, []);
                }
                groups.get(dateKey).push(card);
            });

            const sortedDates = Array.from(groups.keys()).sort((a, b) => new Date(b) - new Date(a));
            list.innerHTML = '';

            sortedDates.forEach((dateKey, index) => {
                const groupId = `transaction-group-${index + 1}`;
                const groupWrapper = document.createElement('div');
                groupWrapper.className = 'bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden';

                const headerButton = document.createElement('button');
                headerButton.type = 'button';
                headerButton.className = 'w-full flex items-center justify-between gap-3 px-4 py-4 text-left bg-gray-50 hover:bg-gray-100 transition-colors';
                headerButton.innerHTML = `
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fas ${index === 0 ? 'fa-chevron-down' : 'fa-chevron-right'} text-primary text-sm shrink-0"></i>
                        <span class="font-bold text-gray-800 truncate">${new Intl.DateTimeFormat('en-US', { month: 'long', day: '2-digit', year: 'numeric' }).format(new Date(dateKey + 'T00:00:00'))}</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 shrink-0">${groups.get(dateKey).length} Record${groups.get(dateKey).length > 1 ? 's' : ''}</span>
                `;
                headerButton.addEventListener('click', function() {
                    toggleTransactionGroup(groupId, headerButton);
                });

                const panel = document.createElement('div');
                panel.id = groupId;
                panel.className = index === 0 ? 'p-4 bg-gray-50 border-t border-gray-100 flex flex-col gap-4' : 'hidden p-4 bg-gray-50 border-t border-gray-100 flex flex-col gap-4';

                groups.get(dateKey).forEach(card => panel.appendChild(card));

                groupWrapper.appendChild(headerButton);
                groupWrapper.appendChild(panel);
                list.appendChild(groupWrapper);
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1280) {
                    toggleTransactionHistoryMobile(false);
                }
            });
        });

    </script>
</body>
</html>
