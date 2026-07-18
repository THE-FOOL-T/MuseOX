<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$db      = Database::getConnection();
$success = '';
$error   = '';
$donated = false;

// Pre-fill from session if logged in
$prefill_name  = '';
$prefill_email = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("SELECT username, email FROM users WHERE user_id = :p_user_id");
        $stmt->bindValue(':p_user_id', (int)$_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $prefill_name  = $row['USERNAME'] ?? '';
            $prefill_email = $row['EMAIL']    ?? '';
        }
    } catch (PDOException $e) {}
}

$purposes = [
    'General Fund',
    'Artifact Acquisition',
    'Exhibition Support',
    'Building & Maintenance',
    'Education Programs',
];

// ============================================================
//  Handle POST — calls pkg_MuseoX.sp_RecordDonation
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donor_name  = sanitizeInput($_POST['donor_name']  ?? '');
    $donor_email = sanitizeInput($_POST['donor_email'] ?? '');
    $amount      = $_POST['amount'] ?? '';
    $purpose     = sanitizeInput($_POST['purpose']     ?? 'General Fund');
    $message     = sanitizeInput($_POST['message']     ?? '');
    $is_anon     = isset($_POST['is_anonymous']) ? 1 : 0;
    $user_id_val = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if (empty($donor_name)) {
        $error = 'Please enter your name.';
    } elseif (!is_numeric($amount) || (float)$amount < 1) {
        $error = 'Please enter a valid donation amount (minimum $1).';
    } elseif (!in_array($purpose, $purposes, true)) {
        $error = 'Invalid purpose selected.';
    } else {
        $out_did = 0;
        try {
            $spSql  = "BEGIN pkg_MuseoX.sp_RecordDonation(:p_uid,:p_name,:p_email,:p_amount,:p_purpose,:p_msg,:p_anon,:o_did); END;";
            $spStmt = $db->prepare($spSql);
            $spStmt->bindValue(':p_uid',     $user_id_val,    PDO::PARAM_INT);
            $spStmt->bindValue(':p_name',    $is_anon ? 'Anonymous Patron' : $donor_name);
            $spStmt->bindValue(':p_email',   $donor_email ?: null);
            $spStmt->bindValue(':p_amount',  (float)$amount);
            $spStmt->bindValue(':p_purpose', $purpose);
            $spStmt->bindValue(':p_msg',     $message ?: null);
            $spStmt->bindValue(':p_anon',    $is_anon, PDO::PARAM_INT);
            $spStmt->bindParam(':o_did',     $out_did, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 4000);
            $spStmt->execute();
            $donated = true;
            $success = "Thank you" . ($is_anon ? "" : ", " . htmlspecialchars($donor_name)) . "! Donation #$out_did of $" . number_format((float)$amount, 2) . " received.";
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'ORA-20020') !== false) {
                $error = 'Amount must be greater than zero.';
            } else {
                $error = 'Donation failed. Please try again.';
                error_log('Donation error: ' . $msg);
            }
        }
    }
}

// Fetch donation totals per purpose for the public display
$donation_totals = [];
try {
    $stmt = $db->query(
        "SELECT purpose,
                COUNT(*)       AS total_donations,
                SUM(amount)    AS total_raised,
                ROUND(AVG(amount), 2) AS avg_donation
         FROM donations
         GROUP BY purpose
         ORDER BY total_raised DESC"
    );
    $donation_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$grand_total = array_sum(array_column($donation_totals, 'TOTAL_RAISED'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Support MuseoX — Donate to help preserve and share our shared cultural heritage.">
    <title>MuseoX | Donate</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <li><a href="search.php">Search</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <li><a href="dashboard.php">Admin Panel</a></li>
                <?php else: ?>
                    <li><a href="feedback.php">Feedback</a></li>
                    <li><a href="donate.php" style="color:var(--secondary-color);">Donate</a></li>
                <?php endif; ?>
                <li><a href="profile.php" style="font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding:0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="donate.php" style="color:var(--secondary-color);">Donate</a></li>
                <li><a href="login.php" style="color:var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding:0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <h1 style="font-size:2.8rem; margin-bottom:0.75rem;">Support MuseoX</h1>
        <p style="color:var(--text-light); max-width:620px; margin:0 auto;">
            Your generosity preserves history and makes culture accessible to everyone.
            Every contribution — big or small — makes a difference.
        </p>
    </header>

    <section class="section" style="padding-top:3rem; max-width:1000px;">

        <!-- Raised so far -->
        <?php if (!empty($donation_totals)): ?>
        <div class="report-card" style="padding:2rem; margin-bottom:3rem; text-align:center;">
            
            <div style="font-size:2.8rem; font-family:var(--font-heading); font-weight:700; color:var(--secondary-color);">
                $<?php echo number_format($grand_total, 2); ?>
            </div>
            <p style="color:var(--text-light); margin-top:0.35rem; font-size:0.9rem;">Total raised across all programs</p>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1rem; margin-top:1.75rem;">
                <?php foreach ($donation_totals as $dt): ?>
                    <?php $pct = $grand_total > 0 ? round(((float)$dt['TOTAL_RAISED'] / $grand_total) * 100) : 0; ?>
                    <div style="text-align:center; padding:1rem; background:#FDFBF7; border-radius:6px; border:1px solid var(--border);">
                        <div style="font-weight:700; font-size:0.85rem; font-family:var(--font-heading); margin-bottom:0.4rem;"><?php echo htmlspecialchars($dt['PURPOSE']); ?></div>
                        <div style="font-size:1.2rem; color:var(--secondary-color); font-weight:700;">$<?php echo number_format((float)$dt['TOTAL_RAISED'], 0); ?></div>
                        <div style="font-size:0.75rem; color:var(--text-light); margin-top:0.2rem;"><?php echo (int)$dt['TOTAL_DONATIONS']; ?> donation(s) · <?php echo $pct; ?>%</div>
                        <div style="background:#F0EBE3; border-radius:4px; height:6px; margin-top:0.5rem; overflow:hidden;">
                            <div style="height:100%; width:<?php echo $pct; ?>%; background:var(--secondary-color); border-radius:4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Success message -->
        <?php if ($donated && !empty($success)): ?>
        <div class="report-card" style="padding:3rem; text-align:center; margin-bottom:3rem; border-top:4px solid #16A34A;">
            <div style="font-size:3rem; margin-bottom:1rem;">💚</div>
            <h2 style="font-family:var(--font-heading); font-size:1.8rem; margin-bottom:0.75rem;">Thank You!</h2>
            <p style="color:var(--text-light); margin-bottom:1.5rem;"><?php echo $success; ?></p>
            <a href="donate.php" class="btn btn-outline" style="margin-right:0.75rem;">Donate Again</a>
            <a href="exhibitions.php" class="btn btn-primary">Explore Exhibitions</a>
        </div>
        <?php else: ?>

        <!-- Donation Form -->
        <div class="report-card" style="margin-bottom:4rem;">
            <div style="padding:1.75rem 2rem; border-bottom:1px solid var(--border);">
                <h3 style="font-size:1.3rem; font-family:var(--font-heading);">Make a Donation</h3>
                <p style="font-size:0.78rem; color:var(--text-light); margin-top:0.25rem;">
                    <span class="db-badge">BEGIN pkg_MuseoX.sp_RecordDonation(user_id, name, email, amount, purpose, msg, is_anon, o_id); END;</span>
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" style="margin:1.5rem 2rem 0;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div style="padding:2rem;">
                <form method="POST" id="donateForm">

                    <!-- Quick amount buttons -->
                    <div class="form-group">
                        <label>Select Amount</label>
                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.5rem; margin-bottom:0.75rem;">
                            <?php foreach ([25, 50, 100, 250, 500] as $amt): ?>
                                <button type="button" class="btn btn-outline amount-btn"
                                        data-amount="<?php echo $amt; ?>"
                                        style="padding:0.5rem 1.25rem;">
                                    $<?php echo $amt; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="number" id="amount" name="amount" class="form-control"
                               min="1" step="0.01" required placeholder="Or enter custom amount ($)"
                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                        <div class="form-group">
                            <label for="donor_name">Your Name *</label>
                            <input type="text" id="donor_name" name="donor_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['donor_name'] ?? $prefill_name); ?>"
                                   placeholder="Full name">
                        </div>
                        <div class="form-group">
                            <label for="donor_email">Email Address</label>
                            <input type="email" id="donor_email" name="donor_email" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['donor_email'] ?? $prefill_email); ?>"
                                   placeholder="Optional — for receipt">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label for="purpose">Donation Purpose</label>
                            <select id="purpose" name="purpose" class="form-control">
                                <?php foreach ($purposes as $p): ?>
                                    <option value="<?php echo $p; ?>"
                                        <?php if (($_POST['purpose'] ?? 'General Fund') === $p) echo 'selected'; ?>>
                                        <?php echo $p; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="message">Personal Message <span style="color:var(--text-light); font-size:0.82rem;">(optional)</span></label>
                        <textarea id="message" name="message" class="form-control" rows="3" style="resize:vertical;"
                                  placeholder="Share what inspired your donation..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.5rem;">
                        <input type="checkbox" id="is_anonymous" name="is_anonymous"
                               style="width:18px; height:18px; cursor:pointer;"
                               <?php if (isset($_POST['is_anonymous'])) echo 'checked'; ?>>
                        <label for="is_anonymous" style="cursor:pointer; margin:0;">
                            Make this donation anonymous <span style="color:var(--text-light); font-size:0.82rem;">(your name won't appear publicly)</span>
                        </label>
                    </div>
                    <div style="text-align:right;">
                        <button type="submit" class="btn btn-primary" style="padding:0.85rem 2.5rem; font-size:1rem;">
                            Donate Now 💚
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

    <script>
        document.querySelectorAll('.amount-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('amount').value = this.dataset.amount;
            });
        });
    </script>

</body>
</html>
