const _last_request = {};

async function fetchData(url, data = null) {
    let method = 'GET';
    if (data)
        method = 'POST';
    
    try {
        let params = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include'
        }
        
        if (data)
            params.body = JSON.stringify(data);
        
        const response = await fetch(url, params);

        if (!response.ok) {
            throw new Error('Network response was not ok ' + response.statusText);
        }
        return await response.json();
        
    } catch (error) {
        console.error('There was a problem with your fetch operation:', error);
        throw error;
    }
}

async function Request(action, data = null) {
    const url_obj = new URL(siteAPI);
    url_obj.searchParams.append('action', action);
    url = url_obj.toString();
    
    _last_request.status = 'pending';
    _last_request.receiveTime = null;
    
    try {
        const result = await fetchData(url, data);
        
        _last_request.receiveTime = new Date();
        if (result.status != 'success') {
            _last_request.status = 'error';
            throw new Error('The server returned an error: ' + result.message);
        }
        
        _last_request.status = 'received';
        
        return result.data;
    } catch (error) {
        
        _last_request.receiveTime = new Date();
        _last_request.status = 'error';
        
        throw error;
    }
}

function localize(local_id) {
    if (locals === undefined || !locals.hasOwnProperty(local_id))
        return local_id;
    return locals[local_id];
}


function generateOwnRange(div) {
    const range = document.createElement('input');
    range.type = 'range';
    
    const attrs = ["min", "max", "value"];
    
    attrs.forEach(a => {
        if ( d_attr = div.getAttribute("data-" + a) ) {
            range.setAttribute(a, d_attr);
        }
    });

    const counter = document.createElement('span');
    
    div.append(range, counter);
    div.inputCallbacks = [];
    div.changeCallbacks = [];
    
    div.onInputBaseFunc = (e) => {
        counter.innerText = range.value;
        div.inputCallbacks.forEach(f => f.call(div, e));
    }
    div.onChangeBaseFunc = (e) => {
        div.changeCallbacks.forEach(f => f.call(div, e));
    }
    
    div.onInputBaseFunc();
    
    div.event_listeners = [];
    div.event_listeners.push(range.addEventListener('input', div.onInputBaseFunc));
    div.event_listeners.push(range.addEventListener('change', div.onChangeBaseFunc));
    
    div._OnInput = (func) => {
        div.inputCallbacks.push(func);
    }
    
    div._OnChange = (func) => {
        div.changeCallbacks.push(func);
    }
    
    div._Disable = () => {
        range.disabled = true;
    }
    div._Enable = () => {
        range.disabled = false;
    }
    
    Object.defineProperty(div, '_Value', {
        get() {
            return Number(range.value);
        }
    });
}

document.querySelectorAll('.js-own-range').forEach(el => {
    generateOwnRange(el);
});

function generateGameMap(ships, shoots) {
    
}


//data = await fetchData('https://api.example.com/data');
