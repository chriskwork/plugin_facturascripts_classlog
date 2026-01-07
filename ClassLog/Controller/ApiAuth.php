<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Core\Base\DataBase;

class ApiAuth extends ApiController
{
    /**
     * Override run() to allow public access to auth endpoints (login, register)
     * without requiring API token validation
     */
    public function run(): void
    {
        // Set CORS headers for all requests
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Token, Authorization');
        header('Access-Control-Max-Age: 86400');
        header('Content-Type: application/json');

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Directly run the resource without token validation
        $this->runResource();
    }

    protected function runResource(): void
    {
        // Get request body
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        error_log("API Request - Raw input: " . $rawInput);
        error_log("API Request - JSON data: " . print_r($jsonData, true));
        error_log("API Request - REQUEST_URI: " . $_SERVER['REQUEST_URI']);
        error_log("API Request - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

        // Try to get action from JSON body first, then fall back to request params
        $action = isset($jsonData['action']) ? $jsonData['action'] : $this->request->request->get('action', '');

        error_log("API Request - Action: " . $action);

        switch($action) {
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

    private function login()
    {
        $db = new DataBase();
        $db->connect();

        // Get request body
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        error_log("Login attempt - Raw input: " . $rawInput);
        error_log("Login attempt - JSON data: " . print_r($jsonData, true));
        error_log("Login attempt - Request method: " . $_SERVER['REQUEST_METHOD']);

        // Try to get data from JSON body first, then fall back to request params
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

        // Get user by email
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

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ]);
            exit;
        }

        // Start session and store user info
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        // Remove password from response
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

    private function register()
    {
        $db = new DataBase();
        $db->connect();

        // Get request body
        $rawInput = file_get_contents('php://input');
        $jsonData = json_decode($rawInput, true);

        error_log("Register attempt - Raw input: " . $rawInput);
        error_log("Register attempt - JSON data: " . print_r($jsonData, true));

        // Try to get data from JSON body first, then fall back to request params
        $email = isset($jsonData['email']) ? $jsonData['email'] : $this->request->request->get('email', '');
        $password = isset($jsonData['password']) ? $jsonData['password'] : $this->request->request->get('password', '');
        $nombre = isset($jsonData['nombre']) ? $jsonData['nombre'] : $this->request->request->get('nombre', '');
        $apellidos = isset($jsonData['apellidos']) ? $jsonData['apellidos'] : $this->request->request->get('apellidos', '');

        // Validate required fields
        if (empty($email) || empty($password) || empty($nombre) || empty($apellidos)) {
            error_log("Register failed - Missing required fields");
            echo json_encode([
                'success' => false,
                'message' => 'Todos los campos son requeridos'
            ]);
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Email no válido'
            ]);
            exit;
        }

        // Check if email already exists
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

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Use direct mysqli connection to bypass FacturaScripts transaction issues
        // Get config values
        $dbHost = defined('FS_DB_HOST') ? FS_DB_HOST : 'localhost';
        $dbUser = defined('FS_DB_USER') ? FS_DB_USER : 'root';
        $dbPass = defined('FS_DB_PASS') ? FS_DB_PASS : 'root';
        $dbName = defined('FS_DB_NAME') ? FS_DB_NAME : 'facturascripts';
        $dbPort = defined('FS_DB_PORT') ? FS_DB_PORT : 3306;

        error_log("DB Connection params - Host: $dbHost, User: $dbUser, DB: $dbName, Port: $dbPort");

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

        // Prepare statement - include all required fields
        // Note: 'asignatura' field exists in the table but not in the XML schema
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

            // Get the newly created user
            $getUserStmt = $mysqli->prepare("SELECT id, email, nombre, apellidos, telefono, avatar_url, created_at, updated_at FROM cl_usuarios WHERE id = ?");
            $getUserStmt->bind_param("i", $userId);
            $getUserStmt->execute();
            $userResult = $getUserStmt->get_result();
            $user = $userResult->fetch_assoc();
            $getUserStmt->close();

            // Start session
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

    private function logout()
    {
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

    private function getProfile()
    {
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

    private function updateProfile()
    {
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
            // Get updated user
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

    private function updateSecurity()
    {
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

        // Check if email is being changed
        if (!empty($email)) {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email no válido'
                ]);
                exit;
            }

            // Check if email exists for another user
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

        // Build update query
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

    private function deleteAccount()
    {
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

        // Delete user
        $sql = "DELETE FROM cl_usuarios WHERE id = {$userId}";

        if ($db->exec($sql)) {
            // Destroy session
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
