<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once '_config.php';
require_once PTH . DIR_SEP . 'functions.php';

function jsonResponse($status, $message, $data = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    stop();
}

Delay_ms(50);

try {
    $pdo = getDBO(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD);
} catch (Exception $e) {
    jsonResponse('error', 'Failed to connect to DB');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$session_id = session_id();
$user_id = updateUserOnline($pdo, $session_id, $ip);


$method = $_SERVER['REQUEST_METHOD'];

$input_data = $_GET;

if ($method === 'POST') {
    $post_data = json_decode(file_get_contents('php://input'), true);
    $input_data = array_merge($input_data, $post_data);
}

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse('error', 'Invalid JSON format');
}

$SETS = [
    8 => [3 => 1, 2 => 2, 1 => 3],
    9 => [3 => 2, 2 => 3, 1 => 4],
    10 => [4 => 1, 3 => 2, 2 => 3, 1 => 4],
    11 => [4 => 2, 3 => 3, 2 => 3, 1 => 4],
    12 => [5 => 1, 4 => 2, 3 => 3, 2 => 3, 1 => 4]
];


switch ($input_data['action']) {
    case 'im_online':
        jsonResponse('success', 'Noted.');
        break;
    case 'get_session_id':
        jsonResponse('success', '', ['session_id' => $session_id]);
        break;
    case 'get_current_game':
        $game_id = getCurrentGameBySessID($pdo, $session_id);

        if ($game_id != -1) {
            jsonResponse('success', '', ['in_game' => true, 'game_id' => $game_id]);
        } else {
            jsonResponse('success', '', ['in_game' => false]);
        }
        
    case 'get_ship_sets':
        jsonResponse('success', '', ['sets' => $SETS]);
        
        break;
    case 'populate_field':
        if ( !isset($input_data['size']) || !isset($SETS[$input_data['size']]) )
            jsonResponse('error', 'Incorrect field size');
        
        $f_size = $input_data['size'];
        if ($ships = populateGameField($f_size, $SETS[$f_size])) {
            jsonResponse('success', '', ['ships' => $ships]);
        } else {
            jsonResponse('error', 'An error occurred while filling in the field');
        }
            
        break;
    case 'get_games_list':
        $gameslist = selectFullGamesInfo($pdo, $user_id, true);
        jsonResponse('success', '', ['games' => $gameslist]);

        break;
    case 'get_game_info_by_id':
        if ( !isset($input_data['game_id']) )
            jsonResponse('error', 'Game ID not provided');
        
        $game = selectFullGameInfoById($pdo, $input_data['game_id'], $user_id, true);
        if ($game === false) {
            jsonResponse('error', 'Game not found');
        }
        
        jsonResponse('success', '', ['game' => $game]);

        break;
    case 'create_game':
        if ( !isset($input_data['size']) || !isset($SETS[$input_data['size']]) )
            jsonResponse('error', 'Incorrect field size');
        
        $f_size = $input_data['size'];
        
        if ( !isset($input_data['ships']) )
            jsonResponse('error', 'No ships are provided');
    
        $game_id = getCurrentGameBySessID($pdo, $session_id);
        if ($game_id !== -1)
            jsonResponse('error', 'The user is already participating in the game');
        
        $count_by_size = [];
        $ships = $input_data['ships'];
        try {
            foreach($ships as $ship) {
                if (!isset($ship['size'])
                    || !isset($ship['x'])
                    || !isset($ship['y'])
                    || !isset($ship['direction'])
                    ) {
                    jsonResponse('error', 'Some of ships have incorrect format');
                }
                
                $count_by_size[$ship['size']]++;
            }
        } catch (Exception $e) {
            jsonResponse('error', 'Incorrect ships list format');
        }
        
        foreach ($count_by_size as $size => $count) {
            if ( !isset($SETS[$f_size][$size]) ) {
                jsonResponse('error', 'Some of ships have incorrect size');
            } else if ($SETS[$f_size][$size] !== $count) {
                jsonResponse('error', 'Incorrect number of ships');
            }
        }
        
        if ( !checkGameMap($f_size, $ships) ) {
            jsonResponse('error', 'Incorrect placement of ships');
        }
        
        clearOldUserGames($pdo, $user_id);
        
        $with_computer = false;
        if ( isset($input_data['computer']) && $input_data['computer'] ) {
            $with_computer = true;
        }
        
        
        if ( isset($input_data['name']) && mb_strlen($input_data['name']) > 0 && mb_strlen($input_data['name']) <= MAX_GAME_NAME_LENGTH ) {
            $name = $input_data['name'];
        } else {
            $name = date('Y-m-d H:i:s');
        }
        
        createGame($pdo, $user_id, $name, $session_id, $f_size, $with_computer, $ships, $SETS[$f_size]);
        jsonResponse('success', 'The command to create a game has been given');
        
        break;
    case 'join_game':
        if ( !isset($input_data['game_id']) )
            jsonResponse('error', 'Game ID not provided');
        if ( !isset($input_data['ships']) )
            jsonResponse('error', 'Ships not provided');
        
        $game = selectFullGameInfoById($pdo, (int) $input_data['game_id'], $user_id, true);
        
        if ($game === false)
            jsonResponse('error', 'Game not found');
        
        $curr_game_id = getCurrentGameBySessID($pdo, $session_id);
        
        if ( !$game['can_user_join'] || $curr_game_id !== -1)
            jsonResponse('error', 'User cannot join the game');
        
        $f_size = (int) $game['field_size'];
        
        $count_by_size = [];
        $ships = $input_data['ships'];
        try {
            foreach($ships as $ship) {
                if (!isset($ship['size'])
                    || !isset($ship['x'])
                    || !isset($ship['y'])
                    || !isset($ship['direction'])
                    ) {
                    jsonResponse('error', 'Some of ships have incorrect format');
                }
                
                $count_by_size[$ship['size']]++;
            }
        } catch (Exception $e) {
            jsonResponse('error', 'Incorrect ships list format');
        }
        
        foreach ($count_by_size as $size => $count) {
            if ( !isset($SETS[$f_size][$size]) ) {
                jsonResponse('error', 'Some of ships have incorrect size');
            } else if ($SETS[$f_size][$size] !== $count) {
                jsonResponse('error', 'Incorrect number of ships');
            }
        }
        
        try {
            $matrix = getMatrix($f_size);
            addGamePlayer($pdo, $game['id'], $user_id, $ships, $matrix);
        } catch (Exception $e) {
            jsonResponse('error', 'Failed to connect to the game');
        }
        
        updateGameStatus($pdo, $game['id']);
        
        jsonResponse('success', 'The user has joined the game');
        
        break;
    case 'is_need_game_update':
        $game_id = getCurrentGameBySessID($pdo, $session_id);

        if ($game_id === -1) {
            jsonResponse('error', 'Not in game');
        }
        
        $stmt = $pdo->prepare("SELECT update_required FROM `" . TABLE_GAME_MAP . "` WHERE game_id = :game_id AND user_id = :user_id");
        $stmt->execute([
            ':game_id' => $game_id,
            ':user_id' => $user_id
        ]);
        $need_update = $stmt->fetch(PDO::FETCH_COLUMN);
        
        if ($need_update === false) {
            jsonResponse('error', 'User not found in game');
        }
        
        jsonResponse('success', '', ['need_update' => (bool) $need_update]);
        
        break;
    case 'im_updated':
        $game_id = getCurrentGameBySessID($pdo, $session_id);

        if ($game_id === -1) {
            jsonResponse('error', 'Not in game');
        }
        
        $stmt = $pdo->prepare("UPDATE `" . TABLE_GAME_MAP . "` SET update_required = 0 WHERE game_id = :game_id AND user_id = :user_id");
        $stmt->execute([
            ':game_id' => $game_id,
            ':user_id' => $user_id
        ]);
        
        jsonResponse('success', 'Noted.');
        
        break;
    case 'make_a_move':
        $game_id = getCurrentGameBySessID($pdo, $session_id);

        if ($game_id === -1) {
            jsonResponse('error', 'Not in game');
        }
        
        $game = selectGameInfoById($pdo, $game_id);
        
        if ( getCurrentTurnUserIdByGameinfo($pdo, $game) !== $user_id )
            jsonResponse('error', 'The user is currently not moving');
        
        if ( !isset($input_data['x']) || !isset($input_data['y']) )
            jsonResponse('error', 'Incomplete coordinates provided');
        
        $enemy_id = getCurrentTurnUserIdByGameinfo($pdo, $game, true);
        if ($enemy_id === -2) 
            jsonResponse('error', 'Unable to find enemy');
        
        $x = (int) $input_data['x'];
        $y = (int) $input_data['y'];
        $f_size = (int) $game['field_size'];
        
        if ($x < 0 || $x >= $f_size)
            jsonResponse('error', 'Incorrect x coordinate');
        if ($y < 0 || $y >= $f_size)
            jsonResponse('error', 'Incorrect y coordinate');
        
        $enemy_num = (1 - (int) $game['player_num']);
        $success = makeAMove($pdo, $game_id, $enemy_id, $enemy_num, $x, $y);
        
        if (!$success)
            jsonResponse('error', 'Unable to make a move');
        
        jsonResponse('success', 'The move has been made');
        
        break;
    default:
        jsonResponse('error', 'Not allowed `action` field.');
        break;
}

jsonResponse('error', 'An internal error occurred.');


?>