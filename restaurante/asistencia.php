<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION["id_rol"]) || (int)$_SESSION["id_rol"] !== 3) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set("America/Bogota");

$nombreUsuario = $_SESSION["nombre"] ?? "Usuario Restaurante";
$apellidoUsuario = $_SESSION["apellido"] ?? "";
$nombreCompleto = trim($nombreUsuario . " " . $apellidoUsuario);

$partesNombre = preg_split('/\s+/', $nombreCompleto);
$primerNombre = $partesNombre[0] ?? "Usuario";

$iniciales = "";
foreach (array_slice($partesNombre, 0, 2) as $parte) {
    $iniciales .= strtoupper(substr($parte, 0, 1));
}
if ($iniciales === "") {
    $iniciales = "RE";
}

$horaActual = date("H:i:s");
$fechaActual = date("d/m/Y");
$fechaHoy = date("Y-m-d");

/* =========================================================
   ASISTENCIAS DE HOY
========================================================= */

$asistenciasHoy = [];

$sqlAsistencias = "
    SELECT
        ar.id_asistencia_restaurante,
        ar.id_estudiante,
        ar.fecha,
        ar.hora,
        ar.estado,
        ar.observacion,
        e.documento,
        e.nombres,
        e.apellidos
    FROM asistencia_restaurante ar
    INNER JOIN estudiantes e
        ON e.id_estudiante = ar.id_estudiante
    WHERE ar.fecha = ?
    ORDER BY ar.id_asistencia_restaurante DESC
";

$stmtAsistencias = mysqli_prepare($conexion, $sqlAsistencias);

if ($stmtAsistencias) {
    mysqli_stmt_bind_param($stmtAsistencias, "s", $fechaHoy);
    mysqli_stmt_execute($stmtAsistencias);

    $resultadoAsistencias = mysqli_stmt_get_result($stmtAsistencias);

    while ($fila = mysqli_fetch_assoc($resultadoAsistencias)) {
        $asistenciasHoy[] = $fila;
    }

    mysqli_stmt_close($stmtAsistencias);
}

$totalHoy = count($asistenciasHoy);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Asistencia QR | Tomar asistencia</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>

        :root{
            --aqua:#18d8ce;
            --aqua-dark:#087d92;
            --blue:#69b8d5;
            --mint:#42cda1;
            --purple:#8579d2;
            --coral:#e99a78;
            --yellow:#f0b84d;
            --text:#3e6f7d;
            --dark:#20596d;
            --muted:#7897a0;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            min-height:100vh;
            overflow-x:hidden;
            font-family:"Segoe UI",Arial,sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at 5% 10%,rgba(24,216,206,.13),transparent 27%),
                radial-gradient(circle at 96% 88%,rgba(133,121,210,.13),transparent 30%),
                linear-gradient(135deg,#e8faf7 0%,#f8fdfc 48%,#eaf6fb 100%);
        }

        .app{
            position:relative;
            z-index:1;
            display:flex;
            gap:18px;
            min-height:100vh;
            padding:18px;
        }

        /* =====================================================
           SIDEBAR - MISMO ESTILO DEL DASHBOARD
        ====================================================== */

        .sidebar{
            width:285px;
            flex-shrink:0;
            min-height:calc(100vh - 36px);
            display:flex;
            flex-direction:column;
            padding:22px 16px 16px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:29px;
            background:linear-gradient(145deg,rgba(255,255,255,.87),rgba(232,250,247,.68));
            backdrop-filter:blur(25px);
            box-shadow:0 25px 65px rgba(55,113,129,.10);
        }

        .sidebar-header{
            display:flex;
            align-items:center;
            gap:13px;
            padding:3px 9px 20px;
        }

        .logo-container{
            width:64px;
            height:64px;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:7px;
            flex-shrink:0;
            border-radius:19px;
            background:rgba(255,255,255,.76);
            border:1px solid rgba(255,255,255,.95);
            box-shadow:0 12px 30px rgba(55,113,129,.10);
        }

        .logo-container img{
            width:100%;
            height:100%;
            object-fit:contain;
        }

        .sidebar-title strong{
            display:block;
            color:#075273;
            font-size:19px;
            font-weight:950;
        }

        .sidebar-title small{
            display:block;
            margin-top:6px;
            color:#7898a1;
            font-size:11px;
            font-weight:750;
        }

        .sidebar-line{
            position:relative;
            height:1px;
            margin:0 9px 16px;
            background:rgba(50,111,130,.09);
        }

        .sidebar-line span{
            position:absolute;
            left:0;
            top:-1px;
            width:55px;
            height:2px;
            border-radius:5px;
            background:linear-gradient(90deg,var(--aqua),transparent);
        }

        .navigation{
            flex:1;
        }

        .menu-section{
            margin-bottom:12px;
        }

        .menu-label{
            display:flex;
            align-items:center;
            gap:8px;
            min-height:30px;
            padding:0 11px;
            color:#7d9aa3;
            font-size:11px;
            font-weight:950;
            letter-spacing:1.35px;
        }

        .label-line{
            width:17px;
            height:2px;
            border-radius:4px;
            background:#b9d7db;
        }

        .nav-link{
            position:relative;
            display:flex;
            align-items:center;
            gap:11px;
            width:100%;
            min-height:55px;
            margin-bottom:4px;
            padding:6px 10px;
            border-radius:15px;
            color:#557f8b;
            text-decoration:none;
            font-size:14px;
            font-weight:850;
            transition:.25s;
        }

        .nav-link:hover{
            color:#075273;
            background:rgba(255,255,255,.74);
            transform:translateX(4px);
        }

        .nav-link.active{
            color:#08758a;
            background:linear-gradient(100deg,rgba(24,216,206,.17),rgba(255,255,255,.70));
        }

        .nav-link.active::before{
            content:"";
            position:absolute;
            left:0;
            top:8px;
            bottom:8px;
            width:4px;
            border-radius:0 7px 7px 0;
            background:linear-gradient(180deg,var(--aqua),var(--blue));
        }

        .nav-icon{
            width:40px;
            height:40px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink:0;
            border-radius:12px;
            color:#4b98a8;
            background:rgba(24,216,206,.075);
            font-size:19px;
        }

        .nav-icon.qr{
            color:#078395;
            background:rgba(24,216,206,.12);
        }

        .nav-icon.search{
            color:#5578ca;
            background:rgba(105,184,213,.12);
        }

        .nav-icon.report{
            color:#bd8a40;
            background:rgba(209,161,88,.12);
        }

        .nav-icon.info{
            color:#7569c2;
            background:rgba(133,121,210,.11);
        }

        .nav-arrow{
            margin-left:auto;
            color:#a1b8be;
            opacity:0;
            transition:.2s;
        }

        .nav-link:hover .nav-arrow{
            opacity:1;
        }

        .sidebar-bottom{
            margin-top:auto;
            padding-top:10px;
        }

        .profile-card{
            display:flex;
            align-items:center;
            gap:10px;
            padding:10px 11px;
            border-radius:15px;
            background:rgba(255,255,255,.54);
            border:1px solid rgba(255,255,255,.85);
        }

        .profile-avatar{
            width:42px;
            height:42px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink:0;
            border-radius:12px;
            color:#fff;
            background:linear-gradient(145deg,#f0bd57,#d28c20);
            font-size:14px;
            font-weight:950;
        }

        .profile-info{
            flex:1;
            min-width:0;
        }

        .profile-info strong{
            display:block;
            overflow:hidden;
            color:#4d7c89;
            font-size:13px;
            font-weight:900;
            white-space:nowrap;
            text-overflow:ellipsis;
        }

        .profile-info small{
            display:block;
            margin-top:3px;
            color:#8ca6ad;
            font-size:10px;
        }

        .profile-status{
            color:#27b884;
            font-size:11px;
        }

        .logout{
            display:flex;
            align-items:center;
            gap:9px;
            min-height:48px;
            margin-top:3px;
            padding:0 10px;
            color:#b86e77;
            text-decoration:none;
            font-size:13px;
            font-weight:850;
        }

        .logout:hover{
            color:#a4535c;
            background:rgba(242,143,150,.08);
            border-radius:13px;
        }

        .logout-icon{
            width:35px;
            height:35px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:10px;
            background:rgba(242,143,150,.08);
        }

        /* =====================================================
           MAIN Y TOPBAR - MISMO ESTILO DEL DASHBOARD
        ====================================================== */

        .main{
            flex:1;
            min-width:0;
            display:flex;
            flex-direction:column;
            gap:18px;
        }

        .topbar{
            min-height:82px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
            padding:14px 22px;
            border:1px solid rgba(255,255,255,.92);
            border-radius:23px;
            background:rgba(255,255,255,.68);
            backdrop-filter:blur(20px);
            box-shadow:0 16px 42px rgba(55,113,129,.065);
        }

        .page-info{
            display:flex;
            align-items:center;
            gap:13px;
        }

        .page-indicator{
            width:9px;
            height:47px;
            border-radius:7px;
            background:linear-gradient(180deg,var(--yellow),var(--aqua));
        }

        .page-title h1{
            color:#15576c;
            font-size:28px;
            font-weight:950;
        }

        .page-title p{
            margin-top:5px;
            color:#7898a2;
            font-size:14px;
            font-weight:650;
        }

        .clock-box{
            display:flex;
            align-items:center;
            gap:12px;
            padding:10px 15px;
            border-radius:15px;
            background:rgba(255,255,255,.73);
            border:1px solid rgba(255,255,255,.90);
        }

        .clock-icon{
            width:38px;
            height:38px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:11px;
            color:#0b9f9c;
            background:rgba(24,216,206,.10);
            font-size:18px;
        }

        .clock-time{
            color:#155b70;
            font-size:18px;
            font-weight:950;
            letter-spacing:.5px;
        }

        .clock-date{
            margin-top:2px;
            color:#819ba3;
            font-size:10px;
            font-weight:750;
        }

        /* =====================================================
           ESCÁNER
        ====================================================== */

        .scanner-card{
            position:relative;
            overflow:hidden;
            padding:28px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:28px;
            background:
                radial-gradient(circle at 90% 10%,rgba(245,190,70,.10),transparent 25%),
                radial-gradient(circle at 10% 100%,rgba(24,216,206,.08),transparent 28%),
                rgba(255,255,255,.76);
            box-shadow:0 22px 52px rgba(55,113,129,.08);
        }

        .scanner-heading{
            text-align:center;
            margin-bottom:22px;
        }

        .scanner-tag{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:7px 12px;
            margin-bottom:10px;
            border-radius:10px;
            color:#087d92;
            background:rgba(24,216,206,.09);
            font-size:10px;
            font-weight:950;
            letter-spacing:.7px;
        }

        .scanner-heading h2{
            color:#15576c;
            font-size:25px;
            font-weight:950;
        }

        .scanner-heading p{
            margin-top:6px;
            color:#819ca4;
            font-size:13px;
            font-weight:650;
        }

        .scanner-layout{
            display:grid;
            grid-template-columns:minmax(330px,500px) minmax(250px,1fr);
            gap:25px;
            align-items:center;
            max-width:980px;
            margin:0 auto;
        }

        .reader-box{
            position:relative;
            min-height:365px;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:18px;
            border-radius:23px;
            background:rgba(238,250,248,.72);
            border:1px solid rgba(255,255,255,.95);
            box-shadow:inset 0 0 0 1px rgba(24,216,206,.035);
        }

        #reader{
            width:100%;
            max-width:430px;
            min-height:300px;
            overflow:hidden;
            border-radius:18px;
            background:#edf8f7;
        }

        #reader video{
            display:block;
            width:100% !important;
            border-radius:18px;
        }

        #reader__dashboard_section_csr,
        #reader__dashboard_section_swaplink{
            color:#557f8b !important;
            font-size:12px !important;
        }

        .qr-frame{
            position:absolute;
            width:250px;
            height:250px;
            left:50%;
            top:50%;
            transform:translate(-50%,-50%);
            border:3px solid #18bdb7;
            border-radius:20px;
            pointer-events:none;
            z-index:10;
            overflow:hidden;
            display:none;
            box-shadow:0 0 0 9999px rgba(20,80,90,.035);
        }

        .qr-frame.activo{
            display:block;
        }

        .qr-line{
            position:absolute;
            left:12px;
            right:12px;
            top:15px;
            height:3px;
            border-radius:5px;
            background:linear-gradient(90deg,transparent,#18d8ce,transparent);
            box-shadow:0 0 12px rgba(24,216,206,.45);
            animation:scanLine 2s linear infinite;
        }

        @keyframes scanLine{
            0%{top:15px}
            50%{top:225px}
            100%{top:15px}
        }

        .scanner-info{
            padding:25px;
            border-radius:23px;
            background:linear-gradient(145deg,rgba(255,255,255,.80),rgba(236,250,248,.68));
            border:1px solid rgba(255,255,255,.94);
        }

        .scanner-info h3{
            color:#416f7e;
            font-size:18px;
            font-weight:950;
        }

        .scanner-info p{
            margin-top:7px;
            color:#819ca4;
            font-size:12px;
            font-weight:650;
            line-height:1.65;
        }

        .step{
            display:flex;
            align-items:center;
            gap:11px;
            margin-top:14px;
            color:#557f8b;
            font-size:12px;
            font-weight:800;
        }

        .step-icon{
            width:36px;
            height:36px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink:0;
            border-radius:11px;
            color:#087d92;
            background:rgba(24,216,206,.10);
        }

        .step:nth-child(4) .step-icon{
            color:#d99a24;
            background:rgba(245,190,70,.12);
        }

        .step:nth-child(5) .step-icon{
            color:#7569c2;
            background:rgba(133,121,210,.10);
        }

        .camera-state{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            margin-top:18px;
            color:#7897a0;
            font-size:12px;
            font-weight:750;
        }

        .state-dot{
            width:9px;
            height:9px;
            border-radius:50%;
            background:#a9b9bd;
        }

        .camera-state.active .state-dot{
            background:#27b884;
            box-shadow:0 0 0 5px rgba(39,184,132,.12);
        }

        .message{
            min-height:22px;
            margin-top:12px;
            text-align:center;
            color:#7897a0;
            font-size:12px;
            font-weight:800;
        }

        .message.success{color:#15956c}
        .message.warning{color:#bd7a1b}
        .message.error{color:#b65f68}
        .message.primary{color:#087d92}

        .camera-buttons{
            display:flex;
            justify-content:center;
            gap:9px;
            margin-top:13px;
        }

        .btn-camera{
            border:0;
            border-radius:13px;
            padding:11px 17px;
            color:white;
            background:linear-gradient(145deg,#18d8ce,#087d92);
            box-shadow:0 10px 22px rgba(24,216,206,.18);
            font-family:inherit;
            font-size:12px;
            font-weight:900;
            cursor:pointer;
            transition:.2s;
        }

        .btn-camera:hover{
            transform:translateY(-2px);
        }

        .btn-stop{
            background:linear-gradient(145deg,#e99a78,#c97170);
            box-shadow:0 10px 22px rgba(201,113,112,.16);
        }

        .result{
            display:none;
            margin-top:17px;
            padding:15px 18px;
            text-align:center;
            border-radius:17px;
            font-size:12px;
            font-weight:750;
        }

        .result.show{
            display:block;
        }

        .result.success{
            color:#126d59;
            background:rgba(66,205,161,.11);
            border:1px solid rgba(66,205,161,.22);
        }

        .result.warning{
            color:#98651d;
            background:rgba(245,190,70,.12);
            border:1px solid rgba(245,190,70,.24);
        }

        .result.error{
            color:#9e5861;
            background:rgba(242,143,150,.10);
            border:1px solid rgba(242,143,150,.20);
        }

        .result i{
            font-size:22px;
            display:block;
            margin-bottom:4px;
        }

        .result-name{
            display:block;
            margin-top:4px;
            color:#315f70;
            font-size:17px;
            font-weight:950;
        }

        .result-time{
            display:block;
            margin-top:3px;
            color:#7897a0;
            font-size:11px;
        }

        /* =====================================================
           RESUMEN Y TABLA
        ====================================================== */

        .summary-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:16px;
        }

        .summary-item{
            position:relative;
            min-height:105px;
            overflow:hidden;
            display:flex;
            align-items:center;
            gap:17px;
            padding:21px 22px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:23px;
            background:rgba(255,255,255,.76);
            box-shadow:0 18px 42px rgba(55,113,129,.065);
            transition:.25s;
        }

        .summary-item::after{
            content:"";
            position:absolute;
            width:90px;
            height:90px;
            right:-35px;
            bottom:-45px;
            border-radius:50%;
            background:rgba(24,216,206,.055);
        }

        .summary-item:hover{
            transform:translateY(-4px);
            box-shadow:0 24px 48px rgba(55,113,129,.10);
        }

        .summary-icon{
            width:58px;
            height:58px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink:0;
            border-radius:17px;
            color:#0a9995;
            background:rgba(24,216,206,.10);
            font-size:25px;
        }

        .summary-item:nth-child(2) .summary-icon{
            color:#d99a24;
            background:rgba(245,190,70,.12);
        }

        .summary-text span{
            display:block;
            color:#819da5;
            font-size:12px;
            font-weight:800;
        }

        .summary-text strong{
            display:block;
            margin-top:5px;
            color:#315f70;
            font-size:28px;
            font-weight:950;
            line-height:1;
        }

        .table-card{
            padding:24px 25px 20px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:25px;
            background:rgba(255,255,255,.74);
            box-shadow:0 18px 42px rgba(55,113,129,.065);
        }

        .section-heading{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:15px;
            margin-bottom:17px;
        }

        .section-heading h3{
            color:#416f7e;
            font-size:18px;
            font-weight:950;
        }

        .section-heading p{
            margin-top:5px;
            color:#819ca4;
            font-size:12px;
            font-weight:650;
        }

        .today-label{
            display:flex;
            align-items:center;
            gap:7px;
            padding:7px 10px;
            border-radius:10px;
            color:#17867f;
            background:rgba(24,216,206,.08);
            font-size:10px;
            font-weight:900;
        }

        .table-wrapper{
            overflow-x:auto;
        }

        .attendance-table{
            width:100%;
            border-collapse:collapse;
            min-width:650px;
        }

        .attendance-table th{
            padding:12px 10px;
            text-align:left;
            color:#7d9aa3;
            background:rgba(240,249,248,.72);
            font-size:10px;
            font-weight:950;
            letter-spacing:.7px;
            text-transform:uppercase;
        }

        .attendance-table th:first-child{
            border-radius:10px 0 0 10px;
        }

        .attendance-table th:last-child{
            border-radius:0 10px 10px 0;
        }

        .attendance-table td{
            padding:13px 10px;
            border-bottom:1px solid rgba(50,111,130,.07);
            color:#557f8b;
            font-size:12px;
            font-weight:650;
        }

        .attendance-table tbody tr{
            transition:.2s;
        }

        .attendance-table tbody tr:hover{
            background:rgba(24,216,206,.035);
        }

        .status{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 9px;
            border-radius:10px;
            color:#17815e;
            background:rgba(66,205,161,.10);
            font-size:10px;
            font-weight:900;
        }

        .status::before{
            content:"";
            width:6px;
            height:6px;
            border-radius:50%;
            background:#27b884;
        }

        .empty-row{
            text-align:center !important;
            padding:34px 15px !important;
            color:#91a8ae !important;
        }

        .empty-row i{
            display:block;
            margin-bottom:7px;
            color:#a8c5ca;
            font-size:27px;
        }

        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media(max-width:1100px){
            .sidebar{width:255px}
            .scanner-layout{
                grid-template-columns:1fr;
                max-width:650px;
            }
            .scanner-info{
                display:none;
            }
        }

        @media(max-width:900px){
            .app{
                flex-direction:column;
                padding:10px;
            }

            .sidebar{
                width:100%;
                min-height:auto;
            }

            .navigation{
                display:grid;
                grid-template-columns:repeat(2,1fr);
            }

            .menu-label{
                grid-column:1/-1;
            }

            .sidebar-bottom{
                display:none;
            }
        }

        @media(max-width:700px){
            .topbar{
                align-items:flex-start;
                flex-direction:column;
            }

            .clock-box{
                width:100%;
            }

            .navigation{
                grid-template-columns:1fr;
            }

            .menu-label{
                grid-column:auto;
            }

            .scanner-card{
                padding:18px;
            }

            .scanner-layout{
                display:block;
            }

            .reader-box{
                min-height:300px;
                padding:10px;
            }

            .qr-frame{
                width:220px;
                height:220px;
            }

            .summary-grid{
                grid-template-columns:1fr;
            }

            .section-heading{
                flex-direction:column;
            }
        }

    </style>
</head>

<body>

<div class="app">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="sidebar-header">

            <div class="logo-container">
                <img
                    src="../Logo.png"
                    alt="Logo Asistencia QR"
                >
            </div>

            <div class="sidebar-title">
                <strong>ASISTENCIA QR</strong>
                <small>Sistema académico</small>
            </div>

        </div>

        <div class="sidebar-line">
            <span></span>
        </div>

        <nav class="navigation">

            <div class="menu-section">

                <div class="menu-label">
                    <span class="label-line"></span>
                    NAVEGACIÓN
                </div>

                <a href="dashboard.php" class="nav-link">
                    <div class="nav-icon">
                        <i class="bi bi-grid-1x2"></i>
                    </div>
                    <span>Inicio</span>
                    <span class="nav-arrow">→</span>
                </a>

            </div>

            <div class="menu-section">

                <div class="menu-label">
                    <span class="label-line"></span>
                    CONTROL DEL RESTAURANTE
                </div>

                <a href="asistencia.php" class="nav-link active">
                    <div class="nav-icon qr">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                    <span>Tomar asistencia</span>
                    <span class="nav-arrow">→</span>
                </a>

                <a href="consultar.php" class="nav-link">
                    <div class="nav-icon search">
                        <i class="bi bi-search"></i>
                    </div>
                    <span>Consultar asistencia</span>
                    <span class="nav-arrow">→</span>
                </a>

                <a href="reportes.php" class="nav-link">
                    <div class="nav-icon report">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                    <span>Reportes</span>
                    <span class="nav-arrow">→</span>
                </a>

            </div>

            <div class="menu-section">

                <div class="menu-label">
                    <span class="label-line"></span>
                    INFORMACIÓN
                </div>

                <a href="consultar.php" class="nav-link">
                    <div class="nav-icon info">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <span>Registros del día</span>
                    <span class="nav-arrow">→</span>
                </a>

            </div>

        </nav>

        <div class="sidebar-bottom">

            <div class="profile-card">

                <div class="profile-avatar">
                    <?= htmlspecialchars($iniciales) ?>
                </div>

                <div class="profile-info">

                    <strong>
                        <?= htmlspecialchars($nombreCompleto) ?>
                    </strong>

                    <small>RESTAURANTE</small>

                </div>

                <div class="profile-status">●</div>

            </div>

            <a
                href="../auth/logout.php"
                class="logout"
                onclick="return confirm('¿Deseas cerrar tu sesión?');"
            >
                <div class="logout-icon">
                    <i class="bi bi-box-arrow-left"></i>
                </div>
                <span>Cerrar sesión</span>
            </a>

        </div>

    </aside>

    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">

        <!-- TOPBAR -->

        <header class="topbar">

            <div class="page-info">

                <div class="page-indicator"></div>

                <div class="page-title">
                    <h1>Tomar asistencia</h1>
                    <p>Control de asistencia al restaurante</p>
                </div>

            </div>

            <div class="clock-box">

                <div class="clock-icon">
                    <i class="bi bi-clock"></i>
                </div>

                <div>
                    <div class="clock-time" id="reloj">
                        <?= $horaActual ?>
                    </div>

                    <div class="clock-date">
                        <?= $fechaActual ?>
                    </div>
                </div>

            </div>

        </header>

        <!-- =================================================
             ESCÁNER
        ================================================== -->

        <section class="scanner-card">

            <div class="scanner-heading">

                <div class="scanner-tag">
                    <i class="bi bi-qr-code-scan"></i>
                    CONTROL DE ASISTENCIA
                </div>

                <h2>Escáner de estudiantes</h2>

                <p>
                    Escanea el código QR de la tarjeta del estudiante.
                </p>

            </div>

            <div class="scanner-layout">

                <div>

                    <div class="reader-box">

                        <div id="reader"></div>

                        <div
                            class="qr-frame"
                            id="qrFrame"
                        >
                            <div class="qr-line"></div>
                        </div>

                    </div>

                    <div
                        class="camera-state"
                        id="estadoCamara"
                    >
                        <span class="state-dot"></span>
                        <span id="textoEstado">Cámara apagada</span>
                    </div>

                    <div
                        class="message"
                        id="mensajeQR"
                    >
                        Presiona "Iniciar cámara" para comenzar.
                    </div>

                    <div class="camera-buttons">

                        <button
                            type="button"
                            class="btn-camera"
                            id="btnIniciar"
                        >
                            <i class="bi bi-camera-fill"></i>
                            Iniciar cámara
                        </button>

                        <button
                            type="button"
                            class="btn-camera btn-stop"
                            id="btnDetener"
                            style="display:none;"
                        >
                            <i class="bi bi-camera-video-off-fill"></i>
                            Detener cámara
                        </button>

                    </div>

                    <div
                        class="result"
                        id="resultadoQR"
                    >
                        <i id="resultadoIcono"></i>

                        <div id="resultadoMensaje"></div>

                        <span
                            class="result-name"
                            id="resultadoNombre"
                        ></span>

                        <span
                            class="result-time"
                            id="resultadoHora"
                        ></span>
                    </div>

                </div>

                <div class="scanner-info">

                    <h3>
                        ¿Cómo tomar asistencia?
                    </h3>

                    <p>
                        Utiliza el código QR que se encuentra
                        en la tarjeta del estudiante.
                    </p>

                    <div class="step">
                        <div class="step-icon">
                            <i class="bi bi-camera"></i>
                        </div>
                        <span>Activa la cámara del dispositivo.</span>
                    </div>

                    <div class="step">
                        <div class="step-icon">
                            <i class="bi bi-qr-code"></i>
                        </div>
                        <span>Coloca el código QR dentro del cuadro.</span>
                    </div>

                    <div class="step">
                        <div class="step-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <span>El sistema registra la asistencia automáticamente.</span>
                    </div>

                    <div class="result success show" style="margin-top:20px;">
                        <i class="bi bi-calendar-check"></i>
                        Un estudiante solo puede registrar una asistencia
                        de restaurante por día.
                    </div>

                </div>

            </div>

        </section>

        <!-- =================================================
             RESUMEN
        ================================================== -->

        <section class="summary-grid">

            <div class="summary-item">

                <div class="summary-icon">
                    <i class="bi bi-people-fill"></i>
                </div>

                <div class="summary-text">
                    <span>Asistencias de hoy</span>
                    <strong id="totalHoy"><?= $totalHoy ?></strong>
                </div>

            </div>

            <div class="summary-item">

                <div class="summary-icon">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>

                <div class="summary-text">
                    <span>Fecha actual</span>
                    <strong style="font-size:22px;">
                        <?= $fechaActual ?>
                    </strong>
                </div>

            </div>

        </section>

        <!-- =================================================
             TABLA
        ================================================== -->

        <section class="table-card">

            <div class="section-heading">

                <div>
                    <h3>Asistencias del restaurante</h3>
                    <p>
                        Estudiantes registrados durante el día de hoy.
                    </p>
                </div>

                <div class="today-label">
                    <i class="bi bi-calendar-check"></i>
                    <?= $totalHoy ?> <?= $totalHoy === 1 ? "REGISTRO" : "REGISTROS" ?>
                </div>

            </div>

            <div class="table-wrapper">

                <table class="attendance-table">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Documento</th>
                            <th>Estudiante</th>
                            <th>Hora</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody id="tablaAsistencias">

                    <?php if ($totalHoy > 0): ?>

                        <?php foreach ($asistenciasHoy as $index => $fila): ?>

                            <tr>

                                <td><?= $index + 1 ?></td>

                                <td>
                                    <?= htmlspecialchars($fila["documento"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        trim($fila["nombres"] . " " . $fila["apellidos"])
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        date("h:i:s A", strtotime($fila["hora"]))
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status">
                                        <?= htmlspecialchars($fila["estado"]) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr id="filaSinRegistros">
                            <td colspan="5" class="empty-row">
                                <i class="bi bi-inbox"></i>
                                Todavía no hay asistencias registradas hoy.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<script>

let lectorQR = null;
let camaraActiva = false;
let procesandoQR = false;

const btnIniciar = document.getElementById("btnIniciar");
const btnDetener = document.getElementById("btnDetener");
const mensajeQR = document.getElementById("mensajeQR");
const estadoCamara = document.getElementById("estadoCamara");
const textoEstado = document.getElementById("textoEstado");
const resultadoQR = document.getElementById("resultadoQR");
const resultadoIcono = document.getElementById("resultadoIcono");
const resultadoMensaje = document.getElementById("resultadoMensaje");
const resultadoNombre = document.getElementById("resultadoNombre");
const resultadoHora = document.getElementById("resultadoHora");
const tablaAsistencias = document.getElementById("tablaAsistencias");
const totalHoy = document.getElementById("totalHoy");
const qrFrame = document.getElementById("qrFrame");

btnIniciar.addEventListener("click", iniciarCamara);
btnDetener.addEventListener("click", () => {
    procesandoQR = false;
    detenerCamara(true);
});

async function iniciarCamara() {

    if (camaraActiva) return;

    procesandoQR = false;
    ocultarResultado();

    mensajeQR.textContent = "Solicitando acceso a la cámara...";
    mensajeQR.className = "message primary";

    btnIniciar.style.display = "none";

    const configuracion = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        disableFlip: false
    };

    try {

        lectorQR = new Html5Qrcode("reader");

        await lectorQR.start(
            { facingMode: { ideal: "environment" } },
            configuracion,
            cuandoLeeQR,
            cuandoNoLeeQR
        );

        activarCamara();

    } catch (error) {

        console.error("Primer intento de cámara:", error);

        try {

            if (lectorQR) {
                try { await lectorQR.clear(); } catch(e) {}
                lectorQR = null;
            }

            const cameras = await Html5Qrcode.getCameras();

            if (!cameras || cameras.length === 0) {
                throw new Error("No se encontraron cámaras.");
            }

            lectorQR = new Html5Qrcode("reader");

            await lectorQR.start(
                cameras[0].id,
                configuracion,
                cuandoLeeQR,
                cuandoNoLeeQR
            );

            activarCamara();

        } catch (errorSecundario) {

            console.error("Error de cámara:", errorSecundario);

            camaraActiva = false;
            lectorQR = null;

            qrFrame.classList.remove("activo");

            btnIniciar.style.display = "inline-flex";
            btnDetener.style.display = "none";

            estadoCamara.classList.remove("active");
            textoEstado.textContent = "Cámara apagada";

            mensajeQR.textContent =
                "No se pudo acceder a la cámara. Verifica los permisos del navegador.";
            mensajeQR.className = "message error";
        }
    }
}

function activarCamara() {

    camaraActiva = true;

    qrFrame.classList.add("activo");

    btnIniciar.style.display = "none";
    btnDetener.style.display = "inline-flex";

    estadoCamara.classList.add("active");
    textoEstado.textContent = "Cámara activa";

    mensajeQR.textContent =
        "Cámara activa. Coloca el QR dentro del cuadro.";
    mensajeQR.className = "message success";
}

function cuandoLeeQR(texto) {

    if (procesandoQR) return;

    procesandoQR = true;

    const documento = String(texto || "").trim();

    if (!documento) {
        procesandoQR = false;
        return;
    }

    mensajeQR.textContent =
        "QR detectado. Registrando asistencia...";
    mensajeQR.className = "message primary";

    detenerCamara(false);

    registrarAsistencia(documento);
}

function cuandoNoLeeQR() {
    /* El lector ejecuta esta función mientras busca un QR. */
}

async function registrarAsistencia(documento) {

    const datos = new FormData();
    datos.append("documento", documento);

    try {

        const respuesta = await fetch(
            "procesar_asistencia.php",
            {
                method: "POST",
                body: datos
            }
        );

        const data = await respuesta.json();

        if (data.ok === true && data.tipo === "registrado") {

            mostrarResultadoExito(data);

            if (typeof data.total_hoy !== "undefined") {
                totalHoy.textContent = data.total_hoy;
            }

            agregarFilaTabla(data);

            mensajeQR.textContent =
                "Asistencia registrada correctamente.";
            mensajeQR.className = "message success";

            setTimeout(() => {
                ocultarResultado();
                iniciarCamara();
            }, 1800);

            return;
        }

        if (data.ok === false && data.tipo === "duplicado") {

            mostrarResultadoDuplicado(data);

            mensajeQR.textContent =
                "Este estudiante ya fue registrado hoy.";
            mensajeQR.className = "message warning";

            setTimeout(() => {
                ocultarResultado();
                iniciarCamara();
            }, 2200);

            return;
        }

        mostrarResultadoError(
            data.mensaje || "No fue posible registrar la asistencia."
        );

        mensajeQR.textContent =
            data.mensaje || "Ocurrió un error.";
        mensajeQR.className = "message error";

        setTimeout(() => {
            ocultarResultado();
            iniciarCamara();
        }, 2200);

    } catch (error) {

        console.error("Error AJAX:", error);

        mostrarResultadoError(
            "No fue posible comunicarse con el servidor."
        );

        mensajeQR.textContent =
            "Error de conexión con el servidor.";
        mensajeQR.className = "message error";

        setTimeout(() => {
            ocultarResultado();
            iniciarCamara();
        }, 2500);
    }
}

function mostrarResultadoExito(data) {

    resultadoQR.className = "result success show";

    resultadoIcono.className =
        "bi bi-check-circle-fill";

    resultadoMensaje.textContent =
        "¡Asistencia registrada!";

    resultadoNombre.textContent =
        data.estudiante.nombre;

    resultadoHora.textContent =
        "Hora: " + formatearHora(data.asistencia.hora);
}

function mostrarResultadoDuplicado(data) {

    resultadoQR.className = "result warning show";

    resultadoIcono.className =
        "bi bi-exclamation-circle-fill";

    resultadoMensaje.textContent =
        "Asistencia ya registrada";

    resultadoNombre.textContent =
        data.estudiante.nombre;

    resultadoHora.textContent =
        "Ya fue registrado hoy a las " +
        formatearHora(data.asistencia.hora);
}

function mostrarResultadoError(mensaje) {

    resultadoQR.className = "result error show";

    resultadoIcono.className =
        "bi bi-x-circle-fill";

    resultadoMensaje.textContent = mensaje;

    resultadoNombre.textContent = "";

    resultadoHora.textContent = "";
}

function ocultarResultado() {

    resultadoQR.className = "result";

    resultadoIcono.className = "";

    resultadoMensaje.textContent = "";
    resultadoNombre.textContent = "";
    resultadoHora.textContent = "";
}

function agregarFilaTabla(data) {

    const filaSinRegistros =
        document.getElementById("filaSinRegistros");

    if (filaSinRegistros) {
        filaSinRegistros.remove();
    }

    const fila = document.createElement("tr");

    fila.innerHTML = `
        <td>1</td>
        <td>${escapeHtml(data.estudiante.documento)}</td>
        <td>${escapeHtml(data.estudiante.nombre)}</td>
        <td>${escapeHtml(formatearHora(data.asistencia.hora))}</td>
        <td>
            <span class="status">REGISTRADO</span>
        </td>
    `;

    tablaAsistencias.prepend(fila);

    renumerarTabla();
}

function renumerarTabla() {

    const filas =
        tablaAsistencias.querySelectorAll("tr");

    filas.forEach((fila, index) => {

        const primeraCelda =
            fila.querySelector("td");

        if (primeraCelda) {
            primeraCelda.textContent = index + 1;
        }
    });
}

function escapeHtml(texto) {

    const div = document.createElement("div");

    div.textContent = texto ?? "";

    return div.innerHTML;
}

function formatearHora(hora) {

    if (!hora) return "";

    const partes = hora.split(":");

    if (partes.length < 2) return hora;

    let horas = parseInt(partes[0], 10);
    const minutos = partes[1];
    const segundos = partes[2] || "00";

    const periodo = horas >= 12 ? "p. m." : "a. m.";

    horas = horas % 12;

    if (horas === 0) horas = 12;

    return (
        String(horas).padStart(2, "0") +
        ":" +
        minutos +
        ":" +
        segundos +
        " " +
        periodo
    );
}

async function detenerCamara(mostrarMensaje = true) {

    /* Ocultar el marco inmediatamente. */
    qrFrame.classList.remove("activo");

    if (!lectorQR) {

        camaraActiva = false;
        btnIniciar.style.display = "inline-flex";
        btnDetener.style.display = "none";

        if (mostrarMensaje) {
            estadoCamara.classList.remove("active");
            textoEstado.textContent = "Cámara apagada";
        }

        return;
    }

    try {
        await lectorQR.stop();
    } catch (error) {
        console.error("Error al detener cámara:", error);
    }

    try {
        await lectorQR.clear();
    } catch (error) {
        console.error("Error al limpiar lector:", error);
    }

    lectorQR = null;
    camaraActiva = false;

    btnIniciar.style.display = "inline-flex";
    btnDetener.style.display = "none";

    estadoCamara.classList.remove("active");
    textoEstado.textContent = "Cámara apagada";

    if (mostrarMensaje) {
        mensajeQR.textContent = "La cámara está apagada.";
        mensajeQR.className = "message";
    }
}

function actualizarReloj() {

    const ahora = new Date();

    const horas = String(ahora.getHours()).padStart(2, "0");
    const minutos = String(ahora.getMinutes()).padStart(2, "0");
    const segundos = String(ahora.getSeconds()).padStart(2, "0");

    const reloj = document.getElementById("reloj");

    if (reloj) {
        reloj.textContent =
            horas + ":" + minutos + ":" + segundos;
    }
}

actualizarReloj();
setInterval(actualizarReloj, 1000);

window.addEventListener("beforeunload", () => {

    if (lectorQR) {
        lectorQR.stop().catch(() => {});
    }

});

</script>

</body>
</html>
