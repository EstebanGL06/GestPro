<?php
session_start();
require 'conexion.php';

$es_admin = isset($_SESSION['es_admin'])
    ? filter_var($_SESSION['es_admin'], FILTER_VALIDATE_BOOLEAN)
    : false;

if (!isset($_SESSION['usuario_id']) || !$es_admin) {
    header("Location: dashboard.php");
    exit;
}

$factura_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$factura_id) {
    header("Location: dashboard.php?section=facturas");
    exit;
}

try {
    $pdo->beginTransaction();

    // Eliminamos primero los registros dependientes para evitar errores de clave foranea.
    $stmt_lineas = $pdo->prepare("DELETE FROM LineaFactura WHERE factura_id = :id");
    $stmt_lineas->execute([':id' => $factura_id]);

    $stmt_rv = $pdo->prepare("DELETE FROM RegistroVerifactu WHERE factura_id = :id");
    $stmt_rv->execute([':id' => $factura_id]);

    $stmt_f = $pdo->prepare("DELETE FROM Factura WHERE factura_id = :id");
    $stmt_f->execute([':id' => $factura_id]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Error borrando factura {$factura_id}: " . $e->getMessage());
}

header("Location: dashboard.php?section=facturas");
exit;
?>
