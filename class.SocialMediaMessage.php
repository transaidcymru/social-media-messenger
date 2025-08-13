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

    public function encode()
    {
        // todo: gaping security flaw.
        $time_formatted = date("Y-m-d H:i:s", $this->time);
        return "<div id='message'> <script>alert('hehehe');</script><b>$time_formatted</b> $this->content</div>";
    }
}
?>
