<?php

session_start();

/* =========================================================
   PROTECCIÓN DE SESIÓN
========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    header("Location: ../auth/login.php");
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
   MENSAJES
========================================================= */

$mensaje = '';
$tipoMensaje = '';


/* =========================================================
   CREAR SESIÓN DE CLASE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['crear_sesion'])
) {

    $idCurso = isset($_POST['id_curso'])
        ? (int)$_POST['id_curso']
        : 0;


    if ($idCurso <= 0) {

        $mensaje = 'Debes seleccionar un curso.';
        $tipoMensaje = 'error';

    } else {

        /* ---------------------------------------------
           VERIFICAR QUE EL CURSO PERTENECE AL DOCENTE
        --------------------------------------------- */

        $sqlVerificarCurso = "
            SELECT id_curso
            FROM docente_curso
            WHERE id_usuario = ?
            AND id_curso = ?
            LIMIT 1
        ";

        $stmtVerificarCurso = mysqli_prepare(
            $conexion,
            $sqlVerificarCurso
        );

        $cursoValido = false;

        if ($stmtVerificarCurso) {

            mysqli_stmt_bind_param(
                $stmtVerificarCurso,
                "ii",
                $idDocente,
                $idCurso
            );

            mysqli_stmt_execute(
                $stmtVerificarCurso
            );

            $resultadoVerificarCurso =
                mysqli_stmt_get_result(
                    $stmtVerificarCurso
                );

            if (
                mysqli_num_rows(
                    $resultadoVerificarCurso
                ) > 0
            ) {

                $cursoValido = true;

            }

            mysqli_stmt_close(
                $stmtVerificarCurso
            );

        }


        if (!$cursoValido) {

            $mensaje =
                'El curso seleccionado no está asignado a tu usuario.';

            $tipoMensaje = 'error';

        } else {

            /* -----------------------------------------
               VERIFICAR SI YA HAY UNA SESIÓN ABIERTA
               PARA ESE CURSO HOY
            ----------------------------------------- */

            $sqlSesionAbierta = "
                SELECT id_sesion
                FROM sesiones_clase
                WHERE id_docente = ?
                AND id_curso = ?
                AND fecha = ?
                AND estado = 'ABIERTA'
                LIMIT 1
            ";

            $stmtSesionAbierta = mysqli_prepare(
                $conexion,
                $sqlSesionAbierta
            );

            $idSesionExistente = 0;

            if ($stmtSesionAbierta) {

                mysqli_stmt_bind_param(
                    $stmtSesionAbierta,
                    "iis",
                    $idDocente,
                    $idCurso,
                    $fechaHoy
                );

                mysqli_stmt_execute(
                    $stmtSesionAbierta
                );

                $resultadoSesionAbierta =
                    mysqli_stmt_get_result(
                        $stmtSesionAbierta
                    );

                $filaSesionAbierta =
                    mysqli_fetch_assoc(
                        $resultadoSesionAbierta
                    );

                if ($filaSesionAbierta) {

                    $idSesionExistente =
                        (int)$filaSesionAbierta['id_sesion'];

                }

                mysqli_stmt_close(
                    $stmtSesionAbierta
                );

            }


            if ($idSesionExistente > 0) {

                header(
                    "Location: asistencia.php?sesion="
                    . $idSesionExistente
                );

                exit();

            }


            /* -----------------------------------------
               CREAR NUEVA SESIÓN
            ----------------------------------------- */

            $horaInicio = date('H:i:s');

            $sqlCrearSesion = "
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
                    'ABIERTA'
                )
            ";

            $stmtCrearSesion = mysqli_prepare(
                $conexion,
                $sqlCrearSesion
            );

            if ($stmtCrearSesion) {

                mysqli_stmt_bind_param(
                    $stmtCrearSesion,
                    "iiss",
                    $idDocente,
                    $idCurso,
                    $fechaHoy,
                    $horaInicio
                );

                if (
                    mysqli_stmt_execute(
                        $stmtCrearSesion
                    )
                ) {

                    $idNuevaSesion =
                        mysqli_insert_id(
                            $conexion
                        );

                    mysqli_stmt_close(
                        $stmtCrearSesion
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

                    mysqli_stmt_close(
                        $stmtCrearSesion
                    );

                }

            } else {

                $mensaje =
                    'No fue posible preparar la sesión.';

                $tipoMensaje = 'error';

            }

        }

    }

}


/* =========================================================
   CERRAR SESIÓN DE CLASE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['cerrar_sesion'])
) {

    $idSesionCerrar = isset($_POST['id_sesion'])
        ? (int)$_POST['id_sesion']
        : 0;


    if ($idSesionCerrar > 0) {

        $sqlCerrarSesion = "
            UPDATE sesiones_clase
            SET estado = 'CERRADA'
            WHERE id_sesion = ?
            AND id_docente = ?
            LIMIT 1
        ";

        $stmtCerrarSesion = mysqli_prepare(
            $conexion,
            $sqlCerrarSesion
        );

        if ($stmtCerrarSesion) {

            mysqli_stmt_bind_param(
                $stmtCerrarSesion,
                "ii",
                $idSesionCerrar,
                $idDocente
            );

            mysqli_stmt_execute(
                $stmtCerrarSesion
            );

            mysqli_stmt_close(
                $stmtCerrarSesion
            );

        }

    }

    header("Location: asistencia.php");
    exit();

}


/* =========================================================
   CURSOS DEL DOCENTE
========================================================= */

$cursosDocente = [];

$sqlCursos = "
    SELECT
        c.id_curso,
        c.nombre_curso
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
        $fila = mysqli_fetch_assoc(
            $resultadoCursos
        )
    ) {

        $cursosDocente[] = $fila;

    }

    mysqli_stmt_close(
        $stmtCursos
    );

}


/* =========================================================
   SESIONES DEL DOCENTE
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

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoSesiones
        )
    ) {

        $sesiones[] = $fila;

    }

    mysqli_stmt_close(
        $stmtSesiones
    );

}


/* =========================================================
   SESIÓN SELECCIONADA
========================================================= */

$idSesionActual = isset($_GET['sesion'])
    ? (int)$_GET['sesion']
    : 0;

$sesionActual = null;


/* =========================================================
   SI HAY SESIÓN SELECCIONADA
========================================================= */

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
        AND s.id_docente = ?
        LIMIT 1
    ";

    $stmtSesionActual = mysqli_prepare(
        $conexion,
        $sqlSesionActual
    );

    if ($stmtSesionActual) {

        mysqli_stmt_bind_param(
            $stmtSesionActual,
            "ii",
            $idSesionActual,
            $idDocente
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
   ASISTENCIAS DE LA SESIÓN
========================================================= */

$asistencias = [];

if ($sesionActual) {

    $sqlAsistencias = "
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

    $stmtAsistencias = mysqli_prepare(
        $conexion,
        $sqlAsistencias
    );

    if ($stmtAsistencias) {

        mysqli_stmt_bind_param(
            $stmtAsistencias,
            "i",
            $idSesionActual
        );

        mysqli_stmt_execute(
            $stmtAsistencias
        );

        $resultadoAsistencias =
            mysqli_stmt_get_result(
                $stmtAsistencias
            );

        while (
            $fila = mysqli_fetch_assoc(
                $resultadoAsistencias
            )
        ) {

            $asistencias[] = $fila;

        }

        mysqli_stmt_close(
            $stmtAsistencias
        );

    }

}


/* =========================================================
   LLEGADAS TARDE DE HOY
   SOLO ESTUDIANTES DE CURSOS DEL DOCENTE
========================================================= */

$llegadasTarde = [];

$sqlLlegadasTarde = "
    SELECT
        al.id_llegada,
        al.id_estudiante,
        al.fecha,
        al.hora_llegada,
        al.estado,
        e.documento,
        e.nombres,
        e.apellidos,
        c.nombre_curso
    FROM asistencia_llegada al

    INNER JOIN estudiantes e
        ON e.id_estudiante = al.id_estudiante

    INNER JOIN cursos c
        ON c.id_curso = e.id_curso

    INNER JOIN docente_curso dc
        ON dc.id_curso = e.id_curso

    WHERE dc.id_usuario = ?
    AND al.fecha = ?

    ORDER BY al.hora_llegada DESC
";

$stmtLlegadasTarde = mysqli_prepare(
    $conexion,
    $sqlLlegadasTarde
);

if ($stmtLlegadasTarde) {

    mysqli_stmt_bind_param(
        $stmtLlegadasTarde,
        "is",
        $idDocente,
        $fechaHoy
    );

    mysqli_stmt_execute(
        $stmtLlegadasTarde
    );

    $resultadoLlegadasTarde =
        mysqli_stmt_get_result(
            $stmtLlegadasTarde
        );

    while (
        $fila = mysqli_fetch_assoc(
            $resultadoLlegadasTarde
        )
    ) {

        $llegadasTarde[] = $fila;

    }

    mysqli_stmt_close(
        $stmtLlegadasTarde
    );

}

$totalLlegadasTarde =
    count($llegadasTarde);


/* =========================================================
   CONTADORES DE ASISTENCIA
========================================================= */

$totalPresentes = 0;
$totalAusentes = 0;
$totalTarde = 0;
$totalEvadieron = 0;

foreach ($asistencias as $asistencia) {

    switch ($asistencia['estado']) {

        case 'PRESENTE':
            $totalPresentes++;
            break;

        case 'AUSENTE':
            $totalAusentes++;
            break;

        case 'TARDE':
            $totalTarde++;
            break;

        case 'EVADIO':
            $totalEvadieron++;
            break;

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
    Asistencia QR | Asistencia docente
</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<script src="https://unpkg.com/html5-qrcode"></script>

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
   PAGE HEADER
========================================================= */

.page-card{

    position:relative;

    overflow:hidden;

    padding:
        25px 30px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:

        radial-gradient(
            circle at 90% 20%,
            rgba(24,216,206,.15),
            transparent 27%
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


.page-card-tag{

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

    font-size:30px;

    font-weight:950;

}


.page-card p{

    margin-top:8px;

    color:#7898a2;

    font-size:14px;

    font-weight:650;

}


/* =========================================================
   GRID PRINCIPAL
========================================================= */

.attendance-grid{

    display:grid;

    grid-template-columns:
        minmax(0,1.05fr)
        minmax(0,1.55fr);

    gap:16px;

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
   SELECTOR
========================================================= */

.session-card{

    padding:24px;

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


.form-label{

    display:block;

    margin-bottom:8px;

    color:#527b87;

    font-size:12px;

    font-weight:900;

}


.select-box{

    width:100%;

    min-height:48px;

    padding:
        0 14px;

    border:
        1px solid
        rgba(100,150,160,.16);

    border-radius:14px;

    outline:none;

    color:#456f7c;

    background:
        rgba(255,255,255,.78);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    font-size:13px;

    font-weight:750;

}


.select-box:focus{

    border-color:
        rgba(24,216,206,.55);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.08);

}


.primary-button{

    width:100%;

    min-height:49px;

    margin-top:14px;

    display:flex;

    align-items:center;

    justify-content:center;

    gap:9px;

    border:0;

    border-radius:14px;

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc6,
            #0a9d9b
        );

    box-shadow:
        0 12px 25px
        rgba(24,216,206,.16);

    cursor:pointer;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    font-size:13px;

    font-weight:900;

    transition:.25s;

}


.primary-button:hover{

    transform:
        translateY(-2px);

    box-shadow:
        0 16px 30px
        rgba(24,216,206,.22);

}


/* =========================================================
   SESIÓN ACTUAL
========================================================= */

.current-session{

    margin-top:18px;

    padding:14px;

    border-radius:16px;

    background:
        rgba(24,216,206,.065);

    border:
        1px solid
        rgba(24,216,206,.10);

}


.current-session-top{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

}


.current-session strong{

    color:#3f707d;

    font-size:13px;

    font-weight:950;

}


.status-open{

    padding:
        5px 8px;

    border-radius:8px;

    color:#15966f;

    background:
        rgba(66,205,161,.11);

    font-size:9px;

    font-weight:950;

}


.status-closed{

    padding:
        5px 8px;

    border-radius:8px;

    color:#b86e77;

    background:
        rgba(242,143,150,.10);

    font-size:9px;

    font-weight:950;

}


.current-session small{

    display:block;

    margin-top:5px;

    color:#819ca4;

    font-size:10px;

    font-weight:700;

}


.close-button{

    width:100%;

    min-height:42px;

    margin-top:12px;

    border:0;

    border-radius:12px;

    color:#a4535c;

    background:
        rgba(242,143,150,.08);

    cursor:pointer;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    font-size:11px;

    font-weight:900;

}


/* =========================================================
   ESCÁNER
========================================================= */

.scanner-card{

    padding:24px;

}


.scanner-box{

    position:relative;

    min-height:310px;

    overflow:hidden;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:20px;

    border:
        1px dashed
        rgba(24,216,206,.35);

    background:
        linear-gradient(
            145deg,
            rgba(236,250,248,.80),
            rgba(255,255,255,.72)
        );

}


#reader{

    width:100%;

    max-width:430px;

}


#reader video{

    border-radius:16px !important;

}


.scanner-placeholder{

    padding:35px 20px;

    text-align:center;

}


.scanner-placeholder i{

    display:block;

    margin-bottom:10px;

    color:#63bfc0;

    font-size:45px;

}


.scanner-placeholder strong{

    display:block;

    color:#4a7784;

    font-size:14px;

    font-weight:950;

}


.scanner-placeholder span{

    display:block;

    margin-top:6px;

    color:#8aa3aa;

    font-size:11px;

    font-weight:700;

}


.scanner-buttons{

    display:flex;

    gap:10px;

    margin-top:14px;

}


.scanner-button{

    flex:1;

    min-height:45px;

    border:0;

    border-radius:13px;

    cursor:pointer;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    font-size:12px;

    font-weight:900;

}


.start-button{

    color:#087d82;

    background:
        rgba(24,216,206,.11);

}


.stop-button{

    color:#a4535c;

    background:
        rgba(242,143,150,.09);

}


.scanner-message{

    min-height:18px;

    margin-top:10px;

    color:#7897a0;

    text-align:center;

    font-size:11px;

    font-weight:750;

}


/* =========================================================
   RESUMEN ASISTENCIA
========================================================= */

.attendance-summary{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:12px;

    margin-top:16px;

}


.mini-stat{

    padding:15px;

    border-radius:17px;

    background:
        rgba(255,255,255,.70);

    border:
        1px solid
        rgba(255,255,255,.90);

}


.mini-stat span{

    display:block;

    color:#819ca4;

    font-size:10px;

    font-weight:800;

}


.mini-stat strong{

    display:block;

    margin-top:4px;

    color:#416f7e;

    font-size:22px;

    font-weight:950;

}


.mini-stat.present strong{

    color:#239774;

}


.mini-stat.absent strong{

    color:#b86e77;

}


.mini-stat.late strong{

    color:#bd8a40;

}


.mini-stat.evaded strong{

    color:#8579d2;

}


/* =========================================================
   TABLA
========================================================= */

.table-card{

    padding:24px;

}


.table-container{

    width:100%;

    overflow-x:auto;

}


table{

    width:100%;

    border-collapse:collapse;

}


thead th{

    padding:
        11px 10px;

    color:#7897a0;

    border-bottom:
        1px solid
        rgba(80,130,140,.10);

    text-align:left;

    font-size:10px;

    font-weight:950;

    letter-spacing:.4px;

}


tbody td{

    padding:
        12px 10px;

    color:#527784;

    border-bottom:
        1px solid
        rgba(80,130,140,.06);

    font-size:11px;

    font-weight:700;

}


tbody tr:last-child td{

    border-bottom:0;

}


.student-name{

    color:#456f7c;

    font-weight:900;

}


.status-badge{

    display:inline-flex;

    align-items:center;

    padding:
        5px 8px;

    border-radius:8px;

    font-size:9px;

    font-weight:950;

}


.status-present{

    color:#198e70;

    background:
        rgba(66,205,161,.11);

}


.status-absent{

    color:#b86e77;

    background:
        rgba(242,143,150,.10);

}


.status-late{

    color:#bd8a40;

    background:
        rgba(209,161,88,.11);

}


.status-evaded{

    color:#766cc8;

    background:
        rgba(133,121,210,.10);

}


.empty-state{

    padding:
        35px 10px;

    text-align:center;

    color:#8aa3aa;

    font-size:12px;

    font-weight:750;

}


.empty-state i{

    display:block;

    margin-bottom:8px;

    color:#8dc6c5;

    font-size:32px;

}


/* =========================================================
   LLEGADAS TARDE
========================================================= */

.late-card{

    padding:24px;

}


.late-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-bottom:18px;

}


.late-title{

    display:flex;

    align-items:center;

    gap:11px;

}


.late-icon{

    width:43px;
    height:43px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:13px;

    color:#bd8a40;

    background:
        rgba(209,161,88,.12);

    font-size:20px;

}


.late-title h3{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}


.late-title p{

    margin-top:4px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


.late-count{

    min-width:42px;

    height:42px;

    display:flex;

    align-items:center;
    justify-content:center;

    padding:0 12px;

    border-radius:13px;

    color:#bd8a40;

    background:
        rgba(209,161,88,.12);

    font-size:16px;

    font-weight:950;

}


.late-list{

    display:flex;

    flex-direction:column;

    gap:9px;

}


.late-student{

    display:flex;

    align-items:center;

    gap:12px;

    padding:12px;

    border-radius:15px;

    background:
        rgba(255,255,255,.68);

}


.late-student-icon{

    width:40px;
    height:40px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:12px;

    color:#bd8a40;

    background:
        rgba(209,161,88,.10);

}


.late-student-info{

    flex:1;

    min-width:0;

}


.late-student-info strong{

    display:block;

    overflow:hidden;

    color:#456f7c;

    font-size:13px;

    font-weight:900;

    white-space:nowrap;

    text-overflow:ellipsis;

}


.late-student-info small{

    display:block;

    margin-top:3px;

    color:#8aa2a9;

    font-size:10px;

    font-weight:700;

}


.late-time{

    color:#bd8a40;

    font-size:12px;

    font-weight:950;

}


.no-late{

    padding:
        28px 10px;

    text-align:center;

    color:#8aa3aa;

    font-size:12px;

    font-weight:750;

}


.no-late i{

    display:block;

    margin-bottom:8px;

    color:#8dc6c5;

    font-size:30px;

}


/* =========================================================
   MENSAJES
========================================================= */

.alert-message{

    padding:
        13px 15px;

    border-radius:14px;

    font-size:12px;

    font-weight:800;

}


.alert-error{

    color:#a4535c;

    background:
        rgba(242,143,150,.09);

}


.alert-success{

    color:#198e70;

    background:
        rgba(66,205,161,.10);

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px){

    .sidebar{

        width:255px;

    }

    .attendance-grid{

        grid-template-columns:1fr;

    }

}


@media(max-width:1000px){

    .attendance-summary{

        grid-template-columns:
            repeat(2,1fr);

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

    .attendance-summary{

        grid-template-columns:1fr;

    }

    .late-student{

        flex-wrap:wrap;

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
            Asistencia
        </h1>

        <p>
            Registro de asistencia de tus cursos
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
     ENCABEZADO
====================================================== -->

<section class="page-card">

    <div class="page-card-tag">

        <i class="bi bi-qr-code-scan"></i>

        CONTROL DE ASISTENCIA

    </div>


    <h2>
        Toma de asistencia
    </h2>


    <p>
        Selecciona uno de tus cursos para abrir una sesión
        y registra la asistencia de tus estudiantes mediante
        el código QR.
    </p>

</section>


<?php if ($mensaje !== ''): ?>

    <div
        class="alert-message
        <?= $tipoMensaje === 'error'
            ? 'alert-error'
            : 'alert-success'
        ?>"
    >

        <?= htmlspecialchars($mensaje) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     SECCIÓN PRINCIPAL
====================================================== -->

<section class="attendance-grid">


<!-- =====================================================
     SESIÓN
====================================================== -->

<div class="card session-card">

    <div class="section-heading">

        <div>

            <h3>
                Sesión de clase
            </h3>

            <p>
                Selecciona el curso
                correspondiente.
            </p>

        </div>

    </div>


    <form
        method="POST"
        action="asistencia.php"
    >

        <label class="form-label">

            Curso

        </label>


        <select
            name="id_curso"
            class="select-box"
            required
        >

            <option value="">
                Selecciona un curso
            </option>


            <?php foreach (
                $cursosDocente
                as $curso
            ): ?>

                <option
                    value="<?= (int)$curso['id_curso'] ?>"
                    <?= (
                        $sesionActual
                        &&
                        (int)$sesionActual['id_curso']
                        === (int)$curso['id_curso']
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


        <button
            type="submit"
            name="crear_sesion"
            class="primary-button"
        >

            <i class="bi bi-play-circle-fill"></i>

            Abrir sesión

        </button>

    </form>


    <?php if ($sesionActual): ?>

        <div class="current-session">

            <div class="current-session-top">

                <strong>

                    <?= htmlspecialchars(
                        $sesionActual['nombre_curso']
                    ) ?>

                </strong>


                <?php if (
                    $sesionActual['estado']
                    === 'ABIERTA'
                ): ?>

                    <span class="status-open">
                        ABIERTA
                    </span>

                <?php else: ?>

                    <span class="status-closed">
                        CERRADA
                    </span>

                <?php endif; ?>

            </div>


            <small>

                Fecha:
                <?= htmlspecialchars(
                    date(
                        'd/m/Y',
                        strtotime(
                            $sesionActual['fecha']
                        )
                    )
                ) ?>

                &nbsp; • &nbsp;

                Hora:
                <?= htmlspecialchars(
                    substr(
                        $sesionActual['hora_inicio'],
                        0,
                        5
                    )
                ) ?>

            </small>


            <?php if (
                $sesionActual['estado']
                === 'ABIERTA'
            ): ?>

                <form
                    method="POST"
                    action="asistencia.php?sesion=<?= $idSesionActual ?>"
                >

                    <input
                        type="hidden"
                        name="id_sesion"
                        value="<?= $idSesionActual ?>"
                    >


                    <button
                        type="submit"
                        name="cerrar_sesion"
                        class="close-button"
                        onclick="
                            return confirm(
                                '¿Deseas cerrar esta sesión?'
                            );
                        "
                    >

                        <i class="bi bi-stop-circle"></i>

                        Cerrar sesión

                    </button>

                </form>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>


<!-- =====================================================
     ESCÁNER
====================================================== -->

<div class="card scanner-card">

    <div class="section-heading">

        <div>

            <h3>
                Escanear código QR
            </h3>

            <p>
                Coloca el código QR del estudiante
                frente a la cámara.
            </p>

        </div>

    </div>


    <?php if (
        $sesionActual
        &&
        $sesionActual['estado'] === 'ABIERTA'
    ): ?>


        <div class="scanner-box">

            <div id="reader">

                <div class="scanner-placeholder">

                    <i class="bi bi-qr-code-scan"></i>

                    <strong>
                        Cámara lista
                    </strong>

                    <span>
                        Presiona "Iniciar cámara"
                        para comenzar.
                    </span>

                </div>

            </div>

        </div>


        <div class="scanner-buttons">

            <button
                type="button"
                class="scanner-button start-button"
                id="btnIniciar"
            >

                <i class="bi bi-camera-fill"></i>

                Iniciar cámara

            </button>


            <button
                type="button"
                class="scanner-button stop-button"
                id="btnDetener"
            >

                <i class="bi bi-stop-circle"></i>

                Detener

            </button>

        </div>


        <div
            class="scanner-message"
            id="mensajeScanner"
        >

            Cámara detenida.

        </div>


        <form
            method="POST"
            action="procesar_asistencia.php"
            id="formQR"
        >

            <input
                type="hidden"
                name="documento"
                id="documentoQR"
            >

            <input
                type="hidden"
                name="id_sesion"
                value="<?= $idSesionActual ?>"
            >

        </form>


    <?php else: ?>


        <div class="scanner-box">

            <div class="scanner-placeholder">

                <i class="bi bi-camera-video-off"></i>

                <strong>
                    No hay una sesión abierta
                </strong>

                <span>
                    Selecciona un curso y abre una sesión
                    para activar el escáner.
                </span>

            </div>

        </div>


        <div class="scanner-message">

            Primero debes abrir una sesión de clase.

        </div>


    <?php endif; ?>

</div>

</section>


<!-- =====================================================
     RESUMEN
====================================================== -->

<?php if ($sesionActual): ?>

<section class="attendance-summary">

    <div class="mini-stat present">

        <span>
            Presentes
        </span>

        <strong>
            <?= $totalPresentes ?>
        </strong>

    </div>


    <div class="mini-stat absent">

        <span>
            Ausentes
        </span>

        <strong>
            <?= $totalAusentes ?>
        </strong>

    </div>


    <div class="mini-stat late">

        <span>
            Tarde
        </span>

        <strong>
            <?= $totalTarde ?>
        </strong>

    </div>


    <div class="mini-stat evaded">

        <span>
            Evadieron
        </span>

        <strong>
            <?= $totalEvadieron ?>
        </strong>

    </div>

</section>

<?php endif; ?>


<!-- =====================================================
     ASISTENCIAS DE LA SESIÓN
====================================================== -->

<?php if ($sesionActual): ?>

<section class="card table-card">

    <div class="section-heading">

        <div>

            <h3>
                Registros de la sesión
            </h3>

            <p>
                Estudiantes registrados en esta clase.
            </p>

        </div>

    </div>


    <div class="table-container">

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
                        ESTADO
                    </th>

                    <th>
                        HORA
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (
                    count($asistencias) > 0
                ): ?>


                    <?php foreach (
                        $asistencias
                        as $asistencia
                    ): ?>


                        <tr>

                            <td>

                                <span class="student-name">

                                    <?= htmlspecialchars(
                                        $asistencia['nombres']
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $asistencia['apellidos']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $asistencia['documento']
                                ) ?>

                            </td>


                            <td>

                                <?php

                                $claseEstado = 'status-present';

                                if (
                                    $asistencia['estado']
                                    === 'AUSENTE'
                                ) {

                                    $claseEstado =
                                        'status-absent';

                                } elseif (
                                    $asistencia['estado']
                                    === 'TARDE'
                                ) {

                                    $claseEstado =
                                        'status-late';

                                } elseif (
                                    $asistencia['estado']
                                    === 'EVADIO'
                                ) {

                                    $claseEstado =
                                        'status-evaded';

                                }

                                ?>


                                <span
                                    class="status-badge
                                    <?= $claseEstado ?>"
                                >

                                    <?= htmlspecialchars(
                                        $asistencia['estado']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        'h:i A',
                                        strtotime(
                                            $asistencia[
                                                'hora_registro'
                                            ]
                                        )
                                    )
                                ) ?>

                            </td>

                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="4"
                            class="empty-state"
                        >

                            <i class="bi bi-person-check"></i>

                            Todavía no hay estudiantes
                            registrados en esta sesión.

                        </td>

                    </tr>


                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php endif; ?>


<!-- =====================================================
     LLEGADAS TARDE
====================================================== -->

<section class="card late-card">

    <div class="late-header">

        <div class="late-title">

            <div class="late-icon">

                <i class="bi bi-clock-history"></i>

            </div>


            <div>

                <h3>
                    Llegadas tarde de hoy
                </h3>


                <p>
                    Estudiantes de tus cursos registrados
                    por el administrador al llegar tarde.
                </p>

            </div>

        </div>


        <div class="late-count">

            <?= $totalLlegadasTarde ?>

        </div>

    </div>


    <div class="late-list">


        <?php if (
            $totalLlegadasTarde > 0
        ): ?>


            <?php foreach (
                $llegadasTarde
                as $llegada
            ): ?>


                <div class="late-student">


                    <div class="late-student-icon">

                        <i class="bi bi-person-fill"></i>

                    </div>


                    <div class="late-student-info">

                        <strong>

                            <?= htmlspecialchars(
                                $llegada['nombres']
                            ) ?>

                            <?= htmlspecialchars(
                                $llegada['apellidos']
                            ) ?>

                        </strong>


                        <small>

                            Documento:

                            <?= htmlspecialchars(
                                $llegada['documento']
                            ) ?>

                            &nbsp; • &nbsp;

                            Curso:

                            <?= htmlspecialchars(
                                $llegada['nombre_curso']
                            ) ?>

                        </small>

                    </div>


                    <div class="late-time">

                        <?= htmlspecialchars(
                            date(
                                'h:i A',
                                strtotime(
                                    $llegada[
                                        'hora_llegada'
                                    ]
                                )
                            )
                        ) ?>

                    </div>

                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="no-late">

                <i class="bi bi-check2-circle"></i>

                No tienes estudiantes registrados
                con llegada tarde hoy.

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


/* =========================================================
   ESCÁNER QR
========================================================= */

let lectorQR = null;

let camaraActiva = false;

let procesandoQR = false;


const btnIniciar =
    document.getElementById(
        'btnIniciar'
    );


const btnDetener =
    document.getElementById(
        'btnDetener'
    );


const mensajeScanner =
    document.getElementById(
        'mensajeScanner'
    );


const documentoQR =
    document.getElementById(
        'documentoQR'
    );


const formQR =
    document.getElementById(
        'formQR'
    );


function mostrarMensajeScanner(
    mensaje
)
{

    if (mensajeScanner) {

        mensajeScanner.textContent =
            mensaje;

    }

}


/* =========================================================
   INICIAR CÁMARA
========================================================= */

async function iniciarCamara()
{

    if (!btnIniciar) {
        return;
    }


    if (camaraActiva) {

        mostrarMensajeScanner(
            'La cámara ya está activa.'
        );

        return;

    }


    if (
        typeof Html5Qrcode
        === 'undefined'
    ) {

        mostrarMensajeScanner(
            'No se pudo cargar el lector QR.'
        );

        return;

    }


    mostrarMensajeScanner(
        'Solicitando acceso a la cámara...'
    );


    try {

        lectorQR =
            new Html5Qrcode(
                'reader'
            );


        await lectorQR.start(

            {
                facingMode:
                    'environment'
            },

            {
                fps:10,

                qrbox:{
                    width:250,
                    height:250
                },

                aspectRatio:1.0

            },

            qrCodeMessage => {

                procesarQR(
                    qrCodeMessage
                );

            },

            errorMessage => {

                /*

                No mostramos cada error
                de lectura porque la cámara
                sigue funcionando normalmente.

                */

            }

        );


        camaraActiva = true;


        mostrarMensajeScanner(
            'Cámara activa. Coloca el QR frente a la cámara.'
        );


    } catch (error) {

        console.error(
            error
        );


        mostrarMensajeScanner(
            'No fue posible acceder a la cámara. Revisa los permisos del navegador.'
        );

    }

}


/* =========================================================
   DETENER CÁMARA
========================================================= */

async function detenerCamara()
{

    if (
        !lectorQR
        ||
        !camaraActiva
    ) {

        mostrarMensajeScanner(
            'La cámara ya está detenida.'
        );

        return;

    }


    try {

        await lectorQR.stop();


        lectorQR.clear();


        camaraActiva = false;


        mostrarMensajeScanner(
            'Cámara detenida.'
        );


    } catch (error) {

        console.error(
            error
        );

        mostrarMensajeScanner(
            'No fue posible detener la cámara.'
        );

    }

}


/* =========================================================
   PROCESAR QR
========================================================= */

function procesarQR(
    contenidoQR
)
{

    if (procesandoQR) {
        return;
    }


    const documento =
        String(
            contenidoQR
        ).trim();


    if (documento === '') {

        return;

    }


    procesandoQR = true;


    mostrarMensajeScanner(
        'QR detectado. Registrando asistencia...'
    );


    if (documentoQR) {

        documentoQR.value =
            documento;

    }


    if (!formQR) {

        mostrarMensajeScanner(
            'No se encontró el formulario de asistencia.'
        );

        procesandoQR = false;

        return;

    }


    const datos =
        new FormData(
            formQR
        );


    fetch(
        'procesar_asistencia.php',
        {
            method:'POST',
            body:datos
        }
    )

    .then(
        respuesta =>
            respuesta.json()
    )

    .then(
        resultado => {

            console.log(
                resultado
            );


            if (
                resultado.success
                ||
                resultado.ok
            ) {

                let nombre =
                    resultado.estudiante
                    || 'Estudiante';


                let estado =
                    resultado.estado
                    || 'PRESENTE';


                mostrarMensajeScanner(
                    nombre
                    + ' - '
                    + estado
                    + '. Asistencia registrada.'
                );


                setTimeout(
                    () => {

                        window.location.reload();

                    },
                    1200
                );


            } else {

                mostrarMensajeScanner(

                    resultado.message
                    ||
                    resultado.mensaje
                    ||
                    'No fue posible registrar la asistencia.'

                );


                procesandoQR = false;

            }

        }
    )

    .catch(
        error => {

            console.error(
                error
            );


            mostrarMensajeScanner(
                'Ocurrió un error al registrar la asistencia.'
            );


            procesandoQR = false;

        }
    );

}


/* =========================================================
   BOTONES
========================================================= */

if (btnIniciar) {

    btnIniciar.addEventListener(
        'click',
        iniciarCamara
    );

}


if (btnDetener) {

    btnDetener.addEventListener(
        'click',
        detenerCamara
    );

}


/* =========================================================
   DETENER CÁMARA AL SALIR
========================================================= */

window.addEventListener(
    'beforeunload',
    () => {

        if (
            lectorQR
            &&
            camaraActiva
        ) {

            lectorQR.stop()
                .catch(
                    () => {}
                );

        }

    }
);

</script>

</body>

</html>