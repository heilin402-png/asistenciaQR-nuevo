<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


/* =========================================================
   INFORMACIÓN DEL USUARIO
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

$horaActual = date('H:i:s');
$fechaActual = date('d/m/Y');
$fechaHoy = date('Y-m-d');

$idUsuarioSesion = (int)$_SESSION['id_usuario'];


/* =========================================================
   FUNCIÓN PARA OBTENER UN VALOR
========================================================= */

function obtenerValor($conexion, $sql)
{
    $resultado = mysqli_query(
        $conexion,
        $sql
    );

    if ($resultado) {

        $fila = mysqli_fetch_assoc(
            $resultado
        );

        if ($fila) {

            return (int)(
                array_values($fila)[0] ?? 0
            );
        }
    }

    return 0;
}


/* =========================================================
   ESTUDIANTES ACTIVOS
========================================================= */

$totalEstudiantes = obtenerValor(
    $conexion,
    "
        SELECT COUNT(*) AS total
        FROM estudiantes
        WHERE estado = 'ACTIVO'
    "
);


/* =========================================================
   CURSOS ACTIVOS
========================================================= */

$totalCursos = obtenerValor(
    $conexion,
    "
        SELECT COUNT(*) AS total
        FROM cursos
        WHERE estado = 'ACTIVO'
    "
);


/* =========================================================
   DOCENTES
========================================================= */

$totalDocentes = obtenerValor(
    $conexion,
    "
        SELECT COUNT(DISTINCT id_usuario) AS total
        FROM docente_curso
    "
);


/* =========================================================
   USUARIOS
========================================================= */

$totalUsuarios = obtenerValor(
    $conexion,
    "
        SELECT COUNT(*) AS total
        FROM usuarios
    "
);


/* =========================================================
   ASISTENCIAS DE HOY
========================================================= */

$totalAsistenciasHoy = obtenerValor(
    $conexion,
    "
        SELECT COUNT(*) AS total
        FROM asistencia_clase ac
        INNER JOIN sesiones_clase sc
            ON ac.id_sesion = sc.id_sesion
        WHERE sc.fecha = '$fechaHoy'
    "
);


/* =========================================================
   SESIONES DE HOY
========================================================= */

$totalSesionesHoy = obtenerValor(
    $conexion,
    "
        SELECT COUNT(*) AS total
        FROM sesiones_clase
        WHERE fecha = '$fechaHoy'
    "
);


/* =========================================================
   ACTIVIDAD RECIENTE
========================================================= */

$actividadReciente = [];

$sqlActividad = "
    SELECT
        sc.id_sesion,
        sc.fecha,
        sc.hora_inicio,
        c.nombre_curso
    FROM sesiones_clase sc
    INNER JOIN cursos c
        ON sc.id_curso = c.id_curso
    ORDER BY
        sc.fecha DESC,
        sc.hora_inicio DESC
    LIMIT 6
";

$resultadoActividad = mysqli_query(
    $conexion,
    $sqlActividad
);

if ($resultadoActividad) {

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoActividad
        )
    ) {

        $actividadReciente[] = $fila;
    }
}


/* =========================================================
   PORCENTAJE DE ASISTENCIA
========================================================= */

$porcentajeAsistencia = 0;

if (
    $totalEstudiantes > 0 &&
    $totalAsistenciasHoy > 0
) {

    $porcentajeAsistencia =
        round(
            (
                $totalAsistenciasHoy /
                $totalEstudiantes
            ) * 100
        );

    if ($porcentajeAsistencia > 100) {
        $porcentajeAsistencia = 100;
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
Asistencia QR | Inicio
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
   GENERAL
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


a{
    text-decoration:none;
    color:inherit;
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
        22px
        16px
        16px;

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

    backdrop-filter:
        blur(25px);

    box-shadow:

        0 25px 65px
        rgba(55,113,129,.10);
}


.sidebar-header{

    display:flex;

    align-items:center;

    gap:13px;

    padding:
        3px
        9px
        20px;
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
        0
        9px
        16px;

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
        0
        11px;

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
        6px
        10px;

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
        0
        7px
        7px
        0;

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
        10px
        11px;

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
        0
        10px;

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
        14px
        22px;

    border:
        1px solid
        rgba(255,255,255,.92);

    border-radius:23px;

    background:
        rgba(255,255,255,.68);

    backdrop-filter:
        blur(20px);

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
        10px
        15px;

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
   BIENVENIDA
========================================================= */

.welcome{

    position:relative;

    min-height:230px;

    overflow:hidden;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:25px;

    padding:
        30px
        32px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:27px;

    background:

        linear-gradient(
            120deg,
            rgba(217,249,246,.88),
            rgba(246,252,253,.92)
        );

    box-shadow:

        0 20px 48px
        rgba(55,113,129,.07);
}


.welcome::before{

    content:"";

    position:absolute;

    width:270px;

    height:270px;

    right:-90px;

    top:-145px;

    border-radius:50%;

    background:
        rgba(24,216,206,.08);
}


.welcome-text{

    position:relative;

    z-index:2;

    max-width:58%;
}


.welcome-tag{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:
        7px
        11px;

    border-radius:10px;

    color:#087d92;

    background:
        rgba(255,255,255,.68);

    font-size:10px;

    font-weight:950;

    letter-spacing:.8px;
}


.welcome h2{

    margin-top:13px;

    color:#315f70;

    font-size:29px;

    font-weight:950;

    letter-spacing:-.6px;
}


.welcome h2 span{

    color:#10aaa9;
}


.welcome p{

    max-width:570px;

    margin-top:9px;

    color:#7898a2;

    font-size:13px;

    line-height:1.7;

    font-weight:650;
}


/* =========================================================
   ILUSTRACIÓN ADMINISTRATIVA
========================================================= */

.admin-illustration{

    position:relative;

    width:350px;

    height:185px;

    flex-shrink:0;

    animation:
        floatIllustration
        5s
        ease-in-out
        infinite;
}


.admin-panel{

    position:absolute;

    left:70px;

    top:25px;

    width:215px;

    height:135px;

    padding:11px;

    border-radius:19px;

    background:
        rgba(255,255,255,.94);

    box-shadow:

        0 18px 35px
        rgba(55,113,129,.13);

    animation:
        panelMove
        4s
        ease-in-out
        infinite;
}


.admin-panel-top{

    display:flex;

    align-items:center;

    gap:5px;
}


.admin-panel-top span{

    width:6px;

    height:6px;

    border-radius:50%;

    background:#d2e9eb;
}


.admin-panel-top span:first-child{

    background:#35c8c4;
}


.admin-panel-content{

    display:flex;

    gap:10px;

    margin-top:10px;

    padding:10px;

    border-radius:12px;

    background:#f0fafb;
}


.admin-person{

    width:38px;

    height:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    color:white;

    background:

        linear-gradient(
            135deg,
            #22cfc4,
            #7d72d0
        );

    font-size:17px;
}


.admin-lines{

    flex:1;

    padding-top:3px;
}


.admin-lines span{

    display:block;

    height:6px;

    margin-bottom:6px;

    border-radius:10px;

    background:#d5edef;
}


.admin-lines span:nth-child(1){

    width:90%;

    background:#bfe7e8;
}


.admin-lines span:nth-child(2){

    width:70%;
}


.admin-lines span:nth-child(3){

    width:48%;

    margin-bottom:0;
}


.admin-bars{

    display:flex;

    align-items:flex-end;

    gap:9px;

    height:39px;

    padding:
        6px
        10px
        0;
}


.admin-bars span{

    width:28px;

    border-radius:
        6px
        6px
        2px
        2px;

    background:

        linear-gradient(
            180deg,
            #59cecc,
            #9aabe1
        );
}


.admin-bars span:nth-child(1){

    height:16px;
}


.admin-bars span:nth-child(2){

    height:29px;
}


.admin-bars span:nth-child(3){

    height:22px;
}


.floating-admin-icon{

    position:absolute;

    width:47px;

    height:47px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:14px;

    background:
        rgba(255,255,255,.96);

    box-shadow:

        0 12px 28px
        rgba(55,113,129,.12);

    font-size:19px;
}


.icon-people{

    left:15px;

    top:42px;

    color:#0ba5aa;

    animation:
        iconMove
        4s
        ease-in-out
        infinite;
}


.icon-check{

    right:14px;

    top:12px;

    color:#7569c2;

    animation:
        iconMove
        4.5s
        ease-in-out
        infinite
        .4s;
}


.icon-book{

    right:35px;

    bottom:0;

    color:#36a47f;

    animation:
        iconMove
        4.2s
        ease-in-out
        infinite
        .8s;
}


@keyframes floatIllustration{

    0%,
    100%{
        transform:translateY(0);
    }

    50%{
        transform:translateY(-6px);
    }
}


@keyframes panelMove{

    0%,
    100%{
        transform:translateY(0) rotate(0deg);
    }

    50%{
        transform:translateY(-4px) rotate(-1deg);
    }
}


@keyframes iconMove{

    0%,
    100%{
        transform:translateY(0);
    }

    50%{
        transform:
            translateY(-8px)
            rotate(2deg);
    }
}


/* =========================================================
   RESUMEN
========================================================= */

.section-title{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin:
        0
        4px;

}


.section-title h2{

    color:#315f70;

    font-size:17px;

    font-weight:950;
}


.section-title span{

    color:#8aa3aa;

    font-size:10px;

    font-weight:750;
}


.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:14px;
}


.summary-card{

    position:relative;

    min-height:135px;

    padding:
        17px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:21px;

    background:
        rgba(255,255,255,.75);

    box-shadow:

        0 15px 38px
        rgba(55,113,129,.065);

    transition:.25s;
}


.summary-card:hover{

    transform:
        translateY(-4px);

    box-shadow:

        0 20px 42px
        rgba(55,113,129,.10);
}


.summary-top{

    display:flex;

    align-items:center;

    justify-content:space-between;
}


.summary-icon{

    width:46px;

    height:46px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:14px;

    font-size:20px;
}


.summary-card.students
.summary-icon{

    color:#0a9da5;

    background:
        rgba(24,216,206,.10);
}


.summary-card.teachers
.summary-icon{

    color:#4b93aa;

    background:
        rgba(105,184,213,.11);
}


.summary-card.courses
.summary-icon{

    color:#7569c2;

    background:
        rgba(133,121,210,.11);
}


.summary-card.users
.summary-icon{

    color:#329c79;

    background:
        rgba(66,205,161,.11);
}


.summary-card.attendance
.summary-icon{

    color:#bd8a40;

    background:
        rgba(209,161,88,.12);
}


.summary-card.sessions
.summary-icon{

    color:#b86e77;

    background:
        rgba(242,143,150,.10);
}


.summary-arrow{

    color:#9bb4bb;

    font-size:14px;
}


.summary-number{

    margin-top:12px;

    color:#315f70;

    font-size:25px;

    line-height:1;

    font-weight:950;
}


.summary-label{

    margin-top:6px;

    color:#809ba3;

    font-size:11px;

    font-weight:750;
}


/* =========================================================
   PARTE INFERIOR
========================================================= */

.bottom-grid{

    display:grid;

    grid-template-columns:
        1fr
        1fr;

    gap:16px;
}


.content-card{

    min-height:220px;

    padding:
        22px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:24px;

    background:
        rgba(255,255,255,.74);

    box-shadow:

        0 20px 48px
        rgba(55,113,129,.07);
}


.content-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:18px;
}


.content-header h3{

    color:#315f70;

    font-size:16px;

    font-weight:950;
}


.content-header p{

    margin-top:4px;

    color:#8aa3aa;

    font-size:10px;

    font-weight:650;
}


.content-icon{

    width:38px;

    height:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    color:#0b9f9c;

    background:
        rgba(24,216,206,.10);

    font-size:17px;
}


/* =========================================================
   RESUMEN DE HOY
========================================================= */

.today-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:10px;
}


.today-item{

    padding:
        15px
        9px;

    text-align:center;

    border-radius:16px;

    background:
        rgba(248,253,252,.86);

    border:
        1px solid
        rgba(225,241,243,.80);
}


.today-icon{

    width:38px;

    height:38px;

    margin:
        0
        auto
        8px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    color:#0b9f9c;

    background:
        rgba(24,216,206,.10);

    font-size:17px;
}


.today-item:nth-child(2)
.today-icon{

    color:#7569c2;

    background:
        rgba(133,121,210,.10);
}


.today-item:nth-child(3)
.today-icon{

    color:#329c79;

    background:
        rgba(66,205,161,.11);
}


.today-number{

    color:#315f70;

    font-size:22px;

    font-weight:950;
}


.today-label{

    margin-top:4px;

    color:#8aa3aa;

    font-size:9px;

    font-weight:750;
}


/* =========================================================
   ACTIVIDAD
========================================================= */

.activity-list{

    display:flex;

    flex-direction:column;

    gap:8px;
}


.activity-item{

    min-height:49px;

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        7px
        9px;

    border-radius:13px;

    background:
        rgba(248,253,252,.84);

    border:
        1px solid
        rgba(225,241,243,.80);
}


.activity-icon{

    width:35px;

    height:35px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:10px;

    color:#0b9f9c;

    background:
        rgba(24,216,206,.10);

    font-size:15px;
}


.activity-info{

    flex:1;

    min-width:0;
}


.activity-info strong{

    display:block;

    overflow:hidden;

    color:#4d7785;

    font-size:10px;

    font-weight:900;

    white-space:nowrap;

    text-overflow:ellipsis;
}


.activity-info span{

    display:block;

    margin-top:3px;

    color:#91a7ae;

    font-size:9px;
}


.activity-time{

    color:#849fa7;

    font-size:9px;

    font-weight:800;

    white-space:nowrap;
}


.empty-activity{

    padding:
        25px
        10px;

    text-align:center;

    color:#8aa3aa;

    font-size:10px;
}


.empty-activity i{

    display:block;

    margin-bottom:7px;

    color:#9dc9cd;

    font-size:27px;
}


/* =========================================================
   ACCESOS RÁPIDOS
========================================================= */

.quick-actions{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:10px;
}


.quick-link{

    display:flex;

    align-items:center;

    gap:9px;

    padding:
        11px;

    border-radius:14px;

    color:#527b87;

    background:
        rgba(255,255,255,.60);

    border:
        1px solid
        rgba(255,255,255,.90);

    font-size:10px;

    font-weight:850;

    transition:.2s;
}


.quick-link:hover{

    transform:
        translateY(-2px);

    background:
        rgba(255,255,255,.90);
}


.quick-link i{

    width:32px;

    height:32px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    color:#0b9f9c;

    background:
        rgba(24,216,206,.10);

    font-size:15px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px){

    .summary-grid{

        grid-template-columns:
            repeat(2,1fr);
    }

    .bottom-grid{

        grid-template-columns:
            1fr;
    }

}


@media(max-width:1050px){

    .sidebar{

        width:250px;
    }

    .welcome-text{

        max-width:53%;
    }

    .admin-illustration{

        transform:
            scale(.85);
    }

}


@media(max-width:850px){

    .app{

        flex-direction:column;
    }

    .sidebar{

        width:100%;

        min-height:auto;
    }

    .navigation{

        display:grid;

        grid-template-columns:
            repeat(2,1fr);

        gap:5px;
    }

    .sidebar-bottom{

        display:none;
    }

}


@media(max-width:700px){

    .app{

        padding:10px;
    }

    .topbar{

        align-items:flex-start;

        flex-direction:column;
    }

    .clock-box{

        width:100%;
    }

    .welcome{

        flex-direction:column;

        align-items:flex-start;

        padding:22px;
    }

    .welcome-text{

        max-width:100%;
    }

    .admin-illustration{

        width:100%;

        transform:
            scale(.78);

        transform-origin:
            left center;
    }

    .summary-grid{

        grid-template-columns:1fr;
    }

    .today-grid{

        grid-template-columns:1fr;
    }

    .quick-actions{

        grid-template-columns:
            repeat(2,1fr);
    }

}


@media(max-width:480px){

    .navigation{

        grid-template-columns:1fr;
    }

    .quick-actions{

        grid-template-columns:1fr;
    }

    .page-title h1{

        font-size:23px;
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
    class="nav-link active"
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


<!-- CERRAR SESIÓN -->

<a
    href="../auth/logout.php"
    class="logout"
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
     CONTENIDO PRINCIPAL
===================================================== -->

<main class="main">


<!-- =====================================================
     TOPBAR
===================================================== -->

<header class="topbar">


<div class="page-info">


<div class="page-indicator">
</div>


<div class="page-title">

<h1>
Inicio
</h1>

<p>
Panel general de administración
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



<!-- =====================================================
     BIENVENIDA
===================================================== -->

<section class="welcome">


<div class="welcome-text">


<div class="welcome-tag">

<i class="bi bi-shield-check"></i>

ADMINISTRACIÓN

</div>


<h2>

¡Hola,

<span>
<?= htmlspecialchars($primerNombre) ?>
</span>!

</h2>


<p>

Bienvenido al panel administrativo de
Asistencia QR. Desde aquí puedes consultar
la información general del sistema,
administrar cursos, usuarios y docentes,
y revisar la actividad de asistencia.

</p>


</div>


<!-- =====================================================
     ILUSTRACIÓN
===================================================== -->

<div class="admin-illustration">


<div class="admin-panel">


<div class="admin-panel-top">

<span></span>
<span></span>
<span></span>

</div>


<div class="admin-panel-content">


<div class="admin-person">

<i class="bi bi-person-fill"></i>

</div>


<div class="admin-lines">

<span></span>
<span></span>
<span></span>

</div>


</div>


<div class="admin-bars">

<span></span>
<span></span>
<span></span>

</div>


</div>


<div class="floating-admin-icon icon-people">

<i class="bi bi-people-fill"></i>

</div>


<div class="floating-admin-icon icon-check">

<i class="bi bi-check-circle-fill"></i>

</div>


<div class="floating-admin-icon icon-book">

<i class="bi bi-book-half"></i>

</div>


</div>


</section>



<!-- =====================================================
     TÍTULO RESUMEN
===================================================== -->

<div class="section-title">

<h2>
Resumen general
</h2>

<span>
Información actual del sistema
</span>

</div>



<!-- =====================================================
     TARJETAS
===================================================== -->

<section class="summary-grid">


<!-- ESTUDIANTES -->

<a
    href="curso_estudiantes.php"
    class="summary-card students"
>


<div class="summary-top">


<div class="summary-icon">

<i class="bi bi-mortarboard-fill"></i>

</div>


<i
    class="bi bi-arrow-up-right summary-arrow"
></i>


</div>


<div class="summary-number">

<?= number_format(
    $totalEstudiantes
) ?>

</div>


<div class="summary-label">

Estudiantes activos

</div>


</a>



<!-- DOCENTES -->

<a
    href="docentes.php"
    class="summary-card teachers"
>


<div class="summary-top">


<div class="summary-icon">

<i class="bi bi-person-workspace"></i>

</div>


<i
    class="bi bi-arrow-up-right summary-arrow"
></i>


</div>


<div class="summary-number">

<?= number_format(
    $totalDocentes
) ?>

</div>


<div class="summary-label">

Docentes

</div>


</a>



<!-- CURSOS -->

<a
    href="curso_estudiantes.php"
    class="summary-card courses"
>


<div class="summary-top">


<div class="summary-icon">

<i class="bi bi-mortarboard"></i>

</div>


<i
    class="bi bi-arrow-up-right summary-arrow"
></i>


</div>


<div class="summary-number">

<?= number_format(
    $totalCursos
) ?>

</div>


<div class="summary-label">

Cursos activos

</div>


</a>



<!-- USUARIOS -->

<a
    href="usuarios.php"
    class="summary-card users"
>


<div class="summary-top">


<div class="summary-icon">

<i class="bi bi-person-badge-fill"></i>

</div>


<i
    class="bi bi-arrow-up-right summary-arrow"
></i>


</div>


<div class="summary-number">

<?= number_format(
    $totalUsuarios
) ?>

</div>


<div class="summary-label">

Usuarios registrados

</div>


</a>



<!-- ASISTENCIAS -->

<a
    href="asistencia.php"
    class="summary-card attendance"
>


<div class="summary-top">


<div class="summary-icon">

<i class="bi bi-clipboard2-check-fill"></i>

</div>


<i
    class="bi bi-arrow-up-right summary-arrow"
></i>


</div>


<div class="summary-number">

<?= number_format(
    $totalAsistenciasHoy
) ?>

</div>


<div class="summary-label">

Asistencias hoy

</div>


</a>



<!-- SESIONES -->

<a
    href="asistencia.php"
    class="summary-card sessions"
>


<div class="summary-top">


<div class="summary-icon">

<i class="bi bi-calendar-check-fill"></i>

</div>


<i
    class="bi bi-arrow-up-right summary-arrow"
></i>


</div>


<div class="summary-number">

<?= number_format(
    $totalSesionesHoy
) ?>

</div>


<div class="summary-label">

Sesiones hoy

</div>


</a>


</section>



<!-- =====================================================
     RESUMEN Y ACTIVIDAD
===================================================== -->

<section class="bottom-grid">


<!-- =====================================================
     RESUMEN DE HOY
===================================================== -->

<div class="content-card">


<div class="content-header">


<div>

<h3>
Resumen de hoy
</h3>

<p>
Actividad registrada durante la jornada
</p>

</div>


<div class="content-icon">

<i class="bi bi-calendar2-day"></i>

</div>


</div>


<div class="today-grid">


<div class="today-item">


<div class="today-icon">

<i class="bi bi-check2-square"></i>

</div>


<div class="today-number">

<?= number_format(
    $totalAsistenciasHoy
) ?>

</div>


<div class="today-label">

Asistencias registradas

</div>


</div>



<div class="today-item">


<div class="today-icon">

<i class="bi bi-calendar-event"></i>

</div>


<div class="today-number">

<?= number_format(
    $totalSesionesHoy
) ?>

</div>


<div class="today-label">

Sesiones de clase

</div>


</div>



<div class="today-item">


<div class="today-icon">

<i class="bi bi-person-check-fill"></i>

</div>


<div class="today-number">

<?= number_format(
    $totalEstudiantes
) ?>

</div>


<div class="today-label">

Estudiantes activos

</div>


</div>


</div>


</div>



<!-- =====================================================
     ACTIVIDAD RECIENTE
===================================================== -->

<div class="content-card">


<div class="content-header">


<div>

<h3>
Actividad reciente
</h3>

<p>
Últimas sesiones registradas
</p>

</div>


<div class="content-icon">

<i class="bi bi-clock-history"></i>

</div>


</div>


<div class="activity-list">


<?php if (
    count($actividadReciente) > 0
): ?>


<?php foreach (
    $actividadReciente
    as $actividad
): ?>


<div class="activity-item">


<div class="activity-icon">

<i class="bi bi-play-circle-fill"></i>

</div>


<div class="activity-info">


<strong>

<?= htmlspecialchars(
    $actividad['nombre_curso']
) ?>

</strong>


<span>

Sesión del

<?= date(
    'd/m/Y',
    strtotime(
        $actividad['fecha']
    )
) ?>

</span>


</div>


<div class="activity-time">

<?= htmlspecialchars(
    substr(
        $actividad['hora_inicio'] ?? '',
        0,
        5
    )
) ?>

</div>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty-activity">

<i class="bi bi-calendar-x"></i>

No hay sesiones registradas
recientemente.

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


    if(reloj){

        reloj.textContent =
            horas +
            ':' +
            minutos +
            ':' +
            segundos;
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