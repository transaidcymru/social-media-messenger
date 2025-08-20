<?php

// Base class for social media API implementations (e.g. Facebook, BlueSky etc.).
class SocialLinkAPI {

    public function getConversations(&$error=null): array {
        return array();
    }

    public function get_request(string $endpoint, array $headers, array $params=null)
    {
        $url = $endpoint;
        if ($params != null)
        {
            $url = $endpoint."?".http_build_query($params);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function post_request(string $endpoint, array $headers, array $params=null)
    {
        $url = $endpoint;
        if ($params != null)
        {
            $url = $endpoint."?".http_build_query($params);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function sendMessage(string $conversation_id, string $message_content, &$error=null) {
        
    }
}

?>

