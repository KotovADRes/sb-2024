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
    case 'get_games_list':
        $stmt = $pdo->prepare("
            SELECT 
                g.id AS game_id, 
                gm.user_id, 
                u.last_update, 
                g.name, 
                g.with_computer, 
                g.field_size, 
                g.is_started, 
                gm.map 
            FROM 
                `" . TABLE_GAMES . "` g 
            LEFT JOIN 
                `" . TABLE_GAME_MAP . "` gm 
            ON 
                g.id = gm.game_id
            LEFT JOIN 
                `" . TABLE_USER . "` u 
            ON 
                gm.user_id = u.id
            WHERE
                g.status < 2
            AND
                g.last_action >= NOW() - INTERVAL " . SQL_ACTUALITY_TIME . ";
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $gameslist = [];
        /* foreach($results as $row) {
            $gameslist[] = [
                
            ];
        } */

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
        $ships = [];
        try {
            foreach($input_data['ships'] as $ship) {
                if (!isset($ship['size'])
                    || !isset($ship['x'])
                    || !isset($ship['y'])
                    || !isset($ship['direction'])
                    ) {
                    jsonResponse('error', 'Some of ships have incorrect format');
                }
                
                $count_by_size[$ship['size']]++;
                $ships[] = [
                    'size' => $ship['size'],
                    'position' => [$ship['x'], $ship['y']],
                    'direction' => $ship['direction'],
                ];
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
        
        $with_computer = false;
        if ( isset($input_data['computer']) && $input_data['computer'] ) {
            $with_computer = true;
        }
        
        
        if ( isset($input_data['name']) && mb_strlen($input_data['name']) > 0 && mb_strlen($input_data['name']) <= MAX_GAME_NAME_LENGTH ) {
            $name = $input_data['name'];
        } else {
            $name = date('Y-m-d H:i:s');
        }
        
        createGame($pdo, $user_id, $name, $session_id, $f_size, $with_computer, $ships);
        jsonResponse('success', 'The command to create a game has been given');
        
        break;
    default:
        jsonResponse('error', 'Not allowed `action` field.');
        break;
}


?>