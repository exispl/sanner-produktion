<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require __DIR__.'/config.php';

// --- Database Initialization ---
function db_init() {
    static $initialized = false;
    if ($initialized) return;

    try {
        $pdo = pdo_conn();
        $sql = file_get_contents(__DIR__.'/schema.sql');
        if ($sql) {
            $pdo->exec($sql);
        }

        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            // Insert default users if table is empty
            $users_to_insert = [
                ['SoG1917', 'Kamil Kowalczyk', '4753', 'Senior Developer'],
                ['SoG2025', 'John Locke', '1234', 'TeamLeiter'],
                ['SoG1', 'Michael Scott', '1234', 'Administrator'],
                ['SoG1899', 'James Corden', '1234', 'Administrator'],
                ['SoP1', 'Piotr Nowak', '1234', 'Administrator'],
                ['SoG2200', 'User 2200', '1234', 'Einrichter'],
                ['SoG2020', 'User 2020', '1234', 'Einrichter'],
            ];

            $user_stmt = $pdo->prepare("INSERT INTO users (uid, name, password_hash, role) VALUES (?, ?, ?, ?)");
            $settings_stmt = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)");

            foreach ($users_to_insert as $user) {
                $pass_hash = password_hash($user[2], PASSWORD_DEFAULT);
                $user_stmt->execute([$user[0], $user[1], $pass_hash, $user[3]]);
                $user_id = $pdo->lastInsertId();
                $settings_stmt->execute([$user_id]);
            }
        }
    } catch (Exception $e) {
        error_log("DB Init Error: " . $e->getMessage());
        // Do not throw exception here, as it might be a permissions issue on first run
    }
    $initialized = true;
}

db_init();

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
$response = ['ok' => false, 'error' => 'Invalid action'];
$status_code = 400;

try {
    $pdo = pdo_conn();
    switch ($action) {
        case 'register':
            // ... (existing register case) ...
            break;

        case 'login':
            session_start();
            $uid = $data['uid'] ?? null;
            $password = $data['password'] ?? null;

            if (!$uid || !$password) {
                throw new Exception('Proszę podać login i hasło.');
            }

            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE uid = ?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_uid'] = $uid;
                $response = ['ok' => true, 'message' => 'Zalogowano pomyślnie.', 'uid' => $uid];
                $status_code = 200;
            } else {
                throw new Exception('Nieprawidłowy login lub hasło.');
            }
            break;

        case 'get_app_state':
            // ... (existing get_app_state case) ...
            break;

        case 'save_user_setting':
            session_start();
            if (!isset($_SESSION['user_id'])) throw new Exception('Brak autoryzacji.');
            $user_id = $_SESSION['user_id'];

            $key = $data['key'] ?? null;
            $value = $data['value'] ?? null;

            $allowed_keys = ['theme', 'language', 'machine_order', 'panel_order'];
            if (!$key || !in_array($key, $allowed_keys)) {
                throw new Exception('Nieprawidłowy klucz ustawień.');
            }

            // For JSON fields, ensure the value is a JSON string
            if (in_array($key, ['machine_order', 'panel_order'])) {
                $value = json_encode($value);
            }

            $stmt = $pdo->prepare("UPDATE user_settings SET `$key` = ? WHERE user_id = ?");
            $stmt->execute([$value, $user_id]);

            $response = ['ok' => true, 'message' => 'Ustawienia zapisane.'];
            $status_code = 200;
            break;

        case 'save_machine_setting':
            // ... (existing save_machine_setting case) ...
            break;

        case 'get_chat_messages':
            $stmt = $pdo->query("SELECT c.id, c.message, c.parent_id, c.created_at, u.uid, u.name FROM chat_messages c JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC LIMIT 50");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['ok' => true, 'data' => array_reverse($messages)];
            $status_code = 200;
            break;

        case 'post_chat_message':
            // ... (existing post_chat_message case) ...
            break;

        case 'get_table_data':
            session_start();
            if (!isset($_SESSION['user_uid']) || $_SESSION['user_uid'] !== 'SoG1917') {
                throw new Exception('Access denied.');
            }
            $table = $data['table'] ?? null;
            $allowed_tables = ['users', 'machines', 'orders', 'user_settings', 'chat_messages', 'plans'];
            if (!$table || !in_array($table, $allowed_tables)) {
                throw new Exception('Invalid table name.');
            }
            $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT 100");
            $response = ['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            $status_code = 200;
            break;

        case 'update_table_data':
            session_start();
            if (!isset($_SESSION['user_uid']) || $_SESSION['user_uid'] !== 'SoG1917') {
                throw new Exception('Access denied.');
            }
            $table = $data['table'] ?? null;
            $id = $data['id'] ?? null;
            $field = $data['field'] ?? null;
            $value = $data['value'] ?? null;

            $allowed_tables = ['users', 'machines', 'orders', 'user_settings'];
            if (!$table || !in_array($table, $allowed_tables) || !$id || !$field) {
                throw new Exception('Invalid parameters for update.');
            }
            // A simple whitelist for editable columns to prevent updating sensitive fields
            $allowed_fields_map = [
                'users' => ['name', 'avatar_url', 'role'],
                'machines' => ['default_duration_min', 'color', 'is_active'],
                // Add other tables and fields as needed
            ];
            if (!isset($allowed_fields_map[$table]) || !in_array($field, $allowed_fields_map[$table])) {
                throw new Exception("Field '$field' cannot be edited for table '$table'.");
            }

            $id_column = ($table === 'user_settings') ? 'user_id' : 'id';

            $stmt = $pdo->prepare("UPDATE `$table` SET `$field` = ? WHERE `$id_column` = ?");
            $stmt->execute([$value, $id]);

            $response = ['ok' => true, 'message' => 'Record updated.'];
            $status_code = 200;
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['error'] = $e->getMessage();
    $status_code = 500;
}

http_response_code($status_code);
echo json_encode($response);
