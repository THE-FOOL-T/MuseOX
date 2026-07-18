<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Admin only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../index.php');
    exit();
}

$db      = Database::getConnection();
$success = '';
$error   = '';

// ============================================================
//  Handle POST — INSERT / UPDATE / DELETE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';

    $artwork_name  = sanitizeInput($_POST['artwork_name']   ?? '');
    $artist_name   = sanitizeInput($_POST['artist_name']    ?? '');
    $category      = sanitizeInput($_POST['category']       ?? '');
    $origin_country = sanitizeInput($_POST['origin_country'] ?? '');
    $creation_year = is_numeric($_POST['creation_year'] ?? '') ? (int)$_POST['creation_year'] : null;
    $description   = sanitizeInput($_POST['description']    ?? '');
    $image_url     = sanitizeInput($_POST['image_url']      ?? '');
    $reference_url = sanitizeInput($_POST['reference_url']  ?? '');

    // -- INSERT --
    if ($form_action === 'insert') {
        if (empty($artwork_name)) {
            $error = 'Artwork name is required.';
        } else {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO gallery
                         (artwork_name, artist_name, category, origin_country,
                          creation_year, description, image_url, reference_url)
                     VALUES
                         (:p_name, :p_artist, :p_cat, :p_country,
                          :p_year, :p_desc, :p_img, :p_ref)"
                );
                $stmt->bindValue(':p_name',    $artwork_name);
                $stmt->bindValue(':p_artist',  $artist_name  ?: null);
                $stmt->bindValue(':p_cat',     $category     ?: null);
                $stmt->bindValue(':p_country', $origin_country ?: null);
                $stmt->bindValue(':p_year',    $creation_year);
                $stmt->bindValue(':p_desc',    $description  ?: null);
                $stmt->bindValue(':p_img',     $image_url    ?: null);
                $stmt->bindValue(':p_ref',     $reference_url ?: null);
                $stmt->execute();
                $success = "Gallery item \"$artwork_name\" added successfully.";
            } catch (PDOException $e) {
                $error = 'Insert failed: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }

    // -- UPDATE --
    elseif ($form_action === 'update') {
        $gallery_id = (int)($_POST['gallery_id'] ?? 0);
        if (empty($artwork_name) || $gallery_id <= 0) {
            $error = 'Invalid data.';
        } else {
            try {
                $stmt = $db->prepare(
                    "UPDATE gallery SET
                         artwork_name   = :p_name,
                         artist_name    = :p_artist,
                         category       = :p_cat,
                         origin_country = :p_country,
                         creation_year  = :p_year,
                         description    = :p_desc,
                         image_url      = :p_img,
                         reference_url  = :p_ref
                     WHERE gallery_id = :p_gid"
                );
                $stmt->bindValue(':p_name',    $artwork_name);
                $stmt->bindValue(':p_artist',  $artist_name  ?: null);
                $stmt->bindValue(':p_cat',     $category     ?: null);
                $stmt->bindValue(':p_country', $origin_country ?: null);
                $stmt->bindValue(':p_year',    $creation_year);
                $stmt->bindValue(':p_desc',    $description  ?: null);
                $stmt->bindValue(':p_img',     $image_url    ?: null);
                $stmt->bindValue(':p_ref',     $reference_url ?: null);
                $stmt->bindValue(':p_gid',     $gallery_id, PDO::PARAM_INT);
                $stmt->execute();
                $success = "Gallery item updated successfully.";
                header('Location: manage_gallery.php?success=' . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = 'Update failed: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }

    // -- DELETE --
    elseif ($form_action === 'delete') {
        $gallery_id = (int)($_POST['gallery_id'] ?? 0);
        if ($gallery_id > 0) {
            try {
                $del = $db->prepare("DELETE FROM gallery WHERE gallery_id = :p_gid");
                $del->bindValue(':p_gid', $gallery_id, PDO::PARAM_INT);
                $del->execute();
                $success = $del->rowCount() > 0 ? 'Gallery item deleted.' : 'Item not found.';
                header('Location: manage_gallery.php?success=' . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
}

if (empty($success) && isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

// ============================================================
//  Fetch gallery item for editing
// ============================================================
$editing = null;
$edit_id = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($edit_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM gallery WHERE gallery_id = :p_gid");
        $stmt->bindValue(':p_gid', $edit_id, PDO::PARAM_INT);
        $stmt->execute();
        $editing = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$view_mode = 'list';
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'add')                    $view_mode = 'add';
    elseif ($_GET['action'] === 'edit' && $editing)   $view_mode = 'edit';
}

// ============================================================
//  Fetch all gallery items with CASE WHEN era classification
// ============================================================
$gallery_items = [];
try {
    $stmt = $db->query(
        "SELECT gallery_id, artwork_name, artist_name, category,
                origin_country, creation_year,
                CASE
                    WHEN creation_year IS NULL       THEN 'Unknown Era'
                    WHEN creation_year < 1400        THEN 'Medieval & Earlier'
                    WHEN creation_year < 1700        THEN 'Renaissance'
                    WHEN creation_year < 1900        THEN 'Baroque / Classical'
                    WHEN creation_year < 1950        THEN 'Modern'
                    ELSE                                  'Contemporary'
                END AS era
         FROM gallery
         ORDER BY gallery_id DESC"
    );
    $gallery_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$categories = ['Painting', 'Sculpture', 'Photography', 'Print', 'Drawing', 'Digital', 'Mixed Media', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Manage Gallery</title>
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Manage Gallery</h1>
      
    </header>

    <section class="section" style="padding-top:2rem; max-width:1200px;">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom:1.5rem;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" style="margin-bottom:1.5rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ========== ADD / EDIT FORM ========== -->
        <?php if ($view_mode === 'add' || $view_mode === 'edit'): ?>
        <div class="report-card" style="margin-bottom:3rem;">
            <div style="padding:1.75rem 2rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="font-size:1.3rem; font-family:var(--font-heading);">
                        <?php echo $view_mode === 'add' ? 'Add Gallery Item' : 'Edit Gallery Item'; ?>
                    </h3>
                    <p style="font-size:0.78rem; color:var(--text-light); margin-top:0.2rem;">
                        <span class="db-badge"><?php echo $view_mode === 'add' ? 'INSERT INTO gallery ...' : 'UPDATE gallery SET ... WHERE gallery_id = ?'; ?></span>
                    </p>
                </div>
                <a href="manage_gallery.php" class="btn btn-outline" style="padding:0.5rem 1rem;">← Back to List</a>
            </div>
            <div style="padding:2rem;">
                <form method="POST" action="manage_gallery.php">
                    <input type="hidden" name="form_action" value="<?php echo $view_mode === 'edit' ? 'update' : 'insert'; ?>">
                    <?php if ($view_mode === 'edit'): ?>
                        <input type="hidden" name="gallery_id" value="<?php echo (int)$editing['GALLERY_ID']; ?>">
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                        <div class="form-group">
                            <label for="artwork_name">Artwork Name *</label>
                            <input type="text" id="artwork_name" name="artwork_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($editing['ARTWORK_NAME'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="artist_name">Artist Name</label>
                            <input type="text" id="artist_name" name="artist_name" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['ARTIST_NAME'] ?? ''); ?>"
                                   placeholder="e.g. Leonardo da Vinci">
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">— Select —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>"
                                        <?php if (($editing['CATEGORY'] ?? '') === $cat) echo 'selected'; ?>>
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="origin_country">Origin Country</label>
                            <input type="text" id="origin_country" name="origin_country" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['ORIGIN_COUNTRY'] ?? ''); ?>"
                                   placeholder="e.g. Italy">
                        </div>
                        <div class="form-group">
                            <label for="creation_year">Creation Year</label>
                            <input type="number" id="creation_year" name="creation_year" class="form-control"
                                   min="1" max="<?php echo date('Y'); ?>"
                                   value="<?php echo htmlspecialchars($editing['CREATION_YEAR'] ?? ''); ?>"
                                   placeholder="e.g. 1503">
                        </div>
                        <div class="form-group">
                            <label for="image_url">Image URL</label>
                            <input type="url" id="image_url" name="image_url" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['IMAGE_URL'] ?? ''); ?>"
                                   placeholder="https://...">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label for="reference_url">Reference URL (Wikipedia, etc.)</label>
                            <input type="url" id="reference_url" name="reference_url" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['REFERENCE_URL'] ?? ''); ?>"
                                   placeholder="https://en.wikipedia.org/...">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control"
                                  rows="3" style="resize:vertical;"><?php echo htmlspecialchars($editing['DESCRIPTION'] ?? ''); ?></textarea>
                    </div>
                    <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:1rem;">
                        <a href="manage_gallery.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $view_mode === 'edit' ? 'Save Changes' : 'Add to Gallery'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>

        <!-- ========== LIST VIEW ========== -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <span class="db-badge">SELECT gallery_id, artwork_name, artist_name, CASE WHEN creation_year &lt; 1400 THEN 'Medieval' ... END AS era FROM gallery ORDER BY gallery_id DESC</span>
            <a href="manage_gallery.php?action=add" class="btn btn-primary" style="white-space:nowrap;">+ Add Artwork</a>
        </div>

        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($gallery_items)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Artwork</th>
                            <th>Artist</th>
                            <th>Category</th>
                            <th>Country</th>
                            <th>Year</th>
                            <th>Era (CASE WHEN)</th>
                            <th colspan="2" style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gallery_items as $g): ?>
                            <tr>
                                <td style="color:var(--text-light); font-size:0.85rem;"><?php echo (int)$g['GALLERY_ID']; ?></td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($g['ARTWORK_NAME']); ?></td>
                                <td><?php echo htmlspecialchars($g['ARTIST_NAME'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($g['CATEGORY'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($g['ORIGIN_COUNTRY'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($g['CREATION_YEAR'] ?? '—'); ?></td>
                                <td>
                                    <span style="font-size:0.8rem; font-style:italic; color:var(--secondary-color);">
                                        <?php echo htmlspecialchars($g['ERA']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="manage_gallery.php?action=edit&id=<?php echo (int)$g['GALLERY_ID']; ?>"
                                       class="btn btn-outline" style="padding:0.25rem 0.75rem; font-size:0.8rem;">Edit</a>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete: <?php echo htmlspecialchars(addslashes($g['ARTWORK_NAME'])); ?>?');">
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="gallery_id" value="<?php echo (int)$g['GALLERY_ID']; ?>">
                                        <button type="submit" class="btn btn-outline"
                                                style="padding:0.25rem 0.75rem; font-size:0.8rem; color:#9E2A2B; border-color:#E5B3B3;">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding:3rem; text-align:center; color:var(--text-light);">
                    <p style="margin-bottom:1rem;">No gallery items found.</p>
                    <a href="manage_gallery.php?action=add" class="btn btn-primary">Add First Artwork</a>
                </div>
            <?php endif; ?>
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
