<?php
require_once 'class.SocialLinkAPI.php';
//require_once "class.SocialMediaConversation.php";
//require_once "class.SocialMediaMessage.php";

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
        error_log(print_r($me_request, true));
        return $me_request->id;
    }

    public function getConversations(&$error=null): array {
        $conversations_req = json_decode($this->get_request(
            self::BASE_URL."/me/conversations",
            $this->headers,
            array("fields" => "participants,updated_time")
            ));

        $ret = array();
        foreach ($conversations_req->data as $conversation)
        {
            // if it's 1, you're talking to yourself.
            // if it's >2 you're in a group chat. we should think about what to do then.
            if (count($conversation->participants->data) !== 2)
                continue;               

            array_push(
                $ret,
                new SocialMediaConversation(
                    $conversation->id,
                    $conversation->participants->data[1]->id,
                    $conversation->participants->data[1]->username,
                    strtotime($conversation->updated_time)));
        }

        return $ret;
    }
    public function getMessages(string $conversation_id, int $since) {
        $message_ids = array();
        $conversation_req = json_decode($this->get_request(
            self::BASE_URL."/".$conversation_id,
            $this->headers,
            array("fields" => "messages", "limit" => "20")
        ));

        foreach ($conversation_req->messages->data as $message)
        {
            $time = strtotime($message->created_time);
            $id = $message->id;

            if ($time <= $since)
                break;

            array_push($message_ids, $id);
        }

        // TODO: parallelize
        // https://danielrotter.at/2025/04/12/batch-curl-requests-in-php-using-multi-handles.html
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
                    strtotime($message_req->created_time),
                    $message_req->message
                ));
            }
        }
        return array_reverse($ret);
    }

    public function sendMessage(string $dest_user_id, string $message_content, &$error=null) {
        // Setup headers
        $headers = [
            "Authorization: Bearer ".$this->api_key,
            "Content-Type: application/json",
        ];

        // Make the request to the Instagram API with message content
        $response = json_decode($this->post_request(
            self::BASE_URL."me/messages",
            $headers,
            json_encode(array(
                "message" => array("text" => $message_content),
                "recipient" => array("id" => $dest_user_id)
            ))
        ));

        // TODO error handling
        if ($response === null){
            // help
        }
        else{
            $message_req = json_decode($this->get_request(
                self::BASE_URL."/".$response->message_id,
                $this->headers,
                array("fields" => "created_time")
            ));

            // help more error handling pls TODO
            if ($message_req === null){

            }else{
                // TODO: Flag for sync update if request is successful
                return $message_req->created_time;
            }
        }

        return $response;
    }
}

?>
