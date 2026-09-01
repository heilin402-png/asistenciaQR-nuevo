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

$idDocente = (int) $_SESSION['id_usuario'];

$nombreUsuario = $_SESSION['nombre'] ?? 'Docente';

$partesNombre = preg_split(
    '/\s+/',
    trim($nombreUsuario)
);

$iniciales = '';

foreach (array_slice($partesNombre, 0, 2) as $parte) {

    $iniciales .= strtoupper(
        substr($parte, 0, 1)
    );

}

if ($iniciales === '') {
    $iniciales = 'DO';
}


/* =========================================================
   FECHA Y HORA
========================================================= */

$horaActual = date('H:i:s');
$fechaActual = date('d/m/Y');


/* =========================================================
   FILTRO DE CURSO
========================================================= */

$idCursoFiltro = 0;

if (isset($_GET['id_curso'])) {

    $idCursoFiltro = (int) $_GET['id_curso'];

}


/* =========================================================
   CURSOS DEL DOCENTE
========================================================= */

$cursos = [];

$sqlCursos = "

    SELECT DISTINCT

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
   VALIDAR FILTRO
========================================================= */

$cursoValido = false;

if ($idCursoFiltro > 0) {

    foreach ($cursos as $curso) {

        if (
            (int)$curso['id_curso']
            === $idCursoFiltro
        ) {

            $cursoValido = true;
            break;

        }

    }

}

if (
    $idCursoFiltro > 0
    && !$cursoValido
) {

    $idCursoFiltro = 0;

}


/* =========================================================
   CONDICIÓN DE CURSO
========================================================= */

$whereCurso = '';

if ($idCursoFiltro > 0) {

    $whereCurso = "
        AND s.id_curso = ?
    ";

}


/* =========================================================
   REGISTROS DE ASISTENCIA
========================================================= */

$registros = [];

if ($idCursoFiltro > 0) {

    $sqlRegistros = "

        SELECT

            a.id_asistencia,
            a.id_sesion,
            a.id_estudiante,
            a.estado,
            a.estado_excusa,
            a.hora_registro,

            e.documento,
            e.nombres,
            e.apellidos,

            c.id_curso,
            c.nombre_curso,

            s.id_docente,
            s.fecha,
            s.hora_inicio

        FROM asistencia_clase a

        INNER JOIN estudiantes e
            ON e.id_estudiante = a.id_estudiante

        INNER JOIN sesiones_clase s
            ON s.id_sesion = a.id_sesion

        INNER JOIN cursos c
            ON c.id_curso = s.id_curso

        INNER JOIN docente_curso dc
            ON dc.id_curso = s.id_curso
            AND dc.id_usuario = s.id_docente

        WHERE s.id_docente = ?

        AND s.id_curso = ?

        ORDER BY
            s.fecha DESC,
            s.hora_inicio DESC,
            e.apellidos ASC,
            e.nombres ASC

    ";


    $stmtRegistros = mysqli_prepare(
        $conexion,
        $sqlRegistros
    );


    if ($stmtRegistros) {

        mysqli_stmt_bind_param(
            $stmtRegistros,
            "ii",
            $idDocente,
            $idCursoFiltro
        );

        mysqli_stmt_execute(
            $stmtRegistros
        );

        $resultadoRegistros =
            mysqli_stmt_get_result(
                $stmtRegistros
            );

        while (
            $fila =
            mysqli_fetch_assoc(
                $resultadoRegistros
            )
        ) {

            $registros[] = $fila;

        }

        mysqli_stmt_close(
            $stmtRegistros
        );

    }

} else {

    $sqlRegistros = "

        SELECT

            a.id_asistencia,
            a.id_sesion,
            a.id_estudiante,
            a.estado,
            a.estado_excusa,
            a.hora_registro,

            e.documento,
            e.nombres,
            e.apellidos,

            c.id_curso,
            c.nombre_curso,

            s.id_docente,
            s.fecha,
            s.hora_inicio

        FROM asistencia_clase a

        INNER JOIN estudiantes e
            ON e.id_estudiante = a.id_estudiante

        INNER JOIN sesiones_clase s
            ON s.id_sesion = a.id_sesion

        INNER JOIN cursos c
            ON c.id_curso = s.id_curso

        INNER JOIN docente_curso dc
            ON dc.id_curso = s.id_curso
            AND dc.id_usuario = s.id_docente

        WHERE s.id_docente = ?

        ORDER BY
            s.fecha DESC,
            s.hora_inicio DESC,
            e.apellidos ASC,
            e.nombres ASC

    ";


    $stmtRegistros = mysqli_prepare(
        $conexion,
        $sqlRegistros
    );


    if ($stmtRegistros) {

        mysqli_stmt_bind_param(
            $stmtRegistros,
            "i",
            $idDocente
        );

        mysqli_stmt_execute(
            $stmtRegistros
        );

        $resultadoRegistros =
            mysqli_stmt_get_result(
                $stmtRegistros
            );

        while (
            $fila =
            mysqli_fetch_assoc(
                $resultadoRegistros
            )
        ) {

            $registros[] = $fila;

        }

        mysqli_stmt_close(
            $stmtRegistros
        );

    }

}


/* =========================================================
   CONTADORES
========================================================= */

$totalRegistros = count($registros);

$totalPresentes = 0;
$totalAusentes = 0;
$totalTardes = 0;
$totalExcusas = 0;
$totalEvadidos = 0;


foreach ($registros as $registro) {

    $estado =
        strtoupper(
            trim(
                $registro['estado'] ?? ''
            )
        );


    switch ($estado) {

        case 'PRESENTE':

            $totalPresentes++;

            break;


        case 'AUSENTE':

            $totalAusentes++;

            break;


        case 'TARDE':

            $totalTardes++;

            break;


        case 'EVADIO':

            $totalEvadidos++;

            break;

    }


    $excusa =
        trim(
            $registro['estado_excusa'] ?? ''
        );


    if ($excusa !== '') {

        $totalExcusas++;

    }

}


/* =========================================================
   CURSO SELECCIONADO
========================================================= */

$nombreCursoSeleccionado = 'Todos los cursos';

if ($idCursoFiltro > 0) {

    foreach ($cursos as $curso) {

        if (
            (int)$curso['id_curso']
            === $idCursoFiltro
        ) {

            $nombreCursoSeleccionado =
                $curso['nombre_curso'];

            break;

        }

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
    Asistencia QR | Reportes
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


/* =========================================================
   FILTRO
========================================================= */

.filter-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    padding:
        17px 22px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:20px;

    background:
        rgba(255,255,255,.75);

    box-shadow:
        0 15px 35px
        rgba(55,113,129,.055);

}


.filter-title{

    display:flex;

    align-items:center;

    gap:10px;

    color:#416f7e;

    font-size:13px;

    font-weight:900;

}


.filter-title i{

    width:36px;
    height:36px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:10px;

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

}


.course-filter{

    min-width:250px;

    padding:
        11px 14px;

    border:
        1px solid
        rgba(111,164,174,.18);

    border-radius:12px;

    outline:none;

    color:#416f7e;

    background:
        rgba(255,255,255,.85);

    font-family:inherit;

    font-size:13px;

    font-weight:750;

    cursor:pointer;

}


/* =========================================================
   CONTADORES
========================================================= */

.stats-grid{

    display:grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:15px;

}


.stat-card{

    position:relative;

    overflow:hidden;

    min-height:120px;

    padding:
        18px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:20px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 15px 35px
        rgba(55,113,129,.055);

}


.stat-card::after{

    content:"";

    position:absolute;

    width:90px;
    height:90px;

    right:-40px;
    bottom:-40px;

    border-radius:50%;

    background:
        rgba(24,216,206,.07);

}


.stat-icon{

    width:40px;
    height:40px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:11px;

    font-size:18px;

}


.stat-card.registros
.stat-icon{

    color:#087d92;

    background:
        rgba(24,216,206,.10);

}


.stat-card.excuses
.stat-icon{

    color:#bd8a40;

    background:
        rgba(209,161,88,.12);

}


.stat-card.ausentes
.stat-icon{

    color:#bd6973;

    background:
        rgba(242,143,150,.11);

}


.stat-card.tardes
.stat-icon{

    color:#8579d2;

    background:
        rgba(133,121,210,.11);

}


.stat-number{

    display:block;

    margin-top:10px;

    color:#315f70;

    font-size:25px;

    font-weight:950;

}


.stat-label{

    display:block;

    margin-top:2px;

    color:#819da5;

    font-size:10px;

    font-weight:850;

    text-transform:uppercase;

    letter-spacing:.5px;

}


/* =========================================================
   TABLA
========================================================= */

.table-card{

    overflow:hidden;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:23px;

    background:
        rgba(255,255,255,.78);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

}


.table-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:
        20px 23px;

    border-bottom:
        1px solid
        rgba(50,111,130,.08);

}


.table-header h3{

    color:#315f70;

    font-size:17px;

    font-weight:950;

}


.table-header p{

    margin-top:4px;

    color:#8aa2a9;

    font-size:11px;

    font-weight:700;

}


.table-wrapper{

    width:100%;

    overflow-x:auto;

}


table{

    width:100%;

    min-width:900px;

    border-collapse:collapse;

}


thead{

    background:
        rgba(232,250,247,.55);

}


th{

    padding:
        13px 16px;

    text-align:left;

    color:#668995;

    font-size:10px;

    font-weight:950;

    letter-spacing:.5px;

    text-transform:uppercase;

}


td{

    padding:
        14px 16px;

    border-top:
        1px solid
        rgba(50,111,130,.06);

    color:#557f8b;

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

    color:#315f70;

    font-weight:900;

}


.student-document{

    margin-top:3px;

    color:#93a9af;

    font-size:10px;

    font-weight:650;

}


.course-name{

    color:#557f8b;

    font-weight:800;

}


.date-cell{

    white-space:nowrap;

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


.status i{

    font-size:7px;

}


.status-presente{

    color:#258e6d;

    background:
        rgba(66,205,161,.11);

}


.status-ausente{

    color:#b85e69;

    background:
        rgba(242,143,150,.11);

}


.status-tarde{

    color:#7569c2;

    background:
        rgba(133,121,210,.11);

}


.status-evadio{

    color:#65747a;

    background:
        rgba(120,140,145,.12);

}


.excuse{

    display:inline-flex;

    align-items:center;

    gap:6px;

    max-width:180px;

    padding:
        6px 9px;

    overflow:hidden;

    border-radius:9px;

    color:#a87628;

    background:
        rgba(245,190,70,.12);

    font-size:9px;

    font-weight:900;

    white-space:nowrap;

    text-overflow:ellipsis;

}


.no-excuse{

    color:#a1b1b5;

    font-size:11px;

}


/* =========================================================
   VACÍO
========================================================= */

.empty-card{

    padding:
        60px 25px;

    text-align:center;

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


.empty-icon{

    width:70px;
    height:70px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:
        0 auto 15px;

    border-radius:20px;

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

    font-size:30px;

}


.empty-card h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.empty-card p{

    max-width:500px;

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

    .stats-grid{

        grid-template-columns:
            repeat(2, 1fr);

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


    .filter-card{

        align-items:flex-start;

        flex-direction:column;

    }


    .course-filter{

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


    .stats-grid{

        grid-template-columns:1fr;

    }


    .page-card{

        padding:20px;

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
                class="nav-link active"
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
                    Reportes
                </h1>

                <p>
                    Consulta los registros de asistencia de tus cursos
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


        <div>

            <div class="page-tag">

                <i class="bi bi-bar-chart-line-fill"></i>

                CONTROL DE ASISTENCIA

            </div>


            <h2>
                Reportes de asistencia
            </h2>


            <p>

                Consulta y revisa los registros de asistencia
                correspondientes a tus cursos.

            </p>

        </div>


    </section>


    <!-- FILTRO -->

    <section class="filter-card">


        <div class="filter-title">

            <i class="bi bi-funnel-fill"></i>

            <span>
                Filtrar por curso
            </span>

        </div>


        <form
            method="GET"
            action="reportes.php"
        >

            <select
                name="id_curso"
                class="course-filter"
                onchange="this.form.submit()"
            >

                <option value="0">

                    Todos los cursos

                </option>


                <?php foreach (
                    $cursos
                    as $curso
                ): ?>


                    <option
                        value="<?= (int)$curso['id_curso'] ?>"

                        <?= (
                            $idCursoFiltro
                            ==
                            (int)$curso['id_curso']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $curso['nombre_curso']
                        ) ?>

                    </option>


                <?php endforeach; ?>


            </select>

        </form>


    </section>


    <!-- CONTADORES -->

    <section class="stats-grid">


        <div class="stat-card registros">


            <div class="stat-icon">

                <i class="bi bi-clipboard-check"></i>

            </div>


            <strong class="stat-number">

                <?= $totalRegistros ?>

            </strong>


            <span class="stat-label">
                Registros
            </span>


        </div>


        <div class="stat-card excuses">


            <div class="stat-icon">

                <i class="bi bi-file-earmark-text"></i>

            </div>


            <strong class="stat-number">

                <?= $totalExcusas ?>

            </strong>


            <span class="stat-label">
                Excusas
            </span>


        </div>


        <div class="stat-card ausentes">


            <div class="stat-icon">

                <i class="bi bi-person-x"></i>

            </div>


            <strong class="stat-number">

                <?= $totalAusentes ?>

            </strong>


            <span class="stat-label">
                Ausentes
            </span>


        </div>


        <div class="stat-card tardes">


            <div class="stat-icon">

                <i class="bi bi-clock-history"></i>

            </div>


            <strong class="stat-number">

                <?= $totalTardes ?>

            </strong>


            <span class="stat-label">
                Tardes
            </span>


        </div>


    </section>


    <!-- TABLA -->

    <?php if (
        count($registros) > 0
    ): ?>


        <section class="table-card">


            <div class="table-header">


                <div>

                    <h3>
                        Registros de asistencia
                    </h3>

                    <p>

                        <?= htmlspecialchars(
                            $nombreCursoSeleccionado
                        ) ?>

                        ·
                        <?= $totalRegistros ?>
                        registros encontrados

                    </p>

                </div>


            </div>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Estudiante
                            </th>

                            <th>
                                Curso
                            </th>

                            <th>
                                Fecha
                            </th>

                            <th>
                                Hora
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Excusa
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $registros
                        as $registro
                    ): ?>


                        <?php

                        $estado =
                            strtoupper(
                                trim(
                                    $registro['estado']
                                    ?? ''
                                )
                            );


                        $claseEstado =
                            'status-presente';


                        $textoEstado =
                            $estado;


                        $iconoEstado =
                            'bi-check-circle-fill';


                        if (
                            $estado === 'AUSENTE'
                        ) {

                            $claseEstado =
                                'status-ausente';

                            $iconoEstado =
                                'bi-x-circle-fill';

                        }


                        elseif (
                            $estado === 'TARDE'
                        ) {

                            $claseEstado =
                                'status-tarde';

                            $iconoEstado =
                                'bi-clock-fill';

                        }


                        elseif (
                            $estado === 'EVADIO'
                        ) {

                            $claseEstado =
                                'status-evadio';

                            $iconoEstado =
                                'bi-exclamation-circle-fill';

                        }


                        $fecha =
                            $registro['fecha']
                            ?? '';


                        $fechaFormateada =
                            $fecha;


                        if (
                            $fecha !== ''
                            &&
                            strtotime($fecha)
                        ) {

                            $fechaFormateada =
                                date(
                                    'd/m/Y',
                                    strtotime($fecha)
                                );

                        }


                        $hora =
                            $registro['hora_registro']
                            ?? $registro['hora_inicio']
                            ?? '';


                        $horaFormateada =
                            $hora;


                        if (
                            $hora !== ''
                            &&
                            strtotime($hora)
                        ) {

                            $horaFormateada =
                                date(
                                    'H:i',
                                    strtotime($hora)
                                );

                        }


                        $excusa =
                            trim(
                                $registro[
                                    'estado_excusa'
                                ]
                                ?? ''
                            );

                        ?>


                        <tr>


                            <td>


                                <div class="student-name">

                                    <?= htmlspecialchars(
                                        $registro[
                                            'nombres'
                                        ]
                                        . ' '
                                        .
                                        $registro[
                                            'apellidos'
                                        ]
                                    ) ?>

                                </div>


                                <div class="student-document">

                                    Documento:
                                    <?= htmlspecialchars(
                                        $registro[
                                            'documento'
                                        ]
                                    ) ?>

                                </div>


                            </td>


                            <td>

                                <div class="course-name">

                                    <?= htmlspecialchars(
                                        $registro[
                                            'nombre_curso'
                                        ]
                                    ) ?>

                                </div>

                            </td>


                            <td class="date-cell">

                                <?= htmlspecialchars(
                                    $fechaFormateada
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $horaFormateada
                                ) ?>

                            </td>


                            <td>


                                <span
                                    class="status
                                    <?= $claseEstado ?>"
                                >

                                    <i
                                        class="bi
                                        <?= $iconoEstado ?>"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $textoEstado
                                    ) ?>

                                </span>


                            </td>


                            <td>


                                <?php if (
                                    $excusa !== ''
                                ): ?>


                                    <span class="excuse">

                                        <i class="bi bi-file-text"></i>

                                        <?= htmlspecialchars(
                                            $excusa
                                        ) ?>

                                    </span>


                                <?php else: ?>


                                    <span class="no-excuse">

                                        —

                                    </span>


                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        </section>


    <?php else: ?>


        <section class="empty-card">


            <div class="empty-icon">

                <i class="bi bi-bar-chart"></i>

            </div>


            <h3>

                No hay registros de asistencia

            </h3>


            <p>

                <?php if (
                    $idCursoFiltro > 0
                ): ?>

                    El curso seleccionado todavía
                    no tiene registros de asistencia.

                <?php else: ?>

                    Todavía no existen registros de asistencia
                    asociados a tus cursos.

                <?php endif; ?>

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
