-- Inventario tecnológico escolar
-- Base de datos para MySQL/MariaDB mediante phpMyAdmin.
-- Los datos de ejemplo son ficticios.

CREATE DATABASE IF NOT EXISTS inventario_colegio
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE inventario_colegio;

CREATE TABLE IF NOT EXISTS equipos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  categoria VARCHAR(80) NOT NULL,
  marca VARCHAR(80) NULL,
  modelo VARCHAR(80) NULL,
  estado ENUM('disponible', 'mantenimiento', 'inactivo') NOT NULL DEFAULT 'disponible',
  observaciones VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equipos_codigo (codigo),
  KEY idx_equipos_nombre (nombre),
  KEY idx_equipos_categoria (categoria),
  KEY idx_equipos_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prestamos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  equipo_id INT UNSIGNED NOT NULL,
  responsable VARCHAR(150) NOT NULL,
  fecha_prestamo DATE NOT NULL,
  fecha_limite DATE NOT NULL,
  fecha_devolucion DATE NULL,
  observaciones VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prestamos_equipo_abierto (equipo_id, fecha_devolucion),
  KEY idx_prestamos_fechas (fecha_prestamo, fecha_limite),
  CONSTRAINT fk_prestamos_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_usuario VARCHAR(50) NOT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_nombre (nombre_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventario ficticio para comprobar la pantalla principal.
INSERT INTO equipos (codigo, nombre, categoria, marca, modelo, estado, observaciones)
VALUES
  ('TEC-001', 'Computadora portátil', 'Cómputo', 'Lenovo', 'IdeaPad 3', 'disponible', 'Equipo de demostración'),
  ('TEC-002', 'Proyector multimedia', 'Proyección', 'Epson', 'PowerLite', 'disponible', 'Equipo de demostración'),
  ('TEC-003', 'Impresora multifuncional', 'Impresión', 'HP', 'LaserJet Pro', 'mantenimiento', 'Revisión preventiva'),
  ('TEC-004', 'Computadora de escritorio', 'Cómputo', 'Dell', 'OptiPlex', 'disponible', 'Equipo de demostración'),
  ('TEC-005', 'Router inalámbrico', 'Redes', 'TP-Link', 'Archer', 'disponible', 'Equipo de demostración')
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  categoria = VALUES(categoria),
  marca = VALUES(marca),
  modelo = VALUES(modelo),
  estado = VALUES(estado),
  observaciones = VALUES(observaciones);
