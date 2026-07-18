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
//  Handle POST — UPDATE user status or role
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';

    // -- Toggle Active/Suspended --
    if ($form_action === 'toggle_status') {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        $new_status = sanitizeInput($_POST['new_status'] ?? '');

        if ($target_uid > 0 && $target_uid !== (int)$_SESSION['user_id']
            && in_array($new_status, ['Active', 'Suspended'], true)) {
            try {
                $upd = $db->prepare(
                    "UPDATE users SET status = :p_status WHERE user_id = :p_tuid"
                );
                $upd->bindValue(':p_status', $new_status);
                $upd->bindValue(':p_tuid',   $target_uid, PDO::PARAM_INT);
                $upd->execute();

                // Audit log
                $ip  = substr($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 0, 45);
                $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 255);
                $log = $db->prepare(
                    "INSERT INTO audit_logs (user_id, action_performed, table_affected, ip_address, user_agent)
                     VALUES (:log_uid, 'USER_STATUS_CHANGED', 'USERS', :log_ip, :log_ua)"
                );
                $log->bindValue(':log_uid', (int)$_SESSION['user_id'], PDO::PARAM_INT);
                $log->bindValue(':log_ip',  $ip);
                $log->bindValue(':log_ua',  $ua);
                $log->execute();

                $success = "User #$target_uid status set to $new_status.";
            } catch (PDOException $e) {
                $error = 'Status update failed: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid request or cannot modify your own account.';
        }
    }

    // -- Change Role --
    elseif ($form_action === 'change_role') {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        $new_role   = sanitizeInput($_POST['new_role'] ?? '');

        if ($target_uid > 0 && $target_uid !== (int)$_SESSION['user_id']
            && in_array($new_role, ['Admin', 'Visitor'], true)) {
            try {
                $upd = $db->prepare(
                    "UPDATE users
                     SET role_id = (SELECT role_id FROM roles WHERE role_name = :p_role)
                     WHERE user_id = :p_tuid"
                );
                $upd->bindValue(':p_role', $new_role);
                $upd->bindValue(':p_tuid', $target_uid, PDO::PARAM_INT);
                $upd->execute();
                $success = "User #$target_uid role changed to $new_role.";
            } catch (PDOException $e) {
                $error = 'Role update failed: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid request or cannot modify your own role.';
        }
    }

    // -- Delete User --
    elseif ($form_action === 'delete_user') {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        if ($target_uid > 0 && $target_uid !== (int)$_SESSION['user_id']) {
            try {
                $del = $db->prepare("DELETE FROM users WHERE user_id = :p_tuid");
                $del->bindValue(':p_tuid', $target_uid, PDO::PARAM_INT);
                $del->execute();
                $success = $del->rowCount() > 0 ? "User #$target_uid deleted." : 'User not found.';
            } catch (PDOException $e) {
                $error = 'Delete failed (user may have related data): ' . $e->getMessage();
            }
        } else {
            $error = 'Cannot delete your own account.';
        }
    }
}

// ============================================================
//  Fetch all users — JOIN roles + visitors, LEFT JOIN tickets
//  Demonstrates: multi-table JOIN, subquery, DECODE, NVL
// ============================================================
$users = [];
try {
    $stmt = $db->query(
        "SELECT u.user_id, u.username, u.email, u.status, u.created_at,
                r.role_name,
                NVL(v.country, '—')  AS country,
                NVL(v.phone,   '—')  AS phone,
                NVL(t.ticket_count, 0) AS ticket_count,
                NVL(t.total_spent,  0) AS total_spent,
                DECODE(u.status, 'Active', 'Active', 'Suspended', 'Suspended', 'Unknown') AS status_label
         FROM users u
         JOIN roles r         ON u.role_id   = r.role_id
         LEFT JOIN visitors v ON u.user_id   = v.user_id
         LEFT JOIN (
             SELECT user_id,
                    COUNT(*)           AS ticket_count,
                    SUM(total_amount)  AS total_spent
             FROM   tickets
             WHERE  status = 'Confirmed'
             GROUP  BY user_id
         ) t ON u.user_id = t.user_id
         ORDER BY u.created_at DESC"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Summary stats
$total_users    = count($users);
$active_users   = count(array_filter($users, fn($u) => $u['STATUS'] === 'Active'));
$admin_count    = count(array_filter($users, fn($u) => $u['ROLE_NAME'] === 'Admin'));
$suspended      = count(array_filter($users, fn($u) => $u['STATUS'] === 'Suspended'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Manage Users</title>
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
        <h1 style="font-size:2.4rem; margin-bottom:0.5rem;">Manage Users</h1>
        
    </header>

    <section class="section" style="padding-top:2rem; max-width:1300px;">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom:1.5rem;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" style="margin-bottom:1.5rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- User Summary Cards -->
        <div class="stat-grid" style="margin-bottom:3rem;">
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_users; ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $active_users; ?></span>
                <span class="stat-label">Active</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $suspended; ?></span>
                <span class="stat-label">Suspended</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $admin_count; ?></span>
                <span class="stat-label">Admins</span>
            </div>
        </div>

        <!-- User List -->
        <div style="margin-bottom:1rem;">
            <span class="db-badge">SELECT u.user_id, u.username, r.role_name, NVL(v.country,'—'), NVL(t.ticket_count,0), DECODE(u.status,'Active','Active','Suspended','Suspended','Unknown') FROM users u JOIN roles r ... LEFT JOIN visitors v ... LEFT JOIN (SELECT user_id, COUNT(*), SUM(total_amount) FROM tickets GROUP BY ...) t ... ORDER BY u.created_at DESC</span>
        </div>

        <div class="report-card" style="margin-bottom:4rem;">
            <?php if (!empty($users)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Country</th>
                            <th>Tickets</th>
                            <th>Spent</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $is_self     = (int)$u['USER_ID'] === (int)$_SESSION['user_id'];
                            $is_active   = $u['STATUS'] === 'Active';
                            $is_admin    = $u['ROLE_NAME'] === 'Admin';
                        ?>
                            <tr style="<?php echo $is_self ? 'background: #FDF3F0;' : ''; ?>">
                                <td style="color:var(--text-light); font-size:0.85rem;"><?php echo (int)$u['USER_ID']; ?></td>
                                <td style="font-weight:600;">
                                    <?php echo htmlspecialchars($u['USERNAME']); ?>
                                    <?php if ($is_self): ?>
                                        <span style="font-size:0.7rem; color:var(--secondary-color); font-weight:400;"> (you)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.85rem;"><?php echo htmlspecialchars($u['EMAIL']); ?></td>
                                <td>
                                    <span class="badge <?php echo $is_admin ? 'badge-upcoming' : 'badge-active'; ?>">
                                        <?php echo htmlspecialchars($u['ROLE_NAME']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($u['COUNTRY']); ?></td>
                                <td><?php echo (int)$u['TICKET_COUNT']; ?></td>
                                <td>$<?php echo number_format((float)$u['TOTAL_SPENT'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $is_active ? 'badge-active' : 'badge-closed'; ?>">
                                        <?php echo htmlspecialchars($u['STATUS_LABEL']); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.82rem;"><?php echo htmlspecialchars(substr($u['CREATED_AT'] ?? '', 0, 10)); ?></td>
                                <td>
                                    <?php if ($is_self): ?>
                                        <span style="color:var(--text-light); font-size:0.82rem;">—</span>
                                    <?php else: ?>
                                        <div style="display:flex; gap:0.4rem; flex-wrap:wrap; justify-content:center;">

                                            <!-- Toggle Status -->
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="form_action" value="toggle_status">
                                                <input type="hidden" name="target_user_id" value="<?php echo (int)$u['USER_ID']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $is_active ? 'Suspended' : 'Active'; ?>">
                                                <button type="submit" class="btn btn-outline"
                                                        style="padding:0.2rem 0.6rem; font-size:0.78rem; <?php echo $is_active ? 'color:#D97706; border-color:#FCD34D;' : 'color:#16A34A; border-color:#86EFAC;'; ?>"
                                                        title="<?php echo $is_active ? 'Suspend' : 'Activate'; ?> user">
                                                    <?php echo $is_active ? 'Suspend' : 'Activate'; ?>
                                                </button>
                                            </form>

                                            <!-- Change Role -->
                                            <form method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Change role of <?php echo htmlspecialchars(addslashes($u['USERNAME'])); ?> to <?php echo $is_admin ? 'Visitor' : 'Admin'; ?>?');">
                                                <input type="hidden" name="form_action" value="change_role">
                                                <input type="hidden" name="target_user_id" value="<?php echo (int)$u['USER_ID']; ?>">
                                                <input type="hidden" name="new_role" value="<?php echo $is_admin ? 'Visitor' : 'Admin'; ?>">
                                                <button type="submit" class="btn btn-outline"
                                                        style="padding:0.2rem 0.6rem; font-size:0.78rem; color:#1D4ED8; border-color:#BFDBFE;"
                                                        title="Make <?php echo $is_admin ? 'Visitor' : 'Admin'; ?>">
                                                    → <?php echo $is_admin ? 'Visitor' : 'Admin'; ?>
                                                </button>
                                            </form>

                                            <!-- Delete -->
                                            <form method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Permanently delete user: <?php echo htmlspecialchars(addslashes($u['USERNAME'])); ?>? This cannot be undone.');">
                                                <input type="hidden" name="form_action" value="delete_user">
                                                <input type="hidden" name="target_user_id" value="<?php echo (int)$u['USER_ID']; ?>">
                                                <button type="submit" class="btn btn-outline"
                                                        style="padding:0.2rem 0.6rem; font-size:0.78rem; color:#9E2A2B; border-color:#E5B3B3;">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding:2rem; color:var(--text-light);">No users found.</p>
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
