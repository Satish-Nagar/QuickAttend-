<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/functions.php';
requireOM();

$db = getDB();
$om_id = $_SESSION['user_id'];

// Fetch OM details
$stmt = $db->prepare("SELECT * FROM operation_managers WHERE id = ?");
$stmt->execute([$om_id]);
$om = $stmt->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $profile_picture = $om['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['tmp_name']) {
        $upload = uploadFile($_FILES['profile_picture'], '../uploads', ['jpg', 'jpeg', 'png']);
        if ($upload) {
            $profile_picture = $upload;
        } else {
            $error = 'Invalid profile picture.';
        }
    }
    if (!$error) {
        $stmt = $db->prepare("UPDATE operation_managers SET name = ?, contact = ?, profile_picture = ? WHERE id = ?");
        if ($stmt->execute([$name, $contact, $profile_picture, $om_id])) {
            $success = 'Profile updated successfully!';
            $om['name'] = $name;
            $om['contact'] = $contact;
            $om['profile_picture'] = $profile_picture;
            $_SESSION['om_name'] = $name;
        } else {
            $error = 'Failed to update profile.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile - Smart Attendance System</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      body {
        background-color: #f8f9fa;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      }
      .om-header-bar {
        width: 100%;
        background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
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
        .om-header-bar {
          display: none;
        }
      }
      .header-profile img {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      }
      .header-profile .dropdown-toggle {
        color: #fff !important;
      }
      .header-profile .dropdown-menu {
        min-width: 160px;
      }

      /* .sidebar {
        background: #fff;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        padding: 20px 0 20px 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        overflow-y: auto;
      }
      .btn-sidebar {
        background: #fff;
        color: #333;
        border: none;
        border-radius: 10px;
        padding: 12px 20px;
        font-size: 1.06rem;
        font-weight: 400;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 10px;
        width: auto;
      }
         */
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
      .btn-sidebar i {
        margin-right: 8px;
        font-size: 1.2rem;
      }
      .btn-sidebar.active,
      .btn-sidebar:active,
      .btn-sidebar:hover {
        background: linear-gradient(45deg, #28a745, #20c997);
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(32, 201, 151, 0.08);
      }
      @media (max-width: 991.98px) {
        .sidebar {
          min-height: auto;
          border-radius: 0;
          padding: 12px 0;
        }
        .sidebar-col {
          min-height: auto;
        }
      }
      .sidebar-col {
        top: 68px;
        left: 0;
        padding-left: 0;
        padding-right: 0;
        background: #fff;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      }
      .main-content {
        padding: 10px;
      }
    </style>
  </head>
  <body>
    <!-- Header Branding Bar (desktop only) -->
    <div
      class="om-header-bar d-none d-lg-flex justify-content-between align-items-center"
    >
      <div class="header-title">
        <img
          src="images/download.png"
          alt="Logo"
          style="
            height: 40px;
            width: auto;
            margin-right: 10px;
            border-radius: 25%;
          "
        />
        Smart Attendance System
      </div>
      <div class="header-profile me-4 position-relative">
        <div class="dropdown">
          <a
            href="#"
            class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
            id="profileDropdown"
            data-bs-toggle="dropdown"
            aria-expanded="false"
          >
            <img
              src="<?php echo $om['profile_picture'] ? '../uploads/' . htmlspecialchars($om['profile_picture']) : 'https://via.placeholder.com/40x40.png?text=OM'; ?>"
              alt="Profile"
              class="rounded-circle"
              style="
                width: 40px;
                height: 40px;
                object-fit: cover;
                border: 2px solid #fff;
              "
            />
            <span class="ms-2 fw-semibold"
              ><?php echo htmlspecialchars($om['name'] ?? 'OM'); ?></span
            >
          </a>
          <ul
            class="dropdown-menu dropdown-menu-end"
            aria-labelledby="profileDropdown"
          >
            <li>
              <a class="dropdown-item" href="profile.php"
                ><i class="fas fa-user"></i> Profile</a
              >
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
              <a class="dropdown-item" href="logout.php"
                ><i class="fas fa-sign-out-alt"></i> Logout</a
              >
            </li>
          </ul>
        </div>
      </div>
    </div>
    <div class="container-fluid">
      <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar-col px-0">
          <div class="sidebar">
            <nav class="nav flex-column">
              <a
                class="btn btn-sidebar text-start<?php if ($current_page == 'dashboard.php') echo ' active'; ?>"
                href="dashboard.php"
              >
                <i class="fa-solid fa-tachometer-alt"></i> Dashboard
              </a>
              <a
                class="btn btn-sidebar text-start<?php if ($current_page == 'add_section.php') echo ' active'; ?>"
                href="add_section.php"
              >
                <i class="fa-solid fa-plus"></i> Add Section
              </a>
              <a
                class="btn btn-sidebar text-start<?php if ($current_page == 'sections.php') echo ' active'; ?>"
                href="sections.php"
              >
                <i class="fa-solid fa-layer-group"></i> My Sections
              </a>
              <a
                class="btn btn-sidebar text-start<?php if ($current_page == 'attendance_history.php') echo ' active'; ?>"
                href="attendance_history.php"
              >
                <i class="fa-solid fa-clock-rotate-left"></i> Attendance History
              </a>
              <a
                class="btn btn-sidebar text-start<?php if ($current_page == 'assignment_history.php') echo ' active'; ?>"
                href="assignment_history.php"
              >
                <i class="fa-solid fa-book"></i> Assignments History
              </a>
              <a
                class="btn btn-sidebar text-start<?php if ($current_page == 'profile.php') echo ' active'; ?>"
                href="profile.php"
              >
                <i class="fa-solid fa-user"></i> Profile
              </a>
            </nav>
          </div>
        </div>
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
          <div class="main-content">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h2 class="mb-0">My Profile</h2>
              <a href="dashboard.php" class="btn btn-secondary btn-sm"
                >&larr; Back to Dashboard</a
              >
            </div>
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="card mb-4">
              <div class="card-header">Profile Details</div>
              <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                  <div class="row mb-3">
                    <div class="col-md-3 text-center">
                      <?php if ($om['profile_picture']): ?>
                      <img
                        src="../uploads/<?php echo htmlspecialchars($om['profile_picture']); ?>"
                        class="img-thumbnail mb-2"
                        style="max-width: 120px"
                      />
                      <?php else: ?>
                      <img
                        src="https://via.placeholder.com/120x120.png?text=Profile"
                        class="img-thumbnail mb-2"
                        style="max-width: 120px"
                      />
                      <?php endif; ?>
                      <input
                        type="file"
                        name="profile_picture"
                        class="form-control form-control-sm mt-2"
                      />
                    </div>
                    <div class="col-md-9">
                      <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input
                          type="text"
                          name="name"
                          class="form-control"
                          value="<?php echo htmlspecialchars($om['name']); ?>"
                          required
                        />
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input
                          type="email"
                          class="form-control"
                          value="<?php echo htmlspecialchars($om['email']); ?>"
                          disabled
                        />
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Designation</label>
                        <input
                          type="text"
                          class="form-control"
                          value="<?php echo htmlspecialchars($om['designation']); ?>"
                          disabled
                        />
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input
                          type="text"
                          name="contact"
                          class="form-control"
                          value="<?php echo htmlspecialchars($om['contact']); ?>"
                          required
                        />
                      </div>
                      <div class="mb-3">
                        <label class="form-label">College</label>
                        <input
                          type="text"
                          class="form-control"
                          value="<?php echo htmlspecialchars($om['college']); ?>"
                          disabled
                        />
                      </div>
                      <button type="submit" class="btn btn-primary">
                        Update Profile
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
