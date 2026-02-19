<?php

namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Core\Base\DataBase;

class ApiAuth extends ApiController {

    public function run(): void {
        // cors para versión web
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Token, Authorization');
        header('Access-Control-Max-Age: 86400');
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // sin validación de token
        $this->runResource();
    }

    protected function runResource(): void {
        // leer cuerpo de la petición
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        error_log("API Request - Raw input: " . $rawInput);
        error_log("API Request - JSON data: " . print_r($jsonData, true));
        error_log("API Request - REQUEST_URI: " . $_SERVER['REQUEST_URI']);
        error_log("API Request - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

        // acción: json > url > post
        $action = isset($jsonData['action'])
            ? $jsonData['action']
            : ($this->request->query->get('action', '') ?: $this->request->request->get('action', ''));

        error_log("API Request - Action: " . $action);

        switch ($action) {
            case 'login':
                $this->login();
                break;
            case 'register':
                $this->register();
                break;
            case 'logout':
                $this->logout();
                break;
            case 'profile':
                $this->getProfile();
                break;
            case 'update-profile':
                $this->updateProfile();
                break;
            case 'update-security':
                $this->updateSecurity();
                break;
            case 'delete-account':
                $this->deleteAccount();
                break;
            default:
                error_log("API Request - Invalid action: " . $action);
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    }

    private function login() {
        $db = new DataBase();
        $db->connect();

        // leer cuerpo de la petición
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        error_log("Login attempt - Raw input: " . $rawInput);
        error_log("Login attempt - JSON data: " . print_r($jsonData, true));
        error_log("Login attempt - Request method: " . $_SERVER['REQUEST_METHOD']);

        // datos: json body o post params
        $email = isset($jsonData['email']) ? $jsonData['email'] : $this->request->request->get('email', '');
        $password = isset($jsonData['password']) ? $jsonData['password'] : $this->request->request->get('password', '');

        error_log("Login attempt - Email: " . $email);
        error_log("Login attempt - Password length: " . strlen($password));

        if (empty($email) || empty($password)) {
            error_log("Login failed - Empty email or password");
            echo json_encode([
                'success' => false,
                'message' => 'Email y contraseña son requeridos'
            ]);
            exit;
        }

        // buscar usuario por email
        $sql = "SELECT * FROM cl_usuarios WHERE email = " . $db->var2str($email);
        error_log("Login SQL query: " . $sql);
        $users = $db->select($sql);
        error_log("Login query result: " . print_r($users, true));

        if (empty($users)) {
            echo json_encode([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ]);
            exit;
        }

        $user = $users[0];

        // verificar contraseña
        if (!password_verify($password, $user['password_hash'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ]);
            exit;
        }

        // iniciar sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        // eliminar contraseña de la respuesta
        unset($user['password_hash']);

        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => $user,
                'session_id' => session_id()
            ]
        ]);

        exit;
    }

    private function register() {
        $db = new DataBase();
        $db->connect();

        // leer cuerpo de la petición
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        error_log("Register attempt - Raw input: " . $rawInput);
        error_log("Register attempt - JSON data: " . print_r($jsonData, true));

        // datos: json body o post params
        $email = isset($jsonData['email']) ? $jsonData['email'] : $this->request->request->get('email', '');
        $password = isset($jsonData['password']) ? $jsonData['password'] : $this->request->request->get('password', '');
        $nombre = isset($jsonData['nombre']) ? $jsonData['nombre'] : $this->request->request->get('nombre', '');
        $apellidos = isset($jsonData['apellidos']) ? $jsonData['apellidos'] : $this->request->request->get('apellidos', '');

        // validar campos requeridos
        if (empty($email) || empty($password) || empty($nombre) || empty($apellidos)) {
            error_log("Register failed - Missing required fields");
            echo json_encode([
                'success' => false,
                'message' => 'Todos los campos son requeridos'
            ]);
            exit;
        }

        // validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Email no válido'
            ]);
            exit;
        }

        // verificar si email ya existe
        $sql = "SELECT id FROM cl_usuarios WHERE email = " . $db->var2str($email);
        $existing = $db->select($sql);

        if (!empty($existing)) {
            error_log("Register failed - Email already exists: " . $email);
            echo json_encode([
                'success' => false,
                'message' => 'Este email ya está registrado'
            ]);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // conexión directa mysqli (evita problemas con transacciones)
        $dbHost = defined('FS_DB_HOST') ? FS_DB_HOST : 'localhost';
        $dbUser = defined('FS_DB_USER') ? FS_DB_USER : 'root';
        $dbPass = defined('FS_DB_PASS') ? FS_DB_PASS : 'root';
        $dbName = defined('FS_DB_NAME') ? FS_DB_NAME : 'facturascripts';
        $dbPort = defined('FS_DB_PORT') ? FS_DB_PORT : 3306;


        $mysqli = new \mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

        if ($mysqli->connect_error) {
            error_log("MySQL connection failed: " . $mysqli->connect_error);
            echo json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos'
            ]);
            exit;
        }

        $mysqli->set_charset('utf8mb4');

        // preparar consulta (asignatura existe en la tabla pero no en el esquema xml)
        $stmt = $mysqli->prepare("INSERT INTO cl_usuarios (email, password_hash, nombre, apellidos, asignatura, created_at, updated_at) VALUES (?, ?, ?, ?, '', NOW(), NOW())");

        if (!$stmt) {
            error_log("Prepare failed: " . $mysqli->error);
            echo json_encode([
                'success' => false,
                'message' => 'Error al preparar consulta'
            ]);
            $mysqli->close();
            exit;
        }

        $stmt->bind_param("ssss", $email, $passwordHash, $nombre, $apellidos);
        $result = $stmt->execute();

        if ($result) {
            $userId = $mysqli->insert_id;
            error_log("Register - New user ID: " . $userId);

            // obtener usuario recién creado
            $getUserStmt = $mysqli->prepare("SELECT id, email, nombre, apellidos, telefono, avatar_url, created_at, updated_at FROM cl_usuarios WHERE id = ?");
            $getUserStmt->bind_param("i", $userId);
            $getUserStmt->execute();
            $userResult = $getUserStmt->get_result();
            $user = $userResult->fetch_assoc();
            $getUserStmt->close();

            // iniciar sesión
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            $stmt->close();
            $mysqli->close();

            echo json_encode([
                'success' => true,
                'message' => 'Registro exitoso',
                'data' => [
                    'user' => $user,
                    'session_id' => session_id()
                ]
            ]);
        } else {
            $error = $stmt->error;
            error_log("Register failed: " . $error);
            $stmt->close();
            $mysqli->close();

            echo json_encode([
                'success' => false,
                'message' => 'Error al crear usuario: ' . $error
            ]);
        }

        exit;
    }

    private function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();

        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada'
        ]);

        exit;
    }

    private function getProfile() {
        $db = new DataBase();
        $db->connect();
        $userId = $this->request->query->get('id', '');

        if (empty($userId)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario requerido'
            ]);
            exit;
        }

        $userId = intval($userId);

        $sql = "SELECT * FROM cl_usuarios WHERE id = {$userId}";
        $users = $db->select($sql);

        if (empty($users)) {
            echo json_encode([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ]);
            exit;
        }

        $user = $users[0];
        unset($user['password_hash']);

        echo json_encode([
            'success' => true,
            'data' => $user
        ]);

        exit;
    }

    private function updateProfile() {
        $db = new DataBase();
        $db->connect();

        $userId = $this->request->request->get('id', '');
        $nombre = $this->request->request->get('nombre', '');
        $apellidos = $this->request->request->get('apellidos', '');
        $telefono = $this->request->request->get('telefono', '');

        if (empty($userId) || empty($nombre) || empty($apellidos)) {
            echo json_encode([
                'success' => false,
                'message' => 'Nombre y apellidos son requeridos'
            ]);
            exit;
        }

        $userId = intval($userId);

        $sql = "UPDATE cl_usuarios SET
                    nombre = " . $db->var2str($nombre) . ",
                    apellidos = " . $db->var2str($apellidos) . ",
                    telefono = " . $db->var2str($telefono) . ",
                    updated_at = NOW()
                WHERE id = {$userId}";

        if ($db->exec($sql)) {
            $sql = "SELECT * FROM cl_usuarios WHERE id = {$userId}";
            $users = $db->select($sql);
            $user = $users[0];
            unset($user['password_hash']);

            echo json_encode([
                'success' => true,
                'message' => 'Perfil actualizado',
                'data' => $user
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar perfil'
            ]);
        }

        exit;
    }

    private function updateSecurity() {
        $db = new DataBase();
        $db->connect();

        $userId = $this->request->request->get('id', '');
        $email = $this->request->request->get('email', '');
        $newPassword = $this->request->request->get('password', '');

        if (empty($userId)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario requerido'
            ]);
            exit;
        }

        $userId = intval($userId);

        // verificar si cambia el email
        if (!empty($email)) {
            // validar email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email no válido'
                ]);
                exit;
            }

            // comprobar si email ya está en uso
            $sql = "SELECT id FROM cl_usuarios WHERE email = " . $db->var2str($email) . " AND id != {$userId}";
            $existing = $db->select($sql);

            if (!empty($existing)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Este email ya está en uso'
                ]);
                exit;
            }
        }

        // construir consulta de actualización
        $updates = [];

        if (!empty($email)) {
            $updates[] = "email = " . $db->var2str($email);
        }

        if (!empty($newPassword)) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updates[] = "password_hash = " . $db->var2str($passwordHash);
        }

        if (empty($updates)) {
            echo json_encode([
                'success' => false,
                'message' => 'No hay cambios para actualizar'
            ]);
            exit;
        }

        $updates[] = "updated_at = NOW()";

        $sql = "UPDATE cl_usuarios SET " . implode(', ', $updates) . " WHERE id = {$userId}";

        if ($db->exec($sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Seguridad actualizada'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar'
            ]);
        }

        exit;
    }

    private function deleteAccount() {
        $db = new DataBase();
        $db->connect();

        $userId = $this->request->request->get('id', '');

        if (empty($userId)) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario requerido'
            ]);
            exit;
        }

        $userId = intval($userId);

        // eliminar usuario
        $sql = "DELETE FROM cl_usuarios WHERE id = {$userId}";

        if ($db->exec($sql)) {
            // destruir sesión
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_destroy();

            echo json_encode([
                'success' => true,
                'message' => 'Cuenta eliminada'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar cuenta'
            ]);
        }

        exit;
    }
}
