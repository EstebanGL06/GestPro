<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'dashboard.php';

if ($query !== '') {
    $target .= '?' . $query;
} else {
    $target .= '?section=grafico';
}

header('Location: ' . $target);
exit;
?>
