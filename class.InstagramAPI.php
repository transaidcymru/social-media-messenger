<?php
require_once "class.SocialLinkAPI.php";

class InstagramAPI extends SocialLinkAPI {
    const BASE_URL = "https://graph.instagram.com/v23.0/";
    private string $api_key;

    function __construct(string $api_key) {
        $this->api_key = $api_key;
        
    }

    public function getConversations(&$error=null): array {
        return array();
    }

    public function sendMessage(string $conversation_id, string $message_content, &$error=null) {
        
    }
}

?>
