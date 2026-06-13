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

    // 1. STRICT HEADER ALIASES (Matches your exact instructions)
    $header_aliases = [
        'date'        => ['dateoftransaction', 'date'],
        'reference_no' => ['referencenoinvoicenoreceiptno', 'referencenoinvoice', 'referenceno', 'invoiceno', 'receiptono', 'refno', 'reference', 'invoice', 'receipt'],
        'member_id'   => ['memberid', 'member_id', 'id'],
        'form_id'     => ['formid', 'form_id', 'formno'],
        'first_name'  => ['memberfirstname', 'firstname'],
        'second_name' => ['membersecondname', 'secondname'],
        'middle_name' => ['membermiddlename', 'middlename'],
        'last_name'   => ['memberlastname', 'lastname'],
        'type'        => ['transactiontype', 'type'],
        'amount'      => ['paymentamount', 'payment', 'amount', 'total']
    ];

    // 2. HELPER FUNCTIONS
    function parseDate($input) {
        $input = trim((string)$input);
        if (empty($input) || $input == '-') return null;
        if (is_numeric($input)) return date('Y-m-d', Date::excelToTimestamp((float)$input));
        $time = strtotime($input);
        if ($time !== false) return date('Y-m-d', $time);
        return null;
    }

    function cleanNumber($input) {
        $val = preg_replace('/[^0-9\.\-]/', '', (string)$input);
        return $val === '' ? 0 : (float)$val;
    }

    function normalizeShareImportText($input): string {
        $value = html_entity_decode((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value));
        if ($value === '') {
            return '';
        }
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    function normalizeShareImportIdentifier($input): string {
        $value = html_entity_decode((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/\s+/u', '', trim($value));
        if ($value === '') {
            return '';
        }
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    // 3. DETECT HEADERS
    $excel_map = [];
    $start_row = 1;
    for ($i = 0; $i < min(10, count($rows)); $i++) {
        $is_header = false;
        foreach($rows[$i] as $col_idx => $col_name) {
            $clean_col = preg_replace('/[^a-z0-9]/', '', strtolower((string)$col_name));
            if (!empty($clean_col)) {
                foreach ($header_aliases as $sys_field => $aliases) {
                    if (in_array($clean_col, $aliases)) {
                        $excel_map[$sys_field] = $col_idx;
                        // If we find the crucial columns, mark as header row
                        if (in_array($sys_field, ['date', 'reference_no', 'member_id', 'form_id', 'first_name', 'last_name', 'type', 'amount'], true)) {
                            $is_header = true;
                        }
                        break;
                    }
                }
            }
        }
        if ($is_header) {
            $start_row = $i + 1; break;
        }
    }

    function getVal($row, $map, $field) {
        return isset($map[$field]) && isset($row[$map[$field]]) ? trim((string)$row[$map[$field]]) : '';
    }

    // 4. PARSE ROWS & STRICTLY INSERT INTO DB
    if (!isset($excel_map['reference_no'])) {
        $_SESSION['alert_title'] = "Missing Column";
        $_SESSION['alert_message'] = "Your Excel file must include a Reference No. / Invoice No. / Receipt No. column.";
        $_SESSION['alert_type'] = "error";
        header("Location: member_shares.php");
        exit();
    }

    $member_rows = [];
    $member_index_by_id = [];
    $member_index_by_form = [];
    $member_query = $conn->query("SELECT member_id, form_id, first_name, middle_name, last_name FROM members");
    if ($member_query) {
        while ($member = $member_query->fetch_assoc()) {
            $member_rows[] = $member;
            $member_id_key = (int)$member['member_id'];
            $member_index_by_id[$member_id_key] = $member;

            $form_key = normalizeShareImportIdentifier($member['form_id'] ?? '');
            if ($form_key !== '') {
                if (!isset($member_index_by_form[$form_key])) {
                    $member_index_by_form[$form_key] = [];
                }
                $member_index_by_form[$form_key][] = $member;
            }
        }
    }

    $summary = [
        'processed' => 0,
        'imported' => 0,
        'unmatched' => 0,
        'ambiguous' => 0,
        'missing_required' => 0,
        'invalid_rows' => 0,
        'duplicate' => 0,
        'details' => []
    ];
    $detail_limit = 12;
    $append_detail = function (string $message) use (&$summary, $detail_limit): void {
        if (count($summary['details']) < $detail_limit) {
            $summary['details'][] = $message;
        }
    };

    $conn->begin_transaction();
    try {
        for ($i = $start_row; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!is_array($row)) {
                continue;
            }

            try {
                $raw_date = getVal($row, $excel_map, 'date');
                $reference_no = trim((string)getVal($row, $excel_map, 'reference_no'));
                $excel_member_id = preg_replace('/\D+/', '', (string)getVal($row, $excel_map, 'member_id'));
                $excel_form_id = normalizeShareImportIdentifier(getVal($row, $excel_map, 'form_id'));
                $first = getVal($row, $excel_map, 'first_name');
                $second = getVal($row, $excel_map, 'second_name');
                $middle = getVal($row, $excel_map, 'middle_name');
                $last = getVal($row, $excel_map, 'last_name');
                $type = getVal($row, $excel_map, 'type');
                $amount = cleanNumber(getVal($row, $excel_map, 'amount'));

                if (trim($raw_date . $reference_no . $excel_member_id . $excel_form_id . $first . $second . $middle . $last . $type . (string)$amount) === '') {
                    continue;
                }

                $summary['processed']++;
                $row_number = $i + 1;

                if ($reference_no === '') {
                    $summary['missing_required']++;
                    $append_detail("Row {$row_number}: missing Reference No. / Invoice No. / Receipt No.");
                    continue;
                }

                if ($amount <= 0) {
                    $summary['missing_required']++;
                    $append_detail("Row {$row_number}: invalid amount.");
                    continue;
                }

                $resolved_member = null;
                $match_source = '';
                $match_notes = [];

                if ($excel_member_id !== '') {
                    $member_id_int = (int)$excel_member_id;
                    if (isset($member_index_by_id[$member_id_int])) {
                        $resolved_member = $member_index_by_id[$member_id_int];
                        $match_source = 'member_id';
                    } else {
                        $match_notes[] = 'member_id not found';
                    }
                }

                if ($resolved_member === null && $excel_form_id !== '') {
                    $form_matches = $member_index_by_form[$excel_form_id] ?? [];
                    if (count($form_matches) === 1) {
                        $resolved_member = $form_matches[0];
                        $match_source = 'form_id';
                    } elseif (count($form_matches) > 1) {
                        $summary['ambiguous']++;
                        $append_detail("Row {$row_number}: ambiguous Form ID '{$excel_form_id}' matched multiple members.");
                        continue;
                    } else {
                        $match_notes[] = 'form_id not found';
                    }
                }

                if ($resolved_member === null) {
                    $excel_first = normalizeShareImportText(trim((string)$first . ' ' . (string)$second));
                    $excel_middle = normalizeShareImportText($middle);
                    $excel_last = normalizeShareImportText($last);

                    $candidate_matches = [];
                    foreach ($member_rows as $member_candidate) {
                        $candidate_first = normalizeShareImportText($member_candidate['first_name'] ?? '');
                        $candidate_middle = normalizeShareImportText($member_candidate['middle_name'] ?? '');
                        $candidate_last = normalizeShareImportText($member_candidate['last_name'] ?? '');

                        if ($excel_last !== '' && $candidate_last !== $excel_last) {
                            continue;
                        }
                        if ($excel_first !== '' && $candidate_first !== $excel_first) {
                            continue;
                        }
                        if ($excel_middle !== '' && $candidate_middle !== $excel_middle) {
                            continue;
                        }
                        $candidate_matches[] = $member_candidate;
                    }

                    if (count($candidate_matches) === 1) {
                        $resolved_member = $candidate_matches[0];
                        $match_source = 'normalized_name';
                    } elseif (count($candidate_matches) > 1) {
                        $summary['ambiguous']++;
                        $append_detail("Row {$row_number}: ambiguous name match for '{$last}, {$first} {$second}'");
                        continue;
                    } else {
                        $summary['unmatched']++;
                        $reason = 'no member match';
                        if (!empty($match_notes)) {
                            $reason .= ' (' . implode(', ', $match_notes) . ')';
                        }
                        $append_detail("Row {$row_number}: {$reason} for '{$last}, {$first} {$second}'");
                        continue;
                    }
                }

                $member_id = (int)$resolved_member['member_id'];
                $member_last = trim((string)($resolved_member['last_name'] ?? ''));
                $member_first = trim((string)($resolved_member['first_name'] ?? ''));
                $member_middle = trim((string)($resolved_member['middle_name'] ?? ''));
                $member_name = trim($member_last . ', ' . $member_first . ' ' . $member_middle);
                $member_name = preg_replace('/\s+/', ' ', trim($member_name));

                $t_date = parseDate($raw_date) ?: date('Y-m-d');
                $resolved_type = function_exists('resolveSharePaymentType') ? resolveSharePaymentType($conn, $type) : null;
                $t_type = $resolved_type['name'] ?? ((stripos($type, 'share') !== false) ? 'Share Capital' : 'Membership Fee');
                $share_payment_type_id = $resolved_type['id'] ?? null;
                $status = 'COMPLETED';
                $items_details = "Payment for " . $t_type;

                $check = $conn->prepare("SELECT transaction_id FROM transactions WHERE invoice_no = ? LIMIT 1");
                if ($check) {
                    $check->bind_param("s", $reference_no);
                    $check->execute();
                    $c_res = $check->get_result();
                    if ($c_res && $c_res->num_rows > 0) {
                        $summary['duplicate']++;
                        $append_detail("Row {$row_number}: duplicate reference '{$reference_no}' already exists.");
                        $check->close();
                        continue;
                    }
                    $check->close();
                }

                $ins = $conn->prepare("INSERT INTO transactions (transaction_date, member_id, member_name, transaction_type, share_payment_type_id, amount, items_details, invoice_no, payment_status, downpayment, remaining_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
                if (!$ins) {
                    throw new Exception('Unable to prepare transaction insert.');
                }
                $ins->bind_param("sissidsss", $t_date, $member_id, $member_name, $t_type, $share_payment_type_id, $amount, $items_details, $reference_no, $status);
                $ins->execute();
                $ins->close();
                $summary['imported']++;
            } catch (Throwable $rowError) {
                $summary['invalid_rows']++;
                $append_detail("Row " . ($i + 1) . ': ' . $rowError->getMessage());
                continue;
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['alert_title'] = "Import Error";
        $_SESSION['alert_message'] = "Import stopped: " . htmlspecialchars($e->getMessage());
        $_SESSION['alert_type'] = "error";
        header("Location: member_shares.php");
        exit();
    }

    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            'ADMIN',
            'IMPORT SHARES',
            'TRANSACTIONS',
            null,
            'Bulk Share / Fee Import',
            'Processed ' . $summary['processed'] . ' row(s); imported ' . $summary['imported'] . '; unmatched ' . $summary['unmatched'] . '; ambiguous ' . $summary['ambiguous'] . '; missing required ' . $summary['missing_required'] . '; duplicates ' . $summary['duplicate'] . '.'
        );
    }

    $detail_html = '';
    if (!empty($summary['details'])) {
        $detail_html .= '<div class="mt-3 text-left"><div class="font-semibold mb-1">Row details</div><ul class="list-disc pl-5 space-y-1">';
        foreach ($summary['details'] as $detail) {
            $detail_html .= '<li>' . htmlspecialchars($detail) . '</li>';
        }
        $detail_html .= '</ul></div>';
    }

    $_SESSION['alert_title'] = "Shares Uploaded";
    $_SESSION['alert_message'] =
        '<div class="text-left space-y-1">' .
        '<div><strong>Rows Processed:</strong> ' . (int)$summary['processed'] . '</div>' .
        '<div><strong>Successfully Imported:</strong> ' . (int)$summary['imported'] . '</div>' .
        '<div><strong>Unmatched:</strong> ' . (int)$summary['unmatched'] . '</div>' .
        '<div><strong>Ambiguous:</strong> ' . (int)$summary['ambiguous'] . '</div>' .
        '<div><strong>Missing Required:</strong> ' . (int)$summary['missing_required'] . '</div>' .
        '<div><strong>Duplicates:</strong> ' . (int)$summary['duplicate'] . '</div>' .
        $detail_html .
        '</div>';
    $_SESSION['alert_type'] = ($summary['imported'] > 0) ? "success" : "error";
    
    header("Location: member_shares.php");
    exit();
}
?>
