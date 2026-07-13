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
//  Summary Stats — SUM, AVG, COUNT
// ============================================================
$stats = [];
try {
    $stmt = $db->query(
        "SELECT COUNT(*)                    AS total_donations,
                NVL(SUM(amount), 0)         AS total_raised,
                NVL(ROUND(AVG(amount), 2), 0) AS avg_donation,
                NVL(MAX(amount), 0)         AS largest_gift,
                COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN user_id END) AS registered_donors
         FROM donations"
    );
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {}

// ============================================================
//  Donations by Purpose — GROUP BY + ROUND + ORDER BY
// ============================================================
$by_purpose = [];
try {
    $stmt = $db->query(
        "SELECT purpose,
                COUNT(*)              AS num_donations,
                SUM(amount)           AS total,
                ROUND(AVG(amount),2)  AS avg_amount,
                MAX(amount)           AS largest
         FROM donations
         GROUP BY purpose
         ORDER BY total DESC"
    );
    $by_purpose = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  Using package function: fn_GetDonationByPurpose
//  Returns total for 'Education Programs' as a demonstration
// ============================================================
$edu_total = 0;
try {
    $stmt = $db->query("SELECT pkg_MuseoX.fn_GetDonationByPurpose('Education Programs') AS edu_total FROM dual");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $edu_total = (float)($row['EDU_TOTAL'] ?? 0);
} catch (PDOException $e) {}

// ============================================================
//  All Donations — LEFT JOIN users, NVL for anonymous,
//  TRUNC(donated_at) for date-only grouping
// ============================================================
$donations = [];
try {
    $stmt = $db->query(
        "SELECT d.donation_id,
                CASE WHEN d.is_anonymous = 1 THEN 'Anonymous Patron'
                     ELSE d.donor_name
                END                             AS display_name,
                d.donor_email,
                d.amount,
                d.purpose,
                d.message,
                d.is_anonymous,
                d.donated_at,
                NVL(u.username, '—')            AS username
         FROM donations d
         LEFT JOIN users u ON d.user_id = u.user_id
         ORDER BY d.donated_at DESC"
    );
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  Top Non-Anonymous Donors — correlated subquery style
// ============================================================
$top_donors = [];
try {
    $stmt = $db->query(
        "SELECT donor_name, donor_email,
                COUNT(*)        AS num_donations,
                SUM(amount)     AS total_given
         FROM donations
         WHERE is_anonymous = 0
         GROUP BY donor_name, donor_email
         ORDER BY total_given DESC
         FETCH FIRST 5 ROWS ONLY"
    );
    $top_donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$grand_total = (float)($stats['TOTAL_RAISED'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Manage Donations</title>
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Donation Analytics</h1>
        <p style="color:var(--text-light);">
            COUNT, SUM, AVG, MAX — GROUP BY purpose, CASE WHEN for anonymous masking, FETCH FIRST for top donors
        </p>
    </header>

    <section class="section" style="padding-top:2rem; max-width:1200px;">

        <!-- Overview Cards -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT COUNT(*), NVL(SUM(amount),0), ROUND(AVG(amount),2), MAX(amount), COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN user_id END) FROM donations</span>
        </div>
        <div class="stat-grid" style="margin-bottom:3rem;">
            <div class="stat-card">
                <span class="stat-number">$<?php echo number_format((float)($stats['TOTAL_RAISED'] ?? 0), 0); ?></span>
                <span class="stat-label">Total Raised</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo (int)($stats['TOTAL_DONATIONS'] ?? 0); ?></span>
                <span class="stat-label">Total Donations</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">$<?php echo number_format((float)($stats['AVG_DONATION'] ?? 0), 2); ?></span>
                <span class="stat-label">Avg per Donation</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">$<?php echo number_format((float)($stats['LARGEST_GIFT'] ?? 0), 2); ?></span>
                <span class="stat-label">Largest Gift</span>
            </div>
        </div>

        <!-- Package Function Demo -->
        <div class="report-card" style="padding:1.25rem 1.75rem; margin-bottom:2rem; display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
            <div>
                <span class="db-badge">SELECT pkg_MuseoX.fn_GetDonationByPurpose('Education Programs') AS edu_total FROM dual</span>
                <p style="font-size:0.82rem; color:var(--text-light); margin-top:0.35rem;">Calling package function from PHP via PDO</p>
            </div>
            <div style="margin-left:auto; text-align:right;">
                <div style="font-size:1.6rem; font-weight:700; color:var(--secondary-color);">$<?php echo number_format($edu_total, 2); ?></div>
                <div style="font-size:0.8rem; color:var(--text-light);">Education Programs Fund</div>
            </div>
        </div>

        <!-- By Purpose -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT purpose, COUNT(*), SUM(amount), ROUND(AVG(amount),2), MAX(amount) FROM donations GROUP BY purpose ORDER BY total DESC</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">Breakdown by Purpose</h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <?php if (!empty($by_purpose)): ?>
            <table class="report-table">
                <thead><tr><th>Purpose</th><th>Donations</th><th>Total</th><th>Average</th><th>Largest</th><th>Share</th></tr></thead>
                <tbody>
                    <?php foreach ($by_purpose as $p): ?>
                        <?php $pct = $grand_total > 0 ? round(((float)$p['TOTAL'] / $grand_total) * 100, 1) : 0; ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($p['PURPOSE']); ?></td>
                            <td><?php echo (int)$p['NUM_DONATIONS']; ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$p['TOTAL'], 2); ?></td>
                            <td>$<?php echo number_format((float)$p['AVG_AMOUNT'], 2); ?></td>
                            <td>$<?php echo number_format((float)$p['LARGEST'], 2); ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <div style="background:#F0EBE3; border-radius:4px; height:8px; width:100px; overflow:hidden;">
                                        <div style="height:100%; width:<?php echo $pct; ?>%; background:var(--secondary-color); border-radius:4px;"></div>
                                    </div>
                                    <span style="font-size:0.82rem; color:var(--text-light);"><?php echo $pct; ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No donations yet.</p>
            <?php endif; ?>
        </div>

        <!-- Top Donors -->
        <?php if (!empty($top_donors)): ?>
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT donor_name, SUM(amount) AS total_given FROM donations WHERE is_anonymous = 0 GROUP BY donor_name, donor_email ORDER BY total_given DESC FETCH FIRST 5 ROWS ONLY</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">Top Donors</h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <table class="report-table">
                <thead><tr><th>Rank</th><th>Donor</th><th>Email</th><th>Donations</th><th>Total Given</th></tr></thead>
                <tbody>
                    <?php foreach ($top_donors as $i => $td): ?>
                        <tr>
                            <td style="font-weight:700; color:var(--secondary-color); font-size:1.1rem;">#<?php echo $i + 1; ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($td['DONOR_NAME']); ?></td>
                            <td style="font-size:0.85rem; color:var(--text-light);"><?php echo htmlspecialchars($td['DONOR_EMAIL'] ?? '—'); ?></td>
                            <td><?php echo (int)$td['NUM_DONATIONS']; ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$td['TOTAL_GIVEN'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- All Donations -->
        <div style="margin-bottom:0.75rem;">
            <span class="db-badge">SELECT CASE WHEN d.is_anonymous=1 THEN 'Anonymous Patron' ELSE d.donor_name END, d.amount, d.purpose, NVL(u.username,'—') FROM donations d LEFT JOIN users u ON d.user_id = u.user_id ORDER BY d.donated_at DESC</span>
        </div>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">All Donations</h2>
        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($donations)): ?>
            <table class="report-table">
                <thead><tr><th>#</th><th>Donor</th><th>Amount</th><th>Purpose</th><th>Member</th><th>Anonymous</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($donations as $d): ?>
                        <tr>
                            <td style="color:var(--text-light);"><?php echo (int)$d['DONATION_ID']; ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($d['DISPLAY_NAME']); ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$d['AMOUNT'], 2); ?></td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars($d['PURPOSE']); ?></td>
                            <td style="font-size:0.85rem; color:var(--text-light);"><?php echo htmlspecialchars($d['USERNAME']); ?></td>
                            <td><?php echo (int)$d['IS_ANONYMOUS'] ? '<span class="badge badge-closed">Yes</span>' : '<span class="badge badge-active">No</span>'; ?></td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars(substr($d['DONATED_AT'] ?? '', 0, 10)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No donations recorded yet.</p>
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
