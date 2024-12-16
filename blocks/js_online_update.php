<script>

if ( typeof _last_request !== 'undefined' ) {
    let updateTimerId = null;
    
    function runOnlineUpdate() {
        const interval = 3000;
        
        stopOnlineUpdate();
        updateTimerId = setInterval(() => {
            const curr_date = new Date();
            if (_last_request.status == 'pending' 
            || (curr_date - _last_request.receiveTime) < parseInt(interval * 0.9)
            || document.visibilityState !== 'visible'
            )
                return;
            
            Request('im_online');
        }, interval);
        
        return updateTimerId;
    }
    
    function stopOnlineUpdate() {
        if (updateTimerId === null)
            return false;
        
        clearInterval(updateTimerId);
        updateTimerId = null;
        
        return true;
    }
    
    
    //runOnlineUpdate();
    
}

</script>