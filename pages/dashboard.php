<?php
session_start();
require_once '../includes/db.php';

// ============================================================
//  ADMIN ONLY — Redirect all non-admins
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../index.php');
    exit();
}

$db = Database::getConnection();

// ============================================================
//  1. Museum Overview — Oracle VIEW (v_museum_stats)
// ============================================================
$stats = [];
try {
    $stmt  = $db->query("SELECT * FROM v_museum_stats");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'TOTAL_ARTIFACTS'      => 0, 'TOTAL_GALLERY'      => 0,
        'TOTAL_EXHIBITIONS'    => 0, 'TOTAL_VISITORS'     => 0,
        'TOTAL_ARTIFACT_VALUE' => 0, 'TOTAL_TICKETS'      => 0,
        'TOTAL_REVENUE'        => 0,
    ];
}

// ============================================================
//  2. Artifacts by Category — GROUP BY + COUNT + SUM + AVG
// ============================================================
$artifacts_by_category = [];
try {
    $stmt = $db->query(
        "SELECT category,
                COUNT(*)             AS item_count,
                SUM(estimated_value) AS total_value,
                AVG(estimated_value) AS avg_value
         FROM artifacts
         GROUP BY category
         ORDER BY item_count DESC"
    );
    $artifacts_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  3. Top Countries by Artifact Value — GROUP BY + SUM + ORDER BY
// ============================================================
$top_countries = [];
try {
    $stmt = $db->query(
        "SELECT origin_country,
                COUNT(*)             AS artifact_count,
                SUM(estimated_value) AS total_value
         FROM artifacts
         GROUP BY origin_country
         ORDER BY total_value DESC NULLS LAST"
    );
    $top_countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  4. Gallery by Artist — GROUP BY + COUNT + HAVING
// ============================================================
$gallery_by_artist = [];
try {
    $stmt = $db->query(
        "SELECT artist_name,
                COUNT(*)           AS artwork_count,
                MIN(creation_year) AS earliest_year,
                MAX(creation_year) AS latest_year
         FROM gallery
         GROUP BY artist_name
         HAVING COUNT(*) >= 1
         ORDER BY artwork_count DESC, artist_name ASC"
    );
    $gallery_by_artist = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  5. Exhibitions by Status — GROUP BY + COUNT + AVG
// ============================================================
$exhibitions_by_status = [];
try {
    $stmt = $db->query(
        "SELECT status,
                COUNT(*)          AS exhibition_count,
                AVG(ticket_price) AS avg_ticket,
                SUM(capacity)     AS total_capacity
         FROM exhibitions
         GROUP BY status
         ORDER BY exhibition_count DESC"
    );
    $exhibitions_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  6. Ticket Sales by Exhibition — JOIN tickets + exhibitions
// ============================================================
$ticket_sales = [];
try {
    $stmt = $db->query(
        "SELECT e.title,
                COUNT(t.ticket_id)     AS booking_count,
                SUM(t.quantity)        AS tickets_sold,
                SUM(t.total_amount)    AS revenue,
                e.capacity,
                SUM(t.quantity) * 100 / NULLIF(e.capacity, 0) AS fill_pct
         FROM exhibitions e
         LEFT JOIN tickets t ON e.exhibition_id = t.exhibition_id AND t.status = 'Confirmed'
         GROUP BY e.exhibition_id, e.title, e.capacity
         ORDER BY revenue DESC NULLS LAST"
    );
    $ticket_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  7. Revenue by Ticket Type — GROUP BY + SUM + COUNT
// ============================================================
$revenue_by_type = [];
try {
    $stmt = $db->query(
        "SELECT ticket_type,
                COUNT(*)           AS booking_count,
                SUM(quantity)      AS total_tickets,
                SUM(total_amount)  AS total_revenue,
                AVG(unit_price)    AS avg_price
         FROM tickets
         WHERE status = 'Confirmed'
         GROUP BY ticket_type
         ORDER BY total_revenue DESC"
    );
    $revenue_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  8. Recent Bookings — tickets JOIN users JOIN exhibitions
// ============================================================
$recent_bookings = [];
try {
    $stmt = $db->query(
        "SELECT * FROM (
             SELECT u.username, e.title AS exhibition_title,
                    t.ticket_type, t.quantity, t.total_amount, t.status, t.booked_at
             FROM tickets t
             JOIN users u       ON t.user_id       = u.user_id
             JOIN exhibitions e ON t.exhibition_id  = e.exhibition_id
             ORDER BY t.booked_at DESC
         ) WHERE ROWNUM <= 10"
    );
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  9. Recently Registered Visitors — JOIN users + visitors
// ============================================================
$recent_visitors = [];
try {
    $stmt = $db->query(
        "SELECT * FROM (
             SELECT u.username, u.email, u.status, v.country, v.phone, u.created_at
             FROM users u
             JOIN visitors v ON u.user_id = v.user_id
             ORDER BY u.created_at DESC
         ) WHERE ROWNUM <= 8"
    );
    $recent_visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  10. Recent Audit Logs — audit_logs LEFT JOIN users
// ============================================================
$recent_logs = [];
try {
    $stmt = $db->query(
        "SELECT * FROM (
             SELECT a.action_performed, a.table_affected,
                    a.ip_address, a.log_timestamp, u.username
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.user_id
             ORDER BY a.log_timestamp DESC
         ) WHERE ROWNUM <= 10"
    );
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MuseoX Admin Panel — Database Reports and Analytics.">
    <title>MuseoX | Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <li><a href="dashboard.php" style="color: var(--secondary-color);">Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="profile.php" style="font-weight: 700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" style="color: var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <h1 style="font-size: 2.8rem; margin-bottom: 1rem;">Admin Panel</h1>
        <p style="color: var(--text-light); max-width: 680px; margin: 0 auto;">
            Live Oracle SQL analytics — Views, GROUP BY, aggregates, JOINs, and HAVING across all museum data.
        </p>
    </header>

    <section class="section" style="padding-top: 3rem;">

        <!-- QUICK ACTIONS: Phase 4 Management Panel -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.25rem;">Quick Actions</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:1rem; margin-bottom:4rem;">
            <a href="manage_artifacts.php" class="report-card" style="padding:1.5rem; text-align:center; text-decoration:none; display:block;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">🏺</div>
                <div style="font-weight:700; font-family:var(--font-heading); margin-bottom:0.25rem;">Manage Artifacts</div>
                <div style="font-size:0.8rem; color:var(--text-light);">Add · Edit · Delete</div>
            </a>
            <a href="manage_exhibitions.php" class="report-card" style="padding:1.5rem; text-align:center; text-decoration:none; display:block;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">🏛️</div>
                <div style="font-weight:700; font-family:var(--font-heading); margin-bottom:0.25rem;">Manage Exhibitions</div>
                <div style="font-size:0.8rem; color:var(--text-light);">Add · Edit · Delete</div>
            </a>
            <a href="manage_gallery.php" class="report-card" style="padding:1.5rem; text-align:center; text-decoration:none; display:block;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">🖼️</div>
                <div style="font-weight:700; font-family:var(--font-heading); margin-bottom:0.25rem;">Manage Gallery</div>
                <div style="font-size:0.8rem; color:var(--text-light);">Add · Edit · Delete</div>
            </a>
            <a href="manage_users.php" class="report-card" style="padding:1.5rem; text-align:center; text-decoration:none; display:block;">
                <div style="font-size:2rem; margin-bottom:0.5rem;">👥</div>
                <div style="font-weight:700; font-family:var(--font-heading); margin-bottom:0.25rem;">Manage Users</div>
                <div style="font-size:0.8rem; color:var(--text-light);">Activate · Suspend · Roles</div>
            </a>
        </div>

        <!-- SECTION 1: Museum Overview — Oracle VIEW -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">Oracle View: SELECT * FROM v_museum_stats</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Museum Overview</h2>
        <div class="stat-grid" style="margin-bottom: 4rem;">
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format((int)($stats['TOTAL_ARTIFACTS'] ?? 0)); ?></span>
                <span class="stat-label">Total Artifacts</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format((int)($stats['TOTAL_GALLERY'] ?? 0)); ?></span>
                <span class="stat-label">Gallery Items</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format((int)($stats['TOTAL_EXHIBITIONS'] ?? 0)); ?></span>
                <span class="stat-label">Exhibitions</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format((int)($stats['TOTAL_VISITORS'] ?? 0)); ?></span>
                <span class="stat-label">Visitors</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format((int)($stats['TOTAL_TICKETS'] ?? 0)); ?></span>
                <span class="stat-label">Tickets Sold</span>
            </div>
            <div class="stat-card">
                <span class="stat-number" style="font-size: 1.6rem;">$<?php echo number_format((float)($stats['TOTAL_REVENUE'] ?? 0), 2); ?></span>
                <span class="stat-label">Total Revenue</span>
            </div>
        </div>

        <!-- SECTION 2: Artifacts by Category -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT category, COUNT(*), SUM(estimated_value), AVG(estimated_value) FROM artifacts GROUP BY category ORDER BY COUNT(*) DESC</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Artifacts by Category</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($artifacts_by_category)): ?>
                <table class="report-table">
                    <thead><tr><th>Category</th><th>Count</th><th>Total Value</th><th>Avg. Value</th></tr></thead>
                    <tbody>
                        <?php foreach ($artifacts_by_category as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['CATEGORY'] ?? 'N/A'); ?></td>
                                <td><?php echo (int)$row['ITEM_COUNT']; ?></td>
                                <td>$<?php echo number_format((float)($row['TOTAL_VALUE'] ?? 0)); ?></td>
                                <td>$<?php echo number_format((float)($row['AVG_VALUE'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No artifact data available.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 3: Top Countries by Artifact Value -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT origin_country, COUNT(*), SUM(estimated_value) FROM artifacts GROUP BY origin_country ORDER BY SUM(estimated_value) DESC</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Top Countries by Artifact Value</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($top_countries)): ?>
                <table class="report-table">
                    <thead><tr><th>Country</th><th>Artifacts</th><th>Total Estimated Value</th></tr></thead>
                    <tbody>
                        <?php foreach ($top_countries as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['ORIGIN_COUNTRY'] ?? 'N/A'); ?></td>
                                <td><?php echo (int)$row['ARTIFACT_COUNT']; ?></td>
                                <td>$<?php echo number_format((float)($row['TOTAL_VALUE'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No data available.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 4: Gallery by Artist -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT artist_name, COUNT(*), MIN(creation_year), MAX(creation_year) FROM gallery GROUP BY artist_name HAVING COUNT(*) &gt;= 1 ORDER BY COUNT(*) DESC</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Gallery Items by Artist</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($gallery_by_artist)): ?>
                <table class="report-table">
                    <thead><tr><th>Artist</th><th>Artworks</th><th>Earliest Work</th><th>Latest Work</th></tr></thead>
                    <tbody>
                        <?php foreach ($gallery_by_artist as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['ARTIST_NAME'] ?? 'Unknown'); ?></td>
                                <td><?php echo (int)$row['ARTWORK_COUNT']; ?></td>
                                <td><?php echo htmlspecialchars($row['EARLIEST_YEAR'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['LATEST_YEAR'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No gallery data.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 5: Exhibitions by Status -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT status, COUNT(*), AVG(ticket_price), SUM(capacity) FROM exhibitions GROUP BY status ORDER BY COUNT(*) DESC</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Exhibitions Summary by Status</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($exhibitions_by_status)): ?>
                <table class="report-table">
                    <thead><tr><th>Status</th><th>Count</th><th>Avg. Ticket Price</th><th>Total Capacity</th></tr></thead>
                    <tbody>
                        <?php foreach ($exhibitions_by_status as $row): ?>
                            <tr>
                                <td>
                                    <?php $s = $row['STATUS'] ?? ''; $bc = match(strtolower($s)) { 'active' => 'badge-active', 'upcoming' => 'badge-upcoming', default => 'badge-closed' }; ?>
                                    <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($s); ?></span>
                                </td>
                                <td><?php echo (int)$row['EXHIBITION_COUNT']; ?></td>
                                <td>$<?php echo number_format((float)($row['AVG_TICKET'] ?? 0), 2); ?></td>
                                <td><?php echo number_format((int)($row['TOTAL_CAPACITY'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No exhibition data.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 6: Ticket Sales by Exhibition -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT e.title, COUNT(t.*), SUM(t.quantity), SUM(t.total_amount) FROM exhibitions e LEFT JOIN tickets t ON ... GROUP BY e.exhibition_id ORDER BY SUM(t.total_amount) DESC</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Ticket Sales by Exhibition</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($ticket_sales)): ?>
                <table class="report-table">
                    <thead><tr><th>Exhibition</th><th>Bookings</th><th>Tickets Sold</th><th>Revenue</th><th>Capacity Used</th></tr></thead>
                    <tbody>
                        <?php foreach ($ticket_sales as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['TITLE'] ?? ''); ?></td>
                                <td><?php echo (int)($row['BOOKING_COUNT'] ?? 0); ?></td>
                                <td><?php echo (int)($row['TICKETS_SOLD'] ?? 0); ?></td>
                                <td>$<?php echo number_format((float)($row['REVENUE'] ?? 0), 2); ?></td>
                                <td><?php echo number_format((float)($row['FILL_PCT'] ?? 0), 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No ticket sales data yet.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 7: Revenue by Ticket Type -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT ticket_type, COUNT(*), SUM(quantity), SUM(total_amount), AVG(unit_price) FROM tickets WHERE status = 'Confirmed' GROUP BY ticket_type ORDER BY SUM(total_amount) DESC</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Revenue by Ticket Type</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($revenue_by_type)): ?>
                <table class="report-table">
                    <thead><tr><th>Type</th><th>Bookings</th><th>Tickets</th><th>Avg. Price</th><th>Total Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($revenue_by_type as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['TICKET_TYPE'] ?? ''); ?></td>
                                <td><?php echo (int)($row['BOOKING_COUNT'] ?? 0); ?></td>
                                <td><?php echo (int)($row['TOTAL_TICKETS'] ?? 0); ?></td>
                                <td>$<?php echo number_format((float)($row['AVG_PRICE'] ?? 0), 2); ?></td>
                                <td>$<?php echo number_format((float)($row['TOTAL_REVENUE'] ?? 0), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No ticket revenue data yet.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 8: Recent Bookings -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT u.username, e.title, t.ticket_type, t.quantity, t.total_amount, t.booked_at FROM tickets t JOIN users u ... JOIN exhibitions e ... ORDER BY t.booked_at DESC ROWNUM &lt;= 10</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Recent Bookings</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($recent_bookings)): ?>
                <table class="report-table">
                    <thead><tr><th>Visitor</th><th>Exhibition</th><th>Type</th><th>Qty</th><th>Amount</th><th>Status</th><th>Booked At</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['USERNAME'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['EXHIBITION_TITLE'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['TICKET_TYPE'] ?? ''); ?></td>
                                <td><?php echo (int)($row['QUANTITY'] ?? 0); ?></td>
                                <td>$<?php echo number_format((float)($row['TOTAL_AMOUNT'] ?? 0), 2); ?></td>
                                <td>
                                    <?php $st = $row['STATUS'] ?? 'Confirmed'; ?>
                                    <span class="badge <?php echo $st === 'Confirmed' ? 'badge-active' : 'badge-closed'; ?>"><?php echo htmlspecialchars($st); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($row['BOOKED_AT'] ?? '', 0, 16)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No bookings yet.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 9: Recent Registered Visitors -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT u.username, u.email, v.country, u.created_at FROM users u JOIN visitors v ON u.user_id = v.user_id ORDER BY u.created_at DESC WHERE ROWNUM &lt;= 8</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">Recently Registered Visitors</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($recent_visitors)): ?>
                <table class="report-table">
                    <thead><tr><th>Username</th><th>Email</th><th>Country</th><th>Phone</th><th>Status</th><th>Registered</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_visitors as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['USERNAME'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['EMAIL'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['COUNTRY'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($row['PHONE'] ?? '—'); ?></td>
                                <td>
                                    <?php $st = $row['STATUS'] ?? 'Active'; ?>
                                    <span class="badge <?php echo $st === 'Active' ? 'badge-active' : 'badge-closed'; ?>"><?php echo htmlspecialchars($st); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($row['CREATED_AT'] ?? '', 0, 16)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No registered visitors yet.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION 10: Recent Audit Logs -->
        <div style="margin-bottom: 1rem;">
            <span class="db-badge">SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.log_timestamp DESC WHERE ROWNUM &lt;= 10</span>
        </div>
        <h2 class="section-title" style="text-align: left; font-size: 1.6rem; margin-bottom: 2rem;">System Audit Logs</h2>
        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($recent_logs)): ?>
                <table class="report-table">
                    <thead><tr><th>User</th><th>Action</th><th>Table</th><th>IP Address</th><th>Timestamp</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_logs as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['USERNAME'] ?? 'System'); ?></td>
                                <td><code style="font-size: 0.82rem; background: var(--surface); padding: 0.2rem 0.5rem; border-radius: 4px;"><?php echo htmlspecialchars($row['ACTION_PERFORMED'] ?? ''); ?></code></td>
                                <td><?php echo htmlspecialchars($row['TABLE_AFFECTED'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['IP_ADDRESS'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['LOG_TIMESTAMP'] ?? '', 0, 16)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No audit logs available.</p>
            <?php endif; ?>
        </div>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top: 10px; margin-bottom: 20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
