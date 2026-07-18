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

    // Common field extraction
    $title        = sanitizeInput($_POST['title']        ?? '');
    $wing         = sanitizeInput($_POST['wing']         ?? '');
    $description  = sanitizeInput($_POST['description']  ?? '');
    $start_date   = $_POST['start_date']                 ?? '';
    $end_date     = $_POST['end_date']                   ?? '';
    $status       = sanitizeInput($_POST['status']       ?? 'Upcoming');
    $ticket_price = is_numeric($_POST['ticket_price'] ?? '') ? (float)$_POST['ticket_price'] : null;
    $capacity     = is_numeric($_POST['capacity']     ?? '') ? (int)$_POST['capacity'] : null;
    $image_url    = sanitizeInput($_POST['image_url']    ?? '');

    // -- INSERT --
    if ($form_action === 'insert') {
        if (empty($title)) {
            $error = 'Exhibition title is required.';
        } elseif (!empty($start_date) && !empty($end_date) && $end_date < $start_date) {
            $error = 'End date cannot be before start date.';
        } else {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO exhibitions
                         (title, wing, description, start_date, end_date,
                          status, ticket_price, capacity, image_url)
                     VALUES
                         (:p_title, :p_wing, :p_desc,
                          TO_DATE(NULLIF(:p_sd,''),'YYYY-MM-DD'),
                          TO_DATE(NULLIF(:p_ed,''),'YYYY-MM-DD'),
                          :p_status, :p_price, :p_cap, :p_img)"
                );
                $stmt->bindValue(':p_title',  $title);
                $stmt->bindValue(':p_wing',   $wing       ?: null);
                $stmt->bindValue(':p_desc',   $description ?: null);
                $stmt->bindValue(':p_sd',     $start_date  ?: '');
                $stmt->bindValue(':p_ed',     $end_date    ?: '');
                $stmt->bindValue(':p_status', $status);
                $stmt->bindValue(':p_price',  $ticket_price);
                $stmt->bindValue(':p_cap',    $capacity);
                $stmt->bindValue(':p_img',    $image_url   ?: null);
                $stmt->execute();
                $success = "Exhibition \"$title\" added successfully.";
            } catch (PDOException $e) {
                $error = 'Insert failed: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }

    // -- UPDATE --
    elseif ($form_action === 'update') {
        $exhibition_id = (int)($_POST['exhibition_id'] ?? 0);
        if (empty($title) || $exhibition_id <= 0) {
            $error = 'Invalid data.';
        } elseif (!empty($start_date) && !empty($end_date) && $end_date < $start_date) {
            $error = 'End date cannot be before start date.';
        } else {
            try {
                $stmt = $db->prepare(
                    "UPDATE exhibitions SET
                         title        = :p_title,
                         wing         = :p_wing,
                         description  = :p_desc,
                         start_date   = TO_DATE(NULLIF(:p_sd,''),'YYYY-MM-DD'),
                         end_date     = TO_DATE(NULLIF(:p_ed,''),'YYYY-MM-DD'),
                         status       = :p_status,
                         ticket_price = :p_price,
                         capacity     = :p_cap,
                         image_url    = :p_img
                     WHERE exhibition_id = :p_eid"
                );
                $stmt->bindValue(':p_title',  $title);
                $stmt->bindValue(':p_wing',   $wing         ?: null);
                $stmt->bindValue(':p_desc',   $description  ?: null);
                $stmt->bindValue(':p_sd',     $start_date   ?: '');
                $stmt->bindValue(':p_ed',     $end_date     ?: '');
                $stmt->bindValue(':p_status', $status);
                $stmt->bindValue(':p_price',  $ticket_price);
                $stmt->bindValue(':p_cap',    $capacity);
                $stmt->bindValue(':p_img',    $image_url    ?: null);
                $stmt->bindValue(':p_eid',    $exhibition_id, PDO::PARAM_INT);
                $stmt->execute();
                $success = "Exhibition \"$title\" updated successfully.";
                header('Location: manage_exhibitions.php?success=' . urlencode($success));
                exit();
            } catch (PDOException $e) {
                $error = 'Update failed: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }

    // -- DELETE --
    elseif ($form_action === 'delete') {
        $exhibition_id = (int)($_POST['exhibition_id'] ?? 0);
        if ($exhibition_id > 0) {
            try {
                // Check if tickets exist (FK constraint)
                $chk = $db->prepare(
                    "SELECT COUNT(*) AS cnt FROM tickets WHERE exhibition_id = :p_eid AND status = 'Confirmed'"
                );
                $chk->bindValue(':p_eid', $exhibition_id, PDO::PARAM_INT);
                $chk->execute();
                $cnt = (int)($chk->fetch(PDO::FETCH_ASSOC)['CNT'] ?? 0);

                if ($cnt > 0) {
                    $error = "Cannot delete: this exhibition has $cnt confirmed ticket(s). Cancel all tickets first.";
                } else {
                    $del = $db->prepare("DELETE FROM exhibitions WHERE exhibition_id = :p_eid");
                    $del->bindValue(':p_eid', $exhibition_id, PDO::PARAM_INT);
                    $del->execute();
                    $success = $del->rowCount() > 0 ? 'Exhibition deleted successfully.' : 'Exhibition not found.';
                    header('Location: manage_exhibitions.php?success=' . urlencode($success));
                    exit();
                }
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
//  Fetch exhibition for editing
// ============================================================
$editing = null;
$edit_id = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) ? (int)$_GET['id'] : 0;
if ($edit_id > 0) {
    try {
        $stmt = $db->prepare(
            "SELECT e.*,
                    TO_CHAR(e.start_date, 'YYYY-MM-DD') AS sd_input,
                    TO_CHAR(e.end_date,   'YYYY-MM-DD') AS ed_input
             FROM exhibitions e
             WHERE e.exhibition_id = :p_eid"
        );
        $stmt->bindValue(':p_eid', $edit_id, PDO::PARAM_INT);
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
//  Fetch all exhibitions — JOIN with ticket count (subquery)
// ============================================================
$exhibitions = [];
try {
    $stmt = $db->query(
        "SELECT e.exhibition_id, e.title, e.wing, e.status,
                e.ticket_price, e.capacity,
                TO_CHAR(e.start_date, 'DD-MON-YYYY') AS start_fmt,
                TO_CHAR(e.end_date,   'DD-MON-YYYY') AS end_fmt,
                NVL(t.tickets_sold, 0)               AS tickets_sold,
                NVL(t.revenue, 0)                    AS revenue
         FROM exhibitions e
         LEFT JOIN (
             SELECT exhibition_id,
                    SUM(quantity)     AS tickets_sold,
                    SUM(total_amount) AS revenue
             FROM   tickets
             WHERE  status = 'Confirmed'
             GROUP  BY exhibition_id
         ) t ON e.exhibition_id = t.exhibition_id
         ORDER BY e.exhibition_id DESC"
    );
    $exhibitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$wings    = ['East Wing', 'West Wing', 'North Wing', 'South Wing', 'Main Hall', 'Science Hall', 'Historical Wing', 'Modern Wing'];
$statuses = ['Active', 'Upcoming', 'Closed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Manage Exhibitions</title>
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Manage Exhibitions</h1>
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
                        <?php echo $view_mode === 'add' ? 'Add New Exhibition' : 'Edit Exhibition'; ?>
                    </h3>
                    <p style="font-size:0.78rem; color:var(--text-light); margin-top:0.2rem;">
                    </p>
                </div>
                <a href="manage_exhibitions.php" class="btn btn-outline" style="padding:0.5rem 1rem;">← Back to List</a>
            </div>
            <div style="padding:2rem;">
                <form method="POST" action="manage_exhibitions.php">
                    <input type="hidden" name="form_action" value="<?php echo $view_mode === 'edit' ? 'update' : 'insert'; ?>">
                    <?php if ($view_mode === 'edit'): ?>
                        <input type="hidden" name="exhibition_id" value="<?php echo (int)$editing['EXHIBITION_ID']; ?>">
                    <?php endif; ?>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                        <div class="form-group">
                            <label for="title">Exhibition Title *</label>
                            <input type="text" id="title" name="title" class="form-control" required
                                   value="<?php echo htmlspecialchars($editing['TITLE'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="wing">Wing / Location</label>
                            <select id="wing" name="wing" class="form-control">
                                <option value="">— Select Wing —</option>
                                <?php foreach ($wings as $w): ?>
                                    <option value="<?php echo $w; ?>"
                                        <?php if (($editing['WING'] ?? '') === $w) echo 'selected'; ?>>
                                        <?php echo $w; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['SD_INPUT'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['ED_INPUT'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <?php foreach ($statuses as $st): ?>
                                    <option value="<?php echo $st; ?>"
                                        <?php if (($editing['STATUS'] ?? 'Upcoming') === $st) echo 'selected'; ?>>
                                        <?php echo $st; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticket_price">Ticket Price ($)</label>
                            <input type="number" id="ticket_price" name="ticket_price" class="form-control"
                                   step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($editing['TICKET_PRICE'] ?? ''); ?>"
                                   placeholder="e.g. 15.00">
                        </div>
                        <div class="form-group">
                            <label for="capacity">Capacity (visitors)</label>
                            <input type="number" id="capacity" name="capacity" class="form-control" min="1"
                                   value="<?php echo htmlspecialchars($editing['CAPACITY'] ?? ''); ?>"
                                   placeholder="e.g. 500">
                        </div>
                        <div class="form-group">
                            <label for="image_url">Image URL</label>
                            <input type="url" id="image_url" name="image_url" class="form-control"
                                   value="<?php echo htmlspecialchars($editing['IMAGE_URL'] ?? ''); ?>"
                                   placeholder="https://...">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control"
                                  rows="3" style="resize:vertical;"><?php echo htmlspecialchars($editing['DESCRIPTION'] ?? ''); ?></textarea>
                    </div>
                    <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:1rem;">
                        <a href="manage_exhibitions.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $view_mode === 'edit' ? 'Save Changes' : 'Add Exhibition'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>

        <!-- ========== LIST VIEW ========== -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <a href="manage_exhibitions.php?action=add" class="btn btn-primary" style="white-space:nowrap;">+ Add Exhibition</a>
        </div>

        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($exhibitions)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Wing</th>
                            <th>Status</th>
                            <th>Dates</th>
                            <th>Price</th>
                            <th>Capacity</th>
                            <th>Tickets</th>
                            <th>Revenue</th>
                            <th colspan="2" style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exhibitions as $ex): ?>
                            <tr>
                                <td style="color:var(--text-light); font-size:0.85rem;"><?php echo (int)$ex['EXHIBITION_ID']; ?></td>
                                <td style="font-weight:600; max-width:160px;"><?php echo htmlspecialchars($ex['TITLE']); ?></td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($ex['WING'] ?? '—'); ?></td>
                                <td>
                                    <?php $s = $ex['STATUS'] ?? ''; $bc = match(strtolower($s)) { 'active' => 'badge-active', 'upcoming' => 'badge-upcoming', default => 'badge-closed' }; ?>
                                    <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($s); ?></span>
                                </td>
                                <td style="font-size:0.82rem; white-space:nowrap;">
                                    <?php echo htmlspecialchars($ex['START_FMT'] ?? '—'); ?><br>
                                    <span style="color:var(--text-light);">→ <?php echo htmlspecialchars($ex['END_FMT'] ?? '—'); ?></span>
                                </td>
                                <td>$<?php echo number_format((float)($ex['TICKET_PRICE'] ?? 0), 2); ?></td>
                                <td><?php echo number_format((int)($ex['CAPACITY'] ?? 0)); ?></td>
                                <td><?php echo (int)$ex['TICKETS_SOLD']; ?></td>
                                <td>$<?php echo number_format((float)$ex['REVENUE'], 2); ?></td>
                                <td>
                                    <a href="manage_exhibitions.php?action=edit&id=<?php echo (int)$ex['EXHIBITION_ID']; ?>"
                                       class="btn btn-outline" style="padding:0.25rem 0.75rem; font-size:0.8rem;">Edit</a>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete exhibition: <?php echo htmlspecialchars(addslashes($ex['TITLE'])); ?>?');">
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="exhibition_id" value="<?php echo (int)$ex['EXHIBITION_ID']; ?>">
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
                    <p style="margin-bottom:1rem;">No exhibitions found.</p>
                    <a href="manage_exhibitions.php?action=add" class="btn btn-primary">Add First Exhibition</a>
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
