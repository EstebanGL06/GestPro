<?php

session_start();

require 'conexion.php'; 



if (!isset($_SESSION['usuario_id'])) {

    header("Location: iniciarsesion.php");

    exit;

}



// Filtramos estrictamente la variable para evitar que un '0' o 'false' se cuele como administrador

$es_admin = isset($_SESSION['es_admin']) ? filter_var($_SESSION['es_admin'], FILTER_VALIDATE_BOOLEAN) : false;



$usuario_id = $_SESSION['usuario_id'];

$nombre_user = $_SESSION['nombre'];



$section = isset($_GET['section']) ? $_GET['section'] : 'facturas';

$mensaje_error = ""; 



// Capturamos el DNI del buscador si existe

$buscar_dni = isset($_GET['buscar_dni']) ? trim($_GET['buscar_dni']) : '';

$factura_lineas = [];
$factura_subtotal = 0.0;
$factura_ajustes = 0.0;



try {

    // --- SECCIÓN: LÓGICA DE VER FACTURA ---

    if ($section === 'ver_factura') {

        $factura_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$factura_id) {

            header("Location: dashboard.php?section=facturas");

            exit;

        }

        try {

            $stmt = $pdo->prepare("

                SELECT f.factura_id, f.total, f.fecha, f.usuario_id,

                       u.nombre AS nombre_cliente, u.dni AS nif_cliente, u.email AS email_cliente,

                       rv.estado

                FROM Factura f

                JOIN Usuario u ON f.usuario_id = u.usuario_id

                LEFT JOIN RegistroVerifactu rv ON f.factura_id = rv.factura_id

                WHERE f.factura_id = :id

            ");

            $stmt->execute([':id' => $factura_id]);

            $f_ver = $stmt->fetch(PDO::FETCH_ASSOC);



            if (!$f_ver) { header("Location: dashboard.php?section=facturas"); exit; }



            if (!$es_admin && (int) $f_ver['usuario_id'] !== (int) $usuario_id) {

                header("Location: dashboard.php?section=facturas"); exit;

            }



            $stmt_lineas = $pdo->prepare("

                SELECT descripcion, cantidad, precio_unitario

                FROM LineaFactura

                WHERE factura_id = :id

            ");

            $stmt_lineas->execute([':id' => $factura_id]);

            $factura_lineas = $stmt_lineas->fetchAll(PDO::FETCH_ASSOC);



            if (!$factura_lineas) {

                $factura_lineas = [[

                    'descripcion' => 'Servicio facturado',

                    'cantidad' => 1,

                    'precio_unitario' => (float) ($f_ver['total'] ?? 0),

                ]];

            }



            foreach ($factura_lineas as &$linea) {

                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio_unitario = (float) ($linea['precio_unitario'] ?? 0);

                $linea['descripcion'] = trim((string) ($linea['descripcion'] ?? '')) !== ''
                    ? $linea['descripcion']
                    : 'Concepto general';
                $linea['cantidad'] = $cantidad > 0 ? $cantidad : 1;
                $linea['precio_unitario'] = $precio_unitario;
                $linea['importe'] = $linea['cantidad'] * $precio_unitario;

                $factura_subtotal += $linea['importe'];

            }

            unset($linea);

            $factura_ajustes = (float) ($f_ver['total'] ?? 0) - $factura_subtotal;

        } catch (PDOException $e) {

            $mensaje_error = "Error al cargar la factura: " . $e->getMessage();

        }

    }



    // --- SECCIÓN: LÓGICA DE EDICIÓN DE FACTURA ---

    elseif ($section === 'editar_factura' && $es_admin) {

        if (!isset($_GET['id'])) {

            header("Location: dashboard.php?section=facturas");

            exit;

        }

        $factura_id = $_GET['id'];



        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $desc = htmlspecialchars($_POST['desc'] ?? '');

            $cant = (int)($_POST['cant'] ?? 1);

            $precio = (float)($_POST['precio'] ?? 0);

            $total_final = (float)($_POST['total_final'] ?? 0);



            try {

                $pdo->beginTransaction();



                $sql_f = "UPDATE Factura SET total = :total WHERE factura_id = :id";

                $stmt_f = $pdo->prepare($sql_f);

                $stmt_f->execute([':total' => $total_final, ':id' => $factura_id]);



                $sql_l = "UPDATE LineaFactura SET descripcion = :desc, cantidad = :cant, precio_unitario = :precio WHERE factura_id = :id";

                $stmt_l = $pdo->prepare($sql_l);

                $stmt_l->execute([':desc' => $desc, ':cant' => $cant, ':precio' => $precio, ':id' => $factura_id]);



                $pdo->commit();

                header("Location: dashboard.php?section=facturas");

                exit;



            } catch (PDOException $e) {

                $pdo->rollBack(); 

                $mensaje_error = "Error al actualizar: " . $e->getMessage();

            }

        }



        try {

            $stmt = $pdo->prepare("

                SELECT f.factura_id, f.total, 

                       u.nombre as nombre_cliente, u.dni as nif_cliente,

                       l.descripcion, l.cantidad, l.precio_unitario

                FROM Factura f 

                JOIN Usuario u ON f.usuario_id = u.usuario_id 

                LEFT JOIN LineaFactura l ON f.factura_id = l.factura_id

                WHERE f.factura_id = :id

            ");

            $stmt->execute([':id' => $factura_id]);

            $f = $stmt->fetch(PDO::FETCH_ASSOC);



            if (!$f) { header("Location: dashboard.php?section=facturas"); exit; }

        } catch (PDOException $e) {

            $mensaje_error = "Error al cargar la factura: " . $e->getMessage();

        }

    } 



    elseif ($section === 'crear_factura' && !$es_admin) {

        $stmt_u = $pdo->prepare("SELECT nombre, dni FROM Usuario WHERE usuario_id = :uid");
        $stmt_u->execute([':uid' => $usuario_id]);
        $datos_u = $stmt_u->fetch(PDO::FETCH_ASSOC) ?: [
            'nombre' => $nombre_user,
            'dni' => $_SESSION['dni'] ?? '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $desc = htmlspecialchars(trim($_POST['desc'] ?? ''));
            $cant = max(1, (int) ($_POST['cant'] ?? 1));
            $precio = max(0, (float) ($_POST['precio'] ?? 0));
            $total_final = max(0, (float) ($_POST['total_final'] ?? 0));
            $fecha_actual = date('Y-m-d');

            try {

                $pdo->beginTransaction();

                $sql_f = "INSERT INTO Factura (usuario_id, total, fecha) VALUES (:uid, :total, :fecha)";
                $stmt_f = $pdo->prepare($sql_f);
                $stmt_f->execute([
                    ':uid' => $usuario_id,
                    ':total' => $total_final,
                    ':fecha' => $fecha_actual,
                ]);

                $nuevo_factura_id = $pdo->lastInsertId();

                $sql_l = "INSERT INTO LineaFactura (factura_id, descripcion, cantidad, precio_unitario) VALUES (:fid, :desc, :cant, :precio)";
                $stmt_l = $pdo->prepare($sql_l);
                $stmt_l->execute([
                    ':fid' => $nuevo_factura_id,
                    ':desc' => $desc !== '' ? $desc : 'Servicio facturado',
                    ':cant' => $cant,
                    ':precio' => $precio,
                ]);

                $pdo->commit();
                header("Location: dashboard.php?section=facturas");
                exit;

            } catch (PDOException $e) {

                $pdo->rollBack();
                $mensaje_error = "Error al crear la factura: " . $e->getMessage();

            }

        }

    }



    elseif ($section === 'grafico' && !$es_admin) {

        $stmt_graf = $pdo->prepare("SELECT fecha, SUM(total) as total_dia FROM Factura WHERE usuario_id = :uid GROUP BY fecha ORDER BY fecha ASC LIMIT 15");

        $stmt_graf->execute([':uid' => $usuario_id]);

        $res_graf = $stmt_graf->fetchAll(PDO::FETCH_ASSOC);

        $labels = json_encode(array_column($res_graf, 'fecha'));

        $valores = json_encode(array_column($res_graf, 'total_dia'));

        

    } elseif ($section === 'usuarios' && $es_admin) {

        // LÓGICA BUSCADOR USUARIOS

        if ($buscar_dni !== '') {

            $sql_us = "SELECT usuario_id, nombre, dni, email, admin FROM Usuario WHERE dni LIKE :dni ORDER BY usuario_id DESC";

            $stmt_us = $pdo->prepare($sql_us);

            $stmt_us->execute([':dni' => "%$buscar_dni%"]);

        } else {

            $sql_us = "SELECT usuario_id, nombre, dni, email, admin FROM Usuario ORDER BY usuario_id DESC";

            $stmt_us = $pdo->prepare($sql_us);

            $stmt_us->execute();

        }

        $usuarios_lista = $stmt_us->fetchAll(PDO::FETCH_ASSOC);

        

    } else {

        // LÓGICA BUSCADOR FACTURAS

        if ($es_admin) {

            if (isset($_GET['cliente_id'])) {

                $filtro_id = $_GET['cliente_id'];

                $sql = "SELECT f.*, u.nombre as cliente, rv.estado FROM Factura f 

                        JOIN Usuario u ON f.usuario_id = u.usuario_id

                        LEFT JOIN RegistroVerifactu rv ON f.factura_id = rv.factura_id 

                        WHERE f.usuario_id = :cid ORDER BY f.fecha DESC";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([':cid' => $filtro_id]);

            } elseif ($buscar_dni !== '') {

                $sql = "SELECT f.*, u.nombre as cliente, rv.estado FROM Factura f 

                        JOIN Usuario u ON f.usuario_id = u.usuario_id

                        LEFT JOIN RegistroVerifactu rv ON f.factura_id = rv.factura_id 

                        WHERE u.dni LIKE :dni ORDER BY f.fecha DESC";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([':dni' => "%$buscar_dni%"]);

            } else {

                $sql = "SELECT f.*, u.nombre as cliente, rv.estado FROM Factura f 

                        JOIN Usuario u ON f.usuario_id = u.usuario_id

                        LEFT JOIN RegistroVerifactu rv ON f.factura_id = rv.factura_id ORDER BY f.fecha DESC";

                $stmt = $pdo->prepare($sql);

                $stmt->execute();

            }

        } else {

            $sql = "SELECT f.*, rv.estado FROM Factura f 

                    LEFT JOIN RegistroVerifactu rv ON f.factura_id = rv.factura_id 

                    WHERE f.usuario_id = :uid ORDER BY f.fecha DESC";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([':uid' => $usuario_id]);

        }

        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

} catch (PDOException $e) {

    $error = "Error de base de datos.";

    if($section === 'crear_factura') $mensaje_error = "Error de conexión: " . $e->getMessage();

    if($section === 'editar_factura' || $section === 'ver_factura') $mensaje_error = "Error de conexión: " . $e->getMessage();

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestpro - Panel</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'); 

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        img,
        svg,
        canvas {
            max-width: 100%;
        }

        input,
        select,
        textarea,
        button,
        a {
            touch-action: manipulation;
        }

        input,
        select,
        textarea {
            font-size: 16px;
        }

        .sidebar {
            flex-shrink: 0;
        }

        .main-content,
        .main-content > * {
            min-width: 0;
        }

        

        .sidebar-backdrop { display: none; }

        .mobile-menu-button { display: none; }

        .chart-panel {

            position: relative;

            width: 100%;

            height: clamp(260px, 55vw, 350px);

            z-index: 10;

            touch-action: pan-y;

        }



        @media (max-width: 768px) {

            body {

                overflow-x: hidden;

            }

            .mobile-menu-button {

                display: inline-flex;

                align-items: center;

                justify-content: center;

                position: fixed;

                top: max(15px, env(safe-area-inset-top));

                left: max(15px, env(safe-area-inset-left));

                z-index: 120;

                background-color: #0f172a; 

                color: white;

                border: 0;

                width: 48px;

                height: 48px;

                padding: 0;

                border-radius: 14px;

                cursor: pointer;

                font-size: 22px;

                line-height: 1;

                box-shadow: 0 4px 6px rgba(0,0,0,0.3);

            }

            .sidebar {

                position: fixed;

                left: calc(-1 * min(18rem, 88vw));

                top: 0;

                height: 100vh;

                height: 100dvh;

                width: min(18rem, 88vw);

                overflow-y: auto;

                padding-bottom: max(1.5rem, env(safe-area-inset-bottom));

                overscroll-behavior: contain;

                z-index: 100;

                pointer-events: auto;

                transition: left 0.3s ease-in-out;

            }

            body.mobile-menu-open .sidebar { left: 0; }

            .sidebar-backdrop {

                display: block;

                position: fixed;

                inset: 0;

                background: rgba(15, 23, 42, 0.55);

                border: 0;

                padding: 0;

                opacity: 0;

                pointer-events: none;

                transition: opacity 0.3s ease-in-out;

                z-index: 40;

            }

            body.mobile-menu-open .sidebar-backdrop {

                opacity: 1;

                pointer-events: auto;

            }

            .main-content {

                max-width: 100% !important;

                padding-top: 84px !important; 

                padding-left: 1rem !important;

                padding-right: 1rem !important;

                padding-bottom: 2rem !important;

                width: 100% !important;

            }

            .responsive-table {

                border: none !important;

                background: transparent !important;

                box-shadow: none !important;

            }

            .responsive-table thead {

                display: none; 

            }

            .responsive-table tbody tr {

                display: block;

                margin-bottom: 1.25rem;

                background-color: white;

                border-radius: 1rem;

                border: 1px solid #e2e8f0;

                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);

                overflow: hidden;

            }

            .responsive-table td {

                display: grid;

                grid-template-columns: minmax(6.5rem, 40%) minmax(0, 1fr);

                align-items: center;

                column-gap: 0.75rem;

                row-gap: 0.5rem;

                padding: 0.9rem 1rem !important;

                text-align: right;

                border-bottom: 1px solid #f1f5f9;

                overflow-wrap: anywhere;

            }

            .responsive-table td:last-child {

                border-bottom: none;

            }

            .responsive-table td::before {

                content: attr(data-label);

                min-width: 0;

                max-width: none;

                font-weight: bold;

                font-size: 0.75rem;

                text-transform: uppercase;

                color: #64748b;

                text-align: left;

            }

            .responsive-table td > * {

                min-width: 0;

                max-width: 100%;

                justify-self: end;

            }

            .responsive-table td:not(.acciones-cell) > :last-child {

                overflow-wrap: anywhere;

            }

            .responsive-table td[colspan] {

                display: block;

                text-align: center;

            }

            .responsive-table td[colspan]::before {

                content: none;

            }

            .responsive-table td.acciones-cell {

                grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));

                gap: 0.65rem;

                align-items: center;

                text-align: left;

            }

            .responsive-table td.acciones-cell::before {

                grid-column: 1 / -1;

                max-width: none; 

            }

            .responsive-table td.acciones-cell > * {

                justify-self: stretch;

            }

            .responsive-table td.acciones-cell a {

                width: 100%;

                min-width: 44px;

                min-height: 44px;

                padding: 0.65rem 0.75rem;

                gap: 0.4rem;

                font-size: 0.75rem;

                font-weight: 700;

            }

            .responsive-table td.acciones-cell a::after {

                content: attr(title);

            }

            .responsive-table td.acciones-cell span {

                min-height: 44px;

                display: inline-flex;

                align-items: center;

            }

            .responsive-table td.acciones-cell > :last-child {

                max-width: none;

            }

        }

        @media (hover: none) and (pointer: coarse) {

            a:hover,
            button:hover {
                transform: none !important;
            }

            .sidebar a {
                transition: none !important;
                -webkit-tap-highlight-color: transparent;
                min-height: 52px;
                position: relative;
                z-index: 110;
                pointer-events: auto;
            }

            .sidebar a:hover {
                background: transparent !important;
            }

            .sidebar a.bg-blue-600:hover {
                background: #2563eb !important;
            }

        }

    </style>

</head>

<body class="bg-slate-50 flex min-h-screen">



    <button type="button" id="mobile-menu-button" class="mobile-menu-button" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">☰</button>



    <button type="button" id="sidebar-backdrop" class="sidebar-backdrop" aria-label="Cerrar menú"></button>

    <aside id="sidebar" class="sidebar w-64 bg-slate-900 text-white p-6 flex flex-col shadow-xl z-40">

        <div class="mb-10 mt-4 md:mt-0">

            <div class="flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-lg shadow-blue-950/20 overflow-hidden">
                    <img src="assets/branding/logo-gestpro.jpeg" alt="Logo de GestPro" class="h-8 w-8 object-contain">
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-blue-400">Gest<span class="text-white">Pro</span></h1>
                    <p class="text-xs text-slate-500 uppercase tracking-widest mt-1">VeriFactu Ready</p>
                </div>
            </div>

        </div>



        <nav class="flex-1 space-y-2">

            <a href="dashboard.php?section=facturas" class="flex items-center space-x-3 p-3 rounded-lg transition <?php echo ($section === 'facturas' || $section === 'editar_factura' || $section === 'ver_factura' || $section === 'crear_factura' || !isset($_GET['section'])) ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-400 md:hover:bg-slate-800 md:hover:text-white'; ?>">

                <span>📄 <?php echo $es_admin ? 'Facturas' : 'Mis Facturas'; ?></span>

            </a>

            

            <?php if(!$es_admin): ?>

            <a href="dashboard.php?section=grafico" class="flex items-center space-x-3 p-3 rounded-lg transition <?php echo $section === 'grafico' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-400 md:hover:bg-slate-800 md:hover:text-white'; ?>">

                <span>📊 Estadísticas</span>

            </a>

            <?php endif; ?>



            <?php if($es_admin): ?>

            <div class="pt-4 mt-4 border-t border-slate-800">

                <p class="text-[10px] text-slate-500 font-bold uppercase mb-2">Administración</p>

                <a href="dashboard.php?section=usuarios" class="flex items-center space-x-3 p-3 rounded-lg transition <?php echo $section === 'usuarios' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'text-slate-400 md:hover:bg-slate-800 md:hover:text-white'; ?>">

                    <span>👥 Usuarios</span>

                </a>

            </div>

            <?php endif; ?>

        </nav>



        <div class="mt-auto pt-6 border-t border-slate-800">

            <div class="flex items-center space-x-3 mb-4">

                <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-blue-400 flex items-center justify-center font-bold text-xs shadow-lg">

                    <?php echo substr($nombre_user, 0, 1); ?>

                </div>

                <div class="overflow-hidden">

                    <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($nombre_user); ?></p>

                    <p class="text-[10px] text-slate-400 uppercase"><?php echo $es_admin ? 'Administrador' : 'Cliente'; ?></p>

                </div>

            </div>

            <a href="logout.php" class="block text-center p-2 text-xs font-bold text-red-400 rounded-lg transition border border-red-400/20 md:hover:bg-slate-800 md:hover:text-red-300 md:hover:border-transparent">Cerrar Sesión</a>

        </div>

    </aside>

    <main class="main-content flex-1 p-4 md:p-8 w-full max-w-7xl mx-auto">

        

        <?php if ($section === 'ver_factura' && isset($f_ver)): ?>

            <div class="bg-white p-5 md:p-8 rounded-3xl shadow-sm max-w-5xl mx-auto border border-slate-200 mt-4 md:mt-0">

                

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">

                    <a href="dashboard.php?section=facturas" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">

                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/></svg>

                        Volver al listado

                    </a>

                    <a href="descargarfactura.php?id=<?php echo htmlspecialchars($f_ver['factura_id']); ?>" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition-colors hover:bg-blue-700">

                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">

                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.6a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-2.6a.5.5 0 0 1 1 0v2.6A1.5 1.5 0 0 1 14.5 14.5h-13A1.5 1.5 0 0 1 0 13v-2.6a.5.5 0 0 1 .5-.5z"/>

                            <path d="M5.646 7.854a.5.5 0 0 0 .708.708L7.5 7.414V1.5a.5.5 0 0 1 1 0v5.914l1.146-1.146a.5.5 0 0 1 .708.708l-2 2a.5.5 0 0 1-.708 0l-2-2z"/>

                        </svg>

                        Descargar PDF

                    </a>

                </div>



                <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5 mb-8">

                    <div>

                        <div class="flex flex-wrap items-center gap-3 mb-3">

                            <h2 class="text-2xl md:text-3xl font-bold text-slate-800">Factura #<?php echo htmlspecialchars($f_ver['factura_id']); ?></h2>

                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-[10px] font-extrabold tracking-wide shadow-sm <?php echo !empty($f_ver['estado']) ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-yellow-100 text-yellow-700 border border-yellow-200'; ?>">

                                <?php echo !empty($f_ver['estado']) ? 'VERIFICADA' : 'PENDIENTE'; ?>

                            </span>

                        </div>

                        <p class="text-sm text-slate-500 max-w-2xl">Vista completa de la factura con todos sus conceptos y el resumen final preparado para descarga.</p>

                    </div>

                    <div class="grid grid-cols-1 gap-3 w-full sm:grid-cols-2 xl:w-auto xl:min-w-[280px]">

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">

                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Fecha</p>

                            <p class="mt-2 text-sm font-semibold text-slate-800"><?php echo date("d/m/Y", strtotime($f_ver['fecha'] ?? 'now')); ?></p>

                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">

                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Conceptos</p>

                            <p class="mt-2 text-sm font-semibold text-slate-800"><?php echo count($factura_lineas); ?></p>

                        </div>

                    </div>

                </div>

                

                <?php if ($mensaje_error): ?>

                    <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $mensaje_error; ?></div>

                <?php endif; ?>



                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6 mb-8">

                    <div class="p-4 md:p-5 bg-slate-50 rounded-xl border border-slate-100">

                        <p class="text-xs font-bold text-slate-400 uppercase mb-1">Datos del Cliente</p>

                        <p class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($f_ver['nombre_cliente'] ?? 'N/A'); ?></p>

                        <p class="text-slate-500 text-sm mt-2">NIF: <span class="font-mono"><?php echo htmlspecialchars($f_ver['nif_cliente'] ?? 'N/A'); ?></span></p>

                        <?php if (!empty($f_ver['email_cliente'])): ?>

                            <p class="text-slate-500 text-sm mt-1 break-all"><?php echo htmlspecialchars($f_ver['email_cliente']); ?></p>

                        <?php endif; ?>

                    </div>

                    <div class="p-4 md:p-5 bg-slate-50 rounded-xl border border-slate-100 md:text-right flex flex-col justify-center">

                        <p class="text-xs font-bold text-slate-400 uppercase mb-1">Fecha de Emisión</p>

                        <p class="font-bold text-slate-800 text-lg"><?php echo date("d/m/Y", strtotime($f_ver['fecha'] ?? 'now')); ?></p>

                    </div>

                </div>



                <div class="mb-8 w-full">

                    <table class="w-full text-left border-collapse responsive-table">

                        <thead class="bg-slate-50 border-y border-slate-200 hidden md:table-header-group">

                            <tr class="text-slate-500 text-xs uppercase font-bold tracking-wider">

                                <th class="p-4">Concepto</th>

                                <th class="p-4 text-center">Cant.</th>

                                <th class="p-4 text-right">Precio Ud.</th>

                                <th class="p-4 text-right">Importe</th>

                            </tr>

                        </thead>

                        <tbody class="text-sm text-slate-700 md:divide-y md:divide-slate-100">

                            <?php foreach ($factura_lineas as $linea): ?>

                            <tr class="md:hover:bg-slate-50/80">

                                <td data-label="Concepto" class="p-4 font-medium text-slate-800"><?php echo htmlspecialchars($linea['descripcion']); ?></td>

                                <td data-label="Cantidad" class="p-4 md:text-center"><?php echo number_format((float) $linea['cantidad'], 2, ',', '.'); ?></td>

                                <td data-label="Precio Unitario" class="p-4 md:text-right"><?php echo number_format((float) $linea['precio_unitario'], 2, ',', '.'); ?> €</td>

                                <td data-label="Importe" class="p-4 md:text-right font-bold"><?php echo number_format((float) $linea['importe'], 2, ',', '.'); ?> €</td>

                            </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>



                <div class="flex justify-end">

                    <div class="w-full md:w-[22rem] p-6 bg-slate-900 rounded-2xl border border-slate-800 text-white">

                        <div class="flex items-center justify-between gap-3 text-sm">

                            <span class="text-slate-300">Subtotal</span>

                            <span class="font-semibold"><?php echo number_format($factura_subtotal, 2, ',', '.'); ?> €</span>

                        </div>

                        <?php if (abs($factura_ajustes) >= 0.01): ?>

                            <div class="mt-3 flex items-center justify-between gap-3 text-sm">

                                <span class="text-slate-300">Impuestos / ajustes</span>

                                <span class="font-semibold"><?php echo number_format($factura_ajustes, 2, ',', '.'); ?> €</span>

                            </div>

                        <?php endif; ?>

                        <div class="my-4 h-px bg-white/10"></div>

                        <p class="text-xs text-blue-200 font-bold uppercase mb-1 tracking-wider">Total a Pagar</p>

                        <p class="text-3xl sm:text-4xl font-black text-white"><?php echo number_format((float) $f_ver['total'], 2, ',', '.'); ?> €</p>

                        <p class="text-[10px] text-slate-400 mt-2 uppercase font-semibold">(Impuestos incluidos)</p>

                    </div>

                </div>

            </div>



        <?php elseif ($section === 'editar_factura' && $es_admin): ?>

            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm max-w-4xl mx-auto border border-slate-200 mt-4 md:mt-0">

                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                    <div>

                        <h2 class="text-2xl font-bold text-slate-800">Modificar Factura #<?php echo htmlspecialchars($factura_id); ?></h2>

                        <p class="mt-2 text-sm text-slate-500">Actualiza los conceptos y revisa el total final con una vista comoda tambien en pantallas pequenas.</p>

                    </div>

                    <a href="dashboard.php?section=facturas" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">

                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/></svg>

                        Volver al listado

                    </a>

                </div>

                

                <?php if ($mensaje_error): ?>

                    <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $mensaje_error; ?></div>

                <?php endif; ?>



                <form action="dashboard.php?section=editar_factura&id=<?php echo $factura_id; ?>" method="POST" id="formFactura">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase">Nombre/Razón Social</label>

                            <input type="text" value="<?php echo htmlspecialchars($f['nombre_cliente'] ?? ''); ?>" readonly class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed outline-none">

                        </div>

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase">NIF/CIF Cliente</label>

                            <input type="text" value="<?php echo htmlspecialchars($f['nif_cliente'] ?? ''); ?>" readonly class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed outline-none">

                        </div>

                    </div>



                    <div class="mb-6 p-4 bg-slate-50 rounded-xl border border-dashed border-slate-300">

                        <h3 class="text-sm font-bold mb-3">Concepto del Servicio</h3>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

                            <div class="md:col-span-2">

                                <label class="block md:hidden text-xs font-bold text-slate-500 mb-1">Descripción</label>

                                <input type="text" name="desc" value="<?php echo htmlspecialchars($f['descripcion'] ?? ''); ?>" placeholder="Descripción" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                            </div>

                            <div>

                                <label class="block md:hidden text-xs font-bold text-slate-500 mb-1">Cantidad</label>

                                <input type="number" name="cant" id="cant" value="<?php echo $f['cantidad'] ?? 1; ?>" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                            </div>

                            <div>

                                <label class="block md:hidden text-xs font-bold text-slate-500 mb-1">Precio Unitario</label>

                                <input type="number" step="0.01" name="precio" id="precio" value="<?php echo $f['precio_unitario'] ?? 0; ?>" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                            </div>

                        </div>

                    </div>



                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-6">

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase">IVA (%)</label>

                            <select name="iva" id="iva" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                                <option value="21" selected>21% (General)</option>

                                <option value="10">10% (Reducido)</option>

                                <option value="4">4% (Superreducido)</option>

                            </select>

                        </div>

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase">Retención IRPF (%)</label>

                            <select name="irpf" id="irpf" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                                <option value="0" selected>0%</option>

                                <option value="7">7% (Nuevos)</option>

                                <option value="15">15% (General)</option>

                            </select>

                        </div>

                        <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 text-left sm:text-right md:col-span-1 mt-4 md:mt-0">

                            <p class="text-xs text-blue-600 font-bold uppercase">Total a Cobrar</p>

                            <p class="text-2xl font-black text-blue-900" id="total_display">0.00 €</p>

                            <input type="hidden" name="total_final" id="total_final" value="<?php echo $f['total'] ?? 0; ?>">

                        </div>

                    </div>



                    <button type="submit" class="mt-8 w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition shadow-lg">

                        Guardar Cambios

                    </button>

                </form>

            </div>



            <script>

            (() => {

                const cant = document.getElementById('cant');

                const precio = document.getElementById('precio');

                const iva = document.getElementById('iva');

                const irpf = document.getElementById('irpf');

                const display = document.getElementById('total_display');

                const totalFinal = document.getElementById('total_final');

                if (!cant || !precio || !iva || !irpf || !display || !totalFinal) return;



                function calcular() {

                    const base = (parseFloat(cant.value) || 0) * (parseFloat(precio.value) || 0);

                    const cuotaIva = base * ((parseInt(iva.value, 10) || 0) / 100);

                    const cuotaIrpf = base * ((parseInt(irpf.value, 10) || 0) / 100);

                    const total = Math.max(0, base + cuotaIva - cuotaIrpf);

                    display.innerText = total.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

                    totalFinal.value = total.toFixed(2);

                }



                [cant, precio, iva, irpf].forEach((el) => {

                    el.addEventListener('input', calcular);

                    el.addEventListener('change', calcular);

                });

                calcular();

            })();

            </script>



        <?php elseif ($section === 'crear_factura' && !$es_admin): ?>

            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm max-w-4xl mx-auto border border-slate-200 mt-4 md:mt-0">

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">

                    <a href="dashboard.php?section=facturas" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">

                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/></svg>

                        Volver a facturas

                    </a>

                    <span class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-blue-50 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-blue-700 border border-blue-100">Nueva emisión</span>

                </div>

                <div class="mb-8">

                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 tracking-tight">Crear nueva factura</h2>

                    <p class="text-sm md:text-base text-slate-500 mt-2">Completa los datos del servicio y el sistema calculará el total final para el cliente.</p>

                </div>

                <?php if ($mensaje_error): ?>

                    <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-6"><?php echo $mensaje_error; ?></div>

                <?php endif; ?>

                <form action="dashboard.php?section=crear_factura" method="POST" class="space-y-6">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Cliente</label>

                            <input type="text" value="<?php echo htmlspecialchars($datos_u['nombre'] ?? $nombre_user); ?>" readonly class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed outline-none">

                        </div>

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">NIF / DNI</label>

                            <input type="text" value="<?php echo htmlspecialchars($datos_u['dni'] ?? ($_SESSION['dni'] ?? '')); ?>" readonly class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed outline-none">

                        </div>

                    </div>

                    <div class="p-4 md:p-5 bg-slate-50 rounded-2xl border border-dashed border-slate-300">

                        <h3 class="text-sm font-bold text-slate-700 mb-4">Concepto del servicio</h3>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

                            <div class="md:col-span-2">

                                <label class="block md:hidden text-xs font-bold text-slate-500 mb-1">Descripción</label>

                                <input type="text" name="desc" value="<?php echo htmlspecialchars($_POST['desc'] ?? ''); ?>" placeholder="Descripción del servicio" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                            </div>

                            <div>

                                <label class="block md:hidden text-xs font-bold text-slate-500 mb-1">Cantidad</label>

                                <input type="number" min="1" name="cant" id="cant-crear" value="<?php echo htmlspecialchars($_POST['cant'] ?? '1'); ?>" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                            </div>

                            <div>

                                <label class="block md:hidden text-xs font-bold text-slate-500 mb-1">Precio Unitario</label>

                                <input type="number" min="0" step="0.01" name="precio" id="precio-crear" value="<?php echo htmlspecialchars($_POST['precio'] ?? '0'); ?>" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                            </div>

                        </div>

                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">IVA (%)</label>

                            <select name="iva" id="iva-crear" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                                <option value="21" <?php echo (($_POST['iva'] ?? '21') === '21') ? 'selected' : ''; ?>>21% (General)</option>

                                <option value="10" <?php echo (($_POST['iva'] ?? '') === '10') ? 'selected' : ''; ?>>10% (Reducido)</option>

                                <option value="4" <?php echo (($_POST['iva'] ?? '') === '4') ? 'selected' : ''; ?>>4% (Superreducido)</option>

                                <option value="0" <?php echo (($_POST['iva'] ?? '') === '0') ? 'selected' : ''; ?>>0%</option>

                            </select>

                        </div>

                        <div>

                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Retención IRPF (%)</label>

                            <select name="irpf" id="irpf-crear" class="w-full p-3 border border-slate-200 bg-white rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">

                                <option value="0" <?php echo (($_POST['irpf'] ?? '0') === '0') ? 'selected' : ''; ?>>0%</option>

                                <option value="7" <?php echo (($_POST['irpf'] ?? '') === '7') ? 'selected' : ''; ?>>7% (Nuevos)</option>

                                <option value="15" <?php echo (($_POST['irpf'] ?? '') === '15') ? 'selected' : ''; ?>>15% (General)</option>

                            </select>

                        </div>

                        <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100 text-right">

                            <p class="text-xs text-blue-600 font-bold uppercase">Total a Cobrar</p>

                            <p class="text-2xl font-black text-blue-900 mt-2" id="total-display-crear">0.00 €</p>

                            <input type="hidden" name="total_final" id="total-final-crear" value="<?php echo htmlspecialchars($_POST['total_final'] ?? '0'); ?>">

                        </div>

                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/20">

                        Emitir factura

                    </button>

                </form>

            </div>

            <script>

            (() => {

                const cantCrear = document.getElementById('cant-crear');
                const precioCrear = document.getElementById('precio-crear');
                const ivaCrear = document.getElementById('iva-crear');
                const irpfCrear = document.getElementById('irpf-crear');
                const totalDisplayCrear = document.getElementById('total-display-crear');
                const totalFinalCrear = document.getElementById('total-final-crear');

                if (!cantCrear || !precioCrear || !ivaCrear || !irpfCrear || !totalDisplayCrear || !totalFinalCrear) return;

                function calcularCrear() {
                    const base = (parseFloat(cantCrear.value) || 0) * (parseFloat(precioCrear.value) || 0);
                    const cuotaIva = base * ((parseInt(ivaCrear.value, 10) || 0) / 100);
                    const cuotaIrpf = base * ((parseInt(irpfCrear.value, 10) || 0) / 100);
                    const total = Math.max(0, base + cuotaIva - cuotaIrpf);

                    totalDisplayCrear.innerText = total.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
                    totalFinalCrear.value = total.toFixed(2);
                }

                [cantCrear, precioCrear, ivaCrear, irpfCrear].forEach((el) => {
                    el.addEventListener('input', calcularCrear);
                    el.addEventListener('change', calcularCrear);
                });
                calcularCrear();

            })();

            </script>



        <?php elseif ($section === 'grafico' && !$es_admin): ?>

            <div class="max-w-5xl mx-auto mt-4 md:mt-0">

                <div class="mb-8">

                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 tracking-tight">Evolución de Facturación</h2>

                    <p class="text-sm md:text-base text-slate-500 mt-1">Resumen de ingresos activos.</p>

                </div>

                

                <div class="bg-white p-4 md:p-8 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden">

                    <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-blue-50 rounded-full blur-3xl"></div>

                    <div class="chart-panel">

                        <canvas id="myChart"></canvas>

                    </div>

                </div>

            </div>



            <script>

                document.addEventListener('DOMContentLoaded', function() {

                    const ctx = document.getElementById('myChart').getContext('2d');

                    let gradient = ctx.createLinearGradient(0, 0, 0, 400);

                    gradient.addColorStop(0, 'rgba(37, 99, 235, 0.4)');

                    gradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)');



                    new Chart(ctx, {

                        type: 'line',

                        data: {

                            labels: <?php echo $labels; ?>,

                            datasets: [{

                                label: 'Ingresos',

                                data: <?php echo $valores; ?>,

                                borderColor: '#2563eb',

                                backgroundColor: gradient,

                                borderWidth: 3,

                                fill: true,

                                tension: 0.4,

                                pointBackgroundColor: '#ffffff',

                                pointBorderColor: '#2563eb',

                                pointBorderWidth: 2,

                                pointRadius: 4,

                                pointHoverRadius: 8,

                                pointHoverBackgroundColor: '#2563eb',

                                pointHoverBorderColor: '#ffffff',

                                pointHoverBorderWidth: 3

                            }]

                        },

                        options: { 

                            responsive: true, 

                            maintainAspectRatio: false, 

                            interaction: { mode: 'index', intersect: false },

                            plugins: { 

                                legend: { display: false },

                                tooltip: {

                                    backgroundColor: 'rgba(15, 23, 42, 0.9)',

                                    titleFont: { family: "'Inter', sans-serif", size: 13, weight: 'normal' },

                                    bodyFont: { family: "'Inter', sans-serif", size: 15, weight: 'bold' },

                                    padding: 12, cornerRadius: 10, displayColors: false,

                                    callbacks: {

                                        label: function(context) { return context.parsed.y.toLocaleString('es-ES', { minimumFractionDigits: 2 }) + ' €'; }

                                    }

                                }

                            },

                            scales: {

                                x: { grid: { display: false, drawBorder: false }, ticks: { font: { family: "'Inter', sans-serif", size: 10 }, color: '#64748b' } },

                                y: { border: { display: false }, grid: { color: '#f1f5f9', drawTicks: false }, ticks: { font: { family: "'Inter', sans-serif", size: 10, weight: '500' }, color: '#64748b', padding: 8, callback: function(value) { return value + ' €'; } }, beginAtZero: true }

                            }

                        }

                    });

                });

            </script>



        <?php elseif ($section === 'usuarios' && $es_admin): ?>

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4 mt-4 md:mt-0">

                <div>

                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 tracking-tight">Listado de Usuarios</h2>

                    <p class="text-slate-500 text-sm md:text-base mt-1">Gestiona los clientes y administradores registrados.</p>

                </div>

                

                <form method="GET" action="" class="w-full sm:w-auto relative">

                    <input type="hidden" name="section" value="usuarios">

                    <input type="text" name="buscar_dni" value="<?php echo htmlspecialchars($buscar_dni); ?>" placeholder="Buscar DNI..." class="w-full sm:w-64 pl-10 pr-10 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm transition-shadow">

                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>

                    <?php if($buscar_dni !== ''): ?>

                        <a href="dashboard.php?section=usuarios" class="absolute right-3.5 top-3.5 text-slate-400 hover:text-red-500 transition-colors" title="Limpiar búsqueda">

                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>

                        </a>

                    <?php endif; ?>

                </form>

            </div>



            <div class="w-full">

                <table class="w-full text-left border-collapse responsive-table bg-white md:rounded-2xl md:shadow-sm md:border md:border-slate-200">

                    <thead class="hidden md:table-header-group">

                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-xs uppercase font-bold tracking-wider">

                            <th class="p-5">ID</th>

                            <th class="p-5">Nombre</th>

                            <th class="p-5">DNI</th>

                            <th class="p-5">Email</th>

                            <th class="p-5 text-center">Rol</th>

                            <th class="p-5 text-right">Acciones</th>

                        </tr>

                    </thead>

                    <tbody class="text-sm text-slate-600 md:divide-y md:divide-slate-100">

                        <?php if(empty($usuarios_lista)): ?>

                            <tr>

                                <td colspan="6" class="p-8 text-center text-slate-400">No se ha encontrado ningún usuario con ese DNI.</td>

                            </tr>

                        <?php else: ?>

                            <?php foreach($usuarios_lista as $u): ?>

                            <tr class="hover:bg-blue-50/50 transition-colors group">

                                <td data-label="ID" class="p-4 md:p-5 font-bold text-slate-900 group-hover:text-blue-600 transition-colors">#<?php echo $u['usuario_id']; ?></td>

                                <td data-label="Nombre" class="p-4 md:p-5 text-slate-800 font-medium"><?php echo htmlspecialchars($u['nombre']); ?></td>

                                <td data-label="DNI" class="p-4 md:p-5 text-slate-500 font-mono text-xs"><?php echo htmlspecialchars($u['dni']); ?></td>

                                <td data-label="Email" class="p-4 md:p-5 text-slate-500 break-all"><?php echo htmlspecialchars($u['email']); ?></td>

                                <td data-label="Rol" class="p-4 md:p-5 md:text-center">

                                    <span class="px-3 py-1.5 rounded-full text-[10px] font-extrabold tracking-wide shadow-sm <?php echo $u['admin'] ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-700 border border-slate-200'; ?>">

                                        <?php echo $u['admin'] ? 'ADMIN' : 'CLIENTE'; ?>

                                    </span>

                                </td>

                                <td data-label="Acciones" class="p-4 md:p-5 md:text-right acciones-cell">

                                    <?php if(!$u['admin']): ?>

                                        <a href="dashboard.php?section=facturas&cliente_id=<?php echo $u['usuario_id']; ?>" class="inline-flex items-center gap-1.5 bg-white border border-blue-200 text-blue-600 hover:bg-blue-50 hover:border-blue-300 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm">

                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/></svg>

                                            Ver Facturas

                                        </a>

                                    <?php else: ?>

                                        <span class="text-slate-300 font-medium text-xs">N/A</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>



        <?php else: ?>

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 md:mb-8 gap-4 mt-2 md:mt-0">

                <div>

                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 tracking-tight">

                        <?php 

                            if ($es_admin) {

                                echo isset($_GET['cliente_id']) ? 'Facturas del Cliente #' . htmlspecialchars($_GET['cliente_id']) : 'Todas las Facturas';

                            } else {

                                echo 'Listado de Facturas';

                            }

                        ?>

                    </h2>

                    <p class="text-sm md:text-base text-slate-500 mt-1">

                        <?php 

                            if ($es_admin) {

                                echo isset($_GET['cliente_id']) ? 'Mostrando únicamente las facturas de este usuario.' : 'Supervisa las facturas emitidas por todos los clientes.';

                            } else {

                                echo 'Gestiona todas tus facturas emitidas y su estado.';

                            }

                        ?>

                    </p>

                </div>

                

                <div class="w-full sm:w-auto flex flex-col sm:flex-row gap-3">

                    <?php if($es_admin): ?>

                    <form method="GET" action="" class="w-full sm:w-auto relative order-last sm:order-first">

                        <input type="hidden" name="section" value="facturas">

                        <input type="text" name="buscar_dni" value="<?php echo htmlspecialchars($buscar_dni); ?>" placeholder="Buscar DNI..." class="w-full sm:w-64 pl-10 pr-10 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm shadow-sm transition-shadow">

                        <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>

                        <?php if($buscar_dni !== ''): ?>

                            <a href="dashboard.php?section=facturas" class="absolute right-3.5 top-3.5 text-slate-400 hover:text-red-500 transition-colors" title="Limpiar búsqueda">

                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>

                            </a>

                        <?php endif; ?>

                    </form>

                    <?php endif; ?>



                    <?php if(!$es_admin): ?>

                    <a href="dashboard.php?section=crear_factura" class="w-full sm:w-auto justify-center bg-blue-600 text-white px-6 py-2.5 rounded-xl shadow-lg shadow-blue-500/30 hover:bg-blue-700 hover:shadow-blue-600/40 transition-all font-medium text-sm flex items-center gap-2">                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>

                        Nueva Factura

                    </a>

                    <?php endif; ?>

                    

                    <?php if($es_admin && isset($_GET['cliente_id'])): ?>

                    <a href="dashboard.php?section=usuarios" class="w-full sm:w-auto justify-center bg-slate-100 text-slate-600 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-200 transition-all font-medium text-sm flex items-center gap-2">

                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/></svg>

                        Volver a Usuarios

                    </a>

                    <?php endif; ?>

                </div>

            </div>



            <div class="w-full">

                <table class="w-full text-left border-collapse responsive-table bg-white md:rounded-2xl md:shadow-sm md:border md:border-slate-200">

                    <thead class="hidden md:table-header-group">

                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-xs uppercase font-bold tracking-wider">

                            <th class="p-5">ID</th>

                            <th class="p-5">Fecha</th>

                            <?php if($es_admin): ?> <th class="p-5">Cliente</th> <?php endif; ?>

                            <th class="p-5">Total</th>

                            <th class="p-5 text-center">Estado</th>

                            <th class="p-5 text-right">Acciones</th>

                        </tr>

                    </thead>

                    <tbody class="text-sm text-slate-600 md:divide-y md:divide-slate-100">

                        <?php if (empty($facturas)): ?>

                            <tr>

                                <td colspan="<?php echo $es_admin ? '6' : '5'; ?>" class="p-8 text-center text-slate-400">No se encontraron facturas.</td>

                            </tr>

                        <?php else: ?>

                            <?php foreach($facturas as $f): ?>

                            <tr class="hover:bg-blue-50/50 transition-colors group">

                                <td data-label="ID Factura" class="p-4 md:p-5 font-bold text-slate-900 group-hover:text-blue-600 transition-colors">#<?php echo isset($f['id']) ? $f['id'] : $f['factura_id']; ?></td>

                                <td data-label="Fecha" class="p-4 md:p-5 text-slate-500"><?php echo isset($f['fecha_emision']) ? date("d/m/Y", strtotime($f['fecha_emision'])) : (isset($f['fecha']) ? date("d/m/Y", strtotime($f['fecha'])) : ''); ?></td>

                                <?php if($es_admin): ?> <td data-label="Cliente" class="p-4 md:p-5 text-blue-600 font-medium"><?php echo htmlspecialchars($f['cliente'] ?? $f['nombre_cliente'] ?? ''); ?></td> <?php endif; ?>

                                <td data-label="Total" class="p-4 md:p-5 font-bold text-slate-800"><?php echo number_format($f['total'], 2, ',', '.'); ?> €</td>

                                <td data-label="Estado" class="p-4 md:p-5 md:text-center">

                                    <span class="px-3 py-1.5 rounded-full text-[10px] font-extrabold tracking-wide shadow-sm <?php echo !empty($f['estado']) ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-yellow-100 text-yellow-700 border border-yellow-200'; ?>">

                                        <?php echo !empty($f['estado']) ? 'VERIFICADO': 'SIN VERIFICAR'; ?>

                                    </span>

                                </td>

                                

                                <td data-label="Acciones" class="p-4 md:p-5 md:text-right acciones-cell">

                                    <a href="dashboard.php?section=ver_factura&id=<?php echo isset($f['id']) ? $f['id'] : $f['factura_id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-300 hover:text-slate-900 transition-colors" title="Ver Factura">

                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">

                                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>

                                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>

                                        </svg>

                                    </a>

                                    <a href="descargarfactura.php?id=<?php echo isset($f['id']) ? $f['id'] : $f['factura_id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-colors" title="Descargar PDF">

                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">

                                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.6a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-2.6a.5.5 0 0 1 1 0v2.6A1.5 1.5 0 0 1 14.5 14.5h-13A1.5 1.5 0 0 1 0 13v-2.6a.5.5 0 0 1 .5-.5z"/>

                                            <path d="M5.646 7.854a.5.5 0 0 0 .708.708L7.5 7.414V1.5a.5.5 0 0 1 1 0v5.914l1.146-1.146a.5.5 0 0 1 .708.708l-2 2a.5.5 0 0 1-.708 0l-2-2z"/>

                                        </svg>

                                    </a>



                                    <?php if($es_admin): ?>

                                    <a href="dashboard.php?section=editar_factura&id=<?php echo isset($f['id']) ? $f['id'] : $f['factura_id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-colors" title="Editar Factura">

                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">

                                            <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>

                                        </svg>

                                    </a>

                                    

                                    <a href="borrarfactura.php?id=<?php echo isset($f['id']) ? $f['id'] : $f['factura_id']; ?>" onclick="return confirm('¿Estás seguro de que deseas eliminar esta factura?');" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-colors" title="Borrar Factura">

                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">

                                            <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5ZM11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H2.506a.58.58 0 0 0-.01 0H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1h-.995a.59.59 0 0 0-.01 0H11Zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5h9.916Zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47ZM8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5Z"/>

                                        </svg>

                                    </a>

                                    <?php endif; ?>

                                </td>

                            </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

        

    </main>

    <script>
    (function () {
        var menuButton = document.getElementById('mobile-menu-button');
        var backdrop = document.getElementById('sidebar-backdrop');
        var sidebar = document.getElementById('sidebar');
        var navigatingFromMenu = false;

        function setMenuOpen(open) {
            document.body.classList.toggle('mobile-menu-open', open);

            if (menuButton) {
                menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        }

        if (menuButton) {
            menuButton.addEventListener('click', function () {
                setMenuOpen(!document.body.classList.contains('mobile-menu-open'));
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setMenuOpen(false);
            });
        }

        if (sidebar) {
            sidebar.addEventListener('touchend', function (event) {
                var link = event.target.closest('a[href]');

                if (!link || !sidebar.contains(link) || navigatingFromMenu) {
                    return;
                }

                navigatingFromMenu = true;
                event.preventDefault();
                event.stopPropagation();
                window.location.href = link.href;
            }, { passive: false, capture: true });

            sidebar.addEventListener('click', function (event) {
                var link = event.target.closest('a[href]');

                if (!link || !sidebar.contains(link) || navigatingFromMenu) {
                    return;
                }

                navigatingFromMenu = true;
                window.location.href = link.href;
            }, true);
        }
    })();
    </script>

</body>

</html>
