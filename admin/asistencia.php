<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');

/* =========================================================
   USUARIO DOCENTE
========================================================= */

$idUsuario = (int) $_SESSION['id_usuario'];

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
   CREAR SESIÓN
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['accion'])
) {

    if ($_POST['accion'] === 'crear_sesion') {

        $idCurso = (int) (
            $_POST['id_curso'] ?? 0
        );

        if ($idCurso <= 0) {

            $mensaje =
                'Selecciona un curso válido.';

            $tipoMensaje =
                'error';

        } else {

            /* =================================================
               VERIFICAR QUE EL DOCENTE TENGA EL CURSO
            ================================================= */

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

                    /* =========================================
                       DATOS DE LA SESIÓN
                    ========================================= */

                    $fecha =
                        date('Y-m-d');

                    $horaInicio =
                        date('Y-m-d H:i:s');

                    $estado =
                        'ABIERTA';


                    /* =========================================
                       INSERTAR SESIÓN
                    ========================================= */

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
    ===================================================== */

    if ($_POST['accion'] === 'cerrar_sesion') {

        $idSesionCerrar =
            (int) (
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
   CARGAR CURSOS DEL DOCENTE
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
    (int) (
        $_GET['sesion'] ?? 0
    );

$sesionActual = null;

if ($idSesionSeleccionada > 0) {

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


/* =========================================================
   SI NO HAY SESIÓN SELECCIONADA,
   BUSCAR UNA ABIERTA
========================================================= */

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
   CARGAR ASISTENCIAS
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
            ON e.id_estudiante =
               a.id_estudiante
        WHERE a.id_sesion = ?
        ORDER BY
            a.hora_registro DESC
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
    Asistencia QR | Docente
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<script
    src="https://unpkg.com/html5-qrcode"
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
   HERO
========================================================= */

.hero{

    padding:
        25px 28px;

    border:
        1px solid
        rgba(255,255,255,.94);

    border-radius:28px;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.88),
            rgba(234,250,247,.72)
        );

    box-shadow:
        0 22px 52px
        rgba(55,113,129,.08);

}


.hero-tag{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:
        7px 11px;

    border-radius:10px;

    color:#087d92;

    background:
        rgba(24,216,206,.10);

    font-size:10px;

    font-weight:950;

    letter-spacing:1px;

}


.hero h2{

    margin-top:13px;

    color:#15576c;

    font-size:25px;

    font-weight:950;

}


.hero p{

    max-width:720px;

    margin-top:6px;

    color:#7898a2;

    font-size:13px;

    font-weight:650;

    line-height:1.5;

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
   CONTENEDOR PRINCIPAL
========================================================= */

.attendance-layout{

    display:grid;

    grid-template-columns:
        minmax(330px,.85fr)
        minmax(0,1.55fr);

    gap:18px;

}


/* =========================================================
   TARJETAS
========================================================= */

.attendance-card,
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


/* =========================================================
   TITULOS
========================================================= */

.attendance-card h2,
.records-header h2{

    color:#416f7e;

    font-size:20px;

    font-weight:950;

}


.attendance-card > p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

    line-height:1.5;

}


/* =========================================================
   CREAR SESIÓN
========================================================= */

.create-section{

    margin-top:20px;

    padding-top:20px;

    border-top:
        1px solid
        rgba(50,111,130,.09);

}


.create-section h3{

    color:#416f7e;

    font-size:15px;

    font-weight:950;

}


.create-section p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

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


.form-group select{

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


.form-group select:focus{

    border-color:
        rgba(24,216,206,.55);

    box-shadow:
        0 0 0 4px
        rgba(24,216,206,.07);

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


.btn-danger{

    width:100%;

    min-height:43px;

    display:flex;

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


/* =========================================================
   SESIÓN ACTUAL
========================================================= */

.current-session{

    margin-top:20px;

    padding:17px;

    border-radius:17px;

    background:
        rgba(232,250,247,.55);

    border:
        1px solid
        rgba(24,216,206,.10);

}


.current-label{

    color:#8aa2aa;

    font-size:10px;

    font-weight:900;

    letter-spacing:.7px;

}


.current-course{

    margin-top:5px;

    color:#356d7d;

    font-size:18px;

    font-weight:950;

}


.current-date{

    margin-top:4px;

    color:#8aa2a9;

    font-size:11px;

    font-weight:700;

}


.session-status{

    display:inline-flex;

    align-items:center;

    gap:6px;

    margin-top:11px;

    padding:
        7px 10px;

    border-radius:9px;

    color:#218e72;

    background:
        rgba(66,205,161,.10);

    font-size:9px;

    font-weight:950;

}


.session-actions{

    margin-top:13px;

}


/* =========================================================
   ESCÁNER
========================================================= */

.scanner-section{

    margin-top:20px;

    padding-top:20px;

    border-top:
        1px solid
        rgba(50,111,130,.09);

}


.scanner-title{

    color:#416f7e;

    font-size:15px;

    font-weight:950;

}


.scanner-description{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

    line-height:1.5;

}


#qr-reader{

    width:100%;

    max-width:420px;

    margin:
        18px auto 0;

    overflow:hidden;

    border:
        1px solid
        rgba(24,216,206,.15);

    border-radius:20px;

    background:#fff;

    box-shadow:
        0 14px 35px
        rgba(55,113,129,.10);

}


#qr-reader video{

    width:100% !important;

    height:auto !important;

    border-radius:18px;

}


#qr-reader__scan_region{

    min-height:250px;

}


#qr-reader__dashboard{

    padding:10px;

    color:#7898a2;

    font-size:11px;

}


#qr-reader__dashboard button{

    border:none;

    border-radius:10px;

    padding:8px 12px;

    color:#fff;

    background:#1599ad;

    font-weight:800;

    cursor:pointer;

}


.scan-message{

    min-height:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    margin-top:10px;

    padding:
        9px 12px;

    border-radius:12px;

    text-align:center;

    font-size:11px;

    font-weight:850;

}


.scan-message.info{

    color:#16848b;

    background:
        rgba(24,216,206,.08);

}


.scan-message.success{

    color:#218e72;

    background:
        rgba(66,205,161,.10);

}


.scan-message.error{

    color:#b65e69;

    background:
        rgba(242,143,150,.10);

}


/* =========================================================
   ESTADÍSTICAS
========================================================= */

.stats-grid{

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:10px;

    margin-top:18px;

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


.stat-box.excuse strong{

    color:#826fc3;

}


/* =========================================================
   CABECERA REGISTROS
========================================================= */

.records-header{

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:15px;

}


.records-header p{

    margin-top:5px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}


/* =========================================================
   TABLA
========================================================= */

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


/* =========================================================
   ESTADOS
========================================================= */

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


.status.other{

    color:#826fc3;

    background:
        rgba(133,121,210,.10);

}


/* =========================================================
   SIN REGISTROS
========================================================= */

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

    .attendance-card,
    .records-card,
    .hero{

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
                    Mis cursos
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
                class="nav-link active"
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
                Asistencia
            </h1>

            <p>
                Control de asistencia mediante código QR
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


            <div
                class="clock-date"
                id="fecha"
            >

                <?= $fechaActual ?>

            </div>

        </div>


    </div>


</header>


<!-- =====================================================
     HERO
====================================================== -->

<section class="hero">


    <span class="hero-tag">

        <i class="bi bi-qr-code-scan"></i>

        CONTROL DE ASISTENCIA

    </span>


    <h2>
        Registro de asistencia
    </h2>


    <p>
        Crea una sesión y registra la asistencia de tus estudiantes mediante su código QR.
    </p>


</section>


<?php if ($mensaje !== ''): ?>


    <div class="alert <?= htmlspecialchars($tipoMensaje) ?>">


        <i
            class="bi
            <?= $tipoMensaje === 'success'
                ? 'bi-check-circle'
                : 'bi-exclamation-circle'
            ?>"
        ></i>


        <?= htmlspecialchars(
            $mensaje
        ) ?>


    </div>


<?php endif; ?>


<!-- =====================================================
     ASISTENCIA
====================================================== -->

<section class="attendance-layout">


<!-- =====================================================
     PANEL IZQUIERDO
====================================================== -->

<div class="attendance-card">


    <?php if (!$sesionActual): ?>


        <h2>
            Nueva sesión de asistencia
        </h2>


        <p>
            Selecciona el curso para comenzar una nueva sesión.
        </p>


        <div class="create-section">


            <h3>
                Curso
            </h3>


            <form
                method="POST"
            >


                <input
                    type="hidden"
                    name="accion"
                    value="crear_sesion"
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


                </div>


                <div class="form-group">


                    <button
                        type="submit"
                        class="btn-primary"
                    >

                        <i class="bi bi-play-circle"></i>

                        Iniciar sesión

                    </button>


                </div>


            </form>


        </div>


    <?php else: ?>


        <h2>
            Sesión actual
        </h2>


        <p>
            Sesión de asistencia activa para el curso seleccionado.
        </p>


        <div class="current-session">


            <div class="current-label">
                CURSO
            </div>


            <div class="current-course">

                <?= htmlspecialchars(
                    $sesionActual['nombre_curso']
                ) ?>

            </div>


            <div class="current-date">

                <?= htmlspecialchars(
                    date(
                        'd/m/Y',
                        strtotime(
                            $sesionActual['fecha']
                        )
                    )
                ) ?>

                &nbsp;

                <?= htmlspecialchars(
                    date(
                        'H:i',
                        strtotime(
                            $sesionActual['hora_inicio']
                        )
                    )
                ) ?>


            </div>


            <?php if (
                $sesionActual['estado']
                === 'ABIERTA'
            ): ?>


                <div class="session-status">

                    <i class="bi bi-circle-fill"></i>

                    SESIÓN ABIERTA

                </div>


                <div class="session-actions">


                    <form
                        method="POST"
                    >


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
                            class="btn-danger"
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


                </div>


            <?php else: ?>


                <div class="session-status">

                    <i class="bi bi-check-circle"></i>

                    SESIÓN CERRADA

                </div>


            <?php endif; ?>


        </div>


        <?php if (
            $sesionActual['estado']
            === 'ABIERTA'
        ): ?>


            <div class="scanner-section">


                <div class="scanner-title">

                    Escanear QR del estudiante

                </div>


                <div class="scanner-description">

                    Apunta la cámara al código QR personal del estudiante.

                </div>


                <div
                    id="qr-reader"
                ></div>


                <div
                    id="scan-message"
                    class="scan-message"
                ></div>


            </div>


        <?php endif; ?>


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

                Estudiantes registrados en esta sesión.

            </p>


        </div>


    </div>


    <div class="stats-grid">


        <div class="stat-box present">


            <span>
                PRESENTES
            </span>


            <strong>
                <?= $totalPresentes ?>
            </strong>


        </div>


        <div class="stat-box excuse">


            <span>
                EXCUSAS
            </span>


            <strong>
                <?= $totalExcusas ?>
            </strong>


        </div>


        <div class="stat-box">


            <span>
                REGISTROS
            </span>


            <strong>
                <?= count($asistencias) ?>
            </strong>


        </div>


    </div>


    <?php if ($sesionActual): ?>


        <?php if (
            count($asistencias) > 0
        ): ?>


            <div class="table-wrapper">


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
                            $asistencias
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

                            $claseEstado =
                                'other';

                            if (
                                $estadoRegistro
                                === 'PRESENTE'
                            ) {

                                $claseEstado =
                                    'present';
                            }

                            ?>


                            <tr>


                                <td
                                    class="student-name"
                                >

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
                                                : 'bi-clock'
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
                                            ] ?? '',
                                            0,
                                            5
                                        )
                                    ) ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <div class="no-records">


                <i class="bi bi-people"></i>


                Todavía no hay registros
                para esta sesión.


            </div>


        <?php endif; ?>


    <?php else: ?>


        <div class="no-records">


            <i class="bi bi-qr-code-scan"></i>


            Inicia una sesión para
            visualizar la asistencia.


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

function actualizarReloj() {

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

    const dia =
        String(
            ahora.getDate()
        ).padStart(2, '0');

    const mes =
        String(
            ahora.getMonth() + 1
        ).padStart(2, '0');

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
            horas + ':' +
            minutos + ':' +
            segundos;

    }

    if (fecha) {

        fecha.textContent =
            dia + '/' +
            mes + '/' +
            anio;

    }

}


actualizarReloj();


setInterval(
    actualizarReloj,
    1000
);


/* =========================================================
   DATOS DE LA SESIÓN
========================================================= */

const idSesion =
    <?= $sesionActual
        ? (int)$sesionActual['id_sesion']
        : 0 ?>;


const sesionAbierta =
    <?= (
        $sesionActual &&
        $sesionActual['estado'] === 'ABIERTA'
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
) {

    const elemento =
        document.getElementById(
            'scan-message'
        );

    if (!elemento) {
        return;
    }

    elemento.className =
        'scan-message ' + tipo;

    elemento.textContent =
        mensaje;

}


/* =========================================================
   REGISTRAR QR
========================================================= */

async function registrarQR(
    contenidoQR
) {

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


        if (documento === '') {

            throw new Error(
                'El código QR está vacío.'
            );

        }


        if (idSesion <= 0) {

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


        if (!resultado.success) {

            mostrarMensaje(
                resultado.mensaje ||
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
            resultado.mensaje +
            ' Estudiante: ' +
            resultado.estudiante +
            ' | Hora: ' +
            resultado.hora,
            'success'
        );


        setTimeout(
            function() {

                window.location.href =
                    'asistencia.php?sesion=' +
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
            error.message ||
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
        document.getElementById(
            'qr-reader'
        );


    if (!lector) {
        return;
    }


    if (
        typeof Html5Qrcode ===
        'undefined'
    ) {

        mostrarMensaje(
            'No se pudo cargar el lector QR.',
            'error'
        );

        return;

    }


    scanner =
        new Html5Qrcode(
            'qr-reader'
        );


    const configuracion = {

        fps:10,

        qrbox:{
            width:250,
            height:250
        },

        aspectRatio:1.0

    };


    scanner.start(

        {
            facingMode:'user'
        },

        configuracion,

        function(decodedText) {

            console.log(
                'Código QR detectado:',
                decodedText
            );


            registrarQR(
                decodedText
            );

        },

        function(errorMessage) {

            // El lector está buscando el QR.

        }

    ).catch(

        function(error) {

            console.error(
                'Error de cámara:',
                error
            );


            mostrarMensaje(
                'No fue posible iniciar la cámara. Verifica los permisos del navegador.',
                'error'
            );

        }

    );

}


/* =========================================================
   INICIAR AL CARGAR
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function() {

        if (
            sesionAbierta &&
            typeof Html5Qrcode !==
            'undefined'
        ) {

            iniciarScanner();

        }

    }
);

</script>


</body>

</html>