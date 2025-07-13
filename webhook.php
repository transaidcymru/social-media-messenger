<?php
require_once '../../../api/api.inc.php';
require_once INCLUDE_DIR.'class.signal.php';
require_once 'class.SocialLinkPlugin.php';

$config = SocialLinkPlugin::getConfigStatic();
$verify_token = $config->get("instagram-verify-token");

$hub_mode = $_GET["hub.mode"];
$hub_challenge = $_GET["hub.challenge"];
$hub_verify_token = $_GET["hub.verify_token"];

if ($verify_token === $hub_verify_token)
{
    Signal::send('smm.instagram-webhook', null,
        [
            "hub_mode" => $hub_mode,
            "hub_challenge" => $hub_challenge,
            "hub_verify_token" => $hub_verify_token
        ]);
    echo $hub_challenge;
}
else
    echo "invalid verify token";
?>
