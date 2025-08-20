<?php

define("TEST_ENV", 1);
include_once "../class.InstagramAPI.php";
include_once "mysqli_test.php";
include_once "../SocialLinkDB.php";

function isTicketOpen($id){
    print_r("checking if $id is open. returning true. because osticket is nowhere\n");
    return true;
}

function newSession($conversation, $messages, $platform) {
    // create a ticket
    print_r("----- CREATING TICKET!!!! ------------------------------------------------------\n");
    print_r($conversation);
    print_r($messages);
    print_r("--------------------------------------------------------------------------------\n\n");
    SocialLinkDB\insertSocialSession(new SocialLinkDB\SocialSession(
        123123,
        $conversation->id,
        $platform,
        $messages[0]->time,
        $messages[count($messages) - 1]->time
    ));
}

function updateSession(
    SocialLinkDB\SocialSession $most_recent_session,
    SocialMediaConversation $conversation,
    array $messages)
{
    print_r("----- UPDATING TICKET!!!! ------------------------------------------------------\n");
    print_r($most_recent_session);
    print_r($conversation);
    print_r($messages);
    print_r("--------------------------------------------------------------------------------\n\n");
    SocialLinkDB\updateEndTime($most_recent_session, $messages[0]->time);
}

db_connect();
SocialLinkDB\initTable();
print_r(SocialLinkDB\selectionQuery("SELECT * FROM tac_socialSessions")->fetch_all(MYSQLI_ASSOC));

$zero_hour = 1754844855;

$api = new InstagramAPI(getenv("API_KEY"));

$conversations = $api->getConversations();
foreach ($conversations as $conversation)
{
    // reject the session if update time is before the 'zero hour'.
    // this avoids the issue where upon going live a new ticket is
    // made for EVERY SINGLE DIRECT MESSAGE WE HAVE EVER RECIEVED
    if($conversation->updated_time < $zero_hour)
    {
        print_r("rejecting message - updated time is before zero hour\n");
        continue;
    }

    $associated_sessions = SocialLinkDB\socialSessionsFromChatID($conversation->id);

    $most_recent_session = null;
    foreach ($associated_sessions as $session)
    {
        if ($most_recent_session === null)
            $most_recent_session = $session;
        else if ($session->timestamp_end > $most_recent_session->timestamp_end)
            $most_recent_session = $session;
    }

    // this will short circuit. no type worries.
    $new_session = $most_recent_session === null || !isTicketOpen($most_recent_session->ticket_id);

    // more short circuiting. This triggers if we found the session but it's up to date.
    if (!$new_session && $conversation->updated_time <= $most_recent_session->timestamp_end)
    {
        print_r("Nothing to do!! continuing on\n");
        continue;
    }

    // if we get this far we have tickets to update/create.

    $update_since = $new_session ? $zero_hour : $most_recent_session->timestamp_end;
    $messages = $api->getMessages($conversation->id, $update_since);

    if($new_session)
        newSession($conversation, $messages, SocialLinkDB\Platform::Instagram);
    else
        updateSession($most_recent_session, $conversation, $messages);
}

?>
