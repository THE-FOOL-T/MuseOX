<?php
session_start();
require_once '../includes/db.php';

$db = Database::getConnection();

// ============================================================
//  WITH CTE: Active Exhibitions with Ratings — plan your visit
//  Demonstrates: WITH ... AS (...), chained CTEs, CROSS JOIN
// ============================================================
$active_exhibitions = [];
try {
    $stmt = $db->query(
        "WITH ticket_stats AS (
             SELECT exhibition_id,
                    SUM(quantity)    AS tickets_sold,
                    SUM(total_amount) AS revenue
             FROM tickets WHERE status = 'Confirmed' GROUP BY exhibition_id
         ),
         feedback_stats AS (
             SELECT exhibition_id,
                    ROUND(AVG(rating), 1) AS avg_rating,
                    COUNT(*)               AS review_count
             FROM feedback WHERE status IN ('Reviewed','Closed') GROUP BY exhibition_id
         )
         SELECT e.exhibition_id, e.title, e.wing, e.description,
                e.ticket_price, e.capacity, e.status,
                TO_CHAR(e.start_date, 'DD Mon YYYY') AS start_fmt,
                TO_CHAR(e.end_date,   'DD Mon YYYY') AS end_fmt,
                NVL(ts.tickets_sold, 0)  AS tickets_sold,
                NVL(fs.avg_rating,   0)  AS avg_rating,
                NVL(fs.review_count, 0)  AS review_count,
                ROUND(NVL(ts.tickets_sold, 0) / NULLIF(e.capacity, 0) * 100, 1) AS occupancy_pct,
                CASE
                    WHEN e.start_date > SYSDATE THEN 'Opening ' || CEIL(e.start_date - SYSDATE) || ' days'
                    WHEN e.end_date   < SYSDATE THEN 'Ended'
                    ELSE 'Open Now'
                END AS visit_status
         FROM exhibitions e
         LEFT JOIN ticket_stats   ts ON e.exhibition_id = ts.exhibition_id
         LEFT JOIN feedback_stats fs ON e.exhibition_id = fs.exhibition_id
         WHERE e.status IN ('Active', 'Upcoming')
         ORDER BY e.status DESC, e.ticket_price ASC"
    );
    $active_exhibitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  PERCENTILE_CONT — Artifact price distribution per category
//  Demonstrates: PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY)
// ============================================================
$percentile_data = [];
try {
    $stmt = $db->query(
        "SELECT category,
                COUNT(*)                          AS artifact_count,
                ROUND(MIN(estimated_value), 0)    AS min_value,
                ROUND(MAX(estimated_value), 0)    AS max_value,
                ROUND(AVG(estimated_value), 0)    AS avg_value,
                ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY estimated_value), 0) AS median_value
         FROM artifacts
         WHERE estimated_value IS NOT NULL AND category IS NOT NULL
         GROUP BY category
         ORDER BY artifact_count DESC"
    );
    $percentile_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  NTILE — Top 25% most valuable artifacts
//  Demonstrates: NTILE(4) OVER (ORDER BY estimated_value DESC)
// ============================================================
$top_artifacts = [];
try {
    $stmt = $db->query(
        "SELECT * FROM (
             SELECT artifact_id, name, category, origin_country,
                    NVL(estimated_value, 0) AS estimated_value,
                    condition_status,
                    NTILE(4) OVER (ORDER BY NVL(estimated_value,0) DESC NULLS LAST) AS quartile
             FROM artifacts
         )
         WHERE quartile = 1 AND ROWNUM <= 6"
    );
    $top_artifacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  PIVOT — Artifact condition counts per category
//  Demonstrates: Oracle native PIVOT clause
// ============================================================
$pivot_data = [];
try {
    $stmt = $db->query("SELECT * FROM v_artifact_condition_pivot");
    $pivot_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback CASE WHEN equivalent
    try {
        $stmt = $db->query(
            "SELECT category,
                    COUNT(CASE WHEN condition_status = 'Excellent' THEN 1 END) AS excellent_count,
                    COUNT(CASE WHEN condition_status = 'Good'      THEN 1 END) AS good_count,
                    COUNT(CASE WHEN condition_status = 'Fair'      THEN 1 END) AS fair_count,
                    COUNT(CASE WHEN condition_status = 'Poor'      THEN 1 END) AS poor_count,
                    COUNT(*) AS total
             FROM artifacts
             WHERE category IS NOT NULL
             GROUP BY category ORDER BY category"
        );
        $pivot_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

// ============================================================
//  Museum summary stats (for hero section)
// ============================================================
$summary = [];
try {
    $stmt = $db->query("SELECT * FROM v_museum_stats");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {}

$ticket_prices = [
    'Adult'  => ['price' => 15.00, 'desc' => 'Full access to all galleries & exhibitions'],
    'Senior' => ['price' => 10.00, 'desc' => 'Available for visitors 60 years and above'],
    'Child'  => ['price' => 7.50,  'desc' => 'Children under 12 (must be with an adult)'],
];
$hours = [
    'Monday – Friday' => '10:00 AM – 6:00 PM',
    'Saturday'        => '9:00 AM – 8:00 PM',
    'Sunday'          => '10:00 AM – 7:00 PM',
    'Public Holidays' => '11:00 AM – 5:00 PM',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plan your visit to MuseoX — opening hours, ticket prices, current exhibitions, and what to expect on your trip.">
    <title>MuseoX | Plan Your Visit</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <li><a href="search.php">Search</a></li>
            <li><a href="visit.php" style="color:var(--secondary-color);">Plan Visit</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <li><a href="dashboard.php">Admin Panel</a></li>
                <?php else: ?>
                    <li><a href="feedback.php">Feedback</a></li>
                    <li><a href="donate.php">Donate</a></li>
                <?php endif; ?>
                <li><a href="profile.php" style="color:var(--secondary-color); font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding:0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="donate.php">Donate</a></li>
                <li><a href="login.php" style="color:var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding:0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <h1 style="font-size:2.8rem; margin-bottom:0.75rem;">Plan Your Visit</h1>
        <p style="color:var(--text-light); max-width:650px; margin:0 auto;">
            Everything you need to know before you arrive — opening hours, admission prices,
            current exhibitions, and our collection highlights.
        </p>
    </header>

    <section class="section" style="padding-top:3rem; max-width:1200px;">

        <!-- Museum Stats -->
        <?php if (!empty($summary)): ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:1rem; margin-bottom:3.5rem;">
            <?php
            $badges = [
                'Artifacts'   => (int)($summary['TOTAL_ARTIFACTS']   ?? 0),
                'Artworks'    => (int)($summary['TOTAL_GALLERY']      ?? 0),
                'Exhibitions' => (int)($summary['TOTAL_EXHIBITIONS']  ?? 0),
                'Members'     => (int)($summary['TOTAL_VISITORS']     ?? 0),
            ];
            foreach ($badges as $label => $val): ?>
                <div class="stat-card" style="text-align:center; padding:1.25rem;">
                    <span class="stat-number" style="font-size:2rem;"><?php echo number_format($val); ?></span>
                    <span class="stat-label" style="font-size:0.75rem;"><?php echo $label; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Hours & Admission Grid -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; margin-bottom:3.5rem;">

            <!-- Opening Hours -->
            <div class="report-card" style="padding:2rem;">
                <h2 style="font-size:1.3rem; font-family:var(--font-heading); margin-bottom:1.5rem;">🕙 Opening Hours</h2>
                <?php foreach ($hours as $day => $time): ?>
                    <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border); padding:0.65rem 0; font-size:0.9rem;">
                        <span style="color:var(--text-light);"><?php echo $day; ?></span>
                        <span style="font-weight:600;"><?php echo $time; ?></span>
                    </div>
                <?php endforeach; ?>
                <p style="font-size:0.78rem; color:var(--text-light); margin-top:1rem; line-height:1.6;">
                    ⚠️ Last admission is 1 hour before closing time.<br>
                    The museum may close early for special events.
                </p>
            </div>

            <!-- Admission Prices -->
            <div class="report-card" style="padding:2rem;">
                <h2 style="font-size:1.3rem; font-family:var(--font-heading); margin-bottom:1.5rem;">🎟 Admission Prices</h2>
                <?php foreach ($ticket_prices as $type => $info): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding:0.75rem 0;">
                        <div>
                            <div style="font-weight:700; font-size:0.95rem;"><?php echo $type; ?></div>
                            <div style="font-size:0.78rem; color:var(--text-light); margin-top:0.15rem;"><?php echo $info['desc']; ?></div>
                        </div>
                        <div style="font-size:1.2rem; font-weight:700; color:var(--secondary-color);">
                            $<?php echo number_format($info['price'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:1.25rem;">
                    <a href="exhibitions.php" class="btn btn-primary" style="width:100%; text-align:center; display:block; padding:0.75rem;">
                        Book Tickets Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Active Exhibitions — WITH CTE -->
        <?php if (!empty($active_exhibitions)): ?>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Current &amp; Upcoming Exhibitions
        </h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:1.5rem; margin-bottom:3.5rem;">
            <?php foreach ($active_exhibitions as $exh): ?>
                <div class="report-card" style="overflow:hidden;">
                    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem;">
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-light); margin-bottom:0.25rem; text-transform:uppercase; letter-spacing:0.5px;"><?php echo htmlspecialchars($exh['WING'] ?? ''); ?></div>
                            <div style="font-weight:700; font-family:var(--font-heading); font-size:1rem;"><?php echo htmlspecialchars($exh['TITLE']); ?></div>
                        </div>
                        <?php $vs = $exh['VISIT_STATUS'] ?? ''; ?>
                        <span class="badge <?php echo $vs === 'Open Now' ? 'badge-active' : 'badge-upcoming'; ?>" style="white-space:nowrap; font-size:0.72rem;">
                            <?php echo htmlspecialchars($vs); ?>
                        </span>
                    </div>
                    <div style="padding:1rem 1.5rem;">
                        <?php if (!empty($exh['DESCRIPTION'])): ?>
                            <p style="font-size:0.83rem; color:var(--text-light); margin-bottom:0.75rem; line-height:1.55;">
                                <?php echo htmlspecialchars(mb_substr($exh['DESCRIPTION'], 0, 120)) . (mb_strlen($exh['DESCRIPTION']) > 120 ? '…' : ''); ?>
                            </p>
                        <?php endif; ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.4rem; font-size:0.8rem; margin-bottom:0.75rem;">
                            <div style="color:var(--text-light);">📅 <?php echo htmlspecialchars($exh['START_FMT'] ?? '—'); ?></div>
                            <div style="color:var(--text-light);">🔚 <?php echo htmlspecialchars($exh['END_FMT'] ?? '—'); ?></div>
                            <div>
                                <?php $rating = (float)$exh['AVG_RATING']; ?>
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <span style="color:<?php echo $s <= round($rating) ? '#D97706' : '#D1D5DB'; ?>; font-size:0.9rem;">★</span>
                                <?php endfor; ?>
                                <span style="color:var(--text-light); margin-left:0.25rem;"><?php echo $rating > 0 ? number_format($rating, 1) : 'No reviews'; ?></span>
                            </div>
                            <div style="color:var(--text-light);">👥 <?php echo (int)$exh['TICKETS_SOLD']; ?> booked</div>
                        </div>
                        <?php if ((float)$exh['OCCUPANCY_PCT'] > 0): ?>
                            <div style="margin-bottom:0.75rem;">
                                <div style="display:flex; justify-content:space-between; font-size:0.73rem; color:var(--text-light); margin-bottom:0.25rem;">
                                    <span>Capacity</span>
                                    <span><?php echo number_format((float)$exh['OCCUPANCY_PCT'], 1); ?>% booked</span>
                                </div>
                                <div style="background:var(--border); border-radius:4px; height:6px; overflow:hidden;">
                                    <div style="height:100%; width:<?php echo min(100, (float)$exh['OCCUPANCY_PCT']); ?>%; background:var(--secondary-color); border-radius:4px;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:1.1rem; font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$exh['TICKET_PRICE'], 2); ?>/ticket</span>
                            <a href="book_ticket.php?exhibition_id=<?php echo (int)$exh['EXHIBITION_ID']; ?>" class="btn btn-primary" style="padding:0.4rem 1rem; font-size:0.85rem;">
                                Book Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="report-card" style="padding:2rem; margin-bottom:3rem; text-align:center; color:var(--text-light);">
                No active or upcoming exhibitions at this time.
                <a href="exhibitions.php" style="display:block; margin-top:0.75rem; color:var(--secondary-color);">Browse all exhibitions →</a>
            </div>
        <?php endif; ?>

        <!-- Top Artifacts — NTILE Q1 -->
        <?php if (!empty($top_artifacts)): ?>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Collection Highlights — Top Quartile
        </h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; margin-bottom:3.5rem;">
            <?php foreach ($top_artifacts as $art): ?>
                <div class="report-card" style="padding:1.25rem; display:flex; flex-direction:column; gap:0.4rem;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <span style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.4px; color:var(--secondary-color); font-weight:700;"><?php echo htmlspecialchars($art['CATEGORY']); ?></span>
                        <span style="background:#D97706; color:#fff; border-radius:10px; font-size:0.68rem; font-weight:700; padding:0.1rem 0.5rem;">Q1</span>
                    </div>
                    <div style="font-weight:700; font-size:0.95rem; font-family:var(--font-heading);"><?php echo htmlspecialchars($art['NAME']); ?></div>
                    <div style="font-size:0.8rem; color:var(--text-light);">
                        <?php echo htmlspecialchars($art['ORIGIN_COUNTRY'] ?? '—'); ?>
                        <?php $c = $art['CONDITION_STATUS'] ?? ''; ?>
                        &nbsp;·&nbsp;<span style="color:<?php echo match($c) { 'Excellent' => '#16A34A', 'Good' => '#D97706', default => '#9E2A2B' }; ?>;"><?php echo htmlspecialchars($c); ?></span>
                    </div>
                    <div style="font-size:1.1rem; font-weight:700; color:var(--secondary-color); margin-top:0.25rem;">
                        $<?php echo number_format((float)$art['ESTIMATED_VALUE'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- PERCENTILE table -->
        <?php if (!empty($percentile_data)): ?>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Artifact Valuation Analysis
        </h2>
        <div class="report-card" style="margin-bottom:3.5rem;">
            <table class="report-table">
                <thead>
                    <tr><th>Category</th><th>Count</th><th>Min Value</th><th>Median Value</th><th>Avg Value</th><th>Max Value</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($percentile_data as $pd): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($pd['CATEGORY']); ?></td>
                            <td><?php echo (int)$pd['ARTIFACT_COUNT']; ?></td>
                            <td>$<?php echo number_format((float)$pd['MIN_VALUE']); ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);">$<?php echo number_format((float)$pd['MEDIAN_VALUE']); ?></td>
                            <td>$<?php echo number_format((float)$pd['AVG_VALUE']); ?></td>
                            <td>$<?php echo number_format((float)$pd['MAX_VALUE']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- PIVOT table -->
        <?php if (!empty($pivot_data)): ?>
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">
            Condition Matrix by Category
        </h2>
        <div class="report-card" style="margin-bottom:4rem;">
            <table class="report-table">
                <thead>
                    <tr><th>Category</th><th style="color:#16A34A;">Excellent</th><th style="color:#D97706;">Good</th><th style="color:#0891B2;">Fair</th><th style="color:#9E2A2B;">Poor</th>
                    <?php if (isset($pivot_data[0]['TOTAL'])): ?><th>Total</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pivot_data as $pv): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($pv['CATEGORY']); ?></td>
                            <td style="font-weight:600; color:#16A34A;"><?php echo (int)($pv['EXCELLENT_COUNT'] ?? 0); ?></td>
                            <td style="font-weight:600; color:#D97706;"><?php echo (int)($pv['GOOD_COUNT']      ?? 0); ?></td>
                            <td style="font-weight:600; color:#0891B2;"><?php echo (int)($pv['FAIR_COUNT']      ?? 0); ?></td>
                            <td style="font-weight:600; color:#9E2A2B;"><?php echo (int)($pv['POOR_COUNT']      ?? 0); ?></td>
                            <?php if (isset($pv['TOTAL'])): ?><td><?php echo (int)$pv['TOTAL']; ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Visitor Info / FAQ -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">Good to Know</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1rem; margin-bottom:4rem;">
            <?php
            $info = [
                ['♿', 'Accessibility', 'Full wheelchair access on all floors. Tactile guides and audio descriptions available at the front desk.'],
                ['🚌', 'Getting Here', 'Located in the city center, accessible by all major bus routes. Parking available in the adjacent multi-story car park.'],
                ['📸', 'Photography', 'Personal photography is permitted in most galleries. Flash photography and tripods are not allowed.'],
                ['🎒', 'Groups & Schools', 'Group discounts available for 15+ visitors. Educational programs for schools available on weekdays.'],
                ['🍽', 'Café & Shop', 'The museum café is open during all visiting hours. The gift shop stocks books, prints, and exclusive items.'],
                ['🔒', 'Storage', 'Locker facilities available at the entrance. Large bags must be stored before entering the galleries.'],
            ];
            foreach ($info as [$icon, $title, $desc]): ?>
                <div class="report-card" style="padding:1.25rem;">
                    <div style="font-size:1.5rem; margin-bottom:0.5rem;"><?php echo $icon; ?></div>
                    <div style="font-weight:700; font-family:var(--font-heading); font-size:0.95rem; margin-bottom:0.4rem;"><?php echo $title; ?></div>
                    <div style="font-size:0.82rem; color:var(--text-light); line-height:1.6;"><?php echo $desc; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Call to Action -->
        <div style="text-align:center; padding:3rem 2rem; background:var(--surface); border-radius:12px; border:1px solid var(--border); margin-bottom:2rem;">
            <h2 style="font-family:var(--font-heading); font-size:1.8rem; margin-bottom:0.75rem;">Ready to Visit?</h2>
            <p style="color:var(--text-light); max-width:480px; margin:0 auto 1.75rem;">
                Browse our current exhibitions and book your tickets online for guaranteed entry.
                Members enjoy free access all year round.
            </p>
            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                <a href="exhibitions.php" class="btn btn-primary">Browse Exhibitions</a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-outline">Become a Member</a>
                <?php else: ?>
                    <a href="feedback.php" class="btn btn-outline">Leave a Review</a>
                <?php endif; ?>
                <a href="donate.php" class="btn btn-outline">Support Us</a>
            </div>
        </div>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
