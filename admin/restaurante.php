<?php
session_start();

/* =========================================================
   PROTECCIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


/* =========================================================
   DATOS DEL USUARIO
========================================================= */

$nombreUsuario = $_SESSION['nombre'] ?? 'Administrador';

$partesNombre = explode(' ', trim($nombreUsuario));

$primerNombre = $partesNombre[0] ?? 'Administrador';

$inicial = strtoupper(
    substr($primerNombre, 0, 1)
);


/* =========================================================
   FILTROS
========================================================= */

$fechaFiltro = $_GET['fecha'] ?? '';

$estadoFiltro = $_GET['estado'] ?? '';

$busqueda = trim($_GET['busqueda'] ?? '');


/* =========================================================
   FUNCIÓN SEGURA
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


/* TOTAL */

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
";

$resultado = mysqli_query(
    $conexion,
    $sqlTotal
);

if ($resultado) {

    $fila = mysqli_fetch_assoc($resultado);

    $totalRegistros =
        (int)($fila['total'] ?? 0);
}


/* HOY */

$hoy = date('Y-m-d');

$sqlHoy = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
    WHERE fecha = '$hoy'
";

$resultado = mysqli_query(
    $conexion,
    $sqlHoy
);

if ($resultado) {

    $fila = mysqli_fetch_assoc($resultado);

    $totalHoy =
        (int)($fila['total'] ?? 0);
}


/* REGISTRADOS */

$sqlRegistrados = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
    WHERE estado = 'REGISTRADO'
";

$resultado = mysqli_query(
    $conexion,
    $sqlRegistrados
);

if ($resultado) {

    $fila = mysqli_fetch_assoc($resultado);

    $totalRegistrados =
        (int)($fila['total'] ?? 0);
}


/* NO REGISTRADOS */

$sqlNoRegistrados = "
    SELECT COUNT(*) AS total
    FROM asistencia_restaurante
    WHERE estado = 'NO_REGISTRADO'
";

$resultado = mysqli_query(
    $conexion,
    $sqlNoRegistrados
);

if ($resultado) {

    $fila = mysqli_fetch_assoc($resultado);

    $totalNoRegistrados =
        (int)($fila['total'] ?? 0);
}


/* =========================================================
   ESTADÍSTICA ÚLTIMOS 7 DÍAS
========================================================= */

$nombresDias = [
    'Domingo',
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado'
];

$estadisticas = [];

$maxRegistros = 1;

$totalSemana = 0;


for ($i = 6; $i >= 0; $i--) {

    $fecha = date(
        'Y-m-d',
        strtotime("-$i days")
    );

    $diaNombre =
        $nombresDias[
            (int)date(
                'w',
                strtotime($fecha)
            )
        ];

    $diaCorto =
        mb_substr(
            $diaNombre,
            0,
            3,
            'UTF-8'
        );

    $diaCorto =
        ucfirst($diaCorto);


    $sqlDia = "
        SELECT COUNT(*) AS total
        FROM asistencia_restaurante
        WHERE fecha = '$fecha'
        AND estado = 'REGISTRADO'
    ";

    $resultadoDia =
        mysqli_query(
            $conexion,
            $sqlDia
        );

    $cantidad = 0;

    if ($resultadoDia) {

        $filaDia =
            mysqli_fetch_assoc(
                $resultadoDia
            );

        $cantidad =
            (int)(
                $filaDia['total'] ?? 0
            );
    }


    if ($cantidad > $maxRegistros) {

        $maxRegistros =
            $cantidad;
    }


    $totalSemana += $cantidad;


    $estadisticas[] = [
        'fecha' => $fecha,
        'dia' => $diaCorto,
        'cantidad' => $cantidad
    ];
}


/* =========================================================
   CONSULTA DEL HISTORIAL
========================================================= */

$condiciones = [];


/* FILTRO FECHA */

if ($fechaFiltro !== '') {

    $fechaSegura =
        mysqli_real_escape_string(
            $conexion,
            $fechaFiltro
        );

    $condiciones[] =
        "ar.fecha = '$fechaSegura'";
}


/* FILTRO ESTADO */

if (
    $estadoFiltro === 'REGISTRADO' ||
    $estadoFiltro === 'NO_REGISTRADO'
) {

    $estadoSegura =
        mysqli_real_escape_string(
            $conexion,
            $estadoFiltro
        );

    $condiciones[] =
        "ar.estado = '$estadoSegura'";
}


/* BÚSQUEDA */

if ($busqueda !== '') {

    $busquedaSegura =
        mysqli_real_escape_string(
            $conexion,
            $busqueda
        );

    $condiciones[] = "
        (
            e.nombres LIKE '%$busquedaSegura%'
            OR e.apellidos LIKE '%$busquedaSegura%'
            OR e.documento LIKE '%$busquedaSegura%'
        )
    ";
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

        e.id_curso

    FROM asistencia_restaurante ar

    INNER JOIN estudiantes e

        ON e.id_estudiante =
           ar.id_estudiante

    $where

    ORDER BY
        ar.fecha DESC,
        ar.hora DESC

    LIMIT 100
";


$resultadoHistorial =
    mysqli_query(
        $conexion,
        $sqlHistorial
    );


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

$resultadoFiltrado =
    mysqli_query(
        $conexion,
        $sqlFiltrado
    );

if ($resultadoFiltrado) {

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

/* =========================================================
   VARIABLES
========================================================= */

:root{

    --aqua:#18d8ce;

    --aqua-dark:#087d92;

    --mint:#42cda1;

    --blue:#69b8d5;

    --purple:#8579d2;

    --gold:#d1a158;

    --coral:#e99a78;

    --red:#d87983;

    --text:#3e6f7d;

    --dark:#20596d;

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
            circle at 7% 8%,
            rgba(24,216,206,.14),
            transparent 27%
        ),

        radial-gradient(
            circle at 94% 90%,
            rgba(133,121,210,.12),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            #e8faf7,
            #f8fdfc 48%,
            #eaf6fb
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
            rgba(255,255,255,.88),
            rgba(232,250,247,.70)
        );

    backdrop-filter:
        blur(25px);

    box-shadow:
        0 25px 65px
        rgba(55,113,129,.10);

}


/* =========================================================
   LOGO
========================================================= */

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

    border-radius:19px;

    background:
        rgba(255,255,255,.76);

    border:
        1px solid
        rgba(255,255,255,.95);

}


.logo-container img{

    width:100%;

    height:100%;

    object-fit:contain;

}


.sidebar-title strong{

    display:block;

    color:#075273;

    font-size:17px;

    font-weight:950;

    letter-spacing:.5px;

}


.sidebar-title small{

    display:block;

    margin-top:6px;

    color:#7898a1;

    font-size:10px;

    font-weight:750;

}


/* =========================================================
   LÍNEA
========================================================= */

.sidebar-line{

    height:1px;

    margin:
        0 9px 16px;

    background:
        rgba(50,111,130,.09);

}


/* =========================================================
   MENÚ
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

    min-height:27px;

    padding:
        0 11px;

    color:#7d9aa3;

    font-size:9px;

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

    min-height:51px;

    margin-bottom:4px;

    padding:
        6px 10px;

    border-radius:15px;

    color:#557f8b;

    text-decoration:none;

    font-size:12px;

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

    width:38px;

    height:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

    border-radius:12px;

    color:#4b98a8;

    background:
        rgba(24,216,206,.075);

    font-size:18px;

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


.nav-icon.restaurant{

    color:#d19a46;

    background:
        rgba(209,161,88,.13);

}


.nav-icon.reports{

    color:#bd8a40;

    background:
        rgba(209,161,88,.12);

}


.nav-icon.audit{

    color:#7569c2;

    background:
        rgba(133,121,210,.11);

}


.nav-arrow{

    margin-left:auto;

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

    gap:9px;

    padding:
        9px 10px;

    border-radius:15px;

    background:
        rgba(255,255,255,.54);

}


.profile-avatar{

    width:39px;

    height:39px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #52dca9,
            #15966f
        );

    font-size:13px;

    font-weight:950;

}


.profile-info{

    flex:1;

}


.profile-info strong{

    display:block;

    color:#4d7c89;

    font-size:11px;

}


.profile-info small{

    color:#8ca6ad;

    font-size:9px;

}


.profile-status{

    color:#27b884;

    animation:
        pulse 2s infinite;

}


.logout{

    display:flex;

    align-items:center;

    gap:9px;

    min-height:45px;

    padding:
        0 10px;

    color:#b86e77;

    text-decoration:none;

    font-size:11px;

    font-weight:850;

}


.logout-icon{

    width:33px;

    height:33px;

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

    gap:16px;

}


/* =========================================================
   TOPBAR
========================================================= */

.topbar{

    min-height:78px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        14px 22px;

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

    height:43px;

    border-radius:7px;

    background:
        linear-gradient(
            180deg,
            var(--gold),
            var(--coral)
        );

}


.page-title h1{

    color:#15576c;

    font-size:23px;

    font-weight:950;

}


.page-title p{

    margin-top:4px;

    color:#7898a2;

    font-size:11px;

    font-weight:650;

}


/* =========================================================
   CLOCK
========================================================= */

.clock-box{

    text-align:right;

}


.clock{

    color:#155b70;

    font-size:20px;

    font-weight:950;

    letter-spacing:1px;

}


.clock-date{

    margin-top:2px;

    color:#819ba3;

    font-size:9px;

    font-weight:750;

}


/* =========================================================
   HERO
========================================================= */

.hero{

    position:relative;

    overflow:hidden;

    min-height:205px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        30px 36px;

    border:
        1px solid
        rgba(255,255,255,.93);

    border-radius:29px;

    background:

        radial-gradient(
            ellipse at 88% 40%,
            rgba(209,161,88,.18),
            transparent 28%
        ),

        radial-gradient(
            ellipse at 65% 100%,
            rgba(24,216,206,.11),
            transparent 32%
        ),

        linear-gradient(
            120deg,
            rgba(255,255,255,.84),
            rgba(235,250,247,.68)
        );

    box-shadow:
        0 25px 60px
        rgba(55,113,129,.08);

}


.hero-content{

    position:relative;

    z-index:2;

}


.eyebrow{

    display:inline-flex;

    align-items:center;

    gap:8px;

    margin-bottom:11px;

    padding:
        7px 12px;

    border-radius:20px;

    color:#aa7833;

    background:
        rgba(209,161,88,.10);

    font-size:9px;

    font-weight:950;

    letter-spacing:1px;

}


.eyebrow span{

    width:6px;

    height:6px;

    border-radius:50%;

    background:#d1a158;

    animation:
        pulse 1.8s infinite;

}


.hero h2{

    color:#15566b;

    font-size:35px;

    font-weight:950;

}


.hero h2 span{

    color:#c08c3f;

}


.hero p{

    max-width:720px;

    margin-top:10px;

    color:#648591;

    font-size:12px;

    line-height:1.7;

    font-weight:650;

}


/* =========================================================
   HERO VISUAL
========================================================= */

.hero-visual{

    position:relative;

    width:190px;

    height:145px;

    flex-shrink:0;

}


.plate{

    position:absolute;

    width:105px;

    height:105px;

    top:20px;

    left:35px;

    display:flex;

    align-items:center;

    justify-content:center;

    border:
        8px solid
        rgba(255,255,255,.75);

    border-radius:50%;

    background:
        linear-gradient(
            145deg,
            #eafbf6,
            #dff5f3
        );

    box-shadow:
        0 20px 35px
        rgba(55,113,129,.12);

    animation:
        float 4s ease-in-out infinite;

}


.plate i{

    color:#d19a46;

    font-size:42px;

}


.orbit{

    position:absolute;

    width:135px;

    height:135px;

    top:5px;

    left:20px;

    border:
        1px dashed
        rgba(209,161,88,.30);

    border-radius:50%;

    animation:
        spin 13s linear infinite;

}


.orbit-dot{

    position:absolute;

    width:8px;

    height:8px;

    top:11px;

    left:63px;

    border-radius:50%;

    background:#d1a158;

    box-shadow:
        0 0 15px
        rgba(209,161,88,.55);

}


/* =========================================================
   CARDS
========================================================= */

.cards{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:14px;

}


.card{

    position:relative;

    overflow:hidden;

    min-height:155px;

    padding:18px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:21px;

    background:
        rgba(255,255,255,.72);

    box-shadow:
        0 15px 38px
        rgba(55,113,129,.065);

    transition:.3s;

}


.card:hover{

    transform:
        translateY(-5px);

}


.card-icon{

    width:43px;

    height:43px;

    display:flex;

    align-items:center;

    justify-content:center;

    margin-bottom:10px;

    border-radius:13px;

    font-size:19px;

}


.card strong{

    display:block;

    color:#155b70;

    font-size:30px;

    font-weight:950;

}


.card span{

    display:block;

    margin-top:3px;

    color:#63838d;

    font-size:10px;

    font-weight:800;

}


.card.mint .card-icon{

    color:#2caf87;

    background:
        rgba(66,205,161,.12);

}


.card.blue .card-icon{

    color:#559fc0;

    background:
        rgba(105,184,213,.12);

}


.card.gold .card-icon{

    color:#c28c3d;

    background:
        rgba(209,161,88,.13);

}


.card.coral .card-icon{

    color:#d87f60;

    background:
        rgba(233,154,120,.13);

}


/* =========================================================
   SECTION HEADER
========================================================= */

.section-heading{

    display:flex;

    align-items:end;

    justify-content:space-between;

    margin:
        1px 5px -1px;

}


.section-heading h3{

    color:#386b7b;

    font-size:18px;

    font-weight:950;

}


.section-heading span{

    color:#7898a2;

    font-size:10px;

    font-weight:750;

}


/* =========================================================
   ANALYTICS
========================================================= */

.analytics{

    display:grid;

    grid-template-columns:
        minmax(0,2fr)
        minmax(260px,1fr);

    gap:14px;

}


.panel{

    padding:20px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:23px;

    background:
        rgba(255,255,255,.72);

    box-shadow:
        0 15px 38px
        rgba(55,113,129,.06);

}


/* =========================================================
   GRAPH
========================================================= */

.panel-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

}


.panel-header strong{

    color:#416f7e;

    font-size:13px;

    font-weight:950;

}


.panel-header small{

    display:block;

    margin-top:3px;

    color:#849da5;

    font-size:9px;

}


.week-total{

    color:#155b70;

    font-size:22px;

    font-weight:950;

}


.chart{

    height:175px;

    display:flex;

    align-items:flex-end;

    gap:12px;

    margin-top:20px;

    padding:
        0 5px;

    border-bottom:
        1px solid
        rgba(66,111,125,.12);

}


.column{

    flex:1;

    height:100%;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:flex-end;

    gap:6px;

}


.value{

    color:#648691;

    font-size:8px;

    font-weight:850;

}


.bar-area{

    width:100%;

    height:130px;

    display:flex;

    align-items:flex-end;

    justify-content:center;

}


.bar{

    width:min(32px,70%);

    min-height:4px;

    border-radius:
        9px 9px 3px 3px;

    background:
        linear-gradient(
            180deg,
            #d9b05e,
            #6bc5ba
        );

    animation:
        grow .8s ease-out both;

}


.day{

    color:#76929c;

    font-size:9px;

    font-weight:850;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary-list{

    margin-top:15px;

}


.summary-item{

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        12px 3px;

    border-bottom:
        1px solid
        rgba(66,111,125,.08);

}


.summary-item:last-child{

    border-bottom:0;

}


.summary-left{

    display:flex;

    align-items:center;

    gap:9px;

    color:#63838d;

    font-size:10px;

    font-weight:800;

}


.summary-left i{

    font-size:14px;

}


.summary-item strong{

    color:#406f7e;

    font-size:12px;

    font-weight:950;

}


/* =========================================================
   FILTERS
========================================================= */

.filters{

    display:flex;

    align-items:end;

    flex-wrap:wrap;

    gap:10px;

    padding:16px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:22px;

    background:
        rgba(255,255,255,.70);

    box-shadow:
        0 14px 35px
        rgba(55,113,129,.05);

}


.field{

    display:flex;

    flex-direction:column;

    gap:5px;

}


.field label{

    color:#6f909a;

    font-size:9px;

    font-weight:900;

}


.field input,
.field select{

    height:39px;

    min-width:145px;

    padding:
        0 11px;

    border:
        1px solid
        rgba(112,158,168,.16);

    outline:none;

    border-radius:11px;

    color:#4f7986;

    background:
        rgba(255,255,255,.78);

    font-family:inherit;

    font-size:10px;

    font-weight:700;

}


.field input:focus,
.field select:focus{

    border-color:
        rgba(24,216,206,.45);

}


.field.search{

    flex:1;

}


.field.search input{

    width:100%;

}


.btn{

    height:39px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        0 15px;

    border:0;

    border-radius:11px;

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

.table-panel{

    padding:18px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:23px;

    background:
        rgba(255,255,255,.72);

    box-shadow:
        0 15px 38px
        rgba(55,113,129,.06);

}


.table-top{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:13px;

}


.table-top strong{

    color:#416f7e;

    font-size:13px;

    font-weight:950;

}


.table-top span{

    padding:
        6px 9px;

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

    min-width:760px;

    border-collapse:collapse;

}


thead th{

    padding:
        10px 9px;

    text-align:left;

    color:#73939c;

    background:
        rgba(232,250,247,.52);

    font-size:9px;

    font-weight:950;

    letter-spacing:.3px;

}


thead th:first-child{

    border-radius:10px 0 0 10px;

}


thead th:last-child{

    border-radius:0 10px 10px 0;

}


tbody td{

    padding:
        12px 9px;

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

    width:32px;

    height:32px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

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


.badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        5px 8px;

    border-radius:9px;

    font-size:8px;

    font-weight:950;

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

    max-width:180px;

    overflow:hidden;

    white-space:nowrap;

    text-overflow:ellipsis;

}


/* =========================================================
   EMPTY
========================================================= */

.empty{

    padding:35px;

    text-align:center;

}


.empty i{

    display:block;

    margin-bottom:10px;

    color:#91c8c4;

    font-size:32px;

}


.empty strong{

    display:block;

    color:#557d89;

    font-size:12px;

}


.empty span{

    display:block;

    margin-top:4px;

    color:#91a8ae;

    font-size:9px;

}


/* =========================================================
   ANIMATIONS
========================================================= */

@keyframes pulse{

    0%,100%{

        opacity:.45;

    }

    50%{

        opacity:1;

    }

}


@keyframes float{

    0%,100%{

        transform:translateY(0);

    }

    50%{

        transform:translateY(-7px);

    }

}


@keyframes spin{

    to{

        transform:rotate(360deg);

    }

}


@keyframes grow{

    from{

        transform:scaleY(0);

    }

    to{

        transform:scaleY(1);

    }

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1250px){

    .sidebar{

        width:265px;

    }

    .cards{

        grid-template-columns:
            repeat(2,1fr);

    }

}


@media(max-width:1000px){

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

    .sidebar-bottom{

        display:none;

    }

    .hero-visual{

        display:none;

    }

    .analytics{

        grid-template-columns:1fr;

    }

}


@media(max-width:700px){

    .topbar{

        align-items:flex-start;

        flex-direction:column;

    }

    .clock-box{

        text-align:left;

    }

    .navigation{

        grid-template-columns:1fr;

    }

    .hero{

        padding:25px;

    }

    .hero h2{

        font-size:29px;

    }

    .cards{

        grid-template-columns:1fr;

    }

    .filters{

        align-items:stretch;

        flex-direction:column;

    }

    .field input,
    .field select,
    .btn{

        width:100%;

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


    <div class="sidebar-line"></div>


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
                    Cursos + estudiantes
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
                    Asistencia QR
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


            <a
                href="auditoria.php"
                class="nav-link"
            >

                <div class="nav-icon audit">

                    <i class="bi bi-shield-check"></i>

                </div>

                <span>
                    Auditoría
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

                <?= htmlspecialchars($inicial) ?>

            </div>


            <div class="profile-info">

                <strong>
                    <?= htmlspecialchars($primerNombre) ?>
                </strong>

                <small>
                    Administrador
                </small>

            </div>


            <div class="profile-status">
                ●
            </div>

        </div>


        <a
            href="../logout.php"
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

            Cerrar sesión

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
                Control de restaurante
            </h1>

            <p>
                Gestión y seguimiento del servicio de alimentación
            </p>

        </div>

    </div>


    <div class="clock-box">

        <div
            class="clock"
            id="liveClock"
        >
            --:--:--
        </div>

        <div class="clock-date">

            <?= date('d/m/Y') ?>

        </div>

    </div>


</header>


<!-- HERO -->

<section class="hero">


    <div class="hero-content">


        <div class="eyebrow">

            <span></span>

            SERVICIO DE ALIMENTACIÓN

        </div>


        <h2>

            Control de
            <span>
                restaurante
            </span>

        </h2>


        <p>

            Supervisa los registros de alimentación
            de los estudiantes, consulta el historial
            y analiza la actividad del servicio desde
            un único espacio administrativo.

        </p>

    </div>


    <div class="hero-visual">

        <div class="orbit">

            <span class="orbit-dot"></span>

        </div>


        <div class="plate">

            <i class="bi bi-egg-fried"></i>

        </div>

    </div>


</section>


<!-- INDICADORES -->

<div class="section-heading">

    <h3>
        Resumen del servicio
    </h3>

    <span>
        Información actualizada
    </span>

</div>


<section class="cards">


    <div class="card mint">

        <div class="card-icon">

            <i class="bi bi-clipboard2-check"></i>

        </div>

        <strong>
            <?= formatoNumero($totalRegistros) ?>
        </strong>

        <span>
            Registros totales
        </span>

    </div>


    <div class="card blue">

        <div class="card-icon">

            <i class="bi bi-calendar-check"></i>

        </div>

        <strong>
            <?= formatoNumero($totalHoy) ?>
        </strong>

        <span>
            Registros de hoy
        </span>

    </div>


    <div class="card gold">

        <div class="card-icon">

            <i class="bi bi-check-circle"></i>

        </div>

        <strong>
            <?= formatoNumero($totalRegistrados) ?>
        </strong>

        <span>
            Servicios registrados
        </span>

    </div>


    <div class="card coral">

        <div class="card-icon">

            <i class="bi bi-dash-circle"></i>

        </div>

        <strong>
            <?= formatoNumero($totalNoRegistrados) ?>
        </strong>

        <span>
            No registrados
        </span>

    </div>


</section>


<!-- ANALÍTICA -->

<div class="section-heading">

    <h3>
        Actividad del restaurante
    </h3>

    <span>
        Últimos 7 días
    </span>

</div>


<section class="analytics">


    <div class="panel">


        <div class="panel-header">

            <div>

                <strong>
                    Consumo semanal
                </strong>

                <small>
                    Registros con estado registrado
                </small>

            </div>


            <div>

                <div class="week-total">

                    <?= formatoNumero($totalSemana) ?>

                </div>

            </div>

        </div>


        <div class="chart">


            <?php foreach (
                $estadisticas
                as $dato
            ): ?>


                <?php

                $altura = 0;

                if ($maxRegistros > 0) {

                    $altura =
                        (
                            $dato['cantidad']
                            /
                            $maxRegistros
                        ) * 100;
                }

                if (
                    $dato['cantidad'] > 0
                    &&
                    $altura < 7
                ) {

                    $altura = 7;
                }

                ?>


                <div class="column">


                    <div class="value">

                        <?= formatoNumero(
                            $dato['cantidad']
                        ) ?>

                    </div>


                    <div class="bar-area">

                        <div
                            class="bar"
                            style="
                                height:
                                <?= $altura ?>%;
                            "
                        ></div>

                    </div>


                    <div class="day">

                        <?= htmlspecialchars(
                            $dato['dia']
                        ) ?>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    </div>


    <div class="panel">


        <div class="panel-header">

            <div>

                <strong>
                    Estado del servicio
                </strong>

                <small>
                    Resumen actual
                </small>

            </div>

        </div>


        <div class="summary-list">


            <div class="summary-item">

                <div class="summary-left">

                    <i class="bi bi-people"></i>

                    Registros de hoy

                </div>

                <strong>

                    <?= formatoNumero(
                        $totalHoy
                    ) ?>

                </strong>

            </div>


            <div class="summary-item">

                <div class="summary-left">

                    <i class="bi bi-check2-circle"></i>

                    Registrados

                </div>

                <strong>

                    <?= formatoNumero(
                        $totalRegistrados
                    ) ?>

                </strong>

            </div>


            <div class="summary-item">

                <div class="summary-left">

                    <i class="bi bi-x-circle"></i>

                    No registrados

                </div>

                <strong>

                    <?= formatoNumero(
                        $totalNoRegistrados
                    ) ?>

                </strong>

            </div>


            <div class="summary-item">

                <div class="summary-left">

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


<!-- FILTROS -->

<div class="section-heading">

    <h3>
        Historial de registros
    </h3>

    <span>
        <?= formatoNumero($totalFiltrado) ?>
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


<!-- TABLA -->

<section class="table-panel">


    <div class="table-top">

        <strong>
            Registros del restaurante
        </strong>

        <span>

            <i class="bi bi-database-check"></i>

            Base de datos conectada

        </span>

    </div>


    <div class="table-wrapper">


        <?php if (
            $resultadoHistorial
            &&
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


                        <td>

                            <?= htmlspecialchars(
                                $registro[
                                    'documento'
                                ]
                            ) ?>

                        </td>


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


                        <td>

                            <?= htmlspecialchars(
                                $registro[
                                    'hora'
                                ]
                            ) ?>

                        </td>


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
   RELOJ EN TIEMPO REAL
========================================================= */

function actualizarHora(){

    const ahora = new Date();

    const horas =
        String(
            ahora.getHours()
        ).padStart(2,'0');

    const minutos =
        String(
            ahora.getMinutes()
        ).padStart(2,'0');

    const segundos =
        String(
            ahora.getSeconds()
        ).padStart(2,'0');


    const reloj =
        document.getElementById(
            'liveClock'
        );


    if(reloj){

        reloj.textContent =
            `${horas}:${minutos}:${segundos}`;

    }

}


actualizarHora();

setInterval(
    actualizarHora,
    1000
);

</script>


</body>

</html>