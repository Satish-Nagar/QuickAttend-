<?php
require_once '../includes/functions.php';
requireAdmin();

$admin_id = $_SESSION['user_id'];
$db = getDB();
$stmt = $db->prepare("SELECT profile_picture FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
$profile_picture = $admin && $admin['profile_picture']
    ? '../uploads/' . htmlspecialchars($admin['profile_picture'])
    : 'https://via.placeholder.com/40x40.png?text=Admin';

// Handle actions
if (isset($_POST['action'])) {
    $om_id = (int)$_POST['om_id'];
    
    switch ($_POST['action']) {
        case 'delete':
            $stmt = $db->prepare("DELETE FROM operation_managers WHERE id = ?");
            $stmt->execute([$om_id]);
            setFlashMessage('success', 'Operation Manager deleted successfully');
            break;
            
        case 'toggle_status':
            $stmt = $db->prepare("UPDATE operation_managers SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$om_id]);
            setFlashMessage('success', 'Operation Manager status updated successfully');
            break;
    }
    
    redirect('manage_oms.php');
}

// Get all OMs with their statistics
$stmt = $db->query("SELECT om.*, 
                           COUNT(DISTINCT s.id) as section_count,
                           COUNT(DISTINCT st.id) as student_count,
                           COUNT(DISTINCT a.id) as attendance_count
                    FROM operation_managers om
                    LEFT JOIN sections s ON om.id = s.om_id
                    LEFT JOIN students st ON s.id = st.section_id
                    LEFT JOIN attendance a ON s.id = a.section_id
                    GROUP BY om.id
                    ORDER BY om.created_at DESC");
$oms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage OMs - Smart Attendance System</title>
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
        .om-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .om-card:hover {
            transform: translateY(-3px);
        }
        .om-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        .om-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .om-stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            min-width: 80px;
        }
        .om-actions {
            display: flex;
            gap: 10px;
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
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
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
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link<?php if ($current_page == 'register_om.php') echo ' active'; ?>" href="register_om.php">
                            <i class="fas fa-user-plus"></i> Register OM
                        </a>
                        <a class="nav-link<?php if ($current_page == 'bulk_register.php') echo ' active'; ?>" href="bulk_register.php">
                            <i class="fas fa-upload"></i> Bulk Register
                        </a>
                        <a class="nav-link<?php if ($current_page == 'manage_oms.php') echo ' active'; ?>" href="manage_oms.php">
                            <i class="fas fa-users-cog"></i> Manage OMs
                        </a>
                        <a class="nav-link<?php if ($current_page == 'attendance_report.php') echo ' active'; ?>" href="attendance_report.php">
                            <i class="fas fa-chart-bar"></i> Attendance Report
                        </a>
                        <a class="nav-link<?php if ($current_page == 'assignment_report.php') echo ' active'; ?>" href="assignment_report.php">
                            <i class="fas fa-chart-bar"></i> Assignment Report
                        </a>
                        <a class="nav-link<?php if ($current_page == 'settings.php') echo ' active'; ?>" href="settings.php">
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

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>
                            <i class="fas fa-users-cog"></i> Manage Operation Managers
                        </h2>
                        <a href="register_om.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add New OM
                        </a>
                    </div>

                    <?php if (empty($oms)): ?>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle"></i> No Operation Managers registered yet.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($oms as $om): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="om-card">
                                        <div class="om-header">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($om['name']); ?></h5>
                                                <small class="text-muted"><?php echo htmlspecialchars($om['designation']); ?></small>
                                            </div>
                                            <?php if ($om['is_active']): ?>
                                                <span class="badge bg-success status-badge">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger status-badge">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($om['email']); ?></div>
                                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($om['contact']); ?></div>
                                            <div><i class="fas fa-university"></i> <?php echo htmlspecialchars($om['college']); ?></div>
                                        </div>
                                        
                                        <div class="om-stats">
                                            <div class="om-stat">
                                                <div class="fw-bold text-primary"><?php echo $om['section_count']; ?></div>
                                                <small class="text-muted">Sections</small>
                                            </div>
                                            <div class="om-stat">
                                                <div class="fw-bold text-success"><?php echo $om['student_count']; ?></div>
                                                <small class="text-muted">Students</small>
                                            </div>
                                            <div class="om-stat">
                                                <div class="fw-bold text-info"><?php echo $om['attendance_count']; ?></div>
                                                <small class="text-muted">Records</small>
                                            </div>
                                        </div>
                                        
                                        <div class="om-actions">
                                            <a href="edit_om.php?id=<?php echo $om['id']; ?>" 
                                               class="btn btn-info btn-action">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="om_id" value="<?php echo $om['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <button type="submit" class="btn btn-warning btn-action" 
                                                        onclick="return confirm('Are you sure you want to change the status?')">
                                                    <i class="fas fa-toggle-on"></i> 
                                                    <?php echo $om['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="om_id" value="<?php echo $om['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-danger btn-action" 
                                                        onclick="return confirm('Are you sure you want to delete this OM? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                Registered: <?php echo formatDate($om['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 