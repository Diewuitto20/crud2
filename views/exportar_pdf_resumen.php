<?php

ob_start();
session_start();
date_default_timezone_set('America/Mexico_City');

if (empty($_SESSION['autenticado'])) {
    http_response_code(403);
    exit('Acceso no autorizado');
}
require_once __DIR__ . '/permisos.php';
if (!es_dueno()) { http_response_code(403); exit('Solo el dueño puede generar este reporte'); }

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

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/fpdf.php';
require_once __DIR__ . '/../models/LogsModel.php';

const LIMITE_DETALLE = 300;

function l(string $s): string { return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'); }
function m(float $v): string  { return '$' . number_format($v, 2); }
function n(float $v): string  { return number_format($v, 2); }
function cortar(string $s, int $max): string { return mb_strimwidth($s, 0, $max, '...', 'UTF-8'); }
function fmt(string $f): string { return date('d/m/Y', strtotime($f)); }
function variacion(float $act, float $ant): string {
    if ($ant == 0) return 'n/d';
    $v = (($act - $ant) / abs($ant)) * 100;
    return ($v >= 0 ? '+' : '') . number_format($v, 1) . '%';
}
function fechaValida(string $f): bool {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)
        && checkdate((int)substr($f, 5, 2), (int)substr($f, 8, 2), (int)substr($f, 0, 4));
}

/* ── Periodo ── */
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
if (!fechaValida($desde)) $desde = date('Y-m-01');
if (!fechaValida($hasta)) $hasta = date('Y-m-d');
if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

$dias = (int)((strtotime($hasta) - strtotime($desde)) / 86400) + 1;
if ($dias > 3660) { http_response_code(400); exit('El periodo máximo es de 10 años'); }

$prevHasta = date('Y-m-d', strtotime("$desde -1 day"));
$prevDesde = date('Y-m-d', strtotime("$prevHasta -" . ($dias - 1) . " days"));

/* ── Consultas ── */
function totCompras(PDO $pdo, string $d, string $h): array {
    $s = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(kilos),0) kg, COALESCE(SUM(dinero_recibido),0) monto
                        FROM gestion_compras WHERE DATE(fecha) BETWEEN :d AND :h");
    $s->execute([':d' => $d, ':h' => $h]);
    return array_map('floatval', $s->fetch());
}
function totVentas(PDO $pdo, string $d, string $h): array {
    $s = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(total),0) monto
                        FROM ventas WHERE estado='Activa' AND DATE(fecha) BETWEEN :d AND :h");
    $s->execute([':d' => $d, ':h' => $h]);
    return array_map('floatval', $s->fetch());
}
function leerJson(string $archivo): array {
    if (!file_exists($archivo)) return [];
    $x = json_decode(file_get_contents($archivo), true);
    return is_array($x) ? $x : [];
}
function resumen(PDO $pdo, string $d, string $h): array {
    $c = totCompras($pdo, $d, $h);
    $v = totVentas($pdo, $d, $h);
    $ingresos = $v['monto'];
    return [
        'kg_compras'    => $c['kg'],
        'monto_compras' => $c['monto'],
        'ingresos'      => $ingresos,
        'ganancia'      => $ingresos - $c['monto'],
        'operaciones'   => $c['n'] + $v['n'],
        'n_compras'     => $c['n'],
        'n_ventas'      => $v['n'],
        'monto_ventas'  => $v['monto'],
    ];
}

$act  = resumen($pdo, $desde, $hasta);
$prev = resumen($pdo, $prevDesde, $prevHasta);

function consulta(PDO $pdo, string $sql, array $p): array {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s->fetchAll();
}
$rango = [':d' => $desde, ':h' => $hasta];

$porClasif = consulta($pdo, "SELECT clasificacion nombre, COUNT(*) ops, SUM(kilos) kg, SUM(dinero_recibido) monto
                 FROM gestion_compras WHERE DATE(fecha) BETWEEN :d AND :h
                 GROUP BY clasificacion ORDER BY kg DESC", $rango);
$porEmpresa = consulta($pdo, "SELECT nombre_empresa nombre, COUNT(*) ops, SUM(kilos) kg, SUM(dinero_recibido) monto
                  FROM gestion_compras WHERE DATE(fecha) BETWEEN :d AND :h
                  GROUP BY nombre_empresa ORDER BY kg DESC", $rango);
$comprasDet = consulta($pdo, "SELECT fecha, nombre_empresa, clasificacion, kilos, dinero_recibido
                  FROM gestion_compras WHERE DATE(fecha) BETWEEN :d AND :h
                  ORDER BY fecha DESC, id_gestion_venta DESC LIMIT " . LIMITE_DETALLE, $rango);
$ventasDet = consulta($pdo, "SELECT id_ventas, fecha, clasificacion, total
                 FROM ventas WHERE estado='Activa' AND DATE(fecha) BETWEEN :d AND :h
                 ORDER BY fecha DESC, id_ventas DESC LIMIT " . LIMITE_DETALLE, $rango);
$existencias = existencias_leer();
$materiales = array_map(fn($m) => [
    'nombre' => $m['nombre'], 'categoria' => $m['categoria'], 'unidad' => $m['unidad'], 'precio_kg' => $m['precio_kg'],
    'stock' => $existencias[$m['id_material']]['existencia'] ?? 0, 'stock_min' => $m['stock_min'],
], materiales_leer());

/* Metas cuyo mes cae dentro del periodo */
$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$ymD = (int)substr($desde, 0, 4) * 12 + (int)substr($desde, 5, 2);
$ymH = (int)substr($hasta, 0, 4) * 12 + (int)substr($hasta, 5, 2);
$tickets = array_map(fn($c) => ['material' => $c['clasificacion'], 'peso' => $c['kilos'], 'fecha' => $c['fecha']],
                     consulta($pdo, "SELECT clasificacion, kilos, fecha FROM gestion_compras", []));
$metas = [];
foreach (leerJson(__DIR__ . '/metas.json') as $mt) {
    $ym = (int)$mt['año'] * 12 + (int)$mt['mes'];
    if ($ym < $ymD || $ym > $ymH) continue;
    $real = 0;
    foreach ($tickets as $t) {
        if (($t['material'] ?? '') === $mt['material']
            && (int)date('n', strtotime($t['fecha'])) === (int)$mt['mes']
            && (int)date('Y', strtotime($t['fecha'])) === (int)$mt['año']) {
            $real += (float)$t['peso'];
        }
    }
    $metas[] = ['nombre' => $mt['nombre'], 'material' => $mt['material'],
                'periodo' => $meses[(int)$mt['mes']] . ' ' . $mt['año'],
                'meta' => (float)$mt['meta_kg'], 'real' => $real];
}

/* ── PDF ── */
class ResumenPDF extends FPDF {
    public string $periodo = '';

    function Header() {
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(0, 9, l('RESUMEN DE OPERACIONES'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 6, l('Periodo: ' . $this->periodo), 0, 1, 'C');
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 5, l('Generado el ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(3);
    }
    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 8, l('Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }
    function seccion(string $t): void {
        if ($this->GetY() > 165) $this->AddPage();
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetFillColor(46, 125, 50);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, l('  ' . $t), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }
    function subtitulo(string $t): void {
        if ($this->GetY() > 175) $this->AddPage();
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(0, 7, l($t), 0, 1);
    }
    private function cabecera(array $cab, array $w, array $al): void {
        $this->SetFillColor(232, 232, 232);
        $this->SetFont('Helvetica', 'B', 9);
        foreach ($cab as $i => $c) $this->Cell($w[$i], 8, l($c), 1, 0, $al[$i], true);
        $this->Ln();
        $this->SetFont('Helvetica', '', 9);
    }
    function tabla(array $cab, array $w, array $al, array $filas, ?array $pie = null, array $resaltar = []): void {
        $this->cabecera($cab, $w, $al);
        if (!$filas) {
            $this->Cell(array_sum($w), 7, l('Sin registros en el periodo'), 1, 1, 'C');
        }
        foreach ($filas as $k => $f) {
            if ($this->GetY() + 7 > $this->PageBreakTrigger) {
                $this->AddPage();
                $this->cabecera($cab, $w, $al);
            }
            $fill = in_array($k, $resaltar, true);
            $this->SetFillColor(255, 220, 220);
            foreach ($f as $i => $v) $this->Cell($w[$i], 7, l((string)$v), 1, 0, $al[$i], $fill);
            $this->Ln();
        }
        if ($pie) {
            if ($this->GetY() + 7 > $this->PageBreakTrigger) $this->AddPage();
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetFillColor(232, 232, 232);
            foreach ($pie as $i => $v) $this->Cell($w[$i], 7, l((string)$v), 1, 0, $al[$i], true);
            $this->Ln();
            $this->SetFont('Helvetica', '', 9);
        }
        $this->Ln(4);
    }
    function kpi(float $x, float $y, float $w, string $titulo, string $valor, string $var = ''): void {
        $this->SetXY($x, $y);
        $this->SetDrawColor(180, 180, 180);
        $this->Rect($x, $y, $w, 24);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell($w, 7, l($titulo), 0, 2, 'C');
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($w, 8, l($valor), 0, 2, 'C');
        $this->SetFont('Helvetica', 'I', 8);
        if (strpos($var, '-') === 0) $this->SetTextColor(200, 40, 40);
        else                         $this->SetTextColor(46, 125, 50);
        $this->Cell($w, 6, l($var), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
    }
}

$pdf = new ResumenPDF('L', 'mm', 'A4');
$pdf->periodo = fmt($desde) . ' al ' . fmt($hasta);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AliasNbPages();
$pdf->AddPage();

/* 1. KPIs */
$pdf->seccion('1. Indicadores del periodo');
$y = $pdf->GetY();
$w = (277 - 5 * 3) / 6;
$k = [
    ['Kg comprados',                n($act['kg_compras']) . ' kg', variacion($act['kg_compras'], $prev['kg_compras'])],
    ['Monto de compras',            m($act['monto_compras']),      variacion($act['monto_compras'], $prev['monto_compras'])],
    ['Ingresos (ventas)', m($act['ingresos']),           variacion($act['ingresos'], $prev['ingresos'])],
    ['Ganancia neta',               m($act['ganancia']),           variacion($act['ganancia'], $prev['ganancia'])],
    ['Operaciones',                 (string)(int)$act['operaciones'], variacion($act['operaciones'], $prev['operaciones'])],
];
foreach ($k as $i => $c) $pdf->kpi(10 + $i * ($w + 3), $y, $w, $c[0], $c[1], $c[2]);
$pdf->SetY($y + 28);

$pdf->subtitulo('Comparativo contra el periodo anterior (' . fmt($prevDesde) . ' al ' . fmt($prevHasta) . ')');
$filas = [];
foreach ([
    ['Kg comprados', 'kg_compras', false], ['Monto de compras', 'monto_compras', true],
    ['Ventas', 'monto_ventas', true],
    ['Ganancia neta', 'ganancia', true], ['Operaciones', 'operaciones', false],
] as [$lbl, $key, $dinero]) {
    $f = fn($v) => $dinero ? m($v) : ($key === 'operaciones' ? (string)(int)$v : n($v));
    $filas[] = [$lbl, $f($act[$key]), $f($prev[$key]), variacion($act[$key], $prev[$key])];
}
$pdf->tabla(['Concepto', 'Periodo actual', 'Periodo anterior', 'Variación'],
            [117, 55, 55, 50], ['L', 'R', 'R', 'R'], $filas);
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->Cell(0, 5, l('Ingresos = ventas activas. Ganancia neta = ingresos - monto de compras.'), 0, 1);

/* 2. Compras */
$pdf->AddPage();
$pdf->seccion('2. Compras');
$totKg = (float)$act['kg_compras'];
$agrupa = function (array $rows) use ($totKg) {
    return array_map(fn($r) => [cortar($r['nombre'], 55), (int)$r['ops'], n((float)$r['kg']), m((float)$r['monto']),
        $totKg > 0 ? number_format($r['kg'] / $totKg * 100, 1) . '%' : '-'], $rows);
};
$pieAg = ['Total', (int)$act['n_compras'], n($totKg), m($act['monto_compras']), $totKg > 0 ? '100%' : '-'];
$wAg = [107, 40, 50, 50, 30];
$alAg = ['L', 'R', 'R', 'R', 'R'];
$pdf->subtitulo('Subtotales por clasificación');
$pdf->tabla(['Clasificación', 'Operaciones', 'Kg', 'Monto', '% kg'], $wAg, $alAg, $agrupa($porClasif), $pieAg);
$porEmpresa = array_values(array_filter($porEmpresa, fn($r) => trim((string)$r['nombre']) !== ''));
if ($porEmpresa) {
    $pdf->subtitulo('Subtotales por empresa (solo compras con proveedor registrado)');
    $pdf->tabla(['Empresa', 'Operaciones', 'Kg', 'Monto', '% kg'], $wAg, $alAg, $agrupa($porEmpresa), $pieAg);
}

$pdf->subtitulo('Detalle de compras' . ($act['n_compras'] > LIMITE_DETALLE ? ' (últimas ' . LIMITE_DETALLE . ' de ' . (int)$act['n_compras'] . '; los subtotales incluyen todas)' : ''));
$filas = array_map(fn($r) => [fmt($r['fecha']), cortar($r['nombre_empresa'] ?: '-', 55), cortar($r['clasificacion'], 30),
    n((float)$r['kilos']), m((float)$r['dinero_recibido'])], $comprasDet);
$pdf->tabla(['Fecha', 'Empresa', 'Clasificación', 'Kg', 'Monto'], [30, 100, 55, 42, 50],
            ['C', 'L', 'L', 'R', 'R'], $filas);

/* 3. Ventas */
$pdf->AddPage();
$pdf->seccion('3. Ventas');
$pdf->subtitulo('Ventas activas' . ($act['n_ventas'] > LIMITE_DETALLE ? ' (últimas ' . LIMITE_DETALLE . ' de ' . (int)$act['n_ventas'] . ')' : ''));
$filas = array_map(fn($r) => ['#' . $r['id_ventas'], fmt($r['fecha']), cortar($r['clasificacion'] ?? '', 70), m((float)$r['total'])], $ventasDet);
$pdf->tabla(['Venta', 'Fecha', 'Clasificación', 'Total'], [40, 45, 130, 62], ['L', 'C', 'L', 'R'],
            $filas, ['Total ventas', '', '', m($act['monto_ventas'])]);

$pdf->subtitulo('Balance del periodo');
$pdf->tabla(['Concepto', 'Monto'], [217, 60], ['L', 'R'], [
    ['Compras', m($act['monto_compras'])],
    ['Ventas', m($act['monto_ventas'])],
], ['Ganancia neta', m($act['ganancia'])]);

/* 4. Inventario */
$pdf->AddPage();
$pdf->seccion('4. Inventario de materiales');
$filas = []; $alerta = [];
foreach ($materiales as $i => $r) {
    $filas[] = [cortar($r['nombre'], 45), cortar($r['categoria'], 30), $r['unidad'], m((float)$r['precio_kg']),
                n((float)$r['stock']), n((float)$r['stock_min'])];
    if ((float)$r['stock'] < (float)$r['stock_min']) $alerta[] = $i;
}
$pdf->tabla(['Nombre', 'Categoría', 'Unidad', 'Precio/kg', 'Existencia', 'Stock mín.'], [80, 55, 25, 40, 38, 39],
            ['L', 'L', 'C', 'R', 'R', 'R'], $filas, null, $alerta);
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->Cell(0, 5, l('Existencia = compras - ventas (histórico). Las filas resaltadas están por debajo del stock mínimo.'), 0, 1);

/* 5. Metas */
$pdf->Ln(4);
$pdf->seccion('5. Cumplimiento de metas');
$filas = array_map(fn($x) => [cortar($x['nombre'], 45), $x['material'], $x['periodo'], n($x['meta']), n($x['real']),
    $x['meta'] > 0 ? min(100, round($x['real'] / $x['meta'] * 100, 1)) . '%' : '-'], $metas);
$pdf->tabla(['Meta', 'Material', 'Mes', 'Objetivo (kg)', 'Avance (kg)', 'Cumplimiento'], [80, 45, 40, 40, 40, 32],
            ['L', 'L', 'C', 'R', 'R', 'R'], $filas);

try { LogsModel::registrar('reportes', 'exportar', "Resumen PDF $desde a $hasta"); } catch (Throwable $e) {}

ob_end_clean();
$pdf->Output('I', "resumen_{$desde}_{$hasta}.pdf");
