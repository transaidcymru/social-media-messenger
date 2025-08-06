<?php
require_once "class.SocialLinkAPI.php";
require_once "class.SocialMediaConversation.php";
require_once "class.SocialMediaMessage.php";

class InstagramAPI extends SocialLinkAPI {
    const BASE_URL = "https://graph.instagram.com/v23.0/";
    private string $api_key;
    private string $my_id;
    private array $headers;

    function __construct(string $api_key) {
        try {
            $this->api_key = $api_key;
            $this->headers = [
                "Authorization: Bearer $this->api_key",
                "Content-Type: application/json"
            ];
            $this->my_id = $this->getOwnID();
        } catch(Exception $e) {
            error_log("shopp");
        }
        
    }

    public function getOwnID(&$error=null): string {
        $me_request = json_decode($this->get_request(
            self::BASE_URL."/me",
            $this->headers,
            array("fields" => "id")));
        return $me_request->id;
    }

    public function getConversations(&$error=null): array {

        try {
            $conversations_req = json_decode($this->get_request(
                self::BASE_URL."/me/conversations",
                $this->headers,
                array("fields" => "messages")));

            
            $ret = array();
            foreach ($conversations_req->data as $conversation)
            {
                array_push(
                    $ret,
                    new SocialMediaConversation(
                        $conversation->id,
                        strtotime($conversation->update_time)));
            }

            return $ret;
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
        return array();
    }
    public function getMessages(string $conversation_id, int $since) {
        try {
            $message_ids = array();
            $more_messages = true;
            $endpoint = self::BASE_URL."/".$conversation_id;
            while ($more_messages)
            {
                $conversation_req = json_decode($this->get_request(
                    $endpoint,
                    $this->headers,
                    array("fields" => "messages")
                ));

                foreach ($conversation_req->messages->data as $message)
                {
                    $time = strtotime($message->created_time);
                    $id = $message->id;

                    if ($time > $since)
                    {
                        $more_messages = false;
                        break;
                    }

                    array_push($message_ids, $id);
                }
                $endpoint = $conversation_req->messages->paging->next;
            }

            $ret = array();
            foreach($message_ids as $id)
            {
                $message_req = json_decode($this->get_request(
                    self::BASE_URL."/".$id,
                    $this->headers,
                    array("fields" => "created_time,from,message")
                ));

                if ($message_req->from->id !== $this->my_id)
                {
                    array_push($ret, new SocialMediaMessage(
                        $id,
                        $message_req->created_time,
                        $message->message
                    ));
                }
            }
            return $ret;
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
        return array();
    }

    public function sendMessage(string $conversation_id, string $message_content, &$error=null) {
        
    }
}

?>
