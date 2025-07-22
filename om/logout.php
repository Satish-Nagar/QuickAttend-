<?php
require_once '../includes/functions.php';

// Destroy session
session_destroy();

// Redirect to home page
redirect('../index.php');
?>
<?php /* Insert header bar and sidebar here, as in dashboard.php lines 170-216 */ ?>
    <footer class="text-center mt-5 mb-3 text-muted small">
        &lt;GoG&gt; Smart Attendance Tracker Presented By Satish Nagar
    </footer>
</body>
</html> 