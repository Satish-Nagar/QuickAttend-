<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/functions.php';
requireOM();

$db = getDB();
$om_id = $_SESSION['user_id'];
// Fetch OM profile picture
$stmt = $db->prepare("SELECT profile_picture FROM operation_managers WHERE id = ?");
$stmt->execute([$om_id]);
$om_profile = $stmt->fetch();
$profile_picture = $om_profile && $om_profile['profile_picture'] ? '../uploads/' . htmlspecialchars($om_profile['profile_picture']) : 'https://via.placeholder.com/40x40.png?text=OM';

// Handle delete section
if (isset($_POST['delete_section_id'])) {
    $section_id = (int)$_POST['delete_section_id'];
    $stmt = $db->prepare("DELETE FROM sections WHERE id = ? AND om_id = ?");
    if ($stmt->execute([$section_id, $om_id])) {
        $success = 'Section deleted successfully!';
    } else {
        $error = 'Failed to delete section.';
    }
}

// Handle new section submission
$success = $success ?? '';
$error = $error ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    if ($name) {
        $stmt = $db->prepare("INSERT INTO sections (om_id, name, description) VALUES (?, ?, ?)");
        if ($stmt->execute([$om_id, $name, $description])) {
            $success = 'Section added successfully!';
        } else {
            $error = 'Failed to add section.';
        }
    } else {
        $error = 'Section name is required.';
    }
}

// Fetch all sections for this OM
$stmt = $db->prepare("SELECT * FROM sections WHERE om_id = ? ORDER BY created_at DESC");
$stmt->execute([$om_id]);
$sections = $stmt->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sections - Smart Attendance System</title>
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
        .section-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(40,167,69,0.07);
            margin-bottom: 32px;
            padding: 28px 24px 20px 24px;
            transition: box-shadow 0.2s, transform 0.2s;
            min-height: 210px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .section-card:hover {
            box-shadow: 0 8px 32px rgba(32,201,151,0.13);
            transform: translateY(-3px) scale(1.01);
        }
        .section-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 6px;
        }
        .section-desc {
            color: #555;
            margin-bottom: 10px;
            font-size: 1.05rem;
        }
        .section-date {
            font-size: 0.97rem;
            color: #888;
            margin-bottom: 18px;
        }
        .section-actions {
            margin-top: 10px;
            display: flex;
            gap: 12px;
        }
        .btn-delete {
            background: #ff4d4f;
            color: #fff;
            border: none;
        }
        .btn-delete:hover {
            background: #d9363e;
            color: #fff;
        }
        .btn-view {
            background: #00b8d9;
            color: #fff;
            border: none;
        }
        .btn-view:hover {
            background: #0097b2;
            color: #fff;
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
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">My Sections</h2>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Back to Dashboard</a>
                    </div>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <div class="row">
                        <?php if (count($sections) === 0): ?>
                            <div class="col-12">
                                <div class="alert alert-info">No sections found.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($sections as $section): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="section-card">
                                        <div>
                                            <div class="section-title"><?php echo htmlspecialchars($section['name']); ?></div>
                                            <div class="section-desc"><?php echo htmlspecialchars($section['description']); ?></div>
                                            <div class="section-date"><i class="far fa-calendar-alt"></i> Created: <?php echo htmlspecialchars($section['created_at']); ?></div>
                                        </div>
                                        <div class="section-actions">
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this section?');" style="display:inline;">
                                                <input type="hidden" name="delete_section_id" value="<?php echo $section['id']; ?>">
                                                <button type="submit" class="btn btn-delete btn-sm"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                            <a href="view_section.php?id=<?php echo $section['id']; ?>" class="btn btn-view btn-sm"><i class="fas fa-users"></i> View Students</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 