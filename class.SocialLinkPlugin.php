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
            $ver = db_version();
            error_log("bootstrap version:".$ver);
        }
        catch(Exception $e) {
            error_log("shit");
        }

        $test_query = db_query("SHOW tables LIKE '".self::TABLE_NAME."';");

        if(false === $test_query)
        {
            error_log("1 Error querying database");
            return;
        }

        if(true === $test_query)
        {
            error_log("1 this can happen i guess?");
            return;
        }

        if($test_query->row_count > 0)
        {
            error_log("1 WE are here!");
        }

        $query_2 = db_query("SHOW tables LIKE 'ost_dev_ticket';");

        if(false === $query_2)
        {
            error_log("2 Error querying database");
            return;
        }

        if(true === $query_2)
        {
            error_log("2 this can happen i guess?");
            return;
        }

        if($query_2->row_count > 0)
        {
            error_log("2 WE are here!");
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
