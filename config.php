<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.message.php';

class SocialLinkPluginConfig extends PluginConfig {
    function getOptions() {
        return [
            'instagram-api-key' => new TextboxField([
                'default' => '',
                'label' => 'Instagram Access Token (API Key)',
                'hint' => 'API key for Instagram.',
                'configuration' => array(
                    'size'   => 500,
                    'length' => 200
                ),
            ]),
            'instagram-refresh-access-token' => new TextboxField([
                'default' => '50', // Instagram access token expiry is 60 days at time of writing, min days passed = 1
                'label' => 'Instagram Access Token Refresh Interval',
                'hint' => 'The number of days before refreshing the token automatically on Instagram sync.',
                'configuration'=>array('validator'=>'number', 'size'=>2)
            ]),
            'instagram-verify-webhook-token' => new TextboxField([
                'default' => '',
                'label' => 'Instagram API Webhook Verify Token',
                'hint' => 'Webhook secret set in admin settings to verify webhook requests.',
            ]),
            // TODO: datetime field
            'zero-hour' => new TextboxField([
                'default' => "",
                'label' => 'Zero hour',
                'hint' => 'Unix time to scrape messages since',
            ]),
            'min-sync-interval' => new TextboxField(array(
                'default' => '30',
                'label' => 'Minimum Interval Between Sync (seconds)',
                'hint'=>'Fewest digits allowed in a valid phone number',
                'configuration'=>array('validator'=>'number'),
            ))
        ];
    }

}?>
