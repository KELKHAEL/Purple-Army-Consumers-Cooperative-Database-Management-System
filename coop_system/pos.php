<?php 
session_start();
include 'db.php'; 

// Fetch negative stock setting
$setting_res = $conn->query("SELECT setting_value FROM config_inventory_settings WHERE setting_key = 'allow_negative_stock'");
$allow_negative = 0;
if ($setting_res && $setting_res->num_rows > 0) {
    $allow_negative = (int)$setting_res->fetch_assoc()['setting_value'];
}

// Fetch all members for the dropdown link
$members = [];
$mem_res = $conn->query("SELECT member_id, last_name, first_name FROM members ORDER BY last_name ASC");
if ($mem_res) {
    while($m = $mem_res->fetch_assoc()) {
        $members[] = $m;
    }
}

// PROCESS THE CHECKOUT CART
$checkout_success = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $payment = $conn->real_escape_string($_POST['payment_method']);
    
    // Default values
    $receipt = 'N/A';
    $buyer_name = 'N/A';
    $member_id = null;
    $status = 'COMPLETED';

    // Handle the specific logic based on Payment Method
    if ($payment === 'Others') {
        $status = 'PENDING';
        $buyer_name = $conn->real_escape_string($_POST['event_name']); // e.g. "Bazaar at Plaza"
        $receipt = 'OUTSOURCED';
    } else {
        // Cash, GCash, Pay Later belong to actual Members
        if (!empty($_POST['member_select'])) {
            $member_id = (int)$_POST['member_select'];
            // Get member name for the string record
            $stmt_m = $conn->query("SELECT last_name, first_name FROM members WHERE member_id = $member_id");
            if ($row_m = $stmt_m->fetch_assoc()) {
                $buyer_name = $row_m['last_name'] . ', ' . $row_m['first_name'];
            }
        }
        
        if ($payment === 'Pay Later') {
            $status = 'PENDING';
        } else {
            $receipt = $conn->real_escape_string($_POST['receipt_no']);
        }
    }

    $date = date('Y-m-d');
    $items_details_arr = [];
    $total_cart_amount = 0;

    if (!empty($cart)) {
        // 1. Process Inventory Outsource Table (Legacy sync)
        foreach ($cart as $item) {
            $id = (int)$item['id'];
            $qty = (int)$item['qty'];
            $name = $conn->real_escape_string($item['name']);
            $price = (float)$item['price'];
            $line_total = $qty * $price;
            
            $total_cart_amount += $line_total;
            $items_details_arr[] = "{$qty}x {$name} @ ₱{$price} = ₱{$line_total}";
            
            $conn->query("UPDATE inventory SET current_quantity = current_quantity - $qty WHERE product_id=$id");
            $conn->query("INSERT INTO inventory_outsourcing (record_date, product_id, quantity_out, payment_method, receipt_no, buyer_name) 
                          VALUES ('$date', $id, $qty, '$payment', '$receipt', '$buyer_name')");
        }

        // 2. Process Modern Transactions Table (To link to view_member.php)
        $items_details = $conn->real_escape_string(implode("\n", $items_details_arr));
        $trans_type = ($payment === 'Others') ? 'OUTSOURCED' : 'PURCHASE';
        
        $sql_trans = "INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
        
        $balance = ($status === 'PENDING') ? $total_cart_amount : 0;
        
        $stmt_trans = $conn->prepare($sql_trans);
        $stmt_trans->bind_param("sissssssd", $date, $member_id, $buyer_name, $trans_type, $total_cart_amount, $items_details, $receipt, $status, $balance);
        $stmt_trans->execute();
        $transaction_id = (int)$conn->insert_id;
        $stmt_trans->close();

        if (function_exists('logActivity')) {
            $item_count = count($cart);
            $action_label = ($payment === 'Others') ? 'OUTSOURCE CHECKOUT' : 'SALE CHECKOUT';
            logActivity(
                $conn,
                'SALES',
                $action_label,
                'TRANSACTION',
                $transaction_id,
                $buyer_name,
                'Payment Method: ' . $payment . ', Items: ' . $item_count . ', Total: ' . number_format($total_cart_amount, 2) . ', Status: ' . $status
            );
        }

        $checkout_success = true;
    }
}

// Fetch dynamic unit types
$unit_types = [];
$res_units = $conn->query("SELECT name FROM config_unit_types ORDER BY name ASC");
if ($res_units) {
    while($u = $res_units->fetch_assoc()) {
        $unit_types[] = $u['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell / Outsource - Coop DBMS</title>
    
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
                <a href="index.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-users w-6"></i> MEMBERSHIP DIRECTORY
                </a>
                <a href="member_shares.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-hand-holding-usd w-6"></i> MEMBER SHARES
                </a>
                <a href="transactions.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-receipt w-6"></i> TRANSACTIONS
                </a>
                <a href="inventory.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors">
                    <i class="fas fa-boxes w-6"></i> INVENTORY
                </a>
                <a href="pos.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark">
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Point of Sale</h1>
                </div>
            </header>

            <div class="flex-1 overflow-hidden flex flex-col lg:flex-row">
                
                <div class="flex-1 flex flex-col h-full bg-gray-50">
                    
                    <div class="p-4 md:p-6 bg-white border-b border-gray-200 shadow-sm flex flex-col sm:flex-row gap-4 items-center z-10 relative">
                        <div class="flex w-full sm:w-1/2 bg-gray-100 border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary focus-within:border-primary transition-all">
                            <div class="px-3 py-2 text-gray-400 flex items-center justify-center"><i class="fas fa-search"></i></div>
                            <input type="text" id="posSearch" placeholder="Search Products..." class="w-full py-2 pr-4 outline-none text-sm text-gray-700 bg-transparent">
                        </div>
                        
                        <div class="flex gap-2 w-full sm:w-auto">
                            <select id="posUnitFilter" onchange="filterProducts()" class="flex-1 sm:flex-none rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <option value="all">All Units</option>
                                <?php foreach($unit_types as $u): ?>
                                    <option value="<?= strtolower(htmlspecialchars($u)) ?>"><?= htmlspecialchars($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select id="posSort" onchange="sortProducts()" class="flex-1 sm:flex-none rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <option value="alpha_asc">A-Z</option>
                                <option value="stock_desc">Highest Stock</option>
                                <option value="price_desc">Highest Price</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 md:p-6 relative">
                        <div id="products-container" class="flex flex-col gap-6">
                            <?php
                            // GROUP PRODUCTS BY CATEGORY
                            $sql = "SELECT * FROM inventory ORDER BY product_type ASC, product_name ASC";
                            $res = $conn->query($sql);
                            
                            if ($res && $res->num_rows > 0) {
                                $current_category = null;
                                
                                while($row = $res->fetch_assoc()) {
                                    if ($current_category !== $row['product_type']) {
                                        // Close previous category grid if it exists
                                        if ($current_category !== null) { echo "</div></div>"; }
                                        $current_category = $row['product_type'];
                                        
                                        // Open new category section
                                        echo "<div class='category-section' data-cat='" . strtolower(htmlspecialchars($current_category)) . "'>";
                                        echo "<h3 class='font-black text-gray-400 uppercase tracking-widest text-xs mb-3 flex items-center'><i class='fas fa-tags mr-2 text-primary opacity-50'></i>" . htmlspecialchars($current_category) . "</h3>";
                                        echo "<div class='grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 products-grid'>";
                                    }
                                    
                                    $stock_color = ($row['current_quantity'] <= 0) ? "text-red-600 font-bold" : "text-green-600 font-bold";
                                    $bg_shade = ($row['current_quantity'] <= 0) ? "bg-red-50/50 grayscale-[50%]" : "bg-white";

                                    echo "
                                    <div class='product-card {$bg_shade} rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col hover:shadow-md hover:border-purple-300 transition-all relative overflow-hidden group' 
                                         data-id='{$row['product_id']}' 
                                         data-name='" . strtolower(htmlspecialchars($row['product_name'])) . "' 
                                         data-price='{$row['price']}' 
                                         data-max-stock='{$row['current_quantity']}' 
                                         data-unit='" . strtolower(htmlspecialchars($row['quantity_type'])) . "'>
                                         
                                        <div class='flex-1 mb-3'>
                                            <h4 class='text-sm font-bold text-gray-800 capitalize leading-tight mb-1 group-hover:text-primary transition-colors'>" . htmlspecialchars($row['product_name']) . "</h4>
                                        </div>
                                        
                                        <div class='text-lg font-extrabold text-gray-900 mb-2'>₱" . number_format($row['price'], 2) . "</div>
                                        
                                        <div class='text-xs text-gray-600 mb-4 bg-gray-100/80 rounded px-2 py-1 inline-block w-max border border-gray-200'>
                                            Stock: <span id='stock-count-{$row['product_id']}' class='{$stock_color}'>{$row['current_quantity']}</span> <span class='text-gray-400 ml-0.5'>{$row['quantity_type']}s</span>
                                        </div>
                                        
                                        <button type='button' class='mt-auto w-full bg-white border-2 border-primary text-primary hover:bg-primary hover:text-white font-bold py-2 rounded-lg text-sm transition-all transform active:scale-95' 
                                            onclick='addToCart({$row['product_id']}, \"" . addslashes($row['product_name']) . "\", {$row['price']}, {$row['current_quantity']})'>
                                            <i class='fas fa-cart-plus mr-1'></i> ADD
                                        </button>
                                    </div>";
                                }
                                // Close the very last category grid
                                if ($current_category !== null) { echo "</div></div>"; }
                            } else {
                                echo "<div class='flex flex-col items-center justify-center py-20 opacity-40'>
                                        <i class='fas fa-box-open text-6xl mb-4'></i>
                                        <p class='text-lg font-bold'>No products available in inventory.</p>
                                      </div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="w-full lg:w-[400px] xl:w-[450px] bg-white border-t lg:border-t-0 lg:border-l border-gray-200 flex flex-col shadow-2xl lg:shadow-none z-20 h-auto lg:h-full max-h-[60vh] lg:max-h-full shrink-0">
                    
                    <div class="p-5 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="font-black text-gray-800 text-lg tracking-tight"><i class="fas fa-shopping-basket text-primary mr-2"></i>Current Order</h3>
                        <span id="cart-item-count" class="bg-purple-100 text-primary px-3 py-1 rounded-full text-xs font-bold border border-purple-200 shadow-sm">0 Items</span>
                    </div>

                    <div id="cart-container" class="flex-1 overflow-y-auto p-4 flex flex-col gap-3 bg-gray-50/50 relative">
                        <div id="cart-empty-state" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 opacity-60">
                            <i class="fas fa-shopping-cart text-5xl mb-3"></i>
                            <p class="text-sm font-semibold">Cart is empty</p>
                        </div>
                    </div>

                    <div class="p-5 bg-white border-t border-gray-200 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] relative z-10">
                        
                        <div class="flex justify-between items-end mb-5">
                            <span class="text-gray-500 font-bold uppercase text-xs tracking-widest mb-1">Total Amount</span>
                            <span class="text-4xl font-black text-green-600 tracking-tighter leading-none">₱<span id="cart-total-price">0.00</span></span>
                        </div>

                        <form action="pos.php" method="POST" id="checkoutForm" class="flex flex-col gap-3">
                            <input type="hidden" name="checkout" value="1">
                            <input type="hidden" name="cart_data" id="cart_data">
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 px-1">Payment Method</label>
                                <select name="payment_method" id="payment_method" required class="w-full font-bold text-gray-800 border-2 border-gray-300 px-4 py-3 rounded-lg focus:outline-none focus:border-primary focus:ring-0 transition-colors bg-gray-50 hover:bg-white cursor-pointer appearance-none shadow-sm">
                                    <option value="Cash">Cash (Member Purchase)</option>
                                    <option value="GCash">GCash (Member Purchase)</option>
                                    <option value="Pay Later">Pay Later (Member Purchase)</option>
                                    <option value="Others" class="text-blue-600">Others (Outsourced)</option>
                                </select>
                            </div>

                            <div id="member_select_group">
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 px-1 mt-1">Select Member</label>
                                <select name="member_select" id="member_select" class="w-full text-sm text-gray-700 border border-gray-300 px-4 py-2.5 rounded-lg focus:outline-none focus:border-primary shadow-sm bg-white">
                                    <option value="">-- Choose Member --</option>
                                    <?php foreach($members as $m): ?>
                                        <option value="<?= $m['member_id'] ?>"><?= htmlspecialchars($m['last_name'] . ', ' . $m['first_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="receipt_group" class="mt-1">
                                <input type="text" name="receipt_no" id="receipt_no" placeholder="Receipt No. *" required class="w-full text-sm text-gray-800 border border-gray-300 px-4 py-2.5 rounded-lg focus:outline-none focus:border-primary shadow-sm bg-white">
                            </div>

                            <div id="outsourced_group" class="hidden mt-1 bg-blue-50 border border-blue-200 p-3 rounded-lg">
                                <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-2"><i class="fas fa-truck-loading mr-1"></i> Outsourced Event Details</label>
                                <input type="text" name="event_name" id="event_name" placeholder="Event Name / Location *" class="w-full text-sm text-gray-800 border border-blue-300 px-3 py-2 rounded focus:outline-none focus:border-blue-500 shadow-sm mb-2">
                                <p class="text-[10px] text-blue-600 leading-tight">These items will be marked as "PENDING" so they can be monitored and returned if unsold.</p>
                            </div>

                            <button type="button" class="w-full bg-green-600 hover:bg-green-700 text-white font-black py-4 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 active:scale-95 mt-4 flex items-center justify-center gap-2 text-lg" onclick="processCheckout()">
                                <i class="fas fa-check-circle"></i> COMPLETE CHECKOUT
                            </button>
                        </form>
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

        // --- CUSTOM ALERT LOGIC ---
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

        <?php if ($checkout_success): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showCustomAlert('Transaction Complete', 'The checkout was processed securely. Inventory levels have been updated and linked to the history logs.', 'success', 'transactions.php');
            });
        <?php endif; ?>

        let cart = {};
        const allowNegativeStock = <?= $allow_negative ?> === 1;

        // --- DYNAMIC PAYMENT UI LOGIC ---
        document.getElementById('payment_method').addEventListener('change', function() {
            const method = this.value;
            const memGroup = document.getElementById('member_select_group');
            const memField = document.getElementById('member_select');
            
            const recGroup = document.getElementById('receipt_group');
            const recField = document.getElementById('receipt_no');
            
            const outGroup = document.getElementById('outsourced_group');
            const outField = document.getElementById('event_name');

            // Reset States
            this.className = "w-full font-bold border-2 px-4 py-3 rounded-lg focus:outline-none focus:ring-0 transition-colors shadow-sm appearance-none cursor-pointer ";
            
            if (method === 'Others') {
                // Outsource Mode (No Member, No Receipt, Require Event)
                this.classList.add('bg-blue-50', 'border-blue-400', 'text-blue-700');
                
                memGroup.classList.add('hidden');
                memField.removeAttribute('required');
                
                recGroup.classList.add('hidden');
                recField.removeAttribute('required');
                
                outGroup.classList.remove('hidden');
                outField.setAttribute('required', 'required');
                
            } else if (method === 'Pay Later') {
                // Pay Later Mode (Require Member, No Receipt)
                this.classList.add('bg-red-50', 'border-red-400', 'text-red-700');
                
                memGroup.classList.remove('hidden');
                memField.setAttribute('required', 'required');
                
                recGroup.classList.add('hidden');
                recField.removeAttribute('required');
                
                outGroup.classList.add('hidden');
                outField.removeAttribute('required');
                
            } else {
                // Cash / GCash Mode (Require Member, Require Receipt)
                this.classList.add('bg-white', 'border-gray-300', 'text-gray-800', 'hover:bg-gray-50');
                
                memGroup.classList.remove('hidden');
                memField.setAttribute('required', 'required');
                
                recGroup.classList.remove('hidden');
                recField.setAttribute('required', 'required');
                recField.placeholder = (method === 'GCash') ? 'GCash Ref No. *' : 'Receipt No. / Invoice *';
                
                outGroup.classList.add('hidden');
                outField.removeAttribute('required');
            }
        });

        // --- UPGRADED SEARCH & FILTER ---
        function filterProducts() {
            let searchFilter = document.getElementById('posSearch').value.toLowerCase();
            let unitFilter = document.getElementById('posUnitFilter').value;
            let sections = document.querySelectorAll('.category-section');
            
            sections.forEach(section => {
                let cards = section.querySelectorAll('.product-card');
                let visibleCards = 0;
                
                cards.forEach(card => {
                    let textMatch = card.dataset.name.includes(searchFilter);
                    let unitMatch = (unitFilter === 'all') || (card.dataset.unit === unitFilter);
                    
                    if (textMatch && unitMatch) {
                        card.style.display = '';
                        visibleCards++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Hide the entire category section if all its products are hidden
                section.style.display = (visibleCards > 0) ? '' : 'none';
            });
        }
        document.getElementById('posSearch').addEventListener('keyup', filterProducts);

        function sortProducts() {
            let sortType = document.getElementById('posSort').value;
            let sections = document.querySelectorAll('.category-section');

            sections.forEach(section => {
                let container = section.querySelector('.products-grid');
                let cards = Array.from(container.querySelectorAll('.product-card'));

                cards.sort((a, b) => {
                    if (sortType === 'alpha_asc') return a.dataset.name.localeCompare(b.dataset.name);
                    if (sortType === 'stock_desc') return parseInt(b.dataset.maxStock) - parseInt(a.dataset.maxStock);
                    if (sortType === 'price_desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                });

                container.innerHTML = '';
                cards.forEach(card => container.appendChild(card));
            });
        }

        // --- CART LOGIC ---
        function addToCart(id, name, price, maxQty) {
            if (cart[id]) {
                if (allowNegativeStock || cart[id].qty < maxQty) {
                    cart[id].qty++;
                } else {
                    showCustomAlert('Stock Limit', `Cannot exceed current stock of <strong>${maxQty}</strong>.<br><br><em>(Negative Stock mapping disabled in settings)</em>`, 'error');
                }
            } else {
                if (allowNegativeStock || maxQty > 0) {
                    cart[id] = { name: name, price: price, qty: 1, max: maxQty };
                } else {
                    showCustomAlert('Out of Stock', `This item is completely out of stock.<br><br><em>(Negative Stock mapping disabled in settings)</em>`, 'error');
                }
            }
            renderCart();
        }

        function updateQty(id, newQty) {
            let n = parseInt(newQty);
            if (!allowNegativeStock && n > cart[id].max) {
                showCustomAlert('Stock Limit', `Cannot manually enter a quantity higher than the current stock of <strong>${cart[id].max}</strong>.`, 'error');
                cart[id].qty = cart[id].max;
            } else if (n < 1 || isNaN(n)) {
                delete cart[id];
            } else {
                cart[id].qty = n;
            }
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cart-container');
            const totalEl = document.getElementById('cart-total-price');
            const countEl = document.getElementById('cart-item-count');
            const emptyState = document.getElementById('cart-empty-state');
            
            // Clear current items (but keep empty state element)
            Array.from(container.children).forEach(child => {
                if(child.id !== 'cart-empty-state') child.remove();
            });

            let total = 0;
            let itemsCount = 0;

            for (let id in cart) {
                itemsCount++;
                const item = cart[id];
                const itemTotal = item.qty * item.price;
                total += itemTotal;

                container.insertAdjacentHTML('beforeend', `
                    <div class="bg-white border border-gray-200 rounded-xl p-3 flex justify-between items-center shadow-sm relative z-10">
                        <div class="flex-1 pr-2">
                            <div class="font-bold text-sm text-gray-800 leading-tight mb-1">${item.name}</div>
                            <div class="text-[11px] text-primary font-bold bg-purple-50 inline-block px-1.5 py-0.5 rounded border border-purple-100">₱${item.price.toFixed(2)} ea</div>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <div class="flex items-center border border-gray-300 rounded-md overflow-hidden h-8 bg-gray-50">
                                <button type="button" onclick="updateQty(${id}, ${item.qty - 1})" class="px-2 hover:bg-gray-200 text-gray-600 transition-colors h-full"><i class="fas fa-minus text-[10px]"></i></button>
                                <input type="number" value="${item.qty}" min="0" class="w-10 text-center text-sm font-bold outline-none h-full bg-transparent" onchange="updateQty(${id}, this.value)">
                                <button type="button" onclick="updateQty(${id}, ${item.qty + 1})" class="px-2 hover:bg-gray-200 text-gray-600 transition-colors h-full"><i class="fas fa-plus text-[10px]"></i></button>
                            </div>
                            <div class="font-black text-gray-800 tracking-tight">₱${itemTotal.toFixed(2)}</div>
                        </div>
                    </div>
                `);
            }

            emptyState.style.display = (itemsCount > 0) ? 'none' : 'flex';
            totalEl.innerText = total.toFixed(2);
            countEl.innerText = itemsCount + (itemsCount === 1 ? ' Item' : ' Items');
            updateStockDisplay();
        }

        function updateStockDisplay() {
            let cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                let id = card.dataset.id;
                let maxStock = parseInt(card.dataset.maxStock);
                let currentStock = maxStock - (cart[id] ? cart[id].qty : 0);
                
                let stockSpan = document.getElementById('stock-count-' + id);
                if (stockSpan) {
                    stockSpan.innerText = currentStock;
                    stockSpan.className = (currentStock <= 0) ? "text-red-600 font-bold" : "text-green-600 font-bold";
                }
            });
        }

        function processCheckout() {
            if (!document.getElementById('checkoutForm').reportValidity()) return;
            if (Object.keys(cart).length === 0) {
                showCustomAlert('Empty Cart', 'Your checkout cart is completely empty. Please add items to the cart before confirming the checkout.', 'error');
                return;
            }

            const cartArray = Object.keys(cart).map(id => ({ id: id, name: cart[id].name, price: cart[id].price, qty: cart[id].qty }));
            document.getElementById('cart_data').value = JSON.stringify(cartArray);
            document.getElementById('checkoutForm').submit();
        }
    </script>
</body>
</html>
