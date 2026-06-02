<?php
session_start();
include 'db.php';
require 'vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['excel_file'])) {
    
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($fileTmpPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // 1. THE HEADER THESAURUS (Alias Mapping)
    $header_aliases = [
        'form_id'           => ['formid', 'id', 'formno'],
        'member_name'       => ['membername', 'name', 'fullname', 'membersname'],
        'member_first_name' => ['memberfirstname', 'firstname'],
        'member_second_name'=> ['membersecondname', 'secondname'],
        'member_middle_name'=> ['membermiddlename', 'middlename'],
        'member_last_name'  => ['memberlastname', 'lastname'],
        'dob'               => ['dateofbirth', 'dob', 'birthdate'],
        'birth_place'       => ['birthplace', 'placeofbirth'],
        'civil_status'      => ['civilstatus', 'status'],
        'religion'          => ['religion'],
        'sex'               => ['sex', 'gender'],
        'tribe'             => ['tribe'],
        'sss_no'            => ['sssgsisno', 'sssno', 'gsisno', 'sss'],
        'tin_no'            => ['tinno', 'tin'],
        'postal_code'       => ['postalcode', 'zipcode'],
        'address'           => ['address', 'homeaddress'],
        'business_add'      => ['businessofficeaddress', 'businessaddress', 'officeaddress'],
        'education'         => ['educationalattainment', 'education', 'attainment'],
        'employment'        => ['presentemploymentbusinessactivities', 'presentemployment', 'businessactivities', 'employment'],
        'occupation'        => ['occupation', 'job'],
        'income'            => ['monthlyincome', 'income'],
        'ben_name'          => ['beneficiariesnames', 'beneficiariesname', 'beneficiaryname', 'beneficiary', 'benname'],
        'ben_dob'           => ['beneficiariesdateofbirth', 'beneficiarydateofbirth', 'bendob'],
        'ben_rel'           => ['relationshiptothemember', 'relationship', 'rel']
    ];

    $res_map = $conn->query("SELECT system_field, excel_header_name FROM config_excel_headers");
    if ($res_map && $res_map->num_rows > 0) {
        while($m = $res_map->fetch_assoc()) {
            $sys_field = $m['system_field'];
            $cleaned_header = preg_replace('/[^a-z0-9]/', '', strtolower(trim($m['excel_header_name'])));
            
            if ($sys_field === 'business_address') $sys_field = 'business_add';
            
            if (!empty($cleaned_header) && isset($header_aliases[$sys_field])) {
                array_unshift($header_aliases[$sys_field], $cleaned_header);
            }
        }
    }

    // 2. THE BULLETPROOF DATE PARSER
    function parseDate($input) {
        $input = trim((string)$input);
        if (empty($input)) return null;
        
        if (is_numeric($input)) {
            return date('Y-m-d', Date::excelToTimestamp((float)$input));
        }

        $time = strtotime($input);
        if ($time !== false) {
            return date('Y-m-d', $time);
        }

        $cleanDate = str_replace([',', '.'], ' ', $input);
        $cleanDate = preg_replace('/\s+/', ' ', $cleanDate);
        $cleanDate = trim($cleanDate);

        $time = strtotime($cleanDate);
        if ($time !== false) {
            return date('Y-m-d', $time);
        }

        if (strpos($cleanDate, '/') !== false) {
            $parts = explode('/', $cleanDate);
            if (count($parts) === 3 && (int)$parts[0] > 12) {
                $cleanDate = str_replace('/', '-', $cleanDate);
                $time = strtotime($cleanDate);
                if ($time !== false) {
                    return date('Y-m-d', $time);
                }
            }
        }

        $formats = ['d/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-y', 'm-d-y'];
        foreach ($formats as $format) {
            $d = DateTime::createFromFormat($format, $input);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }

    // 3. THE ULTIMATE NAME SPLITTER
    function splitName($fullName, $isStrictMember = false) {
        $last = ''; $first = ''; $middle = '';
        
        $cleanName = preg_replace('/\s+/', ' ', trim($fullName)); 
        
        if (strpos($cleanName, ',') !== false) {
            $parts = explode(',', $cleanName, 2);
            $last = trim($parts[0]);
            
            $fm_parts = explode(' ', trim($parts[1]));
            if (count($fm_parts) > 1) {
                if ($isStrictMember) {
                    $middle = array_pop($fm_parts);
                } else {
                    $potential_mi = end($fm_parts);
                    if (strlen($potential_mi) === 1 || count($fm_parts) >= 3 || strpos($potential_mi, '.') !== false) {
                        $middle = array_pop($fm_parts);
                    }
                }
                $first = implode(' ', $fm_parts); 
            } else {
                $first = trim($parts[1]);
            }
        } else {
            $name_parts = explode(' ', $cleanName);
            $last = count($name_parts) > 1 ? array_pop($name_parts) : $cleanName;
            
            if (count($name_parts) > 0) {
                if ($isStrictMember) {
                    $middle = array_pop($name_parts);
                } else {
                    $potential_mi = end($name_parts);
                    if (strlen($potential_mi) === 1 || strpos($potential_mi, '.') !== false) {
                        $middle = array_pop($name_parts);
                    }
                }
            }
            $first = count($name_parts) > 0 ? implode(' ', $name_parts) : '';
        }
        
        return [strtoupper($last), strtoupper($first), strtoupper($middle)];
    }

    // --- DYNAMIC HEADER DETECTION ---
    $excel_header_index_map = [];
    $start_row = 1;
    
    for ($i = 0; $i < min(10, count($rows)); $i++) {
        $is_header_row = false;
        
        foreach($rows[$i] as $col_index => $col_name) {
            $clean_col = preg_replace('/[^a-z0-9]/', '', strtolower((string)$col_name));
            
            if (!empty($clean_col)) {
                foreach ($header_aliases as $sys_field => $aliases) {
                    if (in_array($clean_col, $aliases)) {
                        $excel_header_index_map[$sys_field] = $col_index;
                        if ($sys_field === 'form_id' || $sys_field === 'member_name' || $sys_field === 'member_first_name' || $sys_field === 'member_last_name') {
                            $is_header_row = true;
                        }
                        break;
                    }
                }
            }
        }
        
        if ($is_header_row) {
            $start_row = $i + 1; 
            break;
        }
    }

    function getVal($row, $excel_map, $system_field) {
        if (isset($excel_map[$system_field]) && isset($row[$excel_map[$system_field]])) {
            $val = trim((string)$row[$excel_map[$system_field]]);
            if ($val !== '') return strtoupper($val);
        }
        return '';
    }

    $last_inserted_member_id = null;

    // Loop through Excel Rows
    $members_inserted = 0;
    $members_updated = 0;
    $beneficiaries_inserted = 0;
    $beneficiaries_updated = 0;
    for ($i = $start_row; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (!is_array($row)) continue;

        $form_id            = getVal($row, $excel_header_index_map, 'form_id');
        $full_name          = getVal($row, $excel_header_index_map, 'member_name');
        $member_first       = getVal($row, $excel_header_index_map, 'member_first_name');
        $member_second      = getVal($row, $excel_header_index_map, 'member_second_name');
        $member_middle      = getVal($row, $excel_header_index_map, 'member_middle_name');
        $member_last        = getVal($row, $excel_header_index_map, 'member_last_name');
        $ben_name           = getVal($row, $excel_header_index_map, 'ben_name');

        $has_member_name = !empty($full_name) || !empty($member_first) || !empty($member_last) || !empty($member_middle) || !empty($member_second);
        if (!$has_member_name && empty($ben_name)) {
            continue;
        }

        // -- 1. PROCESS MEMBER --
        if ($has_member_name) {
            if (!empty($member_last) || !empty($member_first) || !empty($member_middle) || !empty($member_second)) {
                $last_name = $member_last;
                $first_name = trim($member_first . (!empty($member_second) ? ' ' . $member_second : ''));
                $middle_name = $member_middle;

                if (empty($last_name) && !empty($full_name)) {
                    list($last_name, $first_name, $middle_name) = splitName($full_name, true);
                }
            } else {
                list($last_name, $first_name, $middle_name) = splitName($full_name, true);
            }

            $dob_val = isset($excel_header_index_map['dob']) ? $row[$excel_header_index_map['dob']] : '';
            $dob = parseDate($dob_val);

            // Extract all other fields mapped from the Excel row
            $form_id_insert = ($form_id === '') ? null : $form_id;
            $birth_place    = getVal($row, $excel_header_index_map, 'birth_place');
            $civil_status   = getVal($row, $excel_header_index_map, 'civil_status');
            $religion       = getVal($row, $excel_header_index_map, 'religion');
            $tribe          = getVal($row, $excel_header_index_map, 'tribe');
            $sss            = preg_replace('/[^0-9\-]/', '', getVal($row, $excel_header_index_map, 'sss_no'));
            $tin            = preg_replace('/[^0-9\-]/', '', getVal($row, $excel_header_index_map, 'tin_no'));
            $postal         = preg_replace('/[^0-9]/', '', getVal($row, $excel_header_index_map, 'postal_code'));
            $address        = getVal($row, $excel_header_index_map, 'address');
            $business_add   = getVal($row, $excel_header_index_map, 'business_add');
            $education      = getVal($row, $excel_header_index_map, 'education');
            $employment     = getVal($row, $excel_header_index_map, 'employment');
            $occupation     = getVal($row, $excel_header_index_map, 'occupation');
            $income         = getVal($row, $excel_header_index_map, 'income');
            
            $raw_sex = strtolower(getVal($row, $excel_header_index_map, 'sex'));
            $sex = '';
            if (strpos($raw_sex, 'female') !== false || $raw_sex === 'f') $sex = 'FEMALE';
            elseif (strpos($raw_sex, 'male') !== false || $raw_sex === 'm') $sex = 'MALE';


            $existing_member_id = null;

            // Priority Match 1: Form ID
            if (!empty($form_id)) {
                $check_stmt = $conn->prepare("SELECT member_id FROM members WHERE form_id = ?");
                $check_stmt->bind_param("s", $form_id);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                if ($check_res->num_rows > 0) {
                    $existing_member_id = $check_res->fetch_assoc()['member_id'];
                }
                $check_stmt->close();
            }

            // Priority Match 2: Exact Name (Fixed to prevent NULL mismatch failures)
            if ($existing_member_id === null) {
                $check_stmt = $conn->prepare("SELECT member_id FROM members WHERE last_name = ? AND first_name = ?");
                $check_stmt->bind_param("ss", $last_name, $first_name);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                if ($check_res->num_rows > 0) {
                    $existing_member_id = $check_res->fetch_assoc()['member_id'];
                }
                $check_stmt->close();
            }

            if ($existing_member_id !== null) {
                $last_inserted_member_id = $existing_member_id;
                
                // FORCE DATABASE SYNC: Dynamically update all columns that have data in the Excel row
                $update_parts = [];
                $update_params = [];
                $types = "";

                if ($form_id !== '') { $update_parts[] = "form_id = ?"; $update_params[] = $form_id; $types .= "s"; }
                if ($dob !== null) { $update_parts[] = "date_of_birth = ?"; $update_params[] = $dob; $types .= "s"; }
                if ($birth_place !== '') { $update_parts[] = "birth_place = ?"; $update_params[] = $birth_place; $types .= "s"; }
                if ($civil_status !== '') { $update_parts[] = "civil_status = ?"; $update_params[] = $civil_status; $types .= "s"; }
                if ($religion !== '') { $update_parts[] = "religion = ?"; $update_params[] = $religion; $types .= "s"; }
                if ($sex !== '') { $update_parts[] = "sex = ?"; $update_params[] = $sex; $types .= "s"; }
                if ($tribe !== '') { $update_parts[] = "tribe = ?"; $update_params[] = $tribe; $types .= "s"; }
                if ($sss !== '') { $update_parts[] = "sss_gsis_no = ?"; $update_params[] = $sss; $types .= "s"; }
                if ($tin !== '') { $update_parts[] = "tin_no = ?"; $update_params[] = $tin; $types .= "s"; }
                if ($postal !== '') { $update_parts[] = "postal_code = ?"; $update_params[] = $postal; $types .= "s"; }
                if ($address !== '') { $update_parts[] = "address = ?"; $update_params[] = $address; $types .= "s"; }
                if ($business_add !== '') { $update_parts[] = "business_office_address = ?"; $update_params[] = $business_add; $types .= "s"; }
                if ($education !== '') { $update_parts[] = "educational_attainment = ?"; $update_params[] = $education; $types .= "s"; }
                if ($employment !== '') { $update_parts[] = "present_employment_business = ?"; $update_params[] = $employment; $types .= "s"; }
                if ($occupation !== '') { $update_parts[] = "occupation = ?"; $update_params[] = $occupation; $types .= "s"; }
                if ($income !== '') { $update_parts[] = "monthly_income = ?"; $update_params[] = $income; $types .= "s"; }

                if (!empty($update_parts)) {
                    $sql_update = "UPDATE members SET " . implode(', ', $update_parts) . " WHERE member_id = ?";
                    $update_params[] = $last_inserted_member_id;
                    $types .= "i";
                    
                    $update_stmt = $conn->prepare($sql_update);
                    $update_stmt->bind_param($types, ...$update_params);
                    $update_stmt->execute();
                    $update_stmt->close();
                    $members_updated++;
                }
                
            } else {
                // INSERT NEW MEMBER
                $stmt = $conn->prepare("INSERT INTO members (form_id, last_name, first_name, middle_name, date_of_birth, birth_place, civil_status, religion, sex, tribe, sss_gsis_no, tin_no, postal_code, address, business_office_address, educational_attainment, present_employment_business, occupation, monthly_income) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssssssssssss", $form_id_insert, $last_name, $first_name, $middle_name, $dob, $birth_place, $civil_status, $religion, $sex, $tribe, $sss, $tin, $postal, $address, $business_add, $education, $employment, $occupation, $income);
                $stmt->execute();
                
                $last_inserted_member_id = $stmt->insert_id;
                $stmt->close();
                $members_inserted++;
            }
        }

        // -- 2. PROCESS BENEFICIARY (Fixed overwriting issue) --
        if (!empty($ben_name) && $last_inserted_member_id !== null) {
            list($ben_last, $ben_first, $ben_middle) = splitName($ben_name, false);

            $ben_dob_val = isset($excel_header_index_map['ben_dob']) ? $row[$excel_header_index_map['ben_dob']] : '';
            $ben_dob = parseDate($ben_dob_val);
            
            $ben_rel = getVal($row, $excel_header_index_map, 'ben_rel');

            // Exact match query to safely differentiate siblings with similar names
            $b_check = $conn->prepare("SELECT beneficiary_id FROM beneficiaries WHERE member_id = ? AND last_name = ? AND first_name = ? AND middle_name = ? AND relationship = ?");
            $b_check->bind_param("issss", $last_inserted_member_id, $ben_last, $ben_first, $ben_middle, $ben_rel);
            $b_check->execute();
            $b_res = $b_check->get_result();

            if ($b_res->num_rows > 0) {
                // Update existing beneficiary
                $ben_id_to_update = $b_res->fetch_assoc()['beneficiary_id'];
                if ($ben_dob !== null) {
                    $upd_b = $conn->prepare("UPDATE beneficiaries SET date_of_birth = ? WHERE beneficiary_id = ?");
                    $upd_b->bind_param("si", $ben_dob, $ben_id_to_update);
                    $upd_b->execute();
                    $upd_b->close();
                    $beneficiaries_updated++;
                }
            } else {
                // Insert new beneficiary
                $stmt_ben = $conn->prepare("INSERT INTO beneficiaries (member_id, last_name, first_name, middle_name, date_of_birth, relationship) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ben->bind_param("isssss", $last_inserted_member_id, $ben_last, $ben_first, $ben_middle, $ben_dob, $ben_rel);
                $stmt_ben->execute();
                $stmt_ben->close();
                $beneficiaries_inserted++;
            }
            $b_check->close();
        }
    }

    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            'ADMIN',
            'IMPORT MEMBERS',
            'MEMBERS',
            null,
            'Bulk Member Import',
            'Inserted ' . $members_inserted . ' member record(s), updated ' . $members_updated . ' member record(s), inserted ' . $beneficiaries_inserted . ' beneficiary record(s), and updated ' . $beneficiaries_updated . ' beneficiary record(s).'
        );
    }

    // Trigger the beautiful Tailwind Success Modal on the dashboard!
    $_SESSION['alert_title'] = "Upload Complete";
    $_SESSION['alert_message'] = "The Excel file was successfully parsed. All existing member profiles were updated and synchronized with the latest data.";
    $_SESSION['alert_type'] = "success";
    
    header("Location: index.php");
    exit();
}
?>
