<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.message.php';

class SocialLinkPluginConfig extends PluginConfig {
    function getOptions() {
        return [
            'purge-age' => new TextboxField([
                'default' => '999',
                'label' => 'Max Ticket age in days',
                'hint' => 'Tickets with no updates in this many days will match and have their status changed.',
                'size' => 5,
                'length' => 4
            ]),
        ];
    }

}?>
