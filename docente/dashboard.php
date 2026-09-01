<?php
session_start();

/* =========================================================
   PROTECCIÓN DE SESIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


/* =========================================================
   DATOS DEL DOCENTE
========================================================= */

$idDocente = (int)$_SESSION['id_usuario'];

$nombreUsuario = $_SESSION['nombre'] ?? 'Docente';

$partesNombre = preg_split(
    '/\s+/',
    trim($nombreUsuario)
);

$primerNombre = $partesNombre[0] ?? 'Docente';

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
$fechaHoy = date('Y-m-d');


/* =========================================================
   CURSOS ASIGNADOS AL DOCENTE
========================================================= */

$cursosDocente = [];

$sqlCursosDocente = "
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

$stmtCursosDocente = mysqli_prepare(
    $conexion,
    $sqlCursosDocente
);

if ($stmtCursosDocente) {

    mysqli_stmt_bind_param(
        $stmtCursosDocente,
        "i",
        $idDocente
    );

    mysqli_stmt_execute(
        $stmtCursosDocente
    );

    $resultadoCursosDocente =
        mysqli_stmt_get_result(
            $stmtCursosDocente
        );

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoCursosDocente
        )
    ) {

        $cursosDocente[] = $fila;

    }

    mysqli_stmt_close(
        $stmtCursosDocente
    );
}


/* =========================================================
   IDS DE CURSOS DEL DOCENTE
========================================================= */

$idsCursos = [];

foreach ($cursosDocente as $curso) {

    $idsCursos[] = (int)$curso['id_curso'];

}


/* =========================================================
   TOTAL CURSOS
========================================================= */

$totalCursos = count($cursosDocente);


/* =========================================================
   TOTAL ESTUDIANTES DE SUS CURSOS
========================================================= */

$totalEstudiantes = 0;

$sqlEstudiantes = "
    SELECT COUNT(DISTINCT e.id_estudiante) AS total
    FROM estudiantes e
    INNER JOIN docente_curso dc
        ON dc.id_curso = e.id_curso
    WHERE dc.id_usuario = ?
    AND e.estado = 'ACTIVO'
";

$stmtEstudiantes = mysqli_prepare(
    $conexion,
    $sqlEstudiantes
);

if ($stmtEstudiantes) {

    mysqli_stmt_bind_param(
        $stmtEstudiantes,
        "i",
        $idDocente
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

    $totalEstudiantes =
        (int)(
            $filaEstudiantes['total'] ?? 0
        );

    mysqli_stmt_close(
        $stmtEstudiantes
    );
}


/* =========================================================
   SESIONES DEL DOCENTE HOY
========================================================= */

$sesionesHoy = [];

$sqlSesionesHoy = "
    SELECT
        s.id_sesion,
        s.id_curso,
        s.fecha,
        s.hora_inicio,
        s.estado,
        c.nombre_curso
    FROM sesiones_clase s
    INNER JOIN cursos c
        ON c.id_curso = s.id_curso
    WHERE s.id_docente = ?
    AND s.fecha = ?
    ORDER BY s.hora_inicio ASC
";

$stmtSesionesHoy = mysqli_prepare(
    $conexion,
    $sqlSesionesHoy
);

if ($stmtSesionesHoy) {

    mysqli_stmt_bind_param(
        $stmtSesionesHoy,
        "is",
        $idDocente,
        $fechaHoy
    );

    mysqli_stmt_execute(
        $stmtSesionesHoy
    );

    $resultadoSesionesHoy =
        mysqli_stmt_get_result(
            $stmtSesionesHoy
        );

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoSesionesHoy
        )
    ) {

        $sesionesHoy[] = $fila;

    }

    mysqli_stmt_close(
        $stmtSesionesHoy
    );
}


/* =========================================================
   ASISTENCIAS DE HOY
========================================================= */

$totalHoy = 0;

$sqlTotalHoy = "
    SELECT
        COUNT(a.id_asistencia) AS total
    FROM asistencia_clase a
    INNER JOIN sesiones_clase s
        ON s.id_sesion = a.id_sesion
    WHERE s.id_docente = ?
    AND s.fecha = ?
";

$stmtTotalHoy = mysqli_prepare(
    $conexion,
    $sqlTotalHoy
);

if ($stmtTotalHoy) {

    mysqli_stmt_bind_param(
        $stmtTotalHoy,
        "is",
        $idDocente,
        $fechaHoy
    );

    mysqli_stmt_execute(
        $stmtTotalHoy
    );

    $resultadoTotalHoy =
        mysqli_stmt_get_result(
            $stmtTotalHoy
        );

    $filaTotalHoy =
        mysqli_fetch_assoc(
            $resultadoTotalHoy
        );

    $totalHoy =
        (int)(
            $filaTotalHoy['total'] ?? 0
        );

    mysqli_stmt_close(
        $stmtTotalHoy
    );
}


/* =========================================================
   SESIONES REALIZADAS
========================================================= */

$totalSesiones = 0;

$sqlSesiones = "
    SELECT COUNT(*) AS total
    FROM sesiones_clase
    WHERE id_docente = ?
";

$stmtSesiones = mysqli_prepare(
    $conexion,
    $sqlSesiones
);

if ($stmtSesiones) {

    mysqli_stmt_bind_param(
        $stmtSesiones,
        "i",
        $idDocente
    );

    mysqli_stmt_execute(
        $stmtSesiones
    );

    $resultadoSesiones =
        mysqli_stmt_get_result(
            $stmtSesiones
        );

    $filaSesiones =
        mysqli_fetch_assoc(
            $resultadoSesiones
        );

    $totalSesiones =
        (int)(
            $filaSesiones['total'] ?? 0
        );

    mysqli_stmt_close(
        $stmtSesiones
    );
}


/* =========================================================
   GRÁFICA DE ASISTENCIAS - ÚLTIMOS 7 DÍAS
========================================================= */

$grafica = [];

$dias = [
    'Mon' => 'Lun',
    'Tue' => 'Mar',
    'Wed' => 'Mié',
    'Thu' => 'Jue',
    'Fri' => 'Vie',
    'Sat' => 'Sáb',
    'Sun' => 'Dom'
];

for ($i = 6; $i >= 0; $i--) {

    $fechaGrafica = date(
        'Y-m-d',
        strtotime("-$i days")
    );

    $diaNombre = date(
        'D',
        strtotime($fechaGrafica)
    );

    $grafica[] = [
        'fecha' => $fechaGrafica,
        'dia' => $dias[$diaNombre] ?? $diaNombre,
        'total' => 0
    ];

}


/* =========================================================
   CONSULTAR ASISTENCIAS
========================================================= */

$fechaInicioGrafica = date(
    'Y-m-d',
    strtotime('-6 days')
);

$sqlGrafica = "
    SELECT
        s.fecha,
        COUNT(a.id_asistencia) AS total
    FROM sesiones_clase s
    LEFT JOIN asistencia_clase a
        ON a.id_sesion = s.id_sesion
    WHERE s.id_docente = ?
    AND s.fecha BETWEEN ? AND ?
    GROUP BY s.fecha
    ORDER BY s.fecha ASC
";

$stmtGrafica = mysqli_prepare(
    $conexion,
    $sqlGrafica
);

if ($stmtGrafica) {

    mysqli_stmt_bind_param(
        $stmtGrafica,
        "iss",
        $idDocente,
        $fechaInicioGrafica,
        $fechaHoy
    );

    mysqli_stmt_execute(
        $stmtGrafica
    );

    $resultadoGrafica =
        mysqli_stmt_get_result(
            $stmtGrafica
        );

    $datosPorFecha = [];

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoGrafica
        )
    ) {

        $datosPorFecha[
            $fila['fecha']
        ] = (int)$fila['total'];

    }


    foreach ($grafica as &$diaGrafica) {

        if (
            isset(
                $datosPorFecha[
                    $diaGrafica['fecha']
                ]
            )
        ) {

            $diaGrafica['total'] =
                $datosPorFecha[
                    $diaGrafica['fecha']
                ];

        }

    }

    unset($diaGrafica);

    mysqli_stmt_close(
        $stmtGrafica
    );
}


/* =========================================================
   MÁXIMO DE LA GRÁFICA
========================================================= */

$maxGrafica = 1;

foreach ($grafica as $dia) {

    if ($dia['total'] > $maxGrafica) {

        $maxGrafica = $dia['total'];

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
    Asistencia QR | Panel docente
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
   WELCOME
========================================================= */

.welcome{

    position:relative;

    min-height:215px;

    overflow:hidden;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:30px;

    padding:
        30px 40px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:

        radial-gradient(
            circle at 84% 20%,
            rgba(24,216,206,.17),
            transparent 25%
        ),

        radial-gradient(
            circle at 65% 110%,
            rgba(133,121,210,.13),
            transparent 34%
        ),

        linear-gradient(
            135deg,
            rgba(255,255,255,.85),
            rgba(236,250,248,.72)
        );

    box-shadow:
        0 22px 52px
        rgba(55,113,129,.08);

}


.welcome-content{

    position:relative;

    z-index:3;

    max-width:720px;

}


.welcome-tag{

    display:inline-flex;

    align-items:center;

    gap:7px;

    margin-bottom:11px;

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


.welcome h2{

    color:#15576c;

    font-size:33px;

    font-weight:950;

    line-height:1.15;

}


.welcome h2 span{

    color:#0a9f9b;

}


.welcome p{

    max-width:680px;

    margin-top:11px;

    color:#7898a2;

    font-size:14px;

    font-weight:650;

    line-height:1.65;

}


/* =========================================================
   ILUSTRACIÓN
========================================================= */

.welcome-illustration{

    position:relative;

    z-index:3;

    width:250px;
    height:170px;

    flex-shrink:0;

}


.illustration-glow{

    position:absolute;

    width:145px;
    height:145px;

    right:25px;
    top:10px;

    border-radius:50%;

    background:
        radial-gradient(
            circle,
            rgba(24,216,206,.17),
            transparent 68%
        );

    animation:
        glowPulse 4s ease-in-out infinite;

}


@keyframes glowPulse{

    0%,100%{
        transform:scale(1);
        opacity:.7;
    }

    50%{
        transform:scale(1.13);
        opacity:1;
    }

}


.illustration-platform{

    position:absolute;

    right:22px;
    bottom:9px;

    width:170px;
    height:34px;

    border-radius:50%;

    transform:
        rotate(-4deg);

    background:
        linear-gradient(
            135deg,
            rgba(24,216,206,.28),
            rgba(133,121,210,.22)
        );

}


.illustration-device{

    position:absolute;

    right:47px;
    top:23px;

    width:118px;
    height:126px;

    padding:10px;

    border-radius:23px;

    transform:
        rotate(7deg);

    background:
        linear-gradient(
            145deg,
            #ffffff,
            #e5f7f5
        );

    box-shadow:
        0 22px 35px
        rgba(55,113,129,.16);

    animation:
        deviceFloat 4s ease-in-out infinite;

}


@keyframes deviceFloat{

    0%,100%{
        transform:
            translateY(0)
            rotate(7deg);
    }

    50%{
        transform:
            translateY(-7px)
            rotate(7deg);
    }

}


.device-screen{

    width:100%;
    height:100%;

    display:flex;

    flex-direction:column;

    align-items:center;
    justify-content:center;

    gap:9px;

    border-radius:15px;

    background:
        linear-gradient(
            145deg,
            #eefcf9,
            #ffffff
        );

}


.qr-modern{

    width:61px;
    height:61px;

    display:grid;

    grid-template-columns:
        repeat(5,1fr);

    gap:3px;

    padding:7px;

    border-radius:11px;

    background:#fff;

}


.qr-modern span{

    border-radius:2px;

    background:#387d89;

}


.qr-modern span:nth-child(2),
.qr-modern span:nth-child(5),
.qr-modern span:nth-child(9),
.qr-modern span:nth-child(12),
.qr-modern span:nth-child(17),
.qr-modern span:nth-child(21),
.qr-modern span:nth-child(24){

    background:#18cfc6;

}


.device-line{

    width:43px;
    height:5px;

    border-radius:5px;

    background:#b9dfe0;

}


.float-element{

    position:absolute;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:14px;

    box-shadow:
        0 12px 25px
        rgba(55,113,129,.12);

    animation:
        elementFloat 3.5s ease-in-out infinite;

}


.float-one{

    left:5px;
    top:32px;

    width:45px;
    height:45px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #42cda1,
            #20a67e
        );

}


.float-two{

    right:0;
    top:7px;

    width:37px;
    height:37px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #8579d2,
            #6b60b8
        );

    animation-delay:.7s;

}


.float-three{

    left:32px;
    bottom:20px;

    width:31px;
    height:31px;

    color:#0b9f9c;

    background:
        rgba(255,255,255,.92);

    animation-delay:1.2s;

}


@keyframes elementFloat{

    0%,100%{
        transform:
            translateY(0);
    }

    50%{
        transform:
            translateY(-8px);
    }

}


/* =========================================================
   RESUMEN
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    gap:16px;

}


.summary-item{

    position:relative;

    min-height:128px;

    overflow:hidden;

    display:flex;

    align-items:center;

    gap:17px;

    padding:
        21px 22px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:23px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

    transition:.25s;

}


.summary-item:hover{

    transform:
        translateY(-5px);

}


.summary-icon{

    width:63px;
    height:63px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:18px;

    color:#0a9995;

    background:
        rgba(24,216,206,.10);

    font-size:28px;

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


.summary-item:nth-child(4)
.summary-icon{

    color:#3aa47d;

    background:
        rgba(66,205,161,.10);

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

    font-size:30px;

    font-weight:950;

    line-height:1;

}


/* =========================================================
   ANALYTICS
========================================================= */

.analytics-layout{

    display:grid;

    grid-template-columns:
        minmax(0,1.75fr)
        minmax(290px,.85fr);

    gap:16px;

}


.chart-card,
.today-card{

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


.chart-card{

    padding:
        24px 25px 21px;

}


.section-heading{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:15px;

    margin-bottom:20px;

}


.section-heading h3,
.today-card h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.section-heading p,
.today-card > p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


.week-label{

    padding:
        7px 10px;

    border-radius:10px;

    color:#17867f;

    background:
        rgba(24,216,206,.08);

    font-size:10px;

    font-weight:900;

}


.chart-area{

    height:220px;

    display:flex;

    align-items:flex-end;

    gap:13px;

    padding:
        10px 5px 0;

}


.chart-column{

    flex:1;

    height:100%;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:flex-end;

    gap:8px;

}


.chart-value{

    min-height:18px;

    color:#668995;

    font-size:10px;

    font-weight:850;

}


.chart-bar-wrapper{

    width:100%;

    height:170px;

    display:flex;

    align-items:flex-end;

    justify-content:center;

}


.chart-bar{

    width:
        min(52px,70%);

    min-height:7px;

    border-radius:
        12px 12px 7px 7px;

    background:
        linear-gradient(
            180deg,
            #18d8ce,
            #69b8d5
        );

    transition:
        height .5s ease;

}


.chart-day{

    color:#7897a0;

    font-size:10px;

    font-weight:850;

}


/* =========================================================
   SESIONES
========================================================= */

.today-card{

    padding:
        24px;

}


.today-list{

    display:flex;

    flex-direction:column;

    gap:9px;

    margin-top:19px;

}


.today-session{

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        11px;

    border-radius:14px;

    background:
        rgba(255,255,255,.68);

}


.today-time{

    width:52px;

    flex-shrink:0;

    color:#118f91;

    font-size:11px;

    font-weight:950;

}


.today-session-info{

    min-width:0;

    flex:1;

}


.today-session-info strong{

    display:block;

    overflow:hidden;

    color:#456f7c;

    font-size:12px;

    font-weight:900;

    white-space:nowrap;

    text-overflow:ellipsis;

}


.today-session-info small{

    display:block;

    margin-top:3px;

    color:#8aa2a9;

    font-size:10px;

    font-weight:700;

}


.today-status{

    width:8px;
    height:8px;

    flex-shrink:0;

    border-radius:50%;

    background:#42cda1;

    box-shadow:
        0 0 0 4px
        rgba(66,205,161,.10);

}


.no-sessions{

    padding:
        35px 10px;

    text-align:center;

    color:#8aa3aa;

    font-size:12px;

    font-weight:750;

}


.no-sessions i{

    display:block;

    margin-bottom:8px;

    color:#8dc6c5;

    font-size:32px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px){

    .sidebar{

        width:255px;

    }

    .summary-grid{

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media(max-width:1000px){

    .analytics-layout{

        grid-template-columns:1fr;

    }

    .welcome-illustration{

        width:210px;

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

        grid-template-columns:
            1fr;

    }

    .welcome{

        padding:
            25px 22px;

    }

    .welcome h2{

        font-size:26px;

    }

    .welcome-illustration{

        display:none;

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

    .chart-area{

        gap:6px;

    }

    .chart-bar{

        width:75%;

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

            <span>Inicio</span>

            <span class="nav-arrow"></span>

        </a>

    </div>


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

            <span>Mis cursos</span>

            <span class="nav-arrow"></span>

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

            <span class="nav-arrow"></span>

        </a>


        <a
            href="reportes.php"
            class="nav-link"
        >

            <div class="nav-icon reports">

                <i class="bi bi-bar-chart-line"></i>

            </div>

            <span>Reportes</span>

            <span class="nav-arrow"></span>

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

<header class="topbar">

<div class="page-info">

    <div class="page-indicator"></div>

    <div class="page-title">

        <h1>
            Panel docente
        </h1>

        <p>
            Resumen de tu actividad académica
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
====================================================== -->

<section class="welcome">

<div class="welcome-content">


    <div class="welcome-tag">

        <i class="bi bi-stars"></i>

        PANEL DEL DOCENTE

    </div>


    <h2>

        ¡Hola,

        <span>
            <?= htmlspecialchars(
                $primerNombre
            ) ?>
        </span>!

    </h2>


    <p>

        Consulta rápidamente tus cursos,
        estudiantes y registros de asistencia
        desde un solo lugar.

    </p>

</div>


<div class="welcome-illustration">

    <div class="illustration-glow"></div>


    <div class="float-element float-one">

        <i class="bi bi-mortarboard-fill"></i>

    </div>


    <div class="float-element float-two">

        <i class="bi bi-stars"></i>

    </div>


    <div class="float-element float-three">

        <i class="bi bi-check-lg"></i>

    </div>


    <div class="illustration-platform"></div>


    <div class="illustration-device">


        <div class="device-screen">


            <div class="qr-modern">

                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>

                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>

                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>

                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>

                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>

            </div>


            <div class="device-line"></div>


        </div>

    </div>

</div>

</section>

<!-- =====================================================
     RESUMEN
====================================================== -->

<section class="summary-grid">

<div class="summary-item">

    <div class="summary-icon">

        <i class="bi bi-mortarboard-fill"></i>

    </div>


    <div class="summary-text">

        <span>
            Mis cursos
        </span>

        <strong>
            <?= $totalCursos ?>
        </strong>

    </div>

</div>


<div class="summary-item">

    <div class="summary-icon">

        <i class="bi bi-people-fill"></i>

    </div>


    <div class="summary-text">

        <span>
            Mis estudiantes
        </span>

        <strong>
            <?= $totalEstudiantes ?>
        </strong>

    </div>

</div>


<div class="summary-item">

    <div class="summary-icon">

        <i class="bi bi-calendar-check-fill"></i>

    </div>


    <div class="summary-text">

        <span>
            Sesiones realizadas
        </span>

        <strong>
            <?= $totalSesiones ?>
        </strong>

    </div>

</div>


<div class="summary-item">

    <div class="summary-icon">

        <i class="bi bi-person-check-fill"></i>

    </div>


    <div class="summary-text">

        <span>
            Asistencias de hoy
        </span>

        <strong>
            <?= $totalHoy ?>
        </strong>

    </div>

</div>

</section>

<!-- =====================================================
     ANALYTICS
====================================================== -->

<section class="analytics-layout">

<!-- SESIONES -->

<div class="today-card">


    <h3>
        Sesiones de hoy
    </h3>


    <p>
        Tus clases programadas
    </p>


    <div class="today-list">


        <?php if (
            count($sesionesHoy) > 0
        ): ?>


            <?php foreach (
                $sesionesHoy
                as $sesion
            ): ?>


                <div class="today-session">


                    <div class="today-time">

                        <?= htmlspecialchars(
                            substr(
                                $sesion[
                                    'hora_inicio'
                                ],
                                0,
                                5
                            )
                        ) ?>

                    </div>


                    <div class="today-session-info">


                        <strong>

                            <?= htmlspecialchars(
                                $sesion[
                                    'nombre_curso'
                                ]
                            ) ?>

                        </strong>


                        <small>

                            Sesión de clase

                        </small>


                    </div>


                    <div class="today-status"></div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="no-sessions">


                <i class="bi bi-calendar2-check"></i>


                No tienes sesiones registradas
                para hoy.


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
