<?php
session_start(); // CRITICAL: Start the session to store our alert message
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['member_id'])) {
    
    $member_id = (int)$_POST['member_id'];

    // 1. Capture All Form Data Safely (Capitalized to maintain database consistency)
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

    // 2. Prepare the full SQL UPDATE statement
    $stmt = $conn->prepare("UPDATE members SET 
        form_id = ?, last_name = ?, first_name = ?, middle_name = ?, date_of_birth = ?, 
        birth_place = ?, civil_status = ?, religion = ?, sex = ?, tribe = ?, 
        sss_gsis_no = ?, tin_no = ?, postal_code = ?, address = ?, business_office_address = ?, 
        educational_attainment = ?, present_employment_business = ?, occupation = ?, monthly_income = ? 
        WHERE member_id = ?");
    
    // Bind the 19 string parameters + 1 integer parameter at the end
    $stmt->bind_param(
        "sssssssssssssssssssi", 
        $form_id, $last_name, $first_name, $middle_name, $dob, 
        $birth_place, $civil_status, $religion, $sex, $tribe, 
        $sss, $tin, $postal, $address, $business_add, 
        $education, $employment, $occupation, $income,
        $member_id
    );

    // 3. Execute and Check Member Update
        if ($stmt->execute()) {
            $stmt->close();

        // 4. SMART BENEFICIARY SYNCHRONIZATION
        
        // Step 4a: Find which existing beneficiaries were kept by the user
        $submitted_existing_ids = [];
        if (isset($_POST['existing_ben_ids'])) {
            foreach ($_POST['existing_ben_ids'] as $id) {
                if ($id !== 'new' && is_numeric($id)) {
                    $submitted_existing_ids[] = (int)$id;
                }
            }
        }

        // Step 4b: Delete any beneficiaries that were REMOVED from the form
        if (empty($submitted_existing_ids)) {
            // If the array is completely empty, the user deleted ALL beneficiaries.
            $del_stmt = $conn->prepare("DELETE FROM beneficiaries WHERE member_id = ?");
            $del_stmt->bind_param("i", $member_id);
            $del_stmt->execute();
            $del_stmt->close();
        } else {
            // Delete beneficiaries that belong to this member but are NO LONGER in the submitted list
            $id_list = implode(',', $submitted_existing_ids);
            $conn->query("DELETE FROM beneficiaries WHERE member_id = $member_id AND beneficiary_id NOT IN ($id_list)");
        }

        // Step 4c: Process the rows (Insert New ones, Update Existing ones)
        if (isset($_POST['ben_last_name']) && is_array($_POST['ben_last_name'])) {
            
            $stmt_ben_insert = $conn->prepare("INSERT INTO beneficiaries (member_id, last_name, first_name, middle_name, date_of_birth, relationship) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_ben_update = $conn->prepare("UPDATE beneficiaries SET last_name = ?, first_name = ?, middle_name = ?, date_of_birth = ?, relationship = ? WHERE beneficiary_id = ? AND member_id = ?");

            for ($i = 0; $i < count($_POST['ben_last_name']); $i++) {
                $b_id     = $_POST['existing_ben_ids'][$i] ?? 'new';
                $b_last   = strtoupper(trim($_POST['ben_last_name'][$i] ?? ''));
                $b_first  = strtoupper(trim($_POST['ben_first_name'][$i] ?? ''));
                $b_middle = strtoupper(trim($_POST['ben_middle_name'][$i] ?? ''));
                $b_dob    = !empty($_POST['ben_dob'][$i]) ? $_POST['ben_dob'][$i] : null;
                $b_rel    = strtoupper(trim($_POST['ben_rel'][$i] ?? ''));

                if (!empty($b_last) && !empty($b_first)) {
                    if ($b_id === 'new') {
                        // This is a brand new row added by the user
                        $stmt_ben_insert->bind_param("isssss", $member_id, $b_last, $b_first, $b_middle, $b_dob, $b_rel);
                        $stmt_ben_insert->execute();
                    } elseif (is_numeric($b_id)) {
                        // This is an existing row that might have been edited
                        $bid_int = (int)$b_id;
                        $stmt_ben_update->bind_param("sssssii", $b_last, $b_first, $b_middle, $b_dob, $b_rel, $bid_int, $member_id);
                        $stmt_ben_update->execute();
                    }
                }
            }
            $stmt_ben_insert->close();
            $stmt_ben_update->close();
        }

        // 5. Trigger the beautiful Tailwind Success Modal on the dashboard!
        if (function_exists('logActivity')) {
            logActivity(
                $conn,
                'MEMBERS',
                'UPDATE MEMBER',
                'MEMBER',
                $member_id,
                trim($last_name . ', ' . $first_name),
                'Member profile and beneficiary records were updated.'
            );
        }

        $_SESSION['alert_title'] = "Update Successful";
        $_SESSION['alert_message'] = "The member profile and beneficiaries were successfully updated.";
        $_SESSION['alert_type'] = "success";
        
        header("Location: index.php");
        exit();

    } else {
        // Pass the error alert securely via PHP Session
        $_SESSION['alert_title'] = "Database Error";
        $_SESSION['alert_message'] = "Error updating member: " . addslashes($conn->error);
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
