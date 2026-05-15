<?php

ob_start();
session_start();

$env = require __DIR__ . '/../env.php';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
        $env['DB_USER'], $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

require_once __DIR__ . '/fpdf.php';

date_default_timezone_set('America/Mexico_City');

function to_latin1(string $s): string {
    return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
}

class ReporteMateriales extends FPDF {
    function Header() {
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(50);
        $this->Cell(140, 10, 'REPORTE DE MATERIALES', 1, 0, 'C');
        $this->Ln(20);
        $this->SetFont('Helvetica', 'I', 10);
        $this->Cell(0, 10, 'Fecha de generacion: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
        $this->Ln(5);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

/* Datos desde tabla materiales */
$materiales = $pdo->query(
    "SELECT id_material, nombre, categoria, unidad, precio_kg, stock, stock_min
     FROM materiales ORDER BY nombre ASC"
)->fetchAll();

$pdf = new ReporteMateriales('L');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 11);

/* ── Encabezado de tabla ── */
$pdf->SetFillColor(232, 232, 232);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(70, 10, 'Nombre',      1, 0, 'L', true);
$pdf->Cell(55, 10, 'Categoria',   1, 0, 'L', true);
$pdf->Cell(25, 10, 'Unidad',      1, 0, 'C', true);
$pdf->Cell(40, 10, 'Precio/kg',   1, 0, 'R', true);
$pdf->Cell(38, 10, 'Stock',       1, 0, 'R', true);
$pdf->Cell(38, 10, 'Stock min.',  1, 1, 'R', true);

/* ── Filas ── */
$pdf->SetFont('Helvetica', '', 10);
foreach ($materiales as $row) {
    $alerta = (float)$row['stock'] <= (float)$row['stock_min'];
    if ($alerta) { $pdf->SetFillColor(255, 220, 220); $fill = true; }
    else          { $fill = false; }

    $pdf->Cell(70, 8, to_latin1($row['nombre']),                 1, 0, 'L', $fill);
    $pdf->Cell(55, 8, to_latin1($row['categoria']),              1, 0, 'L', $fill);
    $pdf->Cell(25, 8, to_latin1($row['unidad']),                 1, 0, 'C', $fill);
    $pdf->Cell(40, 8, '$' . number_format($row['precio_kg'], 2), 1, 0, 'R', $fill);
    $pdf->Cell(38, 8, number_format($row['stock'], 2),           1, 0, 'R', $fill);
    $pdf->Cell(38, 8, number_format($row['stock_min'], 2),       1, 1, 'R', $fill);

    if ($alerta) { $pdf->SetFillColor(232, 232, 232); }
}

/* ── Totales ── */
$total_materiales = count($materiales);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Ln(3);
$pdf->Cell(0, 8, 'Total de materiales registrados: ' . $total_materiales, 0, 1, 'R');

/* ── Leyenda ── */
$pdf->Ln(3);
$pdf->SetFillColor(255, 220, 220);
$pdf->Cell(8, 6, '', 1, 0, 'C', true);
$pdf->SetFont('Helvetica', 'I', 9);
$pdf->Cell(0, 6, '  Stock en nivel de alerta (igual o menor al minimo)', 0, 1, 'L');

ob_end_clean();
$pdf->Output('I', 'Reporte_Materiales_' . date('Ymd') . '.pdf');