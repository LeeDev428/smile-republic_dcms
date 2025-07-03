<?php
require_once 'includes/config.php';

// Destroy session and redirect to home page
session_destroy();
redirect('index.php?logout=1');
?>
