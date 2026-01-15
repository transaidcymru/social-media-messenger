<?php

foreach ([
 'canned',
 'format',
 'list',
 'orm',
 'misc',
 'plugin',
 'ticket',
 'signal',
 'staff'
] as $c) {
    require_once INCLUDE_DIR . "class.$c.php";
}

require_once 'config.php';
require_once 'mysqli.php';
require_once 'SocialLinkDB.php';
require_once 'APIClasses.php';
require_once 'SCHLORP.php';

class SocialLinkPlugin extends Plugin
{
    // -- Definitions ----------------------------------------------------------
    public $config_class = 'SocialLinkPluginConfig';

    public const PLUGIN_NAME = "Social Link Plugin";
    public const TABLE_NAME = "tac_socialSessions";
    public const SOURCES = array(
        "Bluesky",
        "Facebook",
        "Instagram"
    );
    private static $config_static = null;

    // Static version of 'getConfig' - allows access to plugin config
    // when $this isn't available (i.e. in endpoint.). Plugin isn't 
    // allowed multiple instances so this shouldn't cause problems.
    static function getConfigStatic(&$error=null)
    {
        if (self::$config_static === null)
        {
            $error = "Configuration not yet initialised: plugin not instanced.";
            return null;
        }
        return self::$config_static;

    }

    function isMultiInstance()
    {
        return false;
    }

    // -- Main Procedure -------------------------------------------------------
    //
    public function init()
    {
        SCHLORP("innit");
        self::registerEndpoint();
    }

    public function bootstrap()
    {
        try {
            self::$config_static = $this->getConfig();

            Signal::connect('threadentry.created', array($this, 'onNewEntry'));
            Signal::connect('cron', callable: array($this, 'cron'));
            Signal::connect('smm.instagram-webhook', array($this, 'requestSync'));

            $error = null;
            SocialLinkDB\initTable($error);
            if ($error !== null)
            {
                SCHLORP("Database initialisation failed: $error");
            }

        } catch (Exception $e) {
            SCHLORP(print_r($e, true));
        }

    }
    public function cron($data)
    {
        $this->requestSync($this, $data);
        $this->requestTokenRefresh($this, $data);
    }

    public static function registerEndpoint()
    {
        Signal::connect("api", function ($dispatcher) {
            $dispatcher->append(
                url_get("^/social-media-messenger/insta-webhook", function () {
                    self::webhookCallback();
                })
            );
        });
    }

    public static function webhookCallback()
    {
        $verify_token = self::$config_static->get("instagram-verify-webhook-token");

        $body = json_decode(file_get_contents("php://input"));

        $hub_verify_token = $_GET["hub_verify_token"];
        $hub_challenge = $_GET["hub_challenge"];
        $hub_mode = $_GET["hub_mode"];

        $result = strcmp($hub_verify_token, $verify_token);

        if ($result === 0 && $hub_verify_token !== null && $hub_verify_token !== "") {
            echo $hub_challenge;
        }
        else{
            SCHLORP("Failed to verify to challenge :(");
        }

        if (strcmp($body->object, "instagram") === 0) {
            Signal::send('smm.instagram-webhook', null, $body);
        } else {
            echo("<html><p>hewwo? that was not v meta of you<br /><br />Meow!<br /><br />|\---/|<br />| o_o |<br />&nbsp;\_^_/<br /></p></html>");
        }
    }

    // Pushes osTicket updates to social media platforms.
    // Called by threadentry.created signal. 
    public function onNewEntry($entry)
    {

        // Get associated ticket
        $ticketId = $entry->getThreadId();

        if ($ticketId === null){
            SCHLORP("TICKET ID IS NULL. WHY");
            return;
        }

        $error = null;
        $session = SocialLinkDB\getSocialSessionFromTicketId($ticketId, $error);
        if ($error !== null) {
            SCHLORP("it broke :( Error: \"$error\"");
            return;
        }
        
        if ($entry->getTypeName() === 'response'){
            $api_key = self::$config_static->get("instagram-api-key");
            $api = new InstagramAPI($api_key, $error);
            if ($error !== null){
                SCHLORP("Error initialising Instagram API: \"$error\"");
                return;
            }

            $created_time = $api->sendMessage($session->chat_id, strip_tags($entry->getBody()), $error);

            if ($error !== null) {
                SCHLORP("Error sending message: \"$error\"");
                return;
            }

            SocialLinkDB\updateEndTime($session, strtotime($created_time), $error);

            if ($error !== null) {
                SCHLORP("Error updating end time: \"$error\"");
                return;
            }

        }
    }

    private function addMessagesToTicket(Ticket $ticket, array $messages, &$error)
    {
        $string = join(array_map(fn ($m) => $m->encode(),$messages));
        // TODO: as far as i can tell this is sufficient for a successful post. will find out.
        $attachments = array_merge(...array_map(fn ($m) => $m->attachments, $messages));
        $message = $ticket->postMessage(
            array(
                "message" => $string,
                "type" => "text/html",
                "userId" => $ticket->getUserId(),
                "attachments" => $attachments
            ),
            "API",
        );
        if ($message === null)
        {
            $ticket_id = $ticket->getId();
            $error = "unable to post message to thread? ticket_id \"$ticket_id\"";
        }
    }

    private function newSession(
        SocialMediaConversation $conversation,
        array $messages,
        SocialLinkDB\Platform $platform,
        &$error=null)
    {
        $attachments = array_merge(...array_map(fn ($m) => $m->attachments, $messages));
        $email = "$conversation->id@$platform->name.void";
        $ticket_entry = array(
            "source" => "API",
            "source_extra" => $platform->name,
            "email" => $email,
            "name" => "$conversation->username",
            "subject" => $platform->name." ticket from ".$conversation->username,
            "message" => join(array_map(fn ($m) => $m->encode(),$messages)),
            "attachments" => $attachments,
            "type" => "text/html"
            );
        $errors = array();

        User::fromVars(array(
            "name" => $conversation->username,
            "email" => $email), true, true);

        $ticket = Ticket::create($ticket_entry, $errors, $ticket_entry["source"]);
        if (sizeof($errors) > 0)
        {
            $error = print_r($errors, true);
            return;
        }

        SocialLinkDB\insertSocialSession(new SocialLinkDB\SocialSession(
            $ticket->getId(),
            $conversation->user_id,
            $platform,
            $messages[0]->time,
            $messages[count($messages) - 1]->time,
        ), $error);
        $ticket->releaseLock();
    }

    private function updateSession(
        SocialLinkDB\SocialSession $most_recent_session,
        array $messages,
        &$error)
    {
        $ticket = Ticket::lookup($most_recent_session->ticket_id);

        if ($ticket === null)
        {
            $error = "couldn't find ticket \"$most_recent_session->ticket_id\"";
            return;
        }

        $this->addMessagesToTicket($ticket, $messages, $error);

        if ($error !== null)
            return;
        
        $end_time = $messages[count($messages) - 1]->time;
        SocialLinkDB\updateEndTime(
            $most_recent_session, $end_time, $error);
        $ticket->releaseLock();
    }

    // Pushes new social media messages to osTicket - either creating or updating threads.
    public function sync($object, $data)
    {
        // pull messages from social media and sync
        // TODO: do this for each platform.
        
        $api_key = self::$config_static->get("instagram-api-key");

        $error = null;
        $api = new InstagramAPI($api_key, $error);

        if ($error !== null) {
            SCHLORP("Failed to initialise Instagram API: \"".$error."\"");
            return;
        }

        $zero_hour = self::$config_static->get("zero-hour");

        $conversations = $api->getConversations($error);

        if ($error !== null) {
            SCHLORP("Failed to get conversations: \"$error\"");
            return;
        }

        foreach ($conversations as $conversation)
        {
            // reject the session if update time is before the 'zero hour'.
            // this avoids the issue where upon going live a new ticket is
            // made for EVERY SINGLE DIRECT MESSAGE WE HAVE EVER RECIEVED
            if($conversation->updated_time < $zero_hour)
            {
                SCHLORP("Rejecting message - updated time is before zero hour", SCHLORPNESS::DEBUG);
                continue;
            }

            $associated_sessions = SocialLinkDB\socialSessionsFromChatId($conversation->user_id, $error);

            if ($error !== null) {
                SCHLORP("Error fetching associated sessions: \"$error\".");
                return;
            }

            $most_recent_session = null;
            foreach ($associated_sessions as $session)
            {
                if ($most_recent_session === null)
                    $most_recent_session = $session;
                else if ($session->timestamp_end > $most_recent_session->timestamp_end)
                    $most_recent_session = $session;
            }

            // this will short circuit. no type worries.

            $first_session = $most_recent_session === null;
            if (!$first_session)
            {
                $ticket = Ticket::lookup($most_recent_session->ticket_id);
                $has_open_session = $ticket !== null && $ticket->isOpen();
            }
            $new_session = $first_session || !$has_open_session;

            // more short circuiting. This triggers if we found the session but it's up to date.
            //
            if (!$first_session &&
                $conversation->updated_time <= $most_recent_session->timestamp_end)
            {
                SCHLORP("Nothing to do!! continuing on", SCHLORPNESS::DEBUG);
                continue;
            }

            // if we get this far we have tickets to update/create.
            $update_since = $first_session ? $zero_hour : $most_recent_session->timestamp_end;

            $messages = $api->getMessages($conversation->id, $update_since, $error);
            if ($error !== null) {
                SCHLORP("Critical FAIL: \"$error\"", SCHLORPNESS::GUBBINS);
                return;
            }

            if($new_session)
                $this->newSession($conversation, $messages, SocialLinkDB\Platform::Instagram, $error);
            else
                $this->updateSession($most_recent_session, $messages, $error);

            if ($error !== null)
                SCHLORP("GOD FUCKING DAMN IT. I nearly had it there: \"$error\"");
        }

    }

    public function requestTokenRefresh($object, $data)
    {
        $last_sync = (int)self::$config_static->get("ig_last_token_refresh");
        $min_interval_days = (int)self::$config_static->get("instagram-refresh-access-token");
        $now = (int)Misc::dbtime();
        $nowDays = (int)($now / (60 * 60 * 24));
        $lastSyncDays = (int)($last_sync / (60 * 60 * 24));
        if (($now - $last_sync) > ($min_interval_days * 24 * 60 * 60))
        {
            self::$config_static->set("ig_last_token_refresh", $now);

            $api_key = self::$config_static->get("instagram-api-key");
            $error = null;
            $api = new InstagramAPI($api_key, $error);
            if ($error !== null)
            {
                SCHLORP("Error constructing instagram api: \"$error\"");
                return;
            }

            $expiry = $api->refreshAccessToken($error);
            if ($error !== null)
            {
                SCHLORP("Failed to refresh access token: \"$error\"");
                return;
            }

            SCHLORP("Instagram token refresh (last sync: \"$last_sync\", expires in: \"$expiry\")",
                SCHLORPNESS::INFO);
        }
    }

    public function requestSync($object, $data)
    {
        $last_sync = (int)self::$config_static->get("last_sync");
        $min_interval = (int)self::$config_static->get("min-sync-interval");
        $now = (int)Misc::dbtime();
        if ($now - $last_sync > $min_interval)
        {
            self::$config_static->set("last_sync", $now);
            $this->sync($object, $data);
        }
    }


    // -- Utility Functions ----------------------------------------------------


    public static function isTicketsView(): bool
    {
        $tickets_view = false;
        $url = $_SERVER['REQUEST_URI'];

        // Run through the most likely candidates first:
        // Ignore POST data, unless we're seeing a new ticket, then don't ignore.
        if (isset($_POST['a']) && $_POST['a'] == 'open') {
            $tickets_view = true;
        } elseif (!str_contains($url, '/scp/')) {
            // URL doesn't include /scp/ so isn't an agent page
            $tickets_view = false;
        } elseif (isset($_POST) && count($_POST)) {
            // If something has been POST'd to osTicket, assume we're not Viewing a ticket
            $tickets_view = false;
        } elseif (strpos($url, 'a=edit') || strpos($url, 'a=print')) {
            // URL contains a=edit or a=print, so assume we aren't needed here!
            $tickets_view = false;
        } elseif // URL contains a ticket ID and page is index.php or tickets.php, we are viewing a ticket
        (str_contains($url, 'id=') &&
          (str_contains($url, 'index.php') ||
            str_contains($url, 'tasks.php') || // Thanks to leandrovergara in pr#40 :)
            str_contains($url, 'tickets.php'))) {
            $tickets_view = true;
        } else {
            // Default
            $tickets_view = false;
        }


        return $tickets_view;
    }

    // Broken :( osTicket doesn't ever call it...
    public function pre_uninstall(&$errors)
    {
        // Note: THIS NEVER RUNS!!!!!
        $errors = array();
        global $ost;
        // Send an alert to the system admin:
        //$ost->alertAdmin(self::PLUGIN_NAME . ' has been uninstalled', "You wanted that right?", true);
        //$ost->logError(self::PLUGIN_NAME, "Plugin has been uninstalled!!!", false);

        $create_table_query = db_query("DROP TABLE IF EXISTS ".self::TABLE_NAME.";");

        if (!$create_table_query) {
            //error_log(self::PLUGIN_NAME . ": error creating table in database");
            return;
        }
        parent::uninstall($errors);
    }
}
?>
