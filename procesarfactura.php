<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'dashboard.php';

if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target);
exit;
?>
