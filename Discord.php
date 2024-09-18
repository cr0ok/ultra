<?php

class Webhook {
    // POST to /api/webhooks/:id/:token 
    // DELETE to /api/webhooks/:id/:token/messages/:messageid
    private $mStore = 'lastDiscordMessages.dat';
    private $mBaseUrl;
    private $mApi;
    private $mId;
    private $mToken;
    private $mUsername = 'Wonder Man';
    private $mLastMessages = []; // :id => :messageid
    public function __construct($url, $username = 'Wonder Man') {
        $this->setUrl($url);
        $this->setUsername($username);
        $this->loadLastMessages();
       
    }
    public function __destruct() {
        $this->saveLastMessages();
    }

    public function url() {
        return $this->mBaseUrl.'/'.$this->mApi.'/'.$this->mId.'/'.$this->mToken;
    }
    public function username() {
        return $this->mUsername;
    }
    public function setUsername($username) {
        $this->mUsername = $username;
    }
    public function setUrl($url) {
        $parsed = parse_url($url);
        $this->mBaseUrl = $parsed['scheme'].'://'.$parsed['host'];
        $pathParts = explode('/',$parsed['path']);
        $this->mApi = $pathParts[1].'/'.$pathParts[2];
        [$this->mId,$this->mToken] = array_slice($pathParts,-2);
    }
    public function deleteLast() {
        //delete previous message

        $deleteSuccess = false;

        if (isset($this->mLastMessages[$this->mId])) {
            $ch = curl_init($this->url().'/messages/'.$this->mLastMessages[$this->mId]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            $deleteResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
            if ($httpCode == 204) {
                $deleteSuccess = true;
            }
            curl_close($ch);
        }
    }
    public function post(array $content, $filepath = NULL, $mimeType = NULL, $filename = NULL) {
        $content = implode("\r\n",$content);

        $postFields = [
            'username' => $this->mUsername,
            'content' => $content
        ];
        if (!is_null($filepath) && !is_null($mimeType) && !is_null($filename)) {
            $postFields['file'] = curl_file_create($filepath,$mimeType,$filename);
        }

        $ch = curl_init( $this->url().'?wait=true' );
        curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-type: multipart/form-data'));
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

        $postResponse = json_decode(curl_exec( $ch ));
        curl_close( $ch );

        //set last message id
        if (isset($postResponse->id)) {
            $this->mLastMessages[$this->mId] = $postResponse->id;
        }

    }

    protected function loadLastMessages() {
        
        if (file_exists($this->mStore)) {
            $this->mLastMessages = unserialize(file_get_contents($this->mStore));
        }
    }
    protected function saveLastMessages() {
        file_put_contents($this->mStore,serialize($this->mLastMessages));
    }
}

class Discord {
    
}