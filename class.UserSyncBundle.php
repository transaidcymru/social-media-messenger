<?php
require_once "class.SocialMediaMessage.php";
class UserSyncBundle {
    public string $user_id;
    public array $messages;

    function __construct(string $user_id, array $messages)
    {
        $this->user_id = $user_id;
        $this->messages = $messages;
    }
}
?>
