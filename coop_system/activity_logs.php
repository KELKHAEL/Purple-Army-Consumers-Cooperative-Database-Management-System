<?php
session_start();
include 'db.php';

function normalizeActivityDate($value, $fallback)
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if ($date === false) {
        return $fallback;
    }

    return $date->format('Y-m-d');
}

function activityDateLabel($value)
{
    return date('F d, Y', strtotime($value));
}

function activityTimeLabel($value)
{
    return date('h:i A', strtotime($value));
}

function moduleBadgeClass($module)
{
    $module = strtoupper((string)$module);
    return match ($module) {
        'INVENTORY' => 'bg-blue-100 text-blue-700 border-blue-200',
        'SALES' => 'bg-green-100 text-green-700 border-green-200',
        'MEMBERS' => 'bg-purple-100 text-purple-700 border-purple-200',
        'SETTINGS' => 'bg-orange-100 text-orange-700 border-orange-200',
        default => 'bg-gray-100 text-gray-700 border-gray-200',
    };
}

function statusBadgeClass($status)
{
    $status = strtoupper((string)$status);
    return match ($status) {
        'SUCCESS' => 'bg-green-100 text-green-700 border-green-200',
        'INFO' => 'bg-blue-100 text-blue-700 border-blue-200',
        'ERROR' => 'bg-red-100 text-red-700 border-red-200',
        default => 'bg-gray-100 text-gray-700 border-gray-200',
    };
}

$today = date('Y-m-d');
$selected_date = normalizeActivityDate($_GET['date'] ?? $today, $today);
$module_filter = strtoupper(trim((string)($_GET['module'] ?? 'ALL')));
$search = trim((string)($_GET['q'] ?? ''));

$filters = [];
$filter_values = [];
$filter_types = '';

if ($selected_date !== '') {
    $filters[] = 'DATE(created_at) = ?';
    $filter_values[] = $selected_date;
    $filter_types .= 's';
}

if ($module_filter !== '' && $module_filter !== 'ALL') {
    $filters[] = 'module = ?';
    $filter_values[] = $module_filter;
    $filter_types .= 's';
}

if ($search !== '') {
    $filters[] = '(module LIKE ? OR action LIKE ? OR entity_name LIKE ? OR details LIKE ? OR actor_name LIKE ?)';
    $like = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) {
        $filter_values[] = $like;
        $filter_types .= 's';
    }
}

$where_sql = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';

$logs = [];
$stmt = $conn->prepare("SELECT * FROM activity_logs {$where_sql} ORDER BY created_at DESC, log_id DESC");
if ($stmt) {
    if ($filter_values) {
        $stmt->bind_param($filter_types, ...$filter_values);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $logs[] = $row;
    }
    $stmt->close();
}

$summary_counts = [
    'TOTAL' => count($logs),
    'SUCCESS' => 0,
    'INFO' => 0,
    'ERROR' => 0,
];
$module_counts = [
    'INVENTORY' => 0,
    'SALES' => 0,
    'MEMBERS' => 0,
    'SETTINGS' => 0,
];
foreach ($logs as $log) {
    $status_key = strtoupper((string)($log['status'] ?? ''));
    if (isset($summary_counts[$status_key])) {
        $summary_counts[$status_key]++;
    }

    $module_key = strtoupper((string)($log['module'] ?? ''));
    if (isset($module_counts[$module_key])) {
        $module_counts[$module_key]++;
    }
}

$prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
if ($next_date > $today) {
    $next_date = $today;
}

$grouped_logs = [];
foreach ($logs as $log) {
    $day = date('Y-m-d', strtotime($log['created_at']));
    if (!isset($grouped_logs[$day])) {
        $grouped_logs[$day] = [];
    }
    $grouped_logs[$day][] = $log;
}

$group_keys = array_keys($grouped_logs);
rsort($group_keys);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Coop DBMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#6a1b9a', primaryDark: '#570591' }
                }
            }
        }
    </script>
    <style>
        details summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-hidden">
    <?php include 'cover_page.php'; ?>

    <div class="flex h-screen w-full">
        <div id="mobile-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 hidden md:hidden transition-opacity print:hidden" onclick="toggleSidebar()"></div>
        <aside id="sidebar" class="bg-white w-72 border-r border-gray-200 flex flex-col transition-transform transform -translate-x-full md:translate-x-0 fixed md:relative z-50 h-full shadow-lg md:shadow-none print:hidden">
            <div class="p-6 flex items-center justify-center border-b border-gray-100 relative">
                <a href="index.php" class="block">
                    <img src="img/purplearmy_logo-removebg.png" alt="Coop Logo" class="w-40 md:w-52 h-auto object-contain py-2 drop-shadow-sm transition-transform hover:scale-105">
                </a>
                <button class="absolute top-4 right-4 md:hidden text-gray-400 hover:text-gray-800" onclick="toggleSidebar()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4 flex flex-col gap-1">
                <a href="index.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-users w-6"></i> MEMBERSHIP DIRECTORY</a>
                <a href="member_shares.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-hand-holding-usd w-6"></i> MEMBER SHARES</a>
                <a href="transactions.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-receipt w-6"></i> TRANSACTIONS</a>
                <a href="inventory.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-boxes w-6"></i> INVENTORY</a>
                <a href="pos.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-shopping-cart w-6"></i> SELL / OUTSOURCE</a>
                <a href="outsourcing_report.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-chart-line w-6"></i> OUTSOURCING LOGS</a>
                <a href="database_management.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-purple-50 hover:text-primary font-semibold transition-colors"><i class="fas fa-database w-6"></i> DATABASE SETTINGS</a>
                <a href="activity_logs.php" class="flex items-center px-6 py-3 bg-primary text-white font-semibold border-l-4 border-primaryDark"><i class="fas fa-clock-rotate-left w-6"></i> ACTIVITY LOGS</a>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
            <header class="bg-white shadow-sm px-4 md:px-8 py-4 flex justify-between items-center z-10 print:hidden">
                <div class="flex items-center gap-4">
                    <button class="text-gray-500 focus:outline-none md:hidden hover:text-primary" onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Activity Logs</h1>
                        <p class="text-xs text-gray-500 mt-1">Central audit trail for inventory, sales, members, and settings.</p>
                    </div>
                </div>
                <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300 whitespace-nowrap"><i class="fas fa-print mr-2"></i>PRINT</button>
            </header>

            <div class="flex-1 overflow-y-auto p-4 md:p-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Logs Shown</div>
                        <div class="mt-2 text-2xl font-bold text-gray-900"><?= number_format($summary_counts['TOTAL']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Success</div>
                        <div class="mt-2 text-2xl font-bold text-green-700"><?= number_format($summary_counts['SUCCESS']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Info</div>
                        <div class="mt-2 text-2xl font-bold text-blue-700"><?= number_format($summary_counts['INFO']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Error</div>
                        <div class="mt-2 text-2xl font-bold text-red-700"><?= number_format($summary_counts['ERROR']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Selected Date</div>
                        <div class="mt-2 text-sm font-bold text-gray-900"><?= htmlspecialchars(activityDateLabel($selected_date)) ?></div>
                    </div>
                </div>

                <form method="GET" action="activity_logs.php" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm mb-6 print:hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                            <div class="flex gap-2">
                                <a href="?date=<?= htmlspecialchars($prev_date) ?>&module=<?= urlencode($module_filter) ?>&q=<?= urlencode($search) ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100"><i class="fas fa-chevron-left"></i></a>
                                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" max="<?= htmlspecialchars($today) ?>" class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <a href="?date=<?= htmlspecialchars($next_date) ?>&module=<?= urlencode($module_filter) ?>&q=<?= urlencode($search) ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100"><i class="fas fa-chevron-right"></i></a>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Module</label>
                            <select name="module" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                                <option value="ALL" <?= $module_filter === 'ALL' ? 'selected' : '' ?>>All Modules</option>
                                <option value="INVENTORY" <?= $module_filter === 'INVENTORY' ? 'selected' : '' ?>>Inventory</option>
                                <option value="SALES" <?= $module_filter === 'SALES' ? 'selected' : '' ?>>Sales</option>
                                <option value="MEMBERS" <?= $module_filter === 'MEMBERS' ? 'selected' : '' ?>>Members</option>
                                <option value="SETTINGS" <?= $module_filter === 'SETTINGS' ? 'selected' : '' ?>>Settings</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Search</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search logs..." class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary bg-white">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm"><i class="fas fa-filter mr-2"></i>FILTER</button>
                            <a href="activity_logs.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-md text-sm transition-colors shadow-sm border border-gray-300"><i class="fas fa-rotate-right mr-2"></i>RESET</a>
                        </div>
                    </div>
                </form>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Inventory</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['INVENTORY']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Sales</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['SALES']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Members</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['MEMBERS']) ?></div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase text-gray-500">Settings</div>
                        <div class="mt-2 text-xl font-bold text-gray-900"><?= number_format($module_counts['SETTINGS']) ?></div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h4 class="font-bold text-gray-800"><i class="fas fa-stream text-primary mr-2"></i>Activity Feed</h4>
                        <span class="text-xs font-bold text-gray-500 uppercase">Auto refresh every 30 seconds</span>
                    </div>

                    <div class="max-h-[calc(100vh-24rem)] overflow-y-auto">
                        <?php if (!empty($group_keys)): ?>
                            <div class="p-4 space-y-3">
                                <?php foreach ($group_keys as $index => $day): ?>
                                    <details class="group rounded-xl border border-gray-200 overflow-hidden bg-gray-50/60" <?= $index === 0 ? 'open' : '' ?>>
                                        <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary text-white text-xs font-bold shrink-0"><i class="fas fa-chevron-down group-open:rotate-180 transition-transform"></i></span>
                                                <div>
                                                    <div class="font-bold text-gray-800"><?= htmlspecialchars(activityDateLabel($day)) ?></div>
                                                    <div class="text-xs text-gray-500"><?= number_format(count($grouped_logs[$day])) ?> log item(s)</div>
                                                </div>
                                            </div>
                                            <div class="text-xs font-bold text-gray-500 uppercase">Day Snapshot</div>
                                        </summary>
                                        <div class="border-t border-gray-200 bg-white">
                                            <?php foreach ($grouped_logs[$day] as $log): ?>
                                                <div class="px-4 py-3 border-b border-gray-100 last:border-b-0">
                                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase border <?= htmlspecialchars(moduleBadgeClass($log['module'] ?? '')) ?>"><?= htmlspecialchars($log['module'] ?? 'SYSTEM') ?></span>
                                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase border <?= htmlspecialchars(statusBadgeClass($log['status'] ?? '')) ?>"><?= htmlspecialchars($log['status'] ?? 'SUCCESS') ?></span>
                                                                <span class="text-xs text-gray-500 font-mono"><?= htmlspecialchars(activityTimeLabel($log['created_at'])) ?></span>
                                                            </div>
                                                            <div class="font-bold text-gray-800"><?= htmlspecialchars($log['action'] ?? '') ?></div>
                                                            <div class="text-sm text-gray-600 mt-1">
                                                                <?= htmlspecialchars($log['entity_type'] ?? 'Event') ?>
                                                                <?php if (!empty($log['entity_name'])): ?>
                                                                    <span class="font-semibold text-gray-800">- <?= htmlspecialchars($log['entity_name']) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!empty($log['details'])): ?>
                                                                <div class="text-sm text-gray-500 mt-2 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($log['details']) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 lg:text-right shrink-0">
                                                            <div class="font-bold uppercase text-gray-400">Actor</div>
                                                            <div class="font-semibold text-gray-700"><?= htmlspecialchars($log['actor_name'] ?? 'SYSTEM') ?></div>
                                                            <div class="text-[11px] uppercase tracking-wide"><?= htmlspecialchars($log['actor_role'] ?? 'SYSTEM') ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="px-6 py-16 text-center text-gray-500">
                                <i class="fas fa-clock-rotate-left text-3xl mb-3 text-gray-300"></i>
                                <div class="font-bold text-gray-700">No activity logs found.</div>
                                <div class="text-sm mt-1">Try changing the date, module, or search filters.</div>
                            </div>
                        <?php endif; ?>
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

        const filterDate = document.querySelector('input[name="date"]');
        if (filterDate) {
            filterDate.addEventListener('change', function () {
                this.form.submit();
            });
        }

        let liveRefresh = null;
        if (!document.hidden) {
            liveRefresh = setInterval(() => {
                const params = new URLSearchParams(window.location.search);
                params.set('date', document.querySelector('input[name="date"]').value);
                params.set('module', document.querySelector('select[name="module"]').value);
                params.set('q', document.querySelector('input[name="q"]').value);
                window.location.search = params.toString();
            }, 30000);
        }
    </script>
</body>
</html>
