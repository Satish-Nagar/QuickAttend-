<?php
/**
 * Utility Functions
 * Smart Attendance Automation System
 */

session_start();

// Include database configuration
require_once(__DIR__ . '/../config/database.php'); // ✅ Correct and safe


/**
 * Authentication Functions
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

function isOM() {
    return isLoggedIn() && $_SESSION['user_type'] === 'om';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireOM() {
    if (!isOM()) {
        header('Location: ../index.php');
        exit();
    }
}

/**
 * Database Helper Functions
 */
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Validation Functions
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateRollNumber($roll_no) {
    return preg_match('/^[A-Z0-9]+$/', $roll_no);
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Password Functions
 */
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * File Upload Functions
 */
function uploadFile($file, $target_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return false;
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $filename = uniqid() . '.' . $file_extension;
    $target_path = $target_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $filename;
    }

    return false;
}

/**
 * CSV Processing Functions
 */
function processCSV($file_path, $required_headers = []) {
    if (!file_exists($file_path)) {
        return ['success' => false, 'message' => 'File not found'];
    }

    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'Unable to open file'];
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return ['success' => false, 'message' => 'Invalid CSV format'];
    }

    // Validate headers
    foreach ($required_headers as $header) {
        if (!in_array($header, $headers)) {
            fclose($handle);
            return ['success' => false, 'message' => "Missing required header: $header"];
        }
    }

    $data = [];
    $row_number = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        $row_data = array_combine($headers, $row);
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        $data[] = $row_data;
    }

    fclose($handle);
    
    return [
        'success' => true,
        'data' => $data,
        'total_rows' => count($data)
    ];
}

/**
 * Attendance Processing Functions
 */
function generateBinaryString($present_rolls, $all_students) {
    $binary_array = [];
    
    foreach ($all_students as $student) {
        $roll_suffix = substr($student['roll_no'], -3);
        $is_present = in_array($roll_suffix, $present_rolls);
        $binary_array[] = $is_present ? '1' : '0';
    }
    
    return implode("\n", $binary_array);
}

function getAttendanceStats($binary_string) {
    $values = explode(',', $binary_string);
    $present_count = array_count_values($values)['1'] ?? 0;
    $total_count = count($values);
    
    return [
        'present' => $present_count,
        'total' => $total_count,
        'percentage' => $total_count > 0 ? round(($present_count / $total_count) * 100, 2) : 0
    ];
}

/**
 * Response Functions
 */
function sendJSONResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Date Functions
 */
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

function getCurrentDate() {
    return date('Y-m-d');
}

/**
 * Error and Success Messages
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}


?> 
<script>
    function copyToClipboard(event) {
    const binaryOutput = document.getElementById('binaryOutput');
    if (!binaryOutput || !binaryOutput.textContent.trim()) {
        alert('Nothing to copy!');
        return;
    }
    const text = binaryOutput.textContent;
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        button.classList.remove('btn-primary');
        button.classList.add('btn-success');
        setTimeout(function() {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-primary');
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy to clipboard');
    });
}
</script>