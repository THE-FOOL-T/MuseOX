<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="About MuseoX — A full-stack Oracle database project covering museum management with PHP, PL/SQL, and advanced SQL features.">
    <title>MuseoX | About the Project</title>
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
            <li><a href="visit.php">Plan Visit</a></li>
            <li><a href="about.php" style="color:var(--secondary-color);">About</a></li>
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
        <h1 style="font-size:2.8rem; margin-bottom:0.75rem;">About MuseoX</h1>
        <p style="color:var(--text-light); max-width:680px; margin:0 auto;">
            A full-stack museum management system built with PHP &amp; Oracle Database,
            demonstrating core and advanced database concepts across 6 development phases.
        </p>
    </header>

    <section class="section" style="padding-top:3rem; max-width:1050px;">

        <!-- Project Summary -->
        <div class="report-card" style="padding:2rem 2.5rem; margin-bottom:3rem;">
            <h2 style="font-family:var(--font-heading); font-size:1.5rem; margin-bottom:1.25rem;">Project Overview</h2>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
                <div>
                    <p style="color:var(--text-light); line-height:1.8; margin-bottom:1rem;">
                        <strong>MuseoX</strong> is a museum management database project developed as a coursework
                        submission. It demonstrates a complete web application backed by an <strong>Oracle 11g+</strong>
                        relational database, accessed via PHP PDO OCI.
                    </p>
                    <p style="color:var(--text-light); line-height:1.8;">
                        The project covers the full lifecycle of a museum system — from visitor registration and
                        artifact browsing to ticket booking, admin management, feedback collection, and
                        donation processing — all backed by advanced Oracle SQL and PL/SQL.
                    </p>
                </div>
                <div style="display:grid; gap:0.75rem; align-content:start;">
                    <?php
                    $info = [
                        'Project'  => 'MuseoX Museum Management System',
                        'Database' => 'Oracle Database 11g+',
                        'Backend'  => 'PHP 8.x with PDO OCI',
                        'Frontend' => 'Vanilla HTML5 + CSS3',
                        'Phases'   => '8 (complete)',
                        'Tables'   => '9 core tables',
                        'SQL Files'=> '10 .sql files',
                        'Pages'    => '23 PHP pages',
                        'Developer'=> 'Torikul',
                    ];
                    foreach ($info as $k => $v): ?>
                        <div style="display:flex; gap:1rem; border-bottom:1px solid var(--border); padding-bottom:0.4rem;">
                            <span style="font-weight:700; font-size:0.85rem; min-width:90px; color:var(--secondary-color);"><?php echo $k; ?></span>
                            <span style="font-size:0.85rem; color:var(--text-light);"><?php echo $v; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Database Schema -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">Database Schema (9 Tables)</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1rem; margin-bottom:3rem;">
            <?php
            $tables = [
                ['roles',       'Stores Admin / Visitor role definitions',         'role_id, role_name, description'],
                ['users',       'Authentication table — username, email, password', 'user_id, username, email, password, role_id, status'],
                ['visitors',    'Extended visitor profile (phone, country)',        'visitor_id, user_id, phone, country'],
                ['artifacts',   'Core artifact catalog with value & condition',     'artifact_id, name, category, origin_country, acquisition_date, condition_status, estimated_value'],
                ['gallery',     'Virtual gallery — artworks with era & origin',     'gallery_id, artwork_name, artist_name, category, creation_year'],
                ['exhibitions', 'Museum exhibitions with capacity & pricing',       'exhibition_id, title, wing, status, start_date, end_date, ticket_price, capacity'],
                ['tickets',     'Visitor ticket bookings (Adult/Child/Senior)',     'ticket_id, user_id, exhibition_id, ticket_type, quantity, unit_price, total_amount, status'],
                ['feedback',    'Visitor ratings (1–5 stars) per exhibition',      'feedback_id, user_id, exhibition_id, rating, status'],
                ['donations',   'Museum donations by purpose with anonymous flag',  'donation_id, user_id, donor_name, amount, purpose, is_anonymous'],
                ['audit_logs',  'System-wide action audit trail',                  'log_id, user_id, action_performed, table_affected, ip_address, log_timestamp'],
            ];
            foreach ($tables as [$tbl, $desc, $cols]): ?>
                <div class="report-card" style="padding:1.25rem;">
                    <div style="font-weight:700; font-family:monospace; color:var(--secondary-color); margin-bottom:0.35rem; font-size:0.9rem;"><?php echo $tbl; ?></div>
                    <div style="font-size:0.8rem; color:var(--primary-color); margin-bottom:0.5rem;"><?php echo $desc; ?></div>
                    <div style="font-size:0.72rem; color:var(--text-light); font-family:monospace; line-height:1.6;"><?php echo $cols; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Phases Breakdown -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">Development Phases</h2>
        <?php
        $phases = [
            ['Phase 1', 'Foundation & Authentication',
             'oracle.sql (schema + triggers)',
             ['CREATE TABLE (users, roles, visitors, audit_logs)', 'TRIGGER for auto-increment IDs (no sequences)', 'STORED PROCEDURE sp_RegisterVisitor', 'Hash-based login (bcrypt in PHP)', 'PDO OCI connection (db.php)', 'Session-based auth']],

            ['Phase 2', 'Artifact & Gallery Catalogs',
             'artifacts.sql, gallery.sql',
             ['SELECT with WHERE, ORDER BY, LIKE search', 'ROWNUM-based pagination', 'Category filter (WHERE category = ?)', 'JOIN with aliases', 'Card-based UI with image display']],

            ['Phase 3', 'Exhibitions, Tickets & Profile',
             'exhibitions.sql, tickets.sql',
             ['sp_BookTicket PL/SQL procedure with capacity check', 'RAISE_APPLICATION_ERROR for business rule violations', 'RETURNING INTO for new ticket ID', 'Password change (SELECT INTO + UPDATE)', 'Admin-only dashboard access control']],

            ['Phase 4', 'Admin CRUD & Indexes',
             'indexes.sql',
             ['INSERT / UPDATE / DELETE via PHP forms', 'TO_DATE() and TO_CHAR() for date handling', 'CASE WHEN for value tier classification', 'CREATE INDEX (regular, composite, function-based)', 'FUNCTION fn_GetArtifactCount, fn_GetTicketRevenue', 'FK-safe DELETE (checks related records first)']],

            ['Phase 5', 'Feedback, Donations & Search',
             'feedback-donations.sql',
             ['ORACLE PACKAGE pkg_MuseoX (SPEC + BODY)', 'sp_SubmitFeedback, sp_RecordDonation procedures', 'fn_GetExhibitionRating, fn_GetDonationByPurpose functions', 'GROUP BY + AVG + HAVING for ratings analytics', 'UNION ALL cross-table search (artifacts + gallery + exhibitions)', 'Anonymous PL/SQL BEGIN...END block for sample data']],

            ['Phase 6', 'Advanced Features & Reports',
             'advance.sql',
             ['MATERIALIZED VIEW mv_artifact_category_stats (BUILD IMMEDIATE, REFRESH ON DEMAND)', 'SYNONYM (mx_artifacts, mx_gallery… for 8 tables)', 'Explicit CURSOR with OPEN/FETCH/CLOSE', '%ROWTYPE and %TYPE variable declarations', 'Cursor attributes: %NOTFOUND, %ROWCOUNT, %ISOPEN', 'GROUP BY ROLLUP with GROUPING() function', 'MERGE statement (INSERT or UPDATE)', 'MONTHS_BETWEEN for membership duration', 'PIVOT-style CASE WHEN for ticket type breakdown', 'Print-ready reports with @media print CSS']],

            ['Phase 7', 'Analytics, Audit Trail & Ticket Confirmation',
             'analytics.sql',
             ['RANK() OVER (PARTITION BY category ORDER BY estimated_value DESC)', 'DENSE_RANK() for exhibition revenue leaderboard with tier', 'ROW_NUMBER() OVER (PARTITION BY user_id) for latest booking', 'LAG() and LEAD() for month-over-month revenue comparison', 'LISTAGG(origin_country, ", ") WITHIN GROUP (ORDER BY origin_country)', 'BETWEEN TO_DATE(:from) AND TO_DATE(:to)+1 for date range filter', 'SYSDATE - INTERVAL "7" DAY for default audit window', 'NULLIF() in window frame to avoid divide-by-zero', 'Ticket confirmation: 4-table JOIN + NVL + TO_CHAR + security check', 'Consistent site-wide navbar across all pages']],

            ['Phase 8', 'Final Features & Visit Page',
             'final_queries.sql',
             ['WITH CTE (3-level chained CTEs: ticket_stats, feedback_stats, combined)', 'Oracle native PIVOT — condition matrix for artifacts', 'NTILE(4) — divide artifacts into value quartiles', 'FIRST_VALUE / LAST_VALUE with UNBOUNDED frame clause', 'PERCENTILE_CONT(0.5) — median artifact value per category', 'PERCENTILE_DISC — discrete distribution analysis', 'CONNECT BY LEVEL — calendar/sequence generation from DUAL', 'CROSS JOIN for multi-CTE visit summary view', 'visit.php — Plan Your Visit public page with live DB data', 'README.md — Full project documentation']],
        ];

        foreach ($phases as [$phase, $title, $sql_files, $features]): ?>
            <div class="report-card" style="margin-bottom:1.5rem; overflow:hidden;">
                <div style="padding:1.25rem 1.75rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <span style="background:var(--secondary-color); color:#fff; border-radius:4px; padding:0.2rem 0.6rem; font-size:0.78rem; font-weight:700; margin-right:0.75rem;"><?php echo $phase; ?></span>
                        <strong style="font-size:1.05rem; font-family:var(--font-heading);"><?php echo $title; ?></strong>
                    </div>
                </div>
                <ul style="margin:0; padding:1.25rem 1.75rem; display:grid; grid-template-columns:1fr 1fr; gap:0.3rem 1.5rem; list-style:none;">
                    <?php foreach ($features as $f): ?>
                        <li style="font-size:0.83rem; color:var(--text-light); padding:0.2rem 0;">
                            <span style="color:var(--secondary-color); margin-right:0.4rem;">✓</span><?php echo htmlspecialchars($f); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <!-- Oracle SQL Features Quick Reference -->
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem; margin-top:2rem;">Oracle SQL Features Reference</h2>
        <div class="report-card" style="padding:2rem; margin-bottom:4rem;">
            <?php
            $feature_groups = [
                'DDL' => ['CREATE TABLE', 'DROP TABLE', 'ALTER TABLE', 'CREATE INDEX', 'CREATE VIEW', 'CREATE MATERIALIZED VIEW', 'CREATE SYNONYM', 'CREATE SEQUENCE (alternative: trigger)'],
                'DML' => ['INSERT INTO', 'UPDATE ... SET', 'DELETE FROM', 'MERGE INTO ... WHEN MATCHED ... WHEN NOT MATCHED'],
                'DQL' => ['SELECT ... FROM ... WHERE', 'JOIN (INNER, LEFT)', 'GROUP BY', 'HAVING', 'ORDER BY', 'ROLLUP', 'UNION ALL', 'FETCH FIRST N ROWS ONLY', 'ROWNUM', 'BETWEEN ... AND', 'WITH ... AS (...) — CTE', 'PIVOT ... FOR col IN (...)', 'CROSS JOIN', 'CONNECT BY LEVEL'],
                'Oracle Functions' => ['NVL', 'NVL2', 'NULLIF', 'COALESCE', 'DECODE', 'TO_CHAR', 'TO_DATE', 'TRUNC', 'ROUND', 'MONTHS_BETWEEN', 'ADD_MONTHS', 'UPPER', 'LPAD', 'SUBSTR', 'LISTAGG', 'SYSDATE', 'INTERVAL'],
                'Aggregates' => ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'GROUPING()', 'PERCENTILE_CONT', 'PERCENTILE_DISC'],
                'Window Functions' => ['RANK() OVER (PARTITION BY ... ORDER BY ...)', 'DENSE_RANK() OVER (...)', 'ROW_NUMBER() OVER (PARTITION BY ...)', 'LAG(col) OVER (ORDER BY ...)', 'LEAD(col) OVER (ORDER BY ...)', 'NTILE(4) OVER (...)', 'FIRST_VALUE() OVER (... ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)', 'LAST_VALUE() OVER (...)', 'SUM(...) OVER (PARTITION BY ...)'],
                'Constraints' => ['PRIMARY KEY', 'FOREIGN KEY … REFERENCES', 'CHECK', 'NOT NULL', 'DEFAULT', 'UNIQUE', 'ON DELETE CASCADE', 'ON DELETE SET NULL'],
                'PL/SQL' => ['PROCEDURE', 'FUNCTION', 'PACKAGE (SPEC + BODY)', 'TRIGGER', 'CURSOR (explicit)', '%ROWTYPE', '%TYPE', 'RAISE_APPLICATION_ERROR', 'COMMIT', 'ROLLBACK', 'EXCEPTION … WHEN OTHERS', 'RETURNING INTO', 'DBMS_OUTPUT.PUT_LINE'],
                'Misc' => ['CASE WHEN … THEN … END', 'Anonymous PL/SQL Block (BEGIN…END)', 'SELECT … FROM DUAL', 'Bind Variables (:p_name)', 'FETCH FIRST … ROWS ONLY', 'Correlated Subquery', 'REGEXP_LIKE'],
            ];
            foreach ($feature_groups as $group => $feats): ?>
                <div style="margin-bottom:1.5rem;">
                    <div style="font-weight:700; font-size:0.9rem; color:var(--secondary-color); margin-bottom:0.6rem; text-transform:uppercase; letter-spacing:0.5px;"><?php echo $group; ?></div>
                    <div style="display:flex; flex-wrap:wrap; gap:0.4rem;">
                        <?php foreach ($feats as $f): ?>
                            <span style="background:#F0EBE3; border:1px solid var(--border); border-radius:4px; padding:0.2rem 0.65rem; font-size:0.78rem; font-family:monospace; color:var(--primary-color);"><?php echo htmlspecialchars($f); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
