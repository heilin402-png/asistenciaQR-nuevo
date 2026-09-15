<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SESSION["id_rol"] != 3) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/conexion.php";
date_default_timezone_set('America/Bogota');

$nombreUsuario = $_SESSION["nombre"] ?? "Usuario Restaurante";
$apellidoUsuario = $_SESSION["apellido"] ?? "";


$nombreCompleto = trim($nombreUsuario . " " . $apellidoUsuario);

$partesNombre = preg_split('/\s+/', trim($nombreCompleto));
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
   FILTROS
========================================================= */

$fechaInicio = $_GET["fecha_inicio"] ?? $fechaHoy;
$fechaFin = $_GET["fecha_fin"] ?? $fechaHoy;
$documento = trim($_GET["documento"] ?? "");

$registros = [];
$error = "";

if ($fechaInicio !== "" && $fechaFin !== "" && $fechaInicio > $fechaFin) {

    $error = "La fecha inicial no puede ser mayor que la fecha final.";

} else {

    $sql = "
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
            ON ar.id_estudiante = e.id_estudiante
        WHERE 1=1
    ";

    $parametros = [];
    $tipos = "";

    if ($fechaInicio !== "") {
        $sql .= " AND ar.fecha >= ?";
        $parametros[] = $fechaInicio;
        $tipos .= "s";
    }

    if ($fechaFin !== "") {
        $sql .= " AND ar.fecha <= ?";
        $parametros[] = $fechaFin;
        $tipos .= "s";
    }

    if ($documento !== "") {
        $sql .= " AND e.documento LIKE ?";
        $parametros[] = "%" . $documento . "%";
        $tipos .= "s";
    }

    $sql .= " ORDER BY ar.fecha DESC, ar.hora DESC";

    $stmt = mysqli_prepare($conexion, $sql);

    if ($stmt) {

        if (!empty($parametros)) {
            mysqli_stmt_bind_param($stmt, $tipos, ...$parametros);
        }

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);

        while ($fila = mysqli_fetch_assoc($resultado)) {
            $registros[] = $fila;
        }

        mysqli_stmt_close($stmt);

    } else {

        $error = "No fue posible generar el reporte.";

    }
}

/* =========================================================
   RESUMEN
========================================================= */

$totalRegistros = count($registros);

$estudiantesUnicos = [];

foreach ($registros as $registro) {
    $estudiantesUnicos[$registro["id_estudiante"]] = true;
}

$totalEstudiantesReporte = count($estudiantesUnicos);

$totalDias = 0;

if (
    $fechaInicio !== "" &&
    $fechaFin !== "" &&
    $fechaInicio <= $fechaFin
) {

    $inicio = new DateTime($fechaInicio);
    $fin = new DateTime($fechaFin);

    $totalDias = $inicio->diff($fin)->days + 1;
}

$promedioDiario = $totalDias > 0
    ? round($totalRegistros / $totalDias, 1)
    : 0;

function fechaBonita($fecha)
{
    if (!$fecha) {
        return "";
    }

    $partes = explode("-", $fecha);

    if (count($partes) !== 3) {
        return $fecha;
    }

    return $partes[2] . "/" . $partes[1] . "/" . $partes[0];
}

function inicialesEstudiante($nombre)
{
    $partes = preg_split('/\s+/', trim($nombre));
    $iniciales = "";

    foreach (array_slice($partes, 0, 2) as $parte) {
        if ($parte !== "") {
            $iniciales .= strtoupper(substr($parte, 0, 1));
        }
    }

    return $iniciales ?: "ES";
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
    Reportes | Asistencia QR
</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
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
    --yellow:#f0b84d;
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

/* =========================================================
   CABECERA SIDEBAR
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

    padding:10px 11px;

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
            #f0bd57,
            #d28c20
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
    padding:0 10px;

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
            var(--yellow),
            var(--aqua)
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
   CABECERA DEL REPORTE
========================================================= */

.report-header{

    position:relative;
    overflow:hidden;

    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:25px;

    min-height:155px;

    padding:
        27px 32px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:

        radial-gradient(
            circle at 86% 20%,
            rgba(245,190,70,.15),
            transparent 27%
        ),

        radial-gradient(
            circle at 65% 115%,
            rgba(133,121,210,.12),
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

.report-header::after{

    content:"";

    position:absolute;

    width:230px;
    height:230px;

    right:-80px;
    top:-135px;

    border-radius:50%;

    border:
        35px solid
        rgba(245,190,70,.055);

}

.report-header-content{
    position:relative;
    z-index:2;
}

.report-tag{

    display:inline-flex;
    align-items:center;
    gap:7px;

    margin-bottom:10px;
    padding:7px 12px;

    border-radius:10px;

    color:#b47a16;

    background:
        rgba(245,190,70,.11);

    font-size:10px;
    font-weight:950;

    letter-spacing:.7px;

}

.report-header h2{

    color:#15576c;

    font-size:30px;
    font-weight:950;

}

.report-header p{

    max-width:760px;

    margin-top:8px;

    color:#7898a2;

    font-size:13px;
    font-weight:650;

    line-height:1.6;

}

.report-symbol{

    position:relative;
    z-index:2;

    width:105px;
    height:105px;

    display:flex;
    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:28px;

    color:#bd8a40;

    background:
        linear-gradient(
            145deg,
            rgba(245,190,70,.15),
            rgba(24,216,206,.12)
        );

    border:
        1px solid
        rgba(255,255,255,.85);

    box-shadow:
        0 18px 35px
        rgba(55,113,129,.09);

    font-size:48px;

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
    overflow:hidden;

    min-height:120px;

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
        rgba(24,216,206,.055);

}

.summary-item:hover{

    transform:
        translateY(-5px);

    box-shadow:
        0 24px 48px
        rgba(55,113,129,.10);

}

.summary-icon{

    width:60px;
    height:60px;

    display:flex;
    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:18px;

    color:#0a9995;

    background:
        rgba(24,216,206,.10);

    font-size:27px;

}

.summary-item:nth-child(2) .summary-icon{

    color:#d99a24;

    background:
        rgba(245,190,70,.12);

}

.summary-item:nth-child(3) .summary-icon{

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

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
   FILTROS
========================================================= */

.filter-card{

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

    margin-bottom:18px;

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

.filter-grid{

    display:grid;

    grid-template-columns:
        1.15fr
        1fr
        1fr
        auto;

    gap:12px;

    align-items:end;

}

.field label{

    display:block;

    margin:
        0 0 7px 3px;

    color:#7d9aa3;

    font-size:10px;
    font-weight:950;

    letter-spacing:.8px;

}

.field input{

    width:100%;
    height:45px;

    padding:
        0 13px;

    color:#416f7e;

    border:
        1px solid
        #dcecee;

    border-radius:13px;

    outline:none;

    background:
        rgba(255,255,255,.80);

    font-size:12px;
    font-weight:650;

    transition:.2s;

}

.field input:focus{

    border-color:
        rgba(24,216,206,.65);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.08);

}

.filter-actions{

    display:flex;
    gap:8px;

}

.btn{

    min-height:45px;

    display:inline-flex;
    align-items:center;
    justify-content:center;

    gap:7px;

    padding:
        0 16px;

    border:0;
    border-radius:13px;

    cursor:pointer;

    text-decoration:none;

    white-space:nowrap;

    font-size:11px;
    font-weight:900;

    transition:.25s;

}

.btn-primary{

    color:#fff;

    background:
        linear-gradient(
            135deg,
            var(--aqua-dark),
            var(--aqua)
        );

    box-shadow:
        0 9px 20px
        rgba(24,216,206,.18);

}

.btn-primary:hover{

    transform:
        translateY(-2px);

}

.btn-light{

    color:#557f8b;

    background:
        rgba(255,255,255,.72);

    border:
        1px solid
        rgba(255,255,255,.9);

}

.btn-light:hover{

    color:#075273;

    transform:
        translateY(-2px);

}

.error{

    margin-top:14px;

    padding:
        11px 14px;

    border-radius:13px;

    color:#b86e77;

    background:
        rgba(242,143,150,.08);

    font-size:12px;
    font-weight:750;

}

/* =========================================================
   TABLA
========================================================= */

.table-card{

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

.table-heading{

    display:flex;

    align-items:center;
    justify-content:space-between;

    gap:15px;

    margin-bottom:17px;

}

.table-heading h3{

    color:#416f7e;

    font-size:18px;
    font-weight:950;

}

.table-heading p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;
    font-weight:650;

}

.result-badge{

    display:flex;
    align-items:center;
    gap:7px;

    padding:
        8px 11px;

    border-radius:10px;

    color:#17867f;

    background:
        rgba(24,216,206,.08);

    font-size:10px;
    font-weight:900;

}

.table-wrap{

    width:100%;

    overflow-x:auto;

    border:
        1px solid
        rgba(50,111,130,.08);

    border-radius:17px;

}

table{

    width:100%;

    min-width:850px;

    border-collapse:collapse;

}

thead{

    background:
        rgba(24,216,206,.055);

}

th{

    padding:
        13px 14px;

    color:#7897a0;

    font-size:10px;
    font-weight:950;

    text-align:left;

    letter-spacing:.7px;

    white-space:nowrap;

}

td{

    padding:
        12px 14px;

    color:#557f8b;

    font-size:12px;
    font-weight:650;

    border-top:
        1px solid
        rgba(50,111,130,.07);

}

tbody tr{

    transition:.2s;

}

tbody tr:hover{

    background:
        rgba(255,255,255,.72);

}

.student-cell{

    display:flex;
    align-items:center;

    gap:10px;

}

.student-avatar{

    width:37px;
    height:37px;

    display:flex;
    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:#fff;

    background:
        linear-gradient(
            145deg,
            #69b8d5,
            #8579d2
        );

    font-size:10px;
    font-weight:950;

}

.student-name strong{

    display:block;

    color:#416f7e;

    font-size:12px;
    font-weight:900;

}

.student-name small{

    display:block;

    margin-top:2px;

    color:#8ca6ad;

    font-size:10px;

}

.status{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        6px 9px;

    border-radius:9px;

    color:#17896e;

    background:
        rgba(66,205,161,.10);

    font-size:9px;
    font-weight:950;

}

.status i{
    font-size:10px;
}

.empty{

    padding:
        45px 20px;

    text-align:center;

}

.empty-icon{

    width:60px;
    height:60px;

    display:flex;
    align-items:center;
    justify-content:center;

    margin:
        0 auto 12px;

    border-radius:18px;

    color:#6f9aa5;

    background:
        rgba(105,184,213,.10);

    font-size:25px;

}

.empty strong{

    display:block;

    color:#416f7e;

    font-size:14px;
    font-weight:900;

}

.empty p{

    margin-top:5px;

    color:#819ca4;

    font-size:11px;

}

/* =========================================================
   IMPRESIÓN
========================================================= */

.print-only{
    display:none;
}

@media print{

    body{
        background:#fff;
    }

    .sidebar,
    .topbar,
    .report-header,
    .filter-card,
    .print-hide{
        display:none !important;
    }

    .app{
        display:block;
        padding:0;
    }

    .main{
        display:block;
    }

    .print-only{
        display:block;
        margin-bottom:20px;
    }

    .print-only h1{
        color:#15576c;
        font-size:22px;
    }

    .print-only p{
        margin-top:5px;
        color:#7897a0;
        font-size:11px;
    }

    .summary-grid{
        display:grid;
        margin-bottom:18px;
    }

    .summary-item{
        box-shadow:none;
    }

    .table-card{
        padding:0;
        border:0;
        box-shadow:none;
        background:#fff;
    }

    .table-heading{
        margin-bottom:12px;
    }

    table{
        min-width:0;
    }

}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px){

    .sidebar{
        width:255px;
    }

}

@media(max-width:1050px){

    .summary-grid{
        grid-template-columns:
            repeat(2,1fr);
    }

    .filter-grid{
        grid-template-columns:
            repeat(2,1fr);
    }

    .filter-actions{
        grid-column:
            1 / -1;
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

    .report-header{
        padding:
            25px 22px;
    }

    .report-header h2{
        font-size:25px;
    }

    .report-symbol{
        display:none;
    }

    .filter-grid{
        grid-template-columns:1fr;
    }

    .filter-actions{
        grid-column:auto;
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

    .filter-actions{
        flex-direction:column;
    }

    .filter-actions .btn{
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
                CONTROL DEL RESTAURANTE
            </div>

            <a
                href="asistencia.php"
                class="nav-link"
            >

                <div class="nav-icon qr">
                    <i class="bi bi-qr-code-scan"></i>
                </div>

                <span>
                    Tomar asistencia
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>

            <a
                href="consultar.php"
                class="nav-link"
            >

                <div class="nav-icon search">
                    <i class="bi bi-search"></i>
                </div>

                <span>
                    Consultar asistencia
                </span>

                <span class="nav-arrow">
                    →
                </span>

            </a>

            <a
                href="reportes.php"
                class="nav-link active"
            >

                <div class="nav-icon report">
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

        <div class="menu-section">

            <div class="menu-label">
                <span class="label-line"></span>
                INFORMACIÓN
            </div>

            <a
                href="consultar.php"
                class="nav-link"
            >

                <div class="nav-icon info">
                    <i class="bi bi-info-circle"></i>
                </div>

                <span>
                    Registros del día
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

                <?= htmlspecialchars($iniciales) ?>

            </div>

            <div class="profile-info">

                <strong>

                    <?= htmlspecialchars(
                        $nombreCompleto
                    ) ?>

                </strong>

                <small>
                    RESTAURANTE
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
                    Reportes
                </h1>

                <p>
                    Consulta y analiza la asistencia del restaurante
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

    <section class="report-header">

        <div class="report-header-content">

            <div class="report-tag">

                <i class="bi bi-bar-chart-line"></i>

                REPORTES DEL RESTAURANTE

            </div>

            <h2>
                Consulta tus registros
            </h2>

            <p>
                Filtra las asistencias por documento y rango de fechas
                para consultar la información registrada en el restaurante.
            </p>

        </div>

        <div class="report-symbol">

            <i class="bi bi-clipboard-data"></i>

        </div>

    </section>

    <section class="summary-grid">

        <div class="summary-item">

            <div class="summary-icon">
                <i class="bi bi-clipboard-check-fill"></i>
            </div>

            <div class="summary-text">

                <span>
                    Total de registros
                </span>

                <strong>
                    <?= $totalRegistros ?>
                </strong>

            </div>

        </div>

        <div class="summary-item">

            <div class="summary-icon">
                <i class="bi bi-people-fill"></i>
            </div>

            <div class="summary-text">

                <span>
                    Estudiantes registrados
                </span>

                <strong>
                    <?= $totalEstudiantesReporte ?>
                </strong>

            </div>

        </div>

        <div class="summary-item">

            <div class="summary-icon">
                <i class="bi bi-calendar3"></i>
            </div>

            <div class="summary-text">

                <span>
                    Días consultados
                </span>

                <strong>
                    <?= $totalDias ?>
                </strong>

            </div>

        </div>

    </section>

    <section class="filter-card">

        <div class="section-heading">

            <div>

                <h3>
                    Filtros del reporte
                </h3>

                <p>
                    Selecciona los datos que deseas consultar.
                </p>

            </div>

        </div>

        <form
            method="GET"
            action="reportes.php"
        >

            <div class="filter-grid">

                <div class="field">

                    <label for="documento">
                        DOCUMENTO DEL ESTUDIANTE
                    </label>

                    <input
                        type="text"
                        id="documento"
                        name="documento"
                        value="<?= htmlspecialchars($documento) ?>"
                        placeholder="Ej. 123456789"
                    >

                </div>

                <div class="field">

                    <label for="fecha_inicio">
                        FECHA INICIAL
                    </label>

                    <input
                        type="date"
                        id="fecha_inicio"
                        name="fecha_inicio"
                        value="<?= htmlspecialchars($fechaInicio) ?>"
                    >

                </div>

                <div class="field">

                    <label for="fecha_fin">
                        FECHA FINAL
                    </label>

                    <input
                        type="date"
                        id="fecha_fin"
                        name="fecha_fin"
                        value="<?= htmlspecialchars($fechaFin) ?>"
                    >

                </div>

                <div class="filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-search"></i>

                        Consultar

                    </button>

                    <a
                        href="reportes.php"
                        class="btn btn-light"
                    >

                        <i class="bi bi-arrow-counterclockwise"></i>

                        Limpiar

                    </a>

                    <button
                        type="button"
                        class="btn btn-light"
                        onclick="window.print()"
                    >

                        <i class="bi bi-printer"></i>

                        Imprimir

                    </button>

                </div>

            </div>

        </form>

        <?php if ($error !== ""): ?>

            <div class="error">

                <i class="bi bi-exclamation-circle"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>

    </section>

    <section class="table-card">

        <div class="print-only">

            <h1>
                Reporte de asistencia - Restaurante
            </h1>

            <p>
                Periodo:
                <?= htmlspecialchars(fechaBonita($fechaInicio)) ?>
                -
                <?= htmlspecialchars(fechaBonita($fechaFin)) ?>
                | Generado:
                <?= date("d/m/Y H:i:s") ?>
            </p>

        </div>

        <div class="table-heading">

            <div>

                <h3>
                    Registros de asistencia
                </h3>

                <p>

                    <?php if ($fechaInicio && $fechaFin): ?>

                        <?= htmlspecialchars(fechaBonita($fechaInicio)) ?>
                        al
                        <?= htmlspecialchars(fechaBonita($fechaFin)) ?>

                    <?php else: ?>

                        Todos los registros

                    <?php endif; ?>

                </p>

            </div>

            <div class="result-badge">

                <i class="bi bi-check-circle-fill"></i>

                <?= $totalRegistros ?> registros

            </div>

        </div>

        <div class="table-wrap">

            <?php if (!empty($registros)): ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                DOCUMENTO
                            </th>

                            <th>
                                ESTUDIANTE
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

                    <?php foreach ($registros as $registro): ?>

                        <?php

                        $nombreEstudiante = trim(
                            $registro["nombres"] .
                            " " .
                            $registro["apellidos"]
                        );

                        $avatarEstudiante =
                            inicialesEstudiante(
                                $nombreEstudiante
                            );

                        ?>

                        <tr>

                            <td>

                                <?= htmlspecialchars(
                                    $registro["documento"]
                                ) ?>

                            </td>

                            <td>

                                <div class="student-cell">

                                    <div class="student-avatar">

                                        <?= htmlspecialchars(
                                            $avatarEstudiante
                                        ) ?>

                                    </div>

                                    <div class="student-name">

                                        <strong>

                                            <?= htmlspecialchars(
                                                $nombreEstudiante
                                            ) ?>

                                        </strong>

                                        <small>
                                            Estudiante
                                        </small>

                                    </div>

                                </div>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    fechaBonita(
                                        $registro["fecha"]
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        "h:i:s A",
                                        strtotime(
                                            $registro["hora"]
                                        )
                                    )
                                ) ?>

                            </td>

                            <td>

                                <span class="status">

                                    <i class="bi bi-check-circle-fill"></i>

                                    <?= htmlspecialchars(
                                        $registro["estado"]
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?= !empty($registro["observacion"])
                                    ? htmlspecialchars(
                                        $registro["observacion"]
                                    )
                                    : "—"
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">

                        <i class="bi bi-clipboard-x"></i>

                    </div>

                    <strong>
                        No hay registros para mostrar
                    </strong>

                    <p>
                        Prueba con otro rango de fechas o documento.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

</div>

<script>

function actualizarReloj()
{

    const ahora = new Date();

    const horas =
        String(
            ahora.getHours()
        ).padStart(
            2,
            "0"
        );

    const minutos =
        String(
            ahora.getMinutes()
        ).padStart(
            2,
            "0"
        );

    const segundos =
        String(
            ahora.getSeconds()
        ).padStart(
            2,
            "0"
        );

    const reloj =
        document.getElementById(
            "reloj"
        );

    if (reloj) {

        reloj.textContent =
            horas
            + ":"
            + minutos
            + ":"
            + segundos;

    }

}

actualizarReloj();

setInterval(
    actualizarReloj,
    1000
);

const formulario =
    document.querySelector("form");

if (formulario) {

    formulario.addEventListener(
        "submit",
        function(event)
        {

            const inicio =
                document.getElementById(
                    "fecha_inicio"
                ).value;

            const fin =
                document.getElementById(
                    "fecha_fin"
                ).value;

            if (
                inicio &&
                fin &&
                inicio > fin
            ) {

                event.preventDefault();

                alert(
                    "La fecha inicial no puede ser mayor que la fecha final."
                );

            }

        }
    );

}

</script>

</body>
</html>
