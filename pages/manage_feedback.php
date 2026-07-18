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
//  Handle POST — Update feedback status
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $new_status  = sanitizeInput($_POST['new_status'] ?? '');

    if ($form_action === 'update_status' && $feedback_id > 0
        && in_array($new_status, ['Pending', 'Reviewed', 'Closed'], true)) {
        try {
            $upd = $db->prepare(
                "UPDATE feedback SET status = :p_status WHERE feedback_id = :p_fid"
            );
            $upd->bindValue(':p_status', $new_status);
            $upd->bindValue(':p_fid',    $feedback_id, PDO::PARAM_INT);
            $upd->execute();
            $success = "Feedback #$feedback_id status updated to $new_status.";
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    } elseif ($form_action === 'delete' && $feedback_id > 0) {
        try {
            $del = $db->prepare("DELETE FROM feedback WHERE feedback_id = :p_fid");
            $del->bindValue(':p_fid', $feedback_id, PDO::PARAM_INT);
            $del->execute();
            $success = "Feedback #$feedback_id deleted.";
        } catch (PDOException $e) {
            $error = 'Delete failed: ' . $e->getMessage();
        }
    }
}

// ============================================================
//  Exhibition Ratings Summary
//  Demonstrates: GROUP BY, AVG, ROUND, COUNT, HAVING
// ============================================================
$rating_summary = [];
try {
    $stmt = $db->query(
        "SELECT e.exhibition_id, e.title, e.wing,
                COUNT(f.feedback_id)          AS total_reviews,
                ROUND(AVG(f.rating), 2)       AS avg_rating,
                MIN(f.rating)                 AS min_rating,
                MAX(f.rating)                 AS max_rating,
                SUM(CASE WHEN f.rating = 5 THEN 1 ELSE 0 END) AS five_star
         FROM exhibitions e
         JOIN feedback f ON e.exhibition_id = f.exhibition_id
         GROUP BY e.exhibition_id, e.title, e.wing
         HAVING COUNT(f.feedback_id) >= 1
         ORDER BY avg_rating DESC"
    );
    $rating_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  All Feedback — JOIN users + exhibitions + BETWEEN filter
// ============================================================
$filter_status = $_GET['status'] ?? '';
$all_feedback  = [];
try {
    $sql = "SELECT f.feedback_id, f.subject, f.message, f.rating, f.status,
                   f.created_at, e.title AS exhibition_title,
                   NVL(u.username, 'Guest') AS username
            FROM feedback f
            LEFT JOIN exhibitions e ON f.exhibition_id = e.exhibition_id
            LEFT JOIN users u       ON f.user_id       = u.user_id";

    if (!empty($filter_status) && in_array($filter_status, ['Pending', 'Reviewed', 'Closed'], true)) {
        $sql   .= " WHERE f.status = :p_fstatus";
        $stmt   = $db->prepare($sql . " ORDER BY f.created_at DESC");
        $stmt->bindValue(':p_fstatus', $filter_status);
    } else {
        $stmt = $db->prepare($sql . " ORDER BY f.created_at DESC");
    }
    $stmt->execute();
    $all_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Counts
$pending_count  = count(array_filter($all_feedback, fn($f) => $f['STATUS'] === 'Pending'));
$reviewed_count = count(array_filter($all_feedback, fn($f) => $f['STATUS'] === 'Reviewed'));
$closed_count   = count(array_filter($all_feedback, fn($f) => $f['STATUS'] === 'Closed'));
$total_count    = count($all_feedback);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Manage Feedback</title>
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Manage Feedback</h1>
     
    </header>

    <section class="section" style="padding-top:2rem; max-width:1200px;">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom:1.5rem;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" style="margin-bottom:1.5rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <div class="stat-grid" style="margin-bottom:3rem;">
            <div class="stat-card"><span class="stat-number"><?php echo $total_count; ?></span><span class="stat-label">Total Reviews</span></div>
            <div class="stat-card"><span class="stat-number"><?php echo $pending_count; ?></span><span class="stat-label">Pending</span></div>
            <div class="stat-card"><span class="stat-number"><?php echo $reviewed_count; ?></span><span class="stat-label">Reviewed</span></div>
            <div class="stat-card"><span class="stat-number"><?php echo $closed_count; ?></span><span class="stat-label">Closed</span></div>
        </div>

        <!-- Exhibition Ratings Summary — GROUP BY + AVG + HAVING -->
        <?php if (!empty($rating_summary)): ?>
        
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">Ratings by Exhibition</h2>
        <div class="report-card" style="margin-bottom:3rem;">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Exhibition</th>
                        <th>Wing</th>
                        <th>Reviews</th>
                        <th>Avg Rating</th>
                        <th>Min</th>
                        <th>Max</th>
                        <th>⭐ 5-Star</th>
                        <th>Visual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rating_summary as $r): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($r['TITLE']); ?></td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars($r['WING'] ?? '—'); ?></td>
                            <td><?php echo (int)$r['TOTAL_REVIEWS']; ?></td>
                            <td style="font-weight:700; color:var(--secondary-color);"><?php echo number_format((float)$r['AVG_RATING'], 2); ?></td>
                            <td><?php echo (int)$r['MIN_RATING']; ?></td>
                            <td><?php echo (int)$r['MAX_RATING']; ?></td>
                            <td><?php echo (int)$r['FIVE_STAR']; ?></td>
                            <td>
                                <?php
                                    $pct = ((float)$r['AVG_RATING'] / 5) * 100;
                                    $clr = $pct >= 80 ? '#16A34A' : ($pct >= 50 ? '#D97706' : '#9E2A2B');
                                ?>
                                <div style="background:#F0EBE3; border-radius:4px; height:8px; width:120px; overflow:hidden;">
                                    <div style="height:100%; width:<?php echo round($pct); ?>%; background:<?php echo $clr; ?>; border-radius:4px;"></div>
                                </div>
                                <span style="font-size:0.75rem; color:var(--text-light);"><?php echo round($pct); ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- All Feedback List -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.75rem;">
            
            <div style="display:flex; gap:0.5rem;">
                <a href="manage_feedback.php" class="btn btn-outline" style="padding:0.35rem 0.8rem; font-size:0.82rem; <?php echo empty($filter_status) ? 'background:var(--secondary-color);color:#fff;' : ''; ?>">All</a>
                <a href="manage_feedback.php?status=Pending" class="btn btn-outline" style="padding:0.35rem 0.8rem; font-size:0.82rem; <?php echo $filter_status === 'Pending' ? 'background:var(--secondary-color);color:#fff;' : ''; ?>">Pending</a>
                <a href="manage_feedback.php?status=Reviewed" class="btn btn-outline" style="padding:0.35rem 0.8rem; font-size:0.82rem; <?php echo $filter_status === 'Reviewed' ? 'background:var(--secondary-color);color:#fff;' : ''; ?>">Reviewed</a>
                <a href="manage_feedback.php?status=Closed" class="btn btn-outline" style="padding:0.35rem 0.8rem; font-size:0.82rem; <?php echo $filter_status === 'Closed' ? 'background:var(--secondary-color);color:#fff;' : ''; ?>">Closed</a>
            </div>
        </div>

        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($all_feedback)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Exhibition</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_feedback as $f): ?>
                            <tr>
                                <td style="color:var(--text-light); font-size:0.82rem;"><?php echo (int)$f['FEEDBACK_ID']; ?></td>
                                <td><?php echo htmlspecialchars($f['USERNAME']); ?></td>
                                <td style="max-width:120px; font-size:0.85rem;"><?php echo htmlspecialchars($f['EXHIBITION_TITLE'] ?? '—'); ?></td>
                                <td style="font-weight:600; max-width:120px;"><?php echo htmlspecialchars($f['SUBJECT'] ?? '—'); ?></td>
                                <td style="max-width:200px; font-size:0.85rem; color:var(--text-light);">
                                    <?php echo htmlspecialchars(mb_substr($f['MESSAGE'] ?? '', 0, 70)) . (mb_strlen($f['MESSAGE'] ?? '') > 70 ? '…' : ''); ?>
                                </td>
                                <td style="color:#D97706; letter-spacing:1px; font-size:1rem;">
                                    <?php echo str_repeat('★', (int)($f['RATING'] ?? 0)) . str_repeat('☆', 5 - (int)($f['RATING'] ?? 0)); ?>
                                </td>
                                <td>
                                    <?php $s = $f['STATUS'] ?? ''; $bc = match($s) { 'Reviewed' => 'badge-active', 'Closed' => 'badge-closed', default => 'badge-upcoming' }; ?>
                                    <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($s); ?></span>
                                </td>
                                <td style="font-size:0.82rem;"><?php echo htmlspecialchars(substr($f['CREATED_AT'] ?? '', 0, 10)); ?></td>
                                <td>
                                    <div style="display:flex; gap:0.35rem; justify-content:center; flex-wrap:wrap;">
                                        <?php if ($f['STATUS'] !== 'Reviewed'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="form_action"  value="update_status">
                                            <input type="hidden" name="feedback_id"  value="<?php echo (int)$f['FEEDBACK_ID']; ?>">
                                            <input type="hidden" name="new_status"   value="Reviewed">
                                            <button class="btn btn-outline" style="padding:0.2rem 0.55rem; font-size:0.75rem; color:#16A34A; border-color:#86EFAC;">✓ Review</button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($f['STATUS'] !== 'Closed'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="form_action"  value="update_status">
                                            <input type="hidden" name="feedback_id"  value="<?php echo (int)$f['FEEDBACK_ID']; ?>">
                                            <input type="hidden" name="new_status"   value="Closed">
                                            <button class="btn btn-outline" style="padding:0.2rem 0.55rem; font-size:0.75rem; color:#6B7280; border-color:#D1D5DB;">Close</button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete feedback #<?php echo (int)$f['FEEDBACK_ID']; ?>?');">
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="feedback_id" value="<?php echo (int)$f['FEEDBACK_ID']; ?>">
                                            <button class="btn btn-outline" style="padding:0.2rem 0.55rem; font-size:0.75rem; color:#9E2A2B; border-color:#E5B3B3;">Del</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding:2rem; text-align:center; color:var(--text-light);">No feedback found<?php echo $filter_status ? " with status \"$filter_status\"" : ''; ?>.</p>
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
