<?php
array_push($js_requests, 'field', 'game');
array_push($js_locals_required, 'game_over_win', 'game_over_defeat');
?>

<div class="game-wrapper">
    <div class="game-field-title"></div>
    <div class="sections-wrapper">
        <div class="section js-left-field">
            <div class="game-section-title">
                <span><?php $loc->l("game_players_field") ?></span>
            </div>
            <div class="game-field-wrapper game-user-side js-left-game-wrapper"></div>
        </div>
        <div class="section js-right-field">
            <div class="game-section-title">
                <span><?php $loc->l("game_enemys_field") ?></span>
            </div>
            <div class="game-field-wrapper game-enemy-side js-right-game-wrapper"></div>
        </div>
    </div>
</div>

<div class="game-over-popup js-game-over-popup" style="display: none;">
    <div class="game-over-title js-game-over-title"><?php $loc->l("game_over_draw") ?></div>
</div>
