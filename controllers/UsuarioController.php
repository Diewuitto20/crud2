<?php
require_once 'models/UsuarioModel.php';

class UsuarioController {
    private $modelo;
    
    public function __construct($db) {
        $this->modelo = new Usuario($db);
    }

    public function index():void {
        try {
            $usuarios = $this->modelo->listar();

            $viewPath = 'views/UsuarioView.php';
            
            if (!file_exists($viewPath)) {
                throw new Exception("La vista '$viewPath' no se encuentra en el servidor.");
            }

            include $viewPath;
          
        } 
        catch (Throwable $e) {
            logger("Error en UsuarioController::index -> " . $e->getMessage());
            include 'views/errors/404.php';
        }  
    }

    public function crear():void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = $_POST['nombre'] ?? '';
            $email = $_POST['email'] ?? '';

            if (!empty($nombre) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->modelo->guardar($nombre, $email);
            }
            header("Location: index.php");
            exit;
        }
    }

    public function borrar(int $id): void {
        if ($id) {
            $this->modelo->eliminar($id);
        }
        header("Location: index.php");
        exit;
    }

  
    public function editar(int $id):void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = $_POST['nombre'] ?? '';
            $email = $_POST['email'] ?? '';

            if (!empty($nombre) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->modelo->actualizar($id, $nombre, $email);
            }

            header("Location: index.php");
            exit;
        }

        // Si es GET, buscamos al usuario para llenar el formulario
        $usuario = $this->modelo->obtenerPorId($id);
        if (!$usuario) {
            header("Location: index.php");
            exit;
        }
    
        include 'views/editUsuario.php';
    }
}

/*

Les  comparto como un extra
FILTER_VALIDATE_INT
Valida si es un número entero.
FILTER_VALIDATE_FLOAT
Valida números decimales.
FILTER_VALIDATE_BOOLEAN
Valida valores booleanos (true, false, 1, 0, "yes", "no").
FILTER_VALIDATE_EMAIL
Valida correos electrónicos.
FILTER_VALIDATE_URL
Valida URLs.
FILTER_VALIDATE_IP
Valida direcciones IP (IPv4 o IPv6).
FILTER_VALIDATE_MAC
Valida direcciones MAC.
FILTER_VALIDATE_REGEXP
Permite validar usando una expresión regular personalizada.
*/

?>
