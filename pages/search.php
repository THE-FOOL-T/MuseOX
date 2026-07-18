<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$db = Database::getConnection();

$query   = sanitizeInput($_GET['q'] ?? '');
$results = [];
$total   = 0;

// ============================================================
//  UNION ALL cross-table search
//  Demonstrates: UNION ALL across 3 tables, UPPER() for
//  case-insensitive search, ORDER BY on combined result set
// ============================================================
if (!empty($query) && strlen($query) >= 2) {
    try {
        /*
         * UNION ALL — combines results from 3 separate SELECT statements:
         *   1. artifacts  — searched by name + category + description
         *   2. gallery    — searched by artwork_name + artist_name + description
         *   3. exhibitions — searched by title + wing + description
         *
         * Each SELECT returns the same 5 columns so they can be UNION'd.
         * bind variable names must be unique per part (:p_kw1, :p_kw2, :p_kw3)
         * because PDO OCI does not allow reusing named placeholders.
         */
        $stmt = $db->prepare(
            "SELECT * FROM (
                SELECT 'Artifact'   AS result_type,
                       artifact_id  AS result_id,
                       name         AS result_title,
                       category     AS result_subtitle,
                       image_url,
                       short_description AS description
                FROM artifacts
                WHERE UPPER(name)             LIKE UPPER(:p_kw1)
                   OR UPPER(category)         LIKE UPPER(:p_kw2)
                   OR UPPER(short_description) LIKE UPPER(:p_kw3)

                UNION ALL

                SELECT 'Gallery'     AS result_type,
                       gallery_id    AS result_id,
                       artwork_name  AS result_title,
                       artist_name   AS result_subtitle,
                       image_url,
                       description
                FROM gallery
                WHERE UPPER(artwork_name) LIKE UPPER(:p_kw4)
                   OR UPPER(artist_name)  LIKE UPPER(:p_kw5)
                   OR UPPER(description)  LIKE UPPER(:p_kw6)

                UNION ALL

                SELECT 'Exhibition'   AS result_type,
                       exhibition_id  AS result_id,
                       title          AS result_title,
                       wing           AS result_subtitle,
                       image_url,
                       description
                FROM exhibitions
                WHERE UPPER(title)       LIKE UPPER(:p_kw7)
                   OR UPPER(wing)        LIKE UPPER(:p_kw8)
                   OR UPPER(description) LIKE UPPER(:p_kw9)
            )
            ORDER BY result_type, result_title"
        );

        $kw = '%' . $query . '%';
        for ($i = 1; $i <= 9; $i++) {
            $stmt->bindValue(":p_kw$i", $kw);
        }
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total   = count($results);
    } catch (PDOException $e) {
        error_log('Search error: ' . $e->getMessage());
    }
}

// Group by type for display
$grouped = [];
foreach ($results as $r) {
    $grouped[$r['RESULT_TYPE']][] = $r;
}

$type_icons = ['Artifact' => '🏺', 'Gallery' => '🖼️', 'Exhibition' => '🏛️'];
$type_links = [
    'Artifact'   => 'artifacts.php',
    'Gallery'    => 'gallery.php',
    'Exhibition' => 'exhibitions.php',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Search across all MuseoX collections — artifacts, gallery artworks, and exhibitions.">
    <title>MuseoX | Search<?php echo !empty($query) ? ' — ' . htmlspecialchars($query) : ''; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <li><a href="search.php" style="color:var(--secondary-color);">Search</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <li><a href="dashboard.php">Admin Panel</a></li>
                <?php else: ?>
                    <li><a href="feedback.php">Feedback</a></li>
                    <li><a href="donate.php">Donate</a></li>
                <?php endif; ?>
                <li><a href="profile.php" style="font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding:0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="donate.php">Donate</a></li>
                <li><a href="login.php" style="color:var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding:0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <h1 style="font-size:2.6rem; margin-bottom:1.25rem;">Search Collections</h1>
        <!-- Big search bar -->
        <form method="GET" action="search.php" style="max-width:650px; margin:0 auto;">
            <div style="display:flex; gap:0; box-shadow:0 2px 12px rgba(0,0,0,0.12); border-radius:8px; overflow:hidden;">
                <input type="text" name="q" id="q" value="<?php echo htmlspecialchars($query); ?>"
                       placeholder="Search artifacts, artworks, exhibitions…"
                       style="flex:1; padding:1rem 1.25rem; border:none; font-size:1rem;
                              background:#fff; color:var(--primary-color); outline:none;"
                       autocomplete="off" autofocus>
                <button type="submit"
                        style="padding:1rem 1.75rem; background:var(--secondary-color); border:none;
                               color:#fff; font-weight:700; cursor:pointer; font-size:1rem;">
                    Search
                </button>
            </div>
        </form>
        <?php if (!empty($query)): ?>
            <p style="color:var(--text-light); margin-top:0.75rem; font-size:0.9rem;">
                <?php echo $total; ?> result<?php echo $total !== 1 ? 's' : ''; ?> for
                "<strong><?php echo htmlspecialchars($query); ?></strong>"
            </p>
        <?php endif; ?>
    </header>

    <section class="section" style="padding-top:3rem; max-width:1100px;">

        <?php if (!empty($query)): ?>

            <!-- SQL Badge -->
            

            <?php if (empty($results)): ?>
                <div style="text-align:center; padding:4rem 2rem; color:var(--text-light);">
                    <div style="font-size:3rem; margin-bottom:1rem;">🔍</div>
                    <h3 style="font-family:var(--font-heading); margin-bottom:0.5rem;">No results found</h3>
                    <p>Try different keywords — search works across artifact names, artwork titles, and exhibitions.</p>
                </div>
            <?php else: ?>
                <?php foreach ($grouped as $type => $items): ?>
                    <div style="margin-bottom:3rem;">
                        <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:1.25rem;">
                            <span style="font-size:1.5rem;"><?php echo $type_icons[$type] ?? '📄'; ?></span>
                            <h2 style="font-size:1.4rem; font-family:var(--font-heading);"><?php echo $type; ?>s</h2>
                            <span style="background:var(--secondary-color); color:#fff; border-radius:20px; padding:0.1rem 0.7rem; font-size:0.8rem; font-weight:700;"><?php echo count($items); ?></span>
                            <a href="<?php echo $type_links[$type] ?? '#'; ?>"
                               style="margin-left:auto; font-size:0.85rem; color:var(--secondary-color); text-decoration:none;">
                                View all <?php echo strtolower($type); ?>s →
                            </a>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.25rem;">
                            <?php foreach ($items as $item):
                                $detail_url = match($type) {
                                    'Exhibition' => "book_ticket.php?id=" . (int)$item['RESULT_ID'],
                                    default      => $type_links[$type]
                                };
                            ?>
                                <div class="report-card" style="overflow:hidden;">
                                    <?php if (!empty($item['IMAGE_URL'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['IMAGE_URL']); ?>"
                                             alt="<?php echo htmlspecialchars($item['RESULT_TITLE']); ?>"
                                             style="width:100%; height:140px; object-fit:cover;"
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div style="height:80px; background:var(--bg-secondary); display:flex; align-items:center; justify-content:center; font-size:2rem;">
                                            <?php echo $type_icons[$type] ?? '📄'; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="padding:1rem;">
                                        <div style="font-weight:700; font-family:var(--font-heading); font-size:0.95rem; margin-bottom:0.3rem;">
                                            <?php echo htmlspecialchars($item['RESULT_TITLE']); ?>
                                        </div>
                                        <?php if (!empty($item['RESULT_SUBTITLE'])): ?>
                                            <div style="font-size:0.82rem; color:var(--text-light); margin-bottom:0.5rem;">
                                                <?php echo htmlspecialchars($item['RESULT_SUBTITLE']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['DESCRIPTION'])): ?>
                                            <p style="font-size:0.82rem; color:var(--text-light); margin-bottom:0.75rem;">
                                                <?php echo htmlspecialchars(mb_substr($item['DESCRIPTION'], 0, 90)) . (mb_strlen($item['DESCRIPTION']) > 90 ? '…' : ''); ?>
                                            </p>
                                        <?php endif; ?>
                                        <a href="<?php echo $detail_url; ?>"
                                           class="btn btn-outline"
                                           style="font-size:0.82rem; padding:0.35rem 0.9rem;">
                                            View <?php echo $type; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty state / suggestions -->
            <div style="text-align:center; padding:3rem 0; color:var(--text-light);">
                <p style="font-size:1rem; margin-bottom:2rem;">Search across all museum collections in one place.</p>
                <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                    <?php foreach (['Egypt', 'Renaissance', 'Space', 'Sculpture', 'Ancient', 'Modern Art'] as $s): ?>
                        <a href="search.php?q=<?php echo urlencode($s); ?>"
                           style="padding:0.5rem 1.25rem; border:1px solid var(--border); border-radius:20px;
                                  text-decoration:none; color:var(--primary-color); font-size:0.9rem; background:#FDFBF7;">
                            <?php echo $s; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
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
