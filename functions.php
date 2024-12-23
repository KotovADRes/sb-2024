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


function getDirectionCoefs() {
    return [
        [0, -1], 
        [1, 0], 
        [0, 1], 
        [-1, 0]
    ];
}


function placeShipOnMatrix(&$main_matrix, $field_size, $ship) {
    $direction_coefs = getDirectionCoefs();
    
    //0 - sea, 1 - ship exclusion, 2 - ship
    $matrix = $main_matrix;
    
    $x_coef = $direction_coefs[ $ship['direction'] ][0];
    $y_coef = $direction_coefs[ $ship['direction'] ][1];
    
    $x = $ship['x'];
    $y = $ship['y'];
    
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
                    if ($matrix[$x_n][$y_n] > 0) {
                        return false;
                    }
                    $matrix[$x_n][$y_n] = 2;
                } else if (
                    ($x_n != $x - $x_coef || $y_n != $y - $y_coef || $s == 0) &&
                    ($x_n != $x + $x_coef || $y_n != $y + $y_coef || $s == $ship['size'] - 1)
                ) {
                    $matrix[$x_n][$y_n] = 1;
                }
            }
        }
        
        $x += $x_coef;
        $y += $y_coef;
    }
    
    $main_matrix = $matrix;
    return true;
}



//ship: [size => (1...field_size), x => (x), y => (y), direction => (0...3: up, right, down, left)]
//
function checkGameMap($field_size, $ships) {
    $matrix = getMatrix($field_size);
    
    foreach ($ships as $ship) {
        if ( !placeShipOnMatrix($matrix, $field_size, $ship) ) {
            return false;
        }
    }
    
    
    return true;
    
}


function populateGameField($field_size, $ships_set) {
    $tries = 0;
    $ships = [];
    
    $place_ship_tries = 20;
    $get_coordinates_tries = pow($field_size, 2) + 10;
    
    krsort($ships_set);
    
    do {
        $hitches = false;
        $matrix = getMatrix($field_size);
        
        $iter_ships = [];
        foreach($ships_set as $size => $count) {
            for ($c = 0; $c < $count; $c++) {
                $this_placed = false;
                
                for ($i = 0; $i < $place_ship_tries && !$this_placed; $i++) {
                    $ship = [];
                    $ship['size'] = $size;
                    
                    for ($j = 0; $j < $get_coordinates_tries; $j++) {
                        $x = rand(0, $field_size - 1);
                        $y = rand(0, $field_size - 1);
                        
                        if ($matrix[$x][$y] === 0) {
                            if (($x > 0 && $matrix[$x - 1][$y] === 0) || 
                                ($y > 0 && $matrix[$x][$y - 1] === 0) || 
                                ($x < $field_size - 1 && $matrix[$x + 1][$y] === 0) || 
                                ($y < $field_size - 1 && $matrix[$x][$y + 1] === 0) || 
                                ($size === 1)) {
                                    
                                $ship['x'] = $x;
                                $ship['y'] = $y;
                                break;
                            }
                        }
                    }
                    
                    if ( !isset($ship['x']) ) {
                        continue;
                    }
                    
                    for ($d = 0; $d <= 3; $d++) {
                        $ship['direction'] = $d;
                        if (placeShipOnMatrix($matrix, $field_size, $ship)) {
                            $iter_ships[] = $ship;
                            $this_placed = true;
                            break;
                        }
                    }
                    
                }
                
                if (!$this_placed) {
                    $hitches = true;
                    break 2;
                }
            }
            
        }
        
        if (!$hitches) {
            $ships = $iter_ships;
            break;
        }
        
        $tries++;
    } while ($hitches && $tries < 20);
    
    if ($ships) {
        return $ships;
    }
    
    return false;
}

//user id or -1, if not exists
function getUserID($pdo, $session_id) {
    $stmt = $pdo->prepare("SELECT id FROM " . TABLE_USER . " WHERE sess_id = :sess_id");
    $stmt->execute([':sess_id' => $session_id]);
    $user_id = $stmt->fetchColumn();
    
    if ($user_id === false)
        $user_id = -1;
    
    return (int) $user_id;
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
        return (int) $result['game_id'];
    }
    
    return -1;
}

function getCurrentGameStatusBySessID($pdo, $session_id, $game_id = null) {
    if ($game_id === null) {
        $game_id = getCurrentGameBySessID($pdo, $session_id);
    }
    
    if ($game_id === -1) {
        return -1;
    }
    
    $stmt = $pdo->prepare("SELECT status FROM `" . TABLE_GAMES . "` WHERE id = :game_id");
    $stmt->execute([':game_id' => $game_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['status'])) {
        return (int) $result['status'];
    }
    
    return -1;
}


function addGamePlayer($pdo, $game_id, $user_id, $ships, $matrix) {
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
            ':x' => $ship['x'],
            ':y' => $ship['y'],
            ':direction' => $ship['direction'],
            ':user_id' => $user_id
        ]);
    } 
    
    $stmt = $pdo->prepare("
        INSERT INTO 
            `" . TABLE_GAME_MAP . "` (
                game_id, 
                user_id, 
                map,
                update_required
            ) 
        VALUES (
            :game_id, 
            :user_id, 
            :map,
            :update_required
        )
    ");
    
    $stmt->execute([
        ':game_id' => $game_id,
        ':user_id' => $user_id,
        ':map' => json_encode($matrix, JSON_UNESCAPED_UNICODE),
        ':update_required' => 0
    ]);
}


function createGame($pdo, $user_id, $name, $session_id, $field_size, $with_computer, $ships, $ships_set) {
    $stmt = $pdo->prepare("
        INSERT INTO 
            `" . TABLE_GAMES . "` (
                name, 
                with_computer, 
                field_size, 
                status, 
                last_action,
                player_num
            ) 
        VALUES (
            :name, 
            :with_computer, 
            :field_size, 
            :status, 
            :last_action,
            :player_num
        )
    ");
     
    $stmt->execute([
        ':name' => $name,
        ':with_computer' => $with_computer ? 1 : 0,
        ':field_size' => $field_size,
        ':status' => 0,
        ':last_action' => date('Y-m-d H:i:s'),
        ':player_num' => $with_computer ? 1 : 0
    ]);
    
    $game_id = $pdo->lastInsertId();
    $matrix = getMatrix($field_size);
    
    addGamePlayer($pdo, $game_id, $user_id, $ships, $matrix);
    
    if ($with_computer) {
        $opponent_ships = populateGameField($field_size, $ships_set);
        addGamePlayer($pdo, $game_id, -1, $opponent_ships, $matrix);
    }
    
    
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

//-1 - fog, 0 - sea, 1 - near ship area, 2 - ship, 3 - damaged ship
function getFinalGameMap($shots_map, $ships, $fog = false) {
    $field_size = count($shots_map);
    $matrix = getMatrix($field_size);
    $fog_matrix = $shots_map; //0 - fog, 1 - visible
    
    $direction_coefs = getDirectionCoefs();
    
    foreach ($ships as $ship) {
        if ( !placeShipOnMatrix($matrix, $field_size, $ship) || !$fog )
            continue;
        
        $direction = max(0, min($ship['direction'], 3));
        $x_coef = $direction_coefs[ $direction ][0];
        $y_coef = $direction_coefs[ $direction ][1];
        $x = $ship['x'];
        $y = $ship['y'];
        
        $fully_destroyed = true;
        
        for ($s = 0; $s < $ship['size']; $s++) {
            if ((int) $shots_map[$x][$y] !== 1) {
                $fully_destroyed = false;
            }
            
            $x += $x_coef;
            $y += $y_coef;
            
        }
        
        if ($fully_destroyed) {
            $point_1 = ['x' => $ship['x'], 'y' => $ship['y']];
            $point_2 = [
                'x' => $ship['x'] + $x_coef * ($ship['size'] - 1), 
                'y' => $ship['y'] + $y_coef * ($ship['size'] - 1)
            ];
            
            $points = [];
            if ($direction == 0 || $direction == 3) {
                $points = [$point_2, $point_1];
            } else {
                $points = [$point_1, $point_2];
            }
            
            $start_x = $points[0]['x'] - 1;
            $start_y = $points[0]['y'] - 1;
            $end_x = $points[1]['x'] + 1;
            $end_y = $points[1]['y'] + 1;
            
            for ($x = $start_x; $x <= $end_x; $x++) {
                for ($y = $start_y; $y <= $end_y; $y++) {
                    if ( isset($fog_matrix[$x]) && isset($fog_matrix[$x][$y]) )
                        $fog_matrix[$x][$y] = 1;
                }
            }
        }
        
    }
    
    for ($y = 0; $y < $field_size; $y++) {
        for ($x = 0; $x < $field_size; $x++) {
            if ( ((int) $shots_map[$x][$y]) === 1 && $matrix[$x][$y] === 2 ) {
                $matrix[$x][$y] = 3;
            }
            if ( $fog && !$fog_matrix[$x][$y] ) {
                $matrix[$x][$y] = -1;
            }
        }
    }

    return $matrix;

}


function markDestroyedShips($pdo, $shots_map, $ships) {
    $direction_coefs = getDirectionCoefs();
    
    foreach ($ships as &$ship) {
        $x = $ship['x'];
        $y = $ship['y'];
        
        $direction = max(0, min($ship['direction'], 3));
        
        $x_coef = $direction_coefs[ $direction ][0];
        $y_coef = $direction_coefs[ $direction ][1];
        
        $destroyed = true;
        
        for ($s = 0; $s < (int) $ship['size']; $s++) {
            
            if (((int) $shots_map[$x][$y]) === 0) {
                $destroyed = false;
                break;
            }
            
            $x += $x_coef;
            $y += $y_coef;
        }
        
        $ship['is_destroyed'] = $destroyed;
    }
    
    return $ships;
}


function selectGameInfoById($pdo, $game_id) {
    $stmt = $pdo->prepare("SELECT * FROM `" . TABLE_GAMES . "` WHERE id = :game_id AND status < 2 AND last_action >= NOW() - INTERVAL " . SQL_ACTUALITY_TIME .";");
    $stmt->execute([':game_id' => $game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        return false;
    }
    
    return $game;
    
}

function getEnumeratedPlayers($pdo, $game_id) {
    $stmt = $pdo->prepare("SELECT DISTINCT  user_id FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id ORDER BY user_id");
    $stmt->execute([':game_id' => $game_id]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($users === false) {
        return [];
    }
    
    return $users;
}

function enumeratePlayerInGame($player_id, &$game, &$player_ids, $user_id) {
    $player_num = array_search($player_id, $player_ids);
    if ( $player_num === false ) {
        $player_ids[] = $player_id;
        $player_num = count($player_ids) - 1;
        
        if (((int) $player_id) === $user_id) {
            $game['is_users_game'] = true;
            $game['users_player_num'] = $player_num;
        }
        
        $game['players'][$player_num] = [
            'user_id' => (int) $player_id,
            'ships' => [],
            'shots_map' => [],
            'final_map' => [],
            'should_update' => false
        ];
    }
    
    return $player_num;
}

function selectFullGameInfoById($pdo, $game_id, $user_id, $fog = false) {
    $game = selectGameInfoById($pdo, $game_id);
    if ($game === false) {
        return false;
    }
    
    $game['is_users_game'] = false;
    
    $stmt = $pdo->prepare("SELECT * FROM `" . TABLE_GAME_SHIPS . "` WHERE game_id = :game_id ORDER BY user_id");
    $stmt->execute([':game_id' => $game_id]);
    $ships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $player_ids = [];
    $game['players'] = [];
    
    foreach($ships as $ship) {
        $player_num = enumeratePlayerInGame($ship['user_id'], $game, $player_ids, $user_id);
        
        $game['players'][$player_num]['ships'][] = [
            'size' => $ship['size'],
            'x' => $ship['x'],
            'y' => $ship['y'],
            'direction' => $ship['direction'],
        ];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id");
    $stmt->execute([':game_id' => $game_id]);
    $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($maps as $map) {
        $player_num = enumeratePlayerInGame($map['user_id'], $game, $player_ids, $user_id);
        
        $curr_fog = $fog ? (((int) $map['user_id']) !== $user_id) : false;
        $shots_map = json_decode($map['map'], JSON_UNESCAPED_UNICODE);
        
        $game['players'][$player_num]['should_update'] = (bool) $map['update_required'];
        $game['players'][$player_num]['shots_map'] = $shots_map;
        $game['players'][$player_num]['final_map'] = getFinalGameMap($shots_map, $game['players'][$player_num]['ships'], $curr_fog);
        $game['players'][$player_num]['ships'] = markDestroyedShips($pdo, $shots_map, $game['players'][$player_num]['ships']);
    }
    
    if ($fog) {
        foreach($game['players'] as &$player) {
            if ($player['user_id'] === $user_id)
                continue;
            
            unset($player['ships']);
        }
    }
    
    $game['can_user_join'] = ( count($game['players']) < 2 ) && !$game['is_users_game'];
    
    return $game;
    
}

function selectFullGamesInfo($pdo, $user_id, $fog = false) {
    $stmt = $pdo->prepare("SELECT id FROM `" . TABLE_GAMES . "` WHERE last_action >= NOW() - INTERVAL " . SQL_ACTUALITY_TIME .";");
    $stmt->execute();
    $game_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $games = [];
    
    foreach($game_ids as $game_id) {
        $game = selectFullGameInfoById($pdo, $game_id, $user_id, $fog);
        if ($game === false)
            continue;
        
        $games[] = $game;
    }
    
    return $games;
}

function getGamePlayersCount($pdo, $game_id) {
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id");
    $stmt->execute([':game_id' => $game_id]);
    $count = $stmt->fetchColumn();
    
    if ($count === false) {
        $count = -1;
    }
    
    return (int) $count;
    
}


function getGameShotsMapByUserId($pdo, $game_id, $user_id) {
    $stmt = $pdo->prepare("SELECT map FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute([
        ':game_id' => $game_id,
        ':user_id' => $user_id
    ]);
    $map = $stmt->fetchColumn();
    
    if ($map === false) {
        return false;
    }
    
    return json_decode($map, JSON_UNESCAPED_UNICODE);
}

function getAllGameShotsMap($pdo, $game_id) {
    $stmt = $pdo->prepare("SELECT map FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id");
    $stmt->execute([':game_id' => $game_id]);
    $maps = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($maps === false) {
        return false;
    }
    
    $output_maps = [];
    foreach ($maps as $map) {
        $output_maps[] = json_decode($map, JSON_UNESCAPED_UNICODE);
    }
    
    return $output_maps;
    
}

function getGameShipsByUserId($pdo, $game_id, $user_id) {
    $stmt = $pdo->prepare("SELECT size, x, y, direction FROM `" . TABLE_GAME_SHIPS . "` WHERE game_id = :game_id AND user_id = :user_id");
    $stmt->execute([
        ':game_id' => $game_id,
        ':user_id' => $user_id
    ]);
    $ship_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($ship_rows === false) {
        return false;
    }
    
    $ships = [];
    
    foreach ($ship_rows as $ship_row) {
        $ship = [
            'size' => (int) $ship_row['size'],
            'x' => (int) $ship_row['x'],
            'y' => (int) $ship_row['y'],
            'direction' => (int) $ship_row['direction']
        ];
        
        $ships[] = $ship;
    }
    
    return $ships;
}


function getAllGameUserIds($pdo, $game_id) {
    $stmt = $pdo->prepare("SELECT user_id FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id");
    $stmt->execute([':game_id' => $game_id]);
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($user_ids === false) {
        $user_ids = [];
    }
    
    foreach ($user_ids as &$user_id) {
        $user_id = (int) $user_id;
    }
    
    return $user_ids;
    
}


function getMapShipsCombinationByUserId($pdo, $game_id, $user_id) {
    $map = getGameShotsMapByUserId($pdo, $game_id, $user_id);
    if ($map === false)
        return false;
    
    $ships = getGameShipsByUserId($pdo, $game_id, $user_id);
    if ($ships === false)
        return false;
    
    return ['ships' => $ships, 'map' => $map];
}


function checkIfAllShipsDestroyed($pdo, $game_id, $user_id) {
    $ships_map = getMapShipsCombinationByUserId($pdo, $game_id, $user_id);
    
    if ($ships_map === false)
        return false;
        
    $ships = $ships_map['ships'];
    $map = $ships_map['map'];
    
    $marked_ships = markDestroyedShips($pdo, $map, $ships);
    $all_ships_destroyed = true;
    
    foreach($marked_ships as $marked_ship) {
        if ( !$marked_ship['is_destroyed'] ) {
            $all_ships_destroyed = false;
        }
    }
    
    return $all_ships_destroyed;
}


function updateGameStatus($pdo, $game_id) {
    $game = selectGameInfoById($pdo, $game_id);
    if ($game === false) {
        return false;
    }
        
    $status = 2;
    
    if ($game['winner_num'] === '-1') {
        $count = (int) getGamePlayersCount($pdo, $game_id);
        if ($count < 0) {
            return false;
        }
        
        $maps = getAllGameShotsMap($pdo, $game_id);
        if ($maps === false) {
            return false;
        }
        
        $field_size = count($maps);
        
        $nobody_played = true;
        
        for ($x = 0; $x < $field_size; $x++) {
            for ($y = 0; $y < $field_size; $y++) {
                foreach ($maps as $map) {
                    if (((int) $map[$x][$y]) === 1) {
                        $nobody_played = false;
                    }
                }
            }
        }
        
        $users_id = getAllGameUserIds($pdo, $game_id);
        $all_ships_destroyed = true;
        
        foreach($users_id as $user_id) {
            if ( !checkIfAllShipsDestroyed($pdo, $game_id, $user_id) )
                $all_ships_destroyed = false;
        }
        
        if ($count === 1 && $nobody_played) {
            $status = 0;
        }
        
        if ($count === 2 && !$all_ships_destroyed) {
            $status = 1;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE `" . TABLE_GAMES . "` SET status = :status WHERE id = :game_id");
    return $stmt->execute([
        ':status' => $status,
        ':game_id' => $game_id,
    ]);
}


function getCurrentTurnUserIdByGameinfo($pdo, $game_info, $show_enemy = false) {
    $game_id = (int) $game_info['id'];
    $users = getEnumeratedPlayers($pdo, $game_id);

    $player_num = (int) $game_info['player_num'];
    
    if ($show_enemy) {
        $player_num = $player_num ? 0 : 1;
    }
    
    if ( !isset($users[$player_num]) )
        return -2;
    
    return (int) $users[$player_num];
}

function getCurrentTurnUserId($pdo, $game_id, $show_enemy = false) {
    $game = selectGameInfoById($pdo, $game_id);
    
    return getCurrentTurnUserIdByGameinfo($pdo, $game, $show_enemy);
}

function getWinnerIdByGameinfo($pdo, $game_info) {
    $game_id = (int) $game_info['id'];
    $users = getEnumeratedPlayers($pdo, $game_id);

    $winner_num = (int) $game_info['winner_num'];
    
    if ( $winner_num == -1 || !isset($users[$winner_num]) )
        return -1;
    
    return (int) $users[$winner_num];
}

function isExistsShipOnThisCell($pdo, $field_size, $ships, $x, $y) {
    $matrix = getMatrix($field_size);
    
    foreach($ships as $ship) {
        placeShipOnMatrix($matrix, $field_size, $ship);
    }
    
    if (!isset($matrix[$x][$y]) || $matrix[$x][$y] != 2)
        return false;
    
    return true;
}


function makeAMove($pdo, $game_id, $enemy_id, $next_player_num, $x, $y) {
    $ships_map = getMapShipsCombinationByUserId($pdo, $game_id, $enemy_id);
    
    if ($ships_map === false)
        return false;
    
    $ships = $ships_map['ships'];
    $map = $ships_map['map'];
    
    if (((int) $map[$x][$y]) === 1) {
        return false;
    }
    
    $map[$x][$y] = 1;
    
    $stmt = $pdo->prepare("UPDATE `" . TABLE_GAME_MAP . "` SET map = :map, update_required = 1 WHERE game_id = :game_id AND user_id = :enemy_id");
    $stmt->execute([
        ':map' => json_encode($map, JSON_UNESCAPED_UNICODE),
        ':game_id' => $game_id,
        ':enemy_id' => $enemy_id
    ]);
    
    $hit_ship = isExistsShipOnThisCell($pdo, count($map), $ships, $x, $y);
    
    if ($hit_ship) {
        $next_player_num = (1 - $next_player_num);
        $player_won = checkIfAllShipsDestroyed($pdo, $game_id, $enemy_id);
    }
    
    $sql_winner = '';
    if ($player_won) {
        $sql_winner = ', winner_num = ' . $next_player_num;
    }
    
    
    $stmt = $pdo->prepare("UPDATE `" . TABLE_GAME_MAP . "` SET update_required = 1 WHERE game_id = :game_id");
    $stmt->execute([
        ':game_id' => $game_id
    ]);
    
    $stmt = $pdo->prepare("UPDATE `" . TABLE_GAMES . "` SET player_num = :player_num, last_action = NOW()" . $sql_winner . " WHERE id = :game_id");
    $stmt->execute([
        ':game_id' => $game_id,
        ':player_num' => $next_player_num
    ]);
    
    return true;
    
}


function isAllPlayersUpdated($pdo, $game_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id AND update_required = 1");
    $stmt->execute([':game_id' => $game_id]);
    $count = $stmt->fetchColumn();
    
    return $count === '0';
}

?>