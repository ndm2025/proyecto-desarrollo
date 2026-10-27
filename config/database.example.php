<?php
class Database {
    private $host = "127.0.0.1";
    private $db_name = "ClinicaDB";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

function validarSesion() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: /clinica/login.php");
        exit();
    }
}

function tienePermiso($modulo) {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    $permisos = [
        'Administrador' => ['Dashboard', 'Pacientes', 'Citas', 'Expedientes', 'Usuarios', 'Reportes', 'Configuración', 'Medicamentos', 'Lotes'],
        'Médico' => ['Dashboard', 'Pacientes', 'Citas', 'Expedientes'],
        'Recepcionista' => ['Dashboard', 'Pacientes', 'Citas'],
        'Enfermera' => ['Dashboard', 'Pacientes', 'Expedientes']
    ];
    $rol = $_SESSION['rol'];
    return in_array($modulo, $permisos[$rol] ?? []);
}
?>