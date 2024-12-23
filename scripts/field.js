function createGameField(field_size, wrapper = null) {
    const field = document.createElement('div');
    field.classList.add('seabattle-field');
    
    field.sb = {};
    field.sb.cells = [];
    field.sb.field_size = field_size;
    const additional_classes = ['hidden-cell', 'near-ship-cell', 'ship-cell', 'damaged-ship-cell', 'error-cell']
    
    field.sb.reload = () => {
        field.sb.cells.forEach(cell => cell.remove());
        field.sb.cells = [];
        field.sb.ships = [];
        field.sb.global_errors = new Set();
        field.innerHTML = '';
        
        field.style.gridTemplateColumns = 'repeat(' + field.sb.field_size + ', 1fr)';
        
        for (let y = 0; y < field.sb.field_size; y++) {
            for (let x = 0; x < field.sb.field_size; x++) {
                const cell = document.createElement('div');
                cell.classList.add('seabattle-cell');
                
                cell.sb = {};
                
                cell.sb.x = x;
                cell.sb.y = y;
                
                cell.sb.clearClasses = () => {
                    additional_classes.forEach(ac => {
                        cell.classList.remove(ac);
                    });
                }
                
                cell.sb.setClassByIndex = (indx) => {
                    cell.classList.add( additional_classes[indx] );
                }
                
                //-1 - hidden, 0 - sea, 1 - near-ship, 2 - ship, 3 - damaged ship
                cell.sb.status = 0;
                cell.sb.is_shot = false;
                cell.sb.errors = new Set();
                
                cell.sb.updateCell = () => {
                    cell.sb.clearClasses();
                    
                    if (cell.sb.errors.size > 0) {
                        cell.sb.setClassByIndex(4);
                        return;
                    }
                    
                    const stat = cell.sb.status;
                    
                    if (stat < 0) {
                        cell.sb.setClassByIndex(0);
                    } else if (stat > 0 && stat <= 3) {
                        cell.sb.setClassByIndex(stat);
                    }  
                    
                    if (cell.sb.is_shot) {
                        cell.classList.add('shot-crossmark');
                    } else {
                        cell.classList.remove('shot-crossmark');
                    }
                }
                
                
                cell.sb.setStatus = (stat) => {
                    cell.sb.status = stat;
                    
                    if(stat < 2 && cell.sb.getShip() !== null)
                        cell.sb.setShip(null);
                    
                    cell.sb.updateCell();
                }
                
                cell.sb.setShot = (shot) => {
                    cell.sb.is_shot = Boolean(shot);
                    cell.sb.updateCell();
                }
                
                
                cell.sb.addError = (flag) => {
                    cell.sb.errors.add(flag);
                    cell.sb.updateCell();
                }
                
                cell.sb.removeError = (flag) => {
                    cell.sb.errors.delete(flag);
                    cell.sb.updateCell();
                }
                
                cell.sb.clearErrors = () => {
                    cell.sb.errors.clear();
                    cell.sb.updateCell();
                }
                
                
                cell.sb.binded_ship = null;
                cell.sb.getShip = () => {
                    return cell.sb.binded_ship;
                }
                cell.sb.setShip = (ship) => {
                    cell.sb.binded_ship = ship;
                }
                
                
                field.sb.cells.push(cell);
                field.append(cell);
            }
        }
    }
    
    field.sb.changeSize = (size) => {
        field.sb.field_size = size;
        field.sb.reload();
    }
    
    field.sb.reload();
    
    field.sb.getCellByCoordinates = (x, y) => {
        let found_cell = null;
        
        field.sb.cells.some(cell => {
            if (cell.sb.x == x && cell.sb.y == y) {
                found_cell = cell;
                return true;
            }
            
            return false;
        });
        
        return found_cell;
    };
    
    field.sb.setCellStatus = (x, y, stat) => {
        const cell = field.sb.getCellByCoordinates(x, y);
        if (cell === null)
            return false;
        
        cell.sb.setStatus(stat)
        return true;
    };
    
    field.sb.flagError = (x, y, flag) => {
        const cell = field.sb.getCellByCoordinates(x, y);
        if (cell === null)
            return false;
        
        cell.sb.addError(flag);
        
        return true;
    }
    
    field.sb.hasErrors = () => {
        return field.sb.cells.some(cell => cell.sb.errors.size > 0) || field.sb.global_errors.size > 0;
    }
    
    field.sb.updateShips = () => {
        const direction_coefs = [
            [0, -1], 
            [1, 0], 
            [0, 1], 
            [-1, 0]
        ];
        
        field.sb.cells.forEach(cell => {
            cell.sb.setStatus(0)
            cell.sb.clearErrors();
            field.sb.global_errors.delete('ship_out_of_boudaries');
        });
        
        field.sb.ships.forEach(ship => {
            const dc = direction_coefs[ship.direction];
            let x = ship.x;
            let y = ship.y;
            
            for (let s = 0; s < ship.size; s++) {
                for (let y_n = y - 1; y_n <= y + 1; y_n++) {
                    for (let x_n = x - 1; x_n <= x + 1; x_n++) {
                        const curr_cell = field.sb.getCellByCoordinates(x_n, y_n);
                        if (curr_cell === null) {
                            if (x_n == x && y_n == y) {
                                field.sb.global_errors.add('ship_out_of_boudaries');
                            }
                            continue;
                        }
                        
                        const cell_status = curr_cell.sb.status;
                        
                        if (x_n == x && y_n == y) {
                            if (curr_cell.sb.getShip() !== ship)
                                curr_cell.sb.setShip(ship);
                            
                            if (cell_status > 0) {
                                field.sb.flagError(x_n, y_n, 'ship_overlap');
                            } else {
                                field.sb.setCellStatus(x_n, y_n, 2);
                            }
                        } else if (!(
                            x_n == x + dc[0] 
                            && y_n == y + dc[1]
                            && s < ship.size - 1)
                            
                            && !(
                            x_n == x - dc[0] 
                            && y_n == y - dc[1]
                            && s > 0)
                            
                            ) {
                            
                            if (cell_status > 1) {
                                field.sb.flagError(x_n, y_n, 'ship_overlap');
                            } else {
                                field.sb.setCellStatus(x_n, y_n, 1);
                            }
                            
                        }
                        
                    }
                }
                
                x += dc[0];
                y += dc[1];
            }
            
        });
    }
    
    //{size: (1 ... field_size), x: (x), y: (y), direction: (0 ... 3: up, right, down, left)
    field.sb.placeShip = (ship, update = true) => {
        if (!(
        ship.hasOwnProperty('size')
        && ship.size > 0
        && ship.size <= field.sb.field_size
        )) {
            return null;
        }
        
        if (!(
        ship.hasOwnProperty('x')
        && ship.hasOwnProperty('y')
        && ship.x >= 0
        && ship.x < field.sb.field_size
        && ship.y >= 0
        && ship.y < field.sb.field_size
        )) {
            return null;
        }
        
        if (!(
        ship.hasOwnProperty('direction')
        && ship.direction >= 0
        && ship.direction < 4
        )) {
            return null;
        }
        
        ship.setPosition = (x, y) => {
            ship.x = x;
            ship.y = y;
            field.sb.updateShips();
        }
        
        ship.setDirection = (d) => {
            ship.direction = d;
            field.sb.updateShips();
        }
        
        field.sb.ships.push(ship);
        
        if (update)
            field.sb.updateShips();
        
        return ship;
    }
    
    field.sb.changeShipPosition = (x, y, ship) => {
        ship.setPosition(x, y);
    }
    
    field.sb.removeShip = (rem_ship) => {
        field.sb.ships = field.sb.ships.filter(ship => ship !== rem_ship);
        field.sb.updateShips();
    }
    
    field.sb.mapShots = (shots_map) => {
        field.sb.cells.forEach(cell => {
            const x = cell.sb.x;
            const y = cell.sb.y;
            
            if (shots_map.length > x && shots_map[x].length > y) {
                cell.sb.setShot(shots_map[x][y]);
            }
        });
    }
    
    field.sb.mapCells = (status_map) => {
        field.sb.cells.forEach(cell => {
            const x = cell.sb.x;
            const y = cell.sb.y;
            
            if (status_map.length > x && status_map[x].length > y) {
                cell.sb.setStatus(status_map[x][y]);
            }
        });
    }
    
    if (wrapper instanceof Element) {
        wrapper.append(field);
    } 
    
    return field;
    
}