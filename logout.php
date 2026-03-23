<?php
session_start();

// 1. Clear all session variables (Requirement #4)
$_SESSION = array();

// 2. Destroy the actual session
session_destroy();

// 3. Redirect to login with a status so we can show a message
header("Location: login.php?status=loggedout");
exit();
?>