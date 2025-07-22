<?php
require_once '../includes/functions.php';
requireAdmin();

$db = getDB();

// Get statistics
$stmt = $db->query("SELECT COUNT(*) as total_oms FROM operation_managers WHERE is_active = 1");
$total_oms = $stmt->fetch()['total_oms'];

$stmt = $db->query("SELECT COUNT(*) as total_sections FROM sections");
$total_sections = $stmt->fetch()['total_sections'];

$stmt = $db->query("SELECT COUNT(*) as total_students FROM students");
$total_students = $stmt->fetch()['total_students'];

$stmt = $db->query("SELECT COUNT(*) as total_attendance FROM attendance");
$total_attendance = $stmt->fetch()['total_attendance'];

// Get recent OMs
$stmt = $db->query("SELECT * FROM operation_managers ORDER BY created_at DESC LIMIT 5");
$recent_oms = $stmt->fetchAll();

$admin_id = $_SESSION['user_id'];
// Fetch admin profile picture
$stmt = $db->prepare("SELECT profile_picture FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin_profile = $stmt->fetch();
$profile_picture = $admin_profile && $admin_profile['profile_picture'] ? '../uploads/' . htmlspecialchars($admin_profile['profile_picture']) : 'https://via.placeholder.com/40x40.png?text=Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #667eea, #764ba2);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(45deg, #667eea, #764ba2);
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
        .recent-oms {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #667eea;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-clipboard-check"></i> Smart Attendance System
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown d-flex align-items-center">
                    <img src="<?php echo $profile_picture; ?>" alt="Profile" class="rounded-circle me-2" style="width:36px;height:36px;object-fit:cover;">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield"></i> Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link" href="register_om.php">
                            <i class="fas fa-user-plus"></i> Register OM
                        </a>
                        <a class="nav-link" href="bulk_register.php">
                            <i class="fas fa-upload"></i> Bulk Register
                        </a>
                        <a class="nav-link" href="manage_oms.php">
                            <i class="fas fa-users-cog"></i> Manage OMs
                        </a>
                        <a class="nav-link" href="attendance_report.php">
                            <i class="fas fa-chart-bar"></i> Attendance Report
                        </a>
                        <a class="nav-link" href="assignment_report.php">
                            <i class="fas fa-chart-bar"></i> Assignment Report
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
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
                        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                    </h2>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-number text-primary"><?php echo $total_oms; ?></div>
                                <div class="text-muted">Active OMs</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-success">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <div class="stat-number text-success"><?php echo $total_sections; ?></div>
                                <div class="text-muted">Total Sections</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-info">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="stat-number text-info"><?php echo $total_students; ?></div>
                                <div class="text-muted">Total Students</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card text-center">
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="stat-number text-warning"><?php echo $total_attendance; ?></div>
                                <div class="text-muted">Attendance Records</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col">
                            <button class="btn btn-primary w-100" onclick="location.href='register_om.php'">Register New OM</button>
                        </div>
                        <div class="col">
                            <button class="btn btn-success w-100" onclick="location.href='bulk_register.php'">Bulk Register OMs</button>
                        </div>
                        <div class="col">
                            <button class="btn btn-info w-100" onclick="location.href='manage_oms.php'">Manage OMs</button>
                        </div>
                        <div class="col">
                            <button class="btn btn-warning w-100" onclick="location.href='attendance_report.php'"><i class="fas fa-chart-bar"></i> Attendance Report</button>
                        </div>
                        <div class="col">
                            <button class="btn btn-secondary w-100" onclick="location.href='assignment_report.php'"><i class="fas fa-chart-bar"></i> Assignment Report</button>
                        </div>
                    </div>

                    <!-- Recent OMs -->
                    <div class="row">
                        <div class="col-12">
                            <div class="recent-oms">
                                <h5 class="mb-3">
                                    <i class="fas fa-clock"></i> Recently Registered OMs
                                </h5>
                                <?php if (empty($recent_oms)): ?>
                                    <p class="text-muted">No OMs registered yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>College</th>
                                                    <th>Status</th>
                                                    <th>Registered</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_oms as $om): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($om['name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($om['designation']); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($om['email']); ?></td>
                                                        <td><?php echo htmlspecialchars($om['college']); ?></td>
                                                        <td>
                                                            <?php if ($om['is_active']): ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Inactive</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo formatDate($om['created_at']); ?></td>
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

    <footer class="text-center mt-5 mb-3 text-muted small">
        &lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 