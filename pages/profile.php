<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Profile requires login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db      = Database::getConnection();
$user_id = (int)$_SESSION['user_id'];

$success = '';
$error   = '';

// ============================================================
//  Fetch User + Visitor Data — JOIN query
// ============================================================
function fetchUserData(PDO $db, int $uid): array {
    $stmt = $db->prepare(
        "SELECT u.user_id, u.username, u.email, u.status, u.created_at,
                r.role_name, v.phone, v.country
         FROM users u
         JOIN roles r         ON u.role_id  = r.role_id
         LEFT JOIN visitors v ON u.user_id  = v.user_id
         WHERE u.user_id = :p_user_id"
    );
    $stmt->bindValue(':p_user_id', $uid, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$user = fetchUserData($db, $user_id);

// ============================================================
//  Handle POST Actions
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- Action 1: Update Contact Details --
    if ($action === 'update_contact') {
        $phone   = sanitizeInput($_POST['phone']   ?? '');
        $country = sanitizeInput($_POST['country'] ?? '');

        if (empty($country)) {
            $error = 'Country cannot be empty.';
        } else {
            try {
                // Check if visitor row exists for this user
                $chk = $db->prepare("SELECT visitor_id FROM visitors WHERE user_id = :p_user_id");
                $chk->bindValue(':p_user_id', $user_id, PDO::PARAM_INT);
                $chk->execute();

                if ($chk->fetch(PDO::FETCH_ASSOC)) {
                    $upd = $db->prepare(
                        "UPDATE visitors SET phone = :phone, country = :country WHERE user_id = :p_user_id"
                    );
                } else {
                    $upd = $db->prepare(
                        "INSERT INTO visitors (user_id, phone, country) VALUES (:p_user_id, :phone, :country)"
                    );
                }
                $upd->bindValue(':phone',      $phone);
                $upd->bindValue(':country',    $country);
                $upd->bindValue(':p_user_id',  $user_id, PDO::PARAM_INT);
                $upd->execute();

                // Audit log
                $ip = substr($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 0, 45);
                $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 255);
                $log = $db->prepare(
                    "INSERT INTO audit_logs (user_id, action_performed, table_affected, ip_address, user_agent)
                     VALUES (:log_uid, 'CONTACT_UPDATED', 'VISITORS', :log_ip, :log_ua)"
                );
                $log->bindValue(':log_uid', $user_id, PDO::PARAM_INT);
                $log->bindValue(':log_ip',  $ip);
                $log->bindValue(':log_ua',  $ua);
                $log->execute();

                $success = 'Contact details updated successfully.';
                $user    = fetchUserData($db, $user_id);
            } catch (PDOException $e) {
                $error = 'Update failed. Please try again.';
                error_log('Contact update error: ' . $e->getMessage());
            }
        }
    }

    // -- Action 2: Change Password --
    elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password']     ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new_pass) || empty($confirm)) {
            $error = 'All password fields are required.';
        } elseif ($new_pass !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            try {
                $chk = $db->prepare("SELECT password FROM users WHERE user_id = :p_user_id");
                $chk->bindValue(':p_user_id', $user_id, PDO::PARAM_INT);
                $chk->execute();
                $row = $chk->fetch(PDO::FETCH_ASSOC);

                if (!$row || !password_verify($current, $row['PASSWORD'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                    $upd = $db->prepare(
                        "UPDATE users SET password = :pwd WHERE user_id = :p_user_id"
                    );
                    $upd->bindValue(':pwd',       $hashed);
                    $upd->bindValue(':p_user_id', $user_id, PDO::PARAM_INT);
                    $upd->execute();

                    // Audit log
                    $ip = substr($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 0, 45);
                    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 255);
                    $log = $db->prepare(
                        "INSERT INTO audit_logs (user_id, action_performed, table_affected, ip_address, user_agent)
                         VALUES (:log_uid, 'PASSWORD_CHANGED', 'USERS', :log_ip, :log_ua)"
                    );
                    $log->bindValue(':log_uid', $user_id, PDO::PARAM_INT);
                    $log->bindValue(':log_ip',  $ip);
                    $log->bindValue(':log_ua',  $ua);
                    $log->execute();

                    $success = 'Password changed successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Password change failed. Please try again.';
                error_log('Password change error: ' . $e->getMessage());
            }
        }
    }

    // -- Action 3: Cancel Ticket --
    elseif ($action === 'cancel_ticket') {
        $ticket_id = isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id'])
                     ? (int)$_POST['ticket_id'] : 0;

        if ($ticket_id > 0) {
            try {
                // Only cancel if it belongs to this user and is Confirmed
                $upd = $db->prepare(
                    "UPDATE tickets SET status = 'Cancelled'
                     WHERE ticket_id = :p_ticket_id AND user_id = :p_user_id AND status = 'Confirmed'"
                );
                $upd->bindValue(':p_ticket_id', $ticket_id, PDO::PARAM_INT);
                $upd->bindValue(':p_user_id',   $user_id,   PDO::PARAM_INT);
                $upd->execute();

                $success = $upd->rowCount() > 0
                    ? 'Ticket #' . $ticket_id . ' has been cancelled.'
                    : 'Ticket not found or already cancelled.';
            } catch (PDOException $e) {
                $error = 'Cancellation failed. Please try again.';
                error_log('Ticket cancel error: ' . $e->getMessage());
            }
        }
    }
}

// ============================================================
//  Fetch Booked Tickets — tickets JOIN exhibitions
// ============================================================
$my_tickets = [];
try {
    $stmt = $db->prepare(
        "SELECT t.ticket_id, t.ticket_type, t.quantity, t.unit_price,
                t.total_amount, t.status, t.booked_at,
                e.title AS exhibition_title, e.wing,
                e.start_date, e.end_date, e.status AS ex_status
         FROM tickets t
         JOIN exhibitions e ON t.exhibition_id = e.exhibition_id
         WHERE t.user_id = :p_user_id
         ORDER BY t.booked_at DESC"
    );
    $stmt->bindValue(':p_user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $my_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  Fetch Activity Log — audit_logs WHERE user_id
// ============================================================
$activity_log = [];
try {
    $stmt = $db->prepare(
        "SELECT * FROM (
             SELECT action_performed, ip_address, log_timestamp
             FROM audit_logs
             WHERE user_id = :p_user_id
             ORDER BY log_timestamp DESC
         ) WHERE ROWNUM <= 10"
    );
    $stmt->bindValue(':p_user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $activity_log = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Your MuseoX visitor profile.">
    <title>MuseoX | My Profile</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <li><a href="dashboard.php">Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="profile.php" style="color: var(--secondary-color); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" style="color: var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <h1 style="font-size: 2.8rem; margin-bottom: 1rem;">My Profile</h1>
        <p style="color: var(--text-light); max-width: 600px; margin: 0 auto;">
            Manage your account, update contact details, and view your ticket bookings.
        </p>
    </header>

    <section class="section" style="padding-top: 3rem; max-width: 960px;">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ======================================================
             BLOCK 1: Account Information — SELECT + JOIN
             ====================================================== -->
        <div class="report-card" style="margin-bottom: 3rem;">
            <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border);">
                <h3 style="font-size: 1.3rem; font-family: var(--font-heading);">Account Information</h3>
                <p style="font-size: 0.78rem; color: var(--text-light); margin-top: 0.25rem;">
                </p>
            </div>
            <div style="padding: 1.5rem 2rem;">
                <div class="profile-row">
                    <span class="profile-label">Username</span>
                    <span class="profile-value"><?php echo htmlspecialchars($user['USERNAME'] ?? '—'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Email</span>
                    <span class="profile-value"><?php echo htmlspecialchars($user['EMAIL'] ?? '—'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Role</span>
                    <span class="profile-value">
                        <?php $role = $user['ROLE_NAME'] ?? 'Visitor'; ?>
                        <span class="badge <?php echo $role === 'Admin' ? 'badge-upcoming' : 'badge-active'; ?>">
                            <?php echo htmlspecialchars($role); ?>
                        </span>
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Account Status</span>
                    <span class="profile-value">
                        <?php $st = $user['STATUS'] ?? 'Active'; ?>
                        <span class="badge <?php echo $st === 'Active' ? 'badge-active' : 'badge-closed'; ?>">
                            <?php echo htmlspecialchars($st); ?>
                        </span>
                    </span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Country</span>
                    <span class="profile-value"><?php echo htmlspecialchars($user['COUNTRY'] ?? '—'); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Phone</span>
                    <span class="profile-value"><?php echo htmlspecialchars($user['PHONE'] ?? '—'); ?></span>
                </div>
                <div class="profile-row" style="border-bottom: none;">
                    <span class="profile-label">Member Since</span>
                    <span class="profile-value"><?php echo htmlspecialchars(substr($user['CREATED_AT'] ?? '', 0, 10)); ?></span>
                </div>
            </div>
        </div>

        <!-- ======================================================
             BLOCK 2: Update Contact Details — UPDATE visitors
             ====================================================== -->
        <div class="report-card" style="margin-bottom: 3rem;">
            <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border);">
                <h3 style="font-size: 1.3rem; font-family: var(--font-heading);">Update Contact Details</h3>
                <p style="font-size: 0.78rem; color: var(--text-light); margin-top: 0.25rem;">
                </p>
            </div>
            <div style="padding: 2rem;">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_contact">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-control"
                                   value="<?php echo htmlspecialchars($user['COUNTRY'] ?? ''); ?>"
                                   placeholder="e.g. Bangladesh" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($user['PHONE'] ?? ''); ?>"
                                   placeholder="e.g. +880 1700-000000">
                        </div>
                    </div>
                    <div style="margin-top: 2rem; text-align: right;">
                        <button type="submit" class="btn btn-primary">Save Contact Details</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================
             BLOCK 3: Change Password — UPDATE users
             ====================================================== -->
        <div class="report-card" style="margin-bottom: 3rem;">
            <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border);">
                <h3 style="font-size: 1.3rem; font-family: var(--font-heading);">Change Password</h3>
                <p style="font-size: 0.78rem; color: var(--text-light); margin-top: 0.25rem;">
                </p>
            </div>
            <div style="padding: 2rem;">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="change_password">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password"
                                   class="form-control" placeholder="Enter current password" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password"
                                   class="form-control" placeholder="Min. 6 characters" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="form-control" placeholder="Repeat new password" required>
                        </div>
                    </div>
                    <div style="margin-top: 2rem; text-align: right;">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================
             BLOCK 4: My Booked Tickets — tickets JOIN exhibitions
             ====================================================== -->
        
        <h2 class="section-title" style="text-align: left; font-size: 1.4rem; margin-bottom: 1.5rem;">My Booked Tickets</h2>

        <div class="report-card" style="margin-bottom: 3rem;">
            <?php if (!empty($my_tickets)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Exhibition</th>
                            <th>Wing</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_tickets as $t): ?>
                            <tr>
                                <td><?php echo (int)$t['TICKET_ID']; ?></td>
                                <td><?php echo htmlspecialchars($t['EXHIBITION_TITLE'] ?? ''); ?></td>
                                <td style="color: var(--text-light); font-size: 0.85rem;"><?php echo htmlspecialchars($t['WING'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($t['TICKET_TYPE'] ?? ''); ?></td>
                                <td><?php echo (int)$t['QUANTITY']; ?></td>
                                <td>$<?php echo number_format((float)($t['UNIT_PRICE'] ?? 0), 2); ?></td>
                                <td style="font-weight: 600;">$<?php echo number_format((float)($t['TOTAL_AMOUNT'] ?? 0), 2); ?></td>
                                <td>
                                    <?php $ts = $t['STATUS'] ?? 'Confirmed'; ?>
                                    <span class="badge <?php echo $ts === 'Confirmed' ? 'badge-active' : 'badge-closed'; ?>">
                                        <?php echo htmlspecialchars($ts); ?>
                                    </span>
                                </td>
                                <td>
                                     <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                         <a href="ticket_confirmation.php?id=<?php echo (int)$t['TICKET_ID']; ?>"
                                            class="btn btn-outline"
                                            style="padding:0.25rem 0.75rem; font-size:0.8rem; color:var(--primary-color);">
                                             View
                                         </a>
                                         <?php if ($ts === 'Confirmed'): ?>
                                             <form method="POST" action="profile.php" style="display:inline;"
                                                   onsubmit="return confirm('Cancel ticket #<?php echo (int)$t['TICKET_ID']; ?>?');">
                                                 <input type="hidden" name="action" value="cancel_ticket">
                                                 <input type="hidden" name="ticket_id" value="<?php echo (int)$t['TICKET_ID']; ?>">
                                                 <button type="submit" class="btn btn-outline"
                                                         style="padding:0.25rem 0.75rem; font-size:0.8rem; color:#9E2A2B !important; border-color:#E5B3B3;">
                                                     Cancel
                                                 </button>
                                             </form>
                                         <?php endif; ?>
                                     </div>
                                 </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 3rem; text-align: center; color: var(--text-light);">
                    <p style="margin-bottom: 1rem;">You have no ticket bookings yet.</p>
                    <a href="exhibitions.php" class="btn btn-primary">Browse Exhibitions</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================
             BLOCK 5: Activity Log — SELECT from audit_logs
             ====================================================== -->
        
        <h2 class="section-title" style="text-align: left; font-size: 1.4rem; margin-bottom: 1.5rem;">Activity Log</h2>

        <div class="report-card" style="margin-bottom: 4rem;">
            <?php if (!empty($activity_log)): ?>
                <table class="report-table">
                    <thead>
                        <tr><th>Action</th><th>IP Address</th><th>Timestamp</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_log as $log): ?>
                            <tr>
                                <td><code style="font-size: 0.85rem; background: var(--surface); padding: 0.2rem 0.5rem; border-radius: 4px;"><?php echo htmlspecialchars($log['ACTION_PERFORMED'] ?? ''); ?></code></td>
                                <td><?php echo htmlspecialchars($log['IP_ADDRESS'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(substr($log['LOG_TIMESTAMP'] ?? '', 0, 16)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 2rem; color: var(--text-light);">No activity recorded yet.</p>
            <?php endif; ?>
        </div>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top: 10px; margin-bottom: 20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
