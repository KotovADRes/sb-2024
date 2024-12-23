<?php
array_push($js_requests, 'field', 'game');
//array_push($js_locals_required, 'game_create_error_out_of_boundaries');
?>

<div class="game-wrapper">
    <div class="game-field-title"></div>
    <div class="sections-wrapper">
        <div class="section js-left-field">
            <div class="game-section-title">
                <span><?php $loc->l("game_players_field") ?></span>
            </div>
            <div class="game-field-wrapper js-left-game-wrapper"></div>
        </div>
        <div class="section js-right-field">
            <div class="game-section-title">
                <span><?php $loc->l("game_enemys_field") ?></span>
            </div>
            <div class="game-field-wrapper js-right-game-wrapper"></div>
        </div>
    </div>
</div>
