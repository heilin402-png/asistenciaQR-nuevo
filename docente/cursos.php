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


$partesNombre = preg_split(
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
   CURSOS DEL DOCENTE
========================================================= */

$cursos = [];

$sqlCursos = "

    SELECT
        c.id_curso,
        c.nombre_curso,
        c.estado

    FROM docente_curso dc

    INNER JOIN cursos c
        ON c.id_curso = dc.id_curso

    WHERE dc.id_usuario = ?

    AND c.estado = 'ACTIVO'

    ORDER BY c.nombre_curso ASC

";

$stmtCursos = mysqli_prepare(
    $conexion,
    $sqlCursos
);

if ($stmtCursos) {

    mysqli_stmt_bind_param(
        $stmtCursos,
        "i",
        $idDocente
    );

    mysqli_stmt_execute(
        $stmtCursos
    );

    $resultadoCursos =
        mysqli_stmt_get_result(
            $stmtCursos
        );

    while (
        $fila =
        mysqli_fetch_assoc(
            $resultadoCursos
        )
    ) {

        $cursos[] = $fila;

    }

    mysqli_stmt_close(
        $stmtCursos
    );

}

/* =========================================================
   CONTAR ESTUDIANTES POR CURSO
========================================================= */

$estudiantesPorCurso = [];


foreach (
    $cursos
    as $curso
) {

    $idCurso =
        (int) $curso['id_curso'];


    $sqlEstudiantes = "

        SELECT COUNT(*) AS total

        FROM estudiantes

        WHERE id_curso = ?

        AND estado = 'ACTIVO'

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


        $filaEstudiantes =
            mysqli_fetch_assoc(
                $resultadoEstudiantes
            );


        $estudiantesPorCurso[
            $idCurso
        ] =
            (int)(
                $filaEstudiantes['total']
                ?? 0
            );


        mysqli_stmt_close(
            $stmtEstudiantes
        );

    }

}


/* =========================================================
   TOTAL CURSOS
========================================================= */

$totalCursos =
    count($cursos);

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
    Asistencia QR | Cursos
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
   CABECERA
========================================================= */

.page-card{

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


.page-card-content{

    min-width:0;

}


.page-tag{

    display:inline-flex;

    align-items:center;

    gap:7px;

    margin-bottom:9px;

    padding:
        7px 12px;

    border-radius:10px;

    color:#087d82;

    background:
        rgba(24,216,206,.09);

    font-size:10px;

    font-weight:950;

    letter-spacing:.7px;

}


.page-card h2{

    color:#15576c;

    font-size:27px;

    font-weight:950;

}


.page-card p{

    margin-top:7px;

    color:#7898a2;

    font-size:13px;

    font-weight:650;

}


.total-courses{

    min-width:125px;

    padding:
        15px 18px;

    text-align:center;

    border-radius:17px;

    background:
        rgba(255,255,255,.72);

    border:
        1px solid
        rgba(255,255,255,.90);

}


.total-courses i{

    color:#7569c2;

    font-size:22px;

}


.total-courses strong{

    display:block;

    margin-top:4px;

    color:#315f70;

    font-size:27px;

    font-weight:950;

}


.total-courses span{

    display:block;

    margin-top:2px;

    color:#819da5;

    font-size:10px;

    font-weight:800;

}


/* =========================================================
   GRID DE CURSOS
========================================================= */

.courses-grid{

    display:grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                270px,
                1fr
            )
        );

    gap:17px;

}


/* =========================================================
   TARJETA DE CURSO
========================================================= */

.course-card{

    position:relative;

    overflow:hidden;

    min-height:210px;

    display:flex;

    flex-direction:column;

    padding:
        22px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:23px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

    text-decoration:none;

    transition:
        transform .25s,
        box-shadow .25s;

}


.course-card::before{

    content:"";

    position:absolute;

    width:130px;
    height:130px;

    right:-55px;
    top:-55px;

    border-radius:50%;

    background:
        rgba(24,216,206,.08);

}


.course-card::after{

    content:"";

    position:absolute;

    width:90px;
    height:90px;

    left:-45px;
    bottom:-45px;

    border-radius:50%;

    background:
        rgba(133,121,210,.055);

}


.course-card:hover{

    transform:
        translateY(-6px);

    box-shadow:
        0 25px 52px
        rgba(55,113,129,.11);

}


.course-top{

    position:relative;

    z-index:2;

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:15px;

}


.course-icon{

    width:58px;
    height:58px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:17px;

    color:#7569c2;

    background:
        rgba(133,121,210,.11);

    font-size:26px;

}


.course-status{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        6px 9px;

    border-radius:9px;

    color:#258e6d;

    background:
        rgba(66,205,161,.10);

    font-size:9px;

    font-weight:950;

}


.course-status i{

    font-size:8px;

}


.course-content{

    position:relative;

    z-index:2;

    flex:1;

    margin-top:18px;

}


.course-content h3{

    color:#315f70;

    font-size:19px;

    font-weight:950;

    line-height:1.25;

}


.course-content p{

    margin-top:6px;

    color:#8aa2a9;

    font-size:11px;

    font-weight:700;

}


.course-footer{

    position:relative;

    z-index:2;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    margin-top:20px;

    padding-top:14px;

    border-top:
        1px solid
        rgba(50,111,130,.08);

}


.course-students{

    display:flex;

    align-items:center;

    gap:7px;

    color:#668995;

    font-size:11px;

    font-weight:850;

}


.course-students i{

    color:#4b98a8;

    font-size:16px;

}


.course-enter{

    display:flex;

    align-items:center;

    gap:5px;

    color:#087d92;

    font-size:10px;

    font-weight:950;

}


.course-enter i{

    transition:.2s;

}


.course-card:hover
.course-enter i{

    transform:
        translateX(4px);

}


/* =========================================================
   SIN CURSOS
========================================================= */

.empty-card{

    padding:
        65px 25px;

    text-align:center;

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

}


@media(max-width:700px){

    .page-card{

        align-items:flex-start;

        flex-direction:column;

    }


    .total-courses{

        width:100%;

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
                    Mis cursos
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
                    Cursos
                </h1>


                <p>
                    Cursos asignados al docente
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


    <!-- CABECERA -->

    <section class="page-card">


        <div class="page-card-content">


            <div class="page-tag">

                <i class="bi bi-mortarboard-fill"></i>

                GESTIÓN ACADÉMICA

            </div>


            <h2>
                Mis cursos
            </h2>


            <p>

                Selecciona un curso para consultar
                los estudiantes que pertenecen a él.

            </p>


        </div>


        <div class="total-courses">


            <i class="bi bi-collection-fill"></i>


            <strong>
                <?= $totalCursos ?>
            </strong>


            <span>
                cursos activos
            </span>


        </div>


    </section>


    <!-- CURSOS -->

    <?php if (
        count($cursos) > 0
    ): ?>


        <section class="courses-grid">


            <?php foreach (
                $cursos
                as $indice => $curso
            ): ?>


                <?php

                $idCurso =
                    (int)
                    $curso['id_curso'];


                $cantidadEstudiantes =
                    $estudiantesPorCurso[
                        $idCurso
                    ]
                    ?? 0;


                ?>


                <a
                    href="
                        curso_estudiantes.php?id_curso=
                        <?= $idCurso ?>
                    "
                    class="course-card"
                >


                    <div class="course-top">


                        <div class="course-icon">

                            <i class="bi bi-mortarboard-fill"></i>

                        </div>


                        <div class="course-status">

                            <i class="bi bi-circle-fill"></i>

                            ACTIVO

                        </div>


                    </div>


                    <div class="course-content">


                        <h3>

                            <?= htmlspecialchars(
                                $curso[
                                    'nombre_curso'
                                ]
                            ) ?>

                        </h3>


                        <p>
                            Curso académico asignado
                        </p>


                    </div>


                    <div class="course-footer">


                        <div class="course-students">


                            <i class="bi bi-people-fill"></i>


                            <?= $cantidadEstudiantes ?>


                            estudiante

                            <?= (
                                $cantidadEstudiantes != 1
                                ? 's'
                                : ''
                            ) ?>


                        </div>


                        <div class="course-enter">

                            Ver estudiantes

                            <i class="bi bi-arrow-right"></i>

                        </div>


                    </div>


                </a>


            <?php endforeach; ?>


        </section>


    <?php else: ?>


        <section class="empty-card">


            <div class="empty-icon">

                <i class="bi bi-mortarboard"></i>

            </div>


            <h3>

                No tienes cursos asignados

            </h3>


            <p>

                Actualmente no hay cursos activos
                asociados a tu sesión de docente.

            </p>


        </section>


    <?php endif; ?>


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