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
        $time_formatted = date("Y-m-d H:i:s", $this->time);
        return "<div id='message' style='background-color: linear-gradient(90deg, #5BCEFA 0%, #5BCEFA 20%, #F5A9B8 20%, #F5A9B8 40%, #ffffff 40%, #ffffff 60%, #F5A9B8 60%, #F5A9B8 80%, #5BCEFA 80%, #5BCEFA 100%);'><b>$time_formatted</b>$this->content</div>";
    }
}
?>
