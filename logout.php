<?php
session_start();
unset($_SESSION['dealer'], $_SESSION['access_token']);
setcookie('access_token', '', time() - 3600, '/');
header("Location: login.php");
exit();
?>
