<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.message.php';

class SocialLinkPluginConfig extends PluginConfig {
    function getOptions() {
        return [
            'instagram-api-key' => new TextboxField([
                'default' => '',
                'label' => 'Instagram API Key',
                'hint' => 'API key for instagram API.',
            ]),
            'instagram-webhook-secret' => new TextboxField([
                'default' => '',
                'label' => 'Instagram API Webhook Key',
                'hint' => 'Webhook secret set in admin settings to verify webhook requests.',
            ]),
        ];
    }

}?>
