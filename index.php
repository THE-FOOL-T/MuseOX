<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseoX | Contemporary Museum Management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time(); ?>">
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="nav-logo">MuseoX</a>
        <ul class="nav-links">
            <li><a href="pages/exhibitions.php">Exhibitions</a></li>
            <li><a href="pages/artifacts.php">Artifacts</a></li>
            <li><a href="pages/gallery.php">Virtual Gallery</a></li>
            <li><a href="pages/search.php">Search</a></li>
            <li><a href="pages/visit.php">Plan Visit</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <li><a href="pages/dashboard.php">Admin Panel</a></li>
                <?php else: ?>
                    <li><a href="pages/feedback.php">Feedback</a></li>
                    <li><a href="pages/donate.php">Donate</a></li>
                <?php endif; ?>
                <li><a href="pages/profile.php" style="color: var(--secondary-color); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
                <li><a href="pages/login.php?action=logout" class="btn btn-outline" style="padding: 0.5rem 1rem;">Logout</a></li>
            <?php else: ?>
                <li><a href="pages/donate.php">Donate</a></li>
                <li><a href="pages/login.php" style="color: var(--primary-color);">Sign In</a></li>
                <li><a href="pages/register.php" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <header class="hero">
        <h1>Art &amp; History, Digitized.</h1>
        <p>Explore thousands of artifacts, book your visit, and take a virtual tour of our contemporary and historical galleries.</p>
        <!-- Search bar -->
        <form action="pages/search.php" method="GET"
              style="display:flex; max-width:520px; margin:2rem auto 0; box-shadow:0 4px 20px rgba(0,0,0,0.25); border-radius:8px; overflow:hidden;">
            <input type="text" name="q" placeholder="Search artifacts, artworks, exhibitions…"
                   style="flex:1; padding:0.9rem 1.25rem; border:none; font-size:1rem;
                          background:rgba(255,255,255,0.96); color:#1C1917; outline:none;">
            <button type="submit"
                    style="padding:0.9rem 1.5rem; background:var(--secondary-color);
                           border:none; color:#fff; font-weight:700; font-size:1rem; cursor:pointer;">
                Search
            </button>
        </form>
        <div style="margin-top: 1.75rem;">
            <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'Admin'): ?>
                <a href="pages/exhibitions.php" class="btn btn-primary" style="margin-right:12px;">Book Tickets</a>
                <a href="pages/feedback.php" class="btn btn-outline" style="margin-right:12px;">Leave Feedback</a>
                <a href="pages/donate.php" class="btn btn-outline">Donate</a>
            <?php else: ?>
                <a href="pages/exhibitions.php" class="btn btn-primary" style="margin-right:12px;">Explore Exhibitions</a>
                <a href="pages/artifacts.php" class="btn btn-outline">Browse Artifacts</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="section" id="exhibitions">
        <h2 class="section-title">Current Exhibitions</h2>
        <div class="grid">
            <div class="card">
                <img src="https://images.unsplash.com/photo-1518998053401-878c73fd616e?auto=format&fit=crop&w=600&q=80" alt="Ancient Rome" class="card-img">
                <div class="card-content">
                    <div class="card-category">Historical Wing • Until Dec 2026</div>
                    <h3 class="card-title">The Fall of Rome</h3>
                    <p class="card-text">Explore over 200 artifacts from the late Roman Empire, including newly discovered statues and everyday items from the 4th century.</p>
                </div>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1544640808-32cb4f6864c7?auto=format&fit=crop&w=600&q=80" alt="Modern Art" class="card-img">
                <div class="card-content">
                    <div class="card-category">Contemporary Wing • Permanent</div>
                    <h3 class="card-title">Modern Expressions</h3>
                    <p class="card-text">A permanent collection tracking the evolution of modern art. <a href="pages/gallery.php" style="color: var(--secondary-color); text-decoration: none; font-weight: 600; margin-top: 10px; display: inline-block;">View Gallery &rarr;</a></p>
                </div>
            </div>
            <div class="card">
                <img src="https://images.unsplash.com/photo-1566127444979-b3d2b654e3d7?auto=format&fit=crop&w=600&q=80" alt="Space Exploration" class="card-img">
                <div class="card-content">
                    <div class="card-category">Science Hall • Opening Soon</div>
                    <h3 class="card-title">Beyond Earth</h3>
                    <p class="card-text">Discover the history of space exploration. This exhibition features actual spacesuits, rover prototypes, and lunar samples.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" style="background-color: var(--surface);">
        <h2 class="section-title">Plan Your Visit</h2>
        <div class="grid">
            <div style="background: var(--background); padding: 2.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                <h3 style="margin-bottom: 1rem; font-size: 1.25rem;">Hours of Operation</h3>
                <p style="color: var(--text-light); margin-bottom: 0.5rem;"><strong>Mon - Fri:</strong> 10:00 AM – 6:00 PM</p>
                <p style="color: var(--text-light); margin-bottom: 1rem;"><strong>Sat - Sun:</strong> 9:00 AM – 8:00 PM</p>
                <p style="font-size: 0.85rem; color: var(--secondary-color); font-weight: 600;">* Last entry 1 hour before closing</p>
            </div>
            
            <div style="background: var(--background); padding: 2.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                <h3 style="margin-bottom: 1rem; font-size: 1.25rem;">Location</h3>
                <p style="color: var(--text-light);">123 Heritage Avenue<br>Arts District, NY 10001</p>
                <a href="#" style="display: inline-block; margin-top: 1.5rem; color: var(--primary-color); font-weight: 700; text-decoration: none; border-bottom: 2px solid var(--secondary-color);">Get Directions</a>
            </div>
            
            <div style="background: var(--background); padding: 2.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                <h3 style="margin-bottom: 1rem; font-size: 1.25rem;">Ticketing System</h3>
                <p style="color: var(--text-light);">Our ticketing system is fully integrated with our central database, ensuring real-time capacity management and secure booking records for all visitors.</p>
            </div>
        </div>
    </section>

    <footer>
        <h2>MUSEOX</h2>
        <p style="margin-top: 10px; margin-bottom: 20px;">Preserving History through Modern Technology</p>
        <p>&copy; 2026 MuseoX. Developed by Torikul.</p>
    </footer>

</body>
</html>