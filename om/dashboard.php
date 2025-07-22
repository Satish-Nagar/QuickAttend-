<?php
require_once '../includes/functions.php';
requireOM();

$db = getDB();
$om_id = $_SESSION['user_id'];
// Fetch OM profile picture
$stmt = $db->prepare("SELECT profile_picture FROM operation_managers WHERE id = ?");
$stmt->execute([$om_id]);
$om_profile = $stmt->fetch();
$profile_picture = $om_profile && $om_profile['profile_picture'] ? '../uploads/' . htmlspecialchars($om_profile['profile_picture']) : 'https://via.placeholder.com/40x40.png?text=OM';

// Get OM's sections
$stmt = $db->prepare("SELECT s.*, 
                             (SELECT COUNT(*) FROM students WHERE section_id = s.id) as student_count,
                             (SELECT COUNT(*) FROM attendance WHERE section_id = s.id) as attendance_count
                      FROM sections s 
                      WHERE s.om_id = ? 
                      ORDER BY s.created_at DESC");
$stmt->execute([$om_id]);
$sections = $stmt->fetchAll();

// Get recent attendance records
$stmt = $db->prepare("SELECT a.*, s.name as section_name 
                      FROM attendance a 
                      JOIN sections s ON a.section_id = s.id 
                      WHERE s.om_id = ?     
                      ORDER BY a.date DESC 
                      LIMIT 5");
$stmt->execute([$om_id]);
$recent_attendance = $stmt->fetchAll();

// Get total statistics
$stmt = $db->prepare("SELECT 
                        COUNT(DISTINCT s.id) as total_sections,
                        COUNT(DISTINCT st.id) as total_students,
                        COUNT(DISTINCT a.id) as total_attendance
                      FROM sections s 
                      LEFT JOIN students st ON s.id = st.section_id 
                      LEFT JOIN attendance a ON s.id = a.section_id 
                      WHERE s.om_id = ?");
$stmt->execute([$om_id]);
$stats = $stmt->fetch();

// Add this at the top after session start and OM check
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OM Dashboard - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #28a745, #20c997);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            min-height: calc(100vh - 76px);
            padding: 20px 0 20px 0;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            min-height: calc(100vh - 76px);
            justify-content: center;
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        .section-card:hover {
            transform: translateY(-3px);
        }
        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        .section-stats {
            display: flex;
            gap: 15px;
        }
        .section-stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            min-width: 80px;
        }
        .section-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
        }
        /* Header Branding Bar (desktop only) */
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
        @media (max-width: 991.98px) {
            .om-header-bar { display: none; }
        }
        .header-profile img { box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
        .header-profile .dropdown-toggle { color: #fff !important; }
        .header-profile .dropdown-menu { min-width: 160px; }
    </style>
</head>
<body>
    <!-- Header Branding Bar (desktop only) -->
    <div class="om-header-bar d-none d-lg-flex justify-content-between align-items-center">
        <div class="header-title">
        <img src="images\download.png" alt="Logo" style="height:40px;width:auto;margin-right:10px; border-radius:25%;"> 
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
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link<?php if ($current_page == 'add_section.php') echo ' active'; ?>" href="add_section.php">
                            <i class="fas fa-plus"></i> Add Section
                        </a>
                        <a class="nav-link<?php if ($current_page == 'sections.php') echo ' active'; ?>" href="sections.php">
                            <i class="fas fa-layer-group"></i> My Sections
                        </a>
                        <a class="nav-link<?php if ($current_page == 'attendance_history.php') echo ' active'; ?>" href="attendance_history.php">
                            <i class="fas fa-history"></i> Attendance History
                        </a>
                        <a class="nav-link<?php if ($current_page == 'assignment_history.php') echo ' active'; ?>" href="assignment_history.php">
                            <i class="fas fa-book"></i> Assignments History
                        </a>
                        <a class="nav-link<?php if ($current_page == 'profile.php') echo ' active'; ?>" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Flash Messages -->
                    <?php $flash = getFlashMessage(); ?>
                    <?php if ($flash): ?>
                        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $flash['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <h2 class="mb-4">
                        <i class="fas fa-tachometer-alt"></i> Welcome, <?php echo htmlspecialchars($_SESSION['om_name']); ?>!
                    </h2>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <div class="stat-number text-primary"><?php echo $stats['total_sections']; ?></div>
                                <div class="text-muted">Total Sections</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-success">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="stat-number text-success"><?php echo $stats['total_students']; ?></div>
                                <div class="text-muted">Total Students</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-info">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="stat-number text-info"><?php echo $stats['total_attendance']; ?></div>
                                <div class="text-muted">Attendance Records</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt"></i> Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <a href="add_section.php" class="btn btn-primary w-100 mb-2">
                                                <i class="fas fa-plus"></i> Add New Section
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="sections.php" class="btn btn-success w-100 mb-2">
                                                <i class="fas fa-layer-group"></i> Manage Sections
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="attendance_history.php" class="btn btn-info w-100 mb-2">
                                                <i class="fas fa-history"></i> View History
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="profile.php" class="btn btn-warning w-100 mb-2">
                                                <i class="fas fa-user"></i> Update Profile
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sections Overview -->
                    <div class="row">
                        <div class="col-12">
                            <h4 class="mb-3">
                                <i class="fas fa-layer-group"></i> My Sections
                            </h4>
                            
                            <?php if (empty($sections)): ?>
                                <div class="alert alert-info" role="alert">
                                    <i class="fas fa-info-circle"></i> You haven't created any sections yet. 
                                    <a href="add_section.php" class="alert-link">Create your first section</a> to get started.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($sections as $section): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="section-card">
                                                <div class="section-header">
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($section['name']); ?></h5>
                                                    <span class="badge bg-primary"><?php echo $section['student_count']; ?> students</span>
                                                </div>
                                                
                                                <?php if ($section['description']): ?>
                                                    <p class="text-muted mb-3"><?php echo htmlspecialchars($section['description']); ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="section-stats">
                                                    <div class="section-stat">
                                                        <div class="fw-bold"><?php echo $section['student_count']; ?></div>
                                                        <small class="text-muted">Students</small>
                                                    </div>
                                                    <div class="section-stat">
                                                        <div class="fw-bold"><?php echo $section['attendance_count']; ?></div>
                                                        <small class="text-muted">Records</small>
                                                    </div>
                                                </div>
                                                
                                                <div class="section-actions">
                                                <a href="view_section.php?id=<?php echo $section['id']; ?>" 
                                                       class="btn btn-info btn-action">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="mark_attendance.php?section_id=<?php echo $section['id']; ?>" 
                                                       class="btn btn-success btn-action">
                                                        <i class="fas fa-check"></i> Mark Attendance
                                                    </a>
                                                   
                                                    <a href="mark_assignment.php?section_id=<?php echo $section['id']; ?>" 
                                                       class="btn btn-warning btn-action">
                                                        <i class="fas fa-book"></i> Mark Assignment
                                                    </a>
                                                    <!-- <a href="assignment_history.php?section_id=<?php echo $section['id']; ?>" 
                                                       class="btn btn-secondary btn-action">
                                                        <i class="fas fa-list"></i> Assignment History
                                                    </a> -->
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    

                    <!-- Recent Attendance -->
                    <?php if (!empty($recent_attendance)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-history"></i> Recent Attendance Records
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
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
                                                    <?php foreach ($recent_attendance as $record): ?>
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-5 mb-3 text-muted small">
        &lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Assignment Modal HTML -->
    <script src="assignment_modal.js"></script>
    <script>
    // The openAssignmentModal function is no longer needed as assignments are now direct links.
    // Keeping the script block structure as it was in the original file.
    </script>
</body>
</html> 