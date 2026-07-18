<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Feedback requires login (visitors only)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db      = Database::getConnection();
$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ============================================================
//  Fetch exhibitions available to rate (Active or Closed)
// ============================================================
$exhibitions = [];
try {
    $stmt = $db->query(
        "SELECT exhibition_id, title, wing, status,
                pkg_MuseoX.fn_GetExhibitionRating(exhibition_id) AS avg_rating
         FROM exhibitions
         WHERE status IN ('Active', 'Closed')
         ORDER BY title ASC"
    );
    $exhibitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================================
//  Handle POST — calls pkg_MuseoX.sp_SubmitFeedback
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exhibition_id = isset($_POST['exhibition_id']) && is_numeric($_POST['exhibition_id'])
                     ? (int)$_POST['exhibition_id'] : 0;
    $subject       = sanitizeInput($_POST['subject']  ?? '');
    $message       = sanitizeInput($_POST['message']  ?? '');
    $rating        = isset($_POST['rating']) && is_numeric($_POST['rating'])
                     ? max(1, min(5, (int)$_POST['rating'])) : 0;

    if ($exhibition_id <= 0 || $rating === 0 || empty($message)) {
        $error = 'Please select an exhibition, write a message, and choose a rating.';
    } else {
        $out_fid = 0;
        try {
            $spSql  = "BEGIN pkg_MuseoX.sp_SubmitFeedback(:p_uid, :p_eid, :p_subj, :p_msg, :p_rating, :o_fid); END;";
            $spStmt = $db->prepare($spSql);
            $spStmt->bindValue(':p_uid',    $user_id,       PDO::PARAM_INT);
            $spStmt->bindValue(':p_eid',    $exhibition_id, PDO::PARAM_INT);
            $spStmt->bindValue(':p_subj',   $subject ?: 'Visitor Feedback');
            $spStmt->bindValue(':p_msg',    $message);
            $spStmt->bindValue(':p_rating', $rating,        PDO::PARAM_INT);
            $spStmt->bindParam(':o_fid',    $out_fid,       PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 4000);
            $spStmt->execute();
            $success = "Thank you! Your feedback #$out_fid has been submitted.";
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'ORA-20010') !== false) {
                $error = 'Rating must be between 1 and 5.';
            } else {
                $error = 'Submission failed. Please try again.';
                error_log('Feedback error: ' . $msg);
            }
        }
    }
}

// ============================================================
//  Fetch this visitor's own feedback history — SELECT + JOIN
// ============================================================
$my_feedback = [];
try {
    $stmt = $db->prepare(
        "SELECT f.feedback_id, f.subject, f.message, f.rating, f.status,
                f.created_at, e.title AS exhibition_title
         FROM feedback f
         LEFT JOIN exhibitions e ON f.exhibition_id = e.exhibition_id
         WHERE f.user_id = :p_user_id
         ORDER BY f.created_at DESC"
    );
    $stmt->bindValue(':p_user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $my_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Leave your feedback and rate exhibitions at MuseoX.">
    <title>MuseoX | Feedback & Ratings</title>
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
                    <li><a href="feedback.php" style="color:var(--secondary-color);">Feedback</a></li>
                    <li><a href="donate.php">Donate</a></li>
                <?php endif; ?>
                <li><a href="profile.php" style="font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding:0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="donate.php">Donate</a></li>
                <li><a href="login.php" style="color:var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding:0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <h1 style="font-size:2.6rem; margin-bottom:0.75rem;">Feedback & Ratings</h1>
        <p style="color:var(--text-light); max-width:600px; margin:0 auto;">
            Share your experience and help us improve. Rate the exhibitions you've visited.
        </p>
    </header>

    <section class="section" style="padding-top:3rem; max-width:1000px;">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom:2rem;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" style="margin-bottom:2rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Exhibition Ratings Overview -->
        <?php if (!empty($exhibitions)): ?>
        
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">Exhibition Ratings</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:1rem; margin-bottom:3rem;">
            <?php foreach ($exhibitions as $ex): ?>
                <?php $avg = (float)($ex['AVG_RATING'] ?? 0); $full = floor($avg); $half = ($avg - $full) >= 0.5; ?>
                <div class="report-card" style="padding:1.25rem; text-align:center;">
                    <div style="font-weight:700; font-family:var(--font-heading); font-size:0.95rem; margin-bottom:0.5rem;">
                        <?php echo htmlspecialchars($ex['TITLE']); ?>
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-light); margin-bottom:0.5rem;">
                        <?php echo htmlspecialchars($ex['WING'] ?? ''); ?>
                    </div>
                    <div style="font-size:1.4rem; color:#D97706; letter-spacing:2px; margin-bottom:0.25rem;">
                        <?php
                            echo str_repeat('★', $full);
                            echo $half ? '½' : '';
                            echo str_repeat('☆', max(0, 5 - $full - ($half ? 1 : 0)));
                        ?>
                    </div>
                    <div style="font-size:0.85rem; font-weight:600; color:var(--secondary-color);">
                        <?php echo $avg > 0 ? number_format($avg, 1) . ' / 5.0' : 'No ratings yet'; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Submit Feedback Form -->
        <div class="report-card" style="margin-bottom:3rem;">
            <div style="padding:1.75rem 2rem; border-bottom:1px solid var(--border);">
                <h3 style="font-size:1.3rem; font-family:var(--font-heading);">Submit Feedback</h3>
                <p style="font-size:0.78rem; color:var(--text-light); margin-top:0.25rem;">
                </p>
            </div>
            <div style="padding:2rem;">
                <form method="POST" id="feedbackForm">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                        <div class="form-group">
                            <label for="exhibition_id">Exhibition *</label>
                            <select id="exhibition_id" name="exhibition_id" class="form-control" required>
                                <option value="">— Select Exhibition —</option>
                                <?php foreach ($exhibitions as $ex): ?>
                                    <option value="<?php echo (int)$ex['EXHIBITION_ID']; ?>"
                                        <?php if (isset($_POST['exhibition_id']) && (int)$_POST['exhibition_id'] === (int)$ex['EXHIBITION_ID']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($ex['TITLE']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                   placeholder="e.g. Amazing Experience!">
                        </div>
                    </div>

                    <!-- Star Rating -->
                    <div class="form-group">
                        <label>Your Rating *</label>
                        <div style="display:flex; gap:0.5rem; margin-top:0.5rem;" id="starRating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>"
                                           style="display:none;" class="star-radio"
                                           <?php if ((int)($_POST['rating'] ?? 0) === $i) echo 'checked'; ?>>
                                    <span class="star-btn" data-val="<?php echo $i; ?>"
                                          style="font-size:2rem; color:#D1C5BC; transition:color 0.15s; cursor:pointer;">★</span>
                                </label>
                            <?php endfor; ?>
                            <span id="ratingLabel" style="align-self:center; font-size:0.85rem; color:var(--text-light); margin-left:0.5rem;"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Your Message *</label>
                        <textarea id="message" name="message" class="form-control" rows="4"
                                  style="resize:vertical;" required
                                  placeholder="Share your thoughts about this exhibition..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <div style="text-align:right;">
                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- My Feedback History -->
        <?php if (!empty($my_feedback)): ?>
        
        <h2 class="section-title" style="text-align:left; font-size:1.4rem; margin-bottom:1.5rem;">My Feedback History</h2>
        <div class="report-card" style="margin-bottom:4rem;">
            <table class="report-table">
                <thead><tr><th>#</th><th>Exhibition</th><th>Subject</th><th>Rating</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($my_feedback as $f): ?>
                        <tr>
                            <td style="color:var(--text-light);"><?php echo (int)$f['FEEDBACK_ID']; ?></td>
                            <td><?php echo htmlspecialchars($f['EXHIBITION_TITLE'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($f['SUBJECT'] ?? ''); ?></td>
                            <td style="color:#D97706; letter-spacing:1px;">
                                <?php echo str_repeat('★', (int)($f['RATING'] ?? 0)) . str_repeat('☆', 5 - (int)($f['RATING'] ?? 0)); ?>
                            </td>
                            <td>
                                <?php $st = $f['STATUS'] ?? 'Pending'; $bc = match($st) { 'Reviewed' => 'badge-active', 'Closed' => 'badge-closed', default => 'badge-upcoming' }; ?>
                                <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($st); ?></span>
                            </td>
                            <td style="font-size:0.85rem;"><?php echo htmlspecialchars(substr($f['CREATED_AT'] ?? '', 0, 16)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

    <script>
        const labels  = ['','Terrible','Poor','Average','Good','Excellent'];
        const radios  = document.querySelectorAll('.star-radio');
        const stars   = document.querySelectorAll('.star-btn');
        const rLabel  = document.getElementById('ratingLabel');

        function highlightStars(val) {
            stars.forEach(s => {
                s.style.color = parseInt(s.dataset.val) <= val ? '#D97706' : '#D1C5BC';
            });
            rLabel.textContent = val ? labels[val] : '';
        }

        // Init from checked state (page reload)
        const checked = document.querySelector('.star-radio:checked');
        if (checked) highlightStars(parseInt(checked.value));

        stars.forEach(s => {
            s.addEventListener('mouseover', () => highlightStars(parseInt(s.dataset.val)));
            s.addEventListener('click', () => {
                const v = parseInt(s.dataset.val);
                radios[v - 1].checked = true;
                highlightStars(v);
            });
        });
        document.getElementById('starRating').addEventListener('mouseleave', () => {
            const c = document.querySelector('.star-radio:checked');
            highlightStars(c ? parseInt(c.value) : 0);
        });
    </script>

</body>
</html>
