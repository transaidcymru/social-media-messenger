<?php
class SocialMediaMessage {
    public string $content;
    public string $id;
    public int $time;

    function __construct(string $id, int $time, string $content)
    {
        $this->id = $id;
        $this->time = $time;
        $this->content = $content;
    }
}
?>
