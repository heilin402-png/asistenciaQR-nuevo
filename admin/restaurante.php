<?php

session_start();

/* =========================================================
   PROTECCIÓN DE SESIÓN Y ROL ADMINISTRADOR
========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

if (
    !isset($_SESSION['id_rol']) ||
    (int)$_SESSION['id_rol'] !== 1
) {
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

$nombreUsuario =
    $_SESSION['nombre'] ??
    'Administrador Sistema';

$partesNombre = preg_split(
    '/\s+/',
    trim($nombreUsuario)
);

$primerNombre =
    $partesNombre[0] ??
    'Administrador';

$iniciales = '';

foreach (
    array_slice(
        $partesNombre,
        0,
        2
    ) as $parte
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
    $iniciales = 'AS';
}

/* =========================================================
   FECHA Y HORA
========================================================= */

$horaActual = date('H:i:s');
$fechaActual = date('d/m/Y');
$fechaHoy = date('Y-m-d');

/* =========================================================
   FUNCIÓN NÚMEROS
========================================================= */

function formatoNumero($numero)
{
    return number_format(
        (int)$numero,
        0,
        ',',
        '.'
    );
}

/* =========================================================
   CONTADORES
========================================================= */

$totalRegistros = 0;
$totalHoy = 0;
$totalRegistrados = 0;
$totalNoRegistrados = 0;

/* =========================================================
   TOTAL REGISTROS
========================================================= */

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
";

$resultadoTotal = mysqli_query(
    $conexion,
    $sqlTotal
);

if ($resultadoTotal) {

    $filaTotal =
        mysqli_fetch_assoc(
            $resultadoTotal
        );

    $totalRegistros =
        (int)(
            $filaTotal['total'] ?? 0
        );
}

/* =========================================================
   REGISTROS DE HOY
========================================================= */

$sqlHoy = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
    WHERE fecha = ?
";

$stmtHoy = mysqli_prepare(
    $conexion,
    $sqlHoy
);

if ($stmtHoy) {

    mysqli_stmt_bind_param(
        $stmtHoy,
        "s",
        $fechaHoy
    );

    mysqli_stmt_execute(
        $stmtHoy
    );

    $resultadoHoy =
        mysqli_stmt_get_result(
            $stmtHoy
        );

    $filaHoy =
        mysqli_fetch_assoc(
            $resultadoHoy
        );

    $totalHoy =
        (int)(
            $filaHoy['total'] ?? 0
        );

    mysqli_stmt_close(
        $stmtHoy
    );
}

/* =========================================================
   REGISTRADOS
========================================================= */

$sqlRegistrados = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
    WHERE estado = 'REGISTRADO'
";

$resultadoRegistrados =
    mysqli_query(
        $conexion,
        $sqlRegistrados
    );

if ($resultadoRegistrados) {

    $filaRegistrados =
        mysqli_fetch_assoc(
            $resultadoRegistrados
        );

    $totalRegistrados =
        (int)(
            $filaRegistrados['total'] ?? 0
        );
}

/* =========================================================
   NO REGISTRADOS
========================================================= */

$sqlNoRegistrados = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
    WHERE estado = 'NO_REGISTRADO'
";

$resultadoNoRegistrados =
    mysqli_query(
        $conexion,
        $sqlNoRegistrados
    );

if ($resultadoNoRegistrados) {

    $filaNoRegistrados =
        mysqli_fetch_assoc(
            $resultadoNoRegistrados
        );

    $totalNoRegistrados =
        (int)(
            $filaNoRegistrados['total'] ?? 0
        );
}

/* =========================================================
   ESTADÍSTICA ÚLTIMOS 7 DÍAS
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

for (
    $i = 6;
    $i >= 0;
    $i--
) {

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
        'dia' =>
            $dias[$diaNombre]
            ?? $diaNombre,
        'total' => 0
    ];
}

/* =========================================================
   CONSULTAR ACTIVIDAD DEL RESTAURANTE
========================================================= */

$fechaInicioGrafica = date(
    'Y-m-d',
    strtotime('-6 days')
);

$sqlGrafica = "
    SELECT
        fecha,
        COUNT(*) AS total

    FROM asistencia_restaurante

    WHERE fecha BETWEEN ? AND ?
      AND estado = 'REGISTRADO'

    GROUP BY fecha

    ORDER BY fecha ASC
";

$stmtGrafica = mysqli_prepare(
    $conexion,
    $sqlGrafica
);

if ($stmtGrafica) {

    mysqli_stmt_bind_param(
        $stmtGrafica,
        "ss",
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
        $fila =
        mysqli_fetch_assoc(
            $resultadoGrafica
        )
    ) {

        $datosPorFecha[
            $fila['fecha']
        ] =
            (int)$fila['total'];
    }

    foreach (
        $grafica as &$diaGrafica
    ) {

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
   TOTAL SEMANAL
========================================================= */

$totalSemana = 0;

foreach (
    $grafica as $dia
) {

    $totalSemana +=
        (int)$dia['total'];
}

/* =========================================================
   FILTROS
========================================================= */

$fechaFiltro =
    $_GET['fecha'] ?? '';

$estadoFiltro =
    $_GET['estado'] ?? '';

$busqueda =
    trim(
        $_GET['busqueda'] ?? ''
    );

/* =========================================================
   CONSULTA HISTORIAL
========================================================= */

$condiciones = [];
$parametros = [];
$tipos = '';

if ($fechaFiltro !== '') {

    $condiciones[] =
        "ar.fecha = ?";

    $parametros[] =
        $fechaFiltro;

    $tipos .= "s";
}

if (
    $estadoFiltro === 'REGISTRADO' ||
    $estadoFiltro === 'NO_REGISTRADO'
) {

    $condiciones[] =
        "ar.estado = ?";

    $parametros[] =
        $estadoFiltro;

    $tipos .= "s";
}

if ($busqueda !== '') {

    $condiciones[] = "
        (
            e.nombres LIKE ?
            OR e.apellidos LIKE ?
            OR e.documento LIKE ?
        )
    ";

    $valorBusqueda =
        '%' .
        $busqueda .
        '%';

    $parametros[] =
        $valorBusqueda;

    $parametros[] =
        $valorBusqueda;

    $parametros[] =
        $valorBusqueda;

    $tipos .= "sss";
}

$where = '';

if (!empty($condiciones)) {

    $where =
        'WHERE ' .
        implode(
            ' AND ',
            $condiciones
        );
}

/* =========================================================
   HISTORIAL
========================================================= */

$sqlHistorial = "

    SELECT

        ar.id_asistencia_restaurante,
        ar.id_estudiante,
        ar.fecha,
        ar.hora,
        ar.estado,
        ar.observacion,
        ar.fecha_registro,

        e.documento,
        e.nombres,
        e.apellidos,
        e.id_curso,

        c.nombre_curso

    FROM asistencia_restaurante ar

    INNER JOIN estudiantes e
        ON e.id_estudiante =
           ar.id_estudiante

    LEFT JOIN cursos c
        ON c.id_curso =
           e.id_curso

    $where

    ORDER BY
        ar.fecha DESC,
        ar.hora DESC

    LIMIT 100
";

$stmtHistorial =
    mysqli_prepare(
        $conexion,
        $sqlHistorial
    );

$resultadoHistorial = false;

if ($stmtHistorial) {

    if (!empty($parametros)) {

        mysqli_stmt_bind_param(
            $stmtHistorial,
            $tipos,
            ...$parametros
        );
    }

    mysqli_stmt_execute(
        $stmtHistorial
    );

    $resultadoHistorial =
        mysqli_stmt_get_result(
            $stmtHistorial
        );
}

/* =========================================================
   TOTAL FILTRADO
========================================================= */

$totalFiltrado = 0;

$sqlFiltrado = "

    SELECT COUNT(*) AS total

    FROM asistencia_restaurante ar

    INNER JOIN estudiantes e
        ON e.id_estudiante =
           ar.id_estudiante

    $where
";

$stmtFiltrado =
    mysqli_prepare(
        $conexion,
        $sqlFiltrado
    );

if ($stmtFiltrado) {

    if (!empty($parametros)) {

        mysqli_stmt_bind_param(
            $stmtFiltrado,
            $tipos,
            ...$parametros
        );
    }

    mysqli_stmt_execute(
        $stmtFiltrado
    );

    $resultadoFiltrado =
        mysqli_stmt_get_result(
            $stmtFiltrado
        );

    $filaFiltrado =
        mysqli_fetch_assoc(
            $resultadoFiltrado
        );

    $totalFiltrado =
        (int)(
            $filaFiltrado['total'] ?? 0
        );
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
    Asistencia QR | Restaurante
</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<style>

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
            #d1a158,
            var(--coral)
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

    color:#c08c3f;

    background:
        rgba(209,161,88,.10);

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
            rgba(209,161,88,.17),
            transparent 25%
        ),

        radial-gradient(
            circle at 65% 110%,
            rgba(24,216,206,.13),
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

.welcome::before{

    content:"";

    position:absolute;

    width:300px;
    height:300px;

    right:-70px;
    top:-190px;

    border-radius:50%;

    border:
        45px solid
        rgba(209,161,88,.055);

}

.welcome::after{

    content:"";

    position:absolute;

    width:190px;
    height:190px;

    right:300px;
    bottom:-135px;

    border-radius:50%;

    background:
        rgba(133,121,210,.06);

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

    color:#a97935;

    background:
        rgba(209,161,88,.09);

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
    color:#c08c3f;
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
   RESTAURANT ILLUSTRATION
========================================================= */

.restaurant-illustration{

    position:relative;

    z-index:3;

    width:250px;
    height:170px;

    flex-shrink:0;

}

.restaurant-glow{

    position:absolute;

    width:145px;
    height:145px;

    right:25px;
    top:10px;

    border-radius:50%;

    background:
        radial-gradient(
            circle,
            rgba(209,161,88,.18),
            transparent 68%
        );

    animation:
        glowPulse 4s ease-in-out infinite;

}

.restaurant-plate{

    position:absolute;

    right:42px;
    top:20px;

    width:125px;
    height:125px;

    display:flex;

    align-items:center;
    justify-content:center;

    border:
        9px solid
        rgba(255,255,255,.86);

    border-radius:50%;

    background:
        linear-gradient(
            145deg,
            #f8fffd,
            #e6f6f3
        );

    box-shadow:
        0 22px 35px
        rgba(55,113,129,.14);

    animation:
        plateFloat 4s ease-in-out infinite;

}

.restaurant-plate i{

    color:#d19a46;

    font-size:52px;

}

.restaurant-float{

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

.restaurant-float.one{

    left:5px;
    top:32px;

    width:45px;
    height:45px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #d1a158,
            #b87b2d
        );

}

.restaurant-float.two{

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

.restaurant-float.three{

    left:32px;
    bottom:20px;

    width:31px;
    height:31px;

    color:#0b9f9c;

    background:
        rgba(255,255,255,.92);

    animation-delay:1.2s;

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

@keyframes plateFloat{

    0%,100%{
        transform:translateY(0);
    }

    50%{
        transform:translateY(-7px);
    }

}

@keyframes elementFloat{

    0%,100%{
        transform:translateY(0);
    }

    50%{
        transform:translateY(-8px);
    }

}

/* =========================================================
   SUMMARY
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

    transition:
        transform .25s,
        box-shadow .25s;

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
        rgba(209,161,88,.055);

}

.summary-item:hover{

    transform:
        translateY(-5px);

    box-shadow:
        0 24px 48px
        rgba(55,113,129,.10);

}

.summary-icon{

    width:63px;
    height:63px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:18px;

    color:#c08c3f;

    background:
        rgba(209,161,88,.10);

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

    color:#d47778;

    background:
        rgba(216,121,131,.10);

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

.chart-card{

    padding:
        24px 25px 21px;

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

.section-heading{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:15px;

    margin-bottom:20px;

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

.week-label{

    display:flex;

    align-items:center;

    gap:7px;

    padding:
        7px 10px;

    border-radius:10px;

    color:#a97835;

    background:
        rgba(209,161,88,.08);

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
            #d1a158,
            #69b8d5
        );

    box-shadow:
        0 8px 18px
        rgba(209,161,88,.13);

    transition:
        height .5s ease,
        transform .2s ease;

}

.chart-bar:hover{

    transform:
        translateY(-5px);

}

.chart-day{

    color:#7897a0;

    font-size:10px;

    font-weight:850;

}

/* =========================================================
   SERVICE CARD
========================================================= */

.today-card{

    padding:
        24px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:25px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.80),
            rgba(236,250,248,.68)
        );

    box-shadow:
        0 18px 42px
        rgba(55,113,129,.065);

}

.today-card h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}

.today-card > p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}

.service-list{

    display:flex;

    flex-direction:column;

    gap:9px;

    margin-top:19px;

}

.service-row{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    padding:
        12px;

    border-radius:14px;

    background:
        rgba(255,255,255,.68);

    border:
        1px solid
        rgba(255,255,255,.82);

}

.service-info{

    display:flex;

    align-items:center;

    gap:9px;

    color:#63838d;

    font-size:11px;

    font-weight:800;

}

.service-info i{

    color:#c08c3f;

    font-size:15px;

}

.service-row strong{

    color:#416f7e;

    font-size:14px;

    font-weight:950;

}

/* =========================================================
   FILTERS
========================================================= */

.filter-section{

    padding:
        22px;

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

.filter-title{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-bottom:16px;

}

.filter-title h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}

.filter-title span{

    color:#819ca4;

    font-size:11px;

    font-weight:700;

}

.filters{

    display:flex;

    align-items:flex-end;

    flex-wrap:wrap;

    gap:12px;

}

.field{

    display:flex;

    flex-direction:column;

    gap:6px;

}

.field label{

    color:#6f909a;

    font-size:9px;

    font-weight:900;

    letter-spacing:.5px;

}

.field input,
.field select{

    height:42px;

    min-width:160px;

    padding:
        0 12px;

    border:
        1px solid
        rgba(112,158,168,.16);

    outline:none;

    border-radius:12px;

    color:#4f7986;

    background:
        rgba(255,255,255,.78);

    font-family:inherit;

    font-size:11px;

    font-weight:700;

}

.field.search{

    flex:1;

    min-width:220px;

}

.field.search input{

    width:100%;

}

.field input:focus,
.field select:focus{

    border-color:
        rgba(24,216,206,.45);

}

.btn{

    height:42px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        0 16px;

    border:0;

    border-radius:12px;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc3,
            #459bc2
        );

    cursor:pointer;

    text-decoration:none;

    font-family:inherit;

    font-size:10px;

    font-weight:900;

    box-shadow:
        0 8px 18px
        rgba(24,207,195,.16);

    transition:.25s;

}

.btn:hover{

    transform:
        translateY(-2px);

}

.btn.secondary{

    color:#597d88;

    background:
        rgba(255,255,255,.80);

    box-shadow:none;

}

/* =========================================================
   TABLE
========================================================= */

.table-card{

    padding:
        22px;

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

.table-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-bottom:16px;

}

.table-header h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}

.table-header span{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        7px 10px;

    border-radius:10px;

    color:#168b7b;

    background:
        rgba(66,205,161,.09);

    font-size:9px;

    font-weight:900;

}

.table-wrapper{

    overflow-x:auto;

}

table{

    width:100%;

    min-width:900px;

    border-collapse:collapse;

}

thead th{

    padding:
        11px 10px;

    text-align:left;

    color:#73939c;

    background:
        rgba(232,250,247,.52);

    font-size:9px;

    font-weight:950;

    letter-spacing:.3px;

}

thead th:first-child{

    border-radius:
        10px 0 0 10px;

}

thead th:last-child{

    border-radius:
        0 10px 10px 0;

}

tbody td{

    padding:
        13px 10px;

    border-bottom:
        1px solid
        rgba(66,111,125,.075);

    color:#5d808b;

    font-size:10px;

    font-weight:700;

}

tbody tr{

    transition:.2s;

}

tbody tr:hover{

    background:
        rgba(24,216,206,.035);

}

.student{

    display:flex;

    align-items:center;

    gap:9px;

}

.student-avatar{

    width:34px;
    height:34px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #6accc0,
            #549fc2
        );

    font-size:11px;

    font-weight:950;

}

.student-name strong{

    display:block;

    color:#416f7e;

    font-size:10px;

    font-weight:900;

}

.student-name small{

    display:block;

    margin-top:2px;

    color:#91a8ae;

    font-size:8px;

}

.course{

    display:inline-flex;

    align-items:center;

    padding:
        6px 8px;

    border-radius:9px;

    color:#6578bd;

    background:
        rgba(133,121,210,.08);

    font-size:8px;

    font-weight:900;

}

.time{

    color:#416f7e;

    font-size:10px;

    font-weight:900;

    white-space:nowrap;

}

.badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        5px 8px;

    border-radius:9px;

    font-size:8px;

    font-weight:950;

    white-space:nowrap;

}

.badge.registered{

    color:#198d6b;

    background:
        rgba(39,184,132,.10);

}

.badge.not-registered{

    color:#b96d76;

    background:
        rgba(216,121,131,.10);

}

.badge i{

    font-size:8px;

}

.observation{

    max-width:190px;

    overflow:hidden;

    white-space:nowrap;

    text-overflow:ellipsis;

}

/* =========================================================
   EMPTY
========================================================= */

.empty{

    padding:
        50px 20px;

    text-align:center;

}

.empty i{

    display:block;

    margin-bottom:10px;

    color:#8dc6c5;

    font-size:38px;

}

.empty strong{

    display:block;

    color:#557d89;

    font-size:13px;

}

.empty span{

    display:block;

    margin-top:5px;

    color:#91a8ae;

    font-size:10px;

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

        grid-template-columns:1fr;

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

    .filter-section{

        padding:17px;

    }

    .filters{

        flex-direction:column;

        align-items:stretch;

    }

    .field,
    .field.search{

        width:100%;

        min-width:0;

    }

    .field input,
    .field select,
    .btn{

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

    .chart-area{

        gap:6px;

    }

    .chart-bar{

        width:75%;

    }

    .table-card{

        padding:15px;

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
                class="nav-link active"
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

<header class="topbar">

    <div class="page-info">

        <div class="page-indicator"></div>

        <div class="page-title">

            <h1>
                Control de restaurante
            </h1>

            <p>
                Gestión y seguimiento del servicio de alimentación
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
     WELCOME
====================================================== -->

<section class="welcome">

    <div class="welcome-content">

        <div class="welcome-tag">

            <i class="bi bi-egg-fried"></i>

            SERVICIO DE ALIMENTACIÓN

        </div>

        <h2>

            Control del
            <span>
                restaurante
            </span>

        </h2>

        <p>

            Supervisa los registros de alimentación
            de los estudiantes, consulta la actividad
            del servicio y revisa el historial desde
            un único espacio administrativo.

        </p>

    </div>

    <div class="restaurant-illustration">

        <div class="restaurant-glow"></div>

        <div class="restaurant-float one">

            <i class="bi bi-check-lg"></i>

        </div>

        <div class="restaurant-float two">

            <i class="bi bi-stars"></i>

        </div>

        <div class="restaurant-float three">

            <i class="bi bi-heart-fill"></i>

        </div>

        <div class="restaurant-plate">

            <i class="bi bi-egg-fried"></i>

        </div>

    </div>

</section>

<!-- =====================================================
     RESUMEN
====================================================== -->

<section class="summary-grid">

    <div class="summary-item">

        <div class="summary-icon">

            <i class="bi bi-clipboard2-check"></i>

        </div>

        <div class="summary-text">

            <span>
                Registros totales
            </span>

            <strong>
                <?= formatoNumero(
                    $totalRegistros
                ) ?>
            </strong>

        </div>

    </div>

    <div class="summary-item">

        <div class="summary-icon">

            <i class="bi bi-calendar-check"></i>

        </div>

        <div class="summary-text">

            <span>
                Registros de hoy
            </span>

            <strong>
                <?= formatoNumero(
                    $totalHoy
                ) ?>
            </strong>

        </div>

    </div>

    <div class="summary-item">

        <div class="summary-icon">

            <i class="bi bi-check-circle-fill"></i>

        </div>

        <div class="summary-text">

            <span>
                Servicios registrados
            </span>

            <strong>
                <?= formatoNumero(
                    $totalRegistrados
                ) ?>
            </strong>

        </div>

    </div>

    <div class="summary-item">

        <div class="summary-icon">

            <i class="bi bi-dash-circle-fill"></i>

        </div>

        <div class="summary-text">

            <span>
                No registrados
            </span>

            <strong>
                <?= formatoNumero(
                    $totalNoRegistrados
                ) ?>
            </strong>

        </div>

    </div>

</section>

<!-- =====================================================
     ANALÍTICA
====================================================== -->

<section class="analytics-layout">

    <div class="chart-card">

        <div class="section-heading">

            <div>

                <h3>
                    Actividad del restaurante
                </h3>

                <p>
                    Registros registrados durante los últimos 7 días
                </p>

            </div>

            <div class="week-label">

                <i class="bi bi-calendar3"></i>

                <?= formatoNumero(
                    $totalSemana
                ) ?>

                registros

            </div>

        </div>

        <div class="chart-area">

            <?php

            $maxGrafica = 1;

            foreach (
                $grafica as $dia
            ) {

                if (
                    $dia['total']
                    >
                    $maxGrafica
                ) {

                    $maxGrafica =
                        $dia['total'];

                }

            }

            foreach (
                $grafica as $dia
            ):

                $altura =
                    (
                        $dia['total']
                        /
                        $maxGrafica
                    )
                    * 100;

            ?>

                <div class="chart-column">

                    <div class="chart-value">

                        <?= formatoNumero(
                            $dia['total']
                        ) ?>

                    </div>

                    <div class="chart-bar-wrapper">

                        <div
                            class="chart-bar"
                            style="
                                height:
                                <?= max(
                                    7,
                                    $altura
                                ) ?>%;
                            "
                            title="
                                <?= htmlspecialchars(
                                    $dia['dia']
                                ) ?>:
                                <?= $dia['total'] ?>
                                registros
                            "
                        ></div>

                    </div>

                    <div class="chart-day">

                        <?= htmlspecialchars(
                            $dia['dia']
                        ) ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <!-- RESUMEN DEL SERVICIO -->

    <div class="today-card">

        <h3>
            Estado del servicio
        </h3>

        <p>
            Resumen actual del restaurante
        </p>

        <div class="service-list">

            <div class="service-row">

                <div class="service-info">

                    <i class="bi bi-people-fill"></i>

                    Registros de hoy

                </div>

                <strong>
                    <?= formatoNumero(
                        $totalHoy
                    ) ?>
                </strong>

            </div>

            <div class="service-row">

                <div class="service-info">

                    <i class="bi bi-check-circle-fill"></i>

                    Registrados

                </div>

                <strong>
                    <?= formatoNumero(
                        $totalRegistrados
                    ) ?>
                </strong>

            </div>

            <div class="service-row">

                <div class="service-info">

                    <i class="bi bi-x-circle-fill"></i>

                    No registrados

                </div>

                <strong>
                    <?= formatoNumero(
                        $totalNoRegistrados
                    ) ?>
                </strong>

            </div>

            <div class="service-row">

                <div class="service-info">

                    <i class="bi bi-activity"></i>

                    Actividad semanal

                </div>

                <strong>
                    <?= formatoNumero(
                        $totalSemana
                    ) ?>
                </strong>

            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     FILTROS
====================================================== -->

<section class="filter-section">

    <div class="filter-title">

        <h3>
            Historial de registros
        </h3>

        <span>

            <?= formatoNumero(
                $totalFiltrado
            ) ?>

            resultados encontrados

        </span>

    </div>

    <form
        method="GET"
        class="filters"
    >

        <div class="field search">

            <label>
                ESTUDIANTE
            </label>

            <input
                type="text"
                name="busqueda"
                placeholder="Nombre, apellido o documento..."
                value="<?= htmlspecialchars(
                    $busqueda
                ) ?>"
            >

        </div>

        <div class="field">

            <label>
                FECHA
            </label>

            <input
                type="date"
                name="fecha"
                value="<?= htmlspecialchars(
                    $fechaFiltro
                ) ?>"
            >

        </div>

        <div class="field">

            <label>
                ESTADO
            </label>

            <select name="estado">

                <option value="">
                    Todos
                </option>

                <option
                    value="REGISTRADO"
                    <?= $estadoFiltro === 'REGISTRADO'
                        ? 'selected'
                        : '' ?>
                >
                    Registrado
                </option>

                <option
                    value="NO_REGISTRADO"
                    <?= $estadoFiltro === 'NO_REGISTRADO'
                        ? 'selected'
                        : '' ?>
                >
                    No registrado
                </option>

            </select>

        </div>

        <button
            type="submit"
            class="btn"
        >

            <i class="bi bi-search"></i>

            Buscar

        </button>

        <a
            href="restaurante.php"
            class="btn secondary"
        >

            <i class="bi bi-arrow-counterclockwise"></i>

            Limpiar

        </a>

    </form>

</section>

<!-- =====================================================
     TABLA
====================================================== -->

<section class="table-card">

    <div class="table-header">

        <h3>
            Registros del restaurante
        </h3>

        <span>

            <i class="bi bi-database-check"></i>

            Base de datos conectada

        </span>

    </div>

    <div class="table-wrapper">

        <?php if (
            $resultadoHistorial &&
            mysqli_num_rows(
                $resultadoHistorial
            ) > 0
        ): ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            ESTUDIANTE
                        </th>

                        <th>
                            DOCUMENTO
                        </th>

                        <th>
                            CURSO
                        </th>

                        <th>
                            FECHA
                        </th>

                        <th>
                            HORA
                        </th>

                        <th>
                            ESTADO
                        </th>

                        <th>
                            OBSERVACIÓN
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php while (
                    $registro =
                    mysqli_fetch_assoc(
                        $resultadoHistorial
                    )
                ): ?>

                    <?php

                    $nombreCompleto =
                        trim(
                            $registro['nombres']
                            . ' '
                            .
                            $registro['apellidos']
                        );

                    $inicialEstudiante =
                        strtoupper(
                            substr(
                                $registro['nombres'],
                                0,
                                1
                            )
                        );

                    $esRegistrado =
                        $registro['estado']
                        ===
                        'REGISTRADO';

                    ?>

                    <tr>

                        <!-- ESTUDIANTE -->

                        <td>

                            <div class="student">

                                <div class="student-avatar">

                                    <?= htmlspecialchars(
                                        $inicialEstudiante
                                    ) ?>

                                </div>

                                <div class="student-name">

                                    <strong>

                                        <?= htmlspecialchars(
                                            $nombreCompleto
                                        ) ?>

                                    </strong>

                                    <small>

                                        ID:
                                        <?= htmlspecialchars(
                                            $registro[
                                                'id_estudiante'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        </td>

                        <!-- DOCUMENTO -->

                        <td>

                            <?= htmlspecialchars(
                                $registro[
                                    'documento'
                                ]
                            ) ?>

                        </td>

                        <!-- CURSO -->

                        <td>

                            <span class="course">

                                <?= htmlspecialchars(
                                    $registro[
                                        'nombre_curso'
                                    ]
                                    ??
                                    'Sin curso'
                                ) ?>

                            </span>

                        </td>

                        <!-- FECHA -->

                        <td>

                            <?= date(
                                'd/m/Y',
                                strtotime(
                                    $registro[
                                        'fecha'
                                    ]
                                )
                            ) ?>

                        </td>

                        <!-- HORA -->

                        <td>

                            <span class="time">

                                <?= htmlspecialchars(
                                    date(
                                        'h:i:s A',
                                        strtotime(
                                            $registro[
                                                'hora'
                                            ]
                                        )
                                    )
                                ) ?>

                            </span>

                        </td>

                        <!-- ESTADO -->

                        <td>

                            <?php if (
                                $esRegistrado
                            ): ?>

                                <span
                                    class="
                                        badge
                                        registered
                                    "
                                >

                                    <i class="
                                        bi
                                        bi-check-circle-fill
                                    "></i>

                                    REGISTRADO

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        badge
                                        not-registered
                                    "
                                >

                                    <i class="
                                        bi
                                        bi-dash-circle-fill
                                    "></i>

                                    NO REGISTRADO

                                </span>

                            <?php endif; ?>

                        </td>

                        <!-- OBSERVACIÓN -->

                        <td>

                            <div
                                class="observation"
                                title="<?= htmlspecialchars(
                                    $registro[
                                        'observacion'
                                    ]
                                    ??
                                    'Sin observación'
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $registro[
                                        'observacion'
                                    ]
                                    ??
                                    'Sin observación'
                                ) ?>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">

                <i class="bi bi-inbox"></i>

                <strong>
                    No encontramos registros
                </strong>

                <span>
                    Prueba cambiando los filtros de búsqueda.
                </span>

            </div>

        <?php endif; ?>

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