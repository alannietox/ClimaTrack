-- Schema for ClimaTrack
-- Only table structures, no production data

CREATE TABLE IF NOT EXISTS `ciudades_mundo` (
  `Id_Mundo` int NOT NULL,
  `Nombre` varchar(150) DEFAULT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `Id_Meteored` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`Id_Mundo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `datos_clima` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_municipio` varchar(20) DEFAULT NULL,
  `nombre_municipio` varchar(150) NOT NULL,
  `fecha` date NOT NULL,
  `temp_min` int DEFAULT NULL,
  `temp_max` int DEFAULT NULL,
  `estado_cielo` varchar(150) DEFAULT NULL,
  `estado_manana` varchar(150) DEFAULT NULL,
  `estado_tarde` varchar(150) DEFAULT NULL,
  `orto_sol` varchar(10) DEFAULT NULL,
  `ocaso_sol` varchar(10) DEFAULT NULL,
  `orto_luna` varchar(10) DEFAULT NULL,
  `ocaso_luna` varchar(10) DEFAULT NULL,
  `fase_lunar` varchar(50) DEFAULT NULL,
  `fecha_captura` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `humedad` int DEFAULT NULL,
  `precipitacion` decimal(5,2) DEFAULT NULL,
  `viento_vel` int DEFAULT NULL,
  `viento_dir` varchar(50) DEFAULT NULL,
  `presion` decimal(6,2) DEFAULT NULL,
  `temp_agua` decimal(4,1) DEFAULT NULL,
  `radiacion_solar` decimal(6,2) DEFAULT NULL,
  `racha_viento` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `municipio_fecha` (`id_municipio`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `datos_mundo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_ciudad` int DEFAULT NULL,
  `nombre_ciudad` varchar(150) DEFAULT NULL,
  `fecha` date NOT NULL,
  `temp_min` int DEFAULT NULL,
  `temp_max` int DEFAULT NULL,
  `estado_cielo` varchar(150) DEFAULT NULL,
  `fecha_captura` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ciudad_fecha` (`id_ciudad`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `embalses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT NULL,
  `volumen_actual` decimal(10,2) DEFAULT NULL,
  `capacidad_total` decimal(10,2) DEFAULT NULL,
  `porcentaje` decimal(5,2) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `localidades` (
  `Id_Loc_Aemet` varchar(20) NOT NULL,
  `Nombre` varchar(150) DEFAULT NULL,
  `Latitud` decimal(10,6) DEFAULT NULL,
  `Longitud` decimal(10,6) DEFAULT NULL,
  `Activado` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`Id_Loc_Aemet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `resumen_vasco` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `viento_vel` varchar(20) DEFAULT NULL,
  `viento_dir` varchar(10) DEFAULT NULL,
  `olas_altura` varchar(20) DEFAULT NULL,
  `olas_periodo` varchar(20) DEFAULT NULL,
  `olas_dir` varchar(10) DEFAULT NULL,
  `temp_ambiente` varchar(10) DEFAULT NULL,
  `nubosidad` varchar(10) DEFAULT NULL,
  `temp_agua` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
