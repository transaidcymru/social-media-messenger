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
require_once 'config.php';
require_once 'mysqli.php';

class SocialLinkPlugin extends Plugin {
    var $config_class = 'SocialLinkPluginConfig'; 

    const PLUGIN_NAME = "Social Link Plugin";
    const TABLE_NAME = "tac_socialSessions";

    public function bootstrap() {
        Signal::connect('threadentry.created', array($this, 'threadUpdate'), 'Ticket');
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

    public function onThreadUpdate($threadentry) {
        // filter out thread updates we don't care about.
        // push to social media

    }

    public function fetch($object, $data) {
        // pull messages from social media and sync
    }

    public function getForm() {
        return array();
    }

    function uninstall(&$errors) {
            $errors = array();
            global $ost;
            // Send an alert to the system admin:
            //$ost->alertAdmin(self::PLUGIN_NAME . ' has been uninstalled', "You wanted that right?", true);
            $ost->logError(self::PLUGIN_NAME, "Plugin has been uninstalled!!!", false);

            parent::uninstall($errors);
    }
}
?>
