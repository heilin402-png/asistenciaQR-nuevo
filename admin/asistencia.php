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
   MENSAJES
========================================================= */

$mensaje = '';
$tipoMensaje = '';


/* =========================================================
   CREAR SESIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['crear_sesion'])
) {

    $idCurso = (int)(
        $_POST['id_curso'] ?? 0
    );

    $horaInicio = trim(
        $_POST['hora_inicio'] ?? ''
    );

    if ($idCurso <= 0) {

        $mensaje = 'Selecciona un curso.';
        $tipoMensaje = 'error';

    } else {

        if ($horaInicio === '') {
            $horaInicio = date('H:i:s');
        }

        /*
         * El administrador también puede tomar asistencia.
         * Por eso la sesión se crea usando el usuario actual
         * como docente responsable.
         */

        $sqlCrear = "
            INSERT INTO sesiones_clase
            (
                id_docente,
                id_curso,
                fecha,
                hora_inicio,
                estado
            )
            VALUES
            (?, ?, ?, ?, 'ACTIVA')
        ";

        $stmtCrear = mysqli_prepare(
            $conexion,
            $sqlCrear
        );

        if ($stmtCrear) {

            mysqli_stmt_bind_param(
                $stmtCrear,
                "iiss",
                $_SESSION['id_usuario'],
                $idCurso,
                $fechaHoy,
                $horaInicio
            );

            if (
                mysqli_stmt_execute(
                    $stmtCrear
                )
            ) {

                $idNuevaSesion =
                    mysqli_insert_id(
                        $conexion
                    );

                header(
                    "Location: asistencia.php?sesion="
                    . $idNuevaSesion
                );

                exit();

            } else {

                $mensaje =
                    'No fue posible crear la sesión.';

                $tipoMensaje = 'error';

            }

            mysqli_stmt_close(
                $stmtCrear
            );

        } else {

            $mensaje =
                'Error al preparar la sesión.';

            $tipoMensaje = 'error';

        }

    }

}


/* =========================================================
   CERRAR SESIÓN DE ASISTENCIA
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['cerrar_sesion'])
) {

    $idSesionCerrar = (int)(
        $_POST['id_sesion'] ?? 0
    );

    if ($idSesionCerrar > 0) {

        $sqlCerrar = "
            UPDATE sesiones_clase
            SET estado = 'CERRADA'
            WHERE id_sesion = ?
        ";

        $stmtCerrar = mysqli_prepare(
            $conexion,
            $sqlCerrar
        );

        if ($stmtCerrar) {

            mysqli_stmt_bind_param(
                $stmtCerrar,
                "i",
                $idSesionCerrar
            );

            mysqli_stmt_execute(
                $stmtCerrar
            );

            mysqli_stmt_close(
                $stmtCerrar
            );

        }

        header(
            "Location: asistencia.php"
        );

        exit();

    }

}


/* =========================================================
   CURSOS ACTIVOS
========================================================= */

$cursos = [];

$sqlCursos = "
    SELECT
        id_curso,
        nombre_curso
    FROM cursos
    WHERE estado = 'ACTIVO'
    ORDER BY nombre_curso ASC
";

$resultadoCursos = mysqli_query(
    $conexion,
    $sqlCursos
);

if ($resultadoCursos) {

    while (
        $curso = mysqli_fetch_assoc(
            $resultadoCursos
        )
    ) {

        $cursos[] = $curso;

    }

}


/* =========================================================
   SESIONES DE HOY
========================================================= */

$sesionesHoy = [];

$sqlSesiones = "
    SELECT
        s.id_sesion,
        s.id_curso,
        s.fecha,
        s.hora_inicio,
        s.estado,
        c.nombre_curso,
        CONCAT(
            COALESCE(u.nombre, ''),
            ' ',
            COALESCE(u.apellido, '')
        ) AS docente

    FROM sesiones_clase s

    INNER JOIN cursos c
        ON c.id_curso = s.id_curso

    LEFT JOIN usuarios u
        ON u.id_usuario = s.id_docente

    WHERE s.fecha = ?

    ORDER BY
        s.hora_inicio DESC
";

$stmtSesiones = mysqli_prepare(
    $conexion,
    $sqlSesiones
);

if ($stmtSesiones) {

    mysqli_stmt_bind_param(
        $stmtSesiones,
        "s",
        $fechaHoy
    );

    mysqli_stmt_execute(
        $stmtSesiones
    );

    $resultadoSesiones =
        mysqli_stmt_get_result(
            $stmtSesiones
        );

    while (
        $sesion = mysqli_fetch_assoc(
            $resultadoSesiones
        )
    ) {

        $sesionesHoy[] = $sesion;

    }

    mysqli_stmt_close(
        $stmtSesiones
    );

}


/* =========================================================
   SESIÓN SELECCIONADA
========================================================= */

$idSesionActual = (int)(
    $_GET['sesion'] ?? 0
);

$sesionActual = null;

if ($idSesionActual > 0) {

    $sqlSesionActual = "
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

        WHERE s.id_sesion = ?

        LIMIT 1
    ";

    $stmtSesionActual = mysqli_prepare(
        $conexion,
        $sqlSesionActual
    );

    if ($stmtSesionActual) {

        mysqli_stmt_bind_param(
            $stmtSesionActual,
            "i",
            $idSesionActual
        );

        mysqli_stmt_execute(
            $stmtSesionActual
        );

        $resultadoSesionActual =
            mysqli_stmt_get_result(
                $stmtSesionActual
            );

        $sesionActual =
            mysqli_fetch_assoc(
                $resultadoSesionActual
            );

        mysqli_stmt_close(
            $stmtSesionActual
        );

    }

}


/* =========================================================
   GENERAR DATOS DEL QR DE SESIÓN
========================================================= */

$qrUrl = '';

if (
    $sesionActual
    &&
    strtoupper(
        trim(
            $sesionActual['estado']
        )
    ) !== 'CERRADA'
) {

    /*
     * IP LOCAL DEL COMPUTADOR
     *
     * IMPORTANTE:
     * Cambia esta IP por la IPv4 de tu computador.
     * Ejemplo:
     * 192.168.1.25
     */

    $ipServidor = '192.168.1.100';


    /*
     * URL QUE SE CODIFICARÁ EN EL QR
     */

    $qrUrl =
        'http://'
        . $ipServidor
        . '/asistenciaQR/estudiante/tomar_asistencia.php?sesion='
        . $idSesionActual;

}

/* =========================================================
   ASISTENCIAS DE LA SESIÓN
========================================================= */

$registrosAsistencia = [];

if ($idSesionActual > 0) {

    $sqlRegistros = "
        SELECT
            a.id_asistencia,
            a.id_estudiante,
            a.estado,
            a.estado_excusa,
            a.hora_registro,
            e.documento,
            e.nombres,
            e.apellidos

        FROM asistencia_clase a

        INNER JOIN estudiantes e
            ON e.id_estudiante = a.id_estudiante

        WHERE a.id_sesion = ?

        ORDER BY
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
            $idSesionActual
        );

        mysqli_stmt_execute(
            $stmtRegistros
        );

        $resultadoRegistros =
            mysqli_stmt_get_result(
                $stmtRegistros
            );

        while (
            $registro =
                mysqli_fetch_assoc(
                    $resultadoRegistros
                )
        ) {

            $registrosAsistencia[] =
                $registro;

        }

        mysqli_stmt_close(
            $stmtRegistros
        );

    }

}


/* =========================================================
   CONTADORES
========================================================= */

$totalRegistros =
    count(
        $registrosAsistencia
    );

$totalPresentes = 0;
$totalAusentes = 0;
$totalExcusas = 0;

foreach (
    $registrosAsistencia
    as $registro
) {

    $estado = strtoupper(
        trim(
            $registro['estado'] ?? ''
        )
    );

    if (
        $estado === 'PRESENTE'
        ||
        $estado === 'ASISTIO'
        ||
        $estado === 'ASISTENCIA'
    ) {

        $totalPresentes++;

    }

    if (
        $estado === 'AUSENTE'
        ||
        $estado === 'FALTA'
    ) {

        $totalAusentes++;

    }

    if (
        !empty(
            $registro['estado_excusa']
        )
    ) {

        $totalExcusas++;

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
    Asistencia QR | Control de asistencia
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<!-- =====================================================
     LIBRERÍA PARA QR
====================================================== -->

<script
    src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
></script>


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
   CONTENEDOR DE ASISTENCIA
========================================================= */

.attendance-layout{

    display:grid;

    grid-template-columns:
        minmax(330px,.85fr)
        minmax(0,1.55fr);

    gap:18px;

}


/* =========================================================
   TARJETA QR
========================================================= */

.qr-card{

    position:relative;

    overflow:hidden;

    padding:28px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:

        radial-gradient(
            circle at 50% 10%,
            rgba(24,216,206,.13),
            transparent 35%
        ),

        linear-gradient(
            145deg,
            rgba(255,255,255,.88),
            rgba(234,250,247,.72)
        );

    box-shadow:
        0 22px 52px
        rgba(55,113,129,.08);

}


.qr-card h2{

    color:#15576c;

    font-size:22px;

    font-weight:950;

}


.qr-card > p{

    margin-top:6px;

    color:#7898a2;

    font-size:13px;

    font-weight:650;

    line-height:1.5;

}


.qr-session-info{

    margin-top:18px;

    padding:13px 15px;

    border-radius:15px;

    background:
        rgba(255,255,255,.68);

    border:
        1px solid
        rgba(255,255,255,.88);

}


.qr-session-info span{

    display:block;

    color:#8aa2aa;

    font-size:10px;

    font-weight:800;

}


.qr-session-info strong{

    display:block;

    margin-top:4px;

    color:#356d7d;

    font-size:15px;

    font-weight:950;

}


.qr-wrapper{

    width:235px;
    height:235px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:
        23px auto 17px;

    padding:17px;

    border-radius:25px;

    background:#fff;

    border:
        1px solid
        rgba(24,216,206,.16);

    box-shadow:
        0 18px 40px
        rgba(55,113,129,.13);

}


#qrcode{

    display:flex;

    align-items:center;
    justify-content:center;

}


#qrcode img{

    width:195px !important;
    height:195px !important;

}


.qr-empty{

    min-height:310px;

    display:flex;

    flex-direction:column;

    align-items:center;
    justify-content:center;

    text-align:center;

}


.qr-empty-icon{

    width:78px;
    height:78px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin-bottom:14px;

    border-radius:22px;

    color:#0a9995;

    background:
        rgba(24,216,206,.10);

    font-size:36px;

}


.qr-empty h3{

    color:#456f7c;

    font-size:18px;

    font-weight:950;

}


.qr-empty p{

    max-width:300px;

    margin-top:7px;

    color:#8aa2a9;

    font-size:12px;

    font-weight:650;

    line-height:1.55;

}


/* =========================================================
   BOTONES
========================================================= */

.btn-primary{

    width:100%;

    min-height:48px;

    display:flex;

    align-items:center;
    justify-content:center;

    gap:8px;

    border:none;

    border-radius:14px;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18d8ce,
            #1599ad
        );

    font-family:inherit;

    font-size:13px;

    font-weight:950;

    cursor:pointer;

    box-shadow:
        0 12px 25px
        rgba(24,216,206,.17);

    transition:.25s;

}


.btn-primary:hover{

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 30px
        rgba(24,216,206,.23);

}


.btn-secondary{

    min-height:43px;

    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:7px;

    padding:
        0 15px;

    border:
        1px solid
        rgba(24,216,206,.18);

    border-radius:12px;

    color:#16848b;

    background:
        rgba(24,216,206,.08);

    font-family:inherit;

    font-size:12px;

    font-weight:900;

    text-decoration:none;

    cursor:pointer;

}


.btn-danger{

    min-height:43px;

    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:7px;

    padding:
        0 15px;

    border:none;

    border-radius:12px;

    color:#b65e69;

    background:
        rgba(242,143,150,.10);

    font-family:inherit;

    font-size:12px;

    font-weight:900;

    cursor:pointer;

}


.qr-actions{

    display:flex;

    gap:8px;

}


.qr-actions > *{

    flex:1;

}


/* =========================================================
   FORMULARIO
========================================================= */

.create-card{

    margin-top:20px;

    padding-top:20px;

    border-top:
        1px solid
        rgba(50,111,130,.09);

}


.create-card h3{

    color:#416f7e;

    font-size:15px;

    font-weight:950;

}


.form-group{

    margin-top:13px;

}


.form-group label{

    display:block;

    margin-bottom:6px;

    color:#718f98;

    font-size:11px;

    font-weight:900;

}


.form-group select,
.form-group input{

    width:100%;

    height:45px;

    padding:
        0 12px;

    border:
        1px solid
        rgba(130,180,190,.20);

    border-radius:12px;

    outline:none;

    color:#416f7e;

    background:
        rgba(255,255,255,.70);

    font-family:inherit;

    font-size:12px;

    font-weight:750;

}


.form-group select:focus,
.form-group input:focus{

    border-color:
        rgba(24,216,206,.55);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.07);

}


/* =========================================================
   REGISTROS
========================================================= */

.records-card{

    min-width:0;

    padding:25px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:
        rgba(255,255,255,.76);

    box-shadow:
        0 22px 52px
        rgba(55,113,129,.07);

}


.records-header{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:15px;

    margin-bottom:18px;

}


.records-header h2{

    color:#416f7e;

    font-size:20px;

    font-weight:950;

}


.records-header p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.stats-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:10px;

    margin-bottom:18px;

}


.stat-box{

    padding:13px;

    border-radius:15px;

    background:
        rgba(255,255,255,.67);

    border:
        1px solid
        rgba(255,255,255,.90);

}


.stat-box span{

    display:block;

    color:#8aa1a8;

    font-size:10px;

    font-weight:800;

}


.stat-box strong{

    display:block;

    margin-top:4px;

    color:#356b7b;

    font-size:23px;

    font-weight:950;

}


.stat-box.present strong{

    color:#269d7c;

}


.stat-box.absent strong{

    color:#c66e78;

}


.table-wrapper{

    overflow-x:auto;

    border-radius:17px;

    border:
        1px solid
        rgba(50,111,130,.08);

}


.attendance-table{

    width:100%;

    min-width:620px;

    border-collapse:collapse;

}


.attendance-table th{

    padding:
        13px 12px;

    text-align:left;

    color:#7d989f;

    background:
        rgba(232,250,247,.56);

    font-size:10px;

    font-weight:950;

    letter-spacing:.5px;

}


.attendance-table td{

    padding:
        13px 12px;

    border-top:
        1px solid
        rgba(50,111,130,.06);

    color:#527c88;

    font-size:11px;

    font-weight:700;

}


.student-name{

    color:#416f7e !important;

    font-weight:900 !important;

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


.status.present{

    color:#218e72;

    background:
        rgba(66,205,161,.10);

}


.status.absent{

    color:#b65e69;

    background:
        rgba(242,143,150,.10);

}


.status.other{

    color:#826fc3;

    background:
        rgba(133,121,210,.10);

}


.no-records{

    padding:
        55px 20px;

    text-align:center;

    color:#8aa3aa;

    font-size:12px;

    font-weight:750;

}


.no-records i{

    display:block;

    margin-bottom:10px;

    color:#8dc6c5;

    font-size:40px;

}


/* =========================================================
   SESIONES
========================================================= */

.sessions-card{

    padding:25px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.80),
            rgba(236,250,248,.68)
        );

    box-shadow:
        0 22px 52px
        rgba(55,113,129,.07);

}


.sessions-card h2{

    color:#416f7e;

    font-size:20px;

    font-weight:950;

}


.sessions-card > p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


.sessions-list{

    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:10px;

    margin-top:18px;

}


.session-item{

    display:flex;

    align-items:center;

    gap:11px;

    padding:13px;

    border-radius:15px;

    background:
        rgba(255,255,255,.65);

    border:
        1px solid
        rgba(255,255,255,.86);

}


.session-icon{

    width:43px;
    height:43px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:13px;

    color:#0a9995;

    background:
        rgba(24,216,206,.10);

    font-size:19px;

}


.session-info{

    min-width:0;

    flex:1;

}


.session-info strong{

    display:block;

    overflow:hidden;

    color:#456f7c;

    font-size:12px;

    font-weight:900;

    white-space:nowrap;

    text-overflow:ellipsis;

}


.session-info small{

    display:block;

    margin-top:4px;

    color:#8aa2a9;

    font-size:10px;

    font-weight:700;

}


.session-actions{

    display:flex;

    align-items:center;

    gap:5px;

}


/* =========================================================
   ALERTA
========================================================= */

.alert{

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        13px 15px;

    border-radius:14px;

    font-size:12px;

    font-weight:800;

}


.alert.error{

    color:#a85863;

    background:
        rgba(242,143,150,.10);

    border:
        1px solid
        rgba(242,143,150,.14);

}


.alert.success{

    color:#258b70;

    background:
        rgba(66,205,161,.10);

    border:
        1px solid
        rgba(66,205,161,.14);

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

    .sessions-list{

        grid-template-columns:1fr;

    }

    .stats-grid{

        grid-template-columns:1fr;

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

    .qr-card,
    .records-card,
    .sessions-card{

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

                <span>Inicio</span>

                <span class="nav-arrow">→</span>

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

                <span>Cursos</span>

                <span class="nav-arrow">→</span>

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

                <span>Docentes</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="usuarios.php"
                class="nav-link"
            >

                <div class="nav-icon people">
                    <i class="bi bi-person-badge"></i>
                </div>

                <span>Usuarios</span>

                <span class="nav-arrow">→</span>

            </a>

        </div>


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

                <span>Asistencia</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="restaurante.php"
                class="nav-link"
            >

                <div class="nav-icon restaurant">
                    <i class="bi bi-egg-fried"></i>
                </div>

                <span>Restaurante</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="reportes.php"
                class="nav-link"
            >

                <div class="nav-icon reports">
                    <i class="bi bi-bar-chart-line"></i>
                </div>

                <span>Reportes</span>

                <span class="nav-arrow">→</span>

            </a>


            <a
                href="auditoria.php"
                class="nav-link"
            >

                <div class="nav-icon audit">
                    <i class="bi bi-shield-check"></i>
                </div>

                <span>Auditoría</span>

                <span class="nav-arrow">→</span>

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


<!-- =====================================================
     TOPBAR
====================================================== -->

<header class="topbar">


    <div class="page-info">

        <div class="page-indicator"></div>


        <div class="page-title">

            <h1>
                Control de asistencia
            </h1>

            <p>
                Genera el QR y supervisa la asistencia de tus cursos
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


<?php if ($mensaje !== ''): ?>

    <div class="alert <?= $tipoMensaje ?>">

        <i class="bi bi-exclamation-circle"></i>

        <?= htmlspecialchars(
            $mensaje
        ) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     QR + REGISTROS
====================================================== -->

<section class="attendance-layout">


<!-- =====================================================
     PANEL QR
====================================================== -->

<div class="qr-card">


    <h2>
        Código QR de asistencia
    </h2>


    <p>
        Los estudiantes pueden escanear este código
        para registrar su asistencia en la sesión activa.
    </p>


    <?php if ($sesionActual): ?>


        <div class="qr-session-info">

            <span>
                SESIÓN ACTUAL
            </span>

            <strong>
                <?= htmlspecialchars(
                    $sesionActual['nombre_curso']
                ) ?>
            </strong>

        </div>


        <?php if ($qrUrl !== ''): ?>


            <div class="qr-wrapper">

                <div
                    id="qrcode"
                ></div>

            </div>


            <div class="qr-actions">


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="imprimirQR()"
                >

                    <i class="bi bi-printer"></i>

                    Imprimir

                </button>


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="copiarQR()"
                >

                    <i class="bi bi-copy"></i>

                    Copiar enlace

                </button>


            </div>


            <form
                method="POST"
                style="margin-top:8px;"
            >

                <input
                    type="hidden"
                    name="id_sesion"
                    value="<?= $idSesionActual ?>"
                >

                <button
                    type="submit"
                    name="cerrar_sesion"
                    class="btn-danger"
                    style="width:100%;"
                    onclick="
                        return confirm(
                            '¿Deseas cerrar esta sesión de asistencia?'
                        );
                    "
                >

                    <i class="bi bi-stop-circle"></i>

                    Cerrar sesión

                </button>

            </form>


        <?php else: ?>


            <div class="qr-empty">

                <div class="qr-empty-icon">

                    <i class="bi bi-qr-code"></i>

                </div>

                <h3>
                    Sesión cerrada
                </h3>

                <p>
                    Esta sesión ya no está disponible
                    para recibir nuevos registros.
                </p>

            </div>


        <?php endif; ?>


    <?php else: ?>


        <div class="qr-empty">

            <div class="qr-empty-icon">

                <i class="bi bi-qr-code"></i>

            </div>


            <h3>
                No hay una sesión activa
            </h3>


            <p>
                Selecciona un curso y crea una sesión
                para generar automáticamente el código QR.
            </p>

        </div>


        <div class="create-card">


            <h3>
                Nueva sesión
            </h3>


            <form
                method="POST"
            >


                <div class="form-group">

                    <label>
                        CURSO
                    </label>


                    <select
                        name="id_curso"
                        required
                    >

                        <option value="">
                            Seleccionar curso
                        </option>


                        <?php foreach (
                            $cursos
                            as $curso
                        ): ?>

                            <option
                                value="<?= (int)$curso['id_curso'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $curso['nombre_curso']
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <div class="form-group">

                    <label>
                        HORA DE INICIO
                    </label>


                    <input
                        type="time"
                        name="hora_inicio"
                        value="<?= date('H:i') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <button
                        type="submit"
                        name="crear_sesion"
                        class="btn-primary"
                    >

                        <i class="bi bi-qr-code"></i>

                        Crear sesión y generar QR

                    </button>

                </div>


            </form>


        </div>


    <?php endif; ?>


</div>


<!-- =====================================================
     REGISTROS
====================================================== -->

<div class="records-card">


    <div class="records-header">

        <div>

            <h2>
                Registros de asistencia
            </h2>

            <p>

                <?php if ($sesionActual): ?>

                    <?= htmlspecialchars(
                        $sesionActual['nombre_curso']
                    ) ?>

                <?php else: ?>

                    Selecciona una sesión para consultar

                <?php endif; ?>

            </p>

        </div>

    </div>


    <div class="stats-grid">


        <div class="stat-box">

            <span>
                REGISTRADOS
            </span>

            <strong>
                <?= $totalRegistros ?>
            </strong>

        </div>


        <div class="stat-box present">

            <span>
                PRESENTES
            </span>

            <strong>
                <?= $totalPresentes ?>
            </strong>

        </div>


        <div class="stat-box absent">

            <span>
                AUSENTES
            </span>

            <strong>
                <?= $totalAusentes ?>
            </strong>

        </div>


    </div>


    <?php if ($idSesionActual > 0): ?>


        <div class="table-wrapper">


            <?php if (
                count(
                    $registrosAsistencia
                ) > 0
            ): ?>


                <table
                    class="attendance-table"
                >


                    <thead>

                        <tr>

                            <th>
                                ESTUDIANTE
                            </th>

                            <th>
                                DOCUMENTO
                            </th>

                            <th>
                                ESTADO
                            </th>

                            <th>
                                HORA
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $registrosAsistencia
                            as $registro
                        ): ?>


                            <?php

                            $estadoRegistro =
                                strtoupper(
                                    trim(
                                        $registro['estado']
                                        ?? ''
                                    )
                                );

                            $claseEstado = 'other';

                            if (
                                $estadoRegistro === 'PRESENTE'
                                ||
                                $estadoRegistro === 'ASISTIO'
                                ||
                                $estadoRegistro === 'ASISTENCIA'
                            ) {

                                $claseEstado =
                                    'present';

                            }

                            if (
                                $estadoRegistro === 'AUSENTE'
                                ||
                                $estadoRegistro === 'FALTA'
                            ) {

                                $claseEstado =
                                    'absent';

                            }

                            ?>


                            <tr>


                                <td class="student-name">

                                    <?= htmlspecialchars(
                                        trim(
                                            $registro['nombres']
                                            . ' '
                                            . $registro['apellidos']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $registro['documento']
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="status <?= $claseEstado ?>"
                                    >

                                        <i
                                            class="bi
                                            <?=
                                                $claseEstado === 'present'
                                                ? 'bi-check-circle'
                                                :
                                                (
                                                    $claseEstado === 'absent'
                                                    ? 'bi-x-circle'
                                                    : 'bi-clock'
                                                )
                                            ?>"
                                        ></i>


                                        <?= htmlspecialchars(
                                            $registro['estado']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        substr(
                                            $registro[
                                                'hora_registro'
                                            ]
                                            ?? '',
                                            0,
                                            5
                                        )
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            <?php else: ?>


                <div class="no-records">

                    <i class="bi bi-people"></i>

                    Todavía no hay registros
                    para esta sesión.

                </div>


            <?php endif; ?>


        </div>


    <?php else: ?>


        <div class="no-records">

            <i class="bi bi-qr-code"></i>

            Crea o selecciona una sesión
            para visualizar la asistencia.

        </div>


    <?php endif; ?>


</div>


</section>


<!-- =====================================================
     SESIONES DE HOY
====================================================== -->

<section class="sessions-card">


    <h2>
        Sesiones de hoy
    </h2>


    <p>
        Selecciona una sesión para mostrar su QR
        y consultar sus registros.
    </p>


    <div class="sessions-list">


        <?php if (
            count($sesionesHoy) > 0
        ): ?>


            <?php foreach (
                $sesionesHoy
                as $sesion
            ): ?>


                <div class="session-item">


                    <div class="session-icon">

                        <i class="bi bi-calendar-check"></i>

                    </div>


                    <div class="session-info">


                        <strong>

                            <?= htmlspecialchars(
                                $sesion[
                                    'nombre_curso'
                                ]
                            ) ?>

                        </strong>


                        <small>

                            <?= htmlspecialchars(
                                substr(
                                    $sesion[
                                        'hora_inicio'
                                    ],
                                    0,
                                    5
                                )
                            ) ?>

                            ·

                            <?= htmlspecialchars(
                                trim(
                                    $sesion[
                                        'docente'
                                    ]
                                )
                                ?:
                                'Administrador'
                            ) ?>

                        </small>


                    </div>


                    <div class="session-actions">


                        <a
                            href="asistencia.php?sesion=<?= (int)$sesion['id_sesion'] ?>"
                            class="btn-secondary"
                        >

                            <i class="bi bi-eye"></i>

                            Ver

                        </a>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="no-records">

                <i class="bi bi-calendar2"></i>

                No hay sesiones registradas
                para hoy.

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

    const reloj =
        document.getElementById('reloj');

    if (!reloj) {
        return;
    }

    const ahora =
        new Date();

    const horas =
        String(
            ahora.getHours()
        ).padStart(2, '0');

    const minutos =
        String(
            ahora.getMinutes()
        ).padStart(2, '0');

    const segundos =
        String(
            ahora.getSeconds()
        ).padStart(2, '0');

    reloj.textContent =
        horas + ':' +
        minutos + ':' +
        segundos;

}

setInterval(
    actualizarReloj,
    1000
);


/* =========================================================
   GENERAR QR DE LA SESIÓN
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        const contenedorQR =
            document.getElementById(
                'qrcode'
            );


        if (!contenedorQR) {

            return;

        }


        /*
         * URL generada por PHP
         */

        const urlQR =
            <?= json_encode($qrUrl) ?>;


        /*
         * Verificar que exista
         * la URL de la sesión
         */

        if (!urlQR) {

            console.warn(
                'No existe una URL para generar el QR.'
            );

            return;

        }


        /*
         * Verificar que la librería
         * QRCode esté disponible
         */

        if (
            typeof QRCode ===
            'undefined'
        ) {

            console.error(
                'La librería QRCode no fue cargada.'
            );

            contenedorQR.innerHTML = `

                <div style="
                    text-align:center;
                    color:#a85863;
                    font-size:12px;
                    font-weight:800;
                    padding:25px;
                ">

                    <i
                        class="bi bi-exclamation-triangle"
                        style="
                            display:block;
                            font-size:32px;
                            margin-bottom:10px;
                        "
                    ></i>

                    No fue posible cargar
                    el generador de QR.

                </div>

            `;

            return;

        }


        /*
         * Limpiar contenedor
         */

        contenedorQR.innerHTML =
            '';


        /*
         * GENERAR QR
         */

        new QRCode(
            contenedorQR,
            {

                text: urlQR,

                width:195,

                height:195,

                colorDark:'#15576c',

                colorLight:'#ffffff',

                correctLevel:
                    QRCode.CorrectLevel.H

            }
        );


        /*
         * Guardar URL para
         * copiar posteriormente
         */

        window.urlSesionQR =
            urlQR;

    }
);


/* =========================================================
   COPIAR ENLACE DEL QR
========================================================= */

function copiarQR()
{

    const url =
        window.urlSesionQR ||
        <?= json_encode($qrUrl) ?>;


    if (!url) {

        alert(
            'No existe un enlace de sesión para copiar.'
        );

        return;

    }


    if (
        navigator.clipboard &&
        navigator.clipboard.writeText
    ) {

        navigator.clipboard
            .writeText(url)
            .then(
                function()
                {

                    alert(
                        'Enlace copiado correctamente.'
                    );

                }
            )
            .catch(
                function()
                {

                    copiarQRAlternativo(
                        url
                    );

                }
            );

    }
    else {

        copiarQRAlternativo(
            url
        );

    }

}


/* =========================================================
   COPIAR ENLACE ALTERNATIVO
========================================================= */

function copiarQRAlternativo(url)
{

    const textarea =
        document.createElement(
            'textarea'
        );

    textarea.value =
        url;

    textarea.style.position =
        'fixed';

    textarea.style.left =
        '-9999px';

    document.body.appendChild(
        textarea
    );

    textarea.select();

    try {

        document.execCommand(
            'copy'
        );

        alert(
            'Enlace copiado correctamente.'
        );

    }
    catch(error) {

        alert(
            'No fue posible copiar el enlace.'
        );

    }

    document.body.removeChild(
        textarea
    );

}


/* =========================================================
   IMPRIMIR QR
========================================================= */

function imprimirQR()
{

    const contenedorQR =
        document.getElementById(
            'qrcode'
        );


    if (
        !contenedorQR ||
        !contenedorQR.innerHTML.trim()
    ) {

        alert(
            'No hay un código QR para imprimir.'
        );

        return;

    }


    const ventana =
        window.open(
            '',
            '_blank',
            'width=700,height=800'
        );


    if (!ventana) {

        alert(
            'El navegador bloqueó la ventana de impresión.'
        );

        return;

    }


    ventana.document.write(`

        <!DOCTYPE html>

        <html lang="es">

        <head>

            <meta charset="UTF-8">

            <title>
                Código QR - Asistencia
            </title>

            <style>

                body{

                    margin:0;

                    min-height:100vh;

                    display:flex;

                    align-items:center;

                    justify-content:center;

                    font-family:
                        Arial,
                        sans-serif;

                    text-align:center;

                }

                .contenedor{

                    padding:40px;

                }

                h1{

                    color:#15576c;

                    margin-bottom:10px;

                }

                p{

                    color:#7898a2;

                    margin-bottom:25px;

                }

                img{

                    width:300px;

                    height:300px;

                }

            </style>

        </head>

        <body>

            <div class="contenedor">

                <h1>
                    Asistencia QR
                </h1>

                <p>
                    Escanea este código para registrar tu asistencia
                </p>

                ${contenedorQR.innerHTML}

            </div>

        </body>

        </html>

    `);

    ventana.document.close();

    ventana.focus();

    setTimeout(
        function()
        {

            ventana.print();

        },
        500
    );

}

</script>


</body>

</html>

