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
require_once 'class.InstagramAPI.php';

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
            $error = "configuration not yet initialised: plugin not instanced.";
            return;
        }
        return self::$config_static;

    }

    function isMultiInstance()
    {
        return false;
    }

    // -- Main Procedure -------------------------------------------------------
    public function bootstrap()
    {
        try {
            self::$config_static = $this->getConfig();

            #Signal::connect('threadentry.created', array($this, 'sync'));
            Signal::connect('cron', array($this, 'sync'));
            Signal::connect('smm.instagram-webhook', array($this, 'instagramWebhook'));
            Signal::connect('smm.sync', array($this, 'sync'));

            $error = null;
            SocialLinkDB\initTable($error);
            if ($error !== null)
            {
                $this->debug_log("Database initialisation failed: $error");
                return;
            }


            if (self::isTicketsView()) {
                ob_start();
                register_shutdown_function(
                    function () {
                        static::shutdownHandler($this);
                    }
                );
            }

        } catch (Exception $e) {
            error_log($e->getMessage());
        }

    }

    public static function shutdownHandler(self $plugin)
    {
        $html = ob_get_clean();
        $dom = $plugin->getDom($html);

        $ticket_id = $_GET['id'];
        $error = null;

        $is_social_link = SocialLinkDB\isSocialLinkTicket($ticket_id, $error);
        if ($error !== null)
        {
            error_log(self::PLUGIN_NAME.": database query failure: $error");
            return;
        }

        if (!$is_social_link)
        {
            print $html;
            return;
        }

        $script = $dom->createElement("script");
        $script->textContent =
            "alert(\"Kate and Trin were at this location. Also $ticket_id \");";


        $dom->appendChild($script);

        $new_html = $plugin->printDom($dom);
        print $new_html;
    }

    // Pushes osTicket updates to social media platforms.
    public function onThreadUpdate($entry, $data)
    {
        // Get associated ticket
        $ticket_id = $entry->getParent();

        $ticket = Ticket::lookup($ticket_id);
        if (!$ticket) {
            error_log(self::PLUGIN_NAME."NO ticket associated with $ticket_id");
            return;
        }

        // TODO: i think this is unnecessary - should be able to get source_extra from the ticket object.
        $source_extra_query = db_query(
            "SELECT source_extra from ".TICKET_TABLE." WHERE ticket_id=" . strval($ticket_id) . ";"
        );

        if (false === $source_extra_query) {
            error_log(self::PLUGIN_NAME . ": Database query failure.");
            return;
        }

        if ($source_extra_query->num_rows != 1) {
            error_log(self::PLUGIN_NAME . ": something went wrong here");
            return;
        }

        $source_extra = $source_extra_query->fetch_assoc()["source_extra"];

        // filter out thread updates we don't care about.
        if ($ticket->getSource() != "Other" &&
            !in_array($source_extra, self::SOURCES)) {
            return;
        }

        $session_table_query = db_query(
            "SELECT * from ".self::TABLE_NAME." where ticket-id=" . strval($ticket_id) . ";"
        );

        if (false === $session_table_query) {
            error_log(self::PLUGIN_NAME . ": Database query failure.");
            return;
        }

        if ($session_table_query->num_rows != 1) {
            error_log(self::PLUGIN_NAME . ": what the FUCK");
            return;
        }

        // get all of the info from the thread update
        $session_table_row = $session_table_query->fetch_assoc();

        // push to social media
        error_log(self::PLUGIN_NAME . ": ARRAY!!! " . json_encode($session_table_row));
    }

    private function addMessagesToTicket(Ticket $ticket, array $messages)
    {
        $string = join(array_map(fn ($m) => $m->encode(),$messages));
        // TODO: as far as i can tell this is sufficient for a successful post. will find out.
        $ticket->postMessage(
            array(
                "message" => $string
            )
        );
    }

    private function newSession(
        SocialMediaConversation $conversation,
        array $messages,
        SocialLinkDB\Platform $platform)
    {
        $ticket_entry = array(
                        "source" => "API",
                        "source_extra" => $platform->name,
                        "email" => "void@transaid.cymru",
                        "name" => $conversation->username);
        $errors = array();
        $ticket_id = Ticket::create($ticket_entry, $errors, $ticket_entry["source"]);
        error_log(print_r($errors, true));

        $this->addMessagesToTicket(Ticket::lookup($ticket_id), $messages);

        SocialLinkDB\insertSocialSession(new SocialLinkDB\SocialSession(
            $ticket_id,
            $conversation->id,
            $platform,
            $messages[count($messages) - 1]->time,
            $messages[0]->time
        ));
    }

    private function updateSession(
        SocialLinkDB\SocialSession $most_recent_session,
        SocialMediaConversation $conversation,
        array $messages)
    {
        $ticket = Ticket::lookup($most_recent_session->ticket_id);
        //get lock and release lock?
        // TODO: post messages
        $this->addMessagesToTicket($ticket, $messages);
        
        SocialLinkDB\updateEndTime($most_recent_session, $messages[0]->time);
    }

    // Pushes new social media messages to osTicket - either creating or updating threads.
    public function sync($object, $data)
    {
        // pull messages from social media and sync
        error_log("fetching");
        global $ost;

        // TODO: do this for each platform.
        
        $api_key = self::$config_static->get("instagram-api-key");
        error_log(print_r($api_key, true));

        $api = new InstagramAPI($api_key);
        $zero_hour = self::$config_static->get("zero-hour");

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
            $new_session = $most_recent_session === null
                || !Ticket::lookup($most_recent_session->ticket_id)->isOpen();

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
                $this->newSession($conversation, $messages, SocialLinkDB\Platform::Instagram);
            else
                $this->updateSession($most_recent_session, $conversation, $messages);
        }

    }

    public function instagramWebhook($object, $data)
    {
        ($object); // object is never used. - should always be null.

        // process webhook.
        error_log(print_r($data, true));
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

        if (true) {
            error_log("Matched $url as ".($tickets_view ? 'ticket' : 'not ticket'));
        }

        return $tickets_view;
    }


    public function pre_uninstall(&$errors)
    {
        // Note: THIS NEVER RUNS!!!!!
        $errors = array();
        global $ost;
        // Send an alert to the system admin:
        //$ost->alertAdmin(self::PLUGIN_NAME . ' has been uninstalled', "You wanted that right?", true);
        $ost->logError(self::PLUGIN_NAME, "Plugin has been uninstalled!!!", false);
        error_log("uninsalled!!!!");

        $create_table_query = db_query("DROP TABLE IF EXISTS ".self::TABLE_NAME.";");

        if (!$create_table_query) {
            error_log(self::PLUGIN_NAME . ": error creating table in database");
            return;
        }
        parent::uninstall($errors);
    }
    public function getDom($html = ''): DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->validateOnParse = true;
        $dom->resolveExternals = true;
        $dom->preserveWhiteSpace = false;
        // Turn off XML errors.. if only it was that easy right?
        $dom->strictErrorChecking = false;
        $xml_error_setting = libxml_use_internal_errors(true);

        // Because PJax isn't a full document, it kinda breaks DOMDocument
        // Which expects a full document! (You know with a DOCTYPE, <HTML> <BODY> etc.. )
        if (self::isPjax() &&
          (!str_starts_with($html, '<!DOCTYPE') || !str_starts_with($html, '<html'))) {
            // Prefix the non-doctyped html snippet with an xml prefix
            // This tricks DOMDocument into loading the HTML snippet
            $xml_prefix = '<?xml encoding="UTF-8" />';
            $html = $xml_prefix.$html;
        }

        // Convert the HTML into a DOMDocument, however, don't imply it's HTML, and don't insert a default Document Type Template
        // Note, we can't use the Options parameter until PHP 5.4 http://php.net/manual/en/domdocument.loadhtml.php
        if (!($loaded = $dom->loadHTML($html))) {
            $this->debug_log("There was a problem loading the DOM.");
        } else {
            $this->debug_log("%d chars of HTML was inserted into a DOM", strlen($html));
        }
        libxml_use_internal_errors($xml_error_setting); // restore xml parser error handlers
        $this->debug_log('DOM Loaded.');
        return $dom;
    }

    /**
     * Gets the DOM back as HTML
     *
     * @param  DOMDocument  $dom
     *
     * @return bool|string
     */
    public function printDom(DOMDocument $dom): bool|string
    {
        $this->debug_log("Converting the DOM back to HTML");
        // Check for failure to generate HTML
        // DOMDocument::saveHTML() returns null on error
        $new_html = $dom->saveHTML();

        // Remove the DOMDocument make-happy encoding prefix:
        if (self::isPjax()) {
            $remove_prefix_pattern = '@<\?xml encoding="UTF-8" />@';
            $new_html = preg_replace($remove_prefix_pattern, '', $new_html);
        }
        return $new_html;
    }
    private function debug_log($text, $_ = null): void
    {
        if (true) {
            $args = func_get_args();
            $text = array_shift($args);
            $this->log($text, $args); // send variable amount of args as array
        }
    }
    private function log(string $text, $_ = null): void
    {
        // Log to system, if available
        global $ost;

        $args = func_get_args();
        $format = array_shift($args);
        if (!$format) {
            return;
        }
        if (is_array($args[0])) {
            // handle debug_log's version or array of variables passed
            $text = vsprintf($format, $args[0]);
        } elseif (count($args)) {
            // handle normal variables as arguments
            $text = vsprintf($format, $args);
        } else {
            // no variables passed
            $text = $format;
        }

        if (!$ost instanceof osTicket) {
            // doh, can't log to the admin log without this object
            // setup a callback to do the logging afterwards:
            // save the log message in memory for now
            // the callback registered above will retrieve it and log it
            $this->messages[] = $text;
            error_log("DEBUG: Failed as ost is not an osTicket instance..: ".$text);
            return;
        }

        error_log(self::PLUGIN_NAME.": $text");
        $ost->logInfo(wordwrap($text, 30), $text, false);
    }

    public static function isPjax(): bool
    {
        return (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] == 'true');
    }
}
