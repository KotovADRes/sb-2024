async function getGameInfo() {
    const game = await Request('get_game_info_by_id', {'game_id': game_id});
    return game;
}

const game = {};
const left_side = document.querySelector('.js-left-game-wrapper');
const right_side = document.querySelector('.js-right-game-wrapper');

(async () => {
    game.players = {};
    game.field = {};
    game.dom = {};
    game.func = {};
    game.timer = {};
    
    game.func.load = async () => {
        const data = await getGameInfo();
        game.info = data.game;
    };
    
    await game.func.load();
    
    game.dom.left_game = left_side;
    game.dom.right_game = right_side;
    
    game.field.user = createGameField(game.info.field_size, game.dom.left_game);
    game.field.enemy = createGameField(game.info.field_size, game.dom.right_game);
    
    game.func.update = () => {
        game.players.user_num = game.info.users_player_num;
        game.players.enemy_num = game.info.users_player_num == 1 ? 0 : 1;
        
        game.field.user.sb.mapCells(game.info.players[game.players.user_num].final_map);
        game.field.user.sb.mapShots(game.info.players[game.players.user_num].shots_map);
        
        game.field.enemy.sb.mapCells(game.info.players[game.players.enemy_num].final_map);
        game.field.enemy.sb.mapShots(game.info.players[game.players.enemy_num].shots_map);
    };
    
    game.func.loadAndUpdate = async () => {
        await game.func.load();
        game.func.update();
    };
    
    game.timer.id = null;
    game.timer.clearFunc = () => {
        if (game.timer.id !== null) {
            clearTimeout(game.timer.id);
            game.timer.id = null;
        }
    };
    game.timer.updateFunc = async () => {
        const check = await Request('is_need_game_update');
        if ( !check.need_update )
            return;
        
        await game.func.loadAndUpdate();
        await Request('im_updated');
    };
    game.timer.startFunc = (ms = 3000) => {
        game.timer.clearFunc();
        
        game.timer.id = setInterval(() => {
            if (_last_request.status == 'pending' 
            || document.visibilityState !== 'visible'
            )
                return;
                
            game.timer.updateFunc();
        }, ms);
    };
    
    
    game.func.update();
    game.timer.startFunc();
    
})();

async function updateFields() {
    const check = await Request('is_need_game_update');
    if ( !check.need_update )
        return;
    
    await game.func.loadAndUpdate();
    await Request('im_updated');
}

