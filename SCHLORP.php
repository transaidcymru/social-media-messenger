<?php
    enum SCHLORPNESS {
        case DEBUG;
        case INFO;
        case WARN;
        case ERROR;
        case GUBBINS;
    }

    function SCHLORP($msg, SCHLORPNESS $level = SCHLORPNESS::ERROR){
        if ($level == SCHLORPNESS::DEBUG){
            return;
        }
        error_log("SCHLORP ".$level->name.": ".$msg);
    }

?>
