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

class SocialLinkPlugin extends Plugin {
    var $config_class = 'SocialLinkPluginConfig'; 

    const PLUGIN_NAME = "Social Link Plugin";

    public function bootstrap() {
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
