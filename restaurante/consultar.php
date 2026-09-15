<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/* =========================================================
   PROTECCIÓN
========================================================= */

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION["id_rol"]) || (int)$_SESSION["id_rol"] !== 3) {
    header("Location: ../index.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set("America/Bogota");

/* =========================================================
   DATOS DEL USUARIO
========================================================= */

$nombreUsuario = $_SESSION["nombre"] ?? "Usuario Restaurante";
$apellidoUsuario = $_SESSION["apellido"] ?? "";

$nombreCompleto = trim(
    $nombreUsuario . " " . $apellidoUsuario
);

$partesNombre = preg_split(
    '/\s+/',
    $nombreCompleto
);

$primerNombre = $partesNombre[0] ?? "Usuario";

$iniciales = "";

foreach (array_slice($partesNombre, 0, 2) as $parte) {
    $iniciales .= strtoupper(substr($parte, 0, 1));
}

if ($iniciales === "") {
    $iniciales = "RE";
}

/* =========================================================
   FECHA Y HORA
========================================================= */

$horaActual = date("H:i:s");
$fechaActual = date("d/m/Y");

/* =========================================================
   FILTROS
========================================================= */

$documento = trim($_GET["documento"] ?? "");
$fechaInicio = trim($_GET["fecha_inicio"] ?? "");
$fechaFin = trim($_GET["fecha_fin"] ?? "");

$busquedaRealizada = (
    $documento !== "" ||
    $fechaInicio !== "" ||
    $fechaFin !== ""
);

$estudiante = null;
$registros = [];
$totalRegistros = 0;
$mensaje = "";
$tipoMensaje = "";

/* =========================================================
   VALIDAR FECHAS
========================================================= */

if ($fechaInicio !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
    $fechaInicio = "";
}

if ($fechaFin !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    $fechaFin = "";
}

/* =========================================================
   BUSCAR ESTUDIANTE
========================================================= */

if ($documento !== "") {

    $sqlEstudiante = "
        SELECT
            id_estudiante,
            documento,
            nombres,
            apellidos,
            estado
        FROM estudiantes
        WHERE documento = ?
        LIMIT 1
    ";

    $stmtEstudiante = mysqli_prepare(
        $conexion,
        $sqlEstudiante
    );

    if ($stmtEstudiante) {

        mysqli_stmt_bind_param(
            $stmtEstudiante,
            "s",
            $documento
        );

        mysqli_stmt_execute($stmtEstudiante);

        $resultadoEstudiante =
            mysqli_stmt_get_result(
                $stmtEstudiante
            );

        $estudiante =
            mysqli_fetch_assoc(
                $resultadoEstudiante
            );

        mysqli_stmt_close(
            $stmtEstudiante
        );
    }

    if (!$estudiante) {
        $mensaje = "No se encontró ningún estudiante con ese documento.";
        $tipoMensaje = "error";
    }
}

/* =========================================================
   CONSULTAR ASISTENCIAS
========================================================= */

if ($busquedaRealizada && ($documento === "" || $estudiante)) {

    $condiciones = [];
    $tipos = "";
    $parametros = [];

    if ($estudiante) {
        $condiciones[] = "ar.id_estudiante = ?";
        $tipos .= "i";
        $parametros[] = (int)$estudiante["id_estudiante"];
    }

    if ($fechaInicio !== "") {
        $condiciones[] = "ar.fecha >= ?";
        $tipos .= "s";
        $parametros[] = $fechaInicio;
    }

    if ($fechaFin !== "") {
        $condiciones[] = "ar.fecha <= ?";
        $tipos .= "s";
        $parametros[] = $fechaFin;
    }

    $sqlRegistros = "
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
            ON e.id_estudiante = ar.id_estudiante
    ";

    if (count($condiciones) > 0) {
        $sqlRegistros .=
            " WHERE " .
            implode(" AND ", $condiciones);
    }

    $sqlRegistros .= "
        ORDER BY ar.fecha DESC, ar.hora DESC
    ";

    $stmtRegistros = mysqli_prepare(
        $conexion,
        $sqlRegistros
    );

    if ($stmtRegistros) {

        if (count($parametros) > 0) {

            mysqli_stmt_bind_param(
                $stmtRegistros,
                $tipos,
                ...$parametros
            );
        }

        mysqli_stmt_execute($stmtRegistros);

        $resultadoRegistros =
            mysqli_stmt_get_result(
                $stmtRegistros
            );

        while (
            $fila =
            mysqli_fetch_assoc($resultadoRegistros)
        ) {
            $registros[] = $fila;
        }

        mysqli_stmt_close(
            $stmtRegistros
        );
    }

    $totalRegistros = count($registros);
}

/* =========================================================
   TOTAL GENERAL SI NO HAY FILTROS
========================================================= */

if (!$busquedaRealizada) {

    $sqlRecientes = "
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
            ON e.id_estudiante = ar.id_estudiante
        ORDER BY ar.fecha DESC, ar.hora DESC
        LIMIT 50
    ";

    $resultadoRecientes =
        mysqli_query(
            $conexion,
            $sqlRecientes
        );

    if ($resultadoRecientes) {

        while (
            $fila =
            mysqli_fetch_assoc($resultadoRecientes)
        ) {
            $registros[] = $fila;
        }
    }

    $totalRegistros = count($registros);
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
        Consultar asistencia | Restaurante
    </title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >

    <style>

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

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            min-height:100vh;
            overflow-x:hidden;
            font-family:"Segoe UI",Arial,sans-serif;
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

        /* =====================================================
           SIDEBAR
        ====================================================== */

        .sidebar{
            width:285px;
            flex-shrink:0;
            min-height:calc(100vh - 36px);
            display:flex;
            flex-direction:column;
            padding:22px 16px 16px;
            border:1px solid rgba(255,255,255,.94);
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
            padding:3px 9px 20px;
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
            background:rgba(255,255,255,.76);
            border:1px solid rgba(255,255,255,.95);
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
            margin:0 9px 16px;
            background:rgba(50,111,130,.09);
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
            background:rgba(255,255,255,.74);
            transform:translateX(4px);
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
            border-radius:0 7px 7px 0;
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
            background:rgba(24,216,206,.075);
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
            background:rgba(255,255,255,.54);
            border:1px solid rgba(255,255,255,.85);
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
            background:rgba(242,143,150,.08);
            border-radius:13px;
        }

        .logout-icon{
            width:35px;
            height:35px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:10px;
            background:rgba(242,143,150,.08);
        }

        /* =====================================================
           MAIN
        ====================================================== */

        .main{
            flex:1;
            min-width:0;
            display:flex;
            flex-direction:column;
            gap:18px;
        }

        .topbar{
            min-height:82px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
            padding:14px 22px;
            border:1px solid rgba(255,255,255,.92);
            border-radius:23px;
            background:rgba(255,255,255,.68);
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
                    var(--blue),
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
            padding:10px 15px;
            border-radius:15px;
            background:rgba(255,255,255,.73);
            border:1px solid rgba(255,255,255,.90);
        }

        .clock-icon{
            width:38px;
            height:38px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:11px;
            color:#0b9f9c;
            background:rgba(24,216,206,.10);
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

        /* =====================================================
           BUSCADOR
        ====================================================== */

        .search-card{
            padding:25px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:27px;
            background:
                radial-gradient(
                    circle at 92% 8%,
                    rgba(105,184,213,.12),
                    transparent 27%
                ),
                rgba(255,255,255,.76);
            box-shadow:
                0 22px 52px
                rgba(55,113,129,.08);
        }

        .section-heading{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:15px;
            margin-bottom:20px;
        }

        .section-heading h2,
        .section-heading h3{
            color:#416f7e;
            font-size:19px;
            font-weight:950;
        }

        .section-heading p{
            margin-top:5px;
            color:#819ca4;
            font-size:12px;
            font-weight:650;
        }

        .heading-icon{
            width:46px;
            height:46px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:14px;
            color:#5578ca;
            background:rgba(105,184,213,.11);
            font-size:21px;
        }

        .search-form{
            display:grid;
            grid-template-columns:1.35fr 1fr 1fr auto;
            gap:12px;
            align-items:end;
        }

        .field label{
            display:block;
            margin:0 0 7px 2px;
            color:#668995;
            font-size:11px;
            font-weight:900;
        }

        .input-wrap{
            position:relative;
        }

        .input-wrap i{
            position:absolute;
            left:13px;
            top:50%;
            transform:translateY(-50%);
            color:#79a0aa;
            font-size:16px;
            pointer-events:none;
        }

        .input-control{
            width:100%;
            min-height:47px;
            padding:0 13px 0 39px;
            border:1px solid rgba(157,199,205,.35);
            border-radius:13px;
            outline:none;
            color:#416f7e;
            background:rgba(255,255,255,.78);
            font-family:inherit;
            font-size:12px;
            font-weight:700;
            transition:.2s;
        }

        .input-control:focus{
            border-color:rgba(24,216,206,.50);
            box-shadow:
                0 0 0 4px
                rgba(24,216,206,.07);
        }

        .search-button{
            min-height:47px;
            padding:0 20px;
            border:0;
            border-radius:13px;
            color:#fff;
            background:
                linear-gradient(
                    145deg,
                    #18d8ce,
                    #087d92
                );
            box-shadow:
                0 10px 22px
                rgba(24,216,206,.18);
            font-family:inherit;
            font-size:12px;
            font-weight:900;
            cursor:pointer;
            transition:.2s;
        }

        .search-button:hover{
            transform:translateY(-2px);
        }

        .clear-button{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:47px;
            padding:0 14px;
            border-radius:13px;
            color:#7897a0;
            background:rgba(255,255,255,.65);
            border:1px solid rgba(255,255,255,.90);
            text-decoration:none;
            font-size:12px;
            font-weight:850;
        }

        .clear-button:hover{
            color:#557f8b;
            background:#fff;
        }

        /* =====================================================
           ALERTA
        ====================================================== */

        .alert{
            display:flex;
            align-items:center;
            gap:10px;
            padding:14px 17px;
            border-radius:15px;
            font-size:12px;
            font-weight:800;
        }

        .alert.error{
            color:#9e5861;
            background:rgba(242,143,150,.10);
            border:1px solid rgba(242,143,150,.20);
        }

        .alert i{
            font-size:18px;
        }

        /* =====================================================
           DATOS ESTUDIANTE
        ====================================================== */

        .student-card{
            display:grid;
            grid-template-columns:auto 1fr auto;
            align-items:center;
            gap:17px;
            padding:21px 23px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:23px;
            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.82),
                    rgba(236,250,248,.70)
                );
            box-shadow:
                0 18px 42px
                rgba(55,113,129,.065);
        }

        .student-avatar{
            width:62px;
            height:62px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:18px;
            color:#087d92;
            background:rgba(24,216,206,.10);
            font-size:25px;
        }

        .student-info small{
            display:block;
            color:#8aa2a9;
            font-size:10px;
            font-weight:900;
            letter-spacing:.7px;
        }

        .student-info h2{
            margin-top:3px;
            color:#315f70;
            font-size:20px;
            font-weight:950;
        }

        .student-info p{
            margin-top:4px;
            color:#7897a0;
            font-size:11px;
            font-weight:700;
        }

        .student-status{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:8px 11px;
            border-radius:11px;
            color:#17815e;
            background:rgba(66,205,161,.10);
            font-size:10px;
            font-weight:900;
        }

        .student-status.inactive{
            color:#a15f5f;
            background:rgba(242,143,150,.10);
        }

        /* =====================================================
           RESUMEN
        ====================================================== */

        .summary-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:16px;
        }

        .summary-item{
            min-height:104px;
            display:flex;
            align-items:center;
            gap:17px;
            padding:21px 22px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:23px;
            background:rgba(255,255,255,.76);
            box-shadow:
                0 18px 42px
                rgba(55,113,129,.065);
        }

        .summary-icon{
            width:58px;
            height:58px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:17px;
            color:#5578ca;
            background:rgba(105,184,213,.11);
            font-size:25px;
        }

        .summary-item:nth-child(2) .summary-icon{
            color:#7569c2;
            background:rgba(133,121,210,.10);
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
            font-size:28px;
            font-weight:950;
            line-height:1;
        }

        /* =====================================================
           TABLA
        ====================================================== */

        .table-card{
            padding:24px 25px 20px;
            border:1px solid rgba(255,255,255,.94);
            border-radius:25px;
            background:rgba(255,255,255,.74);
            box-shadow:
                0 18px 42px
                rgba(55,113,129,.065);
        }

        .count-label{
            display:flex;
            align-items:center;
            gap:7px;
            padding:7px 10px;
            border-radius:10px;
            color:#17867f;
            background:rgba(24,216,206,.08);
            font-size:10px;
            font-weight:900;
        }

        .table-wrapper{
            overflow-x:auto;
        }

        .attendance-table{
            width:100%;
            border-collapse:collapse;
            min-width:700px;
        }

        .attendance-table th{
            padding:12px 10px;
            text-align:left;
            color:#7d9aa3;
            background:rgba(240,249,248,.72);
            font-size:10px;
            font-weight:950;
            letter-spacing:.7px;
            text-transform:uppercase;
        }

        .attendance-table th:first-child{
            border-radius:10px 0 0 10px;
        }

        .attendance-table th:last-child{
            border-radius:0 10px 10px 0;
        }

        .attendance-table td{
            padding:13px 10px;
            border-bottom:1px solid rgba(50,111,130,.07);
            color:#557f8b;
            font-size:12px;
            font-weight:650;
        }

        .attendance-table tbody tr:hover{
            background:rgba(24,216,206,.035);
        }

        .status{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 9px;
            border-radius:10px;
            color:#17815e;
            background:rgba(66,205,161,.10);
            font-size:10px;
            font-weight:900;
        }

        .status::before{
            content:"";
            width:6px;
            height:6px;
            border-radius:50%;
            background:#27b884;
        }

        .date-badge{
            display:inline-block;
            padding:5px 8px;
            border-radius:8px;
            color:#557f8b;
            background:rgba(105,184,213,.08);
            font-size:10px;
            font-weight:850;
        }

        .empty{
            padding:42px 20px;
            text-align:center;
            color:#91a8ae;
            font-size:12px;
            font-weight:700;
        }

        .empty i{
            display:block;
            margin-bottom:8px;
            color:#a8c5ca;
            font-size:32px;
        }

        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media(max-width:1100px){

            .sidebar{
                width:255px;
            }

            .search-form{
                grid-template-columns:1fr 1fr;
            }

            .search-button,
            .clear-button{
                width:100%;
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
                grid-template-columns:repeat(2,1fr);
            }

            .menu-label{
                grid-column:1/-1;
            }

            .sidebar-bottom{
                display:none;
            }
        }

        @media(max-width:700px){

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

            .search-form{
                grid-template-columns:1fr;
            }

            .search-card,
            .table-card{
                padding:18px;
            }

            .student-card{
                grid-template-columns:auto 1fr;
            }

            .student-status{
                grid-column:1/-1;
                width:max-content;
            }

            .summary-grid{
                grid-template-columns:1fr;
            }

            .section-heading{
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
                    class="nav-link active"
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
                    class="nav-link"
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
                    class="nav-link active"
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
                        <?= htmlspecialchars($nombreCompleto) ?>
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
                onclick="return confirm('¿Deseas cerrar tu sesión?');"
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
                        Consultar asistencia
                    </h1>

                    <p>
                        Consulta los registros de asistencia del restaurante
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
             BUSCADOR
        ================================================== -->

        <section class="search-card">

            <div class="section-heading">

                <div>

                    <h2>
                        <i class="bi bi-search"></i>
                        Buscar registros
                    </h2>

                    <p>
                        Puedes buscar por documento o utilizar un rango de fechas.
                    </p>

                </div>

                <div class="heading-icon">
                    <i class="bi bi-funnel"></i>
                </div>

            </div>

            <form
                method="GET"
                action="consultar.php"
                class="search-form"
            >

                <div class="field">

                    <label for="documento">
                        DOCUMENTO DEL ESTUDIANTE
                    </label>

                    <div class="input-wrap">

                        <i class="bi bi-person-vcard"></i>

                        <input
                            type="text"
                            id="documento"
                            name="documento"
                            class="input-control"
                            placeholder="Ej. 1234567890"
                            value="<?= htmlspecialchars($documento) ?>"
                        >

                    </div>

                </div>

                <div class="field">

                    <label for="fecha_inicio">
                        FECHA INICIAL
                    </label>

                    <div class="input-wrap">

                        <i class="bi bi-calendar3"></i>

                        <input
                            type="date"
                            id="fecha_inicio"
                            name="fecha_inicio"
                            class="input-control"
                            value="<?= htmlspecialchars($fechaInicio) ?>"
                        >

                    </div>

                </div>

                <div class="field">

                    <label for="fecha_fin">
                        FECHA FINAL
                    </label>

                    <div class="input-wrap">

                        <i class="bi bi-calendar-check"></i>

                        <input
                            type="date"
                            id="fecha_fin"
                            name="fecha_fin"
                            class="input-control"
                            value="<?= htmlspecialchars($fechaFin) ?>"
                        >

                    </div>

                </div>

                <div class="field">

                    <button
                        type="submit"
                        class="search-button"
                    >
                        <i class="bi bi-search"></i>
                        Buscar
                    </button>

                </div>

            </form>

            <div
                style="
                    display:flex;
                    justify-content:flex-end;
                    margin-top:10px;
                "
            >

                <a
                    href="consultar.php"
                    class="clear-button"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Limpiar filtros
                </a>

            </div>

        </section>

        <?php if ($mensaje !== ""): ?>

            <div class="alert <?= htmlspecialchars($tipoMensaje) ?>">

                <i class="bi bi-exclamation-circle-fill"></i>

                <span>
                    <?= htmlspecialchars($mensaje) ?>
                </span>

            </div>

        <?php endif; ?>

        <!-- =================================================
             ESTUDIANTE
        ================================================== -->

        <?php if ($estudiante): ?>

            <section class="student-card">

                <div class="student-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>

                <div class="student-info">

                    <small>
                        ESTUDIANTE CONSULTADO
                    </small>

                    <h2>
                        <?= htmlspecialchars(
                            trim(
                                $estudiante["nombres"] .
                                " " .
                                $estudiante["apellidos"]
                            )
                        ) ?>
                    </h2>

                    <p>
                        Documento:
                        <?= htmlspecialchars($estudiante["documento"]) ?>
                    </p>

                </div>

                <?php if (
                    strtoupper($estudiante["estado"]) === "ACTIVO"
                ): ?>

                    <div class="student-status">
                        <i class="bi bi-check-circle-fill"></i>
                        ESTUDIANTE ACTIVO
                    </div>

                <?php else: ?>

                    <div class="student-status inactive">
                        <i class="bi bi-x-circle-fill"></i>
                        <?= htmlspecialchars($estudiante["estado"]) ?>
                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

        <!-- =================================================
             RESUMEN
        ================================================== -->

        <section class="summary-grid">

            <div class="summary-item">

                <div class="summary-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>

                <div class="summary-text">

                    <span>
                        Registros encontrados
                    </span>

                    <strong>
                        <?= $totalRegistros ?>
                    </strong>

                </div>

            </div>

            <div class="summary-item">

                <div class="summary-icon">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div class="summary-text">

                    <span>
                        Consulta
                    </span>

                    <strong style="font-size:18px;">

                        <?php if ($documento !== ""): ?>

                            Estudiante

                        <?php elseif (
                            $fechaInicio !== "" ||
                            $fechaFin !== ""
                        ): ?>

                            Por fechas

                        <?php else: ?>

                            Últimos registros

                        <?php endif; ?>

                    </strong>

                </div>

            </div>

        </section>

        <!-- =================================================
             TABLA
        ================================================== -->

        <section class="table-card">

            <div class="section-heading">

                <div>

                    <h3>
                        Registros de asistencia
                    </h3>

                    <p>
                        <?= $busquedaRealizada
                            ? "Resultados de la búsqueda realizada."
                            : "Últimos registros almacenados en el sistema."
                        ?>
                    </p>

                </div>

                <div class="count-label">

                    <i class="bi bi-list-check"></i>

                    <?= $totalRegistros ?>
                    <?= $totalRegistros === 1
                        ? "REGISTRO"
                        : "REGISTROS"
                    ?>

                </div>

            </div>

            <div class="table-wrapper">

                <table class="attendance-table">

                    <thead>

                        <tr>

                            <th>#</th>
                            <th>Documento</th>
                            <th>Estudiante</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Estado</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($totalRegistros > 0): ?>

                            <?php foreach (
                                $registros as $index => $fila
                            ): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $fila["documento"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            trim(
                                                $fila["nombres"] .
                                                " " .
                                                $fila["apellidos"]
                                            )
                                        ) ?>
                                    </td>

                                    <td>

                                        <span class="date-badge">

                                            <?= htmlspecialchars(
                                                date(
                                                    "d/m/Y",
                                                    strtotime(
                                                        $fila["fecha"]
                                                    )
                                                )
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date(
                                                "h:i:s A",
                                                strtotime(
                                                    $fila["hora"]
                                                )
                                            )
                                        ) ?>
                                    </td>

                                    <td>

                                        <span class="status">

                                            <?= htmlspecialchars(
                                                $fila["estado"]
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="empty"
                                >

                                    <i class="bi bi-search"></i>

                                    <?php if ($busquedaRealizada): ?>

                                        No se encontraron registros
                                        con los filtros seleccionados.

                                    <?php else: ?>

                                        Todavía no hay registros
                                        de asistencia.

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

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

    const ahora = new Date();

    const horas =
        String(
            ahora.getHours()
        ).padStart(2, "0");

    const minutos =
        String(
            ahora.getMinutes()
        ).padStart(2, "0");

    const segundos =
        String(
            ahora.getSeconds()
        ).padStart(2, "0");

    const reloj =
        document.getElementById("reloj");

    if (reloj) {

        reloj.textContent =
            horas +
            ":" +
            minutos +
            ":" +
            segundos;

    }

}

actualizarReloj();

setInterval(
    actualizarReloj,
    1000
);


/* =========================================================
   VALIDACIÓN SENCILLA DE FECHAS
========================================================= */

const formulario =
    document.querySelector(".search-form");

if (formulario) {

    formulario.addEventListener(
        "submit",
        function(event) {

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
                    "La fecha inicial no puede ser posterior a la fecha final."
                );

            }

        }
    );

}

</script>

</body>
</html>
