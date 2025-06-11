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

//require_once 'class.SocialLinkAPI.php';
//require_once 'class.SocialLinkFetcher.php';
require_once 'config.php';
require_once 'mysqli.php';

class SocialLinkPlugin extends Plugin {
    var $config_class = 'SocialLinkPluginConfig'; 

    var $dummy_api;
    var $dummy;

    const PLUGIN_NAME = "Social Link Plugin";
    const TABLE_NAME = "tac_socialSessions";
    const SOURCES = array(
        "Bluesky",
        "Facebook",
        "Instagram"
    );

    public function bootstrap() {
        Signal::connect('threadentry.created', array($this, 'sync'));
        Signal::connect('cron', array($this, 'sync'));
        try {
            $test_query = db_query("SHOW tables LIKE '".self::TABLE_NAME."';");

            if (false === $test_query)
            {
                error_log(self::PLUGIN_NAME.": Error querying database");
                return;
            }
            if (true === $test_query)
            {
                error_log(self::PLUGIN_NAME.": unexpected query result");
                return;
            }

            if (!($test_query->num_rows > 0))
            {
                // todo: fix this shit
                $create_table_query = db_query("CREATE TABLE `tac_socialSessions` ( `session_id` int(11) unsigned not NULL auto_increment, `ticket_id` int(11) unsigned NOT NULL, `chat_id` varchar(100) NOT NULL, `platform` enum('Unknown','Facebook','Instagram','Bluesky') NOT NULL default 'Unknown', `timestamp_start` datetime NOT NULL, `timestamp_end` datetime NOT NULL, `session_type` varchar(30), PRIMARY KEY  (`session_id`)) DEFAULT CHARSET=utf8;");

                if (!$create_table_query)
                {
                    error_log(self::PLUGIN_NAME . ": error creating table in database");
                    return;
                }
            }
            
            if (self::isTicketsView())
            {
                ob_start();
                register_shutdown_function(
                    function () {
                        static::shutdownHandler($this);
                    }
                );
            }

        }
        catch(Exception $e) {
            error_log("shit");
        }

    }

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
        } elseif
            // URL contains a ticket ID and page is index.php or tickets.php, we are viewing a ticket
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

    public static function shutdownHandler(self $plugin)
    {
        $html = ob_get_clean();
        $dom = $plugin->getDom($html);

        // edit
        $ticket_id = $_SERVER['id'];
        $script = $dom->createElement("script");
        $script->textContent = 
            "alert(\"Kate and Trin were at this location. Also $ticket_id\");";
        $dom->appendChild($script);

        $new_html = $plugin->printDom($dom);
        print $new_html;
    } 
    

    public function onThreadUpdate($entry, $data) {
        // Get associated ticket
        $ticket_id = $entry->getParent();

        $ticket = Ticket::lookup($ticket_id);
        if(!$ticket)
        {
            error_log(self::PLUGIN_NAME."NO ticket associated with $ticket_id");
            return;
        }

        $source_extra_query = db_query(
            "SELECT source_extra from ".TICKET_TABLE." WHERE ticket_id=" . strval($ticket_id) . ";");

        if(false === $source_extra_query)
        {
            error_log(self::PLUGIN_NAME . ": Database query failure.");
            return;
        }

        if($source_extra_query->num_rows != 1)
        {
            error_log(self::PLUGIN_NAME . ": something went wrong here");
            return;
        }

        $source_extra = $source_extra_query->fetch_assoc()["source_extra"];

        // filter out thread updates we don't care about.
        if ($ticket->getSource() != "Other" &&
            !in_array($source_extra, self::SOURCES))
            return;

        $session_table_query = db_query(
            "SELECT * from ".self::TABLE_NAME." where ticket-id=" . strval($ticket_id) . ";");

        if(false === $session_table_query)
        {
            error_log(self::PLUGIN_NAME . ": Database query failure.");
            return;
        }

        if($session_table_query->num_rows != 1)
        {
            error_log(self::PLUGIN_NAME . ": what the FUCK");
            return;
        }

        // get all of the info from the thread update
        $session_table_row = $session_table_query->fetch_assoc();

        // push to social media
        error_log(self::PLUGIN_NAME . ": ARRAY!!! " . json_encode($session_table_row));
        
    }

    public function sync($object, $data) {
        // pull messages from social media and sync
        error_log("fetching");
        global $ost;

        // Incoming dummy message
        $incoming = $this->fetch_messages();

        if (!$incoming){
            return;
        }

        // Seach DB for matching chat id sessions
        $sessions = db_query("SELECT * from tac_socialSessions WHERE chat_id='" . $incoming["chat_id"] . "';");

        // Error checking
        $ticket = null;
        $session = null;
        if (false === $sessions)
        {
            error_log(": Error querying database");
        }
        else if (true === $sessions)
        {
            error_log(": unexpected query result");
        }
        else if (!($sessions->num_rows > 0))
        {
            // Check existing sessions for open ticket
            $bestMatch = null;
            while ($found = $sessions->fetch_assoc())
            {
                $ticket = Ticket::lookup($found["ticket_id"]);
                if ($ticket && $ticket->isOpen() && (!$bestMatch || $ticket->getCreateDate() > $bestMatch->getCreateDate())){
                    $session = $found;
                    $bestMatch = $ticket;
                }
            }
        }
        if (!$session) {
            // TODO: Email is REQUIRED to avoid errors; let's use a dummy email
            $ticket_entry = array("source"=>"API", "source_extra"=>$incoming["platform"], "email"=>"void@transaid.cymru", "name"=>"void");

            // TODO: Manually set source_extra in DB if need be
            $errors = array();
            $ticket = Ticket::create($ticket_entry, $errors, $ticket_entry["source"]);

            $msg = array(
                "ticket_id"=>$ticket->getId(),
                "chat_id"=>"1",
                "platform"=>"Facebook",
                "timestamp_start"=>"1970-01-01 00:00:01",
                "timestamp_end"=>"1970-01-01 00:00:05"
            );
            
            db_query(
                "INSERT INTO tac_socialSessions (ticket_id, chat_id, platform, timestamp_start, timestamp_end)
    VALUES (".$msg["ticket_id"].", ".$msg["chat_id"].", '".$msg["platform"]."', '".$msg["timestamp_start"]."', '".$msg["timestamp_end"]."');");

        }

    }

    public function fetch_messages()
    {
        $query = db_query("SELECT * from tac_socialSessions;");
        
        if (false === $query)
        {
            error_log("malformed query");
        }
        else if ($query->num_rows === 0)
        {
            return array("chat_id" => "1", "platform" => "Facebook", "start" => "1970-01-01 00:00:01", "end" => "1970-01-01 00:00:05");
        }
        return null;
    }

    function pre_uninstall(&$errors) {
        // Note: THIS NEVER RUNS!!!!!
        $errors = array();
        global $ost;
        // Send an alert to the system admin:
        //$ost->alertAdmin(self::PLUGIN_NAME . ' has been uninstalled', "You wanted that right?", true);
        $ost->logError(self::PLUGIN_NAME, "Plugin has been uninstalled!!!", false);
        error_log("uninsalled!!!!");

        $create_table_query = db_query("DROP TABLE IF EXISTS ".self::TABLE_NAME.";");

        if (!$create_table_query)
        {
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

            error_log("AttachmentPreviewPlugin: $text");
            $ost->logInfo(wordwrap($text, 30), $text, false);
        }

        public static function isPjax(): bool
        {
            return (isset($_SERVER['HTTP_X_PJAX']) && $_SERVER['HTTP_X_PJAX'] == 'true');
        }
}
?>
