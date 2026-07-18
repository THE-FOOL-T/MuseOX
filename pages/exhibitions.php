<?php
session_start();
require_once '../includes/db.php';

$db = Database::getConnection();

// Search Parameters
$search_title  = $_GET['search_title']  ?? '';
$search_wing   = $_GET['search_wing']   ?? '';
$search_status = $_GET['search_status'] ?? '';

// Sorting
$sort = $_GET['sort'] ?? 'upcoming';

// Grouping
$group_by = $_GET['group_by'] ?? '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit  = 12;
$offset = ($page - 1) * $limit;

$params       = [];
$where_clauses = [];

if ($search_title !== '') {
    $where_clauses[] = "LOWER(title) LIKE :search_title";
    $params[':search_title'] = '%' . strtolower($search_title) . '%';
}
if ($search_wing !== '') {
    $where_clauses[] = "LOWER(wing) LIKE :search_wing";
    $params[':search_wing'] = '%' . strtolower($search_wing) . '%';
}
if ($search_status !== '') {
    $where_clauses[] = "LOWER(status) = :search_status";
    $params[':search_status'] = strtolower($search_status);
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$items       = [];
$total_pages = 1;

if ($group_by !== '') {
    // GROUP BY Mode — demonstrates Oracle GROUP BY + Aggregate Functions
    $valid_groups = ['wing' => 'Wing', 'status' => 'Status'];
    if (!array_key_exists($group_by, $valid_groups)) {
        $group_by = 'wing';
    }
    $group_col = $group_by;

    $count_sql = "SELECT COUNT(DISTINCT $group_col) AS total FROM exhibitions $where_sql";
    $stmt = $db->prepare($count_sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $total_groups = (int)($stmt->fetch(PDO::FETCH_ASSOC)['TOTAL'] ?? 0);
    $total_pages  = max(1, ceil($total_groups / $limit));

    $sql = "SELECT * FROM (
                SELECT a.*, ROWNUM rnum FROM (
                    SELECT $group_col AS group_name,
                           COUNT(*)             AS item_count,
                           AVG(ticket_price)    AS avg_price,
                           SUM(capacity)        AS total_capacity,
                           MIN(start_date)      AS earliest_start
                    FROM exhibitions
                    $where_sql
                    GROUP BY $group_col
                    ORDER BY group_name ASC
                ) a WHERE ROWNUM <= (:offset + :limit)
            ) WHERE rnum > :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Normal Display Mode — demonstrates Oracle ORDER BY + Pagination (ROWNUM)
    $order_sql = "ORDER BY start_date ASC";
    if ($sort === 'upcoming')    $order_sql = "ORDER BY start_date ASC";
    if ($sort === 'start_desc')  $order_sql = "ORDER BY start_date DESC";
    if ($sort === 'price_asc')   $order_sql = "ORDER BY ticket_price ASC  NULLS LAST";
    if ($sort === 'price_desc')  $order_sql = "ORDER BY ticket_price DESC NULLS LAST";
    if ($sort === 'name_asc')    $order_sql = "ORDER BY title ASC";
    if ($sort === 'name_desc')   $order_sql = "ORDER BY title DESC";
    if ($sort === 'capacity')    $order_sql = "ORDER BY capacity DESC NULLS LAST";

    $count_sql = "SELECT COUNT(*) AS total FROM exhibitions $where_sql";
    $stmt = $db->prepare($count_sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $total_items = (int)($stmt->fetch(PDO::FETCH_ASSOC)['TOTAL'] ?? 0);
    $total_pages = max(1, ceil($total_items / $limit));

    $sql = "SELECT * FROM (
                SELECT a.*, ROWNUM rnum FROM (
                    SELECT * FROM exhibitions
                    $where_sql
                    $order_sql
                ) a WHERE ROWNUM <= (:offset + :limit)
            ) WHERE rnum > :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper: return correct badge class for exhibition status
function statusBadge(string $status): string {
    return match(strtolower($status)) {
        'active'   => 'badge-active',
        'upcoming' => 'badge-upcoming',
        'closed'   => 'badge-closed',
        default    => 'badge-closed',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse current, upcoming, and past museum exhibitions at MuseoX.">
    <title>MuseoX | Exhibitions</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php" style="color:var(--secondary-color);">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <li><a href="search.php">Search</a></li>
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
        <h1 style="font-size: 2.8rem; margin-bottom: 1rem;">Exhibitions</h1>
        <p style="color: var(--text-light); max-width: 600px; margin: 0 auto;">Discover current, upcoming, and past exhibitions across all museum wings.</p>
    </header>

    <section class="section" style="padding-top: 3rem;">

        <!-- SEARCH & FILTER BAR -->
        <div class="search-filter-bar" style="margin-bottom: 3rem;">
            <form method="GET" action="exhibitions.php">

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-size: 1.1rem; color: var(--primary-color);">Search Exhibitions</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <input type="text" name="search_title" class="form-control"
                               value="<?php echo htmlspecialchars($search_title); ?>"
                               placeholder="Search by title, e.g. The Fall of Rome..."
                               style="font-size: 1.1rem; padding: 1rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2.5rem; font-size: 1.1rem;">Search</button>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border); margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1.5rem; color: var(--text-main); font-weight: 600;">Advanced Filters &amp; Sorting</h4>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; align-items: end;">

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Wing</label>
                        <input type="text" name="search_wing" class="form-control"
                               value="<?php echo htmlspecialchars($search_wing); ?>"
                               placeholder="e.g. Historical Wing">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Status</label>
                        <select name="search_status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="active"   <?php if ($search_status === 'active')   echo 'selected'; ?>>Active</option>
                            <option value="upcoming" <?php if ($search_status === 'upcoming') echo 'selected'; ?>>Upcoming</option>
                            <option value="closed"   <?php if ($search_status === 'closed')   echo 'selected'; ?>>Closed</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Sort By</label>
                        <select name="sort" class="form-control">
                            <option value="upcoming"   <?php if ($sort === 'upcoming')   echo 'selected'; ?>>Soonest First</option>
                            <option value="start_desc" <?php if ($sort === 'start_desc') echo 'selected'; ?>>Latest First</option>
                            <option value="price_asc"  <?php if ($sort === 'price_asc')  echo 'selected'; ?>>Lowest Price</option>
                            <option value="price_desc" <?php if ($sort === 'price_desc') echo 'selected'; ?>>Highest Price</option>
                            <option value="capacity"   <?php if ($sort === 'capacity')   echo 'selected'; ?>>Largest Capacity</option>
                            <option value="name_asc"   <?php if ($sort === 'name_asc')   echo 'selected'; ?>>Name A–Z</option>
                            <option value="name_desc"  <?php if ($sort === 'name_desc')  echo 'selected'; ?>>Name Z–A</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Group By</label>
                        <select name="group_by" class="form-control">
                            <option value="">None (Grid View)</option>
                            <option value="wing"   <?php if ($group_by === 'wing')   echo 'selected'; ?>>Wing</option>
                            <option value="status" <?php if ($group_by === 'status') echo 'selected'; ?>>Status</option>
                        </select>
                    </div>

                    <div style="grid-column: 1 / -1; display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem;">
                        <a href="exhibitions.php" class="btn btn-outline">Clear Filters</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- RESULTS -->
        <?php if (count($items) === 0): ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-light);">
                <h3>No exhibitions found matching your criteria.</h3>
            </div>
        <?php else: ?>

            <?php if ($group_by !== ''): ?>
                <!-- GROUPED VIEW -->
                <div class="grid">
                    <?php foreach ($items as $group): ?>
                        <div class="card" style="padding: 2rem; text-align: center; display: flex; flex-direction: column; justify-content: center; min-height: 220px;">
                            <h3 class="card-title" style="font-size: 1.8rem; margin-bottom: 1rem;">
                                <?php echo htmlspecialchars($group['GROUP_NAME'] ?? 'Unknown'); ?>
                            </h3>
                            <div class="card-category" style="font-size: 0.95rem; margin-bottom: 0.5rem;">
                                Total Exhibitions: <?php echo $group['ITEM_COUNT']; ?>
                            </div>
                            <?php if (isset($group['AVG_PRICE']) && $group['AVG_PRICE'] > 0): ?>
                                <p style="color: var(--text-light); font-size: 0.9rem;">
                                    Avg. Ticket: $<?php echo number_format((float)$group['AVG_PRICE'], 2); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (isset($group['TOTAL_CAPACITY']) && $group['TOTAL_CAPACITY'] > 0): ?>
                                <p style="color: var(--text-light); font-size: 0.9rem;">
                                    Total Capacity: <?php echo number_format((int)$group['TOTAL_CAPACITY']); ?> visitors
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- GRID VIEW -->
                <div class="grid">
                    <?php foreach ($items as $item): ?>
                        <div class="card">
                            <?php
                                $img = !empty($item['IMAGE_URL']) ? htmlspecialchars($item['IMAGE_URL']) : '';
                                $fallback = 'https://placehold.co/600x340/2C2420/FDFBF7?text=' . urlencode($item['TITLE'] ?? 'Exhibition');
                            ?>
                            <img src="<?php echo $img ?: $fallback; ?>"
                                 alt="<?php echo htmlspecialchars($item['TITLE']); ?>"
                                 class="card-img"
                                 onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                            <div class="card-content">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                    <div class="card-category"><?php echo htmlspecialchars($item['WING'] ?? ''); ?></div>
                                    <span class="badge <?php echo statusBadge($item['STATUS'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($item['STATUS'] ?? ''); ?>
                                    </span>
                                </div>
                                <h3 class="card-title"><?php echo htmlspecialchars($item['TITLE']); ?></h3>
                                <p class="card-text" style="font-size: 0.85rem; margin-bottom: 0.75rem;">
                                    <?php
                                        $sd = $item['START_DATE'] ?? '';
                                        $ed = $item['END_DATE']   ?? '';
                                        if ($sd && $ed) {
                                            echo '<strong>Dates:</strong> '
                                                . htmlspecialchars(substr($sd, 0, 11))
                                                . ' — '
                                                . htmlspecialchars(substr($ed, 0, 11));
                                        }
                                    ?>
                                    <br>
                                    <?php if (isset($item['TICKET_PRICE']) && $item['TICKET_PRICE'] > 0): ?>
                                        <strong>Ticket:</strong> $<?php echo number_format((float)$item['TICKET_PRICE'], 2); ?>
                                    <?php endif; ?>
                                    <?php if (isset($item['CAPACITY']) && $item['CAPACITY'] > 0): ?>
                                        &nbsp;·&nbsp; <strong>Capacity:</strong> <?php echo number_format((int)$item['CAPACITY']); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="card-text" style="margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($item['DESCRIPTION'] ?? ''); ?>
                                </p>
                                <?php if ($item['STATUS'] === 'Active' || $item['STATUS'] === 'Upcoming'): ?>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'Admin'): ?>
                                        <a href="book_ticket.php?id=<?php echo (int)$item['EXHIBITION_ID']; ?>" class="btn btn-primary" style="width: 100%;">Book Tickets</a>
                                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                                        <a href="login.php" class="btn btn-primary" style="width: 100%;">Sign In to Book</a>
                                    <?php else: ?>
                                        <span class="btn btn-outline" style="width: 100%; cursor: default; display: block; text-align: center; opacity: 0.7;">Admin View</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="btn btn-outline" style="width: 100%; cursor: default; opacity: 0.6; display: block; text-align: center;">Exhibition Closed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                        $query_string = $_GET;
                        if ($page > 1):
                            $query_string['page'] = $page - 1;
                    ?>
                        <a href="exhibitions.php?<?php echo http_build_query($query_string); ?>" class="btn btn-outline">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php
                            $query_string['page'] = $i;
                            $active_style = ($i === $page) ? 'background-color: var(--primary-color); color: #fff !important;' : '';
                        ?>
                        <a href="exhibitions.php?<?php echo http_build_query($query_string); ?>" class="btn btn-outline" style="<?php echo $active_style; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages):
                        $query_string['page'] = $page + 1; ?>
                        <a href="exhibitions.php?<?php echo http_build_query($query_string); ?>" class="btn btn-outline">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top: 10px; margin-bottom: 20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
