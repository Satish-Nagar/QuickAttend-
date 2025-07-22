<?php
// OM Navbar - include this at the top of OM panel pages
?>
<style>
/* Fixed navbar styles */
.om-navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1050;
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: background 0.3s;
}
.om-navbar .navbar-brand {
    font-weight: 700;
    letter-spacing: 1px;
    color: #fff !important;
}
.om-navbar .nav-link {
    color: #fff !important;
    font-weight: 500;
    margin-right: 10px;
    transition: color 0.2s;
}
.om-navbar .nav-link.active, .om-navbar .nav-link:hover {
    color: #ffd700 !important;
}
.om-navbar .navbar-toggler {
    border: none;
    color: #fff;
    font-size: 1.5rem;
}
.om-navbar .navbar-toggler:focus {
    outline: none;
    box-shadow: none;
}
/* Mobile menu overlay */
.om-mobile-menu {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(40, 167, 69, 0.97);
    z-index: 2000;
    display: none;
    flex-direction: column;
    align-items: flex-start;
    padding: 32px 24px 24px 24px;
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
}
.om-mobile-menu.open {
    display: flex;
    animation: omMenuSlideIn 0.3s;
}
@keyframes omMenuSlideIn {
    from { transform: translateY(-40px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.om-mobile-menu .close-menu {
    font-size: 2rem;
    color: #fff;
    position: absolute;
    top: 18px;
    right: 24px;
    cursor: pointer;
    transition: color 0.2s;
}
.om-mobile-menu .close-menu:hover {
    color: #ffd700;
}
.om-mobile-menu .nav-link {
    font-size: 1.2rem;
    margin: 12px 0;
    color: #fff !important;
}
@media (max-width: 991.98px) {
    .om-navbar .navbar-collapse {
        display: none !important;
    }
}
@media (min-width: 992px) {
    .om-mobile-menu {
        display: none !important;
    }
}
/* Add top margin to body for fixed navbar */
body { margin-top: 64px !important; }
@media (max-width: 991.98px) {
    body { margin-top: 56px !important; }
}
</style>
<nav class="navbar om-navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="images\gog transparent-01.png" alt="Logo" style="height:36px;width:auto;margin-right:10px;"> 
            <span><i class="fas fa-clipboard-check"></i> Smart Attendance System</span>
        </a>
        <button class="navbar-toggler" type="button" aria-label="Toggle navigation" onclick="document.getElementById('omMobileMenu').classList.add('open')">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="sections.php"><i class="fas fa-layer-group"></i> My Sections</a></li>
                <li class="nav-item"><a class="nav-link" href="attendance_history.php"><i class="fas fa-history"></i> Attendance History</a></li>
                <li class="nav-item"><a class="nav-link" href="mark_assignment.php"><i class="fas fa-book"></i> Mark Assignment</a></li>
                <li class="nav-item"><a class="nav-link" href="assignment_history.php"><i class="fas fa-list"></i> Assignment History</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="omProfileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['om_name'] ?? 'OM'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="omProfileDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- Mobile menu overlay -->
<div class="om-mobile-menu" id="omMobileMenu">
    <span class="close-menu" onclick="document.getElementById('omMobileMenu').classList.remove('open')">&times;</span>
    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a class="nav-link" href="sections.php"><i class="fas fa-layer-group"></i> My Sections</a>
    <a class="nav-link" href="attendance_history.php"><i class="fas fa-history"></i> Attendance History</a>
    <a class="nav-link" href="mark_assignment.php"><i class="fas fa-book"></i> Mark Assignment</a>
    <a class="nav-link" href="assignment_history.php"><i class="fas fa-list"></i> Assignment History</a>
    <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<script>
// Close mobile menu on outside click
window.addEventListener('click', function(e) {
    var menu = document.getElementById('omMobileMenu');
    if (menu.classList.contains('open') && !menu.contains(e.target) && !e.target.classList.contains('navbar-toggler')) {
        menu.classList.remove('open');
    }
});
// Smooth transition for menu (already handled by CSS)
</script> 