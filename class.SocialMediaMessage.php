<?php
class SocialMediaMessage {
    public string $content;
    public string $message_id;
    public int $timestamp;

    function __construct(string $message_id, int $timestamp, string $content)
    {
        $this->message_id = $message_id;
        $this->timestamp = $timestamp;
        $this->content = $content;
        
    }
}
?>
