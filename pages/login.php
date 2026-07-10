<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Authentication();
$error = '';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        if ($auth->authenticateUser($email, $password)) {
            header("Location: " . BASE_URL . "index.php");
            exit();
        } else {
            $error = "Invalid email or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MuseoX</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
</head>
<body>
    
    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
    </nav>

    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <h2>Welcome Back</h2>
                <p>Sign in to manage your tickets and bookings.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php echo displayFlashMessage(); ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <div class="form-group" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; color: var(--text-light); margin: 0;">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#" style="font-size: 0.9rem; color: var(--secondary-color); text-decoration: none;">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Sign In</button>
            </form>

            <div style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: var(--text-light);">
                Don't have an account? <a href="register.php" style="color: var(--secondary-color); font-weight: 500; text-decoration: none;">Register here</a>
            </div>
        </div>
    </div>

</body>
</html>