<?php
session_start();

require 'conexion.php';
require_once __DIR__ . '/vendor/tcpdf/tcpdf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: iniciarsesion.php');
    exit;
}

$es_admin = isset($_SESSION['es_admin']) ? filter_var($_SESSION['es_admin'], FILTER_VALIDATE_BOOLEAN) : false;
$usuario_id = (int) $_SESSION['usuario_id'];
$factura_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$factura_id) {
    http_response_code(400);
    exit('Acceso denegado: Identificador de factura no válido.');
}

$stmt_factura = $pdo->prepare(
    "SELECT f.factura_id, f.total, f.fecha, f.usuario_id,
            u.nombre AS nombre_cliente, u.dni AS nif_cliente, u.email AS email_cliente,
            rv.estado
     FROM Factura f
     JOIN Usuario u ON f.usuario_id = u.usuario_id
     LEFT JOIN RegistroVerifactu rv ON f.factura_id = rv.factura_id
     WHERE f.factura_id = :id"
);
$stmt_factura->execute([':id' => $factura_id]);
$factura = $stmt_factura->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    http_response_code(404);
    exit('Error: La factura solicitada no existe.');
}

if (!$es_admin && (int) $factura['usuario_id'] !== $usuario_id) {
    http_response_code(403);
    exit('Acceso denegado: No dispone de los permisos necesarios para descargar esta factura.');
}

$stmt_lineas = $pdo->prepare(
    "SELECT descripcion, cantidad, precio_unitario
     FROM LineaFactura
     WHERE factura_id = :id"
);
$stmt_lineas->execute([':id' => $factura_id]);
$lineas = $stmt_lineas->fetchAll(PDO::FETCH_ASSOC);

if (!$lineas) {
    $lineas = [
        [
            'descripcion' => 'Servicio facturado',
            'cantidad' => 1,
            'precio_unitario' => (float) $factura['total'],
        ],
    ];
}

function factura_pdf_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function factura_pdf_money($value)
{
    return number_format((float) $value, 2, ',', '.') . ' EUR';
}

function factura_pdf_date($value)
{
    $timestamp = strtotime((string) $value);

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

$subtotal = 0.0;

foreach ($lineas as $linea) {
    $cantidad = (float) ($linea['cantidad'] ?? 0);
    $precio_unitario = (float) ($linea['precio_unitario'] ?? 0);
    $subtotal += $cantidad * $precio_unitario;
}

$ajustes = (float) $factura['total'] - $subtotal;

$estado_verificado = !empty($factura['estado']);
$badge_bg    = $estado_verificado ? '#dcfce7' : '#fef3c7';
$badge_color = $estado_verificado ? '#15803d' : '#92400e';
$badge_text  = $estado_verificado ? 'VERIFICADA' : 'PENDIENTE';

// ══════════════════════════════════════════════════════
// CONFIGURACIÓN TCPDF — A4 sin márgenes para control total
// ══════════════════════════════════════════════════════
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestPro Technologies S.L.');
$pdf->SetAuthor('GestPro Technologies S.L.');
$pdf->SetTitle('Factura #' . $factura['factura_id']);
$pdf->SetSubject('Factura PDF');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();

// A4 = 210 x 297 mm
$pageW = 210;
$pageH = 297;
$marginL = 20;   // margen izquierdo del contenido
$marginR = 20;   // margen derecho
$contentW = $pageW - $marginL - $marginR; // 170mm de contenido útil

// ── Paleta de colores (Tailwind del proyecto) ─────────
$primary      = '#2563eb';   // blue-600
$primaryDark  = '#1d4ed8';   // blue-700
$primaryLight = '#dbeafe';   // blue-100
$primaryBg    = '#eff6ff';   // blue-50
$bgDark       = '#0f172a';   // slate-900
$bgDarkAlt    = '#1e293b';   // slate-800
$textSecondary = '#94a3b8';  // slate-400
$textMuted    = '#64748b';   // slate-500
$darkText     = '#0f172a';   // slate-900
$bodyText     = '#334155';   // slate-700
$grayText     = '#64748b';   // slate-500
$borderLight  = '#e2e8f0';   // slate-200
$altRowBg     = '#f8fafc';   // slate-50
$lightBg      = '#f1f5f9';   // slate-100

// ══════════════════════════════════════════════════════
// DIBUJAR FONDO Y ELEMENTOS DECORATIVOS
// ══════════════════════════════════════════════════════

// ── Fondo blanco completo ─────────────────────────────
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, $pageW, $pageH, 'F');

// ── Barra lateral izquierda azul (acento vertical) ────
$pdf->SetFillColor(37, 99, 235); // blue-600
$pdf->Rect(0, 0, 6, $pageH, 'F');

// ── Cabecera oscura (slate-900) — 72mm de alto ────────
$pdf->SetFillColor(15, 23, 42); // slate-900
$pdf->Rect(6, 0, $pageW - 6, 72, 'F');

// ── Línea de acento azul bajo cabecera ────────────────
$pdf->SetFillColor(37, 99, 235); // blue-600
$pdf->Rect(6, 72, $pageW - 6, 3, 'F');

// ── Franja de metadatos (slate-50) ────────────────────
$pdf->SetFillColor(248, 250, 252); // slate-50
$pdf->Rect(6, 75, $pageW - 6, 28, 'F');
// Línea inferior metadatos
$pdf->SetFillColor(226, 232, 240); // slate-200
$pdf->Rect(6, 103, $pageW - 6, 0.5, 'F');

// ── Bloque TOTAL (fondo oscuro) — en la zona inferior ─
// Posición dinámica, lo calcularemos después de la tabla

// ── Pie de página (slate-900) — 30mm de alto ─────────
$pdf->SetFillColor(15, 23, 42); // slate-900
$pdf->Rect(0, $pageH - 30, $pageW, 30, 'F');
// Línea decorativa azul sobre el footer
$pdf->SetFillColor(37, 99, 235); // blue-600
$pdf->Rect(6, $pageH - 30, $pageW - 6, 2, 'F');

// ══════════════════════════════════════════════════════
// CABECERA — Logo + N.º de factura (sobre fondo oscuro)
// ══════════════════════════════════════════════════════
$pdf->SetY(12);
$pdf->SetX($marginL + 4);

$headerHTML = '
<table style="width:100%;" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:60%; vertical-align:top; padding:0;">
            <span style="font-size:36pt; font-weight:bold; color:#ffffff; letter-spacing:-1px;">GEST</span><span style="font-size:36pt; font-weight:bold; color:' . $primary . '; letter-spacing:-1px;">PRO</span><br>
            <span style="font-size:9pt; color:' . $textSecondary . '; letter-spacing:0.5px;">Soluciones avanzadas de gestión empresarial</span><br><br><br>
            <span style="font-size:7.5pt; color:' . $textMuted . '; text-transform:uppercase; letter-spacing:2px;">EMISOR</span><br>
            <span style="font-size:11pt; color:#e2e8f0; font-weight:bold;">GESTPRO TECHNOLOGIES S.L.</span><br>
            <span style="font-size:9pt; color:' . $textSecondary . ';">NIF: B-12345678 &nbsp;&bull;&nbsp; info@gestpro.es</span>
        </td>
        <td style="width:40%; vertical-align:top; text-align:right; padding:0;">
            <span style="font-size:8pt; color:' . $textMuted . '; text-transform:uppercase; letter-spacing:3px; font-weight:bold;">FACTURA</span><br>
            <span style="font-size:38pt; font-weight:bold; color:#ffffff; letter-spacing:-2px;">#' . factura_pdf_escape($factura['factura_id']) . '</span><br><br>
            <span style="display:inline-block; padding:5px 16px; background-color:' . $badge_bg . '; color:' . $badge_color . '; font-size:8pt; font-weight:bold; text-transform:uppercase; letter-spacing:1px; border-radius:4px;">' . factura_pdf_escape($badge_text) . '</span><br><br>
            <span style="font-size:7.5pt; color:' . $textMuted . '; text-transform:uppercase; letter-spacing:1px;">FECHA DE EMISIÓN</span><br>
            <span style="font-size:13pt; font-weight:bold; color:#ffffff;">' . factura_pdf_escape(factura_pdf_date($factura['fecha'])) . '</span>
        </td>
    </tr>
</table>';

$pdf->writeHTMLCell($contentW, 0, $marginL + 4, 14, $headerHTML, 0, 1, false, true, '', true);

// ══════════════════════════════════════════════════════
// BARRA DE METADATOS (sobre franja slate-50)
// ══════════════════════════════════════════════════════
$metaHTML = '
<table style="width:100%; border-collapse:collapse;" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:25%; padding:6px 10px; text-align:center;">
            <span style="font-size:6.5pt; color:' . $textMuted . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">FECHA</span><br>
            <span style="font-size:11pt; font-weight:bold; color:' . $darkText . ';">' . factura_pdf_escape(factura_pdf_date($factura['fecha'])) . '</span>
        </td>
        <td style="width:25%; padding:6px 10px; text-align:center; border-left:1.5px solid ' . $borderLight . ';">
            <span style="font-size:6.5pt; color:' . $textMuted . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">N.º FACTURA</span><br>
            <span style="font-size:11pt; font-weight:bold; color:' . $primary . ';">#' . factura_pdf_escape($factura['factura_id']) . '</span>
        </td>
        <td style="width:25%; padding:6px 10px; text-align:center; border-left:1.5px solid ' . $borderLight . ';">
            <span style="font-size:6.5pt; color:' . $textMuted . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">CONCEPTOS</span><br>
            <span style="font-size:11pt; font-weight:bold; color:' . $darkText . ';">' . factura_pdf_escape(count($lineas)) . '</span>
        </td>
        <td style="width:25%; padding:6px 10px; text-align:center; border-left:1.5px solid ' . $borderLight . ';">
            <span style="font-size:6.5pt; color:' . $textMuted . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">TOTAL</span><br>
            <span style="font-size:11pt; font-weight:bold; color:' . $primary . ';">' . factura_pdf_escape(factura_pdf_money($factura['total'])) . '</span>
        </td>
    </tr>
</table>';

$pdf->writeHTMLCell($contentW, 0, $marginL + 4, 77, $metaHTML, 0, 1, false, true, '', true);

// ══════════════════════════════════════════════════════
// DATOS EMISOR + CLIENTE (dos columnas amplias)
// ══════════════════════════════════════════════════════
$clienteHTML = '
<table style="width:100%; border-collapse:collapse;" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:50%; padding:16px 14px 16px 4px; vertical-align:top;">
            <span style="font-size:7.5pt; color:' . $primary . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">DATOS DEL EMISOR</span><br><br>
            <span style="font-size:13pt; font-weight:bold; color:' . $darkText . ';">GESTPRO TECHNOLOGIES S.L.</span><br><br>
            <span style="font-size:9pt; color:' . $grayText . '; line-height:1.8;">
                NIF: B-12345678<br>
                Email: info@gestpro.es<br>
                Web: www.gestpro.es
            </span>
        </td>
        <td style="width:50%; padding:16px 4px 16px 14px; vertical-align:top; border-left:2px solid ' . $primaryLight . ';">
            <span style="font-size:7.5pt; color:' . $primary . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">DATOS DEL CLIENTE</span><br><br>
            <span style="font-size:13pt; font-weight:bold; color:' . $darkText . ';">' . factura_pdf_escape($factura['nombre_cliente'] ?: 'Cliente registrado') . '</span><br><br>
            <span style="font-size:9pt; color:' . $grayText . '; line-height:1.8;">
                NIF: ' . factura_pdf_escape($factura['nif_cliente'] ?: '-') . '<br>
                Email: ' . factura_pdf_escape($factura['email_cliente'] ?: '-') . '
            </span>
        </td>
    </tr>
</table>';

$pdf->writeHTMLCell($contentW, 0, $marginL + 4, 108, $clienteHTML, 0, 1, false, true, '', true);

// ══════════════════════════════════════════════════════
// LÍNEA SEPARADORA antes de la tabla
// ══════════════════════════════════════════════════════
$pdf->SetFillColor(226, 232, 240); // slate-200
$pdf->Rect($marginL + 4, 148, $contentW, 0.5, 'F');

// ══════════════════════════════════════════════════════
// TABLA DE CONCEPTOS
// ══════════════════════════════════════════════════════

// ── Filas HTML ────────────────────────────────────────
$rows_html = '';
foreach ($lineas as $idx => $linea) {
    $cantidad        = (float) ($linea['cantidad'] ?? 0);
    $precio_unitario = (float) ($linea['precio_unitario'] ?? 0);
    $importe         = $cantidad * $precio_unitario;
    $rowBg = ($idx % 2 === 0) ? '#ffffff' : $altRowBg;

    $rows_html .= '
        <tr style="background-color:' . $rowBg . ';">
            <td style="text-align:left; padding:14px 12px; font-size:10pt; color:' . $darkText . '; border-bottom:1px solid ' . $borderLight . ';">'
                . factura_pdf_escape($linea['descripcion'] ?: 'Concepto ' . ($idx + 1)) . '
            </td>
            <td style="text-align:center; padding:14px 8px; font-size:10pt; color:' . $grayText . '; border-bottom:1px solid ' . $borderLight . ';">'
                . factura_pdf_escape(number_format($cantidad, 2, ',', '.')) . '
            </td>
            <td style="text-align:right; padding:14px 12px; font-size:10pt; color:' . $grayText . '; border-bottom:1px solid ' . $borderLight . ';">'
                . factura_pdf_escape(factura_pdf_money($precio_unitario)) . '
            </td>
            <td style="text-align:right; padding:14px 12px; font-size:10.5pt; font-weight:bold; color:' . $primary . '; border-bottom:1px solid ' . $borderLight . ';">'
                . factura_pdf_escape(factura_pdf_money($importe)) . '
            </td>
        </tr>';
}

$tablaHTML = '
<table style="width:100%; border-collapse:collapse;" cellpadding="0" cellspacing="0">
    <thead>
        <tr style="background-color:' . $bgDark . ';">
            <th style="text-align:left;  padding:13px 12px; color:#ffffff; font-size:8pt; text-transform:uppercase; letter-spacing:1px; font-weight:bold; width:43%;">DESCRIPCIÓN</th>
            <th style="text-align:center; padding:13px 8px;  color:#ffffff; font-size:8pt; text-transform:uppercase; letter-spacing:1px; font-weight:bold; width:14%;">CANTIDAD</th>
            <th style="text-align:right;  padding:13px 12px; color:#ffffff; font-size:8pt; text-transform:uppercase; letter-spacing:1px; font-weight:bold; width:21%;">PRECIO UNITARIO</th>
            <th style="text-align:right;  padding:13px 12px; color:#ffffff; font-size:8pt; text-transform:uppercase; letter-spacing:1px; font-weight:bold; width:22%;">IMPORTE TOTAL</th>
        </tr>
    </thead>
    <tbody>
        ' . $rows_html . '
    </tbody>
</table>';

$pdf->writeHTMLCell($contentW, 0, $marginL + 4, 153, $tablaHTML, 0, 1, false, true, '', true);

// ══════════════════════════════════════════════════════
// SECCIÓN TOTALES + CONDICIONES
// Posición: calculada tras la tabla, o mínimo Y=200
// ══════════════════════════════════════════════════════
$curY = $pdf->GetY();
$totalesY = max($curY + 8, 200); // mínimo a 200mm para ocupar espacio

// ── Fila ajustes ──────────────────────────────────────
$ajustes_html = '';
if (abs($ajustes) >= 0.01) {
    $ajustes_html = '
        <tr>
            <td style="padding:10px 14px; color:' . $grayText . '; font-size:9.5pt; border-bottom:1px solid ' . $borderLight . ';">IVA / Impuestos aplicados</td>
            <td style="padding:10px 14px; text-align:right; color:' . $darkText . '; font-weight:bold; font-size:9.5pt; border-bottom:1px solid ' . $borderLight . ';">'
                . factura_pdf_escape(factura_pdf_money($ajustes)) . '</td>
        </tr>';
}

$totalesHTML = '
<table style="width:100%; border-collapse:collapse;" cellpadding="0" cellspacing="0">
    <tr>
        <!-- Condiciones legales (izquierda) -->
        <td style="width:48%; padding:0 10px 0 4px; vertical-align:top;">
            <span style="font-size:7.5pt; color:' . $primary . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">CONDICIONES DE PAGO</span><br><br>
            <span style="font-size:8.5pt; color:' . $grayText . '; line-height:1.7;">
                <strong>Método de pago:</strong> Transferencia bancaria<br>
                <strong>Vencimiento:</strong> A 30 días de la fecha de emisión
            </span><br><br>
            <span style="font-size:7.5pt; color:' . $primary . '; text-transform:uppercase; letter-spacing:1.5px; font-weight:bold;">INFORMACIÓN LEGAL</span><br><br>
            <span style="font-size:8pt; color:' . $grayText . '; line-height:1.6;">
                Esta factura cumple con los requisitos del reglamento de facturación vigente (VeriFactu). El emisor garantiza la integridad y trazabilidad de los datos de este documento.
            </span>
        </td>
        <!-- Bloque totales (derecha) -->
        <td style="width:52%; vertical-align:top; padding:0 0 0 10px;">
            <table style="width:100%; border-collapse:collapse;" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:10px 14px; color:' . $grayText . '; font-size:9.5pt; border-bottom:1px solid ' . $borderLight . ';">Base imponible (Subtotal)</td>
                    <td style="padding:10px 14px; text-align:right; color:' . $darkText . '; font-weight:bold; font-size:9.5pt; border-bottom:1px solid ' . $borderLight . ';">'
                        . factura_pdf_escape(factura_pdf_money($subtotal)) . '</td>
                </tr>
                ' . $ajustes_html . '
            </table>
            <table style="width:100%; border-collapse:collapse; background-color:' . $bgDark . ';" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:18px 14px; color:' . $textSecondary . '; font-size:9pt; font-weight:bold; text-transform:uppercase; letter-spacing:1.5px;">TOTAL FACTURA</td>
                    <td style="padding:18px 14px; text-align:right; color:#ffffff; font-size:22pt; font-weight:bold; letter-spacing:-0.5px;">'
                        . factura_pdf_escape(factura_pdf_money($factura['total'])) . '</td>
                </tr>
            </table>
        </td>
    </tr>
</table>';

$pdf->writeHTMLCell($contentW, 0, $marginL + 4, $totalesY, $totalesHTML, 0, 1, false, true, '', true);

// ══════════════════════════════════════════════════════
// PIE DE PÁGINA — Anclado al fondo del folio
// (El Rect del footer ya se dibujó arriba a 297-30=267mm)
// ══════════════════════════════════════════════════════
$footerHTML = '
<table style="width:100%; border-collapse:collapse;" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:45%; vertical-align:middle; padding:0;">
            <span style="font-size:14pt; font-weight:bold; color:#ffffff; letter-spacing:-0.5px;">GEST</span><span style="font-size:14pt; font-weight:bold; color:' . $primary . '; letter-spacing:-0.5px;">PRO</span><br>
            <span style="font-size:7.5pt; color:' . $textSecondary . ';">Software de Facturación Homologado — VeriFactu</span>
        </td>
        <td style="width:55%; text-align:right; vertical-align:middle; padding:0;">
            <span style="font-size:8pt; color:' . $textSecondary . ';">info@gestpro.es &nbsp;|&nbsp; www.gestpro.es</span><br>
            <span style="font-size:7.5pt; color:' . $textMuted . ';">Factura #' . factura_pdf_escape($factura['factura_id']) . ' &bull; Generada electrónicamente &bull; Gracias por su confianza</span>
        </td>
    </tr>
</table>';

$pdf->writeHTMLCell($contentW, 0, $marginL + 4, $pageH - 24, $footerHTML, 0, 1, false, true, '', true);

// ══════════════════════════════════════════════════════
// GENERAR PDF
// ══════════════════════════════════════════════════════
$pdf->Output('factura-' . $factura['factura_id'] . '.pdf', 'D');
exit;

