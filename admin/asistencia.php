<?php

session_start();

/* =========================================================
   PROTECCIÓN DE SESIÓN Y ROL ADMINISTRADOR
========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['id_rol']) || (int)$_SESSION['id_rol'] !== 1) {
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

$primerNombre = $partesNombre[0] ?? 'Administrador';

$iniciales = '';

foreach (array_slice($partesNombre, 0, 2) as $parte) {

    $iniciales .= strtoupper(
        substr($parte, 0, 1)
    );

}

if ($iniciales === '') {
    $iniciales = 'AS';
}

/* =========================================================
   FECHA Y HORA
========================================================= */

$horaActual = date('H:i:s');
$fechaActual = date('d/m/Y');
$fechaHoy = date('Y-m-d');

/* =========================================================
   TOTAL LLEGADAS TARDE
========================================================= */

$totalLlegadas = 0;

$stmt = mysqli_prepare(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM asistencia_llegada
     WHERE fecha = ?"
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $fechaHoy
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    $fila = mysqli_fetch_assoc($resultado);

    $totalLlegadas = (int)(
        $fila['total'] ?? 0
    );

    mysqli_stmt_close($stmt);
}

/* =========================================================
   ESTUDIANTES ACTIVOS
========================================================= */

$totalEstudiantes = 0;

$resultado = mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM estudiantes
     WHERE estado = 'ACTIVO'"
);

if ($resultado) {

    $fila = mysqli_fetch_assoc($resultado);

    $totalEstudiantes = (int)(
        $fila['total'] ?? 0
    );
}

/* =========================================================
   CURSOS ACTIVOS
========================================================= */

$totalCursos = 0;

$resultado = mysqli_query(
    $conexion,
    "SELECT COUNT(*) AS total
     FROM cursos
     WHERE estado = 'ACTIVO'"
);

if ($resultado) {

    $fila = mysqli_fetch_assoc($resultado);

    $totalCursos = (int)(
        $fila['total'] ?? 0
    );
}

/* =========================================================
   LLEGADAS DE HOY
========================================================= */

$llegadas = [];

$sqlLlegadas = "
    SELECT
        al.id_llegada,
        al.fecha,
        al.hora_llegada,
        al.estado,
        e.documento,
        e.nombres,
        e.apellidos,
        c.nombre_curso,

        COALESCE(
            CONCAT(
                u.nombre,
                ' ',
                u.apellido
            ),
            'Sin director asignado'
        ) AS docente

    FROM asistencia_llegada al

    INNER JOIN estudiantes e
        ON e.id_estudiante = al.id_estudiante

    LEFT JOIN cursos c
        ON c.id_curso = e.id_curso

    LEFT JOIN director_curso dc
        ON dc.id_curso = e.id_curso

    LEFT JOIN usuarios u
        ON u.id_usuario = dc.id_usuario
        AND u.id_rol = 2
        AND u.estado = 'ACTIVO'

    WHERE al.fecha = ?

    ORDER BY al.hora_llegada DESC
";

$stmt = mysqli_prepare(
    $conexion,
    $sqlLlegadas
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $fechaHoy
    );

    mysqli_stmt_execute($stmt);

    $resultado =
        mysqli_stmt_get_result($stmt);

    while (
        $fila = mysqli_fetch_assoc($resultado)
    ) {

        $llegadas[] = $fila;

    }

    mysqli_stmt_close($stmt);
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
    Asistencia QR | Llegadas tarde
</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<script
    src="https://unpkg.com/html5-qrcode"
    type="text/javascript"
></script>

<style>

/* =========================================================
   MISMO ESTILO DEL DASHBOARD ADMIN
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

.nav-arrow{

    margin-left:auto;

    color:#a1b8be;

    opacity:0;

    transition:.2s;

}

.nav-link:hover .nav-arrow{
    opacity:1;
}


/* =========================================================
   PERFIL
========================================================= */

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

.clock-box{

    display:flex;

    align-items:center;

    gap:12px;

    padding:
        10px 15px;

    border-radius:15px;

    background:
        rgba(255,255,255,.73);

    border:
        1px solid
        rgba(255,255,255,.90);

}

.clock-icon{

    width:38px;
    height:38px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:11px;

    color:#0b9f9c;

    background:
        rgba(24,216,206,.10);

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


/* =========================================================
   CONTENIDO ASISTENCIA
========================================================= */

.attendance-content{

    display:flex;

    flex-direction:column;

    gap:16px;

}


/* =========================================================
   ENCABEZADO
========================================================= */

.module-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    padding:
        23px 25px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        rgba(255,255,255,.74);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

}

.module-title{

    display:flex;

    align-items:center;

    gap:14px;

}

.module-icon{

    width:57px;
    height:57px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:17px;

    color:#078d98;

    background:
        rgba(24,216,206,.11);

    font-size:25px;

}

.module-title h2{

    color:#416f7e;

    font-size:21px;

    font-weight:950;

}

.module-title p{

    margin-top:4px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}

.date-badge{

    display:flex;

    align-items:center;

    gap:7px;

    padding:
        9px 13px;

    border-radius:11px;

    color:#17867f;

    background:
        rgba(24,216,206,.08);

    font-size:11px;

    font-weight:900;

}


/* =========================================================
   RESUMEN
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(3,minmax(0,1fr));

    gap:16px;

}

.summary-item{

    position:relative;

    min-height:115px;

    overflow:hidden;

    display:flex;

    align-items:center;

    gap:17px;

    padding:
        20px 22px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:23px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

}

.summary-item::after{

    content:"";

    position:absolute;

    width:90px;
    height:90px;

    right:-35px;
    bottom:-45px;

    border-radius:50%;

    background:
        rgba(24,216,206,.055);

}

.summary-icon{

    width:60px;
    height:60px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:18px;

    color:#c77b58;

    background:
        rgba(233,154,120,.11);

    font-size:27px;

}

.summary-item:nth-child(2)
.summary-icon{

    color:#5578ca;

    background:
        rgba(105,184,213,.12);

}

.summary-item:nth-child(3)
.summary-icon{

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

}

.summary-text span{

    display:block;

    color:#819da5;

    font-size:11px;

    font-weight:850;

}

.summary-text strong{

    display:block;

    margin-top:5px;

    color:#315f70;

    font-size:29px;

    font-weight:950;

    line-height:1;

}


/* =========================================================
   ESCÁNER + TABLA
========================================================= */

.attendance-layout{

    display:grid;

    grid-template-columns:
        minmax(330px,.72fr)
        minmax(0,1.8fr);

    gap:16px;

}


/* =========================================================
   CARD GENERAL
========================================================= */

.attendance-card{

    min-width:0;

    padding:
        23px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        rgba(255,255,255,.74);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

}

.card-heading{

    display:flex;

    align-items:center;

    gap:11px;

    margin-bottom:17px;

}

.card-heading-icon{

    width:43px;
    height:43px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:13px;

    color:#078d98;

    background:
        rgba(24,216,206,.10);

    font-size:18px;

}

.card-heading h3{

    color:#416f7e;

    font-size:17px;

    font-weight:950;

}

.card-heading p{

    margin-top:3px;

    color:#819ca4;

    font-size:10px;

    font-weight:650;

}


/* =========================================================
   READER
========================================================= */

.reader-container{

    min-height:320px;

    overflow:hidden;

    border-radius:20px;

    background:
        linear-gradient(
            145deg,
            rgba(232,250,247,.90),
            rgba(234,246,251,.90)
        );

    border:
        1px solid
        rgba(255,255,255,.90);

}

#reader{

    width:100%;

    min-height:320px;

}

#reader video{

    width:100% !important;

    height:auto !important;

    border-radius:19px;

    object-fit:cover;

}

#reader button{

    border:0 !important;

    border-radius:10px !important;

    padding:
        8px 12px !important;

    background:
        var(--aqua) !important;

    color:#075e6c !important;

    font-family:inherit !important;

    font-weight:900 !important;

}

#reader select{

    border:
        1px solid
        #d4e8e9 !important;

    border-radius:9px !important;

    padding:7px !important;

    font-family:inherit !important;

}


/* =========================================================
   BOTONES
========================================================= */

.scanner-buttons{

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:9px;

    margin-top:11px;

}

.scanner-btn{

    min-height:43px;

    border:0;

    border-radius:13px;

    font-family:inherit;

    font-size:10px;

    font-weight:950;

    cursor:pointer;

    transition:.2s;

}

.scanner-start{

    color:#087d92;

    background:
        rgba(24,216,206,.13);

}

.scanner-start:hover{

    background:
        rgba(24,216,206,.21);

}

.scanner-stop{

    color:#bd7058;

    background:
        rgba(233,154,120,.13);

}

.scanner-stop:hover{

    background:
        rgba(233,154,120,.21);

}


/* =========================================================
   ESTADO
========================================================= */

.scanner-status{

    display:flex;

    align-items:center;

    gap:8px;

    margin-top:11px;

    padding:
        11px 12px;

    border-radius:13px;

    color:#7898a0;

    background:
        rgba(255,255,255,.60);

    font-size:10px;

    font-weight:850;

}

.status-dot{

    width:8px;
    height:8px;

    flex-shrink:0;

    border-radius:50%;

    background:#bdcfd3;

}

.status-dot.active{

    background:
        var(--mint);

    box-shadow:
        0 0 0 4px
        rgba(66,205,161,.10);

}

.status-dot.error{

    background:#e5898f;

}


/* =========================================================
   MENSAJE
========================================================= */

.scan-message{

    display:none;

    margin-top:10px;

    padding:
        11px 12px;

    border-radius:13px;

    font-size:10px;

    line-height:1.5;

    font-weight:850;

}

.scan-message.show{
    display:block;
}

.scan-message strong{

    display:block;

    margin-bottom:3px;

    font-size:11px;

}

.scan-message.success{

    color:#277e63;

    background:
        rgba(66,205,161,.12);

}

.scan-message.warning{

    color:#9a741e;

    background:
        rgba(240,184,77,.13);

}

.scan-message.error{

    color:#ad6069;

    background:
        rgba(242,143,150,.12);

}

.scan-info{

    margin-top:10px;

    padding:
        11px 12px;

    border-radius:13px;

    color:#7c999f;

    background:
        rgba(255,255,255,.48);

    font-size:9px;

    line-height:1.55;

    font-weight:700;

}


/* =========================================================
   TABLA
========================================================= */

.table-container{

    width:100%;

    overflow:auto;

}

.arrival-table{

    width:100%;

    min-width:720px;

    border-collapse:separate;

    border-spacing:
        0 6px;

}

.arrival-table th{

    padding:
        10px 12px;

    color:#91a8ae;

    /* CAMBIO: letras más grandes */
    font-size:12px;

    font-weight:950;

    letter-spacing:.6px;

    text-align:left;

}

.arrival-table tbody tr{

    background:
        rgba(255,255,255,.62);

}

.arrival-table td{

    padding:
        14px 12px;

    color:#698a93;

    /* CAMBIO: letras más grandes */
    font-size:14px;

    font-weight:750;

    border-top:
        1px solid
        rgba(255,255,255,.78);

    border-bottom:
        1px solid
        rgba(255,255,255,.78);

}

.arrival-table td:first-child{

    border-left:
        1px solid
        rgba(255,255,255,.78);

    border-radius:
        12px 0 0 12px;

}

.arrival-table td:last-child{

    border-right:
        1px solid
        rgba(255,255,255,.78);

    border-radius:
        0 12px 12px 0;

}

.hour{

    color:#2c6374 !important;

    font-weight:950 !important;

    white-space:nowrap;

}

.student-name{

    color:#315f70;

    font-weight:950;

}

.document{

    color:#648995;

    font-weight:850;

    white-space:nowrap;

}

.course{

    font-weight:900;

}

.teacher{

    white-space:nowrap;

}

.status-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        7px 10px;

    border-radius:9px;

    color:#b4646c;

    background:
        rgba(242,143,150,.11);

    /* CAMBIO: letras del estado más grandes */
    font-size:11px;

    font-weight:950;

    white-space:nowrap;

}


/* =========================================================
   VACÍO
========================================================= */

.no-arrivals{

    min-height:330px;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    text-align:center;

}

.no-arrivals i{

    margin-bottom:10px;

    color:#8dc6c5;

    font-size:38px;

}

.no-arrivals strong{

    color:#416f7e;

    font-size:13px;

    font-weight:950;

}

.no-arrivals p{

    margin-top:4px;

    color:#8aa3aa;

    font-size:10px;

    font-weight:700;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px){

    .sidebar{
        width:255px;
    }

    .attendance-layout{
        grid-template-columns:1fr;
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

    .summary-grid{
        grid-template-columns:1fr;
    }

    .module-header{
        align-items:flex-start;
        flex-direction:column;
    }

}

@media(max-width:650px){

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

    .scanner-buttons{
        grid-template-columns:1fr;
    }

}

</style>

</head>


<body>


<div class="app">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


    <div class="sidebar-header">

        <div class="logo-container">

            <img
                src="../Logo.png"
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


        <!-- NAVEGACIÓN -->

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

                <span>
                    Inicio
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>

        </div>


        <!-- GESTIÓN ACADÉMICA -->

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

                <span>
                    Cursos
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>

        </div>


        <!-- PERSONAS -->

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

                <span>
                    Docentes
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>


            <a
                href="usuarios.php"
                class="nav-link"
            >

                <div class="nav-icon people">

                    <i class="bi bi-person-badge"></i>

                </div>

                <span>
                    Usuarios
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>

        </div>


        <!-- CONTROL -->

        <div class="menu-section">

            <div class="menu-label">

                <span class="label-line"></span>

                CONTROL

            </div>


            <a
                href="asistencia.php"
                class="nav-link active"
            >

                <div class="nav-icon qr-icon">

                    <i class="bi bi-qr-code-scan"></i>

                </div>

                <span>
                    Asistencia
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>


            <a
                href="restaurante.php"
                class="nav-link"
            >

                <div class="nav-icon restaurant">

                    <i class="bi bi-egg-fried"></i>

                </div>

                <span>
                    Restaurante
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>


            <a
                href="reportes.php"
                class="nav-link"
            >

                <div class="nav-icon reports">

                    <i class="bi bi-bar-chart-line"></i>

                </div>

                <span>
                    Reportes
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>

        </div>


    </nav>


    <!-- PERFIL -->

    <div class="sidebar-bottom">

        <div class="profile-card">

            <div class="profile-avatar">

                <?= htmlspecialchars($iniciales) ?>

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
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="page-info">

            <div class="page-indicator"></div>


            <div class="page-title">

                <h1>
                    Llegadas tarde
                </h1>

                <p>
                    Control de llegada de estudiantes al colegio
                </p>

            </div>

        </div>


        <div class="clock-box">

            <div class="clock-icon">

                <i class="bi bi-clock"></i>

            </div>


            <div>

                <div
                    class="clock-time"
                    id="reloj"
                >
                    <?= $horaActual ?>
                </div>

                <div class="clock-date">
                    <?= $fechaActual ?>
                </div>

            </div>

        </div>


    </header>


    <!-- =================================================
         CONTENIDO
    ================================================== -->

    <section class="attendance-content">


        <!-- ENCABEZADO -->

        <div class="module-header">


            <div class="module-title">

                <div class="module-icon">

                    <i class="bi bi-qr-code-scan"></i>

                </div>


                <div>

                    <h2>
                        Registro de llegada
                    </h2>

                    <p>
                        Escanea el código QR del estudiante
                    </p>

                </div>

            </div>


            <div class="date-badge">

                <i class="bi bi-calendar-check"></i>

                <?= $fechaActual ?>

            </div>

        </div>


        <!-- =================================================
             RESUMEN
        ================================================== -->

        <div class="summary-grid">


            <div class="summary-item">

                <div class="summary-icon">

                    <i class="bi bi-person-exclamation"></i>

                </div>


                <div class="summary-text">

                    <span>
                        Llegadas tarde hoy
                    </span>

                    <strong>
                        <?= $totalLlegadas ?>
                    </strong>

                </div>

            </div>


            <div class="summary-item">

                <div class="summary-icon">

                    <i class="bi bi-people-fill"></i>

                </div>


                <div class="summary-text">

                    <span>
                        Estudiantes activos
                    </span>

                    <strong>
                        <?= $totalEstudiantes ?>
                    </strong>

                </div>

            </div>


            <div class="summary-item">

                <div class="summary-icon">

                    <i class="bi bi-mortarboard-fill"></i>

                </div>


                <div class="summary-text">

                    <span>
                        Cursos activos
                    </span>

                    <strong>
                        <?= $totalCursos ?>
                    </strong>

                </div>

            </div>


        </div>


        <!-- =================================================
             ESCÁNER + TABLA
        ================================================== -->

        <div class="attendance-layout">


            <!-- =================================================
                 ESCÁNER
            ================================================== -->

            <div class="attendance-card">


                <div class="card-heading">

                    <div class="card-heading-icon">

                        <i class="bi bi-camera-fill"></i>

                    </div>


                    <div>

                        <h3>
                            Escáner QR
                        </h3>

                        <p>
                            Cámara del dispositivo
                        </p>

                    </div>

                </div>


                <div class="reader-container">

                    <div id="reader"></div>

                </div>


                <div class="scanner-buttons">


                    <button
                        type="button"
                        class="scanner-btn scanner-start"
                        onclick="iniciarScanner()"
                    >

                        <i class="bi bi-camera"></i>

                        Iniciar cámara

                    </button>


                    <button
                        type="button"
                        class="scanner-btn scanner-stop"
                        onclick="detenerScanner()"
                    >

                        <i class="bi bi-stop-circle"></i>

                        Detener cámara

                    </button>


                </div>


                <div class="scanner-status">

                    <span
                        id="statusDot"
                        class="status-dot"
                    ></span>


                    <span id="scannerStatus">

                        Cámara detenida

                    </span>

                </div>


                <div
                    id="scanMessage"
                    class="scan-message"
                ></div>


                <div class="scan-info">

                    <i class="bi bi-info-circle"></i>

                    Escanea únicamente el código QR del estudiante.
                    El sistema verificará que esté activo,
                    que su curso esté activo y que no tenga
                    una llegada registrada durante el día.

                </div>


            </div>


            <!-- =================================================
                 TABLA
            ================================================== -->

            <div class="attendance-card">


                <div class="card-heading">

                    <div
                        class="card-heading-icon"
                        style="
                            color:#7569c2;
                            background:rgba(133,121,210,.10);
                        "
                    >

                        <i class="bi bi-list-check"></i>

                    </div>


                    <div>

                        <h3>
                            Llegadas registradas hoy
                        </h3>

                        <p>
                            Registro del <?= $fechaActual ?>
                        </p>

                    </div>

                </div>


                <?php if (!empty($llegadas)): ?>


                    <div class="table-container">


                        <table class="arrival-table">


                            <thead>

                                <tr>

                                    <th>
                                        HORA
                                    </th>

                                    <th>
                                        DOCUMENTO
                                    </th>

                                    <th>
                                        ESTUDIANTE
                                    </th>

                                    <th>
                                        CURSO
                                    </th>

                                    <th>
                                        DOCENTE
                                    </th>

                                    <th>
                                        ESTADO
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $llegadas
                                    as $llegada
                                ): ?>


                                    <tr>


                                        <td class="hour">

                                            <?= htmlspecialchars(
                                                date(
                                                    'H:i:s',
                                                    strtotime(
                                                        $llegada[
                                                        'hora_llegada'
                                                        ]
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <td class="document">

                                            <?= htmlspecialchars(
                                                $llegada[
                                                    'documento'
                                                ]
                                            ) ?>

                                        </td>


                                        <td>

                                            <div class="student-name">

                                                <?= htmlspecialchars(
                                                    $llegada['nombres']
                                                    . ' '
                                                    . $llegada['apellidos']
                                                ) ?>

                                            </div>

                                        </td>


                                        <td class="course">

                                            <?= htmlspecialchars(
                                                $llegada[
                                                    'nombre_curso'
                                                ]
                                                ?? 'Sin curso'
                                            ) ?>

                                        </td>


                                        <td class="teacher">

                                            <?= htmlspecialchars(
                                                $llegada[
                                                    'docente'
                                                ]
                                                ?? 'Sin docente'
                                            ) ?>

                                        </td>


                                        <td>

                                            <span class="status-badge">

                                                <i class="bi bi-clock-history"></i>

                                                <?= htmlspecialchars(
                                                    $llegada[
                                                        'estado'
                                                    ]
                                                ) ?>

                                            </span>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="no-arrivals">

                        <i class="bi bi-calendar2-check"></i>

                        <strong>
                            No hay llegadas registradas
                        </strong>

                        <p>
                            Las llegadas tarde de hoy aparecerán aquí.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </section>


</main>


</div>


<script>

/* =========================================================
   RELOJ
========================================================= */

function actualizarReloj(){

    const ahora = new Date();

    const horas =
        String(
            ahora.getHours()
        ).padStart(
            2,
            '0'
        );

    const minutos =
        String(
            ahora.getMinutes()
        ).padStart(
            2,
            '0'
        );

    const segundos =
        String(
            ahora.getSeconds()
        ).padStart(
            2,
            '0'
        );


    const reloj =
        document.getElementById(
            'reloj'
        );


    if(reloj){

        reloj.textContent =
            horas
            + ':'
            + minutos
            + ':'
            + segundos;

    }

}


actualizarReloj();

setInterval(
    actualizarReloj,
    1000
);


/* =========================================================
   ESCÁNER
========================================================= */

let lectorQR = null;

let camaraActiva = false;

let procesandoQR = false;


/* =========================================================
   ESTADO DEL ESCÁNER
========================================================= */

function cambiarEstado(
    texto,
    tipo = ''
){

    const estado =
        document.getElementById(
            'scannerStatus'
        );

    const punto =
        document.getElementById(
            'statusDot'
        );


    estado.textContent =
        texto;


    punto.classList.remove(
        'active',
        'error'
    );


    if(tipo === 'active'){

        punto.classList.add(
            'active'
        );

    }


    if(tipo === 'error'){

        punto.classList.add(
            'error'
        );

    }

}


/* =========================================================
   MENSAJES
========================================================= */

function mostrarMensaje(
    tipo,
    titulo,
    texto
){

    const caja =
        document.getElementById(
            'scanMessage'
        );


    caja.className =
        'scan-message show '
        + tipo;


    caja.innerHTML =
        '<strong>'
        + titulo
        + '</strong>'
        + texto;

}


function ocultarMensaje(){

    const caja =
        document.getElementById(
            'scanMessage'
        );


    caja.className =
        'scan-message';


    caja.innerHTML =
        '';

}


/* =========================================================
   INICIAR ESCÁNER
========================================================= */

async function iniciarScanner(){

    if(camaraActiva){

        return;

    }


    ocultarMensaje();


    try{


        if(lectorQR){

            try{

                await lectorQR.clear();

            }catch(e){}

        }


        lectorQR =
            new Html5Qrcode(
                "reader"
            );


        cambiarEstado(
            "Solicitando acceso a la cámara..."
        );


        await lectorQR.start(

            {
                facingMode:
                    "environment"
            },


            {
                fps:10,

                qrbox:{
                    width:240,
                    height:240
                },

                aspectRatio:1

            },


            async function(decodedText){

                if(procesandoQR){

                    return;

                }


                procesandoQR = true;


                await detenerScanner();


                procesarQR(
                    decodedText
                );

            },


            function(){

                /*
                 * Error normal de lectura.
                 * No mostramos nada.
                 */

            }

        );


        camaraActiva = true;


        cambiarEstado(
            "Cámara activa. Escanea un código QR.",
            "active"
        );


    }catch(error){


        console.error(error);


        camaraActiva = false;


        cambiarEstado(
            "No fue posible iniciar la cámara.",
            "error"
        );


        mostrarMensaje(
            "error",
            "No fue posible acceder a la cámara",
            "Verifica los permisos del navegador y vuelve a intentar."
        );

    }

}


/* =========================================================
   DETENER ESCÁNER
========================================================= */

async function detenerScanner(){

    if(!lectorQR){

        camaraActiva = false;


        cambiarEstado(
            "Cámara detenida"
        );


        return;

    }


    try{

        if(camaraActiva){

            await lectorQR.stop();

        }

    }catch(error){

        console.warn(error);

    }


    try{

        await lectorQR.clear();

    }catch(error){

        console.warn(error);

    }


    lectorQR = null;

    camaraActiva = false;


    cambiarEstado(
        "Cámara detenida"
    );

}


/* =========================================================
   PROCESAR QR
========================================================= */

async function procesarQR(
    documento
){

    documento =
        String(documento).trim();


    if(documento === ''){


        mostrarMensaje(
            "error",
            "Código inválido",
            "El QR no contiene un documento válido."
        );


        procesandoQR = false;


        return;

    }


    cambiarEstado(
        "Verificando estudiante..."
    );


    try{


        const datos =
            new URLSearchParams();


        datos.append(
            'documento',
            documento
        );


        const respuesta =
            await fetch(
                'procesar_llegada.php',
                {

                    method:'POST',

                    headers:{
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8'
                    },

                    body:
                        datos.toString()

                }
            );


        const resultado =
            await respuesta.json();


        /* =================================================
           RESPUESTA OK
        ================================================= */

        if(resultado.ok){


            /* =============================================
               DUPLICADO
            ============================================= */

            if(resultado.duplicado){


                mostrarMensaje(
                    "warning",
                    "Llegada ya registrada",
                    resultado.estudiante
                    + '<br>Documento: '
                    + resultado.documento
                    + '<br>Hora registrada: '
                    + resultado.hora
                );


                cambiarEstado(
                    "El estudiante ya tiene llegada registrada hoy."
                );


                procesandoQR = false;


                setTimeout(
                    function(){

                        ocultarMensaje();

                        iniciarScanner();

                    },
                    2500
                );


                return;

            }


            /* =============================================
               REGISTRO CORRECTO
            ============================================= */

            mostrarMensaje(
                "success",
                "Llegada registrada correctamente",
                resultado.estudiante
                + '<br>Curso: '
                + resultado.curso
                + '<br>Hora: '
                + resultado.hora
            );


            cambiarEstado(
                "Llegada registrada correctamente.",
                "active"
            );


            setTimeout(
                function(){

                    location.reload();

                },
                2200
            );


            return;

        }


        /* =================================================
           ERROR
        ================================================= */

        mostrarMensaje(
            "error",
            "No se pudo registrar",
            resultado.mensaje
            ||
            "Ocurrió un error."
        );


        cambiarEstado(
            "Listo para escanear nuevamente."
        );


        procesandoQR = false;


        setTimeout(
            function(){

                ocultarMensaje();

                iniciarScanner();

            },
            2500
        );


    }catch(error){


        console.error(error);


        mostrarMensaje(
            "error",
            "Error de comunicación",
            "No fue posible comunicarse con el servidor."
        );


        cambiarEstado(
            "Error al procesar el código.",
            "error"
        );


        procesandoQR = false;


        setTimeout(
            function(){

                ocultarMensaje();

                iniciarScanner();

            },
            2500
        );

    }

}


/* =========================================================
   CERRAR CÁMARA AL SALIR
========================================================= */

window.addEventListener(
    'beforeunload',
    function(){

        if(
            lectorQR &&
            camaraActiva
        ){

            lectorQR
                .stop()
                .catch(
                    () => {}
                );

        }

    }
);

</script>


</body>

</html>