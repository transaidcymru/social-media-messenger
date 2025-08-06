<?php
class SocialMediaConversation {
    public string $id;
    public int $updated_time;

    function __construct(string $id, int $updated_time)
    {
        $this->id = $id;
        $this->updated_time = $updated_time;
    }
}
?>
