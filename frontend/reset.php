<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'reset-password.php' . ($query !== '' ? ('?' . $query) : '');
header('Location: ' . $target);
exit;
?>
