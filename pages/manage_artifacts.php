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

    // -- INSERT new artifact --
    if ($form_action === 'insert') {
        $name            = sanitizeInput($_POST['name']             ?? '');
        $category        = sanitizeInput($_POST['category']         ?? '');
        $origin_country  = sanitizeInput($_POST['origin_country']   ?? '');
        $acquisition_date = $_POST['acquisition_date']              ?? '';
        $condition_status = sanitizeInput($_POST['condition_status'] ?? 'Good');
        $estimated_value = is_numeric($_POST['estimated_value'] ?? '') ? (float)$_POST['estimated_value'] : null;
        $description     = sanitizeInput($_POST['description']       ?? '');
        $image_url       = sanitizeInput($_POST['image_url']         ?? '');

        if (empty($name) || empty($category)) {
            $error = 'Name and Category are required fields.';
        } else {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO artifacts
                         (name, category, origin_country, acquisition_date,
                          condition_status, estimated_value, description, image_url)
                     VALUES
                         (:p_name, :p_cat, :p_country,
                          TO_DATE(NULLIF(:p_acq, ''), 'YYYY-MM-DD'),
                          :p_cond, :p_val, :p_desc, :p_img)"
                );
                $stmt->bindValue(':p_name',    $name);
                $stmt->bindValue(':p_cat',     $category);
                $stmt->bindValue(':p_country', $origin_country  ?: null);
                $stmt->bindValue(':p_acq',     $acquisition_date ?: '');
                $stmt->bindValue(':p_cond',    $condition_status);
                $stmt->bindValue(':p_val',     $estimated_value);
                $stmt->bindValue(':p_desc',    $description     ?: null);
                $stmt->bindValue(':p_img',     $image_url       ?: null);
                $stmt->execute();
                $success = "Artifact \"$name\" added successfully.";
            } catch (PDOException $e) {
                $error = 'Insert failed: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }

    // -- UPDATE existing artifact --
    elseif ($form_action === 'update') {
        $artifact_id     = (int)($_POST['artifact_id']             ?? 0);
        $name            = sanitizeInput($_POST['name']             ?? '');
        $category        = sanitizeInput($_POST['category']         ?? '');
        $origin_country  = sanitizeInput($_POST['origin_country']   ?? '');
        $acquisition_date = $_POST['acquisition_date']              ?? '';
        $condition_status = sanitizeInput($_POST['condition_status'] ?? 'Good');
        $estimated_value = is_numeric($_POST['estimated_value'] ?? '') ? (float)$_POST['estimated_value'] : null;
        $description     = sanitizeInput($_POST['description']       ?? '');
        $image_url       = sanitizeInput($_POST['image_url']         ?? '');

        if (empty($name) || empty($category) || $artifact_id <= 0) {
            $error = 'Invalid data. Name and Category are required.';
        } else {
            try {
                $stmt = $db->prepare(
                    "UPDATE artifacts SET
                         name             = :p_name,
                         category         = :p_cat,
                         origin_country   = :p_country,
                         acquisition_date = TO_DATE(NULLIF(:p_acq, ''), 'YYYY-MM-DD'),
                         condition_status = :p_cond,
                         estimated_value  = :p_val,
                         description      = :p_desc,
                         image_url        = :p_img
                     WHERE artifact_id = :p_id"
                );
                $stmt->bindValue(':p_name',    $name);
                $stmt->bindValue(':p_cat',     $category);
                $stmt->bindValue(':p_country', $origin_country   ?: null);
                $stmt->bindValue(':p_acq',     $acquisition_date ?: '');
                $stmt->bindValue(':p_cond',    $condition_status);
                $stmt->bindValue(':p_val',     $estimated_value);
                $stmt->bindValue(':p_desc',    $description      ?: null);
                $stmt->bindValue(':p_img',     $image_url        ?: null);
                $stmt->bindValue(':p_id',      $artifact_id, PDO::PARAM_INT);
                $stmt->execute();
                $success = "Artifact \"$name\" updated successfully.";
                header('Location: manage_artifacts.php?success=' . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = 'Update failed: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }

    // -- DELETE artifact --
    elseif ($form_action === 'delete') {
        $artifact_id = (int)($_POST['artifact_id'] ?? 0);
        if ($artifact_id > 0) {
            try {
                $del = $db->prepare("DELETE FROM artifacts WHERE artifact_id = :p_id");
                $del->bindValue(':p_id', $artifact_id, PDO::PARAM_INT);
                $del->execute();
                $success = $del->rowCount() > 0 ? 'Artifact deleted successfully.' : 'Artifact not found.';
                header('Location: manage_artifacts.php?success=' . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = 'Delete failed (the artifact may have related records): ' . $e->getMessage();
            }
        }
    }
}

// Carry over success from redirect
if (empty($success) && isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

// ============================================================
//  Fetch artifact for editing (GET ?action=edit&id=X)
// ============================================================
$editing  = null;
$edit_id  = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($edit_id > 0) {
    try {
        $stmt = $db->prepare(
            "SELECT a.*,
                    TO_CHAR(a.acquisition_date, 'YYYY-MM-DD') AS acq_date_input
             FROM artifacts a
             WHERE a.artifact_id = :p_id"
        );
        $stmt->bindValue(':p_id', $edit_id, PDO::PARAM_INT);
        $stmt->execute();
        $editing = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Determine view mode
$view_mode = 'list';
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'add')                      $view_mode = 'add';
    elseif ($_GET['action'] === 'edit' && $editing)     $view_mode = 'edit';
}

// ============================================================
//  Fetch all artifacts with Oracle CASE WHEN + TO_CHAR
// ============================================================
$artifacts = [];
try {
    $stmt = $db->query(
        "SELECT artifact_id, name, category, origin_country,
                TO_CHAR(acquisition_date, 'DD-MON-YYYY') AS acq_date_fmt,
                condition_status, estimated_value,
                CASE
                    WHEN estimated_value IS NULL        THEN 'Unknown'
                    WHEN estimated_value <   50000      THEN 'Low'
                    WHEN estimated_value <  500000      THEN 'Medium'
                    WHEN estimated_value < 5000000      THEN 'High'
                    ELSE                                     'Exceptional'
                END AS value_tier
         FROM artifacts
         ORDER BY artifact_id DESC"
    );
    $artifacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$categories      = ['Sculpture', 'Painting', 'Textile', 'Jewelry', 'Weaponry', 'Ceramics', 'Document', 'Fossil', 'Other'];
$conditions      = ['Excellent', 'Good', 'Fair', 'Poor'];
$tier_colors     = ['Low' => '#7A7571', 'Medium' => '#3A6351', 'High' => '#1D4ED8', 'Exceptional' => '#9E2A2B', 'Unknown' => '#9CA3AF'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Manage Artifacts</title>
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Manage Artifacts</h1>
        <p style="color:var(--text-light);">Full CRUD — INSERT, UPDATE, DELETE with Oracle TO_DATE, TO_CHAR, CASE WHEN</p>
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
                        <?php echo $view_mode === 'add' ? 'Add New Artifact' : 'Edit Artifact'; ?>
                    </h3>
                    <p style="font-size:0.78rem; color:var(--text-light); margin-top:0.2rem;">
                        <span class="db-badge"><?php echo $view_mode === 'add' ? 'INSERT INTO artifacts ... TO_DATE(?, \'YYYY-MM-DD\')' : 'UPDATE artifacts SET ... WHERE artifact_id = ?'; ?></span>
                    </p>
                </div>
                <a href="manage_artifacts.php" class="btn btn-outline" style="padding:0.5rem 1rem;">← Back to List</a>
            </div>
            <div style="padding:2rem;">
                <form method="POST" action="manage_artifacts.php">
                    <input type="hidden" name="form_action" value="<?php echo $view_mode === 'edit' ? 'update' : 'insert'; ?>">
                    <?php if ($view_mode === 'edit'): ?>
                        <input type="hidden" name="artifact_id" value="<?php echo (int)$editing['ARTIFACT_ID']; ?>">
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                        <div class="form-group">
                            <label for="name">Artifact Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required
                                   value="<?php echo htmlspecialchars($editing['NAME'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">— Select Category —</option>
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
                                   placeholder="e.g. Egypt">
                        </div>
                        <div class="form-group">
                            <label for="acquisition_date">Acquisition Date</label>
                            <input type="date" id="acquisition_date" name="acquisition_date" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['ACQ_DATE_INPUT'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="condition_status">Condition</label>
                            <select id="condition_status" name="condition_status" class="form-control">
                                <?php foreach ($conditions as $cond): ?>
                                    <option value="<?php echo $cond; ?>"
                                        <?php if (($editing['CONDITION_STATUS'] ?? 'Good') === $cond) echo 'selected'; ?>>
                                        <?php echo $cond; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="estimated_value">Estimated Value ($)</label>
                            <input type="number" id="estimated_value" name="estimated_value" class="form-control"
                                   step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($editing['ESTIMATED_VALUE'] ?? ''); ?>"
                                   placeholder="e.g. 250000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" class="form-control"
                               value="<?php echo htmlspecialchars($editing['IMAGE_URL'] ?? ''); ?>"
                               placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control"
                                  rows="3" style="resize:vertical;"
                                  placeholder="Brief description..."><?php echo htmlspecialchars($editing['DESCRIPTION'] ?? ''); ?></textarea>
                    </div>
                    <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:1rem;">
                        <a href="manage_artifacts.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $view_mode === 'edit' ? 'Save Changes' : 'Add Artifact'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>

        <!-- ========== LIST VIEW ========== -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <div>
                <span class="db-badge">SELECT name, category, TO_CHAR(acq_date,'DD-MON-YYYY'), CASE WHEN value &lt; 50000 THEN 'Low' ... END AS value_tier FROM artifacts ORDER BY artifact_id DESC</span>
            </div>
            <a href="manage_artifacts.php?action=add" class="btn btn-primary" style="white-space:nowrap;">+ Add Artifact</a>
        </div>

        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($artifacts)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Country</th>
                            <th>Acquired</th>
                            <th>Condition</th>
                            <th>Est. Value</th>
                            <th>Tier</th>
                            <th colspan="2" style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artifacts as $a): ?>
                            <tr>
                                <td style="color:var(--text-light); font-size:0.85rem;"><?php echo (int)$a['ARTIFACT_ID']; ?></td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($a['NAME']); ?></td>
                                <td><?php echo htmlspecialchars($a['CATEGORY'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($a['ORIGIN_COUNTRY'] ?? '—'); ?></td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($a['ACQ_DATE_FMT'] ?? '—'); ?></td>
                                <td>
                                    <?php $cnd = $a['CONDITION_STATUS'] ?? ''; $cc = match($cnd) { 'Excellent' => 'badge-active', 'Good' => 'badge-upcoming', 'Fair' => 'badge-closed', default => 'badge-closed' }; ?>
                                    <span class="badge <?php echo $cc; ?>"><?php echo htmlspecialchars($cnd); ?></span>
                                </td>
                                <td><?php echo $a['ESTIMATED_VALUE'] !== null ? '$' . number_format((float)$a['ESTIMATED_VALUE']) : '—'; ?></td>
                                <td>
                                    <?php $tier = $a['VALUE_TIER'] ?? 'Unknown'; $tc = $tier_colors[$tier] ?? '#9CA3AF'; ?>
                                    <span style="font-size:0.8rem; font-weight:700; color:<?php echo $tc; ?>;"><?php echo $tier; ?></span>
                                </td>
                                <td>
                                    <a href="manage_artifacts.php?action=edit&id=<?php echo (int)$a['ARTIFACT_ID']; ?>"
                                       class="btn btn-outline" style="padding:0.25rem 0.75rem; font-size:0.8rem;">Edit</a>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete artifact: <?php echo htmlspecialchars(addslashes($a['NAME'])); ?>?');">
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="artifact_id" value="<?php echo (int)$a['ARTIFACT_ID']; ?>">
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
                    <p style="margin-bottom:1rem;">No artifacts found.</p>
                    <a href="manage_artifacts.php?action=add" class="btn btn-primary">Add First Artifact</a>
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
