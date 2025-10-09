<?php
require_once INCLUDE_DIR . "class.format.php";

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

    public function post_request(string $endpoint, array $headers, string $body, array $params=null)
    {
        $url = $endpoint;
        if ($params != null)
        {
            $url = $endpoint."?".http_build_query($params);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
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

class SocialMediaMessage {
    public string $content;
    public array $attachments;
    public string $id;
    public int $time;
    private array $inlineImageIds;

    function __construct(string $id, int $time, string $content, array $attachments)
    {
        $this->id = $id;
        $this->time = $time;
        $this->content = $content;
        $this->inlineImageIds = array();
        $this->attachments = array();
        error_log(print_r($attachments, true));
        if ($attachments)
        {
            error_log("here!");
            $this->processAttachments($attachments);
        }
    }

    private function processAttachments(array $attachments)
    {
        foreach ($attachments as $attachment)
        {
            $context = stream_context_create([
                "http" => [
                    "method" => "GET",
                    "header" => "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:143.0) Gecko/20100101 Firefox/143.0"
                ]
            ]);
            $data = file_get_contents(
                $attachment->image_data->url,
                false,
                $context
            );
            error_log("\n\n");
            error_log("BEGIN IMAGE");
            error_log($data);
            error_log(base64_encode($data));
            error_log("\n\n");
            $file_info = new finfo(FILEINFO_MIME_TYPE);
            $mime = $file_info->buffer($data);
            $file = array(
                "type" => $mime,
                "name" => md5($attachment->image_data->url),
                "data" => $data 
            );
            $af = AttachmentFile::create($file);
            $inline = $mime === "image/jpeg";
            error_log("~~~~~~~~~~~~~~~~~~~~~~~~~~HI TRIN~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~");
            error_log(print_r($af->getId(), true));
            error_log("~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~");

            $this->attachments[$file['key']] = array(
                "id" => $af->getId(),
                "inline" => $inline,
                "file" => $af);
            if ($inline)
                array_push($this->inlineImageIds, $file['key']);
        }
    }

    public function encode()
    {
        $time_formatted = Format::datetime("Y-m-d H:i:s", $this->time);
        $imageString = join(array_map(fn ($m) => "<figure><img src='cid:$m' data-image='$m' alt='image'/></figure>",$this->inlineImageIds));
        return "<div style='margin: 1em;'> <div style='font-size: smaller'>$time_formatted</div> <div style='background-image:linear-gradient(0deg, #5BCEFA 0%, #5BCEFA 20%, #F5A9B8 20%, #F5A9B8 40%, #ffffff 40%, #ffffff 60%, #F5A9B8 60%, #F5A9B8 80%, #5BCEFA 80%, #5BCEFA 100%); width: fit-content;padding:0.5em; border-radius:1em 1em 1em 0em;'><div style='padding:0.5em;background-color:white; border-radius:0.5em 0.5em 0.5em 0em'>$imageString $this->content</div></div></div>";
    }
}

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
        $conversation_req = json_decode($this->get_request(
            self::BASE_URL."/".$conversation_id."/messages",
            $this->headers,
            array("fields" => "created_time,from,message,attachments", "limit" => "20")
        ));

        error_log(print_r($conversation_req, true));
        $messages = array();
        foreach ($conversation_req->data as $message)
        {
            $time = strtotime($message->created_time);
            $id = $message->id;

            if ($time <= $since)
                break;
            error_log("--------------------------------------------------------------------------------");

            error_log(print_r($message->attachments->data, true));
            error_log("--------------------------------------------------------------------------------");
            array_push($messages, new SocialMediaMessage(
                $id,
                $time,
                $message->message,
                $message->attachments->data ?? array()
            ));

        }

        return array_reverse($messages);
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
