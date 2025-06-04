<?php
foreach ([
 'canned',
 'format',
 'list',
 'orm',
 'misc',
 'plugin',
 'ticket',
 'signal',
 'staff'
] as $c) {
    require_once INCLUDE_DIR . "class.$c.php";
}

//require_once 'class.SocialLinkAPI.php';
//require_once 'class.SocialLinkFetcher.php';
require_once 'config.php';
require_once 'mysqli.php';

class SocialLinkPlugin extends Plugin {
    var $config_class = 'SocialLinkPluginConfig'; 

    var $dummy_api;
    var $dummy;

    const PLUGIN_NAME = "Social Link Plugin";
    const TABLE_NAME = "tac_socialSessions";
    const SOURCES = array(
        "Bluesky",
        "Facebook",
        "Instagram"
    );

    public function bootstrap() {
        Signal::connect('threadentry.created', array($this, 'fetch'));
        Signal::connect('cron', array($this, 'fetch'));
        try {
            $test_query = db_query("SHOW tables LIKE '".self::TABLE_NAME."';");

            if (false === $test_query)
            {
                error_log(self::PLUGIN_NAME.": Error querying database");
                return;
            }
            if (true === $test_query)
            {
                error_log(self::PLUGIN_NAME.": unexpected query result");
                return;
            }

            if (!($test_query->num_rows > 0))
            {
                // todo: fix this shit
                $create_table_query = db_query("CREATE TABLE `tac_socialSessions` ( `session_id` int(11) unsigned not NULL auto_increment, `ticket_id` int(11) unsigned NOT NULL, `chat_id` varchar(100) NOT NULL, `platform` enum('Unknown','Facebook','Instagram','Bluesky') NOT NULL default 'Unknown', `timestamp_start` datetime NOT NULL, `timestamp_end` datetime NOT NULL, `session_type` varchar(30), PRIMARY KEY  (`session_id`)) DEFAULT CHARSET=utf8;");

                if (!$create_table_query)
                {
                    error_log(self::PLUGIN_NAME . ": error creating table in database");
                    return;
                }
            }

        }
        catch(Exception $e) {
            error_log("shit");
        }

    }
    

    public function onThreadUpdate($entry, $data) {
        // Get associated ticket
        $ticket_id = $entry->getParent();

        $ticket = Ticket::lookup($ticket_id);
        if(!$ticket)
        {
            error_log(self::PLUGIN_NAME."NO ticket associated with $ticket_id");
            return;
        }

        $source_extra_query = db_query(
            "SELECT source_extra from ".TICKET_TABLE." WHERE ticket_id=" . strval($ticket_id) . ";");

        if(false === $source_extra_query)
        {
            error_log(self::PLUGIN_NAME . ": Database query failure.");
            return;
        }

        if($source_extra_query->num_rows != 1)
        {
            error_log(self::PLUGIN_NAME . ": something went wrong here");
            return;
        }

        $source_extra = $source_extra_query->fetch_assoc()["source_extra"];

        // filter out thread updates we don't care about.
        if ($ticket->getSource() != "Other" &&
            !in_array($source_extra, self::SOURCES))
            return;

        $session_table_query = db_query(
            "SELECT * from ".self::TABLE_NAME." where ticket-id=" . strval($ticket_id) . ";");

        if(false === $session_table_query)
        {
            error_log(self::PLUGIN_NAME . ": Database query failure.");
            return;
        }

        if($session_table_query->num_rows != 1)
        {
            error_log(self::PLUGIN_NAME . ": what the FUCK");
            return;
        }

        // get all of the info from the thread update
        $session_table_row = $session_table_query->fetch_assoc();

        // push to social media
        error_log(self::PLUGIN_NAME . ": ARRAY!!! " . json_encode($session_table_row));
        
    }

    public function fetch($object, $data) {
        // pull messages from social media and sync
        error_log("fetching");
    }

    function pre_uninstall(&$errors) {
        $errors = array();
        global $ost;
        // Send an alert to the system admin:
        //$ost->alertAdmin(self::PLUGIN_NAME . ' has been uninstalled', "You wanted that right?", true);
        $ost->logError(self::PLUGIN_NAME, "Plugin has been uninstalled!!!", false);
        error_log("uninsalled!!!!");

        $create_table_query = db_query("DROP TABLE IF EXISTS ".self::TABLE_NAME.";");

        if (!$create_table_query)
        {
            error_log(self::PLUGIN_NAME . ": error creating table in database");
            return;
        }
        parent::uninstall($errors);
    }
}
?>
