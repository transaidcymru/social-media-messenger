<?php
namespace SocialLinkDB;

require_once 'mysqli.php';

const TABLE_NAME = "tac_socialSessions";

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

        if(!$create_table_q)
        {
            $error = "Error creating table in database.";
            return;
        }
    }

    return;
}

function isSocialLinkTicket($ticket_id, &$error=null): bool
{
    $q = selectionQuery(
        "SELECT * from ".TABLE_NAME." WHERE ticket_id=".strval($ticket_id).";",
        $error
    );
    if ($error !== null)
        return false;

    if ($q->num_rows > 1)
    {
        $error = "Database corrupt: more than one SocialLink associated with given ticket id.";
        return false;
    }

    return $q->num_rows === 1;
}

?>
