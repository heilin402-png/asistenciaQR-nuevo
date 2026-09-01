```php
<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/conexion.php";

date_default_timezone_set('America/Bogota');

/* =========================================================
   OBTENER ID DEL ESTUDIANTE
========================================================= */

$idEstudiante = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

$estudiante = null;

if ($idEstudiante > 0) {

    $sql = "SELECT
                e.id_estudiante,
                e.id_curso,
                e.documento,
                e.nombres,
                e.apellidos,
                e.estado,
                e.fecha_creacion,
                c.nombre_curso
            FROM estudiantes e
            INNER JOIN cursos c
                ON c.id_curso = e.id_curso
            WHERE e.id_estudiante = ?
            LIMIT 1";

    $stmt = mysqli_prepare(
        $conexion,
        $sql
    );

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idEstudiante
        );

        mysqli_stmt_execute($stmt);

        $resultado =
            mysqli_stmt_get_result($stmt);

        $estudiante =
            mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);
    }
}

/* =========================================================
   SI NO EXISTE EL ESTUDIANTE
========================================================= */

if (!$estudiante) {

    header("Location: curso_estudiantes.php");

    exit();
}

/* =========================================================
   DATOS
========================================================= */

$nombreCompleto =
    trim(
        $estudiante['nombres']
        . ' '
        . $estudiante['apellidos']
    );

$documento =
    trim(
        $estudiante['documento']
    );

$curso =
    $estudiante['nombre_curso'];

$estado =
    $estudiante['estado'];

/*
 * IMPORTANTE:
 * EL CONTENIDO DEL QR ES ÚNICAMENTE
 * EL DOCUMENTO DEL ESTUDIANTE.
 */
$contenidoQR = $documento;

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
QR estudiante | <?= htmlspecialchars($nombreCompleto) ?>
</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<!--
    Librería QR.
    No necesitas descargarla.
    Se carga directamente desde CDN.
-->
<script
    src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
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

    padding:30px;

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

/* =========================================================
   CONTENEDOR
========================================================= */

.qr-page{

    width:min(
        900px,
        100%
    );

}

/* =========================================================
   TARJETA PRINCIPAL
========================================================= */

.qr-card{

    position:relative;

    overflow:hidden;

    padding:34px;

    border:
        1px solid
        rgba(255,255,255,.95);

    border-radius:32px;

    background:
        rgba(255,255,255,.82);

    backdrop-filter:
        blur(25px);

    box-shadow:

        0 30px 80px
        rgba(55,113,129,.14);

}

/* decoración */

.qr-card::before{

    content:"";

    position:absolute;

    width:220px;
    height:220px;

    right:-100px;
    top:-100px;

    border-radius:50%;

    background:
        rgba(24,216,206,.08);

}

.qr-card::after{

    content:"";

    position:absolute;

    width:180px;
    height:180px;

    left:-90px;
    bottom:-90px;

    border-radius:50%;

    background:
        rgba(133,121,210,.07);

}

/* =========================================================
   CABECERA
========================================================= */

.qr-header{

    position:relative;

    z-index:2;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin-bottom:30px;

}

.qr-brand{

    display:flex;

    align-items:center;

    gap:14px;

}

.brand-icon{

    width:58px;
    height:58px;

    display:flex;

    align-items:center;
    justify-content:center;

    border-radius:17px;

    color:#087d92;

    background:
        linear-gradient(
            145deg,
            rgba(24,216,206,.15),
            rgba(105,184,213,.12)
        );

    font-size:27px;

}

.brand-title strong{

    display:block;

    color:#075273;

    font-size:20px;

    font-weight:950;

}

.brand-title span{

    display:block;

    margin-top:4px;

    color:#829da5;

    font-size:11px;

    font-weight:750;

}

.status{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:8px 12px;

    border-radius:11px;

    font-size:10px;

    font-weight:950;

}

.status.active{

    color:#218b6c;

    background:
        rgba(66,205,161,.11);

}

.status.inactive{

    color:#aa7279;

    background:
        rgba(242,143,150,.11);

}

.status-dot{

    width:7px;
    height:7px;

    border-radius:50%;

}

.status.active .status-dot{

    background:#42cda1;

}

.status.inactive .status-dot{

    background:#e99a9f;

}

/* =========================================================
   CONTENIDO
========================================================= */

.qr-content{

    position:relative;

    z-index:2;

    display:grid;

    grid-template-columns:
        1fr
        1fr;

    gap:34px;

    align-items:center;

}

/* =========================================================
   QR
========================================================= */

.qr-section{

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

}

.qr-wrapper{

    width:310px;
    height:310px;

    display:flex;

    align-items:center;
    justify-content:center;

    padding:20px;

    border-radius:28px;

    background:#fff;

    border:
        1px solid
        rgba(180,215,220,.32);

    box-shadow:

        0 20px 50px
        rgba(55,113,129,.10);

}

#qrcode{

    width:260px;
    height:260px;

    display:flex;

    align-items:center;
    justify-content:center;

}

#qrcode img,
#qrcode canvas{

    width:260px !important;
    height:260px !important;

    display:block;

}

.qr-caption{

    margin-top:15px;

    text-align:center;

}

.qr-caption strong{

    display:block;

    color:#315f70;

    font-size:13px;

    font-weight:950;

}

.qr-caption span{

    display:block;

    margin-top:4px;

    color:#8aa3aa;

    font-size:10px;

}

/* =========================================================
   INFORMACIÓN
========================================================= */

.student-info{

    padding:25px;

    border-radius:22px;

    background:
        rgba(248,253,252,.82);

    border:
        1px solid
        rgba(255,255,255,.90);

}

.student-label{

    margin-bottom:6px;

    color:#8aa3aa;

    font-size:10px;

    font-weight:850;

    letter-spacing:1px;

}

.student-name{

    margin-bottom:23px;

    color:#315f70;

    font-size:25px;

    line-height:1.15;

    font-weight:950;

}

.info-item{

    display:flex;

    align-items:center;

    gap:12px;

    padding:13px 0;

    border-bottom:
        1px solid
        rgba(120,170,180,.09);

}

.info-item:last-child{

    border-bottom:none;

}

.info-icon{

    width:40px;
    height:40px;

    display:flex;

    align-items:center;
    justify-content:center;

    flex-shrink:0;

    border-radius:12px;

    color:#087d92;

    background:
        rgba(24,216,206,.09);

    font-size:17px;

}

.info-text{

    min-width:0;

}

.info-text small{

    display:block;

    color:#8aa3aa;

    font-size:9px;

    font-weight:850;

    letter-spacing:.5px;

}

.info-text strong{

    display:block;

    margin-top:3px;

    overflow:hidden;

    color:#416f7e;

    font-size:13px;

    font-weight:900;

    text-overflow:ellipsis;

    white-space:nowrap;

}

/* =========================================================
   DOCUMENTO DESTACADO
========================================================= */

.document-box{

    margin-top:18px;

    padding:15px;

    text-align:center;

    border-radius:15px;

    background:
        linear-gradient(
            135deg,
            rgba(24,216,206,.10),
            rgba(105,184,213,.08)
        );

}

.document-box span{

    display:block;

    color:#7e9ba3;

    font-size:9px;

    font-weight:850;

    letter-spacing:1px;

}

.document-box strong{

    display:block;

    margin-top:5px;

    color:#087d92;

    font-size:22px;

    font-weight:950;

    letter-spacing:1px;

}

/* =========================================================
   BOTONES
========================================================= */

.qr-actions{

    position:relative;

    z-index:2;

    display:flex;

    justify-content:center;

    gap:10px;

    margin-top:30px;

}

.qr-btn{

    min-height:46px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    padding:0 18px;

    border:none;

    border-radius:13px;

    font-family:inherit;

    font-size:11px;

    font-weight:900;

    text-decoration:none;

    cursor:pointer;

    transition:.2s;

}

.qr-btn:hover{

    transform:translateY(-2px);

}

.btn-back{

    color:#668995;

    background:
        rgba(120,170,180,.10);

}

.btn-print{

    color:#fff;

    background:
        linear-gradient(
            135deg,
            #18cfc6,
            #159fae
        );

    box-shadow:
        0 10px 25px
        rgba(24,216,206,.18);

}

/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:700px){

    body{

        padding:15px;

    }

    .qr-card{

        padding:22px;

        border-radius:25px;

    }

    .qr-header{

        align-items:flex-start;

        flex-direction:column;

    }

    .qr-content{

        grid-template-columns:1fr;

        gap:25px;

    }

    .qr-wrapper{

        width:280px;
        height:280px;

    }

    #qrcode{

        width:240px;
        height:240px;

    }

    #qrcode img,
    #qrcode canvas{

        width:240px !important;
        height:240px !important;

    }

    .student-name{

        font-size:21px;

    }

    .qr-actions{

        flex-direction:column;

    }

    .qr-btn{

        width:100%;

    }

}

/* =========================================================
   IMPRESIÓN
========================================================= */

@media print{

    @page{

        size:A4;

        margin:15mm;

    }

    body{

        min-height:auto;

        padding:0;

        background:#fff;

    }

    .qr-page{

        width:100%;

    }

    .qr-card{

        padding:20px;

        border:none;

        box-shadow:none;

        background:#fff;

    }

    .qr-card::before,
    .qr-card::after{

        display:none;

    }

    .qr-actions{

        display:none;

    }

    .qr-wrapper{

        box-shadow:none;

        border:1px solid #ddd;

    }

}

</style>

</head>

<body>

<div class="qr-page">

<div class="qr-card">

<!-- =====================================================
     CABECERA
===================================================== -->

<div class="qr-header">

<div class="qr-brand">

<div class="brand-icon">

<i class="bi bi-qr-code"></i>

</div>

<div class="brand-title">

<strong>
ASISTENCIA QR
</strong>

<span>
Código de identificación del estudiante
</span>

</div>

</div>

<div
    class="status
    <?= $estado === 'ACTIVO'
        ? 'active'
        : 'inactive'
    ?>"
>

<span class="status-dot"></span>

<?= htmlspecialchars($estado) ?>

</div>

</div>

<!-- =====================================================
     CONTENIDO
===================================================== -->

<div class="qr-content">

<!-- QR -->

<div class="qr-section">

<div class="qr-wrapper">

<div id="qrcode"></div>

</div>

<div class="qr-caption">

<strong>
Código QR personal
</strong>

<span>
Escanea este código para identificar al estudiante
</span>

</div>

</div>

<!-- INFORMACIÓN -->

<div class="student-info">

<div class="student-label">
ESTUDIANTE
</div>

<div class="student-name">

<?= htmlspecialchars(
    $nombreCompleto
) ?>

</div>

<div class="info-item">

<div class="info-icon">

<i class="bi bi-card-text"></i>

</div>

<div class="info-text">

<small>
DOCUMENTO
</small>

<strong>
<?= htmlspecialchars($documento) ?>
</strong>

</div>

</div>

<div class="info-item">

<div class="info-icon">

<i class="bi bi-mortarboard-fill"></i>

</div>

<div class="info-text">

<small>
CURSO
</small>

<strong>
<?= htmlspecialchars($curso) ?>
</strong>

</div>

</div>

<div class="info-item">

<div class="info-icon">

<i class="bi bi-person-check-fill"></i>

</div>

<div class="info-text">

<small>
ESTADO
</small>

<strong>
<?= htmlspecialchars($estado) ?>
</strong>

</div>

</div>

<div class="document-box">

<span>
DOCUMENTO CODIFICADO EN EL QR
</span>

<strong>
<?= htmlspecialchars($documento) ?>
</strong>

</div>

</div>

</div>

<!-- =====================================================
     BOTONES
===================================================== -->

<div class="qr-actions">

<a
    href="javascript:history.back()"
    class="qr-btn btn-back"
>

<i class="bi bi-arrow-left"></i>

Volver

</a>

<button
    type="button"
    class="qr-btn btn-print"
    onclick="window.print()"
>

<i class="bi bi-printer-fill"></i>

Imprimir QR

</button>

</div>

</div>

</div>

<script>

/* =========================================================
   GENERAR QR
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const contenedor =
            document.getElementById(
                'qrcode'
            );

        if(!contenedor){

            return;
        }

        new QRCode(
            contenedor,
            {

                text:
                    <?= json_encode(
                        $contenidoQR,
                        JSON_HEX_TAG |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT |
                        JSON_HEX_AMP
                    ) ?>,

                width:260,

                height:260,

                colorDark:"#20596d",

                colorLight:"#ffffff",

                correctLevel:
                    QRCode.CorrectLevel.H

            }
        );

    }
);

</script>

</body>

</html>
```
