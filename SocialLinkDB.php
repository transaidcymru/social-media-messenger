<?php
namespace SocialLinkDB;

if(!defined("TEST_ENV"))
    require_once 'mysqli.php';
else
    require_once "tests/mysqli_test.php";

const TABLE_NAME = "tac_socialSessions";

enum Platform {
    case Unknown;
    case Facebook;
    case Instagram;
    case Bluesky;

    // https://stackoverflow.com/questions/71002391/get-enum-value-by-name-stored-in-a-string-in-php
    static function fromName(string $name): Platform
    {
        foreach (self::cases() as $status) {
            if( $name === $status->name ){
                return $status;
            }
        }
        throw new \ValueError("$name is not a valid backing value for enum " . self::class );
    }
}

class SocialSession {
    public int $session_id;
    public int $ticket_id;
    public string $chat_id;
    public Platform $platform;
    public int $timestamp_start;
    public int $timestamp_end;
    public string $session_type;

    function __construct(
        int $ticket_id,
        string $chat_id,
        Platform $platform,
        int $timestamp_start,
        int $timestamp_end,
        int $session_id=-1, // don't like this
        string $session_type="")
    {
        $this->session_id = $session_id;
        $this->ticket_id = $ticket_id;
        $this->chat_id = $chat_id;
        $this->platform = $platform;
        $this->timestamp_start = $timestamp_start;
        $this->timestamp_end = $timestamp_end;
        $this->session_type = $session_type;
    }

}


// Query that can return a result.
function selectionQuery(string $query, &$error = null)
{
    $q = db_query($query);
    if (false === $q)
    {
        $error = "Error querying database.";
        return;
    }
    if (true === $q)
    {
        $error = "Unexpected query result.";
        return;
    }
    return $q;
}

function insertionQuery(string $query, &$error = null): bool
{
    return true;
}

// Creates table if it doesn't exist. Otherwise does nothing.
function initTable(&$error = null)
{
    $q = selectionQuery("SHOW TABLES LIKE '".TABLE_NAME."';", $error);
    if ($error !== null)
        return;

    if (!($q->num_rows > 0))
    {
        // table doesn't exist, create...
        
        // TODO: might not work.
        $sql = file_get_contents(__DIR__.'/install.sql');
        $create_table_q = db_query($sql);

        if (!$create_table_q)
        {
            $error = "Error creating table in database.";
        }
    }
}

function getSocialSessionFromTicketId(int $ticket_id, &$error=null): SocialSession | null
{
    $q = selectionQuery(
        "SELECT * from ".TABLE_NAME." WHERE ticket_id=".strval($ticket_id).";",
        $error
    );

    if ($error !== null)
        return null;

    if ($q->num_rows > 1)
    {
        $error = "Database corrupt: more than one SocialLink associated with given ticket id.";
        return null;
    }

    if ($q->num_rows === 1){
        $row = $q->fetch_assoc();
        
        return new SocialSession(
            $row["ticket_id"],
            $row["chat_id"],
            Platform::fromName(name: $row["platform"]),
            strtotime($row["timestamp_start"]),
            strtotime($row["timestamp_end"]),
            $row["session_id"],
            session_type: $row["session_type"] ?? ""
        );
    }
    else{
        error_log(message: "getSocialSessionFromTicketId call => \"ticketId=".strval($ticket_id))."\" returned 0 rows!";
    }

    return null;
}

function socialSessionsFromChatId(string $chat_id, &$error=null): array
{
    $q = selectionQuery(
        "SELECT * FROM ".TABLE_NAME." WHERE chat_id='$chat_id';",
        $error
    );

    if($error !== null)
        return array();


    $rows = $q->fetch_all(MYSQLI_ASSOC); 
    $ret = array();
    foreach ($rows as $row)
    {
        array_push($ret,
            new SocialSession(
				$row["ticket_id"],
				$row["chat_id"],
				Platform::fromName($row["platform"]),
				strtotime($row["timestamp_start"]),
				strtotime($row["timestamp_end"]),
                $row["session_id"],
				$row["session_type"] ?? "")
        );
    }
    return $ret;
}

function insertSocialSession(SocialSession $session, &$error=null)
{
    db_query("INSERT INTO " . TABLE_NAME
        . " (ticket_id, chat_id, platform, timestamp_start, timestamp_end)"
        . " VALUES ("
        .$session->ticket_id.", '"
        .$session->chat_id."', '"
        .$session->platform->name."', '"
        .date("Y-m-d H:i:s", $session->timestamp_start)."', '"
        .date("Y-m-d H:i:s", $session->timestamp_end)."');");
}

function updateEndTime(SocialSession $session, int $end_time)
{
    db_query("UPDATE " . TABLE_NAME
        . " SET timestamp_end='" . date("Y-m-d H:i:s", $end_time)
        . "' WHERE session_id=" . $session->session_id . ";");
}

?>
