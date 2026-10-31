-- ClinicaDB: esquema reconstruido por ingeniería inversa del código PHP
-- Uso: C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < db\ClinicaDB.sql

CREATE DATABASE IF NOT EXISTS ClinicaDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ClinicaDB;

-- ---------- Roles / Usuarios ----------
CREATE TABLE IF NOT EXISTS Roles (
    RolID INT NOT NULL AUTO_INCREMENT,
    NombreRol VARCHAR(50) NOT NULL,
    Descripcion VARCHAR(200) NULL,
    Activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (RolID),
    UNIQUE KEY UQ_Roles_NombreRol (NombreRol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Usuarios (
    UsuarioID INT NOT NULL AUTO_INCREMENT,
    NombreUsuario VARCHAR(50) NOT NULL,
    PasswordHash VARBINARY(255) NOT NULL,  -- AES_ENCRYPT(password,'CONTROLCLINICA')
    NombreCompleto VARCHAR(150) NOT NULL,
    Email VARCHAR(100) NOT NULL,
    Telefono VARCHAR(20) NULL,
    RolID INT NOT NULL,
    Activo TINYINT(1) NOT NULL DEFAULT 1,
    FechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UltimoAcceso DATETIME NULL,
    PRIMARY KEY (UsuarioID),
    UNIQUE KEY UQ_Usuarios_NombreUsuario (NombreUsuario),
    KEY IX_Usuarios_RolID (RolID),
    CONSTRAINT FK_Usuarios_Roles FOREIGN KEY (RolID) REFERENCES Roles (RolID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO Roles (NombreRol, Descripcion, Activo) VALUES
 ('Administrador', 'Acceso total al sistema', 1),
 ('Médico', 'Atiende consultas y expedientes', 1),
 ('Recepcionista', 'Gestiona pacientes y citas', 1),
 ('Enfermera', 'Apoyo en pacientes y expedientes', 1)
ON DUPLICATE KEY UPDATE NombreRol = VALUES(NombreRol);

INSERT INTO Usuarios (NombreUsuario, PasswordHash, NombreCompleto, Email, Telefono, RolID, Activo)
SELECT 'admin', AES_ENCRYPT('admin123', 'CONTROLCLINICA'), 'Administrador del Sistema', 'admin@clinica.com', NULL, r.RolID, 1
FROM Roles r WHERE r.NombreRol = 'Administrador'
ON DUPLICATE KEY UPDATE NombreUsuario = NombreUsuario;

-- ---------- Pacientes ----------
CREATE TABLE IF NOT EXISTS Pacientes (
    PacienteID INT NOT NULL AUTO_INCREMENT,
    NumeroExpediente VARCHAR(20) NOT NULL,            -- 'EXP' + 6 dígitos
    Nombres VARCHAR(100) NOT NULL,
    Apellidos VARCHAR(100) NOT NULL,
    FechaNacimiento DATE NOT NULL,
    Sexo CHAR(1) NOT NULL,                            -- 'M' | 'F'
    Telefono VARCHAR(20) NULL,
    Email VARCHAR(100) NULL,
    Direccion VARCHAR(255) NULL,
    ContactoEmergencia VARCHAR(100) NULL,
    TelefonoEmergencia VARCHAR(20) NULL,
    FechaRegistro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UsuarioRegistro INT NULL,
    Activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (PacienteID),
    UNIQUE KEY UQ_Pacientes_NumeroExpediente (NumeroExpediente),
    KEY IX_Pacientes_Apellidos_Nombres (Apellidos, Nombres),
    CONSTRAINT FK_Pacientes_UsuarioRegistro FOREIGN KEY (UsuarioRegistro) REFERENCES Usuarios (UsuarioID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Citas ----------
CREATE TABLE IF NOT EXISTS EstadosCita (
    EstadoCitaID INT NOT NULL AUTO_INCREMENT,
    NombreEstado VARCHAR(50) NOT NULL,
    Color VARCHAR(20) NULL,
    PRIMARY KEY (EstadoCitaID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1 = inicial/pendiente (dashboard), 4 = completada (consultas/nueva.php)
INSERT IGNORE INTO EstadosCita (EstadoCitaID, NombreEstado, Color) VALUES
    (1, 'Programada', '#0d6efd'),
    (2, 'Confirmada', '#198754'),
    (3, 'Cancelada', '#dc3545'),
    (4, 'Completada', '#6c757d'),
    (5, 'No Asistió', '#ffc107');

CREATE TABLE IF NOT EXISTS Citas (
    CitaID INT NOT NULL AUTO_INCREMENT,
    PacienteID INT NOT NULL,
    FechaCita DATETIME NOT NULL,
    DuracionMinutos INT NOT NULL DEFAULT 30,
    MotivoConsulta TEXT NOT NULL,
    EstadoCitaID INT NOT NULL DEFAULT 1,
    MedicoAsignado INT NULL,
    Observaciones TEXT NULL,
    UsuarioCreacion INT NULL,
    FechaCreacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (CitaID),
    KEY idx_Citas_FechaCita (FechaCita),
    KEY idx_Citas_PacienteID (PacienteID),
    KEY idx_Citas_EstadoCitaID (EstadoCitaID),
    KEY idx_Citas_MedicoAsignado (MedicoAsignado),
    CONSTRAINT fk_Citas_Paciente FOREIGN KEY (PacienteID) REFERENCES Pacientes (PacienteID),
    CONSTRAINT fk_Citas_EstadoCita FOREIGN KEY (EstadoCitaID) REFERENCES EstadosCita (EstadoCitaID),
    CONSTRAINT fk_Citas_Medico FOREIGN KEY (MedicoAsignado) REFERENCES Usuarios (UsuarioID),
    CONSTRAINT fk_Citas_UsuarioCreacion FOREIGN KEY (UsuarioCreacion) REFERENCES Usuarios (UsuarioID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Medicamentos / Lotes ----------
CREATE TABLE IF NOT EXISTS Medicamentos (
    MedicamentoID     INT NOT NULL AUTO_INCREMENT,
    NombreMedicamento VARCHAR(150) NOT NULL,
    PrincipioActivo   VARCHAR(150) NOT NULL,
    Presentacion      VARCHAR(100) NOT NULL,
    Activo            TINYINT(1) NOT NULL DEFAULT 1,
    FechaCreacion     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (MedicamentoID),
    UNIQUE KEY UQ_Medicamentos_Nombre (NombreMedicamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS LotesMedicamentos (
    LoteID           INT NOT NULL AUTO_INCREMENT,
    MedicamentoID    INT NOT NULL,
    NumeroLote       VARCHAR(50) NOT NULL,
    FechaVencimiento DATE NOT NULL,
    CantidadInicial  INT NOT NULL,
    CantidadActual   INT NOT NULL,
    Activo           TINYINT(1) NOT NULL DEFAULT 1,
    FechaIngreso     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (LoteID),
    UNIQUE KEY UQ_LotesMedicamentos_NumeroLote (NumeroLote),
    KEY IX_LotesMedicamentos_Medicamento (MedicamentoID),
    CONSTRAINT FK_LotesMedicamentos_Medicamentos FOREIGN KEY (MedicamentoID) REFERENCES Medicamentos (MedicamentoID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TipoMovimiento: 'E' entrada, 'S' salida, 'A' ajuste
CREATE TABLE IF NOT EXISTS MovimientosLote (
    MovimientoID    INT NOT NULL AUTO_INCREMENT,
    LoteID          INT NOT NULL,
    TipoMovimiento  CHAR(1) NOT NULL,
    Cantidad        INT NOT NULL,
    Observaciones   VARCHAR(500) NULL,
    UsuarioID       INT NULL,
    FechaMovimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (MovimientoID),
    KEY IX_MovimientosLote_Lote (LoteID),
    CONSTRAINT CK_MovimientosLote_Tipo CHECK (TipoMovimiento IN ('E','S','A')),
    CONSTRAINT FK_MovimientosLote_Lotes FOREIGN KEY (LoteID) REFERENCES LotesMedicamentos (LoteID),
    CONSTRAINT FK_MovimientosLote_Usuarios FOREIGN KEY (UsuarioID) REFERENCES Usuarios (UsuarioID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Consultas / Recetas ----------
CREATE TABLE IF NOT EXISTS ConsultasMedicas (
    ConsultaID INT NOT NULL AUTO_INCREMENT,
    PacienteID INT NOT NULL,
    CitaID INT NULL,
    FechaConsulta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MotivoConsulta TEXT NULL,
    SignosVitales TEXT NULL,
    Diagnostico TEXT NOT NULL,
    Tratamiento TEXT NULL,
    Observaciones TEXT NULL,
    MedicoID INT NOT NULL,
    PRIMARY KEY (ConsultaID),
    KEY IX_ConsultasMedicas_Fecha (FechaConsulta),
    CONSTRAINT FK_ConsultasMedicas_Pacientes FOREIGN KEY (PacienteID) REFERENCES Pacientes(PacienteID),
    CONSTRAINT FK_ConsultasMedicas_Citas FOREIGN KEY (CitaID) REFERENCES Citas(CitaID),
    CONSTRAINT FK_ConsultasMedicas_Usuarios FOREIGN KEY (MedicoID) REFERENCES Usuarios(UsuarioID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Recetas (
    RecetaID INT NOT NULL AUTO_INCREMENT,
    ConsultaID INT NOT NULL,
    FechaReceta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Observaciones TEXT NULL,
    PRIMARY KEY (RecetaID),
    KEY IX_Recetas_ConsultaID (ConsultaID),
    CONSTRAINT FK_Recetas_ConsultasMedicas FOREIGN KEY (ConsultaID) REFERENCES ConsultasMedicas(ConsultaID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS RecetaDetalle (
    RecetaDetalleID INT NOT NULL AUTO_INCREMENT,
    RecetaID INT NOT NULL,
    MedicamentoID INT NOT NULL,
    Dosis VARCHAR(100) NOT NULL,
    Frecuencia VARCHAR(100) NOT NULL,
    Duracion VARCHAR(100) NOT NULL,
    Indicaciones VARCHAR(255) NULL,
    PRIMARY KEY (RecetaDetalleID),
    CONSTRAINT FK_RecetaDetalle_Recetas FOREIGN KEY (RecetaID) REFERENCES Recetas(RecetaID),
    CONSTRAINT FK_RecetaDetalle_Medicamentos FOREIGN KEY (MedicamentoID) REFERENCES Medicamentos(MedicamentoID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Stored procedure de login ----------
DROP PROCEDURE IF EXISTS sp_ValidarLogin;

DELIMITER //
CREATE PROCEDURE sp_ValidarLogin(IN p_NombreUsuario VARCHAR(50), IN p_Password VARCHAR(255))
BEGIN
    DECLARE v_id INT;

    SELECT u.UsuarioID INTO v_id
    FROM Usuarios u
    WHERE u.NombreUsuario = p_NombreUsuario
      AND u.PasswordHash = AES_ENCRYPT(p_Password, 'CONTROLCLINICA')
      AND u.Activo = 1
    LIMIT 1;

    IF v_id IS NOT NULL THEN
        UPDATE Usuarios SET UltimoAcceso = NOW() WHERE UsuarioID = v_id;
    END IF;

    SELECT u.UsuarioID, u.NombreUsuario, u.NombreCompleto, r.NombreRol, u.Activo
    FROM Usuarios u
    INNER JOIN Roles r ON u.RolID = r.RolID
    WHERE u.UsuarioID = v_id;
END//
DELIMITER ;
