<?php
class SocialMediaConversation {
    public string $id;
    public string $user_id;
    public string $username;
    public int $updated_time;

    function __construct(string $id, string $user_id, string $username, int $updated_time)
    {
        $this->id = $id;
        $this->updated_time = $updated_time;
        $this->user_id = $user_id;
        $this->username = $username;
    }
}
?>
