<?php
session_start();

$factura_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$es_admin = isset($_SESSION['es_admin'])
    ? filter_var($_SESSION['es_admin'], FILTER_VALIDATE_BOOLEAN)
    : false;

if ($factura_id) {
    $target = 'dashboard.php?section=editar_factura&id=' . $factura_id;
} elseif ($es_admin) {
    $target = 'dashboard.php?section=facturas';
} else {
    $target = 'dashboard.php?section=crear_factura';
}

header('Location: ' . $target);
exit;
?>
