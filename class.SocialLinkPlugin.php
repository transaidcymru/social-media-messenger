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

        error_log("SHOW tables LIKE '".self::TABLE_NAME."';");
        error_log(db_query("SHOW tables LIKE '".self::TABLE_NAME."';"));
        error_log("SHOW tables LIKE 'ost_dev_ticket';");
        error_log(db_query("SHOW tables LIKE 'ost_dev_ticket';"));

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
