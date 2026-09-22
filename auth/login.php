<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

date_default_timezone_set('America/Bogota');


/* =========================================================
   CONEXIÓN
   ========================================================= */

require_once(__DIR__ . "/../config/conexion.php");


/* =========================================================
   CONFIGURACIÓN DE ROLES
   =========================================================

   1 = ADMINISTRADOR
   2 = DOCENTE
   3 = RESTAURANTE
   ========================================================= */

$ROLES = [

    "admin" => [
        "id" => 1,
        "nombre" => "Administrador"
    ],

    "docente" => [
        "id" => 2,
        "nombre" => "Docente"
    ],

    "restaurante" => [
        "id" => 3,
        "nombre" => "Restaurante"
    ]

];


/* =========================================================
   SI YA EXISTE UNA SESIÓN
   ========================================================= */

if (isset($_SESSION["id_usuario"]) && isset($_SESSION["id_rol"])) {

    $rolActual = (int) $_SESSION["id_rol"];


    /* ADMINISTRADOR */

    if ($rolActual === 1) {

        header("Location: ../admin/dashboard.php");
        exit;
    }


    /* DOCENTE */

    if ($rolActual === 2) {

        header("Location: ../docente/asistencia.php");
        exit;
    }


    /* RESTAURANTE */

    if ($rolActual === 3) {

        header("Location: ../restaurante/index.php");
        exit;
    }
}


/* =========================================================
   VARIABLES
   ========================================================= */

$mensaje = "";
$tipoMensaje = "";

$rolSeleccionado = $_POST["rol"] ?? "";

$usuarioIngresado = $_POST["usuario"] ?? "";


/* =========================================================
   PROCESAR LOGIN
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $passwordIngresada = $_POST["password"] ?? "";


    /* =====================================================
       VALIDAR ROL
       ===================================================== */

    if (!isset($ROLES[$rolSeleccionado])) {

        $mensaje = "Selecciona el tipo de usuario.";

        $tipoMensaje = "error";

    } else {

        $rol = $ROLES[$rolSeleccionado];


        /* =================================================
           VALIDAR CAMPOS
           ================================================= */

        if (
            trim($usuarioIngresado) === "" ||
            $passwordIngresada === ""
        ) {

            $mensaje = "Por favor completa todos los campos.";

            $tipoMensaje = "error";

        } else {


            /* =============================================
               BUSCAR USUARIO
               ============================================= */

            $sql = "
                SELECT
                    id_usuario,
                    nombre,
                    apellido,
                    usuario,
                    password,
                    id_rol,
                    estado
                FROM usuarios
                WHERE usuario = ?
                LIMIT 1
            ";


            $stmt = $conexion->prepare($sql);


            if (!$stmt) {

                $mensaje =
                    "No fue posible preparar la consulta.";

                $tipoMensaje = "error";

            } else {


                $stmt->bind_param(
                    "s",
                    $usuarioIngresado
                );


                $stmt->execute();


                $resultado = $stmt->get_result();


                /* =========================================
                   COMPROBAR SI EXISTE
                   ========================================= */

                if ($resultado->num_rows === 0) {

                    $mensaje =
                        "El correo o usuario no está registrado.";

                    $tipoMensaje = "error";

                } else {

                    $usuario = $resultado->fetch_assoc();


                    /* =====================================
                       COMPROBAR ESTADO
                       ===================================== */

                    if (
                        isset($usuario["estado"]) &&
                        $usuario["estado"] !== "ACTIVO"
                    ) {

                        $mensaje =
                            "Este usuario se encuentra inactivo.";

                        $tipoMensaje = "error";

                    } else {


                        /* =================================
                           COMPROBAR ROL
                           ================================= */

                        if (
                            (int)$usuario["id_rol"] !==
                            (int)$rol["id"]
                        ) {

                            $mensaje =
                                "El usuario no pertenece al tipo de usuario seleccionado.";

                            $tipoMensaje = "error";

                        } else {


                            /* =============================
                               COMPROBAR CONTRASEÑA
                               =============================

                               La contraseña está almacenada
                               en la columna password.

                               password_verify() comprueba
                               la contraseña escrita contra
                               el hash guardado.
                               ============================= */

                            if (
                                !password_verify(
                                    $passwordIngresada,
                                    $usuario["password"]
                                )
                            ) {

                                $mensaje =
                                    "La contraseña es incorrecta.";

                                $tipoMensaje = "error";

                            } else {


                                /* =========================
                                   LOGIN CORRECTO
                                   ========================= */

                                session_regenerate_id(true);


                                $_SESSION["id_usuario"] =
                                    (int)$usuario["id_usuario"];


                                $_SESSION["id_rol"] =
                                    (int)$usuario["id_rol"];


                                $_SESSION["usuario"] =
                                    $usuario["usuario"];


                                $_SESSION["nombre"] =
                                    $usuario["nombre"];


                                $_SESSION["apellido"] =
                                    $usuario["apellido"];


                                /* =========================
                                   REDIRECCIÓN
                                   ========================= */


                                /* ADMINISTRADOR */

                                if (
                                    (int)$usuario["id_rol"] === 1
                                ) {

                                    header(
                                        "Location: ../admin/dashboard.php"
                                    );

                                    exit;
                                }


                                /* DOCENTE */

                                if (
                                    (int)$usuario["id_rol"] === 2
                                ) {

                                    header(
                                        "Location: ../docente/asistencia.php"
                                    );

                                    exit;
                                }


                                /* RESTAURANTE */

                                if (
                                    (int)$usuario["id_rol"] === 3
                                ) {

                                    header(
                                        "Location: ../restaurante/index.php"
                                    );

                                    exit;
                                }


                                $mensaje =
                                    "El usuario no tiene un módulo asignado.";

                                $tipoMensaje = "error";
                            }
                        }
                    }
                }


                $stmt->close();
            }
        }
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
        Sistema de Asistencia QR
    </title>


    <!-- =====================================================
         FUENTE
         ===================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         ICONOS
         ===================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        html,
        body {
            width: 100%;
            min-height: 100%;
        }


        body {

            font-family: 'Poppins', sans-serif;

            background:
                radial-gradient(
                    circle at 8% 8%,
                    rgba(26, 148, 235, 0.10),
                    transparent 25%
                ),
                radial-gradient(
                    circle at 92% 92%,
                    rgba(20, 190, 174, 0.13),
                    transparent 28%
                ),
                linear-gradient(
                    135deg,
                    #f4faff 0%,
                    #eef7ff 50%,
                    #f7ffff 100%
                );

            color: #12356d;

            overflow-x: hidden;
        }


        /* =====================================================
           DECORACIONES
           ===================================================== */

        body::before {

            content: "";

            position: fixed;

            width: 320px;
            height: 320px;

            top: -160px;
            left: -80px;

            border: 1px solid rgba(39, 130, 220, 0.12);

            border-radius: 50%;

            pointer-events: none;
        }


        body::after {

            content: "";

            position: fixed;

            width: 300px;
            height: 300px;

            right: -120px;
            bottom: -140px;

            border: 1px solid rgba(16, 190, 176, 0.15);

            border-radius: 50%;

            pointer-events: none;
        }


        /* =====================================================
           CONTENEDOR
           ===================================================== */

        .page {

            width: 100%;

            min-height: 100vh;

            display: grid;

            grid-template-columns: 1fr 1fr;

            align-items: center;

            gap: 55px;

            padding: 42px 7%;
        }


        /* =====================================================
           LADO IZQUIERDO
           ===================================================== */

        .left {

            padding-left: 20px;
        }


        /* =====================================================
           MARCA
           ===================================================== */

        .brand {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 30px;
        }


        .brand-logo {

            width: 82px;
            height: 82px;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .brand-logo i {

            font-size: 49px;

            color: #0d9e9c;

            filter:
                drop-shadow(
                    0 5px 10px rgba(0, 150, 180, 0.14)
                );
        }


        .brand-text h2 {

            font-size: 30px;

            line-height: 1.15;

            font-weight: 800;

            color: #12356d;
        }


        .brand-text p {

            margin-top: 5px;

            font-size: 16px;

            font-weight: 600;

            color: #10bcae;
        }


        /* =====================================================
           BADGE
           ===================================================== */

        .badge {

            display: inline-flex;

            align-items: center;

            gap: 10px;

            padding: 11px 20px;

            background: rgba(255,255,255,.88);

            border-radius: 30px;

            box-shadow:
                0 10px 30px rgba(36, 101, 160, .08);

            color: #12356d;

            font-size: 14px;

            font-weight: 700;

            margin-bottom: 40px;
        }


        .badge-dot {

            width: 11px;
            height: 11px;

            border-radius: 50%;

            background: #12bfae;

            box-shadow:
                0 0 0 7px rgba(18,191,174,.10);
        }


        /* =====================================================
           TITULO
           ===================================================== */

        .hero-title {

            max-width: 620px;

            font-size: clamp(48px, 5vw, 76px);

            line-height: 1.02;

            letter-spacing: -2.5px;

            font-weight: 800;

            color: #153a76;

            margin-bottom: 28px;
        }


        .hero-title .blue {

            color: #2187e9;
        }


        .hero-title .green {

            color: #15b9b2;
        }


        .description {

            max-width: 640px;

            font-size: 18px;

            line-height: 1.9;

            color: #426593;

            margin-bottom: 45px;
        }


        /* =====================================================
           CARACTERÍSTICAS
           ===================================================== */

        .features {

            display: flex;

            gap: 55px;

            flex-wrap: wrap;
        }


        .feature {

            width: 125px;

            text-align: center;
        }


        .feature-icon {

            width: 88px;
            height: 88px;

            margin: 0 auto 14px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: rgba(255,255,255,.96);

            border-radius: 23px;

            box-shadow:
                0 12px 30px rgba(40, 105, 170, .12);
        }


        .feature-icon i {

            font-size: 34px;

            color: #1688e9;
        }


        .feature:nth-child(2)
        .feature-icon i {

            color: #0db6aa;
        }


        .feature:nth-child(3)
        .feature-icon i {

            color: #2187e9;
        }


        .feature p {

            color: #153a76;

            font-size: 14px;

            font-weight: 700;

            line-height: 1.5;
        }


        /* =====================================================
           TARJETA LOGIN
           ===================================================== */

        .login-card {

            width: 100%;

            max-width: 605px;

            min-height: 760px;

            margin-left: auto;

            padding: 52px 65px;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.96),
                    rgba(248,253,255,.91)
                );

            border: 1px solid rgba(255,255,255,.95);

            border-radius: 38px;

            box-shadow:
                0 30px 70px rgba(29, 84, 133, .12),
                inset 0 1px 0 rgba(255,255,255,.9);

            backdrop-filter: blur(15px);

            position: relative;

            overflow: hidden;
        }


        .login-card::after {

            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            right: -170px;
            bottom: -170px;

            border-radius: 50%;

            border: 1px solid rgba(17, 194, 178, .14);

            pointer-events: none;
        }


        /* =====================================================
           ICONO LOGIN
           ===================================================== */

        .login-icon {

            width: 150px;
            height: 150px;

            margin: 0 auto 30px;

            border-radius: 50%;

            border: 3px solid #b5d5ff;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                radial-gradient(
                    circle,
                    #ffffff 0%,
                    #f7fbff 70%
                );

            position: relative;
        }


        .login-icon i {

            font-size: 67px;

            color: #1688e9;
        }


        /* =====================================================
           TITULOS
           ===================================================== */

        .login-title-small {

            text-align: center;

            color: #1584e4;

            font-size: 19px;

            font-weight: 800;

            margin-bottom: 25px;
        }


        .login-title-small::before,
        .login-title-small::after {

            content: "•";

            color: #12bfae;

            margin: 0 15px;

            font-size: 22px;
        }


        .login-title {

            text-align: center;

            color: #12356d;

            font-size: 43px;

            font-weight: 800;

            line-height: 1.1;

            margin-bottom: 10px;
        }


        .login-subtitle {

            text-align: center;

            color: #607da4;

            font-size: 17px;

            margin-bottom: 28px;
        }


        /* =====================================================
           MENSAJE
           ===================================================== */

        .message {

            margin-bottom: 22px;

            padding: 14px 18px;

            border-radius: 12px;

            text-align: center;

            font-size: 14px;

            font-weight: 600;

            position: relative;

            z-index: 5;
        }


        .message.error {

            color: #a93232;

            background: #fff0f0;

            border: 1px solid #ffd2d2;
        }


        /* =====================================================
           ROLES
           ===================================================== */

        .role-label {

            display: block;

            color: #153a76;

            font-size: 15px;

            font-weight: 700;

            margin-bottom: 12px;
        }


        .roles {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 10px;

            margin-bottom: 25px;
        }


        .role-option {

            position: relative;

            display: block;
        }


        .role-option input {

            position: absolute;

            opacity: 0;

            pointer-events: none;
        }


        .role-button {

            min-height: 70px;

            width: 100%;

            border: 2px solid #dbe8f8;

            background: #f4f8fe;

            border-radius: 16px;

            cursor: pointer;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            gap: 5px;

            transition: all .2s ease;

            color: #426593;
        }


        .role-button i {

            font-size: 21px;

            color: #6190c8;
        }


        .role-button span {

            font-size: 12px;

            font-weight: 700;
        }


        .role-option input:checked
        + .role-button {

            background:
                linear-gradient(
                    135deg,
                    #2587ec,
                    #11b9ae
                );

            border-color: transparent;

            color: white;

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px rgba(26, 139, 224, .20);
        }


        .role-option input:checked
        + .role-button i {

            color: white;
        }


        /* =====================================================
           CAMPOS
           ===================================================== */

        .field {

            margin-bottom: 23px;
        }


        .field label {

            display: block;

            color: #153a76;

            font-size: 15px;

            font-weight: 700;

            margin-bottom: 11px;
        }


        .input-box {

            width: 100%;

            height: 68px;

            background: #eaf2fd;

            border: 1px solid #d8e5f6;

            border-radius: 14px;

            display: flex;

            align-items: center;

            padding: 0 20px;

            transition: all .2s ease;
        }


        .input-box:focus-within {

            border-color: #48a2ee;

            background: #f1f7ff;

            box-shadow:
                0 0 0 4px rgba(47, 144, 230, .08);
        }


        .input-box > i:first-child {

            width: 30px;

            font-size: 21px;

            color: #6992c5;
        }


        .input-box input {

            width: 100%;

            height: 100%;

            border: none;

            outline: none;

            background: transparent;

            font-family: 'Poppins', sans-serif;

            font-size: 15px;

            color: #183c70;

            padding-left: 15px;
        }


        .input-box input::placeholder {

            color: #7591b7;
        }


        .password-toggle {

            width: 35px;

            cursor: pointer;

            color: #6b8db8 !important;

            text-align: center;
        }


        /* =====================================================
           BOTÓN
           ===================================================== */

        .login-button {

            width: 100%;

            height: 68px;

            border: none;

            border-radius: 15px;

            background:
                linear-gradient(
                    100deg,
                    #2587ec,
                    #11bdb0
                );

            color: white;

            font-family: 'Poppins', sans-serif;

            font-size: 17px;

            font-weight: 800;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 18px;

            box-shadow:
                0 12px 25px rgba(24, 145, 223, .22);

            transition: all .2s ease;
        }


        .login-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 16px 30px rgba(24, 145, 223, .28);
        }


        .login-button i {

            font-size: 21px;
        }


        /* =====================================================
           SEGURIDAD
           ===================================================== */

        .secure {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 12px;

            margin-top: 30px;

            color: #58789f;

            font-size: 14px;

            font-weight: 600;

            position: relative;

            z-index: 2;
        }


        .secure-icon {

            width: 37px;
            height: 37px;

            border-radius: 50%;

            background: #f0f7ff;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #2787e6;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 1200px) {

            .page {

                grid-template-columns: 1fr;

                max-width: 850px;

                margin: auto;

                padding: 35px 25px;
            }


            .left {

                text-align: center;

                padding-left: 0;
            }


            .brand {

                justify-content: center;
            }


            .description {

                margin-left: auto;

                margin-right: auto;
            }


            .features {

                justify-content: center;
            }


            .login-card {

                margin: 0 auto;
            }
        }


        @media (max-width: 650px) {

            .page {

                padding: 20px 14px;
            }


            .brand-text h2 {

                font-size: 23px;
            }


            .brand-text p {

                font-size: 13px;
            }


            .brand-logo {

                width: 62px;

                height: 62px;
            }


            .brand-logo i {

                font-size: 40px;
            }


            .hero-title {

                font-size: 46px;

                letter-spacing: -1.5px;
            }


            .description {

                font-size: 15px;
            }


            .features {

                gap: 15px;
            }


            .feature {

                width: 105px;
            }


            .login-card {

                padding: 35px 23px;

                min-height: auto;

                border-radius: 28px;
            }


            .login-icon {

                width: 115px;

                height: 115px;
            }


            .login-icon i {

                font-size: 50px;
            }


            .login-title {

                font-size: 35px;
            }


            .roles {

                grid-template-columns: 1fr;
            }


            .role-button {

                min-height: 60px;

                flex-direction: row;

                gap: 10px;
            }

        }

    </style>

</head>


<body>


<div class="page">


    <!-- =====================================================
         LADO IZQUIERDO
         ===================================================== -->

    <section class="left">


        <div class="brand">

            <div class="brand-logo">

                <i class="fa-solid fa-qrcode"></i>

            </div>


            <div class="brand-text">

                <h2>
                    Sistema de Asistencia QR
                </h2>

                <p>
                    Gestión académica inteligente
                </p>

            </div>

        </div>


        <div class="badge">

            <span class="badge-dot"></span>

            SISTEMA DIGITAL DE ASISTENCIA

        </div>


        <h1 class="hero-title">

            Gestiona la<br>

            asistencia<br>

            <span class="blue">
                de forma
            </span>

            <span class="green">
                simple.
            </span>

        </h1>


        <p class="description">

            Accede a la plataforma para registrar,
            consultar y administrar la asistencia
            académica mediante tecnología QR.

        </p>


        <div class="features">


            <div class="feature">

                <div class="feature-icon">

                    <i class="fa-solid fa-qrcode"></i>

                </div>

                <p>
                    Registro mediante<br>
                    códigos QR
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">

                    <i class="fa-solid fa-chart-column"></i>

                </div>

                <p>
                    Control y<br>
                    seguimiento
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">

                    <i class="fa-solid fa-shield-halved"></i>

                </div>

                <p>
                    Información<br>
                    segura
                </p>

            </div>


        </div>

    </section>



    <!-- =====================================================
         LOGIN
         ===================================================== -->

    <section class="login-card">


        <div class="login-icon">

            <i class="fa-solid fa-user-shield"></i>

        </div>


        <div class="login-title-small">

            ACCESO AL SISTEMA

        </div>


        <h2 class="login-title">

            Bienvenido

        </h2>


        <p class="login-subtitle">

            Ingresa tus datos para continuar.

        </p>


        <?php if (!empty($mensaje)): ?>

            <div class="message <?= htmlspecialchars($tipoMensaje) ?>">

                <?= htmlspecialchars($mensaje) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FORMULARIO
             ================================================= -->

        <form
            method="POST"
            action=""
            id="loginForm"
            autocomplete="off"
        >


            <!-- =============================================
                 TIPO DE USUARIO
                 ============================================= -->

            <label class="role-label">

                Tipo de usuario

            </label>


            <div class="roles">


                <!-- ADMINISTRADOR -->

                <label class="role-option">

                    <input
                        type="radio"
                        name="rol"
                        value="admin"
                        <?= $rolSeleccionado === "admin"
                            ? "checked"
                            : "" ?>
                    >

                    <span class="role-button">

                        <i class="fa-solid fa-crown"></i>

                        <span>
                            ADMIN
                        </span>

                    </span>

                </label>



                <!-- DOCENTE -->

                <label class="role-option">

                    <input
                        type="radio"
                        name="rol"
                        value="docente"
                        <?= $rolSeleccionado === "docente"
                            ? "checked"
                            : "" ?>
                    >

                    <span class="role-button">

                        <i class="fa-solid fa-chalkboard-user"></i>

                        <span>
                            DOCENTE
                        </span>

                    </span>

                </label>



                <!-- RESTAURANTE -->

                <label class="role-option">

                    <input
                        type="radio"
                        name="rol"
                        value="restaurante"
                        <?= $rolSeleccionado === "restaurante"
                            ? "checked"
                            : "" ?>
                    >

                    <span class="role-button">

                        <i class="fa-solid fa-utensils"></i>

                        <span>
                            RESTAURANTE
                        </span>

                    </span>

                </label>


            </div>



            <!-- =============================================
                 USUARIO / CORREO
                 ============================================= -->

            <div class="field">

                <label for="usuario">

                    Correo electrónico

                </label>


                <div class="input-box">

                    <i class="fa-regular fa-user"></i>


                    <input
                        type="text"
                        name="usuario"
                        id="usuario"
                        placeholder="usuario@colegio.edu.co"
                        value="<?= htmlspecialchars($usuarioIngresado) ?>"
                        required
                    >

                </div>

            </div>



            <!-- =============================================
                 CONTRASEÑA
                 ============================================= -->

            <div class="field">

                <label for="password">

                    Contraseña

                </label>


                <div class="input-box">

                    <i class="fa-solid fa-lock"></i>


                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="••••••••"
                        required
                    >


                    <i
                        class="fa-regular fa-eye password-toggle"
                        id="togglePassword"
                    ></i>

                </div>

            </div>



            <!-- =============================================
                 BOTÓN
                 ============================================= -->

            <button
                type="submit"
                class="login-button"
            >

                <span>
                    Ingresar al sistema
                </span>

                <i class="fa-solid fa-arrow-right"></i>

            </button>


        </form>


        <!-- =================================================
             SEGURIDAD
             ================================================= -->

        <div class="secure">

            <span class="secure-icon">

                <i class="fa-solid fa-shield-halved"></i>

            </span>

            Acceso protegido y seguro

        </div>


    </section>


</div>



<script>


/* =========================================================
   MOSTRAR / OCULTAR CONTRASEÑA
   ========================================================= */

const togglePassword =
    document.getElementById("togglePassword");

const password =
    document.getElementById("password");


togglePassword.addEventListener(
    "click",
    function () {

        if (password.type === "password") {

            password.type = "text";

            this.classList.remove("fa-eye");

            this.classList.add("fa-eye-slash");

        } else {

            password.type = "password";

            this.classList.remove("fa-eye-slash");

            this.classList.add("fa-eye");

        }

    }
);


/* =========================================================
   VALIDAR ROL
   ========================================================= */

document
    .getElementById("loginForm")
    .addEventListener(
        "submit",
        function (event) {

            const rolSeleccionado =
                document.querySelector(
                    'input[name="rol"]:checked'
                );


            if (!rolSeleccionado) {

                event.preventDefault();

                alert(
                    "Selecciona primero el tipo de usuario."
                );

            }

        }
    );

</script>


</body>

</html>