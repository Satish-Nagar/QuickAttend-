<?php
require_once '../includes/functions.php';
requireAdmin();

$db = getDB();
$admin_id = $_SESSION['user_id'];

// Fetch admin details
$stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile_picture = $admin['profile_picture'] ?? null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['tmp_name']) {
        $upload = uploadFile($_FILES['profile_picture'], '../uploads', ['jpg', 'jpeg', 'png']);
        if ($upload) {
            $profile_picture = $upload;
        } else {
            $error = 'Invalid profile picture.';
        }
    }
    if (!$error) {
        $stmt = $db->prepare("UPDATE admins SET profile_picture = ? WHERE id = ?");
        if ($stmt->execute([$profile_picture, $admin_id])) {
            $success = 'Profile updated successfully!';
            $admin['profile_picture'] = $profile_picture;
        } else {
            $error = 'Failed to update profile.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Admin Profile</h2>
    <a href="dashboard.php" class="btn btn-secondary btn-sm mb-3">&larr; Back to Dashboard</a>
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
                        <?php if (!empty($admin['profile_picture'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($admin['profile_picture']); ?>" class="img-thumbnail mb-2" style="max-width:120px;">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/120x120.png?text=Admin" class="img-thumbnail mb-2" style="max-width:120px;">
                        <?php endif; ?>
                        <input type="file" name="profile_picture" class="form-control form-control-sm mt-2">
                    </div>
                    <div class="col-md-9">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html> 