<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/* =====================================================
   VERIFICAR SESIÓN
===================================================== */

if (!isset($_SESSION["id_usuario"])) {

    header("Location: ../auth/login.php");
    exit();

}

/* =====================================================
   VERIFICAR ROL RESTAURANTE
===================================================== */

if ($_SESSION["id_rol"] != 3) {

    header("Location: ../index.php");
    exit();

}

/* =====================================================
   CONEXIÓN
===================================================== */

require_once("../config/conexion.php");

date_default_timezone_set("America/Bogota");

/* =====================================================
   DATOS
===================================================== */

$nombre = $_SESSION["nombre"] ?? "";
$apellido = $_SESSION["apellido"] ?? "";

$fechaHoy = date("Y-m-d");
$horaHoy = date("H:i:s");

$mensaje = "";
$tipoMensaje = "";

/* =====================================================
   RECIBIR DOCUMENTO DEL QR
===================================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $documento = trim($_POST["documento"] ?? "");

    if ($documento == "") {

        $mensaje = "No se recibió ningún código QR.";
        $tipoMensaje = "warning";

    } else {

        /* =================================================
           BUSCAR ESTUDIANTE
        ================================================= */

        $sqlEstudiante = "
            SELECT
                id_estudiante,
                documento,
                nombres,
                apellidos
            FROM estudiantes
            WHERE documento = ?
            AND estado = 'ACTIVO'
            LIMIT 1
        ";

        $stmtEstudiante = mysqli_prepare(
            $conexion,
            $sqlEstudiante
        );

        if (!$stmtEstudiante) {

            $mensaje = "Error al preparar la búsqueda del estudiante.";
            $tipoMensaje = "danger";

        } else {

            mysqli_stmt_bind_param(
                $stmtEstudiante,
                "s",
                $documento
            );

            mysqli_stmt_execute(
                $stmtEstudiante
            );

            $resultadoEstudiante =
                mysqli_stmt_get_result(
                    $stmtEstudiante
                );

            $estudiante =
                mysqli_fetch_assoc(
                    $resultadoEstudiante
                );

            /* =================================================
               ESTUDIANTE NO ENCONTRADO
            ================================================= */

            if (!$estudiante) {

                $mensaje =
                    "No se encontró un estudiante activo con el documento: "
                    . htmlspecialchars($documento);

                $tipoMensaje = "danger";

            } else {

                $idEstudiante =
                    $estudiante["id_estudiante"];

                /* =================================================
                   VERIFICAR ASISTENCIA DE HOY
                ================================================= */

                $sqlExiste = "
                    SELECT
                        id_asistencia_restaurante
                    FROM asistencia_restaurante
                    WHERE id_estudiante = ?
                    AND fecha = ?
                    LIMIT 1
                ";

                $stmtExiste = mysqli_prepare(
                    $conexion,
                    $sqlExiste
                );

                if (!$stmtExiste) {

                    $mensaje =
                        "Error al verificar la asistencia.";

                    $tipoMensaje = "danger";

                } else {

                    mysqli_stmt_bind_param(
                        $stmtExiste,
                        "is",
                        $idEstudiante,
                        $fechaHoy
                    );

                    mysqli_stmt_execute(
                        $stmtExiste
                    );

                    $resultadoExiste =
                        mysqli_stmt_get_result(
                            $stmtExiste
                        );

                    /* =================================================
                       YA REGISTRADO
                    ================================================= */

                    if (
                        mysqli_num_rows(
                            $resultadoExiste
                        ) > 0
                    ) {

                        $mensaje =
                            "El estudiante "
                            . $estudiante["nombres"]
                            . " "
                            . $estudiante["apellidos"]
                            . " ya tiene asistencia registrada hoy.";

                        $tipoMensaje = "warning";

                    } else {

                        /* =================================================
                           INSERTAR ASISTENCIA
                        ================================================= */

                        $sqlInsertar = "
                            INSERT INTO asistencia_restaurante
                            (
                                id_estudiante,
                                fecha,
                                hora,
                                estado
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                'REGISTRADO'
                            )
                        ";

                        $stmtInsertar =
                            mysqli_prepare(
                                $conexion,
                                $sqlInsertar
                            );

                        if (!$stmtInsertar) {

                            $mensaje =
                                "Error al preparar el registro.";

                            $tipoMensaje = "danger";

                        } else {

                            mysqli_stmt_bind_param(
                                $stmtInsertar,
                                "iss",
                                $idEstudiante,
                                $fechaHoy,
                                $horaHoy
                            );

                            if (
                                mysqli_stmt_execute(
                                    $stmtInsertar
                                )
                            ) {

                                $mensaje =
                                    "Asistencia registrada correctamente: "
                                    . $estudiante["nombres"]
                                    . " "
                                    . $estudiante["apellidos"];

                                $tipoMensaje = "success";

                            } else {

                                $mensaje =
                                    "Ocurrió un error al registrar la asistencia.";

                                $tipoMensaje = "danger";

                            }

                            mysqli_stmt_close(
                                $stmtInsertar
                            );
                        }
                    }

                    mysqli_stmt_close(
                        $stmtExiste
                    );
                }
            }

            mysqli_stmt_close(
                $stmtEstudiante
            );
        }
    }
}

/* =====================================================
   CONSULTAR ASISTENCIAS DE HOY
===================================================== */

$sqlAsistencias = "
    SELECT
        ar.id_asistencia_restaurante,
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
    WHERE ar.fecha = ?
    ORDER BY ar.id_asistencia_restaurante DESC
";

$stmtAsistencias = mysqli_prepare(
    $conexion,
    $sqlAsistencias
);

$asistenciasHoy = [];

if ($stmtAsistencias) {

    mysqli_stmt_bind_param(
        $stmtAsistencias,
        "s",
        $fechaHoy
    );

    mysqli_stmt_execute(
        $stmtAsistencias
    );

    $resultadoAsistencias =
        mysqli_stmt_get_result(
            $stmtAsistencias
        );

    while (
        $fila =
        mysqli_fetch_assoc(
            $resultadoAsistencias
        )
    ) {

        $asistenciasHoy[] = $fila;

    }

    mysqli_stmt_close(
        $stmtAsistencias
    );
}

$totalHoy = count(
    $asistenciasHoy
);

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
    Tomar asistencia - Restaurante
</title>

<!-- BOOTSTRAP -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<!-- ICONOS -->

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<!-- LIBRERÍA QR -->

<script
    src="https://unpkg.com/html5-qrcode"
    type="text/javascript">
</script>

<style>

/* =====================================================
   GENERAL
===================================================== */

body {

    background:#f4f8fb;

    font-family:
        Arial,
        sans-serif;

}

/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    min-height:100vh;

    background:
        linear-gradient(
            180deg,
            #0f172a,
            #1e293b
        );

}

.sidebar h5 {

    color:white;

}

.sidebar a {

    color:#cbd5e1;

    text-decoration:none;

    display:block;

    padding:12px 15px;

    border-radius:10px;

    margin-bottom:5px;

}

.sidebar a:hover,
.sidebar a.active {

    background:
        rgba(255,255,255,.12);

    color:white;

}

/* =====================================================
   CONTENIDO
===================================================== */

.main-content {

    padding:30px;

}

.titulo {

    font-weight:700;

    color:#0f172a;

}

/* =====================================================
   TARJETAS
===================================================== */

.card {

    border:none;

    border-radius:18px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.06);

}

/* =====================================================
   ICONO QR
===================================================== */

.qr-icon {

    width:70px;

    height:70px;

    border-radius:18px;

    background:#e0f2fe;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:35px;

    color:#0284c7;

    margin:auto;

}

/* =====================================================
   LECTOR
===================================================== */

.lector-container {

    width:100%;

    max-width:520px;

    margin:25px auto 15px;

}

/*
   CONTENEDOR PRINCIPAL
*/

#reader {

    width:100%;

    min-height:380px;

    background:#111827;

    border-radius:20px;

    overflow:hidden;

    border:4px solid #dbeafe;

    position:relative;

}

/*
   VIDEO
*/

#reader video {

    width:100% !important;

    height:380px !important;

    object-fit:cover !important;

    display:block !important;

}

/*
   REGIÓN DE ESCANEO
*/

#reader__scan_region {

    width:100% !important;

    min-height:380px !important;

    display:flex !important;

    align-items:center !important;

    justify-content:center !important;

}

/*
   OCULTAR IMAGEN INICIAL DE LA LIBRERÍA
*/

#reader__scan_region img {

    display:none !important;

}

/*
   OCULTAR ELEMENTOS INNECESARIOS
*/

#reader__dashboard {

    background:#fff;

    padding:12px;

    text-align:center;

}

#reader__dashboard_section_csr {

    padding:5px;

}

/* =====================================================
   MARCO VISUAL DEL QR
===================================================== */

.qr-frame {

    position:absolute;

    z-index:20;

    width:250px;

    height:250px;

    left:50%;

    top:50%;

    transform:
        translate(
            -50%,
            -50%
        );

    border:3px solid #18d8ce;

    border-radius:20px;

    box-shadow:
        0 0 0 9999px
        rgba(0,0,0,.18);

    pointer-events:none;

}

.qr-frame::before {

    content:"";

    position:absolute;

    left:0;

    right:0;

    top:50%;

    height:2px;

    background:#18d8ce;

    box-shadow:
        0 0 10px
        rgba(24,216,206,.9);

    animation:
        lineaQR 2s
        linear infinite;

}

@keyframes lineaQR {

    0% {

        top:8%;

    }

    50% {

        top:92%;

    }

    100% {

        top:8%;

    }

}

/* =====================================================
   MENSAJE
===================================================== */

.mensaje-qr {

    min-height:28px;

    font-weight:600;

}

/* =====================================================
   BOTONES
===================================================== */

.btn-qr {

    border-radius:12px;

    padding:12px;

    font-weight:600;

}

/* =====================================================
   TABLA
===================================================== */

.table {

    vertical-align:middle;

}

.badge-registrado {

    background:#dcfce7;

    color:#166534;

    padding:7px 12px;

    border-radius:20px;

}

/* =====================================================
   RELOJ
===================================================== */

.reloj {

    font-size:14px;

    color:#64748b;

}

/* =====================================================
   ESTADO CÁMARA
===================================================== */

.estado-camara {

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:7px 12px;

    border-radius:20px;

    background:#f1f5f9;

    color:#64748b;

    font-size:13px;

    font-weight:600;

}

.estado-camara.activa {

    background:#dcfce7;

    color:#166534;

}

.estado-punto {

    width:8px;

    height:8px;

    border-radius:50%;

    background:#94a3b8;

}

.estado-camara.activa .estado-punto {

    background:#22c55e;

}

</style>

</head>

<body>

<div class="container-fluid">

<div class="row">

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="col-md-2 sidebar p-3">

<h5 class="mb-4">

<i class="bi bi-shop"></i>

Restaurante

</h5>

<a href="dashboard.php">

<i class="bi bi-speedometer2 me-2"></i>

Dashboard

</a>

<a
href="asistencia.php"
class="active"

>

<i class="bi bi-qr-code-scan me-2"></i>

Tomar asistencia

</a>

<a href="consultar.php">

<i class="bi bi-search me-2"></i>

Consultar

</a>

<a href="reportes.php">

<i class="bi bi-bar-chart me-2"></i>

Reportes

</a>

<hr class="text-secondary">

<a href="../auth/cerrar_sesion.php">

<i class="bi bi-box-arrow-right me-2"></i>

Cerrar sesión

</a>

</aside>

<!-- =====================================================
     CONTENIDO
===================================================== -->

<main class="col-md-10 main-content">

<!-- ENCABEZADO -->

<div
    class="d-flex justify-content-between align-items-center mb-4"
>

<div>

<h2 class="titulo mb-1">

<i class="bi bi-qr-code-scan"></i>

Tomar asistencia

</h2>

<p class="text-muted mb-0">

Escanea el código QR de la tarjeta del estudiante.

</p>

</div>

<div class="reloj">

<i class="bi bi-clock"></i>

<span id="reloj"></span>

</div>

</div>

<!-- MENSAJE PHP -->

<?php if ($mensaje != ""): ?>

<div
    class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show"
>

<?php echo $mensaje; ?>

<button
type="button"
class="btn-close"
data-bs-dismiss="alert"

> </button>

</div>

<?php endif; ?>

<!-- =====================================================
     LECTOR QR
===================================================== -->

<div class="row justify-content-center mb-4">

<div class="col-md-8">

<div class="card p-4 text-center">

<div class="qr-icon mb-3">

<i class="bi bi-qr-code-scan"></i>

</div>

<h4 class="mb-2">

Escanear código QR

</h4>

<p class="text-muted">

Coloca el código QR de la tarjeta
frente a la cámara.

</p>

<!-- ESTADO -->

<div
    id="estadoCamara"
    class="estado-camara mb-2"
>

<span class="estado-punto"></span>

<span id="textoEstado">

Cámara apagada

</span>

</div>

<!-- CÁMARA -->

<div class="lector-container">

<div id="reader">

<div class="qr-frame"></div>

</div>

</div>

<!-- MENSAJE -->

<div
    id="mensajeQR"
    class="mensaje-qr mt-3 text-muted"
>

Pulsa "Iniciar cámara" para comenzar.

</div>

<!-- INICIAR -->

<button
type="button"
id="btnIniciar"
class="btn btn-primary btn-qr mt-3 w-100"

>

<i class="bi bi-camera me-2"></i>

Iniciar cámara

</button>

<!-- DETENER -->

<button
type="button"
id="btnDetener"
class="btn btn-danger btn-qr mt-2 w-100"
style="display:none;"

>

<i class="bi bi-stop-circle me-2"></i>

Detener cámara

</button>

<!-- FORMULARIO -->

<form
    method="POST"
    id="formQR"
>

<input
type="hidden"
name="documento"
id="documentoQR"

>

</form>

</div>

</div>

</div>

<!-- =====================================================
     ESTADÍSTICA
===================================================== -->

<div class="row mb-4">

<div class="col-md-4">

<div class="card p-4">

<div class="d-flex align-items-center">

<div class="qr-icon me-3">

<i class="bi bi-people"></i>

</div>

<div>

<h6 class="text-muted mb-1">

Asistencias de hoy

</h6>

<h2 class="mb-0">

<?php echo $totalHoy; ?>

</h2>

</div>

</div>

</div>

</div>

</div>

<!-- =====================================================
     TABLA
===================================================== -->

<div class="card p-4">

<div
    class="d-flex justify-content-between align-items-center mb-3"
>

<div>

<h4 class="mb-1">

Asistencias de hoy

</h4>

<small class="text-muted">

<?php echo date("d/m/Y"); ?>

</small>

</div>

<span class="badge bg-primary">

<?php echo $totalHoy; ?>

registros

</span>

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>#</th>

<th>Documento</th>

<th>Estudiante</th>

<th>Hora</th>

<th>Estado</th>

</tr>

</thead>

<tbody>

<?php if ($totalHoy > 0): ?>

<?php foreach (
    $asistenciasHoy
    as $fila
): ?>

<tr>

<td>

<?php
echo htmlspecialchars(
    $fila["id_asistencia_restaurante"]
);
?>

</td>

<td>

<?php
echo htmlspecialchars(
    $fila["documento"]
);
?>

</td>

<td>

<?php
echo htmlspecialchars(
    $fila["nombres"]
    . " "
    . $fila["apellidos"]
);
?>

</td>

<td>

<?php
echo htmlspecialchars(
    $fila["hora"]
);
?>

</td>

<td>

<?php
if (
    $fila["estado"]
    ==
    "REGISTRADO"
):
?>

<span class="badge-registrado">

<i class="bi bi-check-circle"></i>

Registrado

</span>

<?php else: ?>

<span class="badge bg-secondary">

<?php
echo htmlspecialchars(
    $fila["estado"]
);
?>

</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td
    colspan="5"
    class="text-center text-muted py-4"
>

<i class="bi bi-inbox fs-2"></i>

<br>

No hay asistencias registradas hoy.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</main>

</div>

</div>

<!-- =====================================================
     JAVASCRIPT QR
===================================================== -->

<script>

let lectorQR = null;

let camaraActiva = false;

let procesandoQR = false;


/* =====================================================
   ELEMENTOS
===================================================== */

const btnIniciar =
    document.getElementById("btnIniciar");

const btnDetener =
    document.getElementById("btnDetener");

const mensajeQR =
    document.getElementById("mensajeQR");

const estadoCamara =
    document.getElementById("estadoCamara");

const textoEstado =
    document.getElementById("textoEstado");


/* =====================================================
   BOTÓN INICIAR
===================================================== */

btnIniciar.addEventListener(
    "click",
    iniciarCamara
);


/* =====================================================
   INICIAR CÁMARA
===================================================== */

function iniciarCamara() {

    if (camaraActiva) {

        return;

    }

    procesandoQR = false;

    mensajeQR.textContent =
        "Solicitando acceso a la cámara...";

    mensajeQR.className =
        "mensaje-qr mt-3 text-primary";


    btnIniciar.style.display =
        "none";


    /*
       Crear lector
    */

    lectorQR =
        new Html5Qrcode(
            "reader"
        );


    /*
       Configuración
    */

    const configuracion = {

        fps:10,

        qrbox:{
            width:250,
            height:250
        },

        aspectRatio:1.0,

        disableFlip:false

    };


    /*
       Iniciar cámara
    */

    lectorQR
        .start(

            {
                facingMode:{
                    ideal:"environment"
                }
            },

            configuracion,

            cuandoLeeQR,

            cuandoNoLeeQR

        )

        .then(
            function() {

                camaraActiva = true;

                btnDetener.style.display =
                    "block";

                estadoCamara.classList.add(
                    "activa"
                );

                textoEstado.textContent =
                    "Cámara activa";

                mensajeQR.textContent =
                    "Cámara activa. Coloca el QR dentro del cuadro.";

                mensajeQR.className =
                    "mensaje-qr mt-3 text-success";

            }
        )

        .catch(
            function(error) {

                console.error(
                    "Error al iniciar cámara:",
                    error
                );

                camaraActiva = false;

                lectorQR = null;

                btnIniciar.style.display =
                    "block";

                btnDetener.style.display =
                    "none";

                estadoCamara.classList.remove(
                    "activa"
                );

                textoEstado.textContent =
                    "Cámara apagada";

                mensajeQR.textContent =
                    "No se pudo acceder a la cámara. Verifica los permisos del navegador.";

                mensajeQR.className =
                    "mensaje-qr mt-3 text-danger";

            }
        );

}


/* =====================================================
   QR DETECTADO
===================================================== */

function cuandoLeeQR(
    texto,
    resultado
) {

    if (procesandoQR) {

        return;

    }

    procesandoQR = true;


    const documento =
        texto.trim();


    console.log(
        "QR detectado:",
        documento
    );


    if (documento === "") {

        procesandoQR = false;

        return;

    }


    mensajeQR.textContent =
        "QR detectado. Registrando asistencia...";

    mensajeQR.className =
        "mensaje-qr mt-3 text-primary";


    document
        .getElementById(
            "documentoQR"
        )
        .value =
        documento;


    detenerCamara();


    setTimeout(
        function() {

            document
                .getElementById(
                    "formQR"
                )
                .submit();

        },
        400
    );

}


/* =====================================================
   QR NO DETECTADO
===================================================== */

function cuandoNoLeeQR(error) {

    /*
       No mostramos errores porque
       la librería los genera
       constantemente mientras busca.
    */

}


/* =====================================================
   BOTÓN DETENER
===================================================== */

btnDetener.addEventListener(
    "click",
    detenerCamara
);


/* =====================================================
   DETENER CÁMARA
===================================================== */

function detenerCamara() {

    if (!lectorQR) {

        return;

    }


    lectorQR
        .stop()

        .then(
            function() {

                try {

                    lectorQR.clear();

                } catch(error) {

                    console.log(
                        "El lector ya estaba limpio."
                    );

                }


                lectorQR = null;

                camaraActiva = false;


                btnIniciar.style.display =
                    "block";

                btnDetener.style.display =
                    "none";


                estadoCamara.classList.remove(
                    "activa"
                );

                textoEstado.textContent =
                    "Cámara apagada";


                mensajeQR.textContent =
                    "La cámara está apagada.";

                mensajeQR.className =
                    "mensaje-qr mt-3 text-muted";

            }
        )

        .catch(
            function(error) {

                console.error(
                    "Error al detener cámara:",
                    error
                );

            }
        );

}


/* =====================================================
   RELOJ
===================================================== */

function actualizarReloj() {

    const ahora =
        new Date();


    const hora =
        ahora.toLocaleTimeString(
            "es-CO",
            {
                hour:"2-digit",
                minute:"2-digit",
                second:"2-digit"
            }
        );


    document
        .getElementById(
            "reloj"
        )
        .textContent =
        hora;

}


actualizarReloj();


setInterval(
    actualizarReloj,
    1000
);

</script>

<!-- BOOTSTRAP JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>
