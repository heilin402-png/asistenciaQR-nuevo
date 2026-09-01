<?php

session_start();

/* =========================================================
   PROTECCIÓN DE SESIÓN
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
   DATOS DEL DOCENTE
========================================================= */

$nombreUsuario =
    $_SESSION['nombre']
    ?? 'Docente';


$partesNombre =
    preg_split(
        '/\s+/',
        trim($nombreUsuario)
    );


$primerNombre =
    $partesNombre[0]
    ?? 'Docente';


$iniciales = '';


foreach (
    array_slice(
        $partesNombre,
        0,
        2
    )
    as $parte
) {

    $iniciales .= strtoupper(
        substr(
            $parte,
            0,
            1
        )
    );

}


if ($iniciales === '') {

    $iniciales = 'DO';

}


/* =========================================================
   FECHA Y HORA
========================================================= */

$horaActual =
    date('H:i:s');


$fechaActual =
    date('d/m/Y');


/* =========================================================
   ID DEL DOCENTE
========================================================= */

$idDocente =
    (int) $_SESSION['id_usuario'];


/* =========================================================
   OBTENER ID DEL CURSO
========================================================= */

$idCurso =
    isset($_GET['id_curso'])
    ? (int) $_GET['id_curso']
    : 0;


/* =========================================================
   VALIDAR CURSO
========================================================= */

$curso = null;


if ($idCurso > 0) {

    /*
       El curso debe estar asignado al docente
       mediante la tabla docente_curso.
    */

    $sqlCurso = "

        SELECT

            c.id_curso,
            c.nombre_curso,
            c.estado

        FROM cursos c

        INNER JOIN docente_curso dc

            ON dc.id_curso = c.id_curso

        WHERE c.id_curso = ?

        AND dc.id_usuario = ?

        LIMIT 1

    ";


    $stmtCurso =
        mysqli_prepare(
            $conexion,
            $sqlCurso
        );


    if ($stmtCurso) {

        mysqli_stmt_bind_param(
            $stmtCurso,
            "ii",
            $idCurso,
            $idDocente
        );


        mysqli_stmt_execute(
            $stmtCurso
        );


        $resultadoCurso =
            mysqli_stmt_get_result(
                $stmtCurso
            );


        $curso =
            mysqli_fetch_assoc(
                $resultadoCurso
            );


        mysqli_stmt_close(
            $stmtCurso
        );

    }

}


/* =========================================================
   SI EL CURSO NO EXISTE O NO PERTENECE AL DOCENTE
========================================================= */

if (!$curso) {

    header("Location: cursos.php");
    exit();

}


/* =========================================================
   OBTENER ESTUDIANTES
========================================================= */

$estudiantes = [];


$sqlEstudiantes = "

    SELECT

        id_estudiante,
        documento,
        nombres,
        apellidos,
        estado,
        fecha_creacion

    FROM estudiantes

    WHERE id_curso = ?

    ORDER BY apellidos ASC, nombres ASC

";


$stmtEstudiantes =
    mysqli_prepare(
        $conexion,
        $sqlEstudiantes
    );


if ($stmtEstudiantes) {

    mysqli_stmt_bind_param(
        $stmtEstudiantes,
        "i",
        $idCurso
    );


    mysqli_stmt_execute(
        $stmtEstudiantes
    );


    $resultadoEstudiantes =
        mysqli_stmt_get_result(
            $stmtEstudiantes
        );


    while (
        $fila =
        mysqli_fetch_assoc(
            $resultadoEstudiantes
        )
    ) {

        $estudiantes[] =
            $fila;

    }


    mysqli_stmt_close(
        $stmtEstudiantes
    );

}


/* =========================================================
   CONTADORES
========================================================= */

$totalEstudiantes =
    count($estudiantes);


$estudiantesActivos = 0;


foreach (
    $estudiantes
    as $estudiante
) {

    if (
        strtoupper(
            $estudiante['estado']
            ?? ''
        )
        === 'ACTIVO'
    ) {

        $estudiantesActivos++;

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
    Asistencia QR | Estudiantes
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


/* =========================================================
   NAVEGACIÓN
========================================================= */

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


/* =========================================================
   RELOJ
========================================================= */

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
   CABECERA DEL CURSO
========================================================= */

.course-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    padding:
        25px 30px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,.84),
            rgba(236,250,248,.72)
        );

    box-shadow:
        0 20px 48px
        rgba(55,113,129,.07);

}


.course-header-content{

    min-width:0;

}


.back-link{

    display:inline-flex;

    align-items:center;

    gap:7px;

    margin-bottom:11px;

    color:#087d92;

    text-decoration:none;

    font-size:11px;

    font-weight:900;

}


.back-link:hover{

    color:#075273;

}


.course-tag{

    display:inline-flex;

    align-items:center;

    gap:7px;

    margin-bottom:9px;

    padding:
        7px 12px;

    border-radius:10px;

    color:#766cc8;

    background:
        rgba(133,121,210,.10);

    font-size:10px;

    font-weight:950;

    letter-spacing:.7px;

}


.course-header h2{

    color:#15576c;

    font-size:27px;

    font-weight:950;

}


.course-header p{

    margin-top:7px;

    color:#7898a2;

    font-size:13px;

    font-weight:650;

}


.course-stats{

    display:flex;

    gap:10px;

}


.stat-box{

    min-width:105px;

    padding:
        14px 16px;

    text-align:center;

    border-radius:16px;

    background:
        rgba(255,255,255,.72);

    border:
        1px solid
        rgba(255,255,255,.90);

}


.stat-box i{

    color:#7569c2;

    font-size:20px;

}


.stat-box strong{

    display:block;

    margin-top:4px;

    color:#315f70;

    font-size:24px;

    font-weight:950;

}


.stat-box span{

    display:block;

    margin-top:2px;

    color:#819da5;

    font-size:9px;

    font-weight:800;

}


/* =========================================================
   TABLA
========================================================= */

.students-card{

    overflow:hidden;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

}


.students-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        20px 24px;

    border-bottom:
        1px solid
        rgba(50,111,130,.08);

}


.students-title{

    display:flex;

    align-items:center;

    gap:11px;

}


.students-title-icon{

    width:42px;
    height:42px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:12px;

    color:#4b98a8;

    background:
        rgba(105,184,213,.10);

    font-size:19px;

}


.students-title h3{

    color:#315f70;

    font-size:17px;

    font-weight:950;

}


.students-title p{

    margin-top:3px;

    color:#8aa2a9;

    font-size:10px;

    font-weight:700;

}


.student-count{

    padding:
        7px 11px;

    border-radius:10px;

    color:#087d82;

    background:
        rgba(24,216,206,.09);

    font-size:10px;

    font-weight:950;

}


/* =========================================================
   TABLA
========================================================= */

.table-wrapper{

    width:100%;

    overflow-x:auto;

}


table{

    width:100%;

    border-collapse:collapse;

}


thead th{

    padding:
        13px 20px;

    text-align:left;

    color:#819da5;

    background:
        rgba(232,250,247,.42);

    font-size:10px;

    font-weight:950;

    letter-spacing:.6px;

    text-transform:uppercase;

}


tbody td{

    padding:
        15px 20px;

    color:#557f8b;

    border-top:
        1px solid
        rgba(50,111,130,.06);

    font-size:12px;

    font-weight:700;

}


tbody tr{

    transition:.2s;

}


tbody tr:hover{

    background:
        rgba(24,216,206,.035);

}


.student-name{

    display:flex;

    align-items:center;

    gap:11px;

}


.student-avatar{

    width:38px;
    height:38px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

    font-size:15px;

    font-weight:950;

}


.student-info strong{

    display:block;

    color:#416f7e;

    font-size:12px;

    font-weight:900;

}


.student-info small{

    display:block;

    margin-top:2px;

    color:#91a8ae;

    font-size:9px;

}


.documento{

    color:#557f8b;

    font-size:11px;

    font-weight:800;

}


.status{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        6px 9px;

    border-radius:9px;

    font-size:9px;

    font-weight:950;

}


.status.activo{

    color:#258e6d;

    background:
        rgba(66,205,161,.10);

}


.status.inactivo{

    color:#b86e77;

    background:
        rgba(242,143,150,.10);

}


.status i{

    font-size:7px;

}


/* =========================================================
   VACÍO
========================================================= */

.empty-card{

    padding:
        65px 25px;

    text-align:center;

}


.empty-icon{

    width:75px;
    height:75px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:
        0 auto 15px;

    border-radius:22px;

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

    font-size:32px;

}


.empty-card h3{

    color:#416f7e;

    font-size:19px;

    font-weight:950;

}


.empty-card p{

    max-width:450px;

    margin:
        7px auto 0;

    color:#8aa2a9;

    font-size:12px;

    font-weight:700;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .sidebar{

        width:255px;

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


    .course-header{

        align-items:flex-start;

        flex-direction:column;

    }


    .course-stats{

        width:100%;

    }


    .stat-box{

        flex:1;

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


    .students-header{

        align-items:flex-start;

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
                href="cursos.php"
                class="nav-link active"
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


        <!-- CONTROL -->

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

                <span>
                    Asistencia
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
                    DOCENTE
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


    <!-- TOPBAR -->

    <header class="topbar">


        <div class="page-info">


            <div class="page-indicator"></div>


            <div class="page-title">


                <h1>
                    Estudiantes
                </h1>


                <p>
                    Estudiantes pertenecientes al curso
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


    <!-- CABECERA CURSO -->

    <section class="course-header">


        <div class="course-header-content">


            <a
                href="cursos.php"
                class="back-link"
            >

                <i class="bi bi-arrow-left"></i>

                Volver a mis cursos

            </a>


            <div class="course-tag">

                <i class="bi bi-mortarboard-fill"></i>

                CURSO ACADÉMICO

            </div>


            <h2>

                <?= htmlspecialchars(
                    $curso['nombre_curso']
                ) ?>

            </h2>


            <p>

                Consulta los estudiantes
                asignados a este curso.

            </p>


        </div>


        <div class="course-stats">


            <div class="stat-box">

                <i class="bi bi-people-fill"></i>

                <strong>
                    <?= $totalEstudiantes ?>
                </strong>

                <span>
                    estudiantes
                </span>

            </div>


            <div class="stat-box">

                <i class="bi bi-person-check-fill"></i>

                <strong>
                    <?= $estudiantesActivos ?>
                </strong>

                <span>
                    activos
                </span>

            </div>


        </div>


    </section>


    <!-- LISTA DE ESTUDIANTES -->

    <section class="students-card">


        <div class="students-header">


            <div class="students-title">


                <div class="students-title-icon">

                    <i class="bi bi-people-fill"></i>

                </div>


                <div>

                    <h3>
                        Lista de estudiantes
                    </h3>

                    <p>
                        Estudiantes registrados en este curso
                    </p>

                </div>


            </div>


            <div class="student-count">

                <?= $totalEstudiantes ?>

                estudiante<?= (
                    $totalEstudiantes != 1
                    ? 's'
                    : ''
                ) ?>

            </div>


        </div>


        <?php if (
            $totalEstudiantes > 0
        ): ?>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Estudiante
                            </th>

                            <th>
                                Documento
                            </th>

                            <th>
                                Estado
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $estudiantes
                            as $estudiante
                        ): ?>


                            <?php

                            $nombreCompleto =
                                trim(
                                    (
                                        $estudiante['nombres']
                                        ?? ''
                                    )
                                    . ' '
                                    .
                                    (
                                        $estudiante['apellidos']
                                        ?? ''
                                    )
                                );


                            $nombrePartes =
                                preg_split(
                                    '/\s+/',
                                    $nombreCompleto
                                );


                            $inicialEstudiante =
                                strtoupper(
                                    substr(
                                        $nombrePartes[0]
                                        ?? 'E',
                                        0,
                                        1
                                    )
                                );


                            $estado =
                                strtoupper(
                                    $estudiante['estado']
                                    ?? 'ACTIVO'
                                );


                            ?>


                            <tr>


                                <td>


                                    <div class="student-name">


                                        <div class="student-avatar">

                                            <?= htmlspecialchars(
                                                $inicialEstudiante
                                            ) ?>

                                        </div>


                                        <div class="student-info">


                                            <strong>

                                                <?= htmlspecialchars(
                                                    $nombreCompleto
                                                ) ?>

                                            </strong>


                                            <small>

                                                ID:
                                                <?= (int)
                                                    $estudiante[
                                                        'id_estudiante'
                                                    ] ?>

                                            </small>


                                        </div>


                                    </div>


                                </td>


                                <td>


                                    <span class="documento">

                                        <?= htmlspecialchars(
                                            $estudiante[
                                                'documento'
                                            ]
                                        ) ?>

                                    </span>


                                </td>


                                <td>


                                    <?php if (
                                        $estado === 'ACTIVO'
                                    ): ?>


                                        <span
                                            class="status activo"
                                        >

                                            <i class="bi bi-circle-fill"></i>

                                            ACTIVO

                                        </span>


                                    <?php else: ?>


                                        <span
                                            class="status inactivo"
                                        >

                                            <i class="bi bi-circle-fill"></i>

                                            INACTIVO

                                        </span>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <div class="empty-card">


                <div class="empty-icon">

                    <i class="bi bi-people"></i>

                </div>


                <h3>

                    No hay estudiantes registrados

                </h3>


                <p>

                    Actualmente no existen estudiantes
                    asociados a este curso.

                </p>


            </div>


        <?php endif; ?>


    </section>


</main>


</div>


<script>

/* =========================================================
   RELOJ
========================================================= */

function actualizarReloj()
{

    const ahora =
        new Date();


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


    if (reloj) {

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

</script>


</body>

</html>