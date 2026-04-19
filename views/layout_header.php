<?php
/* layout_header.php
   Variables esperadas antes del include:
     $pagina_activa  → 'usuarios' | 'material' | 'calendario' | 'compras'
     $titulo_pagina  → string
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?> – Recicladora Diaz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg:          #f0f2f5;
            --white:       #ffffff;
            --sidebar-w:   260px;
            --green-dark:  #1a5632;
            --green-mid:   #2e7d52;
            --green-light: #e8f5ee;
            --text-dark:   #1f2937;
            --text-gray:   #6b7280;
            --border:      #e5e7eb;
            --shadow-sm:   0 1px 4px rgba(0,0,0,.07);
            --shadow-md:   0 4px 16px rgba(0,0,0,.10);
            --radius-pill: 30px;
            --radius-card: 14px;
            --transition:  .2s ease;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Roboto',sans-serif; background:var(--bg); color:var(--text-dark); display:flex; height:100vh; overflow:hidden; }

        /* SIDEBAR */
        .sidebar { width:var(--sidebar-w); background:var(--white); height:100%; display:flex; flex-direction:column; padding:24px 16px; box-shadow:2px 0 12px rgba(0,0,0,.06); transition:transform var(--transition); z-index:100; }
        .sidebar.hidden { transform:translateX(calc(-1 * var(--sidebar-w))); position:absolute; }
        .logo-wrap { background:var(--green-dark); border-radius:12px; padding:18px 12px; text-align:center; margin-bottom:32px; }
        .logo-wrap .logo-icon { font-size:28px; color:#fff; margin-bottom:6px; }
        .logo-wrap .logo-text { color:#fff; font-weight:700; font-size:15px; line-height:1.3; }
        .logo-wrap .logo-sub  { color:rgba(255,255,255,.65); font-size:11px; margin-top:2px; }
        .nav-menu { list-style:none; flex:1; }
        .nav-menu li { margin-bottom:4px; }
        .nav-menu a { display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:10px; font-size:15px; color:var(--text-gray); text-decoration:none; transition:background var(--transition), color var(--transition); }
        .nav-menu a i { width:18px; text-align:center; font-size:16px; }
        .nav-menu a:hover { background:#f3f4f6; color:var(--text-dark); }
        .nav-menu a.active { background:var(--green-light); color:var(--green-dark); font-weight:500; }
        .sidebar-footer { border-top:1px solid var(--border); padding-top:16px; font-size:13px; color:var(--text-gray); text-align:center; }

        /* MAIN */
        .main-content { flex:1; display:flex; flex-direction:column; overflow:hidden; }
        .top-bar { background:var(--white); display:flex; justify-content:space-between; align-items:center; padding:14px 28px; border-bottom:1px solid var(--border); box-shadow:var(--shadow-sm); gap:16px; }
        .top-left { display:flex; align-items:center; gap:16px; }
        .menu-btn { font-size:20px; cursor:pointer; color:var(--text-gray); transition:color var(--transition); background:none; border:none; }
        .menu-btn:hover { color:var(--text-dark); }
        .search-bar { display:flex; align-items:center; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-pill); padding:8px 16px; width:280px; transition:border-color var(--transition); }
        .search-bar:focus-within { border-color:var(--green-mid); background:#fff; }
        .search-bar i { color:var(--text-gray); font-size:14px; margin-right:10px; }
        .search-bar input { border:none; outline:none; font-size:14px; width:100%; background:transparent; }
        .top-right { display:flex; align-items:center; gap:16px; }
        .user-badge { display:flex; align-items:center; gap:10px; font-size:14px; color:var(--text-dark); }
        .user-avatar { width:34px; height:34px; border-radius:50%; background:var(--green-dark); color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; }
        .logout-btn { display:flex; align-items:center; gap:6px; font-size:14px; color:var(--text-gray); text-decoration:none; padding:7px 14px; border-radius:8px; border:1px solid var(--border); transition:all var(--transition); }
        .logout-btn:hover { background:#fef2f2; color:#dc2626; border-color:#fca5a5; }

        /* SECCIONES */
        .sections-wrap { flex:1; overflow-y:auto; padding:28px 32px; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .section-title { font-size:22px; font-weight:600; color:var(--text-dark); }

        /* TARJETAS */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:24px; }
        .stat-card { background:var(--white); border-radius:var(--radius-card); padding:18px 20px; border:1px solid var(--border); }
        .stat-card .stat-label { font-size:12px; color:var(--text-gray); margin-bottom:6px; }
        .stat-card .stat-value { font-size:26px; font-weight:600; color:var(--text-dark); }
        .stat-card .stat-icon  { font-size:20px; color:var(--green-mid); float:right; margin-top:-4px; }

        /* TABLA */
        .table-card { background:var(--white); border-radius:var(--radius-card); border:1px solid var(--border); overflow:hidden; box-shadow:var(--shadow-sm); }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th { background:#f9fafb; font-size:13px; font-weight:500; color:var(--text-gray); text-align:left; padding:12px 16px; border-bottom:1px solid var(--border); text-transform:uppercase; letter-spacing:.4px; }
        .data-table td { padding:13px 16px; font-size:14px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:#fafafa; }
        .badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; }
        .badge-green { background:#dcfce7; color:#15803d; }
        .badge-gray  { background:#f3f4f6; color:#6b7280; }
        .btn-icon { background:none; border:none; cursor:pointer; width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:14px; color:var(--text-gray); transition:background var(--transition), color var(--transition); }
        .btn-icon:hover { background:var(--green-light); color:var(--green-dark); }
        .btn-icon.danger:hover { background:#fef2f2; color:#dc2626; }
        .email-link { color:var(--green-mid); text-decoration:none; font-size:13px; }
        .email-link:hover { text-decoration:underline; }

        /* BOTÓN PRIMARIO */
        .btn-primary { display:inline-flex; align-items:center; gap:8px; background:var(--green-dark); color:#fff; border:none; padding:10px 20px; border-radius:var(--radius-pill); font-size:14px; font-weight:500; cursor:pointer; transition:background var(--transition), box-shadow var(--transition); box-shadow:var(--shadow-sm); }
        .btn-primary:hover { background:var(--green-mid); box-shadow:var(--shadow-md); }
        .btn-primary:active { transform:scale(.97); }

        /* CALENDARIO */
        .cal-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .cal-nav { display:flex; align-items:center; gap:10px; }
        .cal-nav-btn { background:var(--white); border:1px solid var(--border); width:34px; height:34px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px; color:var(--text-gray); transition:all var(--transition); }
        .cal-nav-btn:hover { background:var(--green-light); color:var(--green-dark); border-color:var(--green-mid); }
        .cal-month { font-size:20px; font-weight:600; color:var(--text-dark); min-width:160px; text-align:center; }
        .cal-grid { background:var(--white); border-radius:var(--radius-card); border:1px solid var(--border); overflow:hidden; box-shadow:var(--shadow-sm); }
        .cal-grid table { width:100%; border-collapse:collapse; }
        .cal-grid th { background:var(--green-dark); color:#fff; text-align:center; padding:10px 0; font-size:12px; font-weight:500; letter-spacing:.5px; }
        .cal-grid td { border:1px solid #f0f2f5; height:90px; padding:8px; vertical-align:top; font-size:13px; color:var(--text-gray); width:14.28%; }
        .cal-grid td.today { background:var(--green-light); color:var(--green-dark); font-weight:700; }
        .cal-grid td.today .day-num { background:var(--green-dark); color:#fff; width:24px; height:24px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; }
        .day-num { display:inline-block; }

        /* MODAL */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; justify-content:center; align-items:center; z-index:1000; backdrop-filter:blur(2px); }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--white); border-radius:16px; width:min(480px,92vw); box-shadow:var(--shadow-md); overflow:hidden; animation:fadeUp .2s ease; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }
        .modal-head { display:flex; justify-content:space-between; align-items:center; padding:18px 24px; border-bottom:1px solid var(--border); }
        .modal-head h3 { font-size:17px; font-weight:600; }
        .modal-close { background:none; border:none; cursor:pointer; width:30px; height:30px; border-radius:8px; font-size:16px; color:var(--text-gray); display:flex; align-items:center; justify-content:center; transition:background var(--transition); }
        .modal-close:hover { background:#f3f4f6; color:var(--text-dark); }
        .modal-body { padding:24px; display:flex; flex-direction:column; gap:14px; }
        .form-row { display:flex; flex-direction:column; gap:5px; }
        .form-row label { font-size:13px; color:var(--text-gray); font-weight:500; }
        .form-row input, .form-row select, .form-row textarea { border:1.5px solid var(--border); border-radius:10px; padding:10px 14px; font-size:14px; outline:none; transition:border-color var(--transition); font-family:inherit; }
        .form-row input:focus, .form-row select:focus, .form-row textarea:focus { border-color:var(--green-mid); box-shadow:0 0 0 3px rgba(46,125,82,.1); }
        .modal-foot { display:flex; gap:10px; justify-content:flex-end; padding:16px 24px; border-top:1px solid var(--border); background:#fafafa; }
        .btn-cancel { padding:9px 20px; border-radius:var(--radius-pill); border:1px solid var(--border); background:var(--white); font-size:14px; cursor:pointer; transition:background var(--transition); }
        .btn-cancel:hover { background:#f3f4f6; }
        .btn-accept { padding:9px 20px; border-radius:var(--radius-pill); border:none; background:var(--green-dark); color:#fff; font-size:14px; font-weight:500; cursor:pointer; transition:background var(--transition); }
        .btn-accept:hover { background:var(--green-mid); }

        @media (max-width:700px) {
            .sidebar { position:absolute; height:100%; }
            .sections-wrap { padding:16px; }
            .top-bar { padding:10px 16px; }
            .search-bar { width:160px; }
        }
    </style>
</head>
<body>

<nav class="sidebar" id="sidebar">
    <div class="logo-wrap">
        <div class="logo-icon"><i class="fa-solid fa-recycle"></i></div>
        <div class="logo-text">Recicladora Diaz</div>
        <div class="logo-sub">Panel de administración</div>
    </div>
    <ul class="nav-menu">
        <li><a href="index.php?menu=usuarios&opc=tabla"   <?= $pagina_activa==='usuarios'   ? 'class="active"':'' ?>>
            <i class="fa-solid fa-users"></i> Usuarios</a></li>
        <li><a href="index.php?menu=material&opc=tabla"   <?= $pagina_activa==='material'   ? 'class="active"':'' ?>>
            <i class="fa-solid fa-box-archive"></i> Material</a></li>
        <li><a href="index.php?menu=calendario"           <?= $pagina_activa==='calendario' ? 'class="active"':'' ?>>
            <i class="fa-solid fa-calendar-days"></i> Calendario</a></li>
        <li><a href="index.php?menu=compras&opc=tabla"    <?= $pagina_activa==='compras'    ? 'class="active"':'' ?>>
            <i class="fa-solid fa-receipt"></i> Registro de compras</a></li>
    </ul>
    <div class="sidebar-footer">Recicladora Diaz &copy; <?= date('Y') ?></div>
</nav>

<main class="main-content">
    <header class="top-bar">
        <div class="top-left">
            <button class="menu-btn" onclick="toggleSidebar()" title="Menú">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Buscar…">
            </div>
        </div>
        <div class="top-right">
            <div class="user-badge">
                <div class="user-avatar">A</div>
                <span>Admin</span>
            </div>
            <a href="index.php?menu=login" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </a>
        </div>
    </header>
    <div class="sections-wrap">