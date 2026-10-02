-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-10-2026 a las 05:51:10
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `asistencia_qr_escolar`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_clase`
--

CREATE TABLE `asistencia_clase` (
  `id_asistencia` int(11) NOT NULL,
  `id_sesion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `estado` enum('PRESENTE','AUSENTE','TARDE','EVADIO') DEFAULT 'PRESENTE',
  `estado_excusa` varchar(50) DEFAULT NULL,
  `hora_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_llegada`
--

CREATE TABLE `asistencia_llegada` (
  `id_llegada` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_llegada` time NOT NULL DEFAULT current_timestamp(),
  `estado` enum('LLEGADA TARDE') NOT NULL DEFAULT 'LLEGADA TARDE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia_restaurante`
--

CREATE TABLE `asistencia_restaurante` (
  `id_asistencia_restaurante` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('REGISTRADO','NO_REGISTRADO') DEFAULT 'REGISTRADO',
  `observacion` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id_auditoria` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nombre_curso` varchar(100) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nombre_curso`, `estado`, `fecha_creacion`) VALUES
(1, '1104', 'ACTIVO', '2026-08-29 20:38:10'),
(2, '1003', 'ACTIVO', '2026-08-29 20:40:31'),
(3, '1101', 'ACTIVO', '2026-08-29 20:59:20'),
(4, '1102', 'ACTIVO', '2026-08-29 20:59:37'),
(5, '1103', 'ACTIVO', '2026-08-29 20:59:41'),
(6, '1001', 'ACTIVO', '2026-08-29 20:59:45'),
(7, '1002', 'ACTIVO', '2026-08-29 20:59:47'),
(8, '1004', 'ACTIVO', '2026-08-29 20:59:52'),
(9, '601', 'ACTIVO', '2026-08-29 21:00:00'),
(10, '602', 'INACTIVO', '2026-08-29 21:00:04'),
(11, '603', 'INACTIVO', '2026-08-29 21:00:07'),
(12, '604', 'INACTIVO', '2026-08-29 21:00:10'),
(13, '605', 'ACTIVO', '2026-08-29 21:00:13'),
(14, '606', 'INACTIVO', '2026-08-29 21:00:17'),
(15, '607', 'INACTIVO', '2026-08-29 21:00:22'),
(16, '801', 'ACTIVO', '2026-08-29 23:28:30'),
(17, '802', 'INACTIVO', '2026-08-29 23:37:01'),
(18, '701', 'ACTIVO', '2026-08-30 05:22:42'),
(20, '608', 'ACTIVO', '2026-08-31 23:42:19'),
(21, '702', 'ACTIVO', '2026-08-31 23:50:11'),
(22, '703', 'ACTIVO', '2026-08-31 23:53:16'),
(24, '704', 'ACTIVO', '2026-08-31 23:53:38'),
(25, '705', 'ACTIVO', '2026-09-01 00:00:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `director_curso`
--

CREATE TABLE `director_curso` (
  `id_director_curso` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_asignacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_curso`
--

CREATE TABLE `docente_curso` (
  `id_docente_curso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL,
  `documento` varchar(30) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `documento`, `nombres`, `apellidos`, `id_curso`, `estado`, `fecha_creacion`) VALUES
(1, '10000001', 'Tetiana', 'mañana', 1, 'ACTIVO', '2026-08-29 20:38:33'),
(2, '100000007', 'Laurix', 'Jr', 1, 'ACTIVO', '2026-08-29 20:40:24'),
(3, '1000000022', 'Chan', 'Eke', 2, 'ACTIVO', '2026-08-29 20:41:00'),
(4, '1000000027', 'Pilin', 'Gonzalez', 1, 'ACTIVO', '2026-08-29 20:55:12'),
(5, '1000000020', 'Zozozorra Zozorrita', 'JR', 1, 'ACTIVO', '2026-08-29 20:55:40'),
(6, '1000000023', 'Gerar niño del choco', 'Chocuano', 1, 'ACTIVO', '2026-08-29 20:56:02'),
(7, '100000009', 'Dayanubius', 'ñatñat', 1, 'ACTIVO', '2026-08-29 20:56:29'),
(8, '10000008', 'Joela', 'JR', 9, 'ACTIVO', '2026-08-29 23:53:55'),
(9, '100000777', 'Karen', 'Ayer', 1, 'ACTIVO', '2026-08-31 23:47:22'),
(11, '1076662947', 'maria', 'prieto', 5, 'ACTIVO', '2026-09-15 00:26:31'),
(12, '1000000006', 'acum', 'uñaña', 5, 'ACTIVO', '2026-09-15 01:58:18'),
(13, '11111111111', 'tuntuhn', 'nosexd', 9, 'ACTIVO', '2026-09-15 03:00:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'ADMINISTRADOR'),
(2, 'DOCENTE'),
(3, 'RESTAURANTE');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_clase`
--

CREATE TABLE `sesiones_clase` (
  `id_sesion` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `estado` enum('ABIERTA','CERRADA') DEFAULT 'ABIERTA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `usuario`, `password`, `id_rol`, `estado`, `fecha_creacion`) VALUES
(9, 'Administrador', 'Sistema', 'admin', '$2y$12$8KvR9AFelZGhS7t2YE9Vpu0581KDZjAbUQK38LvrC4u94HYI5dZnu', 1, 'ACTIVO', '2026-10-02 03:44:10'),
(10, 'Docente', 'Principal', 'docente', '$2y$12$ITQSgWajIE7fspKx6uaP..JhdaS/fkw2LbxlbSBN3SqRIX.f9Zn7a', 2, 'ACTIVO', '2026-10-02 03:44:10'),
(11, 'Restaurante', 'Colegio', 'restaurante', '$2y$12$F1C.6jVeieFWJ1FL6G6Aau6tpbVLpA/K8zUTrAwHpQMZhGankXSZ2', 3, 'ACTIVO', '2026-10-02 03:44:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_acceso`
--

CREATE TABLE `usuarios_acceso` (
  `id_acceso` int(11) NOT NULL,
  `numero_identificacion` varchar(30) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios_acceso`
--

INSERT INTO `usuarios_acceso` (`id_acceso`, `numero_identificacion`, `nombre_completo`, `id_rol`, `fecha_registro`) VALUES
(1, '1059910341', 'Heilin Ledesma', 1, '2026-10-01 22:44:58'),
(2, '1000000009', 'Jhon Vivas', 2, '2026-10-01 22:46:18'),
(3, '10000000007', 'Totis Mañana', 3, '2026-10-01 22:46:59');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asistencia_clase`
--
ALTER TABLE `asistencia_clase`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `asistencia_unica` (`id_sesion`,`id_estudiante`),
  ADD KEY `fk_asistencia_estudiante` (`id_estudiante`);

--
-- Indices de la tabla `asistencia_llegada`
--
ALTER TABLE `asistencia_llegada`
  ADD PRIMARY KEY (`id_llegada`),
  ADD UNIQUE KEY `llegada_unica_dia` (`id_estudiante`,`fecha`),
  ADD KEY `fk_llegada_estudiante` (`id_estudiante`);

--
-- Indices de la tabla `asistencia_restaurante`
--
ALTER TABLE `asistencia_restaurante`
  ADD PRIMARY KEY (`id_asistencia_restaurante`),
  ADD KEY `fk_restaurante_estudiante` (`id_estudiante`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `fk_auditoria_usuario` (`id_usuario`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`),
  ADD UNIQUE KEY `nombre_curso` (`nombre_curso`);

--
-- Indices de la tabla `director_curso`
--
ALTER TABLE `director_curso`
  ADD PRIMARY KEY (`id_director_curso`),
  ADD UNIQUE KEY `unico_curso` (`id_curso`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `docente_curso`
--
ALTER TABLE `docente_curso`
  ADD PRIMARY KEY (`id_docente_curso`),
  ADD UNIQUE KEY `docente_curso_unico` (`id_usuario`,`id_curso`),
  ADD KEY `fk_dc_curso` (`id_curso`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD UNIQUE KEY `documento` (`documento`),
  ADD KEY `fk_estudiante_curso` (`id_curso`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `sesiones_clase`
--
ALTER TABLE `sesiones_clase`
  ADD PRIMARY KEY (`id_sesion`),
  ADD KEY `fk_sesion_docente` (`id_docente`),
  ADD KEY `fk_sesion_curso` (`id_curso`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `fk_usuario_rol` (`id_rol`);

--
-- Indices de la tabla `usuarios_acceso`
--
ALTER TABLE `usuarios_acceso`
  ADD PRIMARY KEY (`id_acceso`),
  ADD UNIQUE KEY `uk_numero_identificacion` (`numero_identificacion`),
  ADD KEY `fk_usuarios_acceso_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asistencia_clase`
--
ALTER TABLE `asistencia_clase`
  MODIFY `id_asistencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asistencia_llegada`
--
ALTER TABLE `asistencia_llegada`
  MODIFY `id_llegada` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asistencia_restaurante`
--
ALTER TABLE `asistencia_restaurante`
  MODIFY `id_asistencia_restaurante` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id_auditoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `director_curso`
--
ALTER TABLE `director_curso`
  MODIFY `id_director_curso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente_curso`
--
ALTER TABLE `docente_curso`
  MODIFY `id_docente_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `sesiones_clase`
--
ALTER TABLE `sesiones_clase`
  MODIFY `id_sesion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `usuarios_acceso`
--
ALTER TABLE `usuarios_acceso`
  MODIFY `id_acceso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencia_clase`
--
ALTER TABLE `asistencia_clase`
  ADD CONSTRAINT `fk_asistencia_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_asistencia_sesion` FOREIGN KEY (`id_sesion`) REFERENCES `sesiones_clase` (`id_sesion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asistencia_llegada`
--
ALTER TABLE `asistencia_llegada`
  ADD CONSTRAINT `fk_llegada_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `asistencia_restaurante`
--
ALTER TABLE `asistencia_restaurante`
  ADD CONSTRAINT `fk_restaurante_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE;

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `director_curso`
--
ALTER TABLE `director_curso`
  ADD CONSTRAINT `director_curso_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  ADD CONSTRAINT `director_curso_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `docente_curso`
--
ALTER TABLE `docente_curso`
  ADD CONSTRAINT `fk_dc_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dc_docente` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `fk_estudiante_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`);

--
-- Filtros para la tabla `sesiones_clase`
--
ALTER TABLE `sesiones_clase`
  ADD CONSTRAINT `fk_sesion_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  ADD CONSTRAINT `fk_sesion_docente` FOREIGN KEY (`id_docente`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Filtros para la tabla `usuarios_acceso`
--
ALTER TABLE `usuarios_acceso`
  ADD CONSTRAINT `fk_usuarios_acceso_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
