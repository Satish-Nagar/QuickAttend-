<?php
require_once '../includes/functions.php';

// Redirect if already logged in as OM
if (isOM()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password, is_active FROM operation_managers WHERE email = ?");
        $stmt->execute([$email]);
        $om = $stmt->fetch();
        
        if ($om && verifyPassword($password, $om['password'])) {
            if (!$om['is_active']) {
                $error = 'Your account has been deactivated. Please contact admin.';
            } else {
                $_SESSION['user_id'] = $om['id'];
                $_SESSION['user_type'] = 'om';
                $_SESSION['om_name'] = $om['name'];
                $_SESSION['om_email'] = $om['email'];
                
                setFlashMessage('success', 'Welcome back, ' . $om['name'] . '!');
                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid email or password';
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      }
      .page-wrapper {
        flex: 1 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 0;
      }
      .login-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        padding: 40px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        max-width: 400px;
        width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 480px;
      }
      .login-header {
        text-align: center;
        margin-bottom: 30px;
      }
      .login-header i {
        font-size: 3rem;
        color: #28a745;
        margin-bottom: 15px;
      }
      .form-control {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 12px 15px;
        transition: all 0.3s ease;
      }
      .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
      }
      .btn-login {
        background: linear-gradient(45deg, #28a745, #20c997);
        border: none;
        border-radius: 10px;
        padding: 12px;
        font-weight: 600;
        width: 100%;
        transition: all 0.3s ease;
      }
      .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
      }
      .back-link {
        text-align: center;
        margin-top: auto;
        padding-top: 20px;
      }
      .back-link a {
        color: #28a745;
        text-decoration: none;
      }
      .back-link a:hover {
        text-decoration: underline;
      }
      footer.text-center {
        color: #fff !important;
        font-size: 1rem;
      }
    </style>
  </head>
  <body>
    <div class="page-wrapper">
      <div class="login-container mx-auto">
        <div class="login-header">
          <i class="fas fa-users"></i>
          <h3>OM Login</h3>
          <p class="text-muted">Access your dashboard</p>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
          <i class="fas fa-exclamation-triangle"></i>
          <?php echo $error; ?>
        </div>
        <?php endif; ?>
        <form method="POST" action="">
          <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="fas fa-envelope"></i>
              </span>
              <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                required
              />
            </div>
          </div>
          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="fas fa-lock"></i>
              </span>
              <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                required
              />
            </div>
          </div>
          <button type="submit" class="btn btn-success btn-login">
            <i class="fas fa-sign-in-alt"></i> Login
          </button>
        </form>
        <div class="back-link">
          <a href="../index.php">
            <i class="fas fa-arrow-left"></i> Back to Home
          </a>
        </div>
      </div>
    </div>
    <footer class="text-center mt-5 mb-3 text-muted small">
      &lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
