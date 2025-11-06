CREATE TABLE `tac_socialSessions` (
    `session_id` int(11) unsigned not NULL auto_increment,
    `ticket_id` int(11) unsigned NOT NULL,
    `chat_id` varchar(100) NOT NULL,
    `platform` enum('Unknown','Facebook','Instagram','Bluesky') NOT NULL default 'Unknown',
    `timestamp_start` datetime NOT NULL,
    `timestamp_end` datetime NOT NULL,
    `last_sent_message_id` varchar(100) NOT NULL,
    `session_type` varchar(30), PRIMARY KEY  (`session_id`)) DEFAULT CHARSET=utf8;
