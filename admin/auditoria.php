<?php
session_start();

/* =========================================================
   PROTECCIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}


/* =========================================================
   CONEXIÓN
========================================================= */

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


/* =========================================================
   DATOS DEL USUARIO
========================================================= */

$nombreUsuario = $_SESSION['nombre'] ?? 'Administrador Sistema';

$partesNombre = preg_split(
    '/\s+/',
    trim($nombreUsuario)
);

$iniciales = '';

foreach (array_slice($partesNombre, 0, 2) as $parte) {
    $iniciales .= strtoupper(substr($parte, 0, 1));
}

if ($iniciales === '') {
    $iniciales = 'AS';
}


/* =========================================================
   FILTROS
========================================================= */

$filtroAccion = trim($_GET['accion'] ?? '');
$filtroFecha = trim($_GET['fecha'] ?? '');


/* =========================================================
   CONSULTA DE AUDITORÍA
========================================================= */

$registros = [];

$sql = "
    SELECT
        a.id_auditoria,
        a.id_usuario,
        a.accion,
        a.descripcion,
        a.fecha_hora,
        CONCAT(
            COALESCE(u.nombre, ''),
            ' ',
            COALESCE(u.apellido, '')
        ) AS usuario
    FROM auditoria a
    LEFT JOIN usuarios u
        ON u.id_usuario = a.id_usuario
    WHERE 1=1
";

$tipos = '';
$parametros = [];


/* FILTRO ACCIÓN */

if ($filtroAccion !== '') {

    $sql .= "
        AND a.accion = ?
    ";

    $tipos .= 's';
    $parametros[] = $filtroAccion;
}


/* FILTRO FECHA */

if ($filtroFecha !== '') {

    $sql .= "
        AND DATE(a.fecha_hora) = ?
    ";

    $tipos .= 's';
    $parametros[] = $filtroFecha;
}


$sql .= "
    ORDER BY a.fecha_hora DESC
";


$stmt = mysqli_prepare(
    $conexion,
    $sql
);


if ($stmt) {

    if (!empty($parametros)) {

        mysqli_stmt_bind_param(
            $stmt,
            $tipos,
            ...$parametros
        );

    }

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    if ($resultado) {

        while ($fila = mysqli_fetch_assoc($resultado)) {

            $registros[] = $fila;

        }

    }

    mysqli_stmt_close($stmt);

}


/* =========================================================
   ACCIONES DISPONIBLES
========================================================= */

$acciones = [];

$sqlAcciones = "
    SELECT DISTINCT accion
    FROM auditoria
    WHERE accion IS NOT NULL
    AND accion <> ''
    ORDER BY accion ASC
";

$resultadoAcciones = mysqli_query(
    $conexion,
    $sqlAcciones
);

if ($resultadoAcciones) {

    while ($accion = mysqli_fetch_assoc($resultadoAcciones)) {

        $acciones[] = $accion['accion'];

    }

}


/* =========================================================
   CONTADORES
========================================================= */

$totalRegistros = count($registros);

$totalHoy = 0;

$fechaHoy = date('Y-m-d');

foreach ($registros as $registro) {

    if (
        isset($registro['fecha_hora']) &&
        substr(
            $registro['fecha_hora'],
            0,
            10
        ) === $fechaHoy
    ) {

        $totalHoy++;

    }

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Asistencia QR | Auditoría
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<style>

/* =========================================================
   VARIABLES
========================================================= */

:root{

    --aqua:#18d8ce;
    --aqua-dark:#087d92;

    --blue:#69b8d5;

    --mint:#42cda1;

    --purple:#8579d2;

    --coral:#e99a78;

    --text:#3e6f7d;

    --dark:#20596d;

    --muted:#7897a0;

}


/* =========================================================
   RESET
========================================================= */

*{

    margin:0;
    padding:0;

    box-sizing:border-box;

}


body{

    min-height:100vh;

    overflow-x:hidden;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    color:var(--text);

    background:

        radial-gradient(
            circle at 5% 10%,
            rgba(24,216,206,.13),
            transparent 27%
        ),

        radial-gradient(
            circle at 96% 88%,
            rgba(133,121,210,.13),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            #e8faf7 0%,
            #f8fdfc 48%,
            #eaf6fb 100%
        );

}


/* =========================================================
   APP
========================================================= */

.app{

    position:relative;

    z-index:1;

    display:flex;

    gap:18px;

    min-height:100vh;

    padding:18px;

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar{

    width:285px;

    flex-shrink:0;

    min-height:
        calc(100vh - 36px);

    display:flex;

    flex-direction:column;

    padding:
        22px 16px 16px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:29px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.87),
            rgba(232,250,247,.68)
        );

    backdrop-filter:blur(25px);

    box-shadow:
        0 25px 65px
        rgba(55,113,129,.10);

}


.sidebar-header{

    display:flex;

    align-items:center;

    gap:13px;

    padding:
        3px 9px 20px;

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

    background:
        rgba(255,255,255,.76);

    border:
        1px solid
        rgba(255,255,255,.95);

    box-shadow:
        0 12px 30px
        rgba(55,113,129,.10);

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

    margin:
        0 9px 16px;

    background:
        rgba(50,111,130,.09);

}


.sidebar-line span{

    position:absolute;

    left:0;
    top:-1px;

    width:55px;
    height:2px;

    border-radius:5px;

    background:
        linear-gradient(
            90deg,
            var(--aqua),
            transparent
        );

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

    padding:
        0 11px;

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

    padding:
        6px 10px;

    border-radius:15px;

    color:#557f8b;

    text-decoration:none;

    font-size:14px;

    font-weight:850;

    transition:.25s;

}


.nav-link:hover{

    color:#075273;

    background:
        rgba(255,255,255,.74);

    transform:
        translateX(4px);

}


.nav-link.active{

    color:#08758a;

    background:
        linear-gradient(
            100deg,
            rgba(24,216,206,.17),
            rgba(255,255,255,.70)
        );

}


.nav-link.active::before{

    content:"";

    position:absolute;

    left:0;

    top:8px;
    bottom:8px;

    width:4px;

    border-radius:
        0 7px 7px 0;

    background:
        linear-gradient(
            180deg,
            var(--aqua),
            var(--blue)
        );

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

    background:
        rgba(24,216,206,.075);

    font-size:19px;

}


.nav-icon.academic{

    color:#766cc8;

    background:
        rgba(133,121,210,.10);

}


.nav-icon.people{

    color:#488da1;

    background:
        rgba(105,184,213,.10);

}


.nav-icon.qr-icon{

    color:#078395;

    background:
        rgba(24,216,206,.12);

}


.nav-icon.reports{

    color:#bd8a40;

    background:
        rgba(209,161,88,.12);

}


.nav-icon.restaurant{

    color:#d99a24;

    background:
        rgba(245,190,70,.14);

}


.nav-icon.audit{

    color:#7569c2;

    background:
        rgba(133,121,210,.11);

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

    padding:
        10px 11px;

    border-radius:15px;

    background:
        rgba(255,255,255,.54);

    border:
        1px solid
        rgba(255,255,255,.85);

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

    background:
        linear-gradient(
            145deg,
            #52dca9,
            #15966f
        );

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

    padding:
        0 10px;

    color:#b86e77;

    text-decoration:none;

    font-size:13px;

    font-weight:850;

}


.logout:hover{

    color:#a4535c;

    background:
        rgba(242,143,150,.08);

    border-radius:13px;

}


.logout-icon{

    width:35px;
    height:35px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:10px;

    background:
        rgba(242,143,150,.08);

}


/* =========================================================
   MAIN
========================================================= */

.main{

    flex:1;

    min-width:0;

    display:flex;

    flex-direction:column;

    gap:18px;

}


/* =========================================================
   TOPBAR
========================================================= */

.topbar{

    min-height:82px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    padding:
        14px 22px;

    border:
        1px solid
        rgba(255,255,255,.92);

    border-radius:23px;

    background:
        rgba(255,255,255,.68);

    backdrop-filter:blur(20px);

    box-shadow:
        0 16px 42px
        rgba(55,113,129,.065);

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

    background:
        linear-gradient(
            180deg,
            var(--aqua),
            var(--purple)
        );

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


/* =========================================================
   AUDITORÍA
========================================================= */

.audit-card{

    padding:25px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:
        rgba(255,255,255,.78);

    box-shadow:
        0 22px 52px
        rgba(55,113,129,.07);

}


.audit-header{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:20px;

    margin-bottom:20px;

}


.audit-header h2{

    color:#416f7e;

    font-size:21px;

    font-weight:950;

}


.audit-header p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


/* =========================================================
   RESUMEN PEQUEÑO
========================================================= */

.summary{

    display:flex;

    gap:10px;

    flex-wrap:wrap;

}


.summary-item{

    display:flex;

    align-items:center;

    gap:8px;

    padding:
        9px 13px;

    border-radius:12px;

    color:#527c88;

    background:
        rgba(232,250,247,.65);

    border:
        1px solid
        rgba(255,255,255,.90);

    font-size:11px;

    font-weight:850;

}


.summary-item i{

    color:#0a9995;

}


/* =========================================================
   FILTROS
========================================================= */

.filters{

    display:grid;

    grid-template-columns:
        minmax(180px,1fr)
        minmax(180px,1fr)
        auto;

    gap:10px;

    margin-bottom:20px;

    padding:15px;

    border-radius:17px;

    background:
        rgba(232,250,247,.45);

    border:
        1px solid
        rgba(50,111,130,.06);

}


.filter-group label{

    display:block;

    margin-bottom:6px;

    color:#718f98;

    font-size:10px;

    font-weight:900;

}


.filter-group select,
.filter-group input{

    width:100%;

    height:43px;

    padding:
        0 12px;

    border:
        1px solid
        rgba(130,180,190,.20);

    border-radius:11px;

    outline:none;

    color:#416f7e;

    background:
        rgba(255,255,255,.78);

    font-family:inherit;

    font-size:12px;

    font-weight:750;

}


.filter-group select:focus,
.filter-group input:focus{

    border-color:
        rgba(24,216,206,.55);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.07);

}


.filter-buttons{

    display:flex;

    align-items:flex-end;

    gap:7px;

}


.btn-filter{

    height:43px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        0 15px;

    border:none;

    border-radius:11px;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18d8ce,
            #1599ad
        );

    font-family:inherit;

    font-size:11px;

    font-weight:900;

    text-decoration:none;

    cursor:pointer;

}


.btn-clear{

    height:43px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        0 13px;

    border:
        1px solid
        rgba(24,216,206,.18);

    border-radius:11px;

    color:#16848b;

    background:
        rgba(24,216,206,.08);

    font-size:11px;

    font-weight:900;

    text-decoration:none;

}


/* =========================================================
   TABLA
========================================================= */

.table-wrapper{

    overflow-x:auto;

    border-radius:17px;

    border:
        1px solid
        rgba(50,111,130,.08);

}


.audit-table{

    width:100%;

    min-width:760px;

    border-collapse:collapse;

}


.audit-table th{

    padding:
        14px 13px;

    text-align:left;

    color:#7d989f;

    background:
        rgba(232,250,247,.58);

    font-size:10px;

    font-weight:950;

    letter-spacing:.5px;

}


.audit-table td{

    padding:
        14px 13px;

    border-top:
        1px solid
        rgba(50,111,130,.06);

    color:#527c88;

    font-size:11px;

    font-weight:700;

}


.audit-table tbody tr{

    transition:.2s;

}


.audit-table tbody tr:hover{

    background:
        rgba(232,250,247,.35);

}


.id-cell{

    color:#8aa3aa !important;

    font-weight:850 !important;

}


.user-cell{

    color:#416f7e !important;

    font-weight:900 !important;

}


.action{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        6px 9px;

    border-radius:9px;

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

    font-size:9px;

    font-weight:950;

}


.description{

    max-width:420px;

    line-height:1.45;

}


.date-cell{

    white-space:nowrap;

    color:#7897a0 !important;

    font-size:10px !important;

}


.empty{

    padding:65px 20px;

    text-align:center;

}


.empty i{

    display:block;

    margin-bottom:12px;

    color:#8dc6c5;

    font-size:44px;

}


.empty strong{

    display:block;

    color:#527c88;

    font-size:15px;

    font-weight:950;

}


.empty p{

    margin-top:6px;

    color:#8aa3aa;

    font-size:11px;

    font-weight:650;

}


/* =========================================================
   RESPONSIVE
========================================================= */

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

        grid-template-columns:
            repeat(2,1fr);

    }

    .menu-label{

        grid-column:
            1 / -1;

    }

    .sidebar-bottom{

        display:none;

    }

}


@media(max-width:700px){

    .filters{

        grid-template-columns:1fr;

    }

    .filter-buttons{

        align-items:stretch;

    }

    .btn-filter,
    .btn-clear{

        flex:1;

    }

}


@media(max-width:650px){

    .topbar{

        align-items:flex-start;

        flex-direction:column;

    }

    .navigation{

        grid-template-columns:1fr;

    }

    .menu-label{

        grid-column:auto;

    }

    .audit-card{

        padding:20px;

    }

    .audit-header{

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
                src="Logo.png"
                alt="Logo Asistencia QR"
            >

        </div>

        <div class="sidebar-title">

            <strong>
                ASISTENCIA QR
            </strong>

            <small>
                Sistema académico
            </small>

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


            <a
                href="dashboard.php"
                class="nav-link"
            >

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

                GESTIÓN ACADÉMICA

            </div>


            <a
                href="curso_estudiantes.php"
                class="nav-link"
            >

                <div class="nav-icon academic">
                    <i class="bi bi-mortarboard"></i>
                </div>

                <span>Cursos</span>

                <span class="nav-arrow">→</span>

            </a>

        </div>


        <div class="menu-section">

            <div class="menu-label">

                <span class="label-line"></span>

                PERSONAS

            </div>


            <a
                href="docentes.php"
                class="nav-link"
            >

                <div class="nav-icon people">
                    <i class="bi bi-person-workspace"></i>
                </div>

                <span>Docentes</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="usuarios.php"
                class="nav-link"
            >

                <div class="nav-icon people">
                    <i class="bi bi-person-badge"></i>
                </div>

                <span>Usuarios</span>

                <span class="nav-arrow">→</span>

            </a>

        </div>


        <div class="menu-section">

            <div class="menu-label">

                <span class="label-line"></span>

                CONTROL

            </div>


            <a
                href="asistencia.php"
                class="nav-link"
            >

                <div class="nav-icon qr-icon">
                    <i class="bi bi-qr-code-scan"></i>
                </div>

                <span>Asistencia</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="restaurante.php"
                class="nav-link"
            >

                <div class="nav-icon restaurant">
                    <i class="bi bi-egg-fried"></i>
                </div>

                <span>Restaurante</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="reportes.php"
                class="nav-link"
            >

                <div class="nav-icon reports">
                    <i class="bi bi-bar-chart-line"></i>
                </div>

                <span>Reportes</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="auditoria.php"
                class="nav-link active"
            >

                <div class="nav-icon audit">
                    <i class="bi bi-shield-check"></i>
                </div>

                <span>Auditoría</span>

                <span class="nav-arrow">→</span>

            </a>

        </div>

    </nav>


    <div class="sidebar-bottom">


        <div class="profile-card">

            <div class="profile-avatar">

                <?= htmlspecialchars(
                    $iniciales
                ) ?>

            </div>


            <div class="profile-info">

                <strong>

                    <?= htmlspecialchars(
                        $nombreUsuario
                    ) ?>

                </strong>


                <small>
                    ADMINISTRADOR
                </small>

            </div>


            <div class="profile-status">
                ●
            </div>

        </div>


        <a
            href="../auth/logout.php"
            class="logout"

            onclick="
                return confirm(
                    '¿Deseas cerrar tu sesión?'
                );
            "
        >

            <div class="logout-icon">

                <i class="bi bi-box-arrow-left"></i>

            </div>

            <span>
                Cerrar sesión
            </span>

        </a>

    </div>

</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">


<!-- =====================================================
     TOPBAR
====================================================== -->

<header class="topbar">


    <div class="page-info">

        <div class="page-indicator"></div>


        <div class="page-title">

            <h1>
                Auditoría del sistema
            </h1>

            <p>
                Consulta las actividades registradas en el sistema
            </p>

        </div>

    </div>

</header>


<!-- =====================================================
     TARJETA PRINCIPAL
====================================================== -->

<section class="audit-card">


    <div class="audit-header">

        <div>

            <h2>
                Registro de actividades
            </h2>

            <p>
                Historial de acciones realizadas por los usuarios
            </p>

        </div>


        <div class="summary">

            <div class="summary-item">

                <i class="bi bi-list-check"></i>

                <?= $totalRegistros ?>

                registros

            </div>


            <div class="summary-item">

                <i class="bi bi-calendar-check"></i>

                <?= $totalHoy ?>

                hoy

            </div>

        </div>

    </div>


    <!-- =================================================
         FILTROS
    ================================================== -->

    <form
        method="GET"
        class="filters"
    >


        <div class="filter-group">

            <label>
                ACCIÓN
            </label>


            <select name="accion">

                <option value="">
                    Todas las acciones
                </option>


                <?php foreach (
                    $acciones
                    as $accion
                ): ?>

                    <option
                        value="<?= htmlspecialchars($accion) ?>"
                        <?= (
                            $filtroAccion === $accion
                            ? 'selected'
                            : ''
                        ) ?>
                    >

                        <?= htmlspecialchars($accion) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="filter-group">

            <label>
                FECHA
            </label>


            <input
                type="date"
                name="fecha"
                value="<?= htmlspecialchars($filtroFecha) ?>"
            >

        </div>


        <div class="filter-buttons">

            <button
                type="submit"
                class="btn-filter"
            >

                <i class="bi bi-funnel"></i>

                Filtrar

            </button>


            <a
                href="auditoria.php"
                class="btn-clear"
            >

                <i class="bi bi-arrow-counterclockwise"></i>

                Limpiar

            </a>

        </div>


    </form>


    <!-- =================================================
         TABLA
    ================================================== -->

    <div class="table-wrapper">


        <?php if (
            count($registros) > 0
        ): ?>


            <table class="audit-table">


                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            USUARIO
                        </th>

                        <th>
                            ACCIÓN
                        </th>

                        <th>
                            DESCRIPCIÓN
                        </th>

                        <th>
                            FECHA Y HORA
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $registros
                        as $registro
                    ): ?>


                        <tr>


                            <td class="id-cell">

                                #<?= (int)$registro['id_auditoria'] ?>

                            </td>


                            <td class="user-cell">

                                <?php

                                $usuarioMostrar =
                                    trim(
                                        $registro['usuario'] ?? ''
                                    );

                                if (
                                    $usuarioMostrar === ''
                                ) {

                                    $usuarioMostrar =
                                        'Usuario #'
                                        . (
                                            (int)
                                            $registro['id_usuario']
                                        );

                                }

                                ?>

                                <?= htmlspecialchars(
                                    $usuarioMostrar
                                ) ?>

                            </td>


                            <td>

                                <span class="action">

                                    <i class="bi bi-activity"></i>

                                    <?= htmlspecialchars(
                                        $registro['accion']
                                    ) ?>

                                </span>

                            </td>


                            <td class="description">

                                <?php

                                $descripcion =
                                    trim(
                                        $registro['descripcion']
                                        ?? ''
                                    );

                                if (
                                    $descripcion === ''
                                ) {

                                    $descripcion =
                                        'Sin descripción';

                                }

                                ?>

                                <?= htmlspecialchars(
                                    $descripcion
                                ) ?>

                            </td>


                            <td class="date-cell">

                                <?= htmlspecialchars(
                                    $registro['fecha_hora']
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">

                <i class="bi bi-shield-check"></i>


                <strong>
                    No hay registros de auditoría
                </strong>


                <p>
                    No se encontraron actividades con los filtros seleccionados.
                </p>

            </div>


        <?php endif; ?>


    </div>


</section>


</main>

</div>


</body>

</html>