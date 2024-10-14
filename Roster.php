<?php


class RaidSignup {
    private $mDiscordId;
    private $mName;
    private $mClass;
    private $mRole;
    private $mbBench;
    private $mbLate;
    private $mbTentative;
    private $mbAbsent;

    private $mGuildRank;
    private $mRace;
    private $mSpec;
    private $mPerf = 0;
    private $mTotal = 0;
    private $mAttended = 0;
    private $mAvailable = 0;

    public function __construct($discordId,$name,$class,$role,$spec,$bench,$late,$tentative,$absent) {
        $this->mDiscordId = $discordId;
        $this->mName = $name;
        $this->mClass = $class;
        $this->mRole = $role;
        $this->mSpec = $spec;
        $this->mbBench = $bench;
        $this->mbLate = $late;
        $this->mbTentative = $tentative;
        $this->mbAbsent = $absent;
    }

    public function discordId() {
        return $this->mDiscordId;
    }
    public function name() {
        return $this->mName;
    }
    public function class() {
        return $this->mClass;
    }
    public function role() {
        return $this->mRole;
    }
    public function guildRank() {
        return $this->mGuildRank;
    }
    public function setGuildRank($guildRank) {
        $this->mGuildRank = $guildRank;
    }
    public function race() {
        $ret = "Unknown";
        if (!empty($this->mRace)) {
            $ret = $this->mRace;
        }
        return $ret;
    }
    public function setRace($race) {
        $this->mRace = $race;
    }
    public function spec() {
        return $this->mSpec;
    }
    public function setSpec($spec) {
        $this->mSpec = $spec;
    }
    public function perf() {
        return $this->mPerf;
    }
    public function setPerf($perf) {
        $this->mPerf = $perf;
    }
    public function total() {
        return $this->mTotal;
    }
    public function setTotal($total) {
        $this->mTotal = $total;
    }
    public function attendance() {
        $ret = 0;
        if ($this->mAvailable == 0) {
            return $ret;
        }
        return ($this->mAttended / $this->mAvailable);
    }
    public function setAttended($attended) {
        $this->mAttended = $attended;
    }
    public function setAvailable($available) {
        $this->mAvailable = $available;
    }

    public function ap() {
        return ($this->attendance() + ($this->perf()/100)) / 2;
    }
    
    public function canInvite() {
        return !$this->absent() && !$this->tentative();
    }
    public function bench() {
        return (bool)$this->mbBench;
    }
    public function late() {
        return (bool)$this->mbLate;
    }
    public function tentative() {
        return (bool)$this->mbTentative;
    }
    public function absent() {
        return (bool)$this->mbAbsent;
    }
}


class Roster {
    private static $sRosterMax = [
        "Naxxramas" => 40,
        "Temple of Ahn'Qiraj" => 40,
        "Blackwing Lair" => 40,
        "Molten Core" => 40,
        "Onyxia's Lair" => 40,
        "Zul'Gurub" => 20,
        "Ruins of Ahn'Qiraj" => 20,
    ];
    private static $sRoleComp = [
        "Naxxramas" => [
            'Tank' => [6,8],
            'Melee' => [10,18],
            'Ranged' => [7,10],
            'Healer' => [11,14],
        ],
        "Temple of Ahn'Qiraj" => [
            'Tank' => [6,8],
            'Melee' => [10,18],
            'Ranged' => [7,10],
            'Healer' => [11,14],
        ],
        "Blackwing Lair" => [
            'Tank' => [5,7],
            'Melee' => [10,18],
            'Ranged' => [6,10],
            'Healer' => [10,12],
        ],
        "Molten Core" => [
            'Tank' => [4,6],
            'Melee' => [10,18],
            'Ranged' => [6,10],
            'Healer' => [8,12],
        ],
        "Onyxia's Lair" => [
            'Tank' => [2,4],
            'Melee' => [4,12],
            'Ranged' => [6,12],
            'Healer' => [4,12],
        ],
        "Zul'Gurub" => [
            'Tank' => [2,3],
            'Melee' => [4,6],
            'Ranged' => [4,6],
            'Healer' => [4,6],
        ],
        "Ruins of Ahn'Qiraj" => [
            'Tank' => [2,3],
            'Melee' => [4,6],
            'Ranged' => [4,6],
            'Healer' => [4,6],
        ]
    ];
    private static $sClassComp = [
        "Naxxramas" => [
            'Warrior' => [10,18],
            'Rogue' => [1,5],
            'Hunter' => [1,2],
            'Mage' => [4,6],
            'Warlock' => [1,3],
            'Druid' => [1,4],
            'Priest' => [4,6],
            'Paladin' => [5,6],
        ],
        "Temple of Ahn'Qiraj" => [
            'Warrior' => [10,16],
            'Rogue' => [1,5],
            'Hunter' => [1,3],
            'Mage' => [4,6],
            'Warlock' => [1,3],
            'Druid' => [1,4],
            'Priest' => [4,6],
            'Paladin' => [5,6],
        ],
        "Blackwing Lair" => [
            'Warrior' => [8,16],
            'Rogue' => [1,6],
            'Hunter' => [1,3],
            'Mage' => [4,6],
            'Warlock' => [1,3],
            'Druid' => [1,4],
            'Priest' => [4,6],
            'Paladin' => [5,6],
        ],
        "Molten Core" => [
            'Warrior' => [8,16],
            'Rogue' => [1,6],
            'Hunter' => [1,3],
            'Mage' => [4,6],
            'Warlock' => [1,3],
            'Druid' => [1,4],
            'Priest' => [3,6],
            'Paladin' => [5,6],
        ],
        "Onyxia's Lair" => [
            'Warrior' => [6,12],
            'Rogue' => [1,6],
            'Hunter' => [2,6],
            'Mage' => [3,6],
            'Warlock' => [1,3],
            'Druid' => [1,4],
            'Priest' => [2,6],
            'Paladin' => [2,6],
        ],
        "Zul'Gurub" => [
            'Warrior' => [3,6],
            'Rogue' => [1,2],
            'Hunter' => [1,2],
            'Mage' => [1,4],
            'Warlock' => [1,3],
            'Druid' => [1,2],
            'Priest' => [1,2],
            'Paladin' => [1,2],
        ],
        "Ruins of Ahn'Qiraj" => [
            'Warrior' => [3,6],
            'Rogue' => [1,2],
            'Hunter' => [1,2],
            'Mage' => [1,4],
            'Warlock' => [1,3],
            'Druid' => [1,2],
            'Priest' => [1,2],
            'Paladin' => [1,2],
        ]

    ];

    public static function camelCase($inStr,$delim = ' ', $ucfirst = true) {
        $ret = '';
        $words = explode($delim,$inStr);
        foreach ($words as $index => $word) {
            if ($index == 0 && !$ucfirst) {
                $ret .= $word;
            } else {
                $ret .= ucfirst($word);
            }
        }
        return $ret;
    }
    
    public static function unCamelCase($inStr, $delim = ' ') {
        $formattedStr = '';
        $re = '/
              (?<=[a-z])
              (?=[A-Z])
            | (?<=[A-Z])
              (?=[A-Z][a-z])
            /x';
        $a = preg_split($re,$inStr);
        $formattedStr = implode($delim,$a);
        return $formattedStr;
    }

    private $mDb;
    private $mServerTimeZone;
    private $mSinceDate;
    private $mEventId;
    private $mEventName;
    private $mZoneName;
    private $mRaidSignups = [];
    private $mAvailable = [];
    private $mInviteList = [];
    private $mClassCount = [];
    private $mRoleCount = [];
    private $mBuffCount = [];
    private $mRaidDps = 0;
    private $mQueryEvent;
    private $mQuerySignups;
    private $mQueryBanned;
    private $mQueryPlayerAttendance;
    private $mQueryCharacterPerformance;
    
    protected function _prepareStatements() {
        //query event
        $sql = <<<SQL
            SELECT name,zone_name,start
            FROM event
            WHERE id = ?
        SQL;
        $this->mQueryEvent = $this->mDb->prepare($sql);
        //query signups
        $sql = <<<SQL
            SELECT discord_id,name,position,class,role,spec,bench,late,tentative,absent 
            FROM event_signup
            WHERE event_id = ?
        SQL;
        $this->mQuerySignups = $this->mDb->prepare($sql);

        //query banned
        /*
        $sql = <<<SQL
            SELECT name || '-' || replace(realm,' ','') AS 'full_name', banned
            FROM character
            WHERE full_name = ?
        SQL;
        */
        $sql = <<<SQL
            SELECT banned
            FROM character
            WHERE name = ? AND realm = ?
        SQL;
        $this->mQueryBanned = $this->mDb->prepare($sql);
        //query attendance/performance

        $sql = <<<SQL
            SELECT
                za.date_first,
                COUNT(r.id) AS 'attended', 
                (   SELECT COUNT(r2.id)
                    FROM report r2 
                    JOIN zone z2, zone_attendance za2, character c2
                    ON (z2.id=z.id AND z2.id=r2.zone_id AND za2.zone_id = z2.id 
                        AND za2.character_wcl_id = c2.wcl_id AND c2.wcl_id = c.wcl_id)
                    WHERE r2.start_time >= za2.date_first AND r2.start_time >= :since_date) AS 'available' 
            FROM character c JOIN report r, zone z, attendance a, zone_attendance za
            ON (r.id=a.report_id AND z.id = r.zone_id AND c.wcl_id = a.character_wcl_id
                AND za.character_wcl_id = c.wcl_id AND za.zone_id = z.id) 
            WHERE c.discord_id = :discord_id AND z.name = :zone_name AND r.start_time >= :since_date
        SQL;
        $this->mQueryPlayerAttendance = $this->mDb->prepare($sql);

        //query character performance
        $sql = <<<SQL
            SELECT c.race, c.class, c.guild_rank,
            p.best,p.median, (p.best+p.median)/2 AS 'perf', p.total
            FROM character c
            JOIN performance p, zone z 
                ON p.character_wcl_id = c.wcl_id AND z.id = p.zone_id AND z.name = :zone_name
            WHERE c.name = :name AND c.realm = :realm
        SQL;
        $this->mQueryCharacterPerformance = $this->mDb->prepare($sql);
        /*
        $sql = <<<SQL
            SELECT c.name || '-' || replace(c.realm,' ','') AS 'full_name', c.race, c.class, c.guild_rank,
            p.best, p.median, (p.best+p.median)/2 perf, p.total,
            COUNT(r.id) AS 'attended', 
            (   SELECT COUNT(r2.id) 
                FROM report r2 
                JOIN zone_attendance za
                ON (z.id=r2.zone_id AND za.zone_id = z.id 
                    AND za.character_wcl_id = c.wcl_id
                    AND r2.start_time >= za.date_first 
                    AND r2.start_time >= :since_date)
            ) AS 'available'
            FROM character c
            JOIN performance p, zone z ON p.character_wcl_id = c.wcl_id 
                AND z.id = p.zone_id AND z.name = :zone_name
            JOIN report r ON r.zone_id = z.id AND r.start_time >= :since_date
            JOIN attendance a ON a.report_id = r.id AND a.character_wcl_id = c.wcl_id
            WHERE full_name = :signup_name
        SQL;
        $this->mQueryAP = $this->mDb->prepare($sql);
        */
    }

    protected function _rosterMax() {
        $ret = 0;
        if (isset(self::$sRosterMax[$this->zoneName()])) {
            $ret = self::$sRosterMax[$this->zoneName()];
        }
        return $ret;
    }

    protected function _sortByAP() {
        usort($this->mRaidSignups,function($a,$b) {
            return -($a->ap() <=> $b->ap());
        });
    }

    protected function _buildInviteList() {
        foreach ($this->mRaidSignups as $s) {
            if ($s->canInvite()) {
                $this->mAvailable[] = $s;
            }
        }


        if (!isset(self::$sRoleComp[$this->mZoneName])){
            echo "No raid comp specified for " . $this->mZoneName . "\n";
            $this->mInviteList = $this->mAvailable;
            return;
        }

        $inviteRole = [
            'Tank' => 0,
            'Melee' => 0,
            'Ranged' => 0,
            'Healer' => 0
        ];
        $inviteClass = [
            'Warrior' => 0,
            'Rogue' => 0,
            'Hunter' => 0,
            'Mage' => 0,
            'Warlock' => 0,
            'Druid' => 0,
            'Priest' => 0,
            'Paladin' => 0
        ];

        //role comp min
        foreach (self::$sRoleComp[$this->mZoneName] as $role => $req) {
            $min = $req[0];
            foreach ($this->mAvailable as $index => $s) {
                if ($s->role() == $role && $inviteRole[$role] < $min) {
                    $inviteRole[$role]++;
                    $inviteClass[$s->class()]++;
                    $this->mInviteList[] = $s;
                    unset($this->mAvailable[$index]);
                }
            }
        }
        //class comp min
        foreach (self::$sClassComp[$this->mZoneName] as $class => $req) {
            $min = $req[0];
            foreach ($this->mAvailable as $index => $s) {
                if ($s->class() == $class && $inviteClass[$class] < $min) {
                    $inviteClass[$class]++;
                    $inviteRole[$s->role()]++;
                    $this->mInviteList[] = $s;
                    unset($this->mAvailable[$index]);
                }
            }
        }
        //role comp max
        krsort($this->mInviteList); //reverse sort
        foreach (self::$sRoleComp[$this->mZoneName] as $role => $req) {
            $max = $req[1];
            foreach ($this->mInviteList as $index => $s) {
                if ($s->role() == $role && $inviteRole[$role] > $max) {
                    $inviteRole[$role]--;
                    $inviteClass[$s->class()]--;
                    echo "Removing ".$s->name()." -- too many in role $role\n";
                    $this->mAvailable[] = $s;
                    unset($this->mInviteList[$index]);
                }
            }
        }
        //class comp max
        foreach (self::$sClassComp[$this->mZoneName] as $class => $req) {
            $max = $req[1];
            foreach ($this->mInviteList as $index => $s) {
                if ($s->class() == $class && $inviteClass[$class] > $max) {
                    $inviteClass[$class]--;
                    $inviteRole[$s->role()]--;
                    echo "Removing ".$s->name()." -- too many in class $class\n";
                    $this->mAvailable[] = $s;
                    unset($this->mInviteList[$index]);
                }
            }
        }
        ksort($this->mInviteList);
        $this->mInviteList = array_values($this->mInviteList);
        
    }

    protected function _count() {
        $list = $this->invitePlusAvailable();
        $classes = [
            'Warrior' => 0,
            'Rogue' => 0,
            'Hunter' => 0,
            'Mage' => 0,
            'Warlock' => 0,
            'Druid' => 0,
            'Priest' => 0,
            'Paladin' => 0
        ];
        $roles = [
            'Tank' => 0,
            'Melee' => 0,
            'Ranged' => 0,
            'Healer' => 0
        ];
        $buffs = [
            'trueShot' => 0,
            'feral' => 0,
            'moonkin' => 0,
            'fearWard' => 0,
            'ignite' => 0,
        ];
        $dps = 0;

        for ($i = 0; ($i < $this->_rosterMax() && $i < count($list)); ++$i) {
            $c = $list[$i];
            if (isset($classes[$c->class()])) {
                $classes[$c->class()]++;
                if ($c->class() == 'Hunter' && $c->spec() == 'Marksmanship') {
                    $buffs['trueShot']++;
                } else if ($c->class() == 'Druid' 
                    && ($c->spec() == 'Feral' || $c->spec() == 'Guardian')) {
                    $buffs['feral']++;
                } else if ($c->class() == 'Druid' && $c->spec() == 'Balance') {
                    $buffs['moonkin']++;
                } else if ($c->class() == 'Mage' && $c->spec() == 'Fire') {
                    $buffs['ignite']++;
                } else if ($c->class() == 'Priest' && $c->race() == 'Dwarf') {
                    $buffs['fearWard']++;
                }
            } else {
                echo "Unexpected class ".$c->class()." for ".$c->name()."\n";
            }
            if (isset($roles[$c->role()])) {
                $roles[$c->role()]++;
                if ($c->role() !== 'Healer') {
                    $dps += $c->total();
                } 
            } else {
                echo "Unexpected role ".$c->role()." for ".$c->role()."\n";
            }
            //echo $c->name()." ".$c->class()." ".$c->role()." ".$c->spec()."\n";
        }
        $this->mClassCount = $classes;
        $this->mRoleCount = $roles;
        $this->mBuffCount = $buffs;
        $this->mRaidDps = $dps;
    }

    protected function _stripRealm($inNameRealm) {
        $nameParts = explode("-",$inNameRealm);
        return $nameParts[0];
    }

    public function __construct($eventId,$sinceDate,$serverTimeZone,$db) {
        $this->mDb = $db;
        $this->mServerTimeZone = new DateTimeZone($serverTimeZone);
        $this->mSinceDate = $sinceDate;
        $this->mEventId = $eventId;
        $this->_prepareStatements();

        $this->mQueryEvent->execute([$eventId]);
        $e = $this->mQueryEvent->fetch(PDO::FETCH_ASSOC);
        
        $this->mEventName = $e['name'];
        $this->mZoneName = $e['zone_name'];
        $this->mStartDateTime = new DateTime($e['start'],new DateTimeZone("UTC"));
        $this->mStartDateTime->setTimeZone($this->mServerTimeZone);

        echo "-> Processing signups for ".$this->mZoneName. " on ".$this->mStartDateTime->format("Y-m-d").":\n";

        $this->mQuerySignups->execute([$eventId]);

        while ($s = $this->mQuerySignups->fetch(PDO::FETCH_ASSOC)) {
            $rs = new RaidSignup($s['discord_id'],$s['name'],$s['class'],$s['role'],$s['spec'],
                                 $s['bench'],$s['late'],$s['tentative'],$s['absent']);
            $bValidCharacterName = false;
            $characterNameParts = explode("-",$s['name']);
            $characterName = "";
            $characterRealm = "";
            if (count($characterNameParts) == 2) {
                $bValidCharacterName = true;
                $characterName = $characterNameParts[0];
                $characterRealm = $characterNameParts[1];
            }
            if ($bValidCharacterName && !$rs->absent()) {
                $bBanned = false;
                $this->mQueryBanned->execute([$characterName,$characterRealm]);
                if ($bannedResult = $this->mQueryBanned->fetch(PDO::FETCH_ASSOC)) {
                    if ($bannedResult) {
                        $bBanned = (bool)$bannedResult['banned'];
                    }
                    
                }
                
                $this->mQueryPlayerAttendance->execute([
                    'since_date' => $this->mSinceDate->format("Y-m-d H:i:s"),
                    'zone_name' => $this->zoneName(),
                    'discord_id' => $s['discord_id']
                ]);
                $att = $this->mQueryPlayerAttendance->fetch(PDO::FETCH_ASSOC);

                $this->mQueryCharacterPerformance->execute([
                    'zone_name' => $this->zoneName(),
                    'name' => $characterName,
                    'realm' => $characterRealm
                ]);
                $perf = $this->mQueryCharacterPerformance->fetch(PDO::FETCH_ASSOC);

                /*
                $this->mQueryAP->execute([
                    'since_date' => $this->mSinceDate->format("Y-m-d H:i:s"),
                    'zone_name' => $this->mZoneName,
                    'signup_name' => $rs->name()
                ]);
                $ap = $this->mQueryAP->fetch(PDO::FETCH_ASSOC);
                */
                $bHasAttended = false;
                if ($att && $perf) {
                    $bHasAttended = true;
                    $rs->setRace($perf['race']);
                    $rs->setGuildRank($perf['guild_rank']);
                    //$rs->setSpec($ap['spec']);
                    $rs->setPerf($perf['perf']);
                    $rs->setTotal($perf['total']);
                    $rs->setAttended($att['attended']);
                    $rs->setAvailable($att['available']);
                    
                } 
                if (!$bBanned) {
                    echo "+ ".$rs->name();
                    if (!$bHasAttended) {
                        echo " (First time in zone)";
                    }
                    echo "\n";
                    $this->mRaidSignups[] = $rs;
                } else {
                    echo "! ".$rs->name() . " is BANNED - ignoring signup!\n";
                }
                
            }                   
            //
        }
        $this->_sortByAP();
        $this->_buildInviteList();
        $this->_count();
    }
    
    public function eventName() {
        return $this->mEventName;
    }

    public function zoneName() {
        return $this->mZoneName;
    }
    public function startDateTime() {
        return $this->mStartDateTime;
    }
    public function raidSignups() {
        return $this->mRaidSignups;
    }

    public function inviteList() {
        return $this->mInviteList;
    }

    public function available() {
        return $this->mAvailable;
    }

    public function invitePlusAvailable() {
        return array_merge($this->mInviteList,$this->mAvailable);
    }

    public function classCount() {
        return $this->mClassCount;
    }
    
    public function roleCount() {
        return $this->mRoleCount;
    }
    
    public function buffCount() {
        return $this->mBuffCount;
    }

    public function raidDps() {
        return $this->mRaidDps;
    }

    public function organizedList() {
        $ret = [];
        $classes = $this->classCount();
        $roles = $this->roleCount();
        $buffs = $this->buffCount();
        $avail = $this->invitePlusAvailable();
        $numAvail = count($avail);

        $trueShot = false;
        $feral = false;
        $warrior = false;
        
        for ($i = 0; ($i < $this->_rosterMax() && $i < $numAvail); ++$i) {
            if ($i % 5 == 0) {
                //new group
                $trueShot = false;
                $feral = false;
                $warrior = false;
            } 

            $selectedIndex = false;
                        
            // feral
            if (!$feral && $buffs['feral'] > 0) {
                foreach ($avail as $index => $s) {
                    if ($s->class() == 'Druid' 
                        && ($s->spec() == 'Feral' || $s->spec() == 'Guardian')) {
                        $selectedIndex = $index;
                        $feral = true;
                        $buffs['feral']--;
                        break;
                    }
                }
            } elseif (!$trueShot && $buffs['trueShot'] > 0) {
                foreach ($avail as $index => $s) {
                    if ($s->class() == 'Hunter' && $s->spec() == 'Marksmanship') {
                        $selectedIndex = $index;
                        $trueShot = true;
                        $buffs['trueShot']--;
                        break;
                    }
                }
            } elseif ($roles['Tank'] > 0) {
                foreach ($avail as $index => $s) {
                    if ($s->role() == 'Tank' && ($s->class() !== "Druid" || !$feral)) {
                        $selectedIndex = $index;
                        break;
                    }
                }
            
            } elseif (!$warrior && $classes['Warrior'] > 0) {
                foreach ($avail as $index => $s) {
                    if ($s->class() == 'Warrior') {
                        $selectedIndex = $index;
                        $warrior = true;
                        break;
                    }
                } 
            } elseif ($roles['Melee'] > 0) {
                foreach ($avail as $index => $s) {
                    if ($s->role() == 'Melee') {
                        $selectedIndex = $index;
                        break;
                    }
                }
            } elseif ($roles['Ranged'] > 0) {
                foreach ($avail as $index => $s) {
                    if ($s->role() == 'Ranged') {
                        $selectedIndex = $index;
                        break;
                    }
                }
            }             

            $selected = false;

            if ($selectedIndex !== false) {
                $selected = $avail[$selectedIndex];
                
                unset($avail[$selectedIndex]);
            } else {
                //just add next
                $selected = array_shift($avail);
            }
            if ($selected) {
                //echo "Selected ".$selected->name()."\n";
                $classes[$selected->class()]--;
                $roles[$selected->role()]--;
                $ret[] = $selected;
            } 
        }
        //add remaining
        foreach ($avail as $s) {
            $ret[] = $s;
            //echo "Added ".$s->name()."\n";
        }
        return $ret;
    }

    public function topDPS($limit = 25) {
        $count = 0;
        $ret = [];
        foreach ($this->inviteList() as $s) {
            if ($s->role() !== 'Healer') {
                $ret[] = $s;
            }
        }
        usort($ret,function($a,$b){
            return -($a->total() <=> $b->total());
        });
        return array_slice($ret,0,$limit);
    }

    public function sporeGroups() {
        
        $t = $this->topDPS(25);
        $magesAvailable = [];
        foreach ($t as $index => $s) {
            if ($s->class() == "Mage") {
                $magesAvailable[] = $s;
                unset($t[$index]);
            }
        }

        $ret = [];

        for ($group = 1; $group <= 5; $group++) {
            $magesRequired = 0;

            if ($group == 1 || $group == 2) {
                $magesRequired = 2;
            }
            for ($spot = 1; $spot <= 5; $spot++) {
                if ($magesRequired > 0 && count($magesAvailable) > 0) {
                    $next = array_shift($magesAvailable);
                    $magesRequired--;
                } else {
                    $next = array_shift($t);   
                }
                if ($next) {
                    $ret[$group][$spot] = $this->_stripRealm($next->name());
                }

            }
        }
        return $ret;
    }


    
}