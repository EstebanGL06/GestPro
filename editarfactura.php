<?php
$factura_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$target = $factura_id
    ? 'dashboard.php?section=editar_factura&id=' . $factura_id
    : 'dashboard.php?section=facturas';

header('Location: ' . $target);
exit;
?>
