<?php

/* ==========================================================
   SESIÓN
========================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ==========================================================
   SI EL USUARIO YA ESTÁ AUTENTICADO
========================================================== */

if (isset($_SESSION['id_usuario'])) {

    if (
        isset($_SESSION['id_rol']) &&
        (int) $_SESSION['id_rol'] === 1
    ) {

        header("Location: ../admin/dashboard.php");
        exit();

    }

    if (
        isset($_SESSION['id_rol']) &&
        (int) $_SESSION['id_rol'] === 2
    ) {

        header("Location: ../docente/dashboard.php");
        exit();

    }

}


/* ==========================================================
   MENSAJE DE ERROR
========================================================== */

$error = $_GET['error'] ?? '';

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
        Iniciar sesión | Sistema de Asistencia QR
    </title>


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICONS
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         FUENTE
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family: "Nunito", sans-serif;

            background:
                radial-gradient(
                    circle at 10% 15%,
                    rgba(52, 152, 219, .10),
                    transparent 25%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(26, 188, 156, .10),
                    transparent 25%
                ),
                linear-gradient(
                    135deg,
                    #f7fbff 0%,
                    #eef7ff 50%,
                    #f5fffd 100%
                );

            color: #173568;

            overflow-x: hidden;
        }


        body::before {

            content: "";

            position: fixed;

            width: 380px;
            height: 380px;

            border-radius: 50%;

            border: 1px solid rgba(52, 152, 219, .13);

            top: -170px;
            left: -160px;

            pointer-events: none;
        }


        body::after {

            content: "";

            position: fixed;

            width: 420px;
            height: 420px;

            border-radius: 50%;

            border: 1px solid rgba(26, 188, 156, .12);

            bottom: -220px;
            right: -180px;

            pointer-events: none;
        }


        /* ==================================================
           CONTENEDOR
        ================================================== */

        .login-container {

            width: min(1380px, 94%);

            min-height: 100vh;

            margin: auto;

            display: grid;

            grid-template-columns: 1fr 0.88fr;

            align-items: center;

            gap: 65px;

            padding: 45px 20px;
        }


        /* ==================================================
           LADO IZQUIERDO
        ================================================== */

        .login-presentation {

            min-height: 700px;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            padding: 35px 20px;
        }


        .login-brand {

            display: flex;

            align-items: center;

            gap: 18px;
        }


        .login-logo {

            width: 76px;
            height: 76px;

            object-fit: contain;

            filter:
                drop-shadow(
                    0 8px 15px rgba(30, 136, 229, .15)
                );
        }


        .login-brand-name {

            font-size: 27px;

            font-weight: 900;

            color: #112f67;

            line-height: 1.1;
        }


        .login-brand-subtitle {

            margin-top: 5px;

            font-size: 16px;

            font-weight: 800;

            color: #13bca9;
        }


        .login-presentation-content {

            max-width: 610px;

            margin-top: 20px;
        }


        .login-badge {

            display: inline-flex;

            align-items: center;

            gap: 10px;

            padding: 10px 18px;

            border-radius: 30px;

            background: rgba(255,255,255,.82);

            border: 1px solid rgba(46, 134, 222, .12);

            box-shadow:
                0 8px 25px rgba(47, 128, 237, .10);

            font-size: 13px;

            font-weight: 900;

            letter-spacing: .3px;

            color: #24477f;

            margin-bottom: 35px;
        }


        .login-badge span {

            width: 11px;
            height: 11px;

            border-radius: 50%;

            background: #18c6b3;

            box-shadow:
                0 0 0 5px rgba(24,198,179,.10);
        }


        .login-presentation h1 {

            margin: 0;

            font-size: clamp(45px, 5vw, 70px);

            line-height: 1.02;

            font-weight: 900;

            letter-spacing: -2px;

            color: #122f66;
        }


        .login-presentation h1 span {

            display: block;

            background:
                linear-gradient(
                    90deg,
                    #2378ed,
                    #13bdb3
                );

            -webkit-background-clip: text;

            background-clip: text;

            color: transparent;
        }


        .login-presentation p {

            margin-top: 30px;

            max-width: 570px;

            font-size: 18px;

            line-height: 1.75;

            font-weight: 600;

            color: #52688e;
        }


        /* ==================================================
           BENEFICIOS
        ================================================== */

        .login-benefits {

            display: flex;

            gap: 28px;

            margin-top: 38px;
        }


        .login-benefit {

            width: 125px;

            text-align: center;
        }


        .login-benefit-icon {

            width: 78px;
            height: 78px;

            margin: auto;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 20px;

            background: rgba(255,255,255,.90);

            box-shadow:
                0 10px 28px rgba(42, 111, 190, .12);

            color: #1685e8;

            font-size: 31px;
        }


        .login-benefit:nth-child(2)
        .login-benefit-icon {

            color: #12bda8;
        }


        .login-benefit:nth-child(3)
        .login-benefit-icon {

            color: #2677df;
        }


        .login-benefit span {

            display: block;

            margin-top: 14px;

            font-size: 14px;

            line-height: 1.35;

            font-weight: 800;

            color: #183668;
        }


        /* ==================================================
           FOOTER
        ================================================== */

        .login-footer-left {

            display: flex;

            align-items: center;

            gap: 12px;

            color: #45618f;

            font-size: 14px;

            font-weight: 800;
        }


        .login-footer-left i {

            color: #16bca8;
        }


        .login-footer-left strong {

            color: #2477e8;
        }


        /* ==================================================
           FORMULARIO
        ================================================== */

        .login-form-section {

            display: flex;

            justify-content: center;

            align-items: center;
        }


        /* ==================================================
           TARJETA
        ================================================== */

        .login-card {

            width: 100%;

            max-width: 535px;

            padding: 45px 58px 35px;

            border-radius: 34px;

            background:
                linear-gradient(
                    145deg,
                    #ffffff 0%,
                    #f7fbff 55%,
                    #f1fffc 100%
                );

            border: 1px solid rgba(255,255,255,.95);

            box-shadow:
                0 25px 65px rgba(34, 91, 153, .14),
                0 5px 20px rgba(34, 91, 153, .06);

            position: relative;

            overflow: hidden;
        }


        .login-card::before {

            content: "";

            position: absolute;

            width: 260px;
            height: 260px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(36,129,239,.08),
                    transparent 70%
                );

            top: -160px;
            right: -90px;

            pointer-events: none;
        }


        .login-card::after {

            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(17,197,174,.07),
                    transparent 70%
                );

            bottom: -150px;
            left: -110px;

            pointer-events: none;
        }


        /* ==================================================
           ICONO
        ================================================== */

        .login-card-icon {

            width: 132px;
            height: 132px;

            margin: 0 auto 28px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #eef8ff
                );

            border: 3px solid #bcd7ff;

            box-shadow:
                0 8px 25px rgba(31, 124, 228, .12),
                inset 0 0 20px rgba(255,255,255,.9);

            position: relative;

            z-index: 2;
        }


        .login-card-icon svg {

            width: 84px;
            height: 84px;

            overflow: visible;
        }


        .person-line {

            fill: none;

            stroke: #138bd7;

            stroke-width: 3.4;

            stroke-linecap: round;

            stroke-linejoin: round;
        }


        .shield-line {

            fill: #ffffff;

            stroke: #14b9b1;

            stroke-width: 3;

            stroke-linejoin: round;
        }


        .lock-line {

            fill: none;

            stroke: #168ed7;

            stroke-width: 3;

            stroke-linecap: round;

            stroke-linejoin: round;
        }


        /* ==================================================
           ENCABEZADO
        ================================================== */

        .login-card-header {

            text-align: center;

            position: relative;

            z-index: 2;
        }


        .login-card-header > span {

            display: inline-flex;

            align-items: center;

            gap: 13px;

            color: #147be5;

            font-size: 17px;

            font-weight: 900;

            letter-spacing: .4px;
        }


        .login-card-header > span::before,
        .login-card-header > span::after {

            content: "";

            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #13bca9;
        }


        .login-card-header h2 {

            margin: 20px 0 7px;

            font-size: 39px;

            font-weight: 900;

            color: #112f67;
        }


        .login-card-header p {

            margin: 0 0 28px;

            color: #637699;

            font-size: 17px;

            font-weight: 600;
        }


        /* ==================================================
           ERROR
        ================================================== */

        .login-error {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 14px 17px;

            margin-bottom: 23px;

            border-radius: 12px;

            background: #fff2f3;

            border: 1px solid #ffd0d5;

            color: #df3948;

            font-size: 14px;

            font-weight: 800;

            position: relative;

            z-index: 3;
        }


        .login-error i {

            font-size: 18px;

            flex-shrink: 0;
        }


        /* ==================================================
           CAMPOS
        ================================================== */

        .login-input-group {

            margin-bottom: 21px;

            position: relative;

            z-index: 2;
        }


        .login-input-group label {

            display: block;

            margin-bottom: 9px;

            font-size: 15px;

            font-weight: 900;

            color: #173568;
        }


        .login-input-wrapper {

            position: relative;
        }


        .login-input-wrapper > i {

            position: absolute;

            left: 19px;

            top: 50%;

            transform: translateY(-50%);

            color: #7188ae;

            font-size: 21px;

            z-index: 2;
        }


        .login-input-wrapper input {

            width: 100%;

            height: 61px;

            padding:
                0 52px 0 58px;

            border-radius: 13px;

            border: 1.5px solid #d8e3f1;

            outline: none;

            background: rgba(255,255,255,.86);

            color: #173568;

            font-family: "Nunito", sans-serif;

            font-size: 16px;

            font-weight: 700;

            transition: .25s ease;

            box-shadow:
                inset 0 2px 5px rgba(30,80,130,.025);
        }


        .login-input-wrapper input::placeholder {

            color: #9aa9c0;

            font-weight: 600;
        }


        .login-input-wrapper input:focus {

            border-color: #36a3ed;

            background: #ffffff;

            box-shadow:
                0 0 0 4px rgba(46, 145, 236, .10);
        }


        /* ==================================================
           MOSTRAR CONTRASEÑA
        ================================================== */

        .password-toggle {

            position: absolute;

            right: 16px;

            top: 50%;

            transform: translateY(-50%);

            width: 35px;
            height: 35px;

            border: none;

            background: transparent;

            color: #7188ae;

            cursor: pointer;

            font-size: 19px;
        }


        .password-toggle:hover {

            color: #1488dc;
        }


        /* ==================================================
           BOTÓN
        ================================================== */

        .login-button {

            width: 100%;

            height: 61px;

            margin-top: 8px;

            border: none;

            border-radius: 14px;

            background:
                linear-gradient(
                    100deg,
                    #287cf0,
                    #159fe5 52%,
                    #11c5ae
                );

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 16px;

            font-family: "Nunito", sans-serif;

            font-size: 17px;

            font-weight: 900;

            cursor: pointer;

            box-shadow:
                0 12px 25px rgba(35, 126, 233, .20);

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }


        .login-button i {

            font-size: 24px;

            transition: transform .2s ease;
        }


        .login-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 16px 30px rgba(35, 126, 233, .27);
        }


        .login-button:hover i {

            transform: translateX(5px);
        }


        .login-button:active {

            transform: translateY(0);
        }


        /* ==================================================
           SEGURIDAD
        ================================================== */

        .login-card-bottom {

            margin-top: 31px;

            display: flex;

            justify-content: center;

            align-items: center;

            gap: 12px;

            color: #52698f;

            font-size: 14px;

            font-weight: 800;
        }


        .login-card-bottom i {

            width: 38px;
            height: 38px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: #edf6ff;

            color: #397bc7;

            font-size: 17px;
        }


        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 1100px) {

            .login-container {

                grid-template-columns: 1fr;

                max-width: 720px;

                gap: 20px;

                padding-top: 30px;
            }


            .login-presentation {

                min-height: auto;

                padding: 20px 10px;

                text-align: center;

                align-items: center;
            }


            .login-brand {

                justify-content: center;

                text-align: left;
            }


            .login-presentation-content {

                margin-top: 55px;
            }


            .login-presentation h1 {

                font-size: 50px;
            }


            .login-presentation p {

                margin-left: auto;
                margin-right: auto;
            }


            .login-benefits {

                justify-content: center;
            }


            .login-footer-left {

                margin-top: 45px;
            }

        }


        @media (max-width: 600px) {

            .login-container {

                width: 100%;

                padding: 20px 14px;
            }


            .login-presentation {

                padding: 10px 5px;
            }


            .login-logo {

                width: 60px;
                height: 60px;
            }


            .login-brand-name {

                font-size: 21px;
            }


            .login-brand-subtitle {

                font-size: 13px;
            }


            .login-presentation-content {

                margin-top: 35px;
            }


            .login-presentation h1 {

                font-size: 40px;

                letter-spacing: -1px;
            }


            .login-presentation p {

                font-size: 16px;
            }


            .login-benefits {

                gap: 10px;
            }


            .login-benefit {

                width: 100px;
            }


            .login-benefit-icon {

                width: 65px;
                height: 65px;

                font-size: 25px;
            }


            .login-benefit span {

                font-size: 12px;
            }


            .login-card {

                padding: 35px 23px 28px;

                border-radius: 26px;
            }


            .login-card-icon {

                width: 110px;
                height: 110px;
            }


            .login-card-header h2 {

                font-size: 33px;
            }


            .login-card-header > span {

                font-size: 14px;
            }


            .login-footer-left {

                font-size: 12px;
            }

        }

    </style>

</head>


<body>


<main class="login-container">


    <!-- ==================================================
         PRESENTACIÓN
    ================================================== -->

    <section class="login-presentation">


        <div class="login-brand">

            <img
                src="../Logo.png"
                alt="Logo del Sistema"
                class="login-logo"
            >

            <div>

                <div class="login-brand-name">
                    Sistema de Asistencia QR
                </div>

                <div class="login-brand-subtitle">
                    Gestión académica inteligente
                </div>

            </div>

        </div>


        <div class="login-presentation-content">


            <div class="login-badge">

                <span></span>

                SISTEMA DIGITAL DE ASISTENCIA

            </div>


            <h1>

                Gestiona la asistencia

                <span>
                    de forma simple.
                </span>

            </h1>


            <p>

                Accede a la plataforma para registrar,
                consultar y administrar la asistencia
                académica mediante tecnología QR.

            </p>


            <div class="login-benefits">


                <div class="login-benefit">

                    <div class="login-benefit-icon">

                        <i class="bi bi-qr-code-scan"></i>

                    </div>

                    <span>
                        Registro mediante
                        códigos QR
                    </span>

                </div>


                <div class="login-benefit">

                    <div class="login-benefit-icon">

                        <i class="bi bi-bar-chart-line-fill"></i>

                    </div>

                    <span>
                        Control y
                        seguimiento
                    </span>

                </div>


                <div class="login-benefit">

                    <div class="login-benefit-icon">

                        <i class="bi bi-shield-lock-fill"></i>

                    </div>

                    <span>
                        Información
                        segura
                    </span>

                </div>


            </div>

        </div>

    </section>


    <!-- ==================================================
         LOGIN
    ================================================== -->

    <section class="login-form-section">


        <div class="login-card">


            <!-- ICONO -->

            <div class="login-card-icon">

                <svg
                    viewBox="0 0 100 100"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >

                    <circle
                        cx="42"
                        cy="31"
                        r="13"
                        class="person-line"
                    />


                    <path
                        d="
                            M18 68
                            C18 52,
                            28 45,
                            42 45
                            C56 45,
                            66 52,
                            66 68
                        "
                        class="person-line"
                    />


                    <path
                        d="
                            M65 43
                            L81 49
                            L81 65
                            C81 75,
                            74 82,
                            65 87
                            C56 82,
                            49 75,
                            49 65
                            L49 49
                            Z
                        "
                        class="shield-line"
                    />


                    <rect
                        x="59"
                        y="61"
                        width="12"
                        height="12"
                        rx="2"
                        class="lock-line"
                    />


                    <path
                        d="
                            M62 61
                            V57
                            C62 53,
                            68 53,
                            68 57
                            V61
                        "
                        class="lock-line"
                    />

                </svg>

            </div>


            <!-- ENCABEZADO -->

            <div class="login-card-header">

                <span>
                    ACCESO AL SISTEMA
                </span>

                <h2>
                    Bienvenido
                </h2>

                <p>
                    Ingresa tus datos para continuar.
                </p>

            </div>


            <!-- ERROR -->

            <?php if ($error !== ''): ?>

                <div class="login-error">

                    <i class="bi bi-exclamation-circle-fill"></i>

                    <span>

                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </span>

                </div>

            <?php endif; ?>


            <!-- FORMULARIO -->

            <form
                method="POST"
                action="procesar_login.php"
                autocomplete="on"
            >


                <!-- USUARIO -->

                <div class="login-input-group">

                    <label for="usuario">
                        Usuario
                    </label>

                    <div class="login-input-wrapper">

                        <i class="bi bi-person"></i>

                        <input
                            type="text"
                            id="usuario"
                            name="usuario"
                            placeholder="Ingresa tu usuario"
                            autocomplete="username"
                            maxlength="150"
                            required
                        >

                    </div>

                </div>


                <!-- CONTRASEÑA -->

                <div class="login-input-group">

                    <label for="password">
                        Contraseña
                    </label>

                    <div class="login-input-wrapper">

                        <i class="bi bi-lock"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Ingresa tu contraseña"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="mostrarPassword()"
                            aria-label="Mostrar contraseña"
                        >

                            <i
                                class="bi bi-eye"
                                id="password-icon"
                            ></i>

                        </button>

                    </div>

                </div>


                <!-- BOTÓN -->

                <button
                    type="submit"
                    class="login-button"
                >

                    <span>
                        Ingresar al sistema
                    </span>

                    <i class="bi bi-arrow-right"></i>

                </button>


            </form>


            <!-- SEGURIDAD -->

            <div class="login-card-bottom">

                <i class="bi bi-shield-lock-fill"></i>

                <span>
                    Acceso protegido y seguro
                </span>

            </div>


        </div>


    </section>


</main>


<script>

function mostrarPassword() {

    const password =
        document.getElementById("password");

    const icon =
        document.getElementById("password-icon");


    if (password.type === "password") {

        password.type = "text";

        icon.classList.remove("bi-eye");

        icon.classList.add("bi-eye-slash");

    } else {

        password.type = "password";

        icon.classList.remove("bi-eye-slash");

        icon.classList.add("bi-eye");

    }

}

</script>


</body>

</html>