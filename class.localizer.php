<?php

class Localizer {
    private $lang_list;
    
    public function __construct($lang_list) {
        $this->lang_list = $lang_list;
    }
    
    public function l($string, $echo = true) {
        $string = strtolower($string);
        $output = $string;
        
        if ( isset($this->lang_list[$string]) )
            $output = $this->lang_list[$string];
        
        if ($echo)
            echo $output;
        
        return $output;
    }
}

?>