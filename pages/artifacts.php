<?php
session_start();
require_once '../includes/db.php';

$db = Database::getConnection();

// Parameters for Filtering & Searching
$search_name = $_GET['search_name'] ?? '';
$search_country = $_GET['search_country'] ?? '';
$search_category = $_GET['search_category'] ?? '';
$search_period = $_GET['search_period'] ?? '';

// Parameters for Sorting
$sort = $_GET['sort'] ?? 'newest';

// Parameters for Grouping
$group_by = $_GET['group_by'] ?? '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$params = [];
$where_clauses = [];

if ($search_name !== '') {
    $where_clauses[] = "LOWER(name) LIKE :search_name";
    $params[':search_name'] = '%' . strtolower($search_name) . '%';
}
if ($search_country !== '') {
    $where_clauses[] = "LOWER(origin_country) LIKE :search_country";
    $params[':search_country'] = '%' . strtolower($search_country) . '%';
}
if ($search_category !== '') {
    $where_clauses[] = "LOWER(category) LIKE :search_category";
    $params[':search_category'] = '%' . strtolower($search_category) . '%';
}
if ($search_period !== '') {
    $where_clauses[] = "LOWER(historical_period) LIKE :search_period";
    $params[':search_period'] = '%' . strtolower($search_period) . '%';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$items = [];
$total_pages = 1;

if ($group_by !== '') {
    // Group By Mode
    $valid_groups = ['origin_country' => 'Country', 'category' => 'Category', 'historical_period' => 'Historical Period'];
    if (!array_key_exists($group_by, $valid_groups)) {
        $group_by = 'category';
    }
    
    $group_col = $group_by; // safe to use directly since validated against array keys
    
    // Total groups for pagination
    $count_sql = "SELECT COUNT(DISTINCT $group_col) as total FROM artifacts $where_sql";
    $stmt = $db->prepare($count_sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $total_groups = $stmt->fetch(PDO::FETCH_ASSOC)['TOTAL'];
    $total_pages = ceil($total_groups / $limit);
    
    // Group query
    $sql = "SELECT * FROM (
                SELECT a.*, ROWNUM rnum FROM (
                    SELECT $group_col as group_name, COUNT(*) as item_count, SUM(estimated_value) as total_value 
                    FROM artifacts 
                    $where_sql 
                    GROUP BY $group_col 
                    ORDER BY group_name ASC
                ) a WHERE ROWNUM <= (:offset + :limit)
            ) WHERE rnum > :offset";
            
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Normal Display Mode
    $order_sql = "ORDER BY artifact_id DESC";
    if ($sort === 'val_desc') $order_sql = "ORDER BY estimated_value DESC NULLS LAST";
    if ($sort === 'val_asc') $order_sql = "ORDER BY estimated_value ASC NULLS LAST";
    if ($sort === 'oldest') $order_sql = "ORDER BY artifact_id ASC";
    if ($sort === 'newest') $order_sql = "ORDER BY artifact_id DESC";
    if ($sort === 'name_asc') $order_sql = "ORDER BY name ASC";
    if ($sort === 'name_desc') $order_sql = "ORDER BY name DESC";

    // Total artifacts for pagination
    $count_sql = "SELECT COUNT(*) as total FROM artifacts $where_sql";
    $stmt = $db->prepare($count_sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['TOTAL'];
    $total_pages = ceil($total_items / $limit);

    $sql = "SELECT * FROM (
                SELECT a.*, ROWNUM rnum FROM (
                    SELECT * FROM artifacts 
                    $where_sql 
                    $order_sql
                ) a WHERE ROWNUM <= (:offset + :limit)
            ) WHERE rnum > :offset";
            
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Artifact Catalog</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="../index.php#exhibitions">Exhibitions</a></li>
            <li><a href="artifacts.php" style="color: var(--secondary-color);">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <li><span style="color: var(--secondary-color); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['username']); ?></span></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" style="color: var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header" style="background-color: var(--surface); padding: 4rem 2rem; text-align: center; border-bottom: 1px solid var(--border);">
        <h1 style="font-size: 2.8rem; margin-bottom: 1rem;">Artifact Catalog</h1>
        <p style="color: var(--text-light); max-width: 600px; margin: 0 auto;">Discover the remnants of ancient civilizations, medieval treasures, and historical relics from around the world.</p>
    </header>

    <section class="section" style="padding-top: 3rem;">
        <div class="search-filter-bar" style="background: var(--background); border: 1px solid var(--border); padding: 2rem; border-radius: var(--radius); margin-bottom: 3rem;">
            <form method="GET" action="artifacts.php" class="filter-form">
                
                <!-- Main Search Bar -->
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-size: 1.1rem; color: var(--primary-color);">Search Artifact</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <input type="text" name="search_name" class="form-control" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Search by name, e.g. Rosetta Stone..." style="font-size: 1.1rem; padding: 1rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 2.5rem; font-size: 1.1rem;">Search</button>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border); margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1.5rem; color: var(--text-main); font-weight: 600;">Advanced Filters & Sorting</h4>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Country</label>
                        <input type="text" name="search_country" class="form-control" value="<?php echo htmlspecialchars($search_country); ?>" placeholder="e.g. Egypt">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Category</label>
                        <input type="text" name="search_category" class="form-control" value="<?php echo htmlspecialchars($search_category); ?>" placeholder="e.g. Sculpture">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Period</label>
                        <input type="text" name="search_period" class="form-control" value="<?php echo htmlspecialchars($search_period); ?>" placeholder="e.g. Ancient">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Sort By</label>
                        <select name="sort" class="form-control">
                            <option value="newest" <?php if($sort=='newest') echo 'selected'; ?>>Newest First</option>
                            <option value="oldest" <?php if($sort=='oldest') echo 'selected'; ?>>Oldest First</option>
                            <option value="val_desc" <?php if($sort=='val_desc') echo 'selected'; ?>>Highest Value First</option>
                            <option value="val_asc" <?php if($sort=='val_asc') echo 'selected'; ?>>Lowest Value First</option>
                            <option value="name_asc" <?php if($sort=='name_asc') echo 'selected'; ?>>Name A-Z</option>
                            <option value="name_desc" <?php if($sort=='name_desc') echo 'selected'; ?>>Name Z-A</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Group By</label>
                        <select name="group_by" class="form-control">
                            <option value="">None (Grid View)</option>
                            <option value="origin_country" <?php if($group_by=='origin_country') echo 'selected'; ?>>Country</option>
                            <option value="category" <?php if($group_by=='category') echo 'selected'; ?>>Category</option>
                            <option value="historical_period" <?php if($group_by=='historical_period') echo 'selected'; ?>>Historical Period</option>
                        </select>
                    </div>

                    <div style="grid-column: 1 / -1; display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem;">
                        <a href="artifacts.php" class="btn btn-outline">Clear Filters</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (count($items) === 0): ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-light);">
                <h3>No records found matching your criteria.</h3>
            </div>
        <?php else: ?>

            <?php if ($group_by !== ''): ?>
                <!-- GROUPED VIEW -->
                <div class="grid">
                    <?php foreach ($items as $group): ?>
                        <div class="card" style="padding: 2rem; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; min-height: 200px;">
                            <h3 class="card-title" style="font-size: 1.8rem; margin-bottom: 1rem;"><?php echo htmlspecialchars($group['GROUP_NAME'] ?? 'Unknown'); ?></h3>
                            <div class="card-category" style="font-size: 1rem; margin-bottom: 0.5rem;">Total Items: <?php echo $group['ITEM_COUNT']; ?></div>
                            <?php if(isset($group['TOTAL_VALUE']) && $group['TOTAL_VALUE'] > 0): ?>
                                <p style="color: var(--text-light); font-weight: 600;">Est. Total Value: $<?php echo number_format($group['TOTAL_VALUE']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- GRID VIEW -->
                <div class="grid">
                    <?php foreach ($items as $item): ?>
                        <div class="card">
                            <img src="<?php echo htmlspecialchars($item['IMAGE_URL']); ?>?v=<?php echo file_exists(__DIR__ . '/' . $item['IMAGE_URL']) ? filemtime(__DIR__ . '/' . $item['IMAGE_URL']) : time(); ?>" alt="<?php echo htmlspecialchars($item['NAME']); ?>" class="card-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1544640808-32cb4f6864c7?auto=format&fit=crop&w=600&q=80';">
                            <div class="card-content">
                                <div class="card-category">
                                    <?php echo htmlspecialchars($item['CATEGORY']); ?> • <?php echo htmlspecialchars($item['HISTORICAL_PERIOD']); ?>
                                </div>
                                <h3 class="card-title"><?php echo htmlspecialchars($item['NAME']); ?></h3>
                                <p class="card-text" style="font-size: 0.85rem; margin-bottom: 0.5rem;">
                                    <strong>Origin:</strong> <?php echo htmlspecialchars($item['ORIGIN_COUNTRY']); ?><br>
                                    <strong>Est. Value:</strong> $<?php echo number_format($item['ESTIMATED_VALUE'] ?? 0); ?>
                                </p>
                                <p class="card-text" style="margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($item['SHORT_DESCRIPTION']); ?></p>
                                <?php if (!empty($item['WIKIPEDIA_URL'])): ?>
                                    <a href="<?php echo htmlspecialchars($item['WIKIPEDIA_URL']); ?>" target="_blank" class="btn btn-outline" style="width: 100%;">Learn More</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination" style="margin-top: 4rem; display: flex; justify-content: center; gap: 0.5rem;">
                    <?php 
                        $query_string = $_GET; 
                        
                        if ($page > 1): 
                            $query_string['page'] = $page - 1;
                    ?>
                        <a href="artifacts.php?<?php echo http_build_query($query_string); ?>" class="btn btn-outline">&laquo; Prev</a>
                    <?php endif; ?>

                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php 
                            $query_string['page'] = $i;
                            $active_style = ($i === $page) ? 'background-color: var(--primary-color); color: #fff !important;' : '';
                        ?>
                        <a href="artifacts.php?<?php echo http_build_query($query_string); ?>" class="btn btn-outline" style="<?php echo $active_style; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): 
                        $query_string['page'] = $page + 1;
                    ?>
                        <a href="artifacts.php?<?php echo http_build_query($query_string); ?>" class="btn btn-outline">Next &raquo;</a>
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
