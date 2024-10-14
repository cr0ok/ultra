<?php

class Event {
	 public $id;
	 public $name;
	 public $startTime;
	 public $description;
	 public $createdBy;
	 public $channelName;
	 public $channelId;
	 public $serverId;
	 public $signups = array();
	 public $errors = array();
     public function zoneName() {
        $ret = "";
        if (stristr($this->name,"naxx")) {
            $ret = "Naxxramas";
        } else if (stristr($this->name,"bwl")) {
            $ret = "Blackwing Lair";
        } else if (stristr($this->name,"aq40")) {
            $ret = "Temple of Ahn'Qiraj";
        } else if (stristr($this->name,"mc")) {
            $ret = "Molten Core";
        } else if (stristr($this->name,"aq20")) {
            $ret = "Ruins of Ahn'Qiraj";
        } else if (stristr($this->name,"zg") || stristr($this->name,"gurub")) {
            $ret = "Zul'Gurub";
        } else if (stristr($this->name,"ony")) {
            $ret = "Onyxia";
        }
        return $ret;
     }
     public function sortSignupsByAP($zoneId) {
         usort($this->signups, function($a,$b) use ($zoneId) {
            $rank = (isset($a->character) && isset($b->character)) ? $b->character->AP($zoneId) <=> $a->character->AP($zoneId) : 0;
            $signupTime = $a->dateTime > $b->dateTime; 
            if ($rank == 0) {
                return $signupTime;
            } 
            return $rank;
        });
     }
     public function sortSignupsByDate() {
        usort($this->signups, function($a,$b) {
            return $a->dateTime > $b->dateTime;
        });
     }
     public function sortSignupsByGuildRank() {
         usort($event->signups, function($a,$b) {
            $rank = (isset($a->character) && isset($b->character)) ? $a->character->rankNum <=> $b->character->rankNum : 0;
            $signupTime = $a->dateTime > $b->dateTime; 
            if ($rank == 0) {
                return $signupTime;
            } 
            return $rank;
        });
     }
}

class Signup {
	public $role;
	public $characterClass;
    public $spec;
	public $name;
	public $discordId;
	public $dateTime;
	public $position;
	public $isAbsent = false;
	public $isLate = false;
	public $isBenched = false;
	public $isTentative = false;
    public function canInvite() {
        return (!$this->isAbsent && !$this->isTentative);
    }

}

class RaidHelper {     
    private static $sAPI = "https://raid-helper.dev/api";
    private static $sRoster = NULL;
    private $mApiKey = NULL;
    
    private $mServerTimeZone = NULL;
    private $mDiscordTimeZone = NULL;
    private $mAllowedRealms = array();
    
    private $mAlwaysAddCharacters = array();
    private $mOverrideCharacters = array();

    private $mDb;
        
    public function __construct($apiKey,$serverTimeZone,$discordTimeZone,$allowedRealms,&$db) {
        $this->mApiKey = $apiKey;
        $this->mServerTimeZone = $serverTimeZone;
        $this->mDiscordTimeZone = $discordTimeZone;
        $this->mAllowedRealms = $allowedRealms;
        $this->mDb = $db;
    }
        
    public function talentHash($inRole, $inClass) {
        //return talent hash
        $ret = 0;
        //https://wow.tools/dbc/?dbc=talenttab&build=2.5.2.40260
        $talentTable = [
            "Mage" => ["Ranged" => 41],
            "Warrior" => ["Tank" => 163, "Melee" => 164],
            "Rogue" => ["Melee" =>181],
            "Priest" => ["Healer" => 202, "Ranged" => 203],
            "Shaman" => ["Healer" => 262, "Ranged" => 263],
            "Druid" => ["Healer" => 282, "Tank" => 281, "Melee" => 281, "Ranged" => 283],
            "Warlock" => ["Ranged" =>301],
            "Hunter" => ["Ranged" => 363],
            "Paladin" => ["Melee" => 381, "Healer" => 382, "Tank" => 383]
        ];
        $talentIndexes = [0,41,61,81,161,163,164,181,182,183,201,202,203,261,262,263,281,282,283,301,302,303,361,362,363,381,382,383];
        $talentHashes = str_split("0bcdfghjkmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ123456789");
        
        if (!empty($inRole) && !empty($inClass) && isset($talentTable[$inClass][$inRole])) {
            $talentNum = $talentTable[$inClass][$inRole];
            $talentKey = array_search($talentNum,$talentIndexes);
            if ($talentKey !== false) {
                $ret = $talentHashes[$talentKey];
            }
        } else {
            echo "talentHash() error: role: ".$inRole . " class: " . $inClass . "\n";
        }
        
        return $ret;
    }

    public function getTinyUrl($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL,'http://' . 'tinyurl.com/api-create.php?url=' . urlencode($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $tinyUrl = curl_exec($ch);
        curl_close($ch);
        
        if ($tinyUrl === false) {
            throw new RuntimeException("Could not create URL");
        }
        
        return $tinyUrl;
    }

    public function setAlwaysAddCharacters($alwaysAddCharacters) {
        $this->mAlwaysAddCharacters = $alwaysAddCharacters;
    }
    
    public function fixEventSignupName($eventId,$discordId,$fullName) {
        $fullName = str_replace(" ","",$fullName); //strip spaces
        $url = self::$sAPI."/v2/events/".$eventId."/signups/".$discordId;
        $ch = curl_init();
        $auth = 'Authorization: '.$this->mApiKey;
        $contentType = 'Content-Type: application/json; charset=utf-8';
        
        $fields = ["name" => $fullName, "notify" => false];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$auth,$contentType]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $res = json_decode(curl_exec($ch));
        curl_close($ch); 
    }
    public function parseEvents($serverId, $startTime = 0, $endTime = 0, $channelId = '') {
        $url = self::$sAPI."/v3/servers/".$serverId."/events";
        $headers = array();
        array_push($headers,'Authorization: '.$this->mApiKey);
        
        if ($startTime > 0) {
            array_push($headers,'StartTimeFilter: '.$startTime);
        }
        if ($endTime > 0) {
            array_push($headers,'EndTimeFilter: '.$endTime);
        }
        if (!empty($channelId)) {
            array_push($headers,'ChannelFilter: '.$channelId);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = json_decode(curl_exec($ch));
        curl_close($ch); 
        $parsedEvents = array();
        if (isset($res->postedEvents)) {
            foreach ($res->postedEvents as $event) {
                echo "Parsing event " . $event->id . "\n";
                $parsedEvents[] = $this->parseEvent($event->id);
            }
        }
        return $parsedEvents;
    }
        
    public function parseEvent($eventId) {
        $eventData = json_decode(file_get_contents(self::$sAPI."/v2/events/".$eventId));
        
        $event = new Event;

        if (!$eventData) return $event;

        $event->name = $eventData->title;
        $dt = new DateTime();
        $dt->setTimeStamp($eventData->startTime);
        $event->startTime = $dt;
        $event->createdBy = $eventData->leaderName;
        $event->description = $eventData->description;
        $event->id = $eventData->id;
        $event->serverId = $eventData->serverId;
        $event->channelName = $eventData->channelName;
        $event->channelId = $eventData->channelId;
        
        $signups = array();
        

        
        foreach ($eventData->signUps as $eventDataSignup) {
            
            $signup = new Signup;
            
            $signup->name = $eventDataSignup->name;
            $signup->discordId = $eventDataSignup->userId;
            
            $dateTime = new DateTime();
            $dateTime->setTimestamp($eventDataSignup->entryTime);
            $signup->dateTime = $dateTime;
            $signup->position = $eventDataSignup->position;
            
            $class = $eventDataSignup->className;
            $spec = isset($eventDataSignup->specName) ? $eventDataSignup->specName : NULL;
            $signup->spec = $spec;
                
            if ($class == "Bench") {
                $signup->isBenched = true;
                $class = $spec;
            } else if ($class == "Tentative") {
                $signup->isTentative = true;
                $class = $spec;
            } else if ($class == "Late") {
                $signup->isLate = true;
                $class = $spec;
            } else if ($class == "Absence") {
                $signup->isAbsent = true;
                $class = $spec;
                //continue;
            }
                        
            //determine role (Tank, Healer, Melee, or Ranged)
            if (isset($eventDataSignup->roleName)) {
                if ($eventDataSignup->roleName == "Tanks") {
                    $signup->role = "Tank";
                } else if ($eventDataSignup->roleName == "Healers") {
                    $signup->role = "Healer";
                } else {
                    $signup->role = $eventDataSignup->roleName;
                }
            } else {
                //guess role for those who didn't finish signing up
                if ($class == "Warrior" || $class == "Rogue") {
                    $signup->role = "Melee";
                } else if ($class == "Mage" || $class == "Warlock" || $class == "Hunter") {
                    $signup->role = "Ranged";
                } else if ($class == "Paladin" || $class == "Priest" || $class == "Druid") {
                    $signup->role = "Healer";
                }
            }

            //determine real character class
            
                    
            if ($spec == "Tank" || $spec == "Protection" || $spec == "Fury" || $spec == "Arms") {
                $class = "Warrior";
            } else if ($spec == "Fire" || $spec == "Frost" || $spec == "Arcane") {
                $class = "Mage";
            } else if ($spec == "Feral" || $spec == "Guardian" || $spec == "Balance" || $spec == "Restoration") {
                $class = "Druid";
            } else	if ($spec == "Shadow" || $spec == "Holy" || $spec == "Discipline" || $spec == "Smite") {
                $class = "Priest";
            } else	if ($spec == "Holy1" || $spec == "Protection1" || $spec == "Retribution") {
                $class = "Paladin";
            } else if ($spec == "Destruction" || $spec == "Demonology") {
                $class = "Warlock";
            } else if ($spec == "Combat" || $spec == "Assassination" || $spec == "Subtlety") {
                $class = "Rogue";
            } else if ($spec == "Marksmanship" || $spec == "Survival" || $spec == "Beastmastery") {
                $class = "Hunter";
            }
            
            if (empty($class)  && !$signup->isAbsent) {
                echo "*** UNFINISHED SIGNUP: ".$eventDataSignup->name.", ".$eventDataSignup->className.", ".$eventDataSignup->userId."\n";
                continue;
            }
                        
            $signup->characterClass = $class;

            array_push($signups,$signup);
           
        }

        usort($signups, function($a,$b) {
            //sort by sign-up date ascending
            return ($a->dateTime > $b->dateTime);
        });
        
        $event->signups = $signups;
                
        return $event;
    }
    
    public function addOverrideCharacterName($discordId,$eventChannelName,$class,$fullName) {
        $this->mOverrideCharacters[$discordId][$eventChannelName][$class] = $fullName;
    }
    public function getOverrideCharacter($discordId,$eventChannelName,$class) {
        $ret = false;
        if (isset($this->mOverrideCharacters[$discordId][$eventChannelName][$class])) {
            $ret = self::$sRoster->characterByFullName($this->mOverrideCharacters[$discordId][$eventChannelName][$class]);
        }
        return $ret;
    }
}
?> 
