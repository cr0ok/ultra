<?php
require_once("Report.php");

class GameClass {
    public $id;
    public $name;
    public $specs = array();
}

class WCL {
    private static $mInstance = NULL;
    private $mClientId = '';
    private $mClientSecret = '';
    private $mRegion = '';
    private $mServerTimeZone = '';
    private $mGameVariant = '';
    private $mAuthUri = '/oauth/authorize'; //not used this class
    private $mTokenUri = '/oauth/token';

    private $mAPI = '/api/v2/client';
    private $mCacheDir = "cache";
    private $mCacheDuration = 3600; //1 hour
    
    private $mToken = NULL;
        
    private $mZones = array();
    
    private $mGameClasses = array();
    
    private $mReports = array();
        
    //PRIVATE
    private function __construct($region = '',$timeZone = '',$gameVariant = '',$clientId = '',$clientSecret = '') {
        if (!empty($region)) $this->mRegion = $region;
        if (!empty($timeZone)) $this->mServerTimeZone = $timeZone;
        if (!empty($gameVariant)) $this->mGameVariant = $gameVariant;
        if (!empty($clientId)) $this->mClientId = $clientId;
        if (!empty($clientSecret)) $this->mClientSecret = $clientSecret;

        //get token
        $this->mToken = $this->getToken();
        if (empty($this->mToken)) {
            die("Error:  Unable to fetch WCL OAuth2.0 token.\n");
        }
        //get game data
        $this->mZones = $this->queryZones();
        $this->mGameClasses = $this->queryGameClasses();
    }

    //PROTECTED
    
    protected function baseUri() {
    	$prefix = empty($this->mGameVariant) ? "" : $this->mGameVariant.".";
    	return "https://".$prefix."warcraftlogs.com";
    }
    
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
        $ret = $this->fetchFromCache($this->mTokenUri,604800);
        if (!$ret) {
            //make new request
            $ch = curl_init($this->baseUri().$this->mTokenUri);
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
    
    protected function queryAPI($gql,$storeToCache = true, $cacheDuration = 14400) {
        $ret = false;
        //try fetching from cache first
        $ret = $this->fetchFromCache($gql,$cacheDuration);
        if (!$ret) {
            //make new request
            $variables = (object) null;
            $gqlQuery = ['query' => $gql, 'variables' => $variables];
            $ch = curl_init($this->baseUri().$this->mAPI);
            $auth = 'Authorization: '.$this->mToken->token_type.' '.$this->mToken->access_token;
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', $auth]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gqlQuery));
            $ret = json_decode(curl_exec($ch));
            curl_close($ch);
            //cache result
            if (!empty($ret) && !isset($ret->error) && $storeToCache) {
                $this->storeToCache($gql,$ret);
            } else if (isset($ret->error)) {
                echo "WCL API error: " . $ret->error . "\n";
            }
        }
        return $ret;
    }
    
    protected function queryGameClasses() {
        echo "Querying game class/spec information.\n";
        $gameClasses = array();
        $gql = 
<<<QUERY
query {
    gameData {
        classes {
            id
            name
            specs {
                slug
            }
        }
    }
}

QUERY;
        $res = $this->queryAPI($gql,true,604800);
        foreach ($res->data->gameData->classes as $c) {
            $gameClass = new GameClass;
            $gameClass->id = $c->id;
            $gameClass->name = $c->name;
            foreach ($c->specs as $spec) {
                $gameClass->specs[] = $spec->slug;
            }
            $gameClasses[$gameClass->id] = $gameClass;
        }
        return $gameClasses;
    }
    

    protected function queryZones() {
        echo "Querying world data.\n";
        $zones = array();   
        $gql = 
<<<QUERY
        query {
            worldData {
                expansions {
                    id
                    name
                    zones {
                        id
                        name
                    }
                }
            }
        }    
QUERY;
        $res = $this->queryAPI($gql,true,604800);
        $classicWowExpansion = 0;
        foreach ($res->data->worldData->expansions[$classicWowExpansion]->zones as $zone) {
            $zones[$zone->id] = $zone->name;
        }
        return $zones;
    }

    protected function queryCharacterDataById($id,$zoneId=0,$role='Any') {
        if ($role == "Ranged" || $role == "Melee") {
            $role = "DPS";
        }
        $ret = array();
        echo "Querying character data for ".$id.", zone $zoneId, role $role.\n";
        $region = $this->mRegion;
        $zone = $zoneId > 0 ? "zoneID: $zoneId, " : ""; 
        $gql = 
<<<QUERY
        query {
            characterData {
                character(id: $id) {
                    classID
                    zoneRankings ($zone compare: Parses, role: $role, metric: default)
                }
            }
        }
QUERY;
        $res = $this->queryAPI($gql,true,21600);
        if (!$res) return $ret;
        if (!isset($res->data->characterData)) {
            var_dump($res);
            return $ret;
        }
        $data = $res->data->characterData->character;
        
        if (!empty($data)) {
            $ret['wcl_id'] = $id;
            $ret['class'] = $data->classID > 0 ? $this->gameClassNameById($data->classID): NULL;
            $ret['bestPerfAvg'] = $data->zoneRankings->bestPerformanceAverage;
            $ret['medianPerfAvg'] = $data->zoneRankings->medianPerformanceAverage;
            $specs = array();
            $totals = array();
            foreach ($data->zoneRankings->rankings as $ranking) {
                if (!isset($ranking->spec)) continue;
                if (!array_key_exists($ranking->spec,$specs)) {
                    $specs[$ranking->spec] = 1;
                } else {
                    $specs[$ranking->spec]++;
                }
                $totals[] = $ranking->bestAmount;
            }
            $ret['bestTotalAvg'] = empty($totals) ? 0 : (array_sum($totals) / count($totals));
            if (!empty($specs)) {
                $ret['spec'] = array_search(max($specs),$specs);
                $ret['role'] = $this->calculateRole($ret['class'],$ret['spec']);
            } else {
                $ret['spec'] = NULL;
                $ret['role'] = NULL;
            }
        }
    
        return $ret;    
    }
    
    protected function queryCharacterData($name,$realm,$zoneId=0,$role='Any') {
        if ($role == "Ranged" || $role == "Melee") {
            $role = "DPS";
        }
        echo "Querying character data for ".$name."-".$realm.", zone $zoneId, role $role.\n";
        $region = $this->mRegion;
        $zone = $zoneId > 0 ? "zoneID: $zoneId, " : ""; 
        $gql = 
<<<QUERY
        query {
            characterData {
                character(name: "$name", 
                                serverSlug: "$realm", 
                                serverRegion: "$region") {
                    id
                    classID
                    zoneRankings ($zone compare: Parses, role: $role, metric: default)
                }
            }
        }
QUERY;
        $res = $this->queryAPI($gql,true,21600);
        $data = $res->data->characterData->character;
        $ret = array();
        if (!empty($data)) {
            $ret['wcl_id'] = $data->id;
            $ret['class'] = $data->classID > 0 ? $this->gameClassNameById($data->classID): NULL;
            $ret['bestPerfAvg'] = $data->zoneRankings->bestPerformanceAverage;
            $ret['medianPerfAvg'] = $data->zoneRankings->medianPerformanceAverage;
            $specs = array();
            $totals = array();
            foreach ($data->zoneRankings->rankings as $ranking) {
                if (!isset($ranking->spec)) continue;
                if (!array_key_exists($ranking->spec,$specs)) {
                    $specs[$ranking->spec] = 1;
                } else {
                    $specs[$ranking->spec]++;
                }
                $totals[] = $ranking->bestAmount;
            }
            $ret['bestTotalAvg'] = empty($totals) ? 0 : (array_sum($totals) / count($totals));
            if (!empty($specs)) {
                $ret['spec'] = array_search(max($specs),$specs);
                $ret['role'] = $this->calculateRole($ret['class'],$ret['spec']);
            } else {
                $ret['spec'] = NULL;
                $ret['role'] = NULL;
            }
        }
    
        return $ret;    
    }

    protected function queryReport($code) {
        echo "Querying report $code.\n";
        $gql =
<<<QUERY
            query {
                reportData {
                    report(code: "$code") {
                        code
                        title
                        startTime
                        endTime
                        zone {
                            id
                            name
                        }
                        rankedCharacters {
                            canonicalID
                            name
                            classID
                            server {
                                name
                            }
                        }
                    }
                }
            }
QUERY;
        $res = $this->queryAPI($gql,true,PHP_INT_MAX);
        $reportData = $res->data->reportData->report;
        $rep = new Report;
        $rep->id = $reportData->code;
        $rep->title = $reportData->title;
        $timestampStart = floor($reportData->startTime/1000);
        $timestampEnd = floor($reportData->endTime/1000);
        $rep->startDateTime = DateTime::createFromFormat("U",
                                                            $timestampStart,
                                                            new DateTimeZone("UTC"));
        $rep->startDateTime->setTimeZone(new DateTimeZone($this->mServerTimeZone));
        $rep->endDateTime = DateTime::createFromFormat("U",
                                                          $timestampEnd,
                                                          new DateTimeZone("UTC"));
        $rep->endDateTime->setTimeZone(new DateTimeZone($this->mServerTimeZone));
        if (isset($reportData->zone) && isset($reportData->rankedCharacters)) {
        
            $rep->zoneId = $reportData->zone->id;
            
            foreach ($reportData->rankedCharacters as $char) {
                array_push($rep->characters,$char);
            }
        }
        return $rep;
    }
        
    protected function calculateRole ($class,$spec) {
        $role = NULL;
        if ($spec == 'Guardian' || $spec == 'Protection') {
            $role = 'Tank';
        } else if ($spec == 'Holy' || $spec == 'Restoration') {
            $role = 'Healer';
        } else if ($class == 'Warrior' || $class == 'Rogue' || $class == 'Paladin' || ($class == 'Druid' && $spec == 'Feral')) {
            $role = 'Melee';
        } else {
            $role = 'Ranged';
        }
        return $role;
    }

    protected function queryGuildReports($guildName,$guildRealm,$startTime=0) {
        echo "Querying guild reports for ".$guildName."-".$guildRealm.".\n";
        $reports = array();
        $startTime = $startTime == 0 ? "" : "startTime: ".($startTime*1000).", ";
        $hasMorePages = true;
        $page = 1;
        while ($hasMorePages) {
            $gql = 
<<<QUERY
            query {
                reportData {
                    reports(guildName: "$guildName", 
                                guildServerSlug: "$guildRealm", 
                                guildServerRegion: "$this->mRegion",
                                $startTime
                                page: $page) {
                        has_more_pages
                        current_page
                        last_page
                        data {
                            code
                        }
                    }
                }
            }
QUERY;
            $res = $this->queryAPI($gql,false);
            //echo $res->data->reportData->reports->current_page . " / " . $res->data->reportData->reports->last_page . "\n";
            if (!isset($res->data)) {
                
                break;
            }

            $hasMorePages = $res->data->reportData->reports->has_more_pages;
            foreach ($res->data->reportData->reports->data as $reportData) {
                
                $rep = $this->queryReport($reportData->code);
                $reportDate = $rep->startDateTime->format("Y-m-d");
                $reportZone = $rep->zoneId;
                if ($reportZone > 0) {
                    $index = $reportDate . "-" . $reportZone;
                    if (!isset($reports[$index])) {
                        $reports[$index] = $rep;
                        $this->mReports[$index] = $rep;
                    }
                }
            }
            $page++;
        }
         uasort($reports, function($a,$b) {
            //sort chronologically
            return ($a->startDateTime > $b->startDateTime);
        });
        return $reports;
    }
    
    //PUBLIC
    
    public static function getInstance($region='', $timeZone = '', $gameVariant = '', $clientId='', $clientSecret='') {
        if (self::$mInstance == NULL) {
            self::$mInstance = new WCL($region,$timeZone,$gameVariant,$clientId,$clientSecret);
        }
        return self::$mInstance;
    }
    
    public function zones() {
        return $this->mZones;
    }
    
    public function zoneIdByName($zoneName) {
        return array_search($zoneName,$this->mZones);
    }

    public function zoneName($zoneId) {
        return $this->mZones[$zoneId];
    }
    
    public function gameClasses() {
        return $this->mGameClasses;
    }

    public function gameClassNameById($id) {
        foreach ($this->mGameClasses as $gameClass) {
            if ($gameClass->id == $id) {
                return $gameClass->name;
            }
        }
        return false;
    }

    public function reportByScheduledEventStart ($startDateTime) {
        $ret = false;
        foreach ($this->mReports as $reportIndex => $report) {
            
            $diff = $report->startDateTime->getTimeStamp() - $startDateTime->getTimeStamp();
            //if report started within 1 hour of scheduled event
            if ($diff > 0 && $diff < 3600) {
                //echo "found report " . $diff . " seconds diff ". $report->title. "\n";
                $ret = $report;
                break;
            }
        }
        return $ret;
    }
    
    public function reports($guildName,$guildRealm,$sinceStr='') {
        $startTime = 0;
        if (!empty($sinceStr)) {
            $startTime = strtotime($sinceStr);
        }
        return $this->queryGuildReports($guildName,$guildRealm,$startTime);
    }
    
    public function zoneRankings($name,$realm,$zoneNameOrId='',$role='Any') {
        
        $zoneId = 0;
        if (is_numeric($zoneNameOrId)) {
            $zoneId = $zoneNameOrId;
        } else if (!empty($zoneName)) {
            $zoneId = array_search($zoneName,$this->mZones);
        }
        return $this->queryCharacterData($name,$realm,$zoneId,$role);
    }

    public function zoneRankingsById($id,$zoneId,$role='Any') {
        return $this->queryCharacterDataById($id,$zoneId,$role);
    }

    public function numReportsFromZone($zoneId) {
        $ret = 0;
        foreach ($this->mReports as $reportIndex => $report) {
            if ($report->zoneId == $zoneId) {
                $ret++;
            }
            
        }
        return $ret;
    }
    
    public function characterData($characterName,$characterRealm) {
        return $this->queryCharacterData($characterName,$characterRealm);
    }
    
}
?> 
