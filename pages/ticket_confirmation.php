<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db      = Database::getConnection();
$user_id = (int)$_SESSION['user_id'];
$ticket_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticket_id <= 0) {
    header('Location: profile.php');
    exit();
}

// ============================================================
//  Fetch ticket — must belong to the logged-in user (security)
//  Demonstrates: multi-table JOIN, TO_CHAR, NVL, date arithmetic
// ============================================================
$ticket = null;
try {
    $stmt = $db->prepare(
        "SELECT t.ticket_id,
                t.ticket_type,
                t.quantity,
                t.unit_price,
                t.total_amount,
                t.status,
                TO_CHAR(t.booked_at,    'DD Month YYYY HH24:MI')  AS booked_fmt,
                TO_CHAR(t.booked_at,    'YYYY-MM-DD')              AS booked_date,
                e.title          AS exhibition_title,
                e.wing,
                e.description    AS exhibition_desc,
                TO_CHAR(e.start_date,   'DD Month YYYY')           AS start_fmt,
                TO_CHAR(e.end_date,     'DD Month YYYY')           AS end_fmt,
                e.ticket_price   AS base_price,
                e.capacity,
                u.username,
                u.email,
                NVL(v.phone,    '—')  AS phone,
                NVL(v.country,  '—')  AS country
         FROM tickets t
         JOIN exhibitions e ON t.exhibition_id = e.exhibition_id
         JOIN users u       ON t.user_id       = u.user_id
         LEFT JOIN visitors v ON t.user_id     = v.user_id
         WHERE t.ticket_id = :p_tid
           AND t.user_id   = :p_uid"
    );
    $stmt->bindValue(':p_tid', $ticket_id, PDO::PARAM_INT);
    $stmt->bindValue(':p_uid', $user_id,   PDO::PARAM_INT);
    $stmt->execute();
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Ticket confirmation error: ' . $e->getMessage());
}

// Ticket not found or doesn't belong to this user
if (!$ticket) {
    header('Location: profile.php');
    exit();
}

// Generate a simple confirmation code from ticket_id + booked_date
$confirm_code = strtoupper(sprintf(
    'MX-%05d-%s',
    $ticket_id,
    strtoupper(substr(md5($ticket_id . $ticket['BOOKED_DATE']), 0, 6))
));

$is_confirmed = $ticket['STATUS'] === 'Confirmed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Ticket #<?php echo $ticket_id; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <style>
        @media print {
            .navbar, footer, .no-print { display: none !important; }
            body { background: #fff; }
            .ticket-wrapper { box-shadow: none !important; border: 2px solid #ccc !important; }
        }
        .ticket-wrapper {
            max-width: 680px;
            margin: 0 auto 3rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            border: 1px solid var(--border);
        }
        .ticket-header {
            background: var(--primary-color);
            color: #fff;
            padding: 2rem 2.5rem;
        }
        .ticket-header h2 {
            font-size: 1.6rem;
            margin-bottom: 0.25rem;
            font-family: var(--font-heading);
        }
        .ticket-body {
            background: #fff;
            padding: 2rem 2.5rem;
        }
        .ticket-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding: 0.65rem 0;
            font-size: 0.9rem;
        }
        .ticket-row:last-child { border-bottom: none; }
        .ticket-label { color: var(--text-light); font-size: 0.82rem; }
        .ticket-value { font-weight: 600; text-align: right; }
        .ticket-footer {
            background: var(--bg-secondary);
            padding: 1.5rem 2.5rem;
            text-align: center;
            border-top: 2px dashed var(--border);
        }
        .confirm-code {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 4px;
            color: var(--secondary-color);
        }
        .barcode-placeholder {
            display: flex;
            gap: 3px;
            justify-content: center;
            margin: 0.75rem 0;
        }
        .barcode-placeholder span {
            display: inline-block;
            width: 3px;
            background: var(--primary-color);
            border-radius: 1px;
        }
    </style>
</head>
<body>

    <nav class="navbar no-print">
        <a href="../index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="exhibitions.php">Exhibitions</a></li>
            <li><a href="artifacts.php">Artifacts</a></li>
            <li><a href="gallery.php">Virtual Gallery</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <li><a href="dashboard.php">Admin Panel</a></li>
            <?php else: ?>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="donate.php">Donate</a></li>
            <?php endif; ?>
            <li><a href="profile.php" style="font-weight:700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
            <li><a href="login.php?action=logout" class="btn btn-outline" style="padding:0.5rem 1rem;">Logout</a></li>
        </ul>
    </nav>

    <header class="page-header" style="padding-bottom:1.5rem;">
        <h1 style="font-size:2rem; margin-bottom:0.5rem;">Booking Confirmation</h1>
        <p style="color:var(--text-light); font-size:0.88rem;">
            SELECT t.*, e.title, e.wing, u.username, TO_CHAR(t.booked_at,'DD Month YYYY HH24:MI'), NVL(v.phone,'—')
            FROM tickets t JOIN exhibitions e … JOIN users u … LEFT JOIN visitors v … WHERE t.ticket_id = ? AND t.user_id = ?
        </p>
    </header>

    <section class="section" style="padding-top:2rem;">

        <div style="text-align:center; margin-bottom:2rem;" class="no-print">
            <a href="profile.php" class="btn btn-outline" style="margin-right:0.75rem;">← Back to Profile</a>
            <button onclick="window.print()" class="btn btn-primary">🖨 Print Ticket</button>
        </div>

        <!-- Ticket -->
        <div class="ticket-wrapper">

            <!-- Header -->
            <div class="ticket-header">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <div style="font-size:0.8rem; letter-spacing:1px; opacity:0.7; text-transform:uppercase; margin-bottom:0.25rem;">MuseoX Museum</div>
                        <h2><?php echo htmlspecialchars($ticket['EXHIBITION_TITLE']); ?></h2>
                        <div style="opacity:0.8; font-size:0.9rem; margin-top:0.25rem;">
                            <?php echo htmlspecialchars($ticket['WING'] ?? ''); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="background:<?php echo $is_confirmed ? '#16A34A' : '#D97706'; ?>;
                                    padding:0.4rem 1rem; border-radius:20px; font-size:0.8rem; font-weight:700; display:inline-block;">
                            <?php echo htmlspecialchars($ticket['STATUS']); ?>
                        </div>
                        <div style="font-size:0.75rem; opacity:0.7; margin-top:0.4rem;">Ticket #<?php echo $ticket_id; ?></div>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="ticket-body">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0; margin-bottom:0.5rem;">
                    <div style="padding-right:1.5rem; border-right:1px solid var(--border);">
                        <div style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-light); margin-bottom:0.75rem; font-weight:700;">Visitor Details</div>
                        <div class="ticket-row"><span class="ticket-label">Name</span><span class="ticket-value"><?php echo htmlspecialchars($ticket['USERNAME']); ?></span></div>
                        <div class="ticket-row"><span class="ticket-label">Email</span><span class="ticket-value" style="font-size:0.82rem;"><?php echo htmlspecialchars($ticket['EMAIL']); ?></span></div>
                        <div class="ticket-row"><span class="ticket-label">Phone</span><span class="ticket-value"><?php echo htmlspecialchars($ticket['PHONE']); ?></span></div>
                        <div class="ticket-row"><span class="ticket-label">Country</span><span class="ticket-value"><?php echo htmlspecialchars($ticket['COUNTRY']); ?></span></div>
                    </div>
                    <div style="padding-left:1.5rem;">
                        <div style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-light); margin-bottom:0.75rem; font-weight:700;">Ticket Details</div>
                        <div class="ticket-row"><span class="ticket-label">Type</span><span class="ticket-value"><?php echo htmlspecialchars($ticket['TICKET_TYPE']); ?></span></div>
                        <div class="ticket-row"><span class="ticket-label">Quantity</span><span class="ticket-value"><?php echo (int)$ticket['QUANTITY']; ?></span></div>
                        <div class="ticket-row"><span class="ticket-label">Unit Price</span><span class="ticket-value">$<?php echo number_format((float)$ticket['UNIT_PRICE'], 2); ?></span></div>
                        <div class="ticket-row"><span class="ticket-label">Total</span><span class="ticket-value" style="color:var(--secondary-color); font-size:1rem;">$<?php echo number_format((float)$ticket['TOTAL_AMOUNT'], 2); ?></span></div>
                    </div>
                </div>

                <div style="background:var(--bg-secondary); border-radius:8px; padding:1rem 1.25rem; margin-top:1rem;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                        <div><div class="ticket-label">Exhibition Period</div>
                            <div style="font-weight:600; font-size:0.88rem; margin-top:0.2rem;">
                                <?php echo htmlspecialchars($ticket['START_FMT'] ?? '—'); ?> — <?php echo htmlspecialchars($ticket['END_FMT'] ?? '—'); ?>
                            </div>
                        </div>
                        <div style="text-align:right;"><div class="ticket-label">Booked On</div>
                            <div style="font-weight:600; font-size:0.88rem; margin-top:0.2rem;">
                                <?php echo htmlspecialchars($ticket['BOOKED_FMT']); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer / Barcode -->
            <div class="ticket-footer">
                <div style="font-size:0.72rem; color:var(--text-light); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.5rem;">Confirmation Code</div>
                <div class="confirm-code"><?php echo $confirm_code; ?></div>

                <!-- Barcode visual (decorative) -->
                <div class="barcode-placeholder" style="margin:0.75rem 0;">
                    <?php
                    srand($ticket_id * 37);
                    for ($i = 0; $i < 50; $i++) {
                        $h = rand(16, 40);
                        echo '<span style="height:' . $h . 'px; width:' . (rand(0, 1) ? '3px' : '2px') . ';"></span>';
                    }
                    ?>
                </div>

                <div style="font-size:0.75rem; color:var(--text-light); max-width:400px; margin:0 auto; line-height:1.6;">
                    Present this confirmation at the museum entrance. This ticket is valid for
                    <strong><?php echo (int)$ticket['QUANTITY']; ?></strong> visitor<?php echo (int)$ticket['QUANTITY'] > 1 ? 's' : ''; ?>.
                    Non-transferable.
                </div>
            </div>

        </div>

        <div style="text-align:center;" class="no-print">
            <a href="profile.php" style="color:var(--text-light); font-size:0.88rem;">← Return to your profile</a>
        </div>

    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top:10px; margin-bottom:20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>
