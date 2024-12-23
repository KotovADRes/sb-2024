const cg_button = document.querySelector('#creategame');
const cg_controls = document.querySelector('.js-gamecreate-controls');

cg_button.trigger_action = (state) => {
    if (!state) {
        cg_button.innerText = cg_button.getAttribute("data-off-value");
        cg_controls.style.height = '0px';
    } else {
        cg_button.innerText = cg_button.getAttribute("data-on-value");
        cg_controls.style.height = '';
    }
}

cg_button.trigger_action(0);

cg_button.addEventListener('click', e => {
    const new_state = cg_controls.style.height !== '';
    cg_button.trigger_action(new_state);
});


//params: {(size): (count), ...}, {4: 3} - 3 ships of size 4
function createFieldConstructor(wrapper, field_size, params) {
    const field = createGameField(field_size);
    
    const field_wrapper = document.createElement('div');
    field_wrapper.classList.add('seabattle-field-wrapper');
    field_wrapper.append(field);
    
    const constructor_wrapper = document.createElement('div');
    constructor_wrapper.classList.add('seabattle-field-contructor-wrapper');
    
    const ships_wrapper = document.createElement('div');
    ships_wrapper.classList.add('seabattle-field-costships-wrapper');
    
    const field_errors_wrapper = document.createElement('div');
    field_errors_wrapper.classList.add('seabattle-field-costerrors-wrapper');
    
    const tips_wrapper = document.createElement('div');
    tips_wrapper.classList.add('seabattle-field-costtips-wrapper');
    
    constructor_wrapper.append(ships_wrapper, field_errors_wrapper, tips_wrapper);
    
    ships_wrapper.sb = {};
    ships_wrapper.sb.init_params = params;
    ships_wrapper.sb.disabled = false;
    ships_wrapper.sb.reload = () => {
        if (ships_wrapper.sb.disabled)
            return;
        
        ships_wrapper.innerHTML = '';
        field.sb.reload();
        
        ships_wrapper.sb.params = {};
        
        Object.keys(ships_wrapper.sb.init_params).sort().reverse().forEach(size => {
            const count = ships_wrapper.sb.init_params[size];
            ships_wrapper.sb.params[size] = {count: count, placed: 0};
            size = parseInt(size);
            
            const ship_group_div = document.createElement('div');
            ship_group_div.classList.add('ships-group');
            
            const ships_counter = document.createElement('div');
            ships_counter.classList.add('ship-counter');
            ships_counter.innerText = count;
            ships_counter.setAttribute('data-size', size);
            
            const ships_icon = document.createElement('div');
            ships_icon.classList.add('ship-icon');
            
            if (size < 5) {
                for (let i = 0; i < size; i++) {
                    const ships_icon_block = document.createElement('div');
                    ships_icon_block.classList.add('ship-icon-block');
                    
                    ships_icon.append(ships_icon_block);
                }
            } else {
                const ships_icon_block1 = document.createElement('span');
                ships_icon_block1.classList.add('ship-icon-block', 'text-block');
                ships_icon_block1.innerText = size + 'x';
                
                const ships_icon_block2 = document.createElement('div');
                ships_icon_block2.classList.add('ship-icon-block');
                
                ships_icon.append(ships_icon_block1, ships_icon_block2);
            }
            
            
            ship_group_div.append(ships_counter, ships_icon);
            
            ship_group_div.addEventListener('pointerup', () => {ships_wrapper.sb.placeMode(size)});
            
            ships_wrapper.append(ship_group_div);
        });
        
        field.sb.cells.forEach(cell => {
            const set_ship_position = () => {
                if (!ships_wrapper.sb.drag.is_active
                    || ships_wrapper.sb.drag.ship === null
                    ){
                    return;
                }
                
                field.focus();
                ships_wrapper.sb.drag.ship.setPosition(cell.sb.x, cell.sb.y);
            }
            
            const cell_click = () => {
                if (!ships_wrapper.sb.drag.is_active
                    || ships_wrapper.sb.drag.ship === null
                    ){
                    
                    if (Date.now() - ships_wrapper.sb.drag.last_place > 100) {
                        ships_wrapper.sb.drag.is_active = true;
                        ships_wrapper.sb.drag.ship = cell.sb.getShip();
                    }
                    
                } else {
                    ships_wrapper.sb.drag.is_active = false;
                    ships_wrapper.sb.drag.ship = null;
                    ships_wrapper.sb.drag.last_place = Date.now();
                    
                    ships_wrapper.sb.checkErrors();
                }
                
            }
            
            cell.addEventListener('pointerover', set_ship_position);
            cell.addEventListener('pointerup', cell_click);
        });
    }
    
    ships_wrapper.sb.drag = {};
    ships_wrapper.sb.drag.is_active = false;
    ships_wrapper.sb.drag.ship = null;
    ships_wrapper.sb.drag.last_place = null;
    
    ships_wrapper.sb.placeMode = (size) => {
        if (ships_wrapper.sb.disabled)
            return;
        
        const ships_left = ships_wrapper.sb.params[size].count - ships_wrapper.sb.params[size].placed
        if (ships_left <= 0)
            return;
        
        const ship = field.sb.placeShip({
            size: size,
            x: 0,
            y: 0,
            direction: 1
        });
        
        if (ship === null)
            return;
        
        ships_wrapper.sb.drag.is_active = true;
        ships_wrapper.sb.drag.ship = ship;
        ships_wrapper.sb.params[size].placed++;
        
        const counter = ships_wrapper.querySelector('.ship-counter[data-size="' + size + '"]');
        counter.innerText = ships_left - 1;
    }
    
    ships_wrapper.sb.cancelPlaceMode = () => {
        if (!ships_wrapper.sb.drag.is_active
            || ships_wrapper.sb.drag.ship === null
            ){
            return;
        }
        ships_wrapper.sb.drag.is_active = false;
        
        const size = ships_wrapper.sb.drag.ship.size;
        field.sb.removeShip(ships_wrapper.sb.drag.ship);
        ships_wrapper.sb.drag.ship = null;
        
        ships_wrapper.sb.drag.last_place = null;
        
        const placed = ships_wrapper.sb.params[size].placed;
        ships_wrapper.sb.params[size].placed -= placed > 0 ? 1 : 0;
        
        const counter = ships_wrapper.querySelector('.ship-counter[data-size="' + size + '"]');
        counter.innerText = ships_wrapper.sb.params[size].count - ships_wrapper.sb.params[size].placed;
        
        ships_wrapper.sb.checkErrors();
    }
    
    ships_wrapper.sb.checkErrors = () => {
        if (field.sb.hasErrors()) {
            if (field.sb.global_errors.has('ship_out_of_boudaries')) {
                field_errors_wrapper.innerText = '(!)' + localize('game_create_error_out_of_boundaries');
            } else {
                field_errors_wrapper.innerText = '(!)' + localize('game_create_error_overlap');
            }
            
        } else {
            field_errors_wrapper.innerText = '';
        }
    }
    
    field.addEventListener('wheel', (e) => {
        if (!ships_wrapper.sb.drag.is_active
            || ships_wrapper.sb.drag.ship === null
            ){
            return;
        }
        
        e.preventDefault();
        
        const curr_dir = ships_wrapper.sb.drag.ship.direction;
        const coef = e.deltaY > 0 ? 1 : -1;
        
        ships_wrapper.sb.drag.ship.setDirection((4 + curr_dir + coef) % 4);
        
    });
    
    field.setAttribute('tabindex', '0');
    
    field.addEventListener('keydown', (e) => {
        if (!ships_wrapper.sb.drag.is_active
            || ships_wrapper.sb.drag.ship === null
            ){
            return;
        }
        if (e.key === 'Escape') {
            ships_wrapper.sb.cancelPlaceMode();
        }
        
    });
    
    ships_wrapper.sb.checkReadinessForStart = () => {
        if (field.sb.hasErrors())
            return false;
        
        const all_ships_placed = Object.keys(ships_wrapper.sb.params).every(s => ships_wrapper.sb.params[s].count === ships_wrapper.sb.params[s].placed);
        if (!all_ships_placed) {
            field_errors_wrapper.innerText = '(!)' + localize('game_create_error_ships_not_placed');
            return false;
        }
        
        return true;
    }
    
    ships_wrapper.sb.isDisabled = (bool) => {
        ships_wrapper.sb.disabled = bool ? true : false;
    }
    ships_wrapper.sb.reload();
    
    wrapper.append(field_wrapper, constructor_wrapper);
    
    return [field, ships_wrapper];
}

let ships_sets = {};
const basic_ship_set = {
    '4': 1,
    '3': 2,
    '2': 3,
    '1': 4
};
function get_ship_set (field_size) {
    if (ships_sets.hasOwnProperty('sets') && ships_sets.sets.hasOwnProperty(field_size))
        return ships_sets.sets[field_size];
    
    return basic_ship_set;
}

(async () => {
    ships_sets = await Request('get_ship_sets');
    
    function get_ship_set (field_size) {
        if (ships_sets.sets.hasOwnProperty(field_size))
            return ships_sets.sets[field_size];
        
        return basic_ship_set;
    }
    
    
    const preview = document.querySelector('.js-gamemap-preview');
    const init_value = document.querySelector('.js-gamecreate-field-size')._Value;
    
    
    const [field, ships_wrapper] = createFieldConstructor(preview, init_value, get_ship_set(init_value));
    const field_size_range = document.querySelector('.js-gamecreate-field-size');

    field_size_range._OnChange(async function (e) {
        field.sb.field_size = this._Value;
        ships_wrapper.sb.init_params = get_ship_set(this._Value);
        //const ships = await Request('populate_field', {size: this._Value});
        ships_wrapper.sb.reload();
        //ships.ships.forEach(s => field.sb.placeShip(s, false));
        //field.sb.updateShips();
    });
    
    document.querySelector('#creategame_start').addEventListener('click', async (e) => {
        if (!ships_wrapper.sb.checkReadinessForStart())
            return;
        
        const with_computer_chkbx = document.querySelector('.js-creategame-with-computer');
        
        with_computer_chkbx.disabled = true;
        field_size_range._Disable();
        ships_wrapper.sb.isDisabled(true);
        
        await Request('create_game', {
            size: field_size_range._Value,
            ships: field.sb.ships,
            computer: with_computer_chkbx.checked
        });
        
        with_computer_chkbx.disabled = false;
        field_size_range._Enable();
        ships_wrapper.sb.isDisabled(false);
        
        gamelist.sb.getAndUpdateData();
        checkAvailabilityOfGameCreation();
    });
})();

function getFieldPopup() {
    const popup_wrapper = document.querySelector('.js-ships-field-popup');
    const popup = document.querySelector('.js-popup-field-wrapper');
    const start = document.querySelector('#joingame');
    
    if ( !(popup.hasOwnProperty('sb')) ) {
        popup.sb = {};
        popup_wrapper.sb = {};
        
        popup.sb.setFieldSize = (field_size) => {
            popup.sb.field_size = field_size;
            popup.sb.set = get_ship_set(field_size);
        }
        
        popup.sb.setFieldSize(Object.keys(ships_sets)[0]);
        
        popup.sb.reload = () => {
            popup.innerHTML = '';
            
            const [field, ships_wrapper] = createFieldConstructor(popup, popup.sb.field_size, popup.sb.set);
            popup.sb.field = field;
            popup.sb.ships_wrapper = ships_wrapper;
        };
        
        popup.sb.gameId = -1;
        
        popup_wrapper.sb.showPopup = () => {
            popup_wrapper.style.display = '';
        }
        popup.sb.showPopup = () => {
            popup_wrapper.sb.showPopup();
        }
        
        popup_wrapper.sb.hidePopup = () => {
            popup_wrapper.style.display = 'none';
        }
        popup.sb.hidePopup = () => {
            popup_wrapper.sb.hidePopup();
        }
        
        popup_wrapper.addEventListener('click', (e) => {
            if (e.target === popup_wrapper)
                popup_wrapper.sb.hidePopup();
        });
        
        start.addEventListener('click', async (e) => {
            if ( popup.sb.ships_wrapper.sb.checkReadinessForStart() && popup.sb.gameId !== -1) {
                const join_game = await Request('join_game', {game_id: popup.sb.gameId, ships: popup.sb.field.sb.ships});
                location.reload();
            }
        });
    
    }
    
    return [popup_wrapper, popup, start];
}

function addGametableFunctions(gamelist) {
    const tbody = gamelist.querySelector('tbody');
    const fields = ['name', 'field_size', 'status', 'date', 'with_computer'];
    
    gamelist.sb = {};
    
    gamelist.sb.getTr = (row) => {
        const tr = document.createElement('tr');
        
        fields.forEach(f => {
            const td = document.createElement('td');
            if (row.hasOwnProperty(f)) {
                switch (f) {
                    case 'with_computer':
                        td.innerText = parseInt(row[f]) ? '👤🖥️' : '👤👤';
                        break;
                    case 'status':
                        td.innerText = parseInt(row[f]) ? localize('game_status_going') : localize('game_status_waiting');
                        break;
                    default:
                        td.innerText = row[f];
                        break;   
                }
            }
            
            tr.append(td);  
        });
        
        return tr;
    }
    
    const [popup_wrapper, popup, start_button] = getFieldPopup();
    
    gamelist.sb.update = () => {
        tbody.innerHTML = '';
        gamelist.sb.data.forEach(row => {
            const tr = gamelist.sb.getTr(row);
            tr.sb = {};
            
            if ( row.can_user_join ) {
                tr.addEventListener('click', (e) => {
                    popup.sb.setFieldSize(row.field_size);
                    popup.sb.gameId = row.id;
                    popup.sb.reload();
                    popup.sb.showPopup();
                });
            }
            
            tbody.append(tr);
        });
    }
    
    gamelist.sb.updateData = (new_data) => {
        gamelist.sb.data = new_data;
        gamelist.sb.update();
    }
    
    gamelist.sb.getAndUpdateData = async () => {
        const gamedata = await Request('get_games_list');
        gamelist.sb.updateData(gamedata.games);
    };
    
    gamelist.sb.timer_id = null;
    
    gamelist.sb.deleteRequestInterval = () => {
        if (gamelist.sb.timer_id !== null) {
            clearInterval(gamelist.sb.timer_id);
            gamelist.sb.timer_id = null;
        }
    }
    
    gamelist.sb.requestInterval = (ms) => {
        gamelist.sb.deleteRequestInterval();
        
        if (gamelist.sb.timer_id !== null) {
            clearInterval(gamelist.sb.timer_id);
            gamelist.sb.timer_id = null;
        }
        
        gamelist.sb.timer_id = setInterval(() => {
            if (_last_request.status == 'pending'
            || document.visibilityState !== 'visible'
            )
                return;
            
            gamelist.sb.getAndUpdateData();
        }, ms);
    }
    
    gamelist.sb.getAndUpdateData();
    
    gamelist.sb.requestInterval(5000);
    
}

const gamelist = document.querySelector('table#gamelist');
addGametableFunctions(gamelist);

async function checkAvailabilityOfGameCreation() {
    const game = await Request('get_current_game');
    const controls = document.querySelector('.js-gamelist-controls-wrapper');
    
    if (!game)
        return;
    
    const in_game = game.in_game;
    
    if ( in_game ) {
        controls.style.display = 'none';
    } else {
        controls.style.display = '';
    }
}

checkAvailabilityOfGameCreation();