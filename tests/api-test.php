<?php
include_once "../class.InstagramAPI.php";


$api = new InstagramAPI(getenv("API_KEY"));

$conversations = $api->getConversations();
foreach ($conversations as $conversation)
{
    print_r($conversation);
    $messages = $api->getMessages($conversation->id, 0);
    foreach($messages as $message)                
    {
        print_r($message);
    }
    print_r("--------------------------------------------------------------------------------\n\n");
}

?>
