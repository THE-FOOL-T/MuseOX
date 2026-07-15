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
//  1. ROLLUP — Artifacts by Category with Grand Total
//  Demonstrates: GROUP BY ROLLUP, GROUPING() function
// ============================================================
$rollup_data = [];
try {
    $stmt = $db->query(
        "SELECT NVL(category, 'GRAND TOTAL')   AS category,
                COUNT(*)                        AS artifact_count,
                NVL(SUM(estimated_value), 0)    AS total_value,
                NVL(ROUND(AVG(estimated_value), 2), 0) AS avg_value,
                NVL(MAX(estimated_value), 0)    AS max_value,
                NVL(MIN(estimated_value), 0)    AS min_value,
                GROUPING(category)              AS is_grand_total
         FROM artifacts
         GROUP BY ROLLUP(category)
         ORDER BY GROUPING(category), NVL(category, 'Z')"
    );
    $rollup_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  2. Materialized View: mv_artifact_category_stats
//  Demonstrates reading from a pre-computed materialized view
// ============================================================
$mv_data = [];
try {
    $stmt = $db->query(
        "SELECT * FROM mv_artifact_category_stats ORDER BY artifact_count DESC"
    );
    $mv_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback: live query if MV not yet created
    try {
        $stmt = $db->query(
            "SELECT category,
                    COUNT(*)                        AS artifact_count,
                    NVL(ROUND(AVG(estimated_value),2),0) AS avg_value,
                    NVL(SUM(estimated_value),0)     AS total_value,
                    NVL(MAX(estimated_value),0)     AS max_value
             FROM artifacts WHERE category IS NOT NULL
             GROUP BY category ORDER BY artifact_count DESC"
        );
        $mv_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

// ============================================================
//  3. Correlated Subquery — Artifacts above category average
//  Demonstrates: inline view JOIN, correlated subquery logic
// ============================================================
$above_avg = [];
try {
    $stmt = $db->query(
        "SELECT a.artifact_id, a.name, a.category, a.origin_country,
                a.estimated_value, a.condition_status,
                ROUND(avg_sub.cat_avg, 2)                           AS category_avg,
                ROUND(a.estimated_value - avg_sub.cat_avg, 2)       AS above_avg_by,
                ROUND((a.estimated_value / avg_sub.cat_avg) * 100 - 100, 1) AS pct_above
         FROM artifacts a
         JOIN (
             SELECT category, AVG(estimated_value) AS cat_avg
             FROM   artifacts
             WHERE  estimated_value IS NOT NULL
             GROUP  BY category
         ) avg_sub ON a.category = avg_sub.category
         WHERE a.estimated_value > avg_sub.cat_avg
         ORDER BY above_avg_by DESC
         FETCH FIRST 10 ROWS ONLY"
    );
    $above_avg = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  4. PIVOT-style CASE WHEN — Ticket counts by type
//  Demonstrates: conditional aggregation (Oracle PIVOT equivalent)
// ============================================================
$ticket_pivot = [];
try {
    $stmt = $db->query(
        "SELECT e.title AS exhibition_title, e.wing,
                SUM(CASE WHEN t.ticket_type = 'Adult'  THEN t.quantity ELSE 0 END) AS adult_qty,
                SUM(CASE WHEN t.ticket_type = 'Child'  THEN t.quantity ELSE 0 END) AS child_qty,
                SUM(CASE WHEN t.ticket_type = 'Senior' THEN t.quantity ELSE 0 END) AS senior_qty,
                SUM(t.quantity)     AS total_qty,
                SUM(t.total_amount) AS total_revenue,
                ROUND(AVG(t.unit_price), 2) AS avg_unit_price
         FROM tickets t
         JOIN exhibitions e ON t.exhibition_id = e.exhibition_id
         WHERE t.status = 'Confirmed'
         GROUP BY e.exhibition_id, e.title, e.wing
         ORDER BY total_revenue DESC"
    );
    $ticket_pivot = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  5. v_visitor_activity — Complex view with MONTHS_BETWEEN
//  Demonstrates reading from phase6 view
// ============================================================
$visitor_activity = [];
try {
    $stmt = $db->query(
        "SELECT * FROM v_visitor_activity ORDER BY total_spent DESC FETCH FIRST 10 ROWS ONLY"
    );
    $visitor_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  6. Museum-wide summary (for report header)
// ============================================================
$museum_summary = [];
try {
    $stmt = $db->query("SELECT * FROM v_museum_stats");
    $museum_summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {}

$report_date = date('d-M-Y H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Advanced Reports</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <style>
        @media print {
            .navbar, footer, .no-print { display: none !important; }
            body { background: #fff; color: #000; }
            .report-card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
            .page-header { background: none; color: #000; padding: 1rem 0; }
            .page-header p { color: #555; }
            .section { padding: 1rem 0; }
            .db-badge { display: none; }
            h2 { color: #000; }
            .stat-card { border: 1px solid #ccc; }
        }
        .print-btn {
            position: fixed; bottom: 2rem; right: 2rem;
            background: var(--secondary-color); color: #fff;
            border: none; border-radius: 50px; padding: 0.75rem 1.5rem;
            font-weight: 700; cursor: pointer; box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            font-size: 0.9rem; z-index: 1000;
        }
        .print-btn:hover { opacity: 0.9; }
        .grand-total-row { background: #F5F0E8 !important; font-weight: 700; border-top: 2px solid var(--secondary-color); }
    </style>
</head>
<body>

    <nav class="navbar no-print">
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Advanced Reports</h1>
        <p style="color:var(--text-light);">
            Generated: <?php echo $report_date; ?> &nbsp;|&nbsp;
            ROLLUP · Materialized View · Correlated Subquery · PIVOT-style CASE WHEN · MONTHS_BETWEEN
        </p>
    </header>

    <section class="section" style="padding-top:2rem; max-width:1200px;">

        <!-- Museum Overview Banner -->
        <?php if (!empty($museum_summary)): ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:3rem;">
            <?php
            $overview = [
                'Artifacts'   => (int)($museum_summary['TOTAL_ARTIFACTS']   ?? 0),
                'Gallery'     => (int)($museum_summary['TOTAL_GALLERY']     ?? 0),
                'Exhibitions' => (int)($museum_summary['TOTAL_EXHIBITIONS'] ?? 0),
                'Members'     => (int)($museum_summary['TOTAL_VISITORS']    ?? 0),
                'Tickets Sold'=> (int)($museum_summary['TOTAL_TICKETS']     ?? 0),
            ];
            foreach ($overview as $label => $val): ?>
                <div class="stat-card" style="padding:1rem; text-align:center;">
                    <span class="stat-number" style="font-size:1.8rem;"><?php echo number_format($val); ?></span>
                    <span class="stat-label" style="font-size:0.75rem;"><?php echo $label; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== REPORT 1: ROLLUP ===== -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT NVL(category,'GRAND TOTAL'), COUNT(*), SUM(estimated_value), AVG(estimated_value), GROUPING(category) FROM artifacts GROUP BY ROLLUP(category) ORDER BY GROUPING(category)</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.25rem;">
            Report 1 — Artifacts by Category <span style="font-size:0.85rem; font-weight:400; color:var(--text-light);">(GROUP BY ROLLUP)</span>
        </h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <?php if (!empty($rollup_data)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Count</th>
                        <th>Total Value</th>
                        <th>Avg Value</th>
                        <th>Min Value</th>
                        <th>Max Value</th>
                        <th>GROUPING()</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rollup_data as $row): ?>
                        <tr class="<?php echo (int)$row['IS_GRAND_TOTAL'] ? 'grand-total-row' : ''; ?>">
                            <td style="font-weight:<?php echo (int)$row['IS_GRAND_TOTAL'] ? '700' : '400'; ?>;">
                                <?php echo htmlspecialchars($row['CATEGORY']); ?>
                            </td>
                            <td><?php echo (int)$row['ARTIFACT_COUNT']; ?></td>
                            <td>$<?php echo number_format((float)$row['TOTAL_VALUE'], 2); ?></td>
                            <td>$<?php echo number_format((float)$row['AVG_VALUE'], 2); ?></td>
                            <td>$<?php echo number_format((float)$row['MIN_VALUE'], 2); ?></td>
                            <td>$<?php echo number_format((float)$row['MAX_VALUE'], 2); ?></td>
                            <td>
                                <span style="font-size:0.8rem; color:<?php echo (int)$row['IS_GRAND_TOTAL'] ? '#9E2A2B' : '#16A34A'; ?>; font-weight:700;">
                                    <?php echo (int)$row['IS_GRAND_TOTAL'] ? '1 (Grand Total)' : '0 (Detail Row)'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No artifact data found.</p>
            <?php endif; ?>
        </div>

        <!-- ===== REPORT 2: Materialized View ===== -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT * FROM mv_artifact_category_stats ORDER BY artifact_count DESC</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.25rem;">
            Report 2 — Materialized View Stats <span style="font-size:0.85rem; font-weight:400; color:var(--text-light);">(mv_artifact_category_stats — pre-computed)</span>
        </h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <?php if (!empty($mv_data)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Count</th>
                        <th>Avg Value</th>
                        <th>Total Value</th>
                        <th>Min Value</th>
                        <th>Max Value</th>
                        <?php if (isset($mv_data[0]['EXCELLENT_COUNT'])): ?><th>Excellent</th><th>Poor</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mv_data as $row): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($row['CATEGORY']); ?></td>
                            <td><?php echo (int)$row['ARTIFACT_COUNT']; ?></td>
                            <td>$<?php echo number_format((float)$row['AVG_VALUE'], 2); ?></td>
                            <td>$<?php echo number_format((float)$row['TOTAL_VALUE'], 2); ?></td>
                            <td>$<?php echo number_format((float)$row['MIN_VALUE'], 2); ?></td>
                            <td>$<?php echo number_format((float)$row['MAX_VALUE'], 2); ?></td>
                            <?php if (isset($row['EXCELLENT_COUNT'])): ?>
                                <td style="color:#16A34A; font-weight:600;"><?php echo (int)$row['EXCELLENT_COUNT']; ?></td>
                                <td style="color:#9E2A2B; font-weight:600;"><?php echo (int)$row['POOR_COUNT']; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">Run <code>phase6.sql</code> to create the materialized view.</p>
            <?php endif; ?>
        </div>

        <!-- ===== REPORT 3: Correlated Subquery ===== -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT a.name, a.estimated_value, avg_sub.cat_avg, (a.estimated_value - avg_sub.cat_avg) AS above_avg_by FROM artifacts a JOIN (SELECT category, AVG(estimated_value) FROM artifacts GROUP BY category) avg_sub ON ... WHERE a.estimated_value > avg_sub.cat_avg ORDER BY above_avg_by DESC FETCH FIRST 10 ROWS ONLY</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.25rem;">
            Report 3 — Artifacts Above Category Average <span style="font-size:0.85rem; font-weight:400; color:var(--text-light);">(Correlated Subquery · Top 10)</span>
        </h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <?php if (!empty($above_avg)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>#</th><th>Name</th><th>Category</th><th>Country</th><th>Condition</th>
                        <th>Estimated Value</th><th>Category Avg</th><th>Above Avg By</th><th>% Above</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($above_avg as $i => $r): ?>
                        <tr>
                            <td style="color:var(--text-light);"><?php echo $i + 1; ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($r['NAME']); ?></td>
                            <td><?php echo htmlspecialchars($r['CATEGORY']); ?></td>
                            <td><?php echo htmlspecialchars($r['ORIGIN_COUNTRY'] ?? '—'); ?></td>
                            <td>
                                <?php $c = $r['CONDITION_STATUS'] ?? ''; ?>
                                <span class="badge <?php echo match($c) { 'Excellent' => 'badge-active', 'Good' => 'badge-upcoming', default => 'badge-closed' }; ?>">
                                    <?php echo htmlspecialchars($c); ?>
                                </span>
                            </td>
                            <td style="font-weight:700;">$<?php echo number_format((float)$r['ESTIMATED_VALUE'], 2); ?></td>
                            <td style="color:var(--text-light);">$<?php echo number_format((float)$r['CATEGORY_AVG'], 2); ?></td>
                            <td style="color:#16A34A; font-weight:600;">+$<?php echo number_format((float)$r['ABOVE_AVG_BY'], 2); ?></td>
                            <td style="color:#16A34A; font-weight:600;">+<?php echo number_format((float)$r['PCT_ABOVE'], 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No artifact value data found.</p>
            <?php endif; ?>
        </div>

        <!-- ===== REPORT 4: PIVOT-style Tickets ===== -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT e.title, SUM(CASE WHEN t.ticket_type='Adult' THEN t.quantity ELSE 0 END) AS adult_qty, SUM(CASE WHEN ... 'Child' ...) AS child_qty, SUM(CASE WHEN ... 'Senior' ...) AS senior_qty FROM tickets t JOIN exhibitions e ... WHERE t.status='Confirmed' GROUP BY e.exhibition_id, e.title, e.wing</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.25rem;">
            Report 4 — Ticket Sales by Exhibition <span style="font-size:0.85rem; font-weight:400; color:var(--text-light);">(PIVOT-style CASE WHEN)</span>
        </h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <?php if (!empty($ticket_pivot)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Exhibition</th><th>Wing</th>
                        <th>Adult</th><th>Child</th><th>Senior</th>
                        <th>Total Qty</th><th>Revenue</th><th>Avg Unit Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ticket_pivot as $tp): ?>
                        <tr>
                            <td style="font-weight:600; max-width:160px;"><?php echo htmlspecialchars($tp['EXHIBITION_TITLE']); ?></td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars($tp['WING'] ?? '—'); ?></td>
                            <td><?php echo (int)$tp['ADULT_QTY']; ?></td>
                            <td><?php echo (int)$tp['CHILD_QTY']; ?></td>
                            <td><?php echo (int)$tp['SENIOR_QTY']; ?></td>
                            <td style="font-weight:700;"><?php echo (int)$tp['TOTAL_QTY']; ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$tp['TOTAL_REVENUE'], 2); ?></td>
                            <td>$<?php echo number_format((float)$tp['AVG_UNIT_PRICE'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No confirmed ticket data found. Book some tickets first.</p>
            <?php endif; ?>
        </div>

        <!-- ===== REPORT 5: Visitor Activity (MONTHS_BETWEEN) ===== -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT * FROM v_visitor_activity (view uses MONTHS_BETWEEN(SYSDATE, CAST(u.created_at AS DATE)), 4 LEFT JOIN subqueries) ORDER BY total_spent DESC FETCH FIRST 10 ROWS ONLY</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.25rem;">
            Report 5 — Visitor Activity <span style="font-size:0.85rem; font-weight:400; color:var(--text-light);">(v_visitor_activity view · MONTHS_BETWEEN)</span>
        </h2>
        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($visitor_activity)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Username</th><th>Role</th><th>Country</th>
                        <th>Months Member</th><th>Tickets</th><th>Spent</th>
                        <th>Feedback</th><th>Donations</th><th>Donated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitor_activity as $va): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($va['USERNAME']); ?></td>
                            <td>
                                <span class="badge <?php echo $va['ROLE_NAME'] === 'Admin' ? 'badge-upcoming' : 'badge-active'; ?>">
                                    <?php echo htmlspecialchars($va['ROLE_NAME']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($va['COUNTRY']); ?></td>
                            <td><?php echo number_format((float)($va['MONTHS_MEMBER'] ?? 0), 1); ?> mo</td>
                            <td><?php echo (int)($va['TOTAL_TICKETS'] ?? 0); ?></td>
                            <td style="font-weight:600; color:var(--secondary-color);">$<?php echo number_format((float)($va['TOTAL_SPENT'] ?? 0), 2); ?></td>
                            <td><?php echo (int)($va['TOTAL_FEEDBACK'] ?? 0); ?></td>
                            <td><?php echo (int)($va['TOTAL_DONATIONS'] ?? 0); ?></td>
                            <td>$<?php echo number_format((float)($va['TOTAL_DONATED'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">Run <code>phase6.sql</code> to create the <code>v_visitor_activity</code> view.</p>
            <?php endif; ?>
        </div>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

    <button class="print-btn no-print" onclick="window.print()">🖨 Print Report</button>

</body>
</html>
