<?php
session_start();
unset($_SESSION['dealer']);
header("Location: login.php");
exit();
?>
