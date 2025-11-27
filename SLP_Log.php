<?php

    enum SLP_Level {
        case DEBUG;
        case INFO;
        case WARN;
        case ERROR;
    }

    function SLP_Log($msg, SLP_Level $level = SLP_Level::ERROR){        
        error_log("SLP ".$level->name.": ".$msg);
    }

?>