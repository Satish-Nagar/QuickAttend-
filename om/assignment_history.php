<?php
require_once '../includes/functions.php';
requireOM();

$om_id = $_SESSION['user_id'];
$db = getDB();

// Fetch OM profile picture for header
$stmt = $db->prepare("SELECT profile_picture FROM operation_managers WHERE id = ?");
$stmt->execute([$om_id]);
$om_profile = $stmt->fetch();
$profile_picture = $om_profile && $om_profile['profile_picture'] ? '../uploads/' . htmlspecialchars($om_profile['profile_picture']) : 'https://via.placeholder.com/40x40.png?text=OM';

// Get OM's sections
$stmt = $db->prepare("SELECT * FROM sections WHERE om_id = ? ORDER BY name");
$stmt->execute([$om_id]);
$sections = $stmt->fetchAll();

$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : ($sections[0]['id'] ?? 0);
$assignments = [];
$details = [];
$selected_assignment = null;

if ($section_id) {
    // Get assignments summary for this section
    $stmt = $db->prepare("SELECT assignment_name, assignment_date, COUNT(*) as total, SUM(status) as attempted, AVG(score) as avg_score FROM assignments WHERE section_id = ? GROUP BY assignment_name, assignment_date ORDER BY assignment_date DESC, assignment_name");
    $stmt->execute([$section_id]);
    $assignments = $stmt->fetchAll();

    // If view details requested
    if (isset($_GET['assignment_name']) && isset($_GET['assignment_date'])) {
        $assignment_name = sanitizeInput($_GET['assignment_name']);
        $assignment_date = sanitizeInput($_GET['assignment_date']);
        $selected_assignment = [
            'name' => $assignment_name,
            'date' => $assignment_date
        ];
        $stmt = $db->prepare("SELECT a.*, s.roll_no, s.name FROM assignments a JOIN students s ON a.student_id = s.id WHERE a.section_id = ? AND a.assignment_name = ? AND a.assignment_date = ? ORDER BY s.name");
        $stmt->execute([$section_id, $assignment_name, $assignment_date]);
        $details = $stmt->fetchAll();
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment History</title>
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
        .history-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .details-table {
            background: #fff;
            border-radius: 10px;
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
                    <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
                    <div class="history-card">
                        <h3 class="mb-4"><i class="fas fa-list"></i> Assignment History</h3>
                        <form method="get" class="mb-4">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label for="section_id" class="form-label">Select Section</label>
                                    <select class="form-select" id="section_id" name="section_id" onchange="this.form.submit()">
                                        <?php foreach ($sections as $sec): ?>
                                            <option value="<?php echo $sec['id']; ?>" <?php if ($section_id == $sec['id']) echo 'selected'; ?>><?php echo htmlspecialchars($sec['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                        <?php if ($section_id && !empty($assignments)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Assignment Name</th>
                                            <th>Date</th>
                                            <th>Attempted</th>
                                            <th>Avg Score</th>
                                            <th>View Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $a): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($a['assignment_name']); ?></td>
                                                <td><?php echo htmlspecialchars($a['assignment_date']); ?></td>
                                                <td><?php echo $a['attempted']; ?> / <?php echo $a['total']; ?></td>
                                                <td><?php echo round($a['avg_score'], 2); ?></td>
                                                <td>
                                                    <a href="assignment_history.php?section_id=<?php echo $section_id; ?>&assignment_name=<?php echo urlencode($a['assignment_name']); ?>&assignment_date=<?php echo urlencode($a['assignment_date']); ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif ($section_id): ?>
                            <div class="alert alert-warning mt-4">No assignments found for this section.</div>
                        <?php endif; ?>
                        <?php if ($selected_assignment && !empty($details)): ?>
                            <div class="mt-5">
                                <h5><i class="fas fa-eye"></i> Assignment Details: <?php echo htmlspecialchars($selected_assignment['name']); ?> (<?php echo htmlspecialchars($selected_assignment['date']); ?>)</h5>
                                <div class="table-responsive details-table">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>Roll No</th>
                                                <th>Name</th>
                                                <th>Score</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($details as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['roll_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                    <td><?php echo $row['score']; ?></td>
                                                    <td><?php echo $row['status']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-info me-2" onclick="copyScores()"><i class="fas fa-copy"></i> Copy Scores</button>
                                    <button class="btn btn-info" onclick="copyStatuses()"><i class="fas fa-copy"></i> Copy Statuses</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="text-center mt-5 mb-3 text-muted small">&lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyScores() {
        var scores = [];
        <?php if (!empty($details)): ?>
            <?php foreach ($details as $row): ?>
                scores.push('<?php echo $row['score']; ?>');
            <?php endforeach; ?>
        <?php endif; ?>
        if (scores.length) {
            navigator.clipboard.writeText(scores.join(",")).then(function() {
                alert('Scores copied!');
            });
        }
    }
    function copyStatuses() {
        var statuses = [];
        <?php if (!empty($details)): ?>
            <?php foreach ($details as $row): ?>
                statuses.push('<?php echo $row['status']; ?>');
            <?php endforeach; ?>
        <?php endif; ?>
        if (statuses.length) {
            navigator.clipboard.writeText(statuses.join(",")).then(function() {
                alert('Statuses copied!');
            });
        }
    }
    </script>
</body>
</html> 