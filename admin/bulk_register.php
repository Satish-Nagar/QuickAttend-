<?php
require_once '../includes/functions.php';
requireAdmin();
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendSMTPMail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'satish@geeksofgurkul.com';
        $mail->Password = 'hiaa oshx vooq ands';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('satish@geeksofgurkul.com', 'Smart Attendance System');
        $mail->addAddress($to);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$admin_id = $_SESSION['user_id'];
$db = getDB();
$stmt = $db->prepare("SELECT profile_picture FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
$profile_picture = $admin && $admin['profile_picture']
    ? '../uploads/' . htmlspecialchars($admin['profile_picture'])
    : 'https://via.placeholder.com/40x40.png?text=Admin';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['oms_file']) && $_FILES['oms_file']['tmp_name']) {
    $file = $_FILES['oms_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $oms = [];
    try {
        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($headers, $row);
                if (!empty($data['name']) && !empty($data['designation']) && !empty($data['email']) && !empty($data['contact']) && !empty($data['college']) && !empty($data['password'])) {
                    $oms[] = $data;
                }
            }
            fclose($handle);
        } elseif (in_array($ext, ['xls', 'xlsx'])) {
            $spreadsheet = IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            $headers = array_map('strtolower', array_map('trim', $rows[1]));
            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i];
                $data = array_combine($headers, $row);
                if (!empty($data['name']) && !empty($data['designation']) && !empty($data['email']) && !empty($data['contact']) && !empty($data['college']) && !empty($data['password'])) {
                    $oms[] = $data;
                }
            }
        } else {
            $error = 'Invalid file type. Only CSV, XLS, XLSX allowed.';
        }
                    } catch (Exception $e) {
        $error = 'Error reading file: ' . $e->getMessage();
    }
    // Insert OMs
    if ($oms && !$error) {
        $stmt = $db->prepare("INSERT INTO operation_managers (name, designation, email, contact, college, password) VALUES (?, ?, ?, ?, ?, ?)");
        $count = 0;
        $emails_sent = 0;
        foreach ($oms as $om) {
            $hashed = password_hash($om['password'], PASSWORD_DEFAULT);
            if ($stmt->execute([$om['name'], $om['designation'], $om['email'], $om['contact'], $om['college'], $hashed])) {
                // Send email with credentials via PHPMailer
                $subject = "Your OM Account - Smart Attendance System";
                $message = "Hello {$om['name']},\n\nYour account has been created.\nEmail: {$om['email']}\nPassword: {$om['password']}\n";
                $sent = sendSMTPMail($om['email'], $subject, $message);
                if ($sent) $emails_sent++;
                $count++;
                    }
        }
        if ($count > 0) {
            if ($emails_sent == $count) {
                $success = "Imported $count Operation Managers. Credentials sent to all emails.";
            } else if ($emails_sent == 0) {
                $success = "Imported $count Operation Managers. But emails could not be sent.";
            } else {
                $success = "Imported $count Operation Managers. Credentials sent to $emails_sent emails.";
            }
        }
    } elseif (!$error) {
        $error = 'No valid OMs found in file.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Register OMs - Smart Attendance System</title>
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
    </style>
</head>
<body>
<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
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
    <div class="container mt-5" style="max-width: 700px;">
        <h2>Bulk Register Operation Managers</h2>
        <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
                                
                                <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
        <div class="card">
            <div class="card-header">Bulk Register OMs (CSV, XLS, XLSX)</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="oms_file" class="form-label">Upload File</label>
                        <input type="file" class="form-control" id="oms_file" name="oms_file" accept=".csv,.xls,.xlsx" required>
                        <div class="form-text">File must have columns: <b>name, designation, email, contact, college, password</b></div>
                    </div>
                    <button type="submit" class="btn btn-success">Bulk Register</button>
                </form>
            </div>
        </div>

        <footer class="text-center mt-5 mb-3 text-muted small">
            &lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 