<?php
    enum SCHLORPNESS {
        case DEBUG;
        case INFO;
        case WARN;
        case ERROR;
        case GUBBINS;
    }

    function SCHLORP($msg, SCHLORPNESS $level = SCHLORPNESS::ERROR){        
        error_log("SLP ".$level->name.": ".$msg);
    }

?>
