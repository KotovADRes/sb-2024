<?php
require_once '_config.php';

function stop($message = '') {
    if ($message)
        echo '<br>' . $message;
    exit;
}

function Delay_ms($ms) {
    usleep($ms * 1000);
}

function getDBO($host, $name, $user, $password) {
    try {
        $dsn = "mysql:host=" .$host. ";dbname=" .$name. ";charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        return $pdo;
    } catch (Exception $e) {
        stop('DBO exception: ' . $e->getMessage());
    }
}

function getAvailableLocals() {
    try {
        if (!is_dir(LANG_PTH)) {
            throw new Exception("Directory not found: " . LANG_PTH);
        }

        $files = scandir(LANG_PTH);
        if ($files === false) {
            throw new Exception("Failed to read directory: " . LANG_PTH);
        }

        $langs = [];
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $langs[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        return $langs;
    } catch (Exception $e) {
        error_log("Error in getAvailableLocals: " . $e->getMessage());
        return [];
    }
    
}


function loadLocal($local_id) {
    try {
        $filepath = LANG_PTH . DIR_SEP . $local_id . '.json';

        if (!file_exists($filepath)) {
            throw new Exception("File not found: " . $filepath);
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new Exception("Failed to read file: " . $filepath);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        return $data;
    } catch (Exception $e) {
        error_log("Error in loadLocal: " . $e->getMessage());
        return null;
    }
}

function getPageLanguageID() {
    $lang_list = getAvailableLocals();
    $lang = BASE_LANG;
    
    if ( in_array($_GET['lang'], $lang_list) ) {
        $lang = $_GET['lang'];
    }
    
    return $lang;
}


function getMatrix($size) {
    $matrix = [];
    
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $matrix[$x][$y] = 0;
        }
    }
    
    return $matrix;
}


//ship: [size => (1...field_size), position => [(x), (y)], direction => (0...3: up, right, down, left)]
//
function checkGameMap($field_size, $ships) {
    //0 - sea, 1 - ship exclusion, 2 - ship
    $matrix = getMatrix($field_size);
    
    $direction_coefs = [
        [0, -1], 
        [1, 0], 
        [0, 1], 
        [-1, 0]
    ];
    
    foreach ($ships as $ship) {
        $x_coef = $direction_coefs[ $ship['direction'] ][0];
        $y_coef = $direction_coefs[ $ship['direction'] ][1];
        
        $x = $ship['position'][0];
        $y = $ship['position'][1];
        
        for ($s = 0; $s < $ship['size']; $s++) {
            for ($x_n = $x - 1; $x_n <= $x + 1; $x_n++) {
                for ($y_n = $y - 1; $y_n <= $y + 1; $y_n++) {
                    if (
                        ($x_n < 0 || $x_n >= $field_size)
                        || ($y_n < 0 || $y_n >= $field_size)
                        ) {
                        if ($x_n == $x && $y_n == $y) {
                            return false;
                        }
                        continue;
                    }
                    
                    if ($x_n == $x && $y_n == $y) {
                        if ( $matrix[$x_n][$y_n] > 0) {
                            return false;
                        }
                        $matrix[$x_n][$y_n] = 2;
                    } else if (
                        $x_n == $x + $x_coef 
                        && $y_n == $y + $y_coef 
                        && $s < $ship['size'] - 1
                        ) {
                        $matrix[$x_n][$y_n] = 0;
                    } else if (!(
                        $x_n == $x - $x_coef 
                        && $y_n == $y - $y_coef 
                        && $s > 0
                        )) {
                        $matrix[$x_n][$y_n] = 1;
                    }
                    
                }
            }
            
            $x += $x_coef;
            $y += $y_coef;
        }
    }
    
    return true;
    
}

//user id or -1, if not exists
function getUserID($pdo, $session_id) {
    $stmt = $pdo->prepare("SELECT id FROM " . TABLE_USER . " WHERE sess_id = :sess_id");
    $stmt->execute([':sess_id' => $session_id]);
    $user_id = $stmt->fetchColumn();
    
    if ($user_id === false)
        $user_id = -1;
    
    return $user_id;
}


function createUserRecord($pdo, $session_id, $ip) {    
    $user_id = getUserID($pdo, $session_id);
    
    if ($user_id > -1)
        return $user_id;
    
    $stmt = $pdo->prepare("INSERT INTO " . TABLE_USER . " (sess_id, ip, last_update) VALUES (:sess_id, :ip, :last_update)");
    
    $stmt->execute([
        ':sess_id'    => $session_id,
        ':ip'         => $ip,
        ':last_update' => date('Y-m-d H:i:s')
    ]);
    
    $user_id = $pdo->lastInsertId();
    return $user_id;
}


function updateUserOnline($pdo, $session_id, $ip) {
    $user_id = createUserRecord($pdo, $session_id, $ip);
    
    $stmt = $pdo->prepare("UPDATE " . TABLE_USER . " SET last_update = :date WHERE id = :user_id");
    $stmt->execute([
        ':date' => date('Y-m-d H:i:s'), 
        ':user_id' => $user_id
    ]);
    
    return $user_id;
}


/* function clearUsersByIP($pdo, $ip) {
    $stmt = $pdo->prepare("DELET FROM `" . TABLE_USER . "` WHERE ip = :ip AND last_update < NOW() - INTERVAL " . SQL_ACTUALITY_TIME);
    $stmt->execute([':ip' => $session_id]);
}
 */

function getCurrentGameBySessID($pdo, $session_id) {
    //0 - created, 1 - started, 2 - ended
    $stmt = $pdo->prepare("SELECT g.id as game_id FROM `" . TABLE_USER . "` u LEFT JOIN `" . TABLE_GAME_MAP . "` gm ON u.id = gm.user_id LEFT JOIN `" . TABLE_GAMES . "` g ON gm.game_id = g.id WHERE u.sess_id = :sess_id AND g.status < 2 AND g.last_action >= NOW() - INTERVAL " . SQL_ACTUALITY_TIME . " GROUP BY g.id");
    $stmt->execute([':sess_id' => $session_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['game_id'])) {
        return $result['game_id'];
    }
    
    return -1;
}


function createGame($pdo, $user_id, $name, $session_id, $field_size, $with_computer, $ships) {
    $stmt = $pdo->prepare("
        INSERT INTO 
            `" . TABLE_GAMES . "` (
                name, 
                with_computer, 
                field_size, 
                status, 
                last_action
            ) 
        VALUES (
            :name, 
            :with_computer, 
            :field_size, 
            :status, 
            :last_action
        )
    ");
    
    $stmt->execute([
        ':name' => $name,
        ':with_computer' => $with_computer ? 1 : 0,
        ':field_size' => $field_size,
        ':status' => 0,
        ':last_action' => date('Y-m-d H:i:s')
    ]);
    
    $game_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("
        INSERT INTO 
            `" . TABLE_GAME_SHIPS . "` (
                game_id, 
                size, 
                x, 
                y, 
                direction, 
                user_id
            ) 
        VALUES (
            :game_id, 
            :size, 
            :x, 
            :y, 
            :direction, 
            :user_id
        )
    ");
    
    foreach($ships as $ship) {
        $stmt->execute([
            ':game_id' => $game_id,
            ':size' => $ship['size'],
            ':x' => $ship['position'][0],
            ':y' => $ship['position'][1],
            ':direction' => $ship['direction'],
            ':user_id' => $user_id
        ]);
    } 
    
    $matrix = getMatrix($field_size);
    
    $stmt = $pdo->prepare("
        INSERT INTO 
            `" . TABLE_GAME_MAP . "` (
                game_id, 
                user_id, 
                map
            ) 
        VALUES (
            :game_id, 
            :user_id, 
            :map
        )
    ");
    
    $stmt->execute([
        ':game_id' => $game_id,
        ':user_id' => $user_id,
        ':map' => json_encode($matrix, JSON_UNESCAPED_UNICODE)
    ]);
    
}

function deleteGame($pdo, $game_id) {
    $tables = [TABLE_GAME_MAP, TABLE_GAME_SHIPS, TABLE_GAMES];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DELETE FROM `" . $table . "` WHERE " . ($table === TABLE_GAMES ? "id" : "game_id") . " = :game_id");
        $stmt->execute([':game_id' => $game_id]);
    }
}

function clearOldUserGames($pdo, $user_id) {
    $stmt = $pdo->prepare("
    SELECT DISTINCT g.id 
    FROM 
        `game_map` gm 
    JOIN 
        `games` g 
    ON 
        g.id = gm.game_id 
    WHERE 
        gm.user_id = :user_id
    AND
        g.last_action < NOW() - INTERVAL " . SQL_ACTUALITY_TIME .";
    ");
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach($result as $game_id) {
        deleteGame($pdo, $game_id);
    }
}

?>