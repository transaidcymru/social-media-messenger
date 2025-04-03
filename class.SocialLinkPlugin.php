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

    public function bootstrap() {
        Signal::connect('ticket.created', array($this, 'onTicketCreated'), 'Ticket');
        try {
            $ver = db_version();
            error_log("bootstrap version:".$ver);
        }
        catch(Exception $e) {
            error_log("shit");
        }

    }

    public function onTicketCreated($ticket) {
        try {
            global $ost;
            $ver = db_version();
            $ost->logError(self::PLUGIN_NAME, "version:".$ver, false);
            error_log("version:".$ver);
        }
        catch(Exception $e) {
            error_log("shit");
        }
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
