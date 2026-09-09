<?php

session_start();

/* ==========================
   VERIFICAR SESIÓN
   ========================== */

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../auth/login.php");
    exit();
}


/* ==========================
   VERIFICAR ROL RESTAURANTE
   ========================== */

if ($_SESSION["id_rol"] != 3) {
    header("Location: ../index.php");
    exit();
}


/* ==========================
   DATOS DEL USUARIO
   ========================== */

$nombre = $_SESSION["nombre"] ?? "";
$apellido = $_SESSION["apellido"] ?? "";

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Restaurante</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >


    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
        }


        /* ==========================
           BARRA LATERAL
           ========================== */

        .sidebar {

            position: fixed;

            left: 0;
            top: 0;

            width: 255px;
            height: 100vh;

            background: linear-gradient(180deg, #0d1b2a, #1b263b);

            color: white;

            padding: 20px;

        }


        .logo {

            text-align: center;

            margin-bottom: 25px;

        }


        .logo img {

            width: 80px;

            height: 80px;

            object-fit: contain;

            margin-bottom: 10px;

        }


        .logo h5 {

            margin: 0;

            font-weight: bold;

        }


        .menu {

            margin-top: 25px;

        }


        .menu a {

            display: block;

            color: white;

            text-decoration: none;

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 8px;

            transition: 0.3s;

        }


        .menu a:hover,
        .menu a.active {

            background-color: rgba(255,255,255,0.15);

        }


        .menu i {

            margin-right: 10px;

        }


        .usuario {

            position: absolute;

            bottom: 20px;

            left: 20px;

            right: 20px;

            border-top: 1px solid rgba(255,255,255,0.2);

            padding-top: 15px;

        }


        .usuario a {

            color: white;

            text-decoration: none;

        }


        /* ==========================
           CONTENIDO
           ========================== */

        .contenido {

            margin-left: 255px;

            padding: 30px;

        }


        .encabezado {

            margin-bottom: 25px;

        }


        .encabezado h2 {

            font-weight: bold;

            color: #1b263b;

        }


        .tarjeta-bienvenida {

            background: white;

            border-radius: 15px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow: 0 4px 15px rgba(0,0,0,0.08);

        }


        .tarjeta-bienvenida h3 {

            color: #1b263b;

            font-weight: bold;

        }


        /* ==========================
           ESTADÍSTICAS
           ========================== */

        .estadistica {

            background: white;

            border-radius: 15px;

            padding: 25px;

            box-shadow: 0 4px 15px rgba(0,0,0,0.08);

            height: 100%;

        }


        .estadistica i {

            font-size: 35px;

            color: #1b263b;

        }


        .estadistica h3 {

            margin-top: 10px;

            font-weight: bold;

        }


        .estadistica p {

            color: #6c757d;

            margin: 0;

        }


        /* ==========================
           ACCIONES
           ========================== */

        .acciones {

            background: white;

            border-radius: 15px;

            padding: 25px;

            margin-top: 25px;

            box-shadow: 0 4px 15px rgba(0,0,0,0.08);

        }


        .boton-accion {

            display: block;

            text-decoration: none;

            color: white;

            background-color: #1b263b;

            padding: 18px;

            border-radius: 10px;

            text-align: center;

            transition: 0.3s;

        }


        .boton-accion:hover {

            background-color: #0d1b2a;

            color: white;

            transform: translateY(-2px);

        }


        .boton-accion i {

            font-size: 30px;

            display: block;

            margin-bottom: 8px;

        }


        /* ==========================
           INFORMACIÓN
           ========================== */

        .informacion {

            background: white;

            border-radius: 15px;

            padding: 25px;

            margin-top: 25px;

            box-shadow: 0 4px 15px rgba(0,0,0,0.08);

        }


        /* ==========================
           RESPONSIVE
           ========================== */

        @media (max-width: 768px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;

            }


            .usuario {

                position: relative;

                left: 0;

                right: 0;

                bottom: 0;

                margin-top: 20px;

            }


            .contenido {

                margin-left: 0;

                padding: 20px;

            }

        }

    </style>

</head>


<body>


<!-- ==========================
     BARRA LATERAL
     ========================== -->

<aside class="sidebar">


    <div class="logo">

        <img src="../Logo.png" alt="Logo">

        <h5>Restaurante</h5>

        <small>Sistema de asistencia</small>

    </div>


    <div class="menu">

        <a href="dashboard.php" class="active">

            <i class="bi bi-speedometer2"></i>

            Dashboard

        </a>


        <a href="asistencia.php">

            <i class="bi bi-qr-code-scan"></i>

            Tomar asistencia

        </a>


        <a href="consultar.php">

            <i class="bi bi-search"></i>

            Consultar asistencia

        </a>


        <a href="reportes.php">

            <i class="bi bi-file-earmark-bar-graph"></i>

            Reportes

        </a>

    </div>


    <div class="usuario">

        <div class="mb-2">

            <i class="bi bi-person-circle"></i>

            <?php echo htmlspecialchars($nombre . " " . $apellido); ?>

        </div>


        <a href="../auth/cerrar_sesion.php">

            <i class="bi bi-box-arrow-right"></i>

            Cerrar sesión

        </a>

    </div>


</aside>



<!-- ==========================
     CONTENIDO PRINCIPAL
     ========================== -->

<main class="contenido">


    <div class="encabezado">

        <h2>Dashboard</h2>

        <p class="text-muted">

            Panel de control del restaurante

        </p>

    </div>



    <!-- BIENVENIDA -->

    <div class="tarjeta-bienvenida">

        <h3>

            ¡Bienvenido/a,

            <?php echo htmlspecialchars($nombre); ?>! 👋

        </h3>


        <p class="text-muted mb-0">

            Desde este panel puedes gestionar la asistencia de los estudiantes al restaurante.

        </p>

    </div>



    <!-- ESTADÍSTICAS -->

    <div class="row g-4">


        <div class="col-md-4">

            <div class="estadistica">

                <i class="bi bi-people-fill"></i>

                <h3>0</h3>

                <p>Estudiantes registrados</p>

            </div>

        </div>



        <div class="col-md-4">

            <div class="estadistica">

                <i class="bi bi-check-circle-fill"></i>

                <h3>0</h3>

                <p>Asistencias de hoy</p>

            </div>

        </div>



        <div class="col-md-4">

            <div class="estadistica">

                <i class="bi bi-calendar3"></i>

                <h3>

                    <?php echo date("d/m/Y"); ?>

                </h3>

                <p>Fecha actual</p>

            </div>

        </div>


    </div>



    <!-- ACCIONES RÁPIDAS -->

    <div class="acciones">

        <h4 class="mb-4">

            Acciones rápidas

        </h4>


        <div class="row g-3">


            <div class="col-md-4">

                <a href="asistencia.php" class="boton-accion">

                    <i class="bi bi-qr-code-scan"></i>

                    Tomar asistencia

                </a>

            </div>


            <div class="col-md-4">

                <a href="consultar.php" class="boton-accion">

                    <i class="bi bi-search"></i>

                    Consultar asistencia

                </a>

            </div>


            <div class="col-md-4">

                <a href="reportes.php" class="boton-accion">

                    <i class="bi bi-file-earmark-bar-graph"></i>

                    Ver reportes

                </a>

            </div>


        </div>

    </div>



    <!-- INFORMACIÓN -->

    <div class="informacion">

        <h4>

            <i class="bi bi-info-circle"></i>

            Información

        </h4>


        <p class="text-muted mb-2">

            El restaurante utiliza el código QR del documento del estudiante

            para registrar su asistencia.

        </p>


        <p class="text-muted mb-2">

            Cada estudiante puede registrar su asistencia al restaurante

            <strong>una sola vez por día</strong>.

        </p>


        <p class="text-muted mb-0">

            Las asistencias quedan almacenadas en el sistema para

            su posterior consulta y generación de reportes.

        </p>

    </div>


</main>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>