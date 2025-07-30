<?php

// Base class for social media API implementations (e.g. Facebook, BlueSky etc.).
class SocialLinkAPI {

    public function getConversations(&$error=null): array {
        return array();
    }

    public function sendMessage(string $conversation_id, string $message_content, &$error=null) {
        
    }
}

?>

