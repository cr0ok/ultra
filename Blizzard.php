<?php

class Blizzard {
    private static $mInstance = NULL;
    private $mClientId = '';
    private $mClientSecret = '';
    private $mRegion = '';
    private $mServerTimeZone = '';
    private $mLocale = 'en_US';

    
    private $mAuthUri = 'https://oauth.battle.net/authorize'; //not used this class
    private $mTokenUri = 'https://oauth.battle.net/token';
    private $mAPI = 'api.blizzard.com';
    private $mGameVariant = '-';

    private $mCacheDir = "cache";
    private $mCacheDuration = 3600; //1 hour
    
    private $mToken = NULL;

    private $mPlayableClasses = array();
    private $mPlayableRaces = array();
       
        
    //PRIVATE
    private function __construct($region = '',$timeZone = '', $gameVariant = '', $clientId = '', $clientSecret = '') {
        if (!empty($region)) $this->mRegion = strtolower($region);
        if (!empty($timeZone)) $this->mServerTimeZone = $timeZone;
        if (!empty($gameVariant)) $this->mGameVariant = "-".$gameVariant."-";
        if (!empty($clientId)) $this->mClientId = $clientId;
        if (!empty($clientSecret)) $this->mClientSecret = $clientSecret;
        //get token
        $this->mToken = $this->getToken();
        if (empty($this->mToken)) {
            die("Error:  Unable to fetch OAuth2.0 token.\n");
        }
        //fetch game data
        $this->fetchGameData();
        
    }

    //PROTECTED
    
      
    protected function fetchFromCache ($request,$cacheDuration) {
        $ret = false;
        $cachedFilepath = $this->mCacheDir."/".md5($request);
        $cachedFileExists = file_exists($cachedFilepath);
        if ($cachedFileExists 
            && (time() - filemtime($cachedFilepath)) < $cacheDuration) {
            //use cached copy
            $ret = unserialize(@file_get_contents($cachedFilepath));
        } else if ($cachedFileExists) {
            //prune old cached file
            unlink($cachedFilepath);
        }
        return $ret;
    }
    
    protected function storeToCache($request,$content) {
        if (!file_exists($this->mCacheDir)) {
            mkdir($this->mCacheDir);
        }
        $cachedFilepath = $this->mCacheDir."/".md5($request);
        file_put_contents($cachedFilepath,serialize($content));
    }
    
    protected function getToken() {
        //fetch from cache first
        echo "Fetching OAuth token.\n";
        $ret = $this->fetchFromCache($this->mTokenUri,86400);
        if (!$ret) {
            //make new request
            $ch = curl_init($this->mTokenUri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "$this->mClientId:$this->mClientSecret");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(["grant_type" => "client_credentials"]));
            $ret = json_decode(curl_exec($ch));
            curl_close($ch);
            //cache result
            if (!empty($ret)) {
                $this->storeToCache($this->mTokenUri,$ret);
            }
        }
        return $ret;
    }

    protected function namespaceSlug($namespace) {
        return $namespace.$this->mGameVariant.$this->mRegion;
    }

    protected function buildQuery($endpoint,$namespace,$params = array()) {
        $ret = "https://".$this->mRegion.".".$this->mAPI.$endpoint
            ."?namespace=".$this->namespaceSlug($namespace)."&locale=".$this->mLocale;
        if (!empty($params)) {
            $ret .= "&" . http_build_query($params);
        }
        return $ret;
    }

    protected function queryAPI($query,$storeToCache = true, $cacheDuration = 14400) {
        $ret = false;
        //try fetching from cache first
        $ret = $this->fetchFromCache($query,$cacheDuration);
        if (!$ret) {
            //make new request

            $ch = curl_init($query);
            $auth = 'Authorization: '.$this->mToken->token_type.' '.$this->mToken->access_token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', $auth]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);            
            $ret = json_decode(curl_exec($ch));
            curl_close($ch);
            //cache result
            if (!empty($ret) && $storeToCache) {
                $this->storeToCache($query,$ret);
            } 
        }
        return $ret;
    }
    
    protected function slug($str) {
        return rawurlencode(str_replace([' ', "'"], ['-', ''], mb_strtolower($str)));
    }

    protected function fetchGameData() {
        $r = $this->queryAPI($this->buildQuery('/data/wow/playable-class/index','static'));
        foreach ($r->classes as $class) {
            $this->mPlayableClasses[$class->id] = $class->name;
        }
        $r = $this->queryAPI($this->buildQuery('/data/wow/playable-race/index','static'));
        foreach ($r->races as $race) {
            $this->mPlayableRaces[$race->id] = $race->name;
        }
    }
    
   
    //PUBLIC
    
    public static function getInstance($region='', $timeZone = '', $gameVariant = '', $clientId='',$clientSecret='') {
        if (self::$mInstance == NULL) {
            self::$mInstance = new Blizzard($region,$timeZone,$gameVariant,$clientId,$clientSecret);
        }
        return self::$mInstance;
    }

    public function relatedRealms($anchorRealm) {
        $ret = array();
        $namespace = 'dynamic';
        $params = [
            'realms.timezone' => $this->mServerTimeZone,
            'locale' => $this->mLocale
        ];
        $q = $this->buildQuery('/data/wow/search/connected-realm',$namespace,$params);

        $r = $this->queryAPI($q);

        $relatedIndex = 0;

        foreach ($r->results as $index => $result) {
            
            foreach ($result->data->realms as $realm) {
                if ($realm->name->{$this->mLocale} == $anchorRealm) {
                    $relatedIndex = $index;
                    break;
                }
            }
               
        }

        $ret = array();
        foreach ($r->results[$relatedIndex]->data->realms as $realm) {
            array_push($ret,$realm->name->{$this->mLocale});
        }
        return $ret;
    }
    
    public function findCharacters($characterName,$faction,$class,$reachableFromRealm) {
        $relatedRealms = $this->relatedRealms($reachableFromRealm);

        $ret = array();
        foreach ($relatedRealms as $realm) {
            
            //query $data->realm->slug
            $p = $this->profileSummary($characterName,$realm);
            if (isset($p->id) 
                && $p->faction->name == $faction
                && $p->character_class->name == $class) {
                //found a match    
                array_push($ret,$p);
            }
        }
        return $ret;

    }

    public function playableClassById($id) {
        return $this->mPlayableClasses[$id];
    }
    public function playableRaceById($id) {
        return $this->mPlayableRaces[$id];
    }

    public function profileSummary($characterName,$characterRealm) {
        $namespace = 'profile';
        $nameSlug = $this->slug($characterName);
        $realmSlug = $this->slug($characterRealm);
        $endpoint = "/profile/wow/character/$realmSlug/$nameSlug";
        $query = $this->buildQuery($endpoint,$namespace);
        return $this->queryAPI($query,$namespace);
    }

    public function profileStatus($characterName,$characterRealm) {
        $namespace = 'profile';
        $nameSlug = $this->slug($characterName);
        $realmSlug = $this->slug($characterRealm);
        $endpoint = "/profile/wow/character/$realmSlug/$nameSlug/status";
        $query = $this->buildQuery($endpoint,$namespace);
        return $this->queryAPI($query,$namespace);
    }
    
    public function characterStats($characterName,$characterRealm) {
        $namespace = 'profile';
        $nameSlug = $this->slug($characterName);
        $realmSlug = $this->slug($characterRealm);
        $endpoint = "/profile/wow/character/$realmSlug/$nameSlug/statistics";
        $query = $this->buildQuery($endpoint,$namespace);
        return $this->queryAPI($query);
    }
    
    public function characterSpecs($characterName,$characterRealm) {
        $namespace = 'profile';
        $nameSlug = $this->slug($characterName);
        $realmSlug = $this->slug($characterRealm);
        $endpoint = "/profile/wow/character/$realmSlug/$nameSlug/specializations";
        $query = $this->buildQuery($endpoint,$namespace);
        return $this->queryAPI($query);
    }
    
    public function guild($guildName,$guildRealm) {
        $namespace = 'profile';
        $nameSlug = $this->slug($guildName);
        $realmSlug = $this->slug($guildRealm);
        $endpoint = "/data/wow/guild/$realmSlug/$nameSlug";
        $query = $this->buildQuery($endpoint,$namespace);
        return $this->queryAPI($query);
    }
    
    public function guildRoster($guildName,$guildRealm) {
        $namespace = 'profile';
        $nameSlug = $this->slug($guildName);
        $realmSlug = $this->slug($guildRealm);
        $endpoint = "/data/wow/guild/$realmSlug/$nameSlug/roster";
        $query = $this->buildQuery($endpoint,$namespace);
        return $this->queryAPI($query);
    }
    
}
?> 
