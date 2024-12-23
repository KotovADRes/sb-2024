<?php

// http://seabattle24/controller/

session_start();

require_once '_config.php';
require_once PTH . DIR_SEP . 'functions.php';

require_once CLASS_PTH . DIR_SEP . 'class.localizer.php';

$pdo = getDBO(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD);

//$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$site_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . "/";
$api_link = $site_link . "api/";

$lang_id = getPageLanguageID();
$loc = new Localizer( loadLocal($lang_id) );

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$session_id = session_id();
$game_id = getCurrentGameBySessID($pdo, $session_id);
$game_status = getCurrentGameStatusBySessID($pdo, $session_id, $game_id);

$js_requests = ['main'];
$js_locals_required = [];
//----

echo '<!DOCTYPE html>';
echo '<html lang="' . $lang_id . '">';

require_once HTML_BLOCKS_PTH . DIR_SEP . 'head.php';

echo '<body>';

//var_dump(checkGameMap(10, [['size' => 3, 'x' => 9, 'y' => 3, 'direction' => 0], ['size' => 3, 'x' => 9, 'y' => 7, 'direction' => 0]]));
//var_dump(selectFullGameInfoById($pdo, 8, 8, true));


if ($game_id === -1 || $game_status !== 1) {
    require_once HTML_BLOCKS_PTH . DIR_SEP . 'game_menu.php';
} else {
    require_once HTML_BLOCKS_PTH . DIR_SEP . 'game.php';
}

echo '<script>';
echo 'const siteAPI = "' . $api_link . '";';
$js_locals_required = array_unique($js_locals_required);
if ( count($js_locals_required) > 0) {
    echo 'const locals = {';
    foreach ($js_locals_required as $local) {
        echo $local . ':"' . $loc->l($local, false).'",';
    }
    echo '};';
}
if ($game_id !== -1) {
    echo 'const game_id = ' . $game_id . ';';
}
echo '</script>';

foreach (array_unique($js_requests) as $request) {
    echo '<script src="'. $site_link . JS_PTH . '/' . $request . '.js"></script>';
}

include_once HTML_BLOCKS_PTH . DIR_SEP . 'js_online_update.php';

echo '</body>';
echo '</html>';

?>