<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Authentication();
$error = '';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = sanitizeInput($_POST['phone'] ?? '');
    $country  = sanitizeInput($_POST['country'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($phone) || empty($country)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        if ($auth->registersVisitorProfile($username, $email, $password, $phone, $country)) {
            setFlashMessage('success', 'Registration successful! You can now log in.');
            header("Location: " . BASE_URL . "pages/login.php");
            exit();
        } else {
            if (isset($_SESSION['flash'])) {
                $error = $_SESSION['flash']['message'];
                unset($_SESSION['flash']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - MuseoX</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
    </nav>

    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <h2>Create Account</h2>
                <p>Register to book tickets and explore our gallery.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST" autocomplete="off">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="torikul" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="torikul@example.com" required autocomplete="off">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="01909493850" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" class="form-control" placeholder="Bangladesh" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; padding: 0.75rem;">Create Account</button>
            </form>

            <div style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: var(--text-light);">
                Already have an account? <a href="login.php" style="color: var(--secondary-color); font-weight: 500; text-decoration: none;">Sign in</a>
            </div>
        </div>
    </div>

</body>
</html>