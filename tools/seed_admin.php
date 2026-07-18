<?php
/**
 * MuseoX — One-Time Admin Seeder Utility
 * DELETE this file after use. Never leave it on a production server.
 *
 * Access: http://localhost/MuseoX/tools/seed_admin.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$message = '';
$type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $message = 'All fields are required.';
        $type    = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $type    = 'error';
    } else {
        try {
            $db = Database::getConnection();

            // Check duplicates
            $chk = $db->prepare("SELECT user_id FROM users WHERE email = :p_email OR username = :p_uname");
            $chk->bindValue(':p_email',  $email);
            $chk->bindValue(':p_uname',  $username);
            $chk->execute();

            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                $message = 'A user with this username or email already exists.';
                $type    = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Get Admin role_id
                $roleStmt = $db->query("SELECT role_id FROM roles WHERE role_name = 'Admin'");
                $role     = $roleStmt->fetch(PDO::FETCH_ASSOC);

                if (!$role) {
                    $message = 'Admin role not found in the roles table. Run oracle.sql first.';
                    $type    = 'error';
                } else {
                    $ins = $db->prepare(
                        "INSERT INTO users (username, email, password, role_id, status)
                         VALUES (:p_uname, :p_email, :p_hash, :p_role, 'Active')"
                    );
                    $ins->bindValue(':p_uname', $username);
                    $ins->bindValue(':p_email', $email);
                    $ins->bindValue(':p_hash',  $hash);
                    $ins->bindValue(':p_role',  $role['ROLE_ID'], PDO::PARAM_INT);
                    $ins->execute();

                    $message = "Admin account created! Login with: $email / [your password]. DELETE this file now.";
                    $type    = 'success';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . htmlspecialchars($e->getMessage());
            $type    = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Admin Seeder (Dev Tool)</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #1C1917; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .seed-card { background: #FDFBF7; border-radius: 8px; padding: 2.5rem; width: 100%; max-width: 440px; }
        .seed-card h2 { font-family: 'Lora', serif; font-size: 1.6rem; margin-bottom: 0.5rem; color: #1C1917; }
        .seed-card .warning { background: #FEF9EC; border: 1px solid #F0D68D; color: #92610D; border-radius: 6px; padding: 0.85rem 1rem; font-size: 0.85rem; margin-bottom: 1.5rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="seed-card">
        <h2>Create Admin Account</h2>
        <p style="color:#7A7571; font-size:0.9rem; margin-bottom:1.5rem;">One-time setup utility. Delete this file after use.</p>

        <div class="warning">⚠ Dev Tool Only — Delete <code>tools/seed_admin.php</code> after creating the admin account.</div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $type === 'error' ? 'error' : 'success'; ?>" style="margin-bottom:1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>"
                       placeholder="admin" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@museox.com'); ?>"
                       placeholder="admin@museox.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Min. 6 characters" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Create Admin Account</button>
        </form>
    </div>
</body>
</html>
