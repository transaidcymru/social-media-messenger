CREATE TABLE `tac_socialSessions` (
 `session_id` int(11) unsigned not NULL auto_increment,
 `ticket_id` int(11) unsigned NOT NULL,
 `chat_id` varchar(100) NOT NULL,
 `platform` enum('Unknown','Facebook','Instagram','Bluesky') NOT NULL default
   'Unknown',
 `timestamp_start` datetime NOT NULL,
 `timestamp_end` datetime NOT NULL,
 `session_type` varchar(30),

  PRIMARY KEY  (`session_id`)

) DEFAULT CHARSET=utf8;


SELECT * from tac_socialSessions;

INSERT INTO tac_socialSessions (ticket_id, chat_id, platform, timestamp_start, timestamp_end)
VALUES (123, 123, 'Facebook', '1970-01-01 00:00:01', '1970-01-01 00:00:05');

INSERT INTO tac_socialSessions (ticket_id, chat_id, platform, timestamp_start, timestamp_end, session_type)
VALUES (312, 312, 'Bluesky', '1970-01-01 00:00:05', '1970-01-01 00:00:09', 'helo');

SELECT * from tac_socialSessions;
