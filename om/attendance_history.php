<?php
require_once '../includes/functions.php';
requireOM();

$db = getDB();
$om_id = $_SESSION['user_id'];

// Fetch OM profile picture for header
$stmt = $db->prepare("SELECT profile_picture FROM operation_managers WHERE id = ?");
$stmt->execute([$om_id]);
$om_profile = $stmt->fetch();
$profile_picture = $om_profile && $om_profile['profile_picture'] ? '../uploads/' . htmlspecialchars($om_profile['profile_picture']) : 'https://via.placeholder.com/40x40.png?text=OM';

// Fetch all sections for this OM
$stmt = $db->prepare("SELECT * FROM sections WHERE om_id = ? ORDER BY created_at DESC");
$stmt->execute([$om_id]);
$sections = $stmt->fetchAll();
$section_ids = array_column($sections, 'id');

// Fetch all attendance records for OM's sections
$attendance = [];
if ($section_ids) {
    $in = str_repeat('?,', count($section_ids) - 1) . '?';
    $stmt = $db->prepare("SELECT a.*, s.name as section_name FROM attendance a JOIN sections s ON a.section_id = s.id WHERE a.section_id IN ($in) ORDER BY a.date DESC");
    $stmt->execute($section_ids);
    $attendance = $stmt->fetchAll();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .om-header-bar {
            width: 100%;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 16px 0 12px 0;
            margin-bottom: 0;
        }
        .om-header-bar .header-title {
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            font-size: 1.5rem;
            margin-left: 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-profile img { box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
        .header-profile .dropdown-toggle { color: #fff !important; }
        .header-profile .dropdown-menu { min-width: 160px; }
        @media (max-width: 991.98px) {
            .om-header-bar { display: none; }
        }
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            min-height: calc(100vh - 76px);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
        .main-content {
            padding: 30px;
        }
        @media (max-width: 991.98px) {
            .sidebar { min-height: auto; }
        }
    </style>
</head>
<body>
    <!-- Header Branding Bar (desktop only) -->
    <div class="om-header-bar d-none d-lg-flex justify-content-between align-items-center">
        <div class="header-title">
        <img src="images/download.png" alt="Logo" style="height:40px;width:auto;margin-right:10px; border-radius:25%;"> 
        Smart Attendance System
        </div>
        <div class="header-profile me-4 position-relative">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?php echo $profile_picture; ?>" alt="Profile" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;border:2px solid #fff;">
                    <span class="ms-2 fw-semibold"><?php echo htmlspecialchars($_SESSION['om_name'] ?? 'OM'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>" href="dashboard.php">
                            <i class="fa-solid fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link<?php if ($current_page == 'add_section.php') echo ' active'; ?>" href="add_section.php">
                            <i class="fa-solid fa-plus"></i> Add Section
                        </a>
                        <a class="nav-link<?php if ($current_page == 'sections.php') echo ' active'; ?>" href="sections.php">
                            <i class="fa-solid fa-layer-group"></i> My Sections
                        </a>
                        <a class="nav-link<?php if ($current_page == 'attendance_history.php') echo ' active'; ?>" href="attendance_history.php">
                            <i class="fa-solid fa-clock-rotate-left"></i> Attendance History
                        </a>
                        <a class="nav-link<?php if ($current_page == 'assignment_history.php') echo ' active'; ?>" href="assignment_history.php">
                            <i class="fa-solid fa-book"></i> Assignments History
                        </a>
                        <a class="nav-link<?php if ($current_page == 'profile.php') echo ' active'; ?>" href="profile.php">
                            <i class="fa-solid fa-user"></i> Profile
                        </a>
                    </nav>
                </div>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
<div class="container mt-5">
    <h2>Attendance History</h2>
    <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
    <div class="card">
        <div class="card-header">All Attendance Records</div>
        <div class="card-body">
            <?php if (empty($attendance)): ?>
                <p>No attendance records found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Date</th>
                                <th>Present</th>
                                <th>Total</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['section_name']); ?></td>
                                <td><?php echo formatDate($record['date']); ?></td>
                                <td><?php echo $record['present_count']; ?></td>
                                <td><?php echo $record['total_count']; ?></td>
                                <td>
                                    <?php 
                                    $percentage = $record['total_count'] > 0 ? 
                                        round(($record['present_count'] / $record['total_count']) * 100, 1) : 0;
                                    echo $percentage . '%';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
 