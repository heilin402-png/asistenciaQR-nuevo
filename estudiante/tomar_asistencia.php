<?php

session_start();

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');


/* =========================================================
   VARIABLES
========================================================= */

$mensaje = '';
$tipoMensaje = '';

$sesion = null;
$estudiante = null;

$idSesion = (int)($_GET['sesion'] ?? 0);


/* =========================================================
   VALIDAR ID DE SESIÓN
========================================================= */

if ($idSesion <= 0) {

    $mensaje = 'El enlace de asistencia no es válido.';
    $tipoMensaje = 'error';

}


/* =========================================================
   BUSCAR SESIÓN
========================================================= */

if ($idSesion > 0) {

    $sqlSesion = "
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

    $stmtSesion = mysqli_prepare(
        $conexion,
        $sqlSesion
    );

    if ($stmtSesion) {

        mysqli_stmt_bind_param(
            $stmtSesion,
            "i",
            $idSesion
        );

        mysqli_stmt_execute(
            $stmtSesion
        );

        $resultadoSesion =
            mysqli_stmt_get_result(
                $stmtSesion
            );

        $sesion =
            mysqli_fetch_assoc(
                $resultadoSesion
            );

        mysqli_stmt_close(
            $stmtSesion
        );

    }


    if (!$sesion) {

        $mensaje =
            'La sesión de asistencia no existe.';

        $tipoMensaje = 'error';

    }

}


/* =========================================================
   ESTADO DE LA SESIÓN
========================================================= */

$sesionActiva = true;

$estadoSesion = 'ACTIVA';

if ($sesion) {

    $estadoSesion = strtoupper(
        trim(
            (string)($sesion['estado'] ?? '')
        )
    );


    /*
     * La aplicación trabaja normalmente con:
     *
     * ACTIVA
     *
     * CERRADA
     *
     * También aceptamos algunos valores equivalentes
     * para evitar problemas por diferencias en la BD.
     */

    if (
        $estadoSesion === 'ACTIVA'
        ||
        $estadoSesion === 'ACTIVO'
        ||
        $estadoSesion === 'ABIERTA'
        ||
        $estadoSesion === 'ABIERTA '
    ) {

        $sesionActiva = true;

    }

}


/* =========================================================
   REGISTRAR ASISTENCIA
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['registrar_qr'])
) {

    $documento = trim(
        (string)($_POST['documento'] ?? '')
    );

    $idSesionPost = (int)(
        $_POST['id_sesion'] ?? 0
    );


    /* -----------------------------------------------------
       VALIDAR SESIÓN
    ----------------------------------------------------- */

    if ($idSesionPost <= 0) {

        $mensaje =
            'La sesión no es válida.';

        $tipoMensaje = 'error';

    }

    elseif (
        !$sesion
        ||
        $idSesionPost !== $idSesion
    ) {

        $mensaje =
            'La sesión de asistencia no es válida.';

        $tipoMensaje = 'error';

    }

    elseif (!$sesionActiva) {

        $mensaje =
            'Esta sesión ya está cerrada.';

        $tipoMensaje = 'error';

    }

    elseif ($documento === '') {

        $mensaje =
            'No fue posible leer el QR del estudiante.';

        $tipoMensaje = 'error';

    }

    else {


        /* =================================================
           LIMPIAR DOCUMENTO
        ================================================= */

        /*
         * El QR personal puede venir solamente como:
         *
         * 123456789
         *
         * También eliminamos espacios.
         */

        $documento =
            preg_replace(
                '/\s+/',
                '',
                $documento
            );


        /* =================================================
           VALIDAR DOCUMENTO
        ================================================= */

        if (
            !preg_match(
                '/^[0-9]+$/',
                $documento
            )
        ) {

            $mensaje =
                'El QR escaneado no contiene un documento válido.';

            $tipoMensaje = 'error';

        }

        else {


            /* =============================================
               BUSCAR ESTUDIANTE
            ============================================= */

            $sqlEstudiante = "
                SELECT
                    e.id_estudiante,
                    e.id_curso,
                    e.documento,
                    e.nombres,
                    e.apellidos,
                    e.estado,
                    c.nombre_curso

                FROM estudiantes e

                INNER JOIN cursos c
                    ON c.id_curso = e.id_curso

                WHERE e.documento = ?

                LIMIT 1
            ";

            $stmtEstudiante =
                mysqli_prepare(
                    $conexion,
                    $sqlEstudiante
                );


            if ($stmtEstudiante) {

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

                mysqli_stmt_close(
                    $stmtEstudiante
                );

            }


            /* =============================================
               ESTUDIANTE NO ENCONTRADO
            ============================================= */

            if (!$estudiante) {

                $mensaje =
                    'El QR no corresponde a un estudiante registrado.';

                $tipoMensaje = 'error';

            }

            else {


                /* =========================================
                   VALIDAR ESTADO DEL ESTUDIANTE
                ========================================= */

                $estadoEstudiante =
                    strtoupper(
                        trim(
                            (string)(
                                $estudiante['estado']
                                ?? ''
                            )
                        )
                    );


                if (
                    $estadoEstudiante !== 'ACTIVO'
                ) {

                    $mensaje =
                        'El estudiante se encuentra inactivo.';

                    $tipoMensaje = 'error';

                }


                /* =========================================
                   VALIDAR CURSO
                ========================================= */

                elseif (
                    (int)$estudiante['id_curso']
                    !==
                    (int)$sesion['id_curso']
                ) {

                    $mensaje =
                        'El estudiante no pertenece al curso de esta sesión.';

                    $tipoMensaje = 'error';

                }


                else {


                    /* =====================================
                       COMPROBAR REGISTRO EXISTENTE
                    ===================================== */

                    $sqlExiste = "
                        SELECT
                            id_asistencia,
                            estado,
                            hora_registro

                        FROM asistencia_clase

                        WHERE id_sesion = ?
                        AND id_estudiante = ?

                        LIMIT 1
                    ";

                    $stmtExiste =
                        mysqli_prepare(
                            $conexion,
                            $sqlExiste
                        );

                    $registroExistente = null;


                    if ($stmtExiste) {

                        mysqli_stmt_bind_param(
                            $stmtExiste,
                            "ii",
                            $idSesion,
                            $estudiante['id_estudiante']
                        );

                        mysqli_stmt_execute(
                            $stmtExiste
                        );

                        $resultadoExiste =
                            mysqli_stmt_get_result(
                                $stmtExiste
                            );

                        $registroExistente =
                            mysqli_fetch_assoc(
                                $resultadoExiste
                            );

                        mysqli_stmt_close(
                            $stmtExiste
                        );

                    }


                    /* =====================================
                       YA REGISTRADO
                    ===================================== */

                    if ($registroExistente) {

                        $nombreEstudiante =
                            trim(
                                $estudiante['nombres']
                                . ' '
                                . $estudiante['apellidos']
                            );

                        $horaAnterior =
                            substr(
                                $registroExistente[
                                    'hora_registro'
                                ] ?? '',
                                0,
                                5
                            );

                        $mensaje =
                            'La asistencia de '
                            . $nombreEstudiante
                            . ' ya fue registrada a las '
                            . $horaAnterior
                            . '.';

                        $tipoMensaje =
                            'warning';

                    }


                    /* =====================================
                       REGISTRAR
                    ===================================== */

                    else {

                        $estadoAsistencia =
                            'PRESENTE';

                        $horaRegistro = date('Y-m-d H:i:s');


                        $sqlInsertar = "
                            INSERT INTO asistencia_clase
                            (
                                id_sesion,
                                id_estudiante,
                                estado,
                                estado_excusa,
                                hora_registro
                            )
                            VALUES
                            (?, ?, ?, NULL, ?)
                        ";


                        $stmtInsertar =
                            mysqli_prepare(
                                $conexion,
                                $sqlInsertar
                            );


                        if ($stmtInsertar) {

                            mysqli_stmt_bind_param(
                                $stmtInsertar,
                                "iiss",
                                $idSesion,
                                $estudiante[
                                    'id_estudiante'
                                ],
                                $estadoAsistencia,
                                $horaRegistro
                            );


                            if (
                                mysqli_stmt_execute(
                                    $stmtInsertar
                                )
                            ) {

                                $mensaje =
                                    '¡Asistencia registrada correctamente!';

                                $tipoMensaje =
                                    'success';

                            }

                            else {

                                $mensaje =
                                    'No fue posible registrar la asistencia.';

                                $tipoMensaje =
                                    'error';

                            }


                            mysqli_stmt_close(
                                $stmtInsertar
                            );

                        }

                        else {

                            $mensaje =
                                'Error al preparar el registro de asistencia.';

                            $tipoMensaje =
                                'error';

                        }

                    }

                }

            }

        }

    }

}


/* =========================================================
   DATOS VISUALES
========================================================= */

$nombreCurso = '';

if ($sesion) {

    $nombreCurso =
        $sesion['nombre_curso'];

}


$nombreEstudianteRegistrado = '';

if (
    $estudiante
    &&
    $tipoMensaje === 'success'
) {

    $nombreEstudianteRegistrado =
        trim(
            $estudiante['nombres']
            . ' '
            . $estudiante['apellidos']
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
    Tomar asistencia | Asistencia QR
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<script
    src="https://unpkg.com/html5-qrcode"
></script>


<style>

:root{

    --aqua:#18d8ce;
    --aqua-dark:#087d92;
    --blue:#69b8d5;
    --mint:#42cda1;
    --purple:#8579d2;
    --text:#3e6f7d;
    --dark:#20596d;

}

*{

    margin:0;
    padding:0;
    box-sizing:border-box;

}

body{

    min-height:100vh;

    display:flex;

    align-items:center;

    justify-content:center;

    padding:20px;

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    color:var(--text);

    background:

        radial-gradient(
            circle at 5% 10%,
            rgba(24,216,206,.16),
            transparent 28%
        ),

        radial-gradient(
            circle at 95% 90%,
            rgba(133,121,210,.16),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            #e8faf7 0%,
            #f8fdfc 48%,
            #eaf6fb 100%
        );

}

.page{

    width:100%;
    max-width:520px;

}

.card{

    position:relative;

    overflow:hidden;

    padding:30px;

    border:
        1px solid
        rgba(255,255,255,.95);

    border-radius:30px;

    background:
        rgba(255,255,255,.86);

    backdrop-filter:blur(25px);

    box-shadow:
        0 30px 80px
        rgba(55,113,129,.14);

}

.card::before{

    content:"";

    position:absolute;

    width:190px;
    height:190px;

    right:-90px;
    top:-90px;

    border-radius:50%;

    background:
        rgba(24,216,206,.08);

}

.card::after{

    content:"";

    position:absolute;

    width:160px;
    height:160px;

    left:-80px;
    bottom:-80px;

    border-radius:50%;

    background:
        rgba(133,121,210,.07);

}

.header{

    position:relative;
    z-index:2;

    text-align:center;

    margin-bottom:22px;

}

.logo{

    width:68px;
    height:68px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:0 auto 14px;

    border-radius:20px;

    color:#087d92;

    background:
        linear-gradient(
            145deg,
            rgba(24,216,206,.14),
            rgba(105,184,213,.12)
        );

    font-size:32px;

}

.header h1{

    color:#075273;

    font-size:25px;

    font-weight:950;

}

.header p{

    margin-top:6px;

    color:#7898a2;

    font-size:12px;

    font-weight:650;

}

.session{

    position:relative;

    z-index:2;

    margin-bottom:18px;

    padding:15px;

    text-align:center;

    border-radius:17px;

    background:
        linear-gradient(
            135deg,
            rgba(24,216,206,.09),
            rgba(105,184,213,.08)
        );

    border:
        1px solid
        rgba(24,216,206,.12);

}

.session span{

    display:block;

    color:#8aa2aa;

    font-size:9px;

    font-weight:900;

    letter-spacing:1px;

}

.session strong{

    display:block;

    margin-top:5px;

    color:#356d7d;

    font-size:18px;

    font-weight:950;

}

.alert{

    position:relative;

    z-index:3;

    display:flex;

    align-items:flex-start;

    gap:10px;

    margin-bottom:17px;

    padding:14px 15px;

    border-radius:14px;

    font-size:12px;

    font-weight:800;

    line-height:1.45;

}

.alert i{

    flex-shrink:0;

    margin-top:1px;

}

.alert.success{

    color:#258b70;

    background:
        rgba(66,205,161,.11);

    border:
        1px solid
        rgba(66,205,161,.16);

}

.alert.error{

    color:#a85863;

    background:
        rgba(242,143,150,.11);

    border:
        1px solid
        rgba(242,143,150,.16);

}

.alert.warning{

    color:#9b7940;

    background:
        rgba(230,190,100,.12);

    border:
        1px solid
        rgba(230,190,100,.16);

}

.instructions{

    position:relative;

    z-index:2;

    text-align:center;

    margin-bottom:15px;

}

.instructions h2{

    color:#416f7e;

    font-size:18px;

    font-weight:950;

}

.instructions p{

    margin-top:6px;

    color:#819ca4;

    font-size:12px;

    line-height:1.5;

    font-weight:650;

}

.scanner-container{

    position:relative;

    z-index:2;

    overflow:hidden;

    width:100%;

    border-radius:20px;

    background:#fff;

    border:
        1px solid
        rgba(24,216,206,.15);

    box-shadow:
        0 15px 35px
        rgba(55,113,129,.09);

}

#reader{

    width:100%;

}

#reader img{

    max-width:100%;

}

#reader__dashboard{

    padding:12px !important;

}

#reader__dashboard_section_csr{

    margin-top:5px;

}

#reader button{

    min-height:40px;

    padding:0 15px;

    border:none;

    border-radius:10px;

    color:#fff;

    background:#159eab;

    font-family:inherit;

    font-weight:800;

    cursor:pointer;

}

#reader select{

    min-height:40px;

    padding:0 10px;

    border:
        1px solid
        #d5e6e8;

    border-radius:10px;

}

.result{

    position:relative;

    z-index:3;

    text-align:center;

    padding:20px 10px;

}

.result-icon{

    width:70px;
    height:70px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:0 auto 13px;

    border-radius:21px;

    color:#218e72;

    background:
        rgba(66,205,161,.11);

    font-size:34px;

}

.result h2{

    color:#416f7e;

    font-size:20px;

    font-weight:950;

}

.result p{

    margin-top:6px;

    color:#819ca4;

    font-size:12px;

    font-weight:650;

}

.closed{

    position:relative;

    z-index:2;

    text-align:center;

    padding:25px 10px;

}

.closed-icon{

    width:75px;
    height:75px;

    display:flex;

    align-items:center;
    justify-content:center;

    margin:0 auto 15px;

    border-radius:22px;

    color:#b86e77;

    background:
        rgba(242,143,150,.10);

    font-size:34px;

}

.closed h2{

    color:#456f7c;

    font-size:20px;

    font-weight:950;

}

.closed p{

    margin-top:8px;

    color:#8aa2a9;

    font-size:12px;

    line-height:1.5;

}

.footer{

    position:relative;

    z-index:2;

    margin-top:20px;

    padding-top:15px;

    text-align:center;

    border-top:
        1px solid
        rgba(50,111,130,.08);

    color:#94a9ae;

    font-size:10px;

    font-weight:700;

}

.footer i{

    margin-right:4px;

    color:#18bcb5;

}

@media(max-width:600px){

    body{

        padding:12px;

    }

    .card{

        padding:23px 17px;

        border-radius:24px;

    }

    .header h1{

        font-size:22px;

    }

}

</style>

</head>


<body>

<div class="page">

<div class="card">


<!-- =====================================================
     CABECERA
====================================================== -->

<div class="header">

    <div class="logo">

        <i class="bi bi-qr-code-scan"></i>

    </div>

    <h1>
        Registrar asistencia
    </h1>

    <p>
        Sistema de asistencia QR
    </p>

</div>


<!-- =====================================================
     SESIÓN
====================================================== -->

<?php if ($sesion): ?>

    <div class="session">

        <span>
            SESIÓN DE ASISTENCIA
        </span>

        <strong>
            <?= htmlspecialchars(
                $nombreCurso
            ) ?>
        </strong>

    </div>

<?php endif; ?>


<!-- =====================================================
     MENSAJE
====================================================== -->

<?php if ($mensaje !== ''): ?>

    <div class="alert <?= htmlspecialchars($tipoMensaje) ?>">

        <i
            class="bi
            <?=
                $tipoMensaje === 'success'
                ? 'bi-check-circle-fill'
                :
                (
                    $tipoMensaje === 'warning'
                    ? 'bi-exclamation-triangle-fill'
                    : 'bi-exclamation-circle-fill'
                )
            ?>"
        ></i>

        <span>

            <?= htmlspecialchars(
                $mensaje
            ) ?>

        </span>

    </div>

<?php endif; ?>


<!-- =====================================================
     SESIÓN ACTIVA
====================================================== -->

<?php if (
    $sesion
    &&
    $sesionActiva
    &&
    $tipoMensaje !== 'success'
): ?>

    <div class="instructions">

        <h2>
            Escanea tu código QR personal
        </h2>

        <p>
            Coloca frente a la cámara el QR
            personal del estudiante para
            registrar automáticamente su asistencia.
        </p>

    </div>


    <div
        class="scanner-container"
        id="scannerContainer"
    >

        <div id="reader"></div>

    </div>


<?php elseif (
    $sesion
    &&
    $sesionActiva
    &&
    $tipoMensaje === 'success'
): ?>


    <div class="result">

        <div class="result-icon">

            <i class="bi bi-check-lg"></i>

        </div>

        <h2>
            ¡Asistencia registrada!
        </h2>

        <p>

            <?= htmlspecialchars(
                $nombreEstudianteRegistrado
            ) ?>

            ha quedado registrado como
            <strong>PRESENTE</strong>.

        </p>

    </div>


<?php elseif (
    $sesion
    &&
    !$sesionActiva
): ?>


    <div class="closed">

        <div class="closed-icon">

            <i class="bi bi-lock-fill"></i>

        </div>

        <h2>
            Sesión cerrada
        </h2>

        <p>
            Esta sesión ya no está disponible
            para registrar nuevas asistencias.
        </p>

    </div>

<?php endif; ?>


<div class="footer">

    <i class="bi bi-shield-check"></i>

    Registro seguro de asistencia

</div>


</div>

</div>


<script>

/* =========================================================
   DATOS DE SESIÓN
========================================================= */

const idSesion =
    <?= json_encode($idSesion) ?>;


/* =========================================================
   PROCESAR QR PERSONAL
========================================================= */

function procesarQR(documento)
{

    if (!documento) {

        return;

    }


    documento =
        String(documento)
        .trim();


    /*
     * El QR personal actual contiene
     * únicamente el documento.
     */

    if (!/^[0-9]+$/.test(documento)) {

        alert(
            'El QR escaneado no corresponde a un estudiante válido.'
        );

        return;

    }


    /*
     * Evitamos múltiples lecturas
     * mientras se procesa el formulario.
     */

    if (window.procesandoQR) {

        return;

    }

    window.procesandoQR = true;


    const formulario =
        document.createElement('form');

    formulario.method =
        'POST';

    formulario.action =
        window.location.href;

    formulario.style.display =
        'none';


    const inputSesion =
        document.createElement('input');

    inputSesion.type =
        'hidden';

    inputSesion.name =
        'id_sesion';

    inputSesion.value =
        idSesion;


    const inputDocumento =
        document.createElement('input');

    inputDocumento.type =
        'hidden';

    inputDocumento.name =
        'documento';

    inputDocumento.value =
        documento;


    const inputRegistrar =
        document.createElement('input');

    inputRegistrar.type =
        'hidden';

    inputRegistrar.name =
        'registrar_qr';

    inputRegistrar.value =
        '1';


    formulario.appendChild(
        inputSesion
    );

    formulario.appendChild(
        inputDocumento
    );

    formulario.appendChild(
        inputRegistrar
    );


    document.body.appendChild(
        formulario
    );


    formulario.submit();

}


/* =========================================================
   INICIAR ESCÁNER
========================================================= */

function iniciarScanner()
{

    const lector =
        document.getElementById('reader');


    if (!lector) {

        return;

    }


    if (
        typeof Html5Qrcode ===
        'undefined'
    ) {

        lector.innerHTML = `

            <div style="
                padding:25px;
                text-align:center;
                color:#a85863;
                font-size:12px;
                font-weight:800;
            ">

                No fue posible cargar
                el lector QR.

            </div>

        `;

        return;

    }


    const scanner =
        new Html5Qrcode('reader');


    const configuracion = {

        fps:10,

        qrbox:{
            width:220,
            height:220
        },

        aspectRatio:1.0

    };


    scanner.start(

        {
            facingMode:"environment"
        },

        configuracion,

function(decodedText){

    scanner.stop()
        .then(
            function(){

                procesarQR(
                    decodedText
                );

            }
        )
        .catch(
            function(){

                procesarQR(
                    decodedText
                );

            }
        );

},
        function(errorMessage){

            /*
             * Los errores mientras busca
             * un QR son normales.
             */

        }

    ).catch(
        function(error){

            lector.innerHTML = `

                <div style="
                    padding:25px;
                    text-align:center;
                    color:#a85863;
                    font-size:12px;
                    font-weight:800;
                    line-height:1.5;
                ">

                    <i
                        class="bi bi-camera"
                        style="
                            display:block;
                            margin-bottom:10px;
                            font-size:30px;
                        "
                    ></i>

                    No fue posible acceder
                    a la cámara.

                    <br><br>

                    Verifica que hayas permitido
                    el acceso a la cámara
                    desde el navegador.

                </div>

            `;

        }
    );

}


/* =========================================================
   INICIAR
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function(){

        iniciarScanner();

    }
);

</script>


</body>

</html>