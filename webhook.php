<?php
require_once '../../../main.inc.php';
require_once INCLUDE_DIR.'class.signal.php';
require_once 'class.SocialLinkPlugin.php';

$nothing = array();

$config = SocialLinkPlugin::getConfigStatic();
$verify_token = $config->get("instagram-verify-webhook-token");

$body = json_decode(file_get_contents("php://input"));

$hub_verify_token = $_GET["hub_verify_token"];
$hub_challenge = $_GET["hub_challenge"];
$hub_mode = $_GET["hub_mode"];

$result = strcmp($hub_verify_token, $verify_token);

if ($result === 0 && $hub_verify_token !== null && $hub_verify_token !== "")
{
    $data = [
        "hub_mode" => $hub_mode,
        "hub_challenge" => $hub_challenge,
        "hub_verify_token" => $hub_verify_token
    ];
    echo $hub_challenge;
}
else
{
    echo "invalid verify token";
}

if (strcmp($body->object, "instagram") === 0) {
    Signal::send('smm.instagram-webhook', null, $body);
}
