<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Admin only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getConnection();

// ============================================================
//  Date filter — defaults to last 7 days using INTERVAL
//  Demonstrates: SYSDATE - INTERVAL 'N' DAY, TO_DATE, BETWEEN
// ============================================================
$default_days = 7;
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to   = sanitizeInput($_GET['date_to']   ?? '');
$filter_action = sanitizeInput($_GET['action_filter'] ?? '');
$filter_user   = sanitizeInput($_GET['user_filter']   ?? '');

// Validate dates
$use_interval = empty($date_from) && empty($date_to);

// ============================================================
//  Summary stats — last 30 days vs previous 30 days
// ============================================================
$stats = [];
try {
    $stmt = $db->query(
        "SELECT COUNT(*) AS total_logs,
                COUNT(CASE WHEN log_timestamp >= SYSDATE - INTERVAL '7'  DAY THEN 1 END) AS last_7_days,
                COUNT(CASE WHEN log_timestamp >= SYSDATE - INTERVAL '30' DAY THEN 1 END) AS last_30_days,
                COUNT(DISTINCT user_id) AS unique_users,
                COUNT(DISTINCT action_performed) AS unique_actions
         FROM audit_logs"
    );
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {}

// ============================================================
//  Action breakdown — GROUP BY action_performed
// ============================================================
$action_counts = [];
try {
    $stmt = $db->query(
        "SELECT action_performed,
                table_affected,
                COUNT(*) AS occurrences,
                COUNT(DISTINCT user_id) AS unique_users,
                MAX(log_timestamp) AS last_seen
         FROM audit_logs
         GROUP BY action_performed, table_affected
         ORDER BY occurrences DESC"
    );
    $action_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  Build filtered log query using BETWEEN + INTERVAL
// ============================================================
$logs      = [];
$sql_shown = '';

try {
    if ($use_interval) {
        // Default: SYSDATE - INTERVAL '7' DAY
        $sql_shown = "SELECT a.*, NVL(u.username,'System') FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id WHERE a.log_timestamp >= SYSDATE - INTERVAL '7' DAY ORDER BY a.log_timestamp DESC";

        $where_parts = ["a.log_timestamp >= SYSDATE - INTERVAL '$default_days' DAY"];
        $params      = [];

        if (!empty($filter_action)) {
            $where_parts[] = "a.action_performed = :p_action";
            $params[':p_action'] = $filter_action;
        }
        if (!empty($filter_user)) {
            $where_parts[] = "UPPER(u.username) LIKE UPPER(:p_user)";
            $params[':p_user'] = '%' . $filter_user . '%';
        }

        $sql  = "SELECT a.log_id, a.action_performed, a.table_affected,
                        a.ip_address,
                        TO_CHAR(a.log_timestamp, 'DD-Mon-YYYY HH24:MI:SS') AS log_ts,
                        NVL(u.username, 'System') AS username
                 FROM audit_logs a
                 LEFT JOIN users u ON a.user_id = u.user_id
                 WHERE " . implode(' AND ', $where_parts);
        $sql = "SELECT * FROM ( " . $sql . " ORDER BY a.log_timestamp DESC ) WHERE ROWNUM <= 100";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // BETWEEN TO_DATE(:start, 'YYYY-MM-DD') AND TO_DATE(:end, 'YYYY-MM-DD')
        $from_safe = !empty($date_from) ? $date_from : date('Y-m-d', strtotime('-7 days'));
        $to_safe   = !empty($date_to)   ? $date_to   : date('Y-m-d');

        $sql_shown = "SELECT a.*, NVL(u.username,'System') FROM audit_logs a LEFT JOIN users u ... WHERE a.log_timestamp BETWEEN TO_DATE('$from_safe','YYYY-MM-DD') AND TO_DATE('$to_safe','YYYY-MM-DD') + 1 ORDER BY a.log_timestamp DESC";

        $where_parts = ["a.log_timestamp BETWEEN TO_DATE(:p_from,'YYYY-MM-DD') AND TO_DATE(:p_to,'YYYY-MM-DD') + 1"];
        $params      = [':p_from' => $from_safe, ':p_to' => $to_safe];

        if (!empty($filter_action)) {
            $where_parts[] = "a.action_performed = :p_action";
            $params[':p_action'] = $filter_action;
        }
        if (!empty($filter_user)) {
            $where_parts[] = "UPPER(u.username) LIKE UPPER(:p_user)";
            $params[':p_user'] = '%' . $filter_user . '%';
        }

        $sql  = "SELECT a.log_id, a.action_performed, a.table_affected,
                        a.ip_address,
                        TO_CHAR(a.log_timestamp, 'DD-Mon-YYYY HH24:MI:SS') AS log_ts,
                        NVL(u.username, 'System') AS username
                 FROM audit_logs a
                 LEFT JOIN users u ON a.user_id = u.user_id
                 WHERE " . implode(' AND ', $where_parts);
        $sql = "SELECT * FROM ( " . $sql . " ORDER BY a.log_timestamp DESC ) WHERE ROWNUM <= 200";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Audit log query error: ' . $e->getMessage());
}

// All distinct actions for filter dropdown
$all_actions = array_unique(array_column($action_counts, 'ACTION_PERFORMED'));
sort($all_actions);

$action_colors = [
    'USER_REGISTERED'       => '#1D4ED8',
    'USER_LOGIN'            => '#16A34A',
    'USER_STATUS_CHANGED'   => '#D97706',
    'FEEDBACK_SUBMITTED'    => '#7C3AED',
    'TICKET_BOOKED'         => '#0891B2',
    'ARTIFACT_CREATED'      => '#059669',
    'ARTIFACT_UPDATED'      => '#D97706',
    'ARTIFACT_DELETED'      => '#9E2A2B',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Audit Logs</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <li><a href="dashboard.php">Admin Panel</a></li>
            <li><a href="profile.php" style="font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
            <li><a href="login.php?action=logout" class="btn btn-outline" style="padding:0.5rem 1rem;">Logout</a></li>
        </ul>
    </nav>

    <header class="page-header">
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Audit Logs</h1>
       
    </header>

    <section class="section" style="padding-top:2rem; max-width:1200px;">

        <!-- Summary Stats -->
        
        <div class="stat-grid" style="margin-bottom:3rem;">
            <div class="stat-card"><span class="stat-number"><?php echo number_format((int)($stats['TOTAL_LOGS'] ?? 0)); ?></span><span class="stat-label">Total Entries</span></div>
            <div class="stat-card"><span class="stat-number"><?php echo (int)($stats['LAST_7_DAYS'] ?? 0); ?></span><span class="stat-label">Last 7 Days</span></div>
            <div class="stat-card"><span class="stat-number"><?php echo (int)($stats['LAST_30_DAYS'] ?? 0); ?></span><span class="stat-label">Last 30 Days</span></div>
            <div class="stat-card"><span class="stat-number"><?php echo (int)($stats['UNIQUE_ACTIONS'] ?? 0); ?></span><span class="stat-label">Action Types</span></div>
        </div>

        <!-- Action Breakdown -->
        <?php if (!empty($action_counts)): ?>
        
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.25rem;">Action Breakdown</h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <table class="report-table">
                <thead><tr><th>Action</th><th>Table</th><th>Count</th><th>Unique Users</th><th>Last Seen</th></tr></thead>
                <tbody>
                    <?php foreach ($action_counts as $ac): ?>
                        <tr>
                            <td>
                                <?php $ac_key = $ac['ACTION_PERFORMED'] ?? ''; $ac_clr = $action_colors[$ac_key] ?? '#6B7280'; ?>
                                <span style="background:<?php echo $ac_clr; ?>22; color:<?php echo $ac_clr; ?>;
                                             border:1px solid <?php echo $ac_clr; ?>44; border-radius:4px;
                                             padding:0.15rem 0.6rem; font-size:0.78rem; font-weight:600; font-family:monospace;">
                                    <?php echo htmlspecialchars($ac_key); ?>
                                </span>
                            </td>
                            <td style="font-size:0.85rem; font-family:monospace;"><?php echo htmlspecialchars($ac['TABLE_AFFECTED'] ?? '—'); ?></td>
                            <td style="font-weight:700;"><?php echo (int)$ac['OCCURRENCES']; ?></td>
                            <td><?php echo (int)$ac['UNIQUE_USERS']; ?></td>
                            <td style="font-size:0.82rem; color:var(--text-light);"><?php echo htmlspecialchars(substr($ac['LAST_SEEN'] ?? '', 0, 16)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Filter Panel -->
        <div class="report-card" style="padding:1.5rem; margin-bottom:2rem;">
            <form method="GET" action="audit_logs.php" style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr auto; gap:1rem; align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label style="font-size:0.82rem;">From Date</label>
                    <input type="date" name="date_from" class="form-control" style="padding:0.55rem;"
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:0.82rem;">To Date</label>
                    <input type="date" name="date_to" class="form-control" style="padding:0.55rem;"
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:0.82rem;">Action Type</label>
                    <select name="action_filter" class="form-control" style="padding:0.55rem;">
                        <option value="">— All Actions —</option>
                        <?php foreach ($all_actions as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>"
                                    <?php if ($filter_action === $a) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($a); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:0.82rem;">Username</label>
                    <input type="text" name="user_filter" class="form-control" style="padding:0.55rem;"
                           placeholder="Search user…" value="<?php echo htmlspecialchars($filter_user); ?>">
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding:0.55rem 1rem; white-space:nowrap;">Filter</button>
                    <a href="audit_logs.php" class="btn btn-outline" style="padding:0.55rem 0.8rem;">Reset</a>
                </div>
            </form>
            <p style="font-size:0.75rem; color:var(--text-light); margin-top:0.75rem; margin-bottom:0;">
                <?php if ($use_interval): ?>
                    &nbsp;— Leave dates empty to use INTERVAL (default: last <?php echo $default_days; ?> days)
                <?php else: ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Log Entries -->
        

        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($logs)): ?>
            <table class="report-table">
                <thead>
                    <tr><th>#</th><th>Action</th><th>Table</th><th>User</th><th>IP Address</th><th>Timestamp</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="color:var(--text-light); font-size:0.8rem;"><?php echo (int)$log['LOG_ID']; ?></td>
                            <td>
                                <?php $ak = $log['ACTION_PERFORMED'] ?? ''; $clr = $action_colors[$ak] ?? '#6B7280'; ?>
                                <span style="background:<?php echo $clr; ?>22; color:<?php echo $clr; ?>;
                                             border:1px solid <?php echo $clr; ?>44; border-radius:4px;
                                             padding:0.15rem 0.55rem; font-size:0.75rem; font-weight:600; font-family:monospace;">
                                    <?php echo htmlspecialchars($ak); ?>
                                </span>
                            </td>
                            <td style="font-size:0.82rem; font-family:monospace; color:var(--text-light);"><?php echo htmlspecialchars($log['TABLE_AFFECTED'] ?? '—'); ?></td>
                            <td style="font-weight:600; font-size:0.85rem;"><?php echo htmlspecialchars($log['USERNAME']); ?></td>
                            <td style="font-size:0.8rem; font-family:monospace; color:var(--text-light);"><?php echo htmlspecialchars($log['IP_ADDRESS'] ?? '—'); ?></td>
                            <td style="font-size:0.8rem; white-space:nowrap;"><?php echo htmlspecialchars($log['LOG_TS'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; text-align:center; color:var(--text-light);">
                    No audit log entries for the selected filters.
                </p>
            <?php endif; ?>
        </div>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
