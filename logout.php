<?php
session_start();
unset($_SESSION['dealer'], $_SESSION['access_token']);
setcookie('access_token', '', time() - 3600, '/');
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Logging out...</title></head>
<body>
<script>
try {
    localStorage.removeItem('provider_access_token');
    localStorage.removeItem('provider_user');
} catch (e) {}
window.location.replace('login.php');
</script>
</body>
</html>
