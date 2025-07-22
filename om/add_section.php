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

$success = '';
$error = '';
$preview = [];
$preview_error = '';

function parse_pasted_students($raw) {
    $lines = preg_split('/\r?\n/', trim($raw));
    $students = [];
    $roll_nos = [];
    $errors = [];
    foreach ($lines as $i => $line) {
        $cols = preg_split('/\t|,/', trim($line));
        $name = trim($cols[0] ?? '');
        $roll_no = trim($cols[1] ?? '');
        $contact = trim($cols[2] ?? '');
        $row_error = [];
        if (!$name) $row_error[] = 'Missing name';
        if (!$roll_no) $row_error[] = 'Missing roll_no';
        if (!$contact) $row_error[] = 'Missing contact';
        if ($roll_no && in_array(strtolower($roll_no), $roll_nos)) {
            $row_error[] = 'Duplicate roll_no in paste';
        }
        $roll_nos[] = strtolower($roll_no);
        $students[] = [
            'name' => $name,
            'roll_no' => $roll_no,
            'contact' => $contact,
            'row_error' => $row_error,
            'row_num' => $i+1
        ];
    }
    return $students;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $students_raw = trim($_POST['students_paste'] ?? '');
    $section_id = null;

    if (isset($_POST['preview'])) {
        // Preview logic
        $preview = parse_pasted_students($students_raw);
        // Check for duplicate roll_no in DB
        $roll_nos = array_map(function($s){return $s['roll_no'];}, $preview);
        $placeholders = implode(',', array_fill(0, count($roll_nos), '?'));
        if ($roll_nos) {
            $stmt = $db->prepare("SELECT roll_no FROM students WHERE LOWER(roll_no) IN ($placeholders)");
            $stmt->execute(array_map('strtolower', $roll_nos));
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($preview as &$stu) {
                if (in_array(strtolower($stu['roll_no']), array_map('strtolower', $existing))) {
                    $stu['row_error'][] = 'Duplicate roll_no in database';
                }
            }
        }
    } elseif (isset($_POST['add_section'])) {
        // Final add logic
        $preview = parse_pasted_students($students_raw);
        $has_error = false;
        $roll_nos = array_map(function($s){return $s['roll_no'];}, $preview);
        $placeholders = implode(',', array_fill(0, count($roll_nos), '?'));
        $existing = [];
        if ($roll_nos) {
            $stmt = $db->prepare("SELECT roll_no FROM students WHERE LOWER(roll_no) IN ($placeholders)");
            $stmt->execute(array_map('strtolower', $roll_nos));
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $duplicate_in_paste = [];
        $duplicate_in_db = [];
        $seen = [];
        foreach ($preview as &$stu) {
            if (!$stu['name'] || !$stu['roll_no'] || !$stu['contact']) {
                $has_error = true;
                $stu['row_error'][] = 'Missing field';
            }
            if (in_array(strtolower($stu['roll_no']), $seen)) {
                $has_error = true;
                $stu['row_error'][] = 'Duplicate roll_no in paste';
                $duplicate_in_paste[] = $stu['roll_no'];
            }
            $seen[] = strtolower($stu['roll_no']);
            if (in_array(strtolower($stu['roll_no']), array_map('strtolower', $existing))) {
                $has_error = true;
                $stu['row_error'][] = 'Duplicate roll_no in database';
                $duplicate_in_db[] = $stu['roll_no'];
            }
        }
        $duplicate_in_paste = array_unique($duplicate_in_paste);
        $duplicate_in_db = array_unique($duplicate_in_db);
        if ($has_error) {
            $error = 'Please fix the errors in the student list before adding.';
            if ($duplicate_in_paste) {
                $error .= '<br>Duplicate roll_no(s) in pasted data: <b>' . implode(', ', $duplicate_in_paste) . '</b>';
            }
            if ($duplicate_in_db) {
                $error .= '<br>Duplicate roll_no(s) already exist in database: <b>' . implode(', ', $duplicate_in_db) . '</b>';
            }
        } elseif ($name) {
            $stmt = $db->prepare("INSERT INTO sections (om_id, name, description) VALUES (?, ?, ?)");
            if ($stmt->execute([$om_id, $name, $description])) {
                $section_id = $db->lastInsertId();
                $stmt = $db->prepare("INSERT INTO students (section_id, name, roll_no, contact) VALUES (?, ?, ?, ?)");
                $count = 0;
                foreach ($preview as $stu) {
                    $stmt->execute([$section_id, $stu['name'], $stu['roll_no'], $stu['contact']]);
                    $count++;
                }
                $success = 'Section and ' . $count . ' students added successfully!';
                $preview = [];
            } else {
                $error = 'Failed to add section.';
            }
        } else {
            $error = 'Section name is required.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add Section & Students - Smart Attendance System</title>
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
      .header-profile img {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      }
      .header-profile .dropdown-toggle {
        color: #fff !important;
      }
      .header-profile .dropdown-menu {
        min-width: 160px;
      }
      @media (max-width: 991.98px) {
        .om-header-bar {
          display: none;
        }
      }

      .sidebar {
        background: #fff;
        padding: 20px 0 20px 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        /* max-height: 100%; */
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
        margin: 0 10px; /* Adds space left and right */
        width: auto; /* Let the button size itself */
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
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }
      .sidebar-col {
        top: 68px;
        left: 0;
        padding-left: 0;
        padding-right: 0;
        background: #fff;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      }

      .sidebar {
        overflow-y: auto;
      }
      .main-content-fixed {
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php /* Insert header bar and sidebar here, as in dashboard.php lines 170-216 */ ?>
    <!-- Header Branding Bar (desktop only) -->
    <div
      class="om-header-bar d-none d-lg-flex justify-content-between align-items-center"
    >
      <div class="header-title">
        <img
          src="images\download.png"
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
              src="<?php echo $profile_picture; ?>"
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
              ><?php echo htmlspecialchars($_SESSION['om_name'] ?? 'OM'); ?></span
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
        <div class="col-md-9 col-lg-10 main-content-fixed">
                <div class="main-content">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h2 class="mb-0">Add Section & Students</h2>
              <a href="dashboard.php" class="btn btn-secondary btn-sm"
                >&larr; Back to Dashboard</a
              >
            </div>
                                <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
            <form method="POST">
              <div class="mb-3">
                <label for="name" class="form-label">Section Name</label>
                <input
                  type="text"
                  class="form-control"
                  id="name"
                  name="name"
                  value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                  required
                />
                                    </div>
                                    <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea
                  class="form-control"
                  id="description"
                  name="description"
                  rows="2"
                >
<?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea
                >
                                    </div>
              <div class="mb-3">
                <label for="students_paste" class="form-label"
                  >Paste Students (name, roll_no, contact)</label
                >
                <textarea
                  class="form-control"
                  id="students_paste"
                  name="students_paste"
                  rows="8"
                  placeholder="Copy from Google Sheet: name, roll_no, contact (tab or comma separated)"
                >
<?php echo htmlspecialchars($_POST['students_paste'] ?? ''); ?></textarea
                >
                <div class="form-text">
                  Paste rows from Google Sheet or Excel. Each row:
                  <b>name, roll_no, contact</b> (comma or tab separated).
                                    </div>
                                    </div>
              <div class="d-flex gap-2">
                <button type="submit" name="preview" class="btn btn-info">
                  Preview
                </button>
                <button
                  type="submit"
                  name="add_section"
                  class="btn btn-primary"
                >
                  Add Section & Students
                                        </button>
                                    </div>
                                </form>
            <?php if ($preview): ?>
            <div class="mt-4">
              <h5>Preview</h5>
              <table class="table table-bordered table-sm">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Roll No</th>
                    <th>Contact</th>
                    <th>Errors</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($preview as $stu): ?>
                  <tr
                    class="<?php echo $stu['row_error'] ? 'error-row' : ''; ?>"
                  >
                    <td><?php echo $stu['row_num']; ?></td>
                    <td><?php echo htmlspecialchars($stu['name']); ?></td>
                    <td><?php echo htmlspecialchars($stu['roll_no']); ?></td>
                    <td><?php echo htmlspecialchars($stu['contact']); ?></td>
                    <td class="error-text">
                      <?php echo $stu['row_error'] ? implode(', ', $stu['row_error']) : ''; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
            <footer class="text-center mt-5 mb-3 text-muted small">
              &lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar
            </footer>
          </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
