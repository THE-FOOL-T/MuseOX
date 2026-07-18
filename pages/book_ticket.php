<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Must be logged in to book
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Admins manage — they don't book tickets
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    header('Location: exhibitions.php');
    exit();
}

$db      = Database::getConnection();
$user_id = (int)$_SESSION['user_id'];

// Validate exhibition ID from URL
$exhibition_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($exhibition_id <= 0) {
    header('Location: exhibitions.php');
    exit();
}

// Fetch the target exhibition (only Active or Upcoming)
$exhibition = [];
try {
    $stmt = $db->prepare(
        "SELECT * FROM exhibitions
         WHERE exhibition_id = :eid AND status IN ('Active', 'Upcoming')"
    );
    $stmt->bindValue(':eid', $exhibition_id, PDO::PARAM_INT);
    $stmt->execute();
    $exhibition = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Exhibition fetch error: ' . $e->getMessage());
}

if (!$exhibition) {
    header('Location: exhibitions.php');
    exit();
}

$success = '';
$error   = '';
$booking = null;

// ============================================================
//  Handle Booking POST — calls sp_BookTicket procedure
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_type = $_POST['ticket_type'] ?? 'Adult';
    $quantity    = isset($_POST['quantity']) && is_numeric($_POST['quantity'])
                   ? max(1, min(10, (int)$_POST['quantity']))
                   : 1;

    $valid_types = ['Adult', 'Child', 'Senior'];
    if (!in_array($ticket_type, $valid_types)) $ticket_type = 'Adult';

    $out_ticket_id = 0;
    $out_total     = 0.0;

    try {
        $spSql  = "BEGIN sp_BookTicket(:p_uid, :p_eid, :p_type, :p_qty, :o_tid, :o_amt); END;";
        $spStmt = $db->prepare($spSql);
        $spStmt->bindValue(':p_uid',  $user_id,       PDO::PARAM_INT);
        $spStmt->bindValue(':p_eid',  $exhibition_id, PDO::PARAM_INT);
        $spStmt->bindValue(':p_type', $ticket_type);
        $spStmt->bindValue(':p_qty',  $quantity,      PDO::PARAM_INT);
        $spStmt->bindParam(':o_tid',  $out_ticket_id, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 4000);
        $spStmt->bindParam(':o_amt',  $out_total,     PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 4000);
        $spStmt->execute();

        $booking = [
            'ticket_id'   => (int)$out_ticket_id,
            'ticket_type' => $ticket_type,
            'quantity'    => $quantity,
            'total'       => (float)$out_total,
            'title'       => $exhibition['TITLE'],
        ];
        $success = 'Your tickets have been booked successfully!';

    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // ORA-20001 = capacity error raised by sp_BookTicket
        if (strpos($msg, 'ORA-20001') !== false) {
            preg_match('/ORA-20001: (.+?)(?:\n|$)/', $msg, $m);
            $error = $m[1] ?? 'Not enough capacity available for this exhibition.';
        } elseif (strpos($msg, 'ORA-20002') !== false) {
            $error = 'This exhibition is no longer available for booking.';
        } else {
            $error = 'Booking failed. Please try again.';
            error_log('BookTicket error: ' . $msg);
        }
    }
}

// Ticket price multipliers (must match sp_BookTicket logic)
$multipliers = ['Adult' => 1.0, 'Senior' => 0.8, 'Child' => 0.5];
$base_price  = (float)($exhibition['TICKET_PRICE'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Book tickets for <?php echo htmlspecialchars($exhibition['TITLE']); ?> at MuseoX.">
    <title>MuseoX | Book Tickets — <?php echo htmlspecialchars($exhibition['TITLE']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>">
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
                    <li><a href="donate.php">Donate</a></li>
                <?php endif; ?>
                <li><a href="profile.php" style="color:var(--secondary-color); font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="login.php?action=logout" class="btn btn-outline" style="padding:0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="donate.php">Donate</a></li>
                <li><a href="login.php" style="color:var(--primary-color);">Sign In</a></li>
                <li><a href="register.php" class="btn btn-primary" style="padding:0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="page-header">
        <div class="card-category" style="margin-bottom: 0.75rem;">
            <?php echo htmlspecialchars($exhibition['WING'] ?? ''); ?>
        </div>
        <h1 style="font-size: 2.4rem; margin-bottom: 0.75rem;">
            <?php echo htmlspecialchars($exhibition['TITLE']); ?>
        </h1>
        <p style="color: var(--text-light); max-width: 650px; margin: 0 auto;">
            <?php echo htmlspecialchars($exhibition['DESCRIPTION'] ?? ''); ?>
        </p>
    </header>

    <section class="section" style="padding-top: 3rem; max-width: 900px;">

        <!-- SUCCESS CONFIRMATION -->
        <?php if ($success && $booking): ?>
            <div style="background: #F2F7F4; border: 1px solid #B7D5C4; border-radius: var(--radius); padding: 2.5rem; margin-bottom: 3rem; text-align: center;">
                <h2 style="color: #2D6A4F; margin-bottom: 1rem; font-family: var(--font-heading);">Booking Confirmed!</h2>
                <p style="color: #2D6A4F; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($success); ?></p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div class="stat-card">
                        <span class="stat-number" style="font-size: 1.6rem; color: #2D6A4F;">#<?php echo $booking['ticket_id']; ?></span>
                        <span class="stat-label">Ticket ID</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number" style="font-size: 1.6rem; color: #2D6A4F;"><?php echo $booking['quantity']; ?></span>
                        <span class="stat-label"><?php echo $booking['ticket_type']; ?> Ticket(s)</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number" style="font-size: 1.6rem; color: #2D6A4F;">$<?php echo number_format($booking['total'], 2); ?></span>
                        <span class="stat-label">Total Amount</span>
                    </div>
                </div>
                <a href="profile.php" class="btn btn-primary" style="margin-right: 1rem;">View My Tickets</a>
                <a href="exhibitions.php" class="btn btn-outline">Back to Exhibitions</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$booking): ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: start;">

            <!-- Exhibition Info Card -->
            <div class="report-card">
                <img src="<?php echo htmlspecialchars($exhibition['IMAGE_URL'] ?? ''); ?>"
                     alt="<?php echo htmlspecialchars($exhibition['TITLE']); ?>"
                     style="width: 100%; height: 220px; object-fit: cover; display: block;"
                     onerror="this.style.display='none'">
                <div style="padding: 1.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div class="card-category"><?php echo htmlspecialchars($exhibition['WING'] ?? ''); ?></div>
                        <?php
                            $s = $exhibition['STATUS'] ?? 'Upcoming';
                            $bc = match(strtolower($s)) { 'active' => 'badge-active', 'upcoming' => 'badge-upcoming', default => 'badge-closed' };
                        ?>
                        <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($s); ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Base Price</span>
                        <span class="profile-value" style="font-weight: 600; color: var(--secondary-color);">
                            $<?php echo number_format($base_price, 2); ?> / ticket
                        </span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Capacity</span>
                        <span class="profile-value"><?php echo number_format((int)($exhibition['CAPACITY'] ?? 0)); ?> visitors</span>
                    </div>
                    <?php
                        $sd = $exhibition['START_DATE'] ?? '';
                        $ed = $exhibition['END_DATE']   ?? '';
                        if ($sd):
                    ?>
                        <div class="profile-row" style="border-bottom: none;">
                            <span class="profile-label">Dates</span>
                            <span class="profile-value">
                                <?php echo htmlspecialchars(substr($sd, 0, 11)); ?><br>
                                to <?php echo htmlspecialchars(substr($ed, 0, 11)); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booking Form -->
            <div>
                <div class="report-card">
                    <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border);">
                        <h3 style="font-size: 1.3rem; font-family: var(--font-heading);">Select Tickets</h3>
                        <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem;">
                            Calls: <code style="background: var(--surface); padding: 0.2rem 0.5rem; border-radius: 4px;">sp_BookTicket(user_id, exhibition_id, type, qty)</code>
                        </p>
                    </div>
                    <div style="padding: 2rem;">
                        <form method="POST" id="bookingForm">

                            <!-- Ticket Type -->
                            <div class="form-group">
                                <label>Ticket Type</label>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-top: 0.5rem;" id="ticketTypeGrid">
                                    <?php foreach ($multipliers as $type => $mult): ?>
                                        <label style="cursor: pointer;">
                                            <input type="radio" name="ticket_type" value="<?php echo $type; ?>"
                                                   <?php echo $type === 'Adult' ? 'checked' : ''; ?>
                                                   class="ticket-type-radio"
                                                   style="display: none;">
                                            <div class="ticket-type-btn" data-price="<?php echo round($base_price * $mult, 2); ?>">
                                                <div style="font-weight: 700; font-size: 0.9rem;"><?php echo $type; ?></div>
                                                <div style="font-size: 0.8rem; color: var(--text-light); margin-top: 0.15rem;">
                                                    $<?php echo number_format($base_price * $mult, 2); ?>
                                                </div>
                                                <?php if ($mult < 1): ?>
                                                    <div style="font-size: 0.7rem; color: var(--secondary-color); font-weight: 700; margin-top: 0.15rem;">
                                                        <?php echo round((1 - $mult) * 100); ?>% OFF
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Quantity -->
                            <div class="form-group">
                                <label for="quantity">Quantity (max 10)</label>
                                <input type="number" id="quantity" name="quantity" class="form-control"
                                       value="1" min="1" max="10">
                            </div>

                            <!-- Price Summary -->
                            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1.5rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="color: var(--text-light); font-size: 0.9rem;">Unit Price</span>
                                    <span id="unitPrice" style="font-weight: 600;">$<?php echo number_format($base_price, 2); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="color: var(--text-light); font-size: 0.9rem;">Quantity</span>
                                    <span id="qtyDisplay">1</span>
                                </div>
                                <hr style="border: 0; border-top: 1px solid var(--border); margin: 0.75rem 0;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="font-weight: 700;">Total</span>
                                    <span id="totalPrice" style="font-weight: 700; font-size: 1.1rem; color: var(--secondary-color);">$<?php echo number_format($base_price, 2); ?></span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1rem; padding: 1rem;">
                                Confirm Booking
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top: 10px; margin-bottom: 20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

    <style>
        .ticket-type-btn {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 0.85rem 0.5rem;
            text-align: center;
            background: var(--background);
            transition: var(--transition);
        }
        .ticket-type-radio:checked + .ticket-type-btn {
            border-color: var(--secondary-color);
            background: #FDF3F0;
        }
    </style>

    <script>
        const basePrice     = <?php echo $base_price; ?>;
        const multipliers   = <?php echo json_encode($multipliers); ?>;
        const unitPriceEl   = document.getElementById('unitPrice');
        const totalPriceEl  = document.getElementById('totalPrice');
        const qtyDisplayEl  = document.getElementById('qtyDisplay');
        const qtyInput      = document.getElementById('quantity');

        function updateSummary() {
            const selectedType = document.querySelector('.ticket-type-radio:checked')?.value || 'Adult';
            const mult      = multipliers[selectedType] || 1.0;
            const unitPrice = Math.round(basePrice * mult * 100) / 100;
            const qty       = parseInt(qtyInput.value) || 1;
            const total     = Math.round(unitPrice * qty * 100) / 100;

            unitPriceEl.textContent  = '$' + unitPrice.toFixed(2);
            qtyDisplayEl.textContent = qty;
            totalPriceEl.textContent = '$' + total.toFixed(2);
        }

        document.querySelectorAll('.ticket-type-radio').forEach(r => r.addEventListener('change', updateSummary));
        qtyInput.addEventListener('input', updateSummary);
        updateSummary();
    </script>

</body>
</html>
