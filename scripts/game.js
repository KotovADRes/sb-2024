async function getGameInfo() {
    const game = await Request('get_game_info_by_id', {'game_id': game_id});
    return game;
}

const game = {};
const left_side = document.querySelector('.js-left-game-wrapper');
const right_side = document.querySelector('.js-right-game-wrapper');

const game_over = document.querySelector('.js-game-over-popup');
const game_over_title = document.querySelector('.js-game-over-title');

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
    game.dom.game_over = game_over;
    game.dom.game_over_title = game_over_title;
    
    game.field.user = createGameField(game.info.field_size, game.dom.left_game);
    game.field.enemy = createGameField(game.info.field_size, game.dom.right_game);
    
    game.field.enemy.addEventListener('click', async (e) => {
        if (!game.players.is_users_turn 
        || !e.target.classList.contains('seabattle-cell')
        || e.target.classList.contains('shot-crossmark')
        || game.players.move_lock
        ) {
            return;
        }
        
        game.players.move_lock = true;
        
        const x = e.target.sb.x;
        const y = e.target.sb.y;
        
        try {
            await Request('make_a_move', {x: x, y: y});
        } catch (e) {
            
        }
        
        await game.timer.updateFunc();
        game.players.move_lock = false;
    });
    
    game.players.move_lock = false;
    game.players.game_over = false;
    
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
    
    game.func.updateTurn = () => {
        if (game.players.is_users_turn) {
            game.field.user.classList.add('current-turn');
            game.field.enemy.classList.remove('current-turn');
        } else {
            game.field.enemy.classList.add('current-turn');
            game.field.user.classList.remove('current-turn');
        }
    };
    
    game.func.stopGame = () => {
        game.dom.game_over_title.innerText = game.players.user_won ? localize('game_over_win') : localize('game_over_defeat');
        document.querySelector('body').style.overflow = 'hidden';
        game.dom.game_over.style.display = '';
        
        game.dom.game_over_title.style.animation = 'slideInFromTop 0.4s ease-out forwards';
        
        game.dom.game_over.addEventListener('click', () => location.reload());
    }
    
    game.timer.id = null;
    game.timer.clearFunc = () => {
        if (game.timer.id !== null) {
            clearTimeout(game.timer.id);
            game.timer.id = null;
        }
    };
    game.timer.updateFunc = async () => {
        const check = await Request('get_the_game_state');
        game.players.is_users_turn = check.is_users_turn;
        game.func.updateTurn();
        
        if (check.game_over) {
            game.players.game_over = true;
            game.players.user_won = check.user_won;
            game.func.stopGame();
            await Request('im_updated');
            return;
        }
        
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
            || game.players.game_over
            )
                return;
                
            game.timer.updateFunc();
        }, ms);
    };
    
    
    game.func.update();
    game.timer.updateFunc();
    game.timer.startFunc(750);
    
})();

async function updateFields() {
    const check = await Request('is_need_game_update');
    if ( !check.need_update )
        return;
    
    await game.func.loadAndUpdate();
    await Request('im_updated');
}

