<?php
/* =====================================================================
   respaldos.php  –  Gestión de copias de seguridad
   ===================================================================== */
require_once __DIR__ . '/data.php';

$env = require __DIR__ . '/../env.php';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
        $env['DB_USER'], $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

$backupDir = __DIR__ . '/backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

/* ── Clase PDF (debe estar fuera del bloque POST) ── */
if (file_exists(__DIR__ . '/fpdf.php')) {
    require_once __DIR__ . '/fpdf.php';

    if (!class_exists('PDF_Compras')) {
        class PDF_Compras extends FPDF {
            function Header() {
                $this->SetFont('Arial', 'B', 15);
                $this->Cell(60);
                $this->Cell(90, 10, 'REPORTE DE COMPRAS', 1, 0, 'C');
                $this->Ln(20);
                $this->SetFont('Arial', 'I', 10);
                $this->Cell(0, 10, 'Fecha de generacion: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
                $this->Ln(5);
            }
            function Footer() {
                $this->SetY(-15);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            }
        }
    }
}

$msg_ok  = '';
$msg_err = '';

/* Mensajes de éxito al regresar de exportación o importación */
if (($_GET['export'] ?? '') === 'ok') {
    $msg_ok = '✓ Respaldo SQL generado y descargado correctamente.';

    /* Si hay un ZIP pendiente de descarga, enviarlo ahora */
    if (!empty($_SESSION['pending_download'])) {
        $zipPending = $backupDir . $_SESSION['pending_download'];
        if (file_exists($zipPending)) {
            $basename = $_SESSION['pending_download'];
            unset($_SESSION['pending_download']);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $basename . '"');
            header('Content-Length: ' . filesize($zipPending));
            readfile($zipPending);
            @unlink($zipPending);
            exit;
        }
        unset($_SESSION['pending_download']);
    }
}
if (($_GET['import'] ?? '') === 'ok') {
    $msg_ok = '✓ Base de datos restaurada correctamente desde el respaldo SQL.';
}

/* ══════════════════════════════════════════════════════════════════════
   DETECTAR RUTA DE mysqldump (MAMP macOS + fallbacks)
   ══════════════════════════════════════════════════════════════════════ */
function detectar_mysqldump(): string {
    $rutas = [
        '/Applications/MAMP/Library/bin/mysql80/bin/mysqldump',  // MAMP macOS mysql80 ← tu ruta
        '/Applications/MAMP/Library/bin/mysql57/bin/mysqldump',  // MAMP macOS mysql57
        '/Applications/MAMP/Library/bin/mysqldump',              // MAMP macOS (antiguo)
        '/Applications/MAMP PRO/Library/bin/mysqldump',          // MAMP PRO macOS
        '/opt/homebrew/bin/mysqldump',                            // Homebrew Apple Silicon
        '/usr/local/bin/mysqldump',                               // Homebrew Intel / macOS
        '/usr/bin/mysqldump',                                     // Ubuntu/Debian
        '/opt/lampp/bin/mysqldump',                               // XAMPP Linux
        'C:/xampp/mysql/bin/mysqldump.exe',                       // XAMPP Windows
        'C:/wamp64/bin/mysql/mysql8.0.31/bin/mysqldump.exe',     // WAMP Windows
    ];
    foreach ($rutas as $ruta) {
        if (@is_executable($ruta)) {
            return $ruta;
        }
    }
    return 'mysqldump'; // último intento con PATH del sistema
}

function detectar_mysql(): string {
    $rutas = [
        '/Applications/MAMP/Library/bin/mysql80/bin/mysql',  // MAMP macOS mysql80 ← tu ruta
        '/Applications/MAMP/Library/bin/mysql57/bin/mysql',  // MAMP macOS mysql57
        '/Applications/MAMP/Library/bin/mysql',              // MAMP macOS (antiguo)
        '/Applications/MAMP PRO/Library/bin/mysql',          // MAMP PRO macOS
        '/opt/homebrew/bin/mysql',                            // Homebrew Apple Silicon
        '/usr/local/bin/mysql',                               // Homebrew Intel / macOS
        '/usr/bin/mysql',                                     // Ubuntu/Debian
        '/opt/lampp/bin/mysql',                               // XAMPP Linux
        'C:/xampp/mysql/bin/mysql.exe',                       // XAMPP Windows
        'C:/wamp64/bin/mysql/mysql8.0.31/bin/mysql.exe',     // WAMP Windows
    ];
    foreach ($rutas as $ruta) {
        if (@is_executable($ruta)) {
            return $ruta;
        }
    }
    return 'mysql';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';
    $uid    = $_SESSION['id_usuario'] ?? null;

    /* ── 1. Exportar SQL (mysqldump → ZIP con JSONs incluidos) ── */
    if ($accion === 'exportar_sql') {
        $fechaArchivo = date('Ymd_His');
        $sqlFile      = $backupDir . "respaldo_{$fechaArchivo}.sql";
        $zipFile      = $backupDir . "respaldo_{$fechaArchivo}.zip";
        $mysqldump    = detectar_mysqldump();

        /* Archivo temporal de credenciales para evitar el warning de password */
        $cnfFile = $backupDir . '.my_export_' . $fechaArchivo . '.cnf';
        file_put_contents($cnfFile,
            "[mysqldump]\n" .
            "host=" . $env['DB_HOST'] . "\n" .
            "user=" . $env['DB_USER'] . "\n" .
            "password=" . $env['DB_PASS'] . "\n"
        );
        chmod($cnfFile, 0600);

        $comando = sprintf(
            '%s --defaults-extra-file=%s --ignore-table=%s.respaldos %s > %s 2>/dev/null',
            escapeshellarg($mysqldump),
            escapeshellarg($cnfFile),
            escapeshellarg($env['DB_NAME']),
            escapeshellarg($env['DB_NAME']),
            escapeshellarg($sqlFile)
        );

        $output = []; $returnVar = -1;
        exec($comando, $output, $returnVar);
        @unlink($cnfFile);

        if ($returnVar !== 0) {
            if (file_exists($sqlFile)) { @unlink($sqlFile); }
            $detalle  = htmlspecialchars(implode(' | ', $output));
            $msg_err  = "Error al ejecutar mysqldump (código {$returnVar}) usando: {$mysqldump}";
            $msg_err .= $detalle ? " — {$detalle}" : '';
        } elseif (!file_exists($sqlFile)) {
            $msg_err = 'El archivo SQL no fue generado. Verifica que mysqldump esté disponible.';
        } else {
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                /* SQL de MySQL */
                $zip->addFile($sqlFile, 'mysql/' . basename($sqlFile));

                /* Archivos JSON de datos planos — misma carpeta que respaldos.php */
                $jsonFiles = [
                    'materiales.json' => __DIR__ . '/materiales.json',
                    'compras.json'    => __DIR__ . '/compras.json',
                    'eventos.json'    => __DIR__ . '/eventos.json',
                ];
                foreach ($jsonFiles as $nombre => $ruta) {
                    if (file_exists($ruta)) {
                        $zip->addFile($ruta, 'json/' . $nombre);
                    }
                }

                $zip->close();
                @unlink($sqlFile);

                $sizeBytes   = filesize($zipFile);
                $zipBasename = basename($zipFile);

                /* Registrar en BD */
                $pdo->prepare(
                    "INSERT INTO respaldos (tipo_operacion, nombre_archivo, formato, nombre_bd, tamanio_bytes, usuario_id, observaciones)
                     VALUES ('EXPORTACION', :archivo, 'ZIP', :bd, :bytes, :uid, 'Respaldo completo: SQL + JSON exportado desde el panel.')"
                )->execute([
                    ':archivo' => $zipBasename,
                    ':bd'      => $env['DB_NAME'],
                    ':bytes'   => $sizeBytes,
                    ':uid'     => $uid,
                ]);

                /* Guardar nombre en sesión para descarga posterior */
                $_SESSION['pending_download'] = $zipBasename;

                /* Redirigir a la misma página — el historial ya tendrá el nuevo registro */
                header('Location: index.php?menu=respaldos&opc=tabla&export=ok');
                exit;
            } else {
                @unlink($sqlFile);
                $msg_err = 'No se pudo crear el archivo ZIP.';
            }
        }
    }

    /* ── 3. Importar SQL (.zip con .sql adentro) ── */
    if ($accion === 'importar_sql') {
        $file = $_FILES['archivo_sql'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $phpErr  = $file['error'] ?? -1;
            $msg_err = "Error durante la carga del archivo (código PHP: {$phpErr}). Intenta de nuevo.";
        } else {
            $origName = $file['name'];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if ($ext !== 'zip') {
                $msg_err = 'Formato incorrecto. Solo se aceptan archivos .zip generados por esta aplicación.';
            } else {
                $importDir   = $backupDir . 'temp_import/';
                if (!is_dir($importDir)) { mkdir($importDir, 0755, true); }

                $tempZipPath = $importDir . basename($origName);

                if (!move_uploaded_file($file['tmp_name'], $tempZipPath)) {
                    $msg_err = 'No se pudo mover el archivo al directorio de procesamiento.';
                } else {
                    $zip     = new ZipArchive();
                    $sqlEntry = null;

                    if ($zip->open($tempZipPath) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $stat = $zip->statIndex($i);
                            if (strtolower(pathinfo($stat['name'], PATHINFO_EXTENSION)) === 'sql') {
                                $sqlEntry = $stat['name'];
                                break;
                            }
                        }

                        if (!$sqlEntry) {
                            $zip->close();
                            @unlink($tempZipPath);
                            $msg_err = 'El archivo .zip no contiene ningún script .sql válido.';
                        } else {
                            /* Extraer SQL */
                            $zip->extractTo($importDir, $sqlEntry);

                            /* Extraer JSONs si existen en el ZIP */
                            $jsonDestinos = [
                                'json/materiales.json' => __DIR__ . '/materiales.json',
                                'json/compras.json'    => __DIR__ . '/compras.json',
                                'json/eventos.json'    => __DIR__ . '/eventos.json',
                            ];
                            foreach ($jsonDestinos as $zipPath => $destino) {
                                if ($zip->locateName($zipPath) !== false) {
                                    $contenido = $zip->getFromName($zipPath);
                                    if ($contenido !== false) {
                                        file_put_contents($destino, $contenido);
                                    }
                                }
                            }
                            $zip->close();

                            $absoluteSqlPath = $importDir . $sqlEntry;
                            $mysql           = detectar_mysql();

                            /* Archivo temporal de credenciales */
                            $cnfImport = $backupDir . '.my_import_' . time() . '.cnf';
                            file_put_contents($cnfImport,
                                "[client]\n" .
                                "host=" . $env['DB_HOST'] . "\n" .
                                "user=" . $env['DB_USER'] . "\n" .
                                "password=" . $env['DB_PASS'] . "\n"
                            );
                            chmod($cnfImport, 0600);

                            $comando = sprintf(
                                '%s --defaults-extra-file=%s %s < %s 2>&1',
                                escapeshellarg($mysql),
                                escapeshellarg($cnfImport),
                                escapeshellarg($env['DB_NAME']),
                                escapeshellarg($absoluteSqlPath)
                            );
                            $output = []; $returnVar = -1;
                            exec($comando, $output, $returnVar);

                            if (file_exists($absoluteSqlPath)) { @unlink($absoluteSqlPath); }
                            if (file_exists($tempZipPath))     { @unlink($tempZipPath); }
                            if (file_exists($cnfImport))       { @unlink($cnfImport); }
                            if (is_dir($importDir))            { @rmdir($importDir); }

                            if ($returnVar !== 0) {
                                $detalle = htmlspecialchars(implode("\n", $output));
                                $msg_err = "Error al restaurar la BD (código {$returnVar}):\n{$detalle}";
                            } else {
                                $pdo->prepare(
                                    "INSERT INTO respaldos (tipo_operacion, nombre_archivo, formato, nombre_bd, tamanio_bytes, usuario_id, observaciones)
                                     VALUES ('IMPORTACION', :archivo, 'ZIP', :bd, :bytes, :uid, 'Restauración manual de la base de datos desde archivo ZIP.')"
                                )->execute([
                                    ':archivo' => $origName,
                                    ':bd'      => $env['DB_NAME'],
                                    ':bytes'   => $file['size'],
                                    ':uid'     => $uid,
                                ]);
                                header('Location: index.php?menu=respaldos&opc=tabla&import=ok');
                                exit;
                            }
                        }
                    } else {
                        @unlink($tempZipPath);
                        $msg_err = 'No se pudo abrir el archivo .zip. Puede estar dañado o incompleto.';
                    }
                }
            }
        }
    }

    /* ── 4. Eliminar registro ── */
    if ($accion === 'eliminar_respaldo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM respaldos WHERE id=:id")->execute([':id' => $id]);
        }
        header('Location: index.php?menu=respaldos&opc=tabla');
        exit;
    }
}

/* ══ Datos para la vista ══ */
$respaldos = $pdo->query(
    "SELECT r.id, r.tipo_operacion, r.nombre_archivo, r.formato,
            r.nombre_bd, r.tamanio_bytes, r.fechayhora, r.observaciones,
            CONCAT(u.nombre, ' ', u.apellido_paterno) AS creado_por
     FROM respaldos r
     LEFT JOIN usuarios u ON u.id_usuario = r.usuario_id
     ORDER BY r.fechayhora DESC"
)->fetchAll();

$total_respaldos  = count($respaldos);
$ultimo           = $total_respaldos > 0 ? $respaldos[0] : null;
$total_materiales = count(materiales_leer());
$total_compras    = count(compras_leer());
$total_eventos    = count(eventos_leer());
$total_usuarios   = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

$pagina_activa = 'respaldos';
$titulo_pagina = 'Respaldos';
require_once __DIR__ . '/layout_header.php';
?>

<style>
/* ─── Badges extra ─────────────────────────────────────── */
.badge-blue  { background:#E6F1FB; color:#185FA5; }
.badge-amber { background:#FAEEDA; color:#854F0B; }
.badge-gray  { background:#F1EFE8; color:#5F5E5A; font-family:monospace; font-size:11px; }
.badge i     { font-size:11px; margin-right:3px; }

/* ─── Alertas ──────────────────────────────────────────── */
.resp-alert {
    display:flex; align-items:center; gap:10px;
    padding:12px 16px; border-radius:10px;
    margin-bottom:18px; font-size:14px; font-weight:500;
}
.resp-alert.ok  { background:#EAF3DE; color:#3B6D11; border:1px solid #C0DD97; }
.resp-alert.err { background:#FCEBEB; color:#A32D2D; border:1px solid #F09595; }
.resp-alert i   { font-size:16px; flex-shrink:0; }
.resp-alert-close {
    margin-left:auto; background:none; border:none;
    font-size:18px; cursor:pointer; color:inherit; line-height:1;
}

/* ─── Encabezado ───────────────────────────────────────── */
.resp-page-title { font-size:22px; font-weight:600; margin-bottom:3px; }
.resp-page-sub   { font-size:13px; color:var(--text-gray,#6b7280); margin-bottom:1.5rem; }

/* ─── Grid tarjetas ────────────────────────────────────── */
.resp-action-grid {
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:12px; margin-bottom:1.5rem;
}
.resp-action-card {
    border-radius:12px; padding:1rem 1.15rem;
    display:flex; flex-direction:column; gap:12px;
    border:0.5px solid transparent;
}
.resp-action-top {
    display:flex; align-items:flex-start;
    justify-content:space-between; gap:8px;
}
.resp-action-title { font-size:14px; font-weight:600; margin-bottom:3px; }
.resp-action-desc  { font-size:12px; line-height:1.5; opacity:.72; }
.resp-action-icon {
    width:38px; height:38px; border-radius:9px;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; font-size:17px;
}
.resp-action-btn {
    display:inline-flex; align-items:center; gap:6px;
    font-size:13px; font-weight:500;
    padding:7px 14px; border-radius:8px;
    border:none; cursor:pointer; color:#fff;
    transition:opacity .15s, transform .1s;
    width:fit-content;
}
.resp-action-btn:hover  { opacity:.87; }
.resp-action-btn:active { transform:scale(.97); }
.resp-action-btn i      { font-size:13px; }

/* Rojo – Exportar PDF */
.resp-card--red                    { background:#FCEBEB; border-color:#F09595; color:#501313; }
.resp-card--red  .resp-action-icon { background:#F09595; color:#A32D2D; }
.resp-btn--red                     { background:#A32D2D; }

/* Morado */
.resp-card--purple                   { background:#EEEDFE; border-color:#CECBF6; color:#3C3489; }
.resp-card--purple .resp-action-icon { background:#CECBF6; color:#534AB7; }
.resp-btn--purple                    { background:#534AB7; }
/* Ámbar */
.resp-card--amber                    { background:#FAEEDA; border-color:#FAC775; color:#633806; }
.resp-card--amber .resp-action-icon  { background:#FAC775; color:#854F0B; }
.resp-btn--amber                     { background:#BA7517; }

/* ─── Resumen ──────────────────────────────────────────── */
.resp-summary-card {
    background:var(--bg-white,#fff);
    border:0.5px solid var(--border,#e5e7eb);
    border-radius:12px; padding:1.25rem; margin-bottom:1.5rem;
}
.resp-summary-heading {
    font-size:11px; font-weight:600; text-transform:uppercase;
    letter-spacing:.06em; color:var(--text-gray,#6b7280); margin-bottom:1rem;
}
.resp-summary-grid {
    display:grid; grid-template-columns:repeat(4,1fr);
    gap:10px; margin-bottom:1rem;
}
.resp-summary-item {
    background:var(--bg-light,#f9fafb);
    border-radius:8px; padding:.75rem 1rem; text-align:center;
}
.resp-summary-label { font-size:11px; color:var(--text-gray,#6b7280); margin-bottom:4px; }
.resp-summary-value { font-size:26px; font-weight:600; }
.resp-summary-last {
    font-size:12px; color:var(--text-gray,#6b7280);
    display:flex; align-items:center; gap:6px;
    padding-top:.75rem; border-top:0.5px solid var(--border,#e5e7eb);
}
.resp-summary-last i { font-size:14px; }

/* ─── Tabla historial ──────────────────────────────────── */
.resp-table-card {
    background:var(--bg-white,#fff);
    border:0.5px solid var(--border,#e5e7eb);
    border-radius:12px; overflow:hidden;
}
.resp-table-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:1rem 1.25rem;
    border-bottom:0.5px solid var(--border,#e5e7eb);
}
.resp-table-title {
    font-size:15px; font-weight:600;
    display:flex; align-items:center; gap:8px;
}
.resp-table-title i { color:var(--text-gray,#6b7280); }

/* ─── Advertencia en modales ───────────────────────────── */
.import-warning {
    display:flex; gap:10px; align-items:flex-start;
    background:#FAEEDA; border:1px solid #FAC775;
    border-radius:8px; padding:12px 14px;
    font-size:13px; color:#633806; line-height:1.5;
}
.import-warning i { margin-top:2px; flex-shrink:0; }

/* ─── Input archivo ────────────────────────────────────── */
.input-file {
    width:100%; padding:8px;
    border:1.5px dashed var(--border,#e5e7eb);
    border-radius:8px; font-size:13px;
    cursor:pointer; background:var(--bg-light,#f9fafb);
}
.form-hint { font-size:11px; color:var(--text-gray,#6b7280); margin-top:4px; display:block; }

/* ─── Overlay confirmación ─────────────────────────────── */
.confirm-overlay {
    position:fixed; inset:0;
    background:rgba(0,0,0,.45);
    z-index:999; display:none;
    align-items:center; justify-content:center;
}
.confirm-box {
    background:var(--bg-white,#fff);
    border-radius:14px; padding:2rem;
    max-width:380px; width:90%;
    text-align:center;
    box-shadow:0 8px 32px rgba(0,0,0,.12);
}
.confirm-icon {
    width:52px; height:52px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 1rem; font-size:22px;
}
.confirm-icon--red { background:#FCEBEB; color:#A32D2D; }
.confirm-title { font-size:16px; font-weight:600; margin-bottom:8px; }
.confirm-msg   { font-size:13px; color:var(--text-gray,#6b7280); line-height:1.6; margin-bottom:1.5rem; }
.confirm-btns  { display:flex; gap:10px; justify-content:center; }
</style>

<?php if ($msg_ok): ?>
<div class="resp-alert ok">
    <i class="fa-solid fa-circle-check"></i>
    <?= htmlspecialchars($msg_ok) ?>
    <button class="resp-alert-close" onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>
<?php if ($msg_err): ?>
<div class="resp-alert err">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <?= htmlspecialchars($msg_err) ?>
    <button class="resp-alert-close" onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>

<h2 class="resp-page-title">Respaldos</h2>
<p class="resp-page-sub">Gestión de copias de seguridad</p>

<!-- ── Tarjetas de acción ── -->
<div class="resp-action-grid">

    <!-- Exportar PDF -->
    <div class="resp-action-card resp-card--red">
        <div class="resp-action-top">
            <div>
                <p class="resp-action-title">Exportar PDF</p>
                <p class="resp-action-desc">Genera un reporte de materiales en formato PDF</p>
            </div>
            <div class="resp-action-icon"><i class="fa-solid fa-file-pdf"></i></div>
        </div>
        <a href="exportar_pdf_compras.php" target="_blank" class="resp-action-btn resp-btn--red">
            <i class="fa-solid fa-download"></i> Exportar PDF
        </a>
    </div>

    <!-- Exportar SQL -->
    <div class="resp-action-card resp-card--purple">
        <div class="resp-action-top">
            <div>
                <p class="resp-action-title">Exportar base de datos</p>
                <p class="resp-action-desc">Genera un volcado SQL completo comprimido en .zip</p>
            </div>
            <div class="resp-action-icon"><i class="fa-solid fa-database"></i></div>
        </div>
        <form method="POST" action="index.php?menu=respaldos&opc=tabla">
            <input type="hidden" name="action" value="exportar_sql">
            <button type="submit" class="resp-action-btn resp-btn--purple"
                    onclick="return mostrarConfirmExportar(event)">
                <i class="fa-solid fa-file-zipper"></i> Exportar SQL (.zip)
            </button>
        </form>
    </div>

    <!-- Importar SQL -->
    <div class="resp-action-card resp-card--amber">
        <div class="resp-action-top">
            <div>
                <p class="resp-action-title">Restaurar BD desde SQL</p>
                <p class="resp-action-desc">Sube el .zip con el respaldo SQL para restaurar la base de datos</p>
            </div>
            <div class="resp-action-icon"><i class="fa-solid fa-upload"></i></div>
        </div>
        <button type="button" class="resp-action-btn resp-btn--amber"
                onclick="openModal('modalImportarSQL')">
            <i class="fa-solid fa-database"></i> Importar SQL
        </button>
    </div>

</div>

<!-- ── Resumen de datos ── -->
<div class="resp-summary-card">
    <p class="resp-summary-heading">Resumen de datos</p>
    <div class="resp-summary-grid">
        <div class="resp-summary-item">
            <p class="resp-summary-label">Materiales</p>
            <p class="resp-summary-value"><?= $total_materiales ?></p>
        </div>
        <div class="resp-summary-item">
            <p class="resp-summary-label">Compras</p>
            <p class="resp-summary-value"><?= $total_compras ?></p>
        </div>
        <div class="resp-summary-item">
            <p class="resp-summary-label">Eventos</p>
            <p class="resp-summary-value"><?= $total_eventos ?></p>
        </div>
        <div class="resp-summary-item">
            <p class="resp-summary-label">Usuarios</p>
            <p class="resp-summary-value"><?= $total_usuarios ?></p>
        </div>
    </div>
    <?php if ($ultimo): ?>
    <p class="resp-summary-last">
        <i class="fa-regular fa-clock"></i>
        Último respaldo: <?= htmlspecialchars(date('Y-m-d', strtotime($ultimo['fechayhora']))) ?>
        a las <?= htmlspecialchars(date('H:i:s', strtotime($ultimo['fechayhora']))) ?>
        <?php if ($ultimo['creado_por']): ?>
            &nbsp;·&nbsp; por <strong><?= htmlspecialchars($ultimo['creado_por']) ?></strong>
        <?php endif; ?>
    </p>
    <?php endif; ?>
</div>

<!-- ── Historial ── -->
<div class="resp-table-card">
    <div class="resp-table-header">
        <h3 class="resp-table-title">
            <i class="fa-solid fa-clock-rotate-left"></i> Historial de respaldos
        </h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Archivo</th>
                    <th>Formato</th>
                    <th>Tamaño</th>
                    <th>Fecha y hora</th>
                    <th>Usuario</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($respaldos)): ?>
                <tr><td colspan="9" class="empty-cell">Sin registros de respaldo aún.</td></tr>
                <?php else: ?>
                <?php $num = 1; foreach ($respaldos as $r):
                    $esExport  = $r['tipo_operacion'] === 'EXPORTACION';
                    $bytes     = (int)($r['tamanio_bytes'] ?? 0);
                    $tamano    = $bytes >= 1048576
                                 ? round($bytes / 1048576, 1) . ' MB'
                                 : ($bytes > 0 ? round($bytes / 1024, 1) . ' KB' : '—');
                ?>
                <tr>
                    <td><?= $num++ ?></td>
                    <td>
                        <span class="badge <?= $esExport ? 'badge-blue' : 'badge-amber' ?>">
                            <i class="fa-solid <?= $esExport ? 'fa-download' : 'fa-upload' ?>"></i>
                            <?= htmlspecialchars($r['tipo_operacion']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px;font-family:monospace">
                        <?= htmlspecialchars($r['nombre_archivo'] ?? '—') ?>
                    </td>
                    <td>
                        <span class="badge badge-gray"><?= htmlspecialchars($r['formato'] ?? '—') ?></span>
                    </td>
                    <td style="font-size:12px"><?= $tamano ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($r['fechayhora'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge-green">
                            <?= htmlspecialchars($r['creado_por'] ?? 'Sistema') ?>
                        </span>
                    </td>
                    <td style="font-size:12px;max-width:200px;white-space:nowrap;
                               overflow:hidden;text-overflow:ellipsis"
                        title="<?= htmlspecialchars($r['observaciones'] ?? '') ?>">
                        <?= htmlspecialchars($r['observaciones'] ?? '—') ?>
                    </td>
                    <td>
                        <form id="formEliminarResp_<?= (int)$r['id'] ?>"
                              method="POST" action="index.php?menu=respaldos&opc=tabla"
                              style="display:inline">
                            <input type="hidden" name="action" value="eliminar_respaldo">
                            <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                        </form>
                        <button type="button" class="btn-icon danger" title="Eliminar"
                                onclick="confirmarEliminarResp(<?= (int)$r['id'] ?>)">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ MODALES ══ -->

<!-- Importar SQL -->
<div class="modal-overlay" id="modalImportarSQL">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fa-solid fa-database"></i> Restaurar BD desde SQL</h3>
            <button class="modal-close" onclick="closeModal('modalImportarSQL')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="index.php?menu=respaldos&opc=tabla"
              enctype="multipart/form-data">
            <input type="hidden" name="action" value="importar_sql">
            <div class="modal-body">
                <div class="import-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <strong>Advertencia:</strong> Esta acción sobreescribirá la base de datos
                        actual con el contenido del respaldo. Asegúrate de tener un respaldo
                        reciente antes de continuar.
                    </div>
                </div>
                <div class="form-row" style="margin-top:14px">
                    <label>Archivo ZIP con respaldo SQL *</label>
                    <input type="file" name="archivo_sql" accept=".zip" required class="input-file">
                    <small class="form-hint">Solo archivos .zip generados por esta aplicación.</small>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="closeModal('modalImportarSQL')">Cancelar</button>
                <button type="submit" class="btn-danger"
                        onclick="return confirmarImportacion(event, 'modalImportarSQL')">
                    <i class="fa-solid fa-rotate-left"></i> Restaurar base de datos
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Confirmación genérica -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon confirm-icon--red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <p class="confirm-title" id="confirmTitle">¿Estás seguro?</p>
        <p class="confirm-msg"   id="confirmMsg">Esta acción no se puede deshacer.</p>
        <div class="confirm-btns">
            <button class="btn-cancel" id="confirmCancel">Cancelar</button>
            <button class="btn-danger" id="confirmOk">Confirmar</button>
        </div>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function mostrarConfirm({ title, msg, onOk }) {
    const overlay = document.getElementById('confirmOverlay');
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent   = msg;
    overlay.style.display = 'flex';

    const okBtn     = document.getElementById('confirmOk');
    const cancelBtn = document.getElementById('confirmCancel');

    const cleanup = () => {
        overlay.style.display = 'none';
        okBtn.replaceWith(okBtn.cloneNode(true));
        cancelBtn.replaceWith(cancelBtn.cloneNode(true));
    };

    document.getElementById('confirmOk').addEventListener('click',
        () => { cleanup(); onOk(); }, { once: true });
    document.getElementById('confirmCancel').addEventListener('click',
        cleanup, { once: true });
}

function mostrarConfirmExportar(e) {
    e.preventDefault();
    const form = e.target.closest('form');
    mostrarConfirm({
        title: '¿Exportar base de datos?',
        msg:   'Se generará un archivo .zip con el volcado SQL completo de la base de datos.',
        onOk:  () => { form.onsubmit = null; form.submit(); }
    });
    return false;
}

function confirmarImportacion(e, modalId) {
    e.preventDefault();
    const form = e.target.closest('form');
    const file = form.querySelector('input[type="file"]').files[0];
    if (!file) { alert('Selecciona un archivo primero.'); return false; }
    closeModal(modalId);
    mostrarConfirm({
        title: '¿Restaurar datos?',
        msg:   `Se restaurarán los datos desde "${file.name}". Los datos actuales serán reemplazados. Esta acción no se puede deshacer.`,
        onOk:  () => { form.onsubmit = null; form.submit(); }
    });
    return false;
}

function confirmarEliminarResp(id) {
    mostrarConfirm({
        title: '¿Eliminar registro?',
        msg:   'Se eliminará este registro del historial de respaldos.',
        onOk:  () => {
            const f = document.getElementById('formEliminarResp_' + id);
            f.onsubmit = null;
            f.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/layout_footer.php'; ?>