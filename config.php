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
                'configuration' => array(
                    'size'   => 500,
                    'length' => 200
                ),
            ]),
            'instagram-verify-token' => new TextboxField([
                'default' => '',
                'label' => 'Instagram API Webhook Key',
                'hint' => 'Webhook secret set in admin settings to verify webhook requests.',
            ]),
            // TODO: datetime field
            'zero-hour' => new TextboxField([
                'default' => "",
                'label' => 'Zero hour',
                'hint' => 'Unix time to scrape messages since',



            ])
        ];
    }

}?>
