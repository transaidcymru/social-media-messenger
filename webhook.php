<?php
require_once '../../../main.inc.php';
require_once INCLUDE_DIR.'class.signal.php';
require_once 'class.SocialLinkPlugin.php';

$config = SocialLinkPlugin::getConfigStatic();
$verify_token = $config->get("instagram-verify-token");


echo "hello world!!";

$hub_mode = $_GET["hub.mode"];
$hub_challenge = $_GET["hub.challenge"];
$hub_verify_token = $_GET["hub.verify_token"];

echo $hub_mode;
echo $hub_challenge;
echo $hub_verify_token;
echo $verify_token;

$result = strcmp($hub_verify_token, $verify_token);

if ($result === 0)
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
