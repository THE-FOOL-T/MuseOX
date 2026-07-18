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
//  1. Artifact Value Rankings — RANK() OVER (PARTITION BY)
// ============================================================
$artifact_ranks = [];
try {
    $stmt = $db->query(
        "SELECT * FROM (
             SELECT artifact_id, name, category, origin_country,
                    condition_status, estimated_value, value_rank,
                    dense_rank, pct_of_category_total
             FROM v_artifact_value_rank
             WHERE value_rank <= 3
         )
         ORDER BY category, value_rank"
    );
    $artifact_ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback: inline window function query
    try {
        $stmt = $db->query(
            "SELECT * FROM (
                SELECT artifact_id, name, category, origin_country,
                       condition_status, NVL(estimated_value, 0) AS estimated_value,
                       RANK() OVER (PARTITION BY category ORDER BY NVL(estimated_value,0) DESC) AS value_rank,
                       DENSE_RANK() OVER (PARTITION BY category ORDER BY NVL(estimated_value,0) DESC) AS dense_rank,
                       ROUND(NVL(estimated_value,0) /
                           NULLIF(SUM(NVL(estimated_value,0)) OVER (PARTITION BY category),0)*100,2) AS pct_of_category_total
                FROM artifacts
                ORDER BY category, value_rank
             ) WHERE ROWNUM <= 30"
        );
        $artifact_ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

// Group artifact ranks by category for display
$ranked_by_cat = [];
foreach ($artifact_ranks as $r) {
    $ranked_by_cat[$r['CATEGORY']][] = $r;
}

// ============================================================
//  2. Exhibition Revenue Leaderboard — DENSE_RANK()
// ============================================================
$exhibition_ranks = [];
try {
    $stmt = $db->query("SELECT * FROM v_exhibition_revenue_rank ORDER BY revenue_rank");
    $exhibition_ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $db->query(
            "SELECT e.exhibition_id, e.title, e.wing, e.status,
                    NVL(t.total_revenue, 0)   AS revenue,
                    NVL(t.total_tickets, 0)   AS tickets_sold,
                    NVL(t.unique_visitors, 0) AS unique_visitors,
                    DENSE_RANK() OVER (ORDER BY NVL(t.total_revenue,0) DESC) AS revenue_rank,
                    CASE WHEN NVL(t.total_revenue,0) > 10000 THEN 'Platinum'
                         WHEN NVL(t.total_revenue,0) > 5000  THEN 'Gold'
                         WHEN NVL(t.total_revenue,0) > 1000  THEN 'Silver'
                         ELSE 'Standard' END AS revenue_tier
             FROM exhibitions e
             LEFT JOIN (
                 SELECT exhibition_id,
                        SUM(total_amount)       AS total_revenue,
                        SUM(quantity)           AS total_tickets,
                        COUNT(DISTINCT user_id) AS unique_visitors
                 FROM   tickets WHERE status = 'Confirmed' GROUP BY exhibition_id
             ) t ON e.exhibition_id = t.exhibition_id"
        );
        $exhibition_ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

// ============================================================
//  3. Monthly Ticket Trends — LAG() / LEAD()
// ============================================================
$monthly_trends = [];
try {
    $stmt = $db->query("SELECT * FROM v_monthly_ticket_trends ORDER BY month_label");
    $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $db->query(
            "SELECT month_label, monthly_revenue, ticket_count,
                    LAG(monthly_revenue)  OVER (ORDER BY month_label) AS prev_month_revenue,
                    LEAD(monthly_revenue) OVER (ORDER BY month_label) AS next_month_revenue,
                    monthly_revenue - LAG(monthly_revenue) OVER (ORDER BY month_label) AS mom_change,
                    ROUND((monthly_revenue - LAG(monthly_revenue) OVER (ORDER BY month_label)) /
                        NULLIF(LAG(monthly_revenue) OVER (ORDER BY month_label),0)*100,1) AS mom_pct_change
             FROM (
                 SELECT TO_CHAR(TRUNC(booked_at,'MM'),'YYYY-MM') AS month_label,
                        SUM(total_amount) AS monthly_revenue,
                        COUNT(ticket_id)  AS ticket_count
                 FROM tickets WHERE status = 'Confirmed'
                 GROUP BY TRUNC(booked_at,'MM')
             ) ORDER BY month_label"
        );
        $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

// ============================================================
//  4. LISTAGG — Countries per artifact category
// ============================================================
$listagg_data = [];
try {
    $stmt = $db->query(
        "SELECT category,
                COUNT(*)  AS artifact_count,
                LISTAGG(DISTINCT origin_country, ', ')
                    WITHIN GROUP (ORDER BY origin_country) AS countries_list
         FROM artifacts
         WHERE origin_country IS NOT NULL
           AND category       IS NOT NULL
         GROUP BY category
         ORDER BY category"
    );
    $listagg_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  5. ROW_NUMBER — Most recent booking per user (top 10)
// ============================================================
$row_number_data = [];
try {
    $stmt = $db->query(
        "SELECT * FROM (
             SELECT * FROM (
                 SELECT t.ticket_id, u.username, e.title AS exhibition_title,
                        t.ticket_type, t.total_amount, t.status,
                        TO_CHAR(t.booked_at, 'DD-Mon-YYYY HH24:MI') AS booked_fmt,
                        ROW_NUMBER() OVER (PARTITION BY t.user_id ORDER BY t.booked_at DESC) AS booking_seq
                 FROM tickets t
                 JOIN users u       ON t.user_id       = u.user_id
                 JOIN exhibitions e ON t.exhibition_id  = e.exhibition_id
             ) WHERE booking_seq = 1
             ORDER BY booked_fmt DESC
         ) WHERE ROWNUM <= 10"
    );
    $row_number_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Try simplified
    try {
        $stmt = $db->query(
            "SELECT * FROM (
                 SELECT t.ticket_id, u.username, e.title AS exhibition_title,
                        t.ticket_type, t.total_amount, t.status,
                        TO_CHAR(t.booked_at, 'DD-Mon-YYYY HH24:MI') AS booked_fmt,
                        ROW_NUMBER() OVER (PARTITION BY t.user_id ORDER BY t.booked_at DESC) AS booking_seq
                 FROM tickets t
                 JOIN users u       ON t.user_id       = u.user_id
                 JOIN exhibitions e ON t.exhibition_id  = e.exhibition_id
             ) WHERE booking_seq = 1"
        );
        $row_number_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

$tier_colors = [
    'Platinum' => '#1D4ED8',
    'Gold'     => '#D97706',
    'Silver'   => '#6B7280',
    'Standard' => '#9CA3AF',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Analytics — Window Functions</title>
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Analytics — Window Functions</h1>
     
    </header>

    <section class="section" style="padding-top:2rem; max-width:1200px;">

        <!-- ===== SECTION 1: RANK / DENSE_RANK / ROW_NUMBER per category ===== -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Top 3 Artifacts by Value per Category
        </h2>

        <?php if (!empty($ranked_by_cat)): ?>
            <?php foreach ($ranked_by_cat as $cat => $items): ?>
                <div style="margin-bottom:1.75rem;">
                    <div style="font-weight:700; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.5px;
                                color:var(--secondary-color); margin-bottom:0.6rem; padding-left:0.25rem;">
                        📂 <?php echo htmlspecialchars($cat); ?>
                    </div>
                    <div class="report-card">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>RANK()</th><th>DENSE_RANK()</th><th>Name</th>
                                    <th>Country</th><th>Condition</th>
                                    <th>Value</th><th>% of Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td style="font-weight:700; color:var(--secondary-color); font-size:1.1rem;">
                                            #<?php echo (int)$item['VALUE_RANK']; ?>
                                        </td>
                                        <td style="color:var(--text-light);">#<?php echo (int)$item['DENSE_RANK']; ?></td>
                                        <td style="font-weight:600;"><?php echo htmlspecialchars($item['NAME']); ?></td>
                                        <td><?php echo htmlspecialchars($item['ORIGIN_COUNTRY'] ?? '—'); ?></td>
                                        <td>
                                            <?php $c = $item['CONDITION_STATUS'] ?? ''; ?>
                                            <span class="badge <?php echo match($c) { 'Excellent' => 'badge-active', 'Good' => 'badge-upcoming', default => 'badge-closed' }; ?>">
                                                <?php echo htmlspecialchars($c); ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:700;">$<?php echo number_format((float)($item['ESTIMATED_VALUE'] ?? 0), 2); ?></td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                                <div style="background:#F0EBE3; border-radius:4px; height:8px; width:80px; overflow:hidden;">
                                                    <div style="height:100%; width:<?php echo min(100, (float)($item['PCT_OF_CATEGORY_TOTAL'] ?? 0)); ?>%;
                                                                background:var(--secondary-color); border-radius:4px;"></div>
                                                </div>
                                                <span style="font-size:0.8rem;"><?php echo number_format((float)($item['PCT_OF_CATEGORY_TOTAL'] ?? 0), 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="report-card" style="padding:2rem; color:var(--text-light);">
                Run <code>analytics.sql</code> first, or add artifacts with estimated values.
            </div>
        <?php endif; ?>

        <!-- ===== SECTION 2: Exhibition Revenue Leaderboard ===== -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Exhibition Revenue Leaderboard
        </h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <?php if (!empty($exhibition_ranks)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Rank</th><th>Exhibition</th><th>Wing</th><th>Status</th>
                        <th>Revenue</th><th>Tickets</th><th>Visitors</th><th>Tier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exhibition_ranks as $er): ?>
                        <tr>
                            <td style="font-weight:700; font-size:1.1rem; color:var(--secondary-color);">
                                #<?php echo (int)$er['REVENUE_RANK']; ?>
                            </td>
                            <td style="font-weight:600; max-width:160px;"><?php echo htmlspecialchars($er['TITLE']); ?></td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars($er['WING'] ?? '—'); ?></td>
                            <td>
                                <?php $s = $er['STATUS'] ?? ''; ?>
                                <span class="badge <?php echo match($s) { 'Active' => 'badge-active', 'Upcoming' => 'badge-upcoming', default => 'badge-closed' }; ?>">
                                    <?php echo htmlspecialchars($s); ?>
                                </span>
                            </td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$er['REVENUE'], 2); ?></td>
                            <td><?php echo (int)$er['TICKETS_SOLD']; ?></td>
                            <td><?php echo (int)$er['UNIQUE_VISITORS']; ?></td>
                            <td>
                                <?php $tier = $er['REVENUE_TIER'] ?? 'Standard'; $tc = $tier_colors[$tier] ?? '#9CA3AF'; ?>
                                <span style="background:<?php echo $tc; ?>; color:#fff; padding:0.2rem 0.65rem; border-radius:12px; font-size:0.75rem; font-weight:700;">
                                    <?php echo $tier; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No exhibition revenue data. Book some tickets first.</p>
            <?php endif; ?>
        </div>

        <!-- ===== SECTION 3: Monthly Trends — LAG / LEAD ===== -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Monthly Revenue Trends
        </h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <?php if (!empty($monthly_trends)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Revenue</th>
                        <th>Tickets</th>
                        <th>LAG (Prev Month)</th>
                        <th>LEAD (Next Month)</th>
                        <th>MoM Change</th>
                        <th>MoM %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_trends as $mt): ?>
                        <?php
                        $change = (float)($mt['MOM_CHANGE'] ?? 0);
                        $pct    = (float)($mt['MOM_PCT_CHANGE'] ?? 0);
                        $chg_color = $change > 0 ? '#16A34A' : ($change < 0 ? '#9E2A2B' : 'var(--text-light)');
                        ?>
                        <tr>
                            <td style="font-weight:700; font-family:monospace;"><?php echo htmlspecialchars($mt['MONTH_LABEL']); ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$mt['MONTHLY_REVENUE'], 2); ?></td>
                            <td><?php echo (int)$mt['TICKET_COUNT']; ?></td>
                            <td style="color:var(--text-light);">
                                <?php echo $mt['PREV_MONTH_REVENUE'] !== null ? '$' . number_format((float)$mt['PREV_MONTH_REVENUE'], 2) : '—'; ?>
                            </td>
                            <td style="color:var(--text-light);">
                                <?php echo $mt['NEXT_MONTH_REVENUE'] !== null ? '$' . number_format((float)$mt['NEXT_MONTH_REVENUE'], 2) : '—'; ?>
                            </td>
                            <td style="font-weight:700; color:<?php echo $chg_color; ?>;">
                                <?php if ($mt['MOM_CHANGE'] === null) { echo '—'; } else { echo ($change >= 0 ? '+' : '') . '$' . number_format($change, 2); } ?>
                            </td>
                            <td style="font-weight:700; color:<?php echo $chg_color; ?>;">
                                <?php if ($mt['MOM_PCT_CHANGE'] === null) { echo '—'; } else { echo ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '%'; } ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No monthly trend data. Requires confirmed tickets across multiple months.</p>
            <?php endif; ?>
        </div>

        <!-- ===== SECTION 4: LISTAGG ===== -->
        <?php if (!empty($listagg_data)): ?>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Countries of Origin per Category
        </h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <table class="report-table">
                <thead><tr><th>Category</th><th>Count</th><th>Countries (LISTAGG)</th></tr></thead>
                <tbody>
                    <?php foreach ($listagg_data as $la): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($la['CATEGORY']); ?></td>
                            <td><?php echo (int)$la['ARTIFACT_COUNT']; ?></td>
                            <td style="font-size:0.85rem; color:var(--text-light); max-width:400px;">
                                <?php echo htmlspecialchars($la['COUNTRIES_LIST']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ===== SECTION 5: ROW_NUMBER — Latest booking per user ===== -->
        <?php if (!empty($row_number_data)): ?>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Most Recent Booking per User
        </h2>
        <div class="report-card" style="margin-bottom:4rem;">
            <table class="report-table">
                <thead><tr><th>#</th><th>User</th><th>Exhibition</th><th>Type</th><th>Amount</th><th>Status</th><th>Booked</th></tr></thead>
                <tbody>
                    <?php foreach ($row_number_data as $i => $rn): ?>
                        <tr>
                            <td style="color:var(--text-light);"><?php echo $i + 1; ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($rn['USERNAME']); ?></td>
                            <td style="font-size:0.85rem; max-width:150px;"><?php echo htmlspecialchars($rn['EXHIBITION_TITLE']); ?></td>
                            <td><?php echo htmlspecialchars($rn['TICKET_TYPE']); ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$rn['TOTAL_AMOUNT'], 2); ?></td>
                            <td>
                                <?php $st = $rn['STATUS'] ?? ''; ?>
                                <span class="badge <?php echo match($st) { 'Confirmed' => 'badge-active', 'Pending' => 'badge-upcoming', default => 'badge-closed' }; ?>">
                                    <?php echo htmlspecialchars($st); ?>
                                </span>
                            </td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($rn['BOOKED_FMT'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
