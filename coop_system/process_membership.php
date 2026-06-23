<?php
session_start(); // CRITICAL: Start the session to store our alert message
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Capture All Form Data Safely
    // We use strtoupper() here to keep your database consistent with capitalized text
    $form_id      = !empty($_POST['form_id']) ? trim($_POST['form_id']) : null;
    $last_name    = strtoupper(trim($_POST['last_name'] ?? ''));
    $first_name   = strtoupper(trim($_POST['first_name'] ?? ''));
    $middle_name  = strtoupper(trim($_POST['middle_name'] ?? ''));
    $dob          = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $birth_place  = strtoupper(trim($_POST['birth_place'] ?? ''));
    $civil_status = strtoupper(trim($_POST['civil_status'] ?? ''));
    
    $religion     = strtoupper(trim($_POST['religion'] ?? ''));
    $sex          = strtoupper(trim($_POST['sex'] ?? ''));
    $tribe        = strtoupper(trim($_POST['tribe'] ?? ''));
    
    $sss          = trim($_POST['sss_gsis_no'] ?? '');
    $tin          = trim($_POST['tin_no'] ?? '');
    $postal       = trim($_POST['postal_code'] ?? '');
    $address      = strtoupper(trim($_POST['address'] ?? ''));
    $business_add = strtoupper(trim($_POST['business_office_address'] ?? ''));
    
    $education    = strtoupper(trim($_POST['educational_attainment'] ?? ''));
    $employment   = strtoupper(trim($_POST['present_employment_business'] ?? ''));
    $occupation   = strtoupper(trim($_POST['occupation'] ?? ''));
    $income       = strtoupper(trim($_POST['monthly_income'] ?? ''));

    $normalize_member_name = function (string $value): string {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    };

    $build_member_display_name = function (string $last, string $first, string $middle = ''): string {
        $last = trim($last);
        $first = trim($first);
        $middle = trim($middle);

        $name = $last . ', ' . $first;
        if ($middle !== '') {
            $name .= ' ' . $middle;
        }

        return preg_replace('/\s+/', ' ', trim($name));
    };

    // 2. Prepare the full SQL INSERT statement
    $stmt = $conn->prepare("INSERT INTO members (
        form_id, last_name, first_name, middle_name, date_of_birth, 
        birth_place, civil_status, religion, sex, tribe, 
        sss_gsis_no, tin_no, postal_code, address, business_office_address, 
        educational_attainment, present_employment_business, occupation, monthly_income
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Bind the 19 parameters
    $stmt->bind_param(
        "sssssssssssssssssss", 
        $form_id, $last_name, $first_name, $middle_name, $dob, 
        $birth_place, $civil_status, $religion, $sex, $tribe, 
        $sss, $tin, $postal, $address, $business_add, 
        $education, $employment, $occupation, $income
    );

    // 3. Execute and Check Member Insertion
    if ($stmt->execute()) {
        $last_inserted_member_id = $stmt->insert_id;
        $stmt->close();

        // Relink waiting share/payment records that were stored before the member existed.
        $new_member_name = $build_member_display_name($last_name, $first_name, $middle_name);
        $normalized_new_member_name = $normalize_member_name($new_member_name);
        $relinked_share_records = 0;

        $waiting_stmt = $conn->prepare("
            SELECT transaction_id, member_name
            FROM transactions
            WHERE (member_id IS NULL OR member_id = 0)
              AND UPPER(COALESCE(payment_status, '')) = 'WAITING'
              AND share_payment_type_id IS NOT NULL
        ");
        if ($waiting_stmt) {
            $waiting_stmt->execute();
            $waiting_res = $waiting_stmt->get_result();
            $update_waiting_stmt = $conn->prepare("
                UPDATE transactions
                SET member_id = ?, member_name = ?, payment_status = 'COMPLETED'
                WHERE transaction_id = ?
            ");

            if ($waiting_res && $update_waiting_stmt) {
                while ($waiting_row = $waiting_res->fetch_assoc()) {
                    $waiting_name = $normalize_member_name((string)($waiting_row['member_name'] ?? ''));
                    if ($waiting_name !== '' && $waiting_name === $normalized_new_member_name) {
                        $waiting_txn_id = (int)($waiting_row['transaction_id'] ?? 0);
                        $update_waiting_stmt->bind_param("isi", $last_inserted_member_id, $new_member_name, $waiting_txn_id);
                        $update_waiting_stmt->execute();
                        $relinked_share_records++;
                    }
                }
                $update_waiting_stmt->close();
            }

            $waiting_stmt->close();
        }

        // 4. Process Beneficiaries (if any were added)
        if (!empty($_POST['ben_last_name'])) {
            $stmt_ben = $conn->prepare("INSERT INTO beneficiaries (member_id, last_name, first_name, middle_name, date_of_birth, relationship) VALUES (?, ?, ?, ?, ?, ?)");
            $beneficiary_count = 0;
            
            // Loop through the dynamically added beneficiaries
            for ($i = 0; $i < count($_POST['ben_last_name']); $i++) {
                $b_last   = strtoupper(trim($_POST['ben_last_name'][$i] ?? ''));
                $b_first  = strtoupper(trim($_POST['ben_first_name'][$i] ?? ''));
                $b_middle = strtoupper(trim($_POST['ben_middle_name'][$i] ?? ''));
                $b_dob    = !empty($_POST['ben_dob'][$i]) ? $_POST['ben_dob'][$i] : null;
                $b_rel    = strtoupper(trim($_POST['ben_rel'][$i] ?? ''));

                // Only insert if they at least provided a first and last name
                if (!empty($b_last) && !empty($b_first)) {
                    $stmt_ben->bind_param("isssss", $last_inserted_member_id, $b_last, $b_first, $b_middle, $b_dob, $b_rel);
                    $stmt_ben->execute();
                    $beneficiary_count++;
                }
            }
            $stmt_ben->close();

            if (function_exists('logActivity')) {
                logActivity(
                    $conn,
                    'MEMBERS',
                    'ADD MEMBER',
                    'MEMBER',
                    $last_inserted_member_id,
                    trim($last_name . ', ' . $first_name),
                    'New member created with ' . $beneficiary_count . ' beneficiary record(s).'
                );
            }
        } elseif (function_exists('logActivity')) {
            logActivity(
                $conn,
                'MEMBERS',
                'ADD MEMBER',
                'MEMBER',
                $last_inserted_member_id,
                trim($last_name . ', ' . $first_name),
                'New member created without beneficiaries.'
            );
        }

        // CRITICAL FIX: Pass the success alert data securely via PHP Session
        $_SESSION['alert_title'] = "Success";
        $_SESSION['alert_message'] = $relinked_share_records > 0
            ? "The new member was successfully added to the database. {$relinked_share_records} waiting share record(s) were linked automatically."
            : "The new member was successfully added to the database.";
        $_SESSION['alert_type'] = "success";
        
        header("Location: index.php");
        exit();

    } else {
        // Pass the error alert securely via PHP Session
        $_SESSION['alert_title'] = "Database Error";
        $_SESSION['alert_message'] = "Error adding member: " . addslashes($conn->error);
        $_SESSION['alert_type'] = "error";
        
        header("Location: index.php");
        exit();
    }
} else {
    // Prevent direct access to this script via URL
    header("Location: index.php");
    exit();
}
?>
