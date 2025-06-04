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

// Handles fetching of msg from social media. Use different APIs for handling different social media platforms.
class SocialLinkFetcher {
    // Logged in social media API associated with this fetcher
    private $social_api;

    // Tickets API
    private $ticket_api;
    private $config;

    function __construct(\SocialLinkAPI $social_api, $config, $charset='UTF-8') {
        $this->social_api = $social_api;
        $this->config = $config;
    }

    function getMaxFetch() {
        return $this->social_api->getMaxFetch();
    }

    function getTicketsApi() {
        // We're forcing CLI interface - this is absolutely necessary since
        // Email Fetching is considered a CLI operation regardless of how
        // it's triggered (cron job / task or autocron)

        // Please note that PHP_SAPI cannot be trusted for installations
        // using php-fpm or php-cgi binaries for php CLI executable.

        if (!isset($this->ticket_api))
            $this->ticket_api = new \TicketApiController('cli');

        return $this->ticket_api;
    }

    function processMessage(int $i, array $defaults = []) {
        try {
            // Please note that the returned object could be anything from
            // ticket, task to thread entry or a boolean.
            // Don't let TicketApi call fool you!
            return $this->getTicketsApi()->processEmail(
                    $this->mbox->getRawEmail($i), $defaults);
        } catch (\TicketDenied $ex) {
            // If a ticket is denied we're going to report it as processed
            // so it can be moved out of the Fetch Folder or Deleted based
            // on the MailBox settings.
            return true;
        } catch (\EmailParseError $ex) {
            // Upstream we try to create a ticket on email parse error - if
            // it fails then that means we have invalid headers.
            // For Debug purposes log the parse error + headers as a warning
            $this->logWarning(sprintf("%s\n\n%s",
                        $ex->getMessage(),
                        $this->mbox->getRawHeader($i)));
        }
        return false;
    }

    function processmsg() {
        // We need a connection
        if (!$this->social_api)
            return false;

        // Get basic fetch settings
        $max = $this->getMaxFetch() ?: 30; // default to 30 if not set

        return $msgs;
    }

    private function logDebug($msg) {
        $this->log($msg, LOG_DEBUG);
    }

    private function logWarning($msg) {
        $this->log($msg, LOG_WARN);
    }

    private function log($msg, $level = LOG_WARN) {
        global $ost;
        $subj = _S('Social Link Fetcher');
        switch ($level) {
            case LOG_WARN:
                $ost->logWarning($subj, $msg);
                break;
            case  LOG_DEBUG:
            default:
                $ost->logDebug($subj, $msg);
        }
    }

    public function fetch(){

        $query = db_query("SELECT * from tac_socialSessions;");
        
        if (false === $query)
        {
            error_log("malformed query");
        }
        else if ($this->config->get("boop") === "boop" && $query->num_rows === 0)
        {
            return array("chat_id" => "1", "platform" => "Facebook", "start" => "1970-01-01 00:00:01", "end" => "1970-01-01 00:00:05");
        }
        return null;
    }

    /*
       MailFetcher::run()

       Static function called to initiate email polling
     */
    public function run() {
        global $ost;

        if(!$ost->getConfig()->isEmailPollingEnabled())
            return;

        // Incoming dummy message
        $incoming = $this->fetch();

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
            
            error_log(
                "INSERT INTO tac_socialSessions (ticket_id, chat_id, platform, timestamp_start, timestamp_end)
    VALUES (".$msg["ticket_id"].", ".$msg["chat_id"].", '".$msg["platform"]."', '".$msg["timestamp_start"]."', '".$msg["timestamp_end"]."');");

        }

        // Check for new msg from chats        
    }
}

?>
