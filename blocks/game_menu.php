<?php
array_push($js_requests, 'field', 'gamelist');
array_push($js_locals_required, 'game_create_error_out_of_boundaries', 'game_create_error_overlap', 'game_create_error_ships_not_placed', 'game_status_waiting', 'game_status_going');
?>

<div class="gamelist-wrapper">
    <div class="gamelist-controls-wrapper js-gamelist-controls-wrapper" style="display: none;">
        <div class="main-controls">
            <button 
                class="button" 
                id="creategame" 
                data-off-value="<?php $loc->l("game_create_add") ?>" 
                data-on-value="<?php $loc->l("game_create_cancel") ?>">
            </button>
        </div>
        <div class="gamecreate-controls js-gamecreate-controls" style="height: 0px;">
            <div class="controls">
                <label for="board-size"><?php $loc->l("game_create_field_size") ?>:</label>
                <br>
                <div class="js-own-range own-range js-gamecreate-field-size" data-min="8" data-max="12" data-value="10"></div>
                <br>
                <div style="display: flex;">
                    <input type="checkbox" class="js-creategame-with-computer" style="transform: scale(1.4);margin-right: 10px;">
                    <label for="board-size"><?php $loc->l("game_create_with_computer") ?></label>
                </div>
                
            </div>
            <div class="js-gamemap-preview preview">
            </div>
            <br>
            <!--<button class="button" id="creategame_fillfild">
                <?php $loc->l("game_create_fill") ?>
            </button>-->
            <button class="button" id="creategame_start">
                <?php $loc->l("game_create_start") ?>
            </button>
        </div>
    </div>
    <div class="gamelist-table-wrapper">
        <table id="gamelist">
            <thead>
                <tr>
                    <td style="width: 30%"><?php $loc->l("game_menu_game") ?></td>
                    <td style="width: 10%"><?php $loc->l("game_menu_field_size") ?></td>
                    <td style="width: 30%"><?php $loc->l("game_menu_status") ?></td>
                    <td style="width: 20%"><?php $loc->l("game_menu_datetime") ?></td>
                    <td style="width: 10%"><?php $loc->l("game_menu_type") ?></td>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div class="ships-field-popup js-ships-field-popup" style="display: none;">
        <div class="popup">
            <div class="js-popup-field-wrapper" style="display: flex;"></div>
            <button class="button" id="joingame" style="margin-top: 20px;">
                <?php $loc->l("game_create_start") ?>
            </button>
        </div>
    </div>
</div>
