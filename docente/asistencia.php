<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


/* =========================================================
   DATOS DEL DOCENTE
========================================================= */

$idUsuario = (int)$_SESSION['id_usuario'];

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
   MENSAJES
========================================================= */

$mensaje = '';
$tipoMensaje = '';


/* =========================================================
   CREAR / CERRAR SESIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['accion'])
) {


    /* =====================================================
       CREAR SESIÓN
    ====================================================== */

    if ($_POST['accion'] === 'crear_sesion') {

        $idCurso = (int)(
            $_POST['id_curso'] ?? 0
        );


        if ($idCurso <= 0) {

            $mensaje =
                'Selecciona un curso válido.';

            $tipoMensaje =
                'error';

        } else {


            /* VERIFICAR QUE EL CURSO PERTENEZCA AL DOCENTE */

            $sqlVerificar = "
                SELECT id_curso
                FROM docente_curso
                WHERE id_usuario = ?
                AND id_curso = ?
                LIMIT 1
            ";

            $stmtVerificar =
                mysqli_prepare(
                    $conexion,
                    $sqlVerificar
                );


            if ($stmtVerificar) {

                mysqli_stmt_bind_param(
                    $stmtVerificar,
                    "ii",
                    $idUsuario,
                    $idCurso
                );

                mysqli_stmt_execute(
                    $stmtVerificar
                );

                $resultadoVerificar =
                    mysqli_stmt_get_result(
                        $stmtVerificar
                    );

                $cursoPermitido =
                    mysqli_fetch_assoc(
                        $resultadoVerificar
                    );

                mysqli_stmt_close(
                    $stmtVerificar
                );


                if (!$cursoPermitido) {

                    $mensaje =
                        'No tienes permiso para utilizar este curso.';

                    $tipoMensaje =
                        'error';

                } else {


                    $fecha =
                        date('Y-m-d');

                    $horaInicio =
                        date('Y-m-d H:i:s');

                    $estado =
                        'ABIERTA';


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
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ";


                    $stmtCrear =
                        mysqli_prepare(
                            $conexion,
                            $sqlCrear
                        );


                    if ($stmtCrear) {

                        mysqli_stmt_bind_param(
                            $stmtCrear,
                            "iisss",
                            $idUsuario,
                            $idCurso,
                            $fecha,
                            $horaInicio,
                            $estado
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

                            mysqli_stmt_close(
                                $stmtCrear
                            );


                            header(
                                "Location: asistencia.php?sesion="
                                . $idNuevaSesion
                            );

                            exit();


                        } else {

                            $mensaje =
                                'No fue posible crear la sesión.';

                            $tipoMensaje =
                                'error';

                            mysqli_stmt_close(
                                $stmtCrear
                            );

                        }


                    } else {

                        $mensaje =
                            'No fue posible preparar la sesión.';

                        $tipoMensaje =
                            'error';

                    }

                }

            } else {

                $mensaje =
                    'No fue posible verificar el curso.';

                $tipoMensaje =
                    'error';

            }

        }

    }


    /* =====================================================
       CERRAR SESIÓN
    ====================================================== */

    if (
        $_POST['accion']
        === 'cerrar_sesion'
    ) {

        $idSesionCerrar =
            (int)(
                $_POST['id_sesion'] ?? 0
            );


        if ($idSesionCerrar > 0) {

            $sqlCerrar = "
                UPDATE sesiones_clase
                SET estado = 'CERRADA'
                WHERE id_sesion = ?
                AND id_docente = ?
            ";


            $stmtCerrar =
                mysqli_prepare(
                    $conexion,
                    $sqlCerrar
                );


            if ($stmtCerrar) {

                mysqli_stmt_bind_param(
                    $stmtCerrar,
                    "ii",
                    $idSesionCerrar,
                    $idUsuario
                );

                mysqli_stmt_execute(
                    $stmtCerrar
                );

                mysqli_stmt_close(
                    $stmtCerrar
                );


                header(
                    "Location: asistencia.php?sesion="
                    . $idSesionCerrar
                );

                exit();

            }

        }

    }

}


/* =========================================================
   CARGAR CURSOS
========================================================= */

$cursos = [];

$sqlCursos = "
    SELECT
        c.id_curso,
        c.nombre_curso
    FROM docente_curso dc
    INNER JOIN cursos c
        ON c.id_curso = dc.id_curso
    WHERE dc.id_usuario = ?
    ORDER BY c.nombre_curso ASC
";


$stmtCursos =
    mysqli_prepare(
        $conexion,
        $sqlCursos
    );


if ($stmtCursos) {

    mysqli_stmt_bind_param(
        $stmtCursos,
        "i",
        $idUsuario
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

        $cursos[] =
            $fila;

    }


    mysqli_stmt_close(
        $stmtCursos
    );

}


/* =========================================================
   CARGAR SESIONES DEL DOCENTE
========================================================= */

$sesiones = [];

$sqlSesiones = "
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
    ORDER BY
        s.fecha DESC,
        s.hora_inicio DESC
";


$stmtSesiones =
    mysqli_prepare(
        $conexion,
        $sqlSesiones
    );


if ($stmtSesiones) {

    mysqli_stmt_bind_param(
        $stmtSesiones,
        "i",
        $idUsuario
    );

    mysqli_stmt_execute(
        $stmtSesiones
    );

    $resultadoSesiones =
        mysqli_stmt_get_result(
            $stmtSesiones
        );


    while (
        $fila =
        mysqli_fetch_assoc(
            $resultadoSesiones
        )
    ) {

        $sesiones[] =
            $fila;

    }


    mysqli_stmt_close(
        $stmtSesiones
    );

}


/* =========================================================
   SESIÓN SELECCIONADA
========================================================= */

$idSesionSeleccionada =
    (int)(
        $_GET['sesion'] ?? 0
    );

$sesionActual = null;


/* SESIÓN INDICADA POR URL */

if (
    $idSesionSeleccionada > 0
) {

    foreach (
        $sesiones
        as $sesion
    ) {

        if (
            (int)$sesion['id_sesion']
            === $idSesionSeleccionada
        ) {

            $sesionActual =
                $sesion;

            break;

        }

    }

}


/* SI NO HAY SESIÓN, BUSCAR ABIERTA */

if (!$sesionActual) {

    foreach (
        $sesiones
        as $sesion
    ) {

        if (
            $sesion['estado']
            === 'ABIERTA'
        ) {

            $sesionActual =
                $sesion;

            $idSesionSeleccionada =
                (int)$sesion['id_sesion'];

            break;

        }

    }

}


/* =========================================================
   ASISTENCIAS
========================================================= */

$asistencias = [];

$totalPresentes = 0;
$totalExcusas = 0;


if ($sesionActual) {

    $sqlAsistencia = "
        SELECT
            a.id_asistencia,
            a.id_sesion,
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
        ORDER BY a.hora_registro DESC
    ";


    $stmtAsistencia =
        mysqli_prepare(
            $conexion,
            $sqlAsistencia
        );


    if ($stmtAsistencia) {

        mysqli_stmt_bind_param(
            $stmtAsistencia,
            "i",
            $idSesionSeleccionada
        );

        mysqli_stmt_execute(
            $stmtAsistencia
        );


        $resultadoAsistencia =
            mysqli_stmt_get_result(
                $stmtAsistencia
            );


        while (
            $fila =
            mysqli_fetch_assoc(
                $resultadoAsistencia
            )
        ) {

            $asistencias[] =
                $fila;


            if (
                $fila['estado']
                === 'PRESENTE'
            ) {

                $totalPresentes++;

            }


            if (
                !empty(
                    $fila['estado_excusa']
                )
            ) {

                $totalExcusas++;

            }

        }


        mysqli_stmt_close(
            $stmtAsistencia
        );

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
    Asistencia QR | Asistencia
</title>


<!-- =====================================================
     ICONOS
====================================================== -->

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<!-- =====================================================
     LECTOR QR
====================================================== -->

<script
    src="https://unpkg.com/html5-qrcode"
></script>


<style>

/* =========================================================
   VARIABLES DEL DASHBOARD
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


a{

    text-decoration:none;

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
   ENCABEZADO
========================================================= */

.page-hero{

    position:relative;

    overflow:hidden;

    padding:
        27px 32px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:

        radial-gradient(
            circle at 90% 20%,
            rgba(24,216,206,.15),
            transparent 25%
        ),

        radial-gradient(
            circle at 65% 120%,
            rgba(133,121,210,.12),
            transparent 35%
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


.page-tag{

    display:inline-flex;

    align-items:center;

    gap:7px;

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


.page-hero h2{

    margin-top:12px;

    color:#15576c;

    font-size:31px;

    font-weight:950;

}


.page-hero p{

    max-width:850px;

    margin-top:8px;

    color:#7898a2;

    font-size:14px;

    font-weight:650;

    line-height:1.6;

}


/* =========================================================
   ALERTA
========================================================= */

.alert{

    display:flex;

    align-items:center;

    gap:10px;

    padding:
        14px 17px;

    border-radius:15px;

    font-size:12px;

    font-weight:800;

}


.alert-error{

    color:#a4535c;

    background:
        rgba(242,143,150,.10);

    border:
        1px solid
        rgba(242,143,150,.14);

}


/* =========================================================
   CARDS
========================================================= */

.card{

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


/* =========================================================
   TÍTULOS
========================================================= */

.card-header{

    padding:
        23px 25px 0;

}


.card-title{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.card-subtitle{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


/* =========================================================
   CREAR SESIÓN
========================================================= */

.create-card{

    padding:
        24px 25px;

}


.create-layout{

    display:grid;

    grid-template-columns:
        minmax(0,1fr)
        auto;

    gap:18px;

    align-items:end;

}


.form-group{

    min-width:0;

}


.form-label{

    display:block;

    margin-bottom:8px;

    color:#557f8b;

    font-size:11px;

    font-weight:900;

}


.select-wrapper{

    position:relative;

}


.select-wrapper i{

    position:absolute;

    right:15px;

    top:50%;

    transform:
        translateY(-50%);

    color:#6e9aa4;

    pointer-events:none;

}


select{

    width:100%;

    height:48px;

    appearance:none;

    border:
        1px solid
        rgba(76,152,168,.16);

    border-radius:13px;

    padding:
        0 43px 0 14px;

    outline:none;

    color:#456f7c;

    background:
        rgba(255,255,255,.85);

    font-family:inherit;

    font-size:13px;

    font-weight:750;

    transition:.2s;

}


select:focus{

    border-color:
        rgba(24,216,206,.55);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.07);

}


/* =========================================================
   BOTONES
========================================================= */

.btn{

    min-height:48px;

    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:8px;

    padding:
        0 19px;

    border:none;

    border-radius:13px;

    cursor:pointer;

    font-family:inherit;

    font-size:12px;

    font-weight:900;

    transition:.25s;

}


.btn:hover{

    transform:
        translateY(-2px);

}


.btn-primary{

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18d8ce,
            #159fae
        );

    box-shadow:
        0 10px 25px
        rgba(24,216,206,.16);

}


.btn-danger{

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #e99a78,
            #d9767f
        );

    box-shadow:
        0 10px 25px
        rgba(217,118,127,.13);

}


/* =========================================================
   SESIÓN ACTUAL
========================================================= */

.session-card{

    padding:
        24px 25px;

}


.session-layout{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

}


.session-main{

    min-width:0;

}


.session-title-row{

    display:flex;

    align-items:center;

    gap:10px;

}


.session-icon{

    width:47px;
    height:47px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:14px;

    color:#087d92;

    background:
        rgba(24,216,206,.10);

    font-size:21px;

}


.session-course{

    color:#315f70;

    font-size:21px;

    font-weight:950;

}


.session-details{

    display:flex;

    flex-wrap:wrap;

    gap:10px;

    margin-top:13px;

}


.detail-pill{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:
        8px 11px;

    border-radius:10px;

    color:#718f98;

    background:
        rgba(255,255,255,.65);

    font-size:11px;

    font-weight:800;

}


.detail-pill i{

    color:#0b9f9c;

}


/* =========================================================
   ESTADOS
========================================================= */

.status-badge{

    display:inline-flex;

    align-items:center;

    gap:6px;

    margin-top:13px;

    padding:
        7px 11px;

    border-radius:10px;

    font-size:10px;

    font-weight:950;

}


.status-open{

    color:#13866f;

    background:
        rgba(66,205,161,.11);

}


.status-closed{

    color:#a4535c;

    background:
        rgba(242,143,150,.10);

}


.status-dot{

    width:7px;
    height:7px;

    border-radius:50%;

    background:#42cda1;

}


.status-closed .status-dot{

    background:#d9858c;

}


/* =========================================================
   ESCÁNER
========================================================= */

.scanner-card{

    padding:
        24px 25px 26px;

}


.scanner-header{

    display:flex;

    align-items:center;

    gap:12px;

    margin-bottom:4px;

}


.scanner-icon{

    width:47px;
    height:47px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:14px;

    color:#087d92;

    background:
        rgba(24,216,206,.11);

    font-size:23px;

}


.scanner-title{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.scanner-description{

    margin:
        0 0 19px 59px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


/* CONTENEDOR DEL QR */

#qr-reader{

    width:100%;

    max-width:650px;

    margin:0 auto;

    overflow:hidden;

    border:
        1px solid
        rgba(255,255,255,.95) !important;

    border-radius:22px;

    background:#182022;

    box-shadow:
        0 20px 45px
        rgba(55,113,129,.10);

}


#qr-reader video{

    width:100% !important;

    border-radius:20px;

}


#qr-reader__scan_region{

    min-height:320px;

}


#qr-reader__dashboard{

    padding:12px !important;

    background:
        rgba(255,255,255,.96);

}


#qr-reader__dashboard button{

    border:none !important;

    border-radius:10px !important;

    padding:
        9px 15px !important;

    color:#176478 !important;

    background:
        rgba(24,216,206,.10) !important;

    font-family:inherit !important;

    font-weight:800 !important;

    cursor:pointer;

}


#qr-reader__dashboard select{

    height:40px;

    margin:5px;

    max-width:100%;

}


.scan-message{

    max-width:650px;

    margin:
        14px auto 0;

    padding:
        12px 15px;

    border-radius:12px;

    display:none;

    font-size:12px;

    font-weight:800;

    text-align:left;

}


.scan-message.success{

    display:block;

    color:#14755e;

    background:
        rgba(66,205,161,.10);

}


.scan-message.error{

    display:block;

    color:#a1515b;

    background:
        rgba(242,143,150,.10);

}


.scan-message.info{

    display:block;

    color:#486e79;

    background:
        rgba(105,184,213,.10);

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

    min-height:120px;

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
        translateY(-4px);

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

    font-size:26px;

}


.summary-item:nth-child(2)
.summary-icon{

    color:#7569c2;

    background:
        rgba(133,121,210,.10);

}


.summary-item:nth-child(3)
.summary-icon{

    color:#3aa47d;

    background:
        rgba(66,205,161,.10);

}


.summary-text span{

    display:block;

    color:#819da5;

    font-size:11px;

    font-weight:850;

}


.summary-text strong{

    display:block;

    margin-top:5px;

    color:#315f70;

    font-size:29px;

    font-weight:950;

    line-height:1;

}


/* =========================================================
   TABLA
========================================================= */

.table-card{

    padding:
        24px 25px;

}


.table-wrap{

    width:100%;

    overflow-x:auto;

    margin-top:20px;

}


table{

    width:100%;

    min-width:650px;

    border-collapse:collapse;

}


thead th{

    padding:
        12px 13px;

    text-align:left;

    color:#7d9aa3;

    border-bottom:
        1px solid
        rgba(50,111,130,.09);

    font-size:10px;

    font-weight:950;

    letter-spacing:.7px;

    text-transform:uppercase;

}


tbody td{

    padding:
        14px 13px;

    color:#587f8b;

    border-bottom:
        1px solid
        rgba(50,111,130,.06);

    font-size:12px;

    font-weight:650;

}


tbody tr{

    transition:.2s;

}


tbody tr:hover{

    background:
        rgba(24,216,206,.035);

}


.student-cell{

    display:flex;

    align-items:center;

    gap:10px;

}


.student-avatar{

    width:38px;
    height:38px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:11px;

    color:#087d92;

    background:
        rgba(24,216,206,.09);

    font-size:15px;

}


.student-name{

    color:#416f7e;

    font-size:12px;

    font-weight:900;

}


.document{

    color:#708f99;

    font-weight:750;

}


.status-table{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:
        6px 9px;

    border-radius:9px;

    color:#13866f;

    background:
        rgba(66,205,161,.10);

    font-size:9px;

    font-weight:950;

}


.status-table-dot{

    width:6px;
    height:6px;

    border-radius:50%;

    background:#42cda1;

}


.empty{

    padding:
        45px 20px;

    text-align:center;

    color:#8aa3aa;

    font-size:12px;

    font-weight:750;

}


.empty-icon{

    width:58px;
    height:58px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:
        0 auto 12px;

    border-radius:17px;

    color:#8dc6c5;

    background:
        rgba(24,216,206,.07);

    font-size:27px;

}


/* =========================================================
   BLOQUE SIN SESIÓN
========================================================= */

.no-session-card{

    padding:
        35px 25px;

}


.no-session{

    padding:
        25px;

    text-align:center;

}


.no-session-icon{

    width:64px;
    height:64px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:
        0 auto 13px;

    border-radius:19px;

    color:#7eb9bc;

    background:
        rgba(24,216,206,.08);

    font-size:29px;

}


.no-session strong{

    display:block;

    color:#416f7e;

    font-size:16px;

    font-weight:900;

}


.no-session p{

    margin-top:6px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

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

    .create-layout{

        grid-template-columns:1fr;

    }


    .session-layout{

        align-items:flex-start;

        flex-direction:column;

    }


    .session-layout .btn{

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


    .page-hero{

        padding:
            25px 22px;

    }


    .page-hero h2{

        font-size:26px;

    }


    .scanner-description{

        margin-left:0;

    }


    .scanner-card,
    .table-card,
    .session-card,
    .create-card{

        padding:
            20px 18px;

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


    .page-title h1{

        font-size:24px;

    }


    .page-title p{

        font-size:12px;

    }


    .session-course{

        font-size:18px;

    }


    .session-title-row{

        align-items:flex-start;

    }


    #qr-reader__dashboard{

        overflow:hidden;

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

                <span>
                    Mis cursos
                </span>

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
                class="nav-link active"
            >

                <div class="nav-icon qr-icon">

                    <i class="bi bi-qr-code-scan"></i>

                </div>

                <span>
                    Asistencia
                </span>

                <span class="nav-arrow"></span>

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

                <span class="nav-arrow"></span>

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


        <!--
            SE MANTIENE LA RUTA QUE YA FUNCIONA
            EN TU asistencia.php
        -->

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


            <span>
                Cerrar sesión
            </span>

        </a>


    </div>


</aside>


<!-- =====================================================
     CONTENIDO PRINCIPAL
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
                Asistencia
            </h1>

            <p>
                Control y registro de asistencia de tus estudiantes
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
                <?= htmlspecialchars(
                    $horaActual
                ) ?>
            </div>


            <div
                class="clock-date"
                id="fecha"
            >
                <?= htmlspecialchars(
                    $fechaActual
                ) ?>
            </div>

        </div>


    </div>


</header>


<!-- =====================================================
     ENCABEZADO
====================================================== -->

<section class="page-hero">


    <div class="page-tag">

        <i class="bi bi-qr-code-scan"></i>

        CONTROL DE ASISTENCIA

    </div>


    <h2>
        Registro de asistencia
    </h2>


    <p>

        Crea una sesión para uno de tus cursos
        y registra la asistencia de tus estudiantes
        mediante el escáner de códigos QR.

    </p>


</section>


<!-- =====================================================
     MENSAJE
====================================================== -->

<?php if ($mensaje !== ''): ?>


    <div class="alert alert-error">

        <i class="bi bi-exclamation-circle"></i>

        <span>
            <?= htmlspecialchars(
                $mensaje
            ) ?>
        </span>

    </div>


<?php endif; ?>


<!-- =====================================================
     CREAR SESIÓN
====================================================== -->

<section class="card create-card">


    <div class="create-layout">


        <div class="form-group">


            <div class="card-title">
                Nueva sesión de asistencia
            </div>


            <div class="card-subtitle">

                Selecciona el curso para comenzar
                una nueva sesión.

            </div>


            <form
                method="POST"
                style="margin-top:17px;"
            >


                <input
                    type="hidden"
                    name="accion"
                    value="crear_sesion"
                >


                <label class="form-label">

                    Curso

                </label>


                <div class="select-wrapper">


                    <select
                        name="id_curso"
                        required
                    >


                        <option value="">

                            Selecciona un curso

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


                    <i class="bi bi-chevron-down"></i>


                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                    style="margin-top:12px;"
                >

                    <i class="bi bi-plus-lg"></i>

                    Iniciar sesión

                </button>


            </form>


        </div>


    </div>


</section>


<?php if ($sesionActual): ?>


<!-- =====================================================
     SESIÓN ACTUAL
====================================================== -->

<section class="card session-card">


    <div class="session-layout">


        <div class="session-main">


            <div class="card-subtitle">
                SESIÓN ACTUAL
            </div>


            <div
                class="session-title-row"
                style="margin-top:8px;"
            >


                <div class="session-icon">

                    <i class="bi bi-calendar-check-fill"></i>

                </div>


                <div class="session-course">

                    <?= htmlspecialchars(
                        $sesionActual['nombre_curso']
                    ) ?>

                </div>


            </div>


            <div class="session-details">


                <div class="detail-pill">

                    <i class="bi bi-calendar3"></i>

                    <?= date(
                        'd/m/Y',
                        strtotime(
                            $sesionActual['fecha']
                        )
                    ) ?>

                </div>


                <div class="detail-pill">

                    <i class="bi bi-clock"></i>

                    <?= date(
                        'H:i',
                        strtotime(
                            $sesionActual['hora_inicio']
                        )
                    ) ?>

                </div>


            </div>


            <?php if (
                $sesionActual['estado']
                === 'ABIERTA'
            ): ?>


                <div class="status-badge status-open">

                    <span class="status-dot"></span>

                    SESIÓN ABIERTA

                </div>


            <?php else: ?>


                <div class="status-badge status-closed">

                    <span class="status-dot"></span>

                    SESIÓN CERRADA

                </div>


            <?php endif; ?>


        </div>


        <?php if (
            $sesionActual['estado']
            === 'ABIERTA'
        ): ?>


            <form method="POST">


                <input
                    type="hidden"
                    name="accion"
                    value="cerrar_sesion"
                >


                <input
                    type="hidden"
                    name="id_sesion"
                    value="<?= (int)$sesionActual['id_sesion'] ?>"
                >


                <button
                    type="submit"
                    class="btn btn-danger"

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


        <?php endif; ?>


    </div>


</section>


<!-- =====================================================
     ESCÁNER
====================================================== -->

<?php if (
    $sesionActual['estado']
    === 'ABIERTA'
): ?>


<section class="card scanner-card">


    <div class="scanner-header">


        <div class="scanner-icon">

            <i class="bi bi-qr-code-scan"></i>

        </div>


        <div>

            <div class="scanner-title">

                Escanear QR del estudiante

            </div>


        </div>


    </div>


    <div class="scanner-description">

        Apunta la cámara al código QR personal
        del estudiante para registrar su asistencia.

    </div>


    <div id="qr-reader"></div>


    <div
        id="scan-message"
        class="scan-message"
    ></div>


</section>


<?php endif; ?>


<!-- =====================================================
     RESUMEN
====================================================== -->

<section class="summary-grid">


    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-person-check-fill"></i>

        </div>


        <div class="summary-text">

            <span>
                Presentes
            </span>


            <strong>
                <?= $totalPresentes ?>
            </strong>

        </div>


    </div>


    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-file-earmark-check-fill"></i>

        </div>


        <div class="summary-text">

            <span>
                Excusas
            </span>


            <strong>
                <?= $totalExcusas ?>
            </strong>

        </div>


    </div>


    <div class="summary-item">


        <div class="summary-icon">

            <i class="bi bi-list-check"></i>

        </div>


        <div class="summary-text">

            <span>
                Registros
            </span>


            <strong>
                <?= count($asistencias) ?>
            </strong>

        </div>


    </div>


</section>


<!-- =====================================================
     TABLA
====================================================== -->

<section class="card table-card">


    <div class="card-title">

        Registros de asistencia

    </div>


    <div class="card-subtitle">

        Estudiantes registrados en esta sesión.

    </div>


    <div class="table-wrap">


        <?php if (
            !empty($asistencias)
        ): ?>


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

                        <th>
                            Hora
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $asistencias
                        as $asistencia
                    ): ?>


                        <tr>


                            <td>


                                <div class="student-cell">


                                    <div class="student-avatar">

                                        <i class="bi bi-person-fill"></i>

                                    </div>


                                    <div>

                                        <div class="student-name">

                                            <?= htmlspecialchars(
                                                $asistencia['nombres']
                                                . ' '
                                                . $asistencia['apellidos']
                                            ) ?>

                                        </div>

                                    </div>


                                </div>


                            </td>


                            <td>

                                <span class="document">

                                    <?= htmlspecialchars(
                                        $asistencia['documento']
                                    ) ?>

                                </span>

                            </td>


                            <td>


                                <span class="status-table">

                                    <span class="status-table-dot"></span>

                                    <?= htmlspecialchars(
                                        $asistencia['estado']
                                    ) ?>

                                </span>


                            </td>


                            <td>

                                <?= date(
                                    'H:i:s',
                                    strtotime(
                                        $asistencia['hora_registro']
                                    )
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">


                <div class="empty-icon">

                    <i class="bi bi-person-check"></i>

                </div>


                Todavía no hay estudiantes registrados
                en esta sesión.


            </div>


        <?php endif; ?>


    </div>


</section>


<?php else: ?>


<!-- =====================================================
     SIN SESIÓN
====================================================== -->

<section class="card no-session-card">


    <div class="no-session">


        <div class="no-session-icon">

            <i class="bi bi-calendar-x"></i>

        </div>


        <strong>

            No hay una sesión de asistencia abierta

        </strong>


        <p>

            Selecciona un curso y pulsa
            <strong>Iniciar sesión</strong>
            para comenzar a registrar asistencia.

        </p>


    </div>


</section>


<?php endif; ?>


</main>

</div>


<!-- =====================================================
     JAVASCRIPT
====================================================== -->

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


    const dia =
        String(
            ahora.getDate()
        ).padStart(
            2,
            '0'
        );


    const mes =
        String(
            ahora.getMonth() + 1
        ).padStart(
            2,
            '0'
        );


    const anio =
        ahora.getFullYear();


    const reloj =
        document.getElementById(
            'reloj'
        );


    const fecha =
        document.getElementById(
            'fecha'
        );


    if (reloj) {

        reloj.textContent =
            horas
            + ':'
            + minutos
            + ':'
            + segundos;

    }


    if (fecha) {

        fecha.textContent =
            dia
            + '/'
            + mes
            + '/'
            + anio;

    }

}


actualizarReloj();


setInterval(
    actualizarReloj,
    1000
);


/* =========================================================
   CONFIGURACIÓN DEL ESCÁNER
========================================================= */

const idSesion =
    <?= $sesionActual
        ? (int)$sesionActual['id_sesion']
        : 0 ?>;


const sesionAbierta =
    <?= (
        $sesionActual
        && $sesionActual['estado'] === 'ABIERTA'
    )
        ? 'true'
        : 'false' ?>;


let procesandoQR = false;

let scanner = null;


/* =========================================================
   MENSAJE DEL ESCÁNER
========================================================= */

function mostrarMensaje(
    mensaje,
    tipo
)
{

    const elemento =
        document.getElementById(
            'scan-message'
        );


    if (!elemento) {
        return;
    }


    elemento.className =
        'scan-message '
        + tipo;


    elemento.textContent =
        mensaje;

}


/* =========================================================
   REGISTRAR QR
========================================================= */

async function registrarQR(
    contenidoQR
)
{

    if (procesandoQR) {
        return;
    }


    procesandoQR = true;


    mostrarMensaje(
        'Procesando asistencia...',
        'info'
    );


    try {


        const documento =
            String(
                contenidoQR || ''
            ).trim();


        if (
            documento === ''
        ) {

            throw new Error(
                'El código QR está vacío.'
            );

        }


        if (
            idSesion <= 0
        ) {

            throw new Error(
                'No existe una sesión válida.'
            );

        }


        const datos =
            new FormData();


        datos.append(
            'documento',
            documento
        );


        datos.append(
            'id_sesion',
            idSesion
        );


        const respuesta =
            await fetch(
                'procesar_asistencia.php',
                {
                    method:'POST',
                    body:datos,
                    cache:'no-store'
                }
            );


        const texto =
            await respuesta.text();


        console.log(
            'Respuesta del servidor:',
            texto
        );


        let resultado;


        try {

            resultado =
                JSON.parse(
                    texto
                );

        } catch (errorJSON) {

            console.error(
                'Respuesta no válida:',
                texto
            );


            throw new Error(
                'El servidor devolvió una respuesta inesperada.'
            );

        }


        if (
            !resultado.success
        ) {

            mostrarMensaje(
                resultado.mensaje
                ||
                'No fue posible registrar la asistencia.',
                'error'
            );


            setTimeout(
                () => {

                    procesandoQR =
                        false;

                },
                1800
            );


            return;

        }


        mostrarMensaje(
            resultado.mensaje
            +
            ' Estudiante: '
            +
            resultado.estudiante
            +
            ' | Hora: '
            +
            resultado.hora,
            'success'
        );


        setTimeout(
            function(){

                window.location.href =
                    'asistencia.php?sesion='
                    +
                    idSesion;

            },
            1300
        );


    } catch (error) {


        console.error(
            'Error:',
            error
        );


        mostrarMensaje(
            error.message
            ||
            'No fue posible comunicarse con el servidor.',
            'error'
        );


        setTimeout(
            () => {

                procesandoQR =
                    false;

            },
            1800
        );

    }

}


/* =========================================================
   INICIAR ESCÁNER
========================================================= */

function iniciarScanner() {

    if (!sesionAbierta) {
        return;
    }

    const lector =
        document.getElementById('qr-reader');

    if (!lector) {
        return;
    }

    if (typeof Html5Qrcode === 'undefined') {

        mostrarMensaje(
            'No se pudo cargar el lector QR.',
            'error'
        );

        return;
    }


    scanner =
        new Html5Qrcode('qr-reader');


    const configuracion = {

        fps: 10,

        qrbox: {
            width: 250,
            height: 250
        },

        aspectRatio: 1.0

    };


    scanner.start(

        {
            facingMode: 'user'
        },

        configuracion,

        function(decodedText) {

            console.log(
                'Código QR detectado:',
                decodedText
            );

            registrarQR(decodedText);

        },

        function(errorMessage) {

            // El lector está buscando el QR.
            // No mostramos estos mensajes.

        }

    ).catch(function(error) {

        console.error(
            'Error de cámara:',
            error
        );

        mostrarMensaje(
            'No fue posible iniciar la cámara. Verifica los permisos del navegador.',
            'error'
        );

    });

}


/* =========================================================
   INICIAR CUANDO CARGUE LA PÁGINA
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        if (
            sesionAbierta
            &&
            typeof Html5Qrcode !== 'undefined'
        ) {

            iniciarScanner();

        }

    }
);

</script>


</body>

</html>