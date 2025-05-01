<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.message.php';

class SocialLinkPluginConfig extends PluginConfig {
    function getOptions() {
        return [
            'purge-age' => new TextboxField([
                'default' => '999',
                'label' => 'boop',
                'hint' => 'boop',
                'size' => 5,
                'length' => 4
            ]),
        ];
    }

}?>
