#!/usr/bin/php
<?php
require("Database.php");
require("WCL.php");
require("Blizzard.php");
require("TMB.php");
require("RaidHelper.php");
//require_once 'Console/Table.php';

$ini = [];

for ($i = 1; $i < $argc; $i++) {
    if (file_exists($argv[$i])) {
        $ini = parse_ini_file($argv[$i],true);
        break;
    }
}

if (empty($ini)) {
    echo "Please provide valid configuration filename as command line argument.\n";
    exit();
} 

$guildName = $ini["general"]["guildName"];
$guildRealm = $ini["general"]["guildRealm"];
$region = $ini["general"]["region"];
$serverTimeZone = $ini["general"]["serverTimeZone"];
$faction = $ini["general"]["faction"];
$performanceZone = $ini["general"]["performanceZone"];
$sinceDate = new DateTime("now");
$sinceDate->modify("-2 months");
$raiderRank = $ini["general"]["raiderRank"]; //guild ranks between 0 (guild master) and this number are considered "raiders"
$rhApiKey = $ini["raid-helper"]["apiKey"]; //used to fix raid-helper signup names to match TMB character names
$rhServerId = $ini["raid-helper"]["serverId"];
$wclClientId = $ini["warcraftlogs"]["clientId"];
$wclClientSecret = $ini["warcraftlogs"]["clientSecret"];
$wclGameVariant = $ini["warcraftlogs"]["gameVariant"];
$blizzClientId = $ini["blizzard"]["clientId"];
$blizzClientSecret = $ini["blizzard"]["clientSecret"];
$blizzGameVariant = $ini["blizzard"]["gameVariant"];

//ULTRA weights
$weights = [
    'U' => (double)$ini["general"]["U"],
    'L' => (double)$ini["general"]["L"],
    'T' => (double)$ini["general"]["T"],
    'R' => (double)$ini["general"]["R"],
    'A' => (double)$ini["general"]["A"],
    'P' => (double)$ini["general"]["P"]
];

$db = Database::getInstance();
$tmb = TMB::getInstance("character-json.json"); //fresh download from thatsmybis.com
$wcl = WCL::getInstance($region,$serverTimeZone,$wclGameVariant,$wclClientId,$wclClientSecret);
$blizz = Blizzard::getInstance($region,$serverTimeZone,$blizzGameVariant,$blizzClientId,$blizzClientSecret);

$relatedRealms = $blizz->relatedRealms($guildRealm);

$rh = new RaidHelper($rhApiKey,$serverTimeZone,$serverTimeZone,$relatedRealms,$db);

if (!file_exists('prios')) {
    mkdir('prios');
}
if (!file_exists('reports')) {
    mkdir('reports');
}


function slug($str) {
    return str_replace([' ', "'"], ['-', ''], mb_strtolower($str));
}

//fetch gulid roster from blizzard

$sql = <<<SQL
    INSERT INTO character 
        (blizzard_id,name,realm,guild_name,level,race,class,guild_rank)
        VALUES (?,?,?,?,?,?,?,?)
        ON CONFLICT (blizzard_id) DO UPDATE
            SET name=excluded.name,
                guild_name=excluded.guild_name,
                level=excluded.level,
                guild_rank=excluded.guild_rank,
                updated_at=excluded.updated_at

SQL;
$insertCharacterFromBlizz = $db->prepare($sql);


$r = $blizz->guildRoster($guildName,$guildRealm);


$curDateTime = new DateTime("now");

$db->beginTransaction();

foreach ($r->members as $member) {    
    if ($member->character->level == 60) {
        
        $iLvl = 0;
        $lastLogin = new DateTime();
        $lastLogin->setTimestamp(0);
        
        
        $row = [
            $member->character->id,
            $member->character->name,
            $member->character->realm->name,
            $guildName,
            $member->character->level,
            $blizz->playableRaceById($member->character->playable_race->id),
            $blizz->playableClassById($member->character->playable_class->id),
            $member->rank,
        ];
        echo "Inserting " . $member->character->name . "-".$member->character->realm->name."\n";
        $insertCharacterFromBlizz->execute($row);
        
    }
} 
$db->commit();


//fetch TMB data
$sql = <<<SQL
    UPDATE character
    SET tmb_id = :tmb_id,
        discord_name = :discord_name, 
        discord_id = :discord_id,
        raid_group_name = :raid_group_name,
        prof_1 = :prof_1,
        prof_2 = :prof_2,
        spec = :spec,
        archetype = :archetype,
        sub_archetype = :sub_archetype,
        public_note = :public_note,
        officer_note = :officer_note
    WHERE name = :name AND realm = :realm
SQL;

$updateCharacterFromTMB = $db->prepare($sql);

$sql = <<<SQL
    INSERT OR IGNORE INTO character (
        tmb_id,name,realm,race,class,level,
        discord_name,discord_id,raid_group_name,
        prof_1,prof_2,spec,archetype,sub_archetype,public_note,officer_note)
    SELECT :tmb_id,:name,:realm,:race,:class,:level,
           :discord_name,:discord_id,:raid_group_name,
           :prof_1,:prof_2,:spec,:archetype,:sub_archetype,:public_note,:officer_note
    WHERE (SELECT Changes() = 0)
SQL;

$insertCharacterFromTMB = $db->prepare($sql);

$sql = <<<SQL
    INSERT OR IGNORE INTO item (id,name,zone_name,source,guild_tier,url)
    VALUES (?,?,?,?,?,?)
SQL;
$insertItem = $db->prepare($sql);

$sql = <<<SQL
    INSERT OR IGNORE INTO received (character_tmb_id,item_id,greed,received_at)
    VALUES (?,?,?,?)
SQL;
$insertReceived = $db->prepare($sql);

$sql = <<<SQL
    REPLACE INTO wishlist (character_tmb_id,item_id,rank,greed,received_at,created_at,updated_at)
    VALUES (?,?,?,?,?,?,?)
SQL;
$insertWishlist = $db->prepare($sql);

$sql = <<<SQL
    REPLACE INTO prio (character_tmb_id,item_id,rank,received_at,created_at,updated_at)
    VALUES (?,?,?,?,?,?)
SQL;
$insertPrio = $db->prepare($sql);

$db->beginTransaction();

foreach ($tmb->roster() as $c) {
    
    $row = [
        'tmb_id' => $c['tmb_id'],
        'name' => $c['name'],
        'realm' => $c['realm'],
        'race' => $c['race'],
        'class' => $c['class'],
        'level' => $c['level'],
        'discord_name' => $c['discord_name'],
        'discord_id' => $c['discord_id'],
        'raid_group_name' => $c['raid_group_name'],
        'prof_1' => $c['prof_1'],
        'prof_2' => $c['prof_2'],
        'spec' => $c['spec'],
        'archetype' => $c['archetype'],
        'sub_archetype' => $c['sub_archetype'],
        'public_note' => $c['public_note'],
        'officer_note' => $c['officer_note'],
    ];
    //var_dump($update_row);
    //remove columns not used in UPDATE to prevent column count mismatch
    $update_row = array_diff_key($row,['race' => NULL, 'level' => NULL, 'class' => NULL]);

    $updateCharacterFromTMB->execute($update_row);
    
    $insertCharacterFromTMB->execute($row);
    echo "Updated " . $c['name'] . " discord id " .$c['discord_id']. "\n";

    //get received, wishlist, and prios
    foreach ($c['received'] as $i) {
        
        $lootItem = $tmb->lootItemById($i->itemId);
        if ($lootItem) {
            $itemRow = [ //id,name,zone_name,source,guild_tier,url
                $i->itemId,
                $lootItem->name,
                $lootItem->zoneName,
                $lootItem->source,
                $i->tier,
                $lootItem->url
            ];
            $insertItem->execute($itemRow);
        } 
        $recRow = [ //character_tmb_id,item_id,greed,received_at
            $c['tmb_id'],
            $i->itemId,
            $i->bOffspec,
            $i->dateReceived->format("Y-m-d H:i:s")
        ];
        $insertReceived->execute($recRow);
    }
    foreach ($c['wishlist'] as $i) {
        $lootItem = $tmb->lootItemById($i->itemId);
        if ($lootItem) {
            $itemRow = [ //id,name,zone_name,source,guild_tier,url
                $i->itemId,
                $lootItem->name,
                $lootItem->zoneName,
                $lootItem->source,
                $i->tier,
                $lootItem->url
            ];
            $insertItem->execute($itemRow);
        } 
        $wlRow = [ //character_tmb_id,item_id,rank,received_at,created_at,updated_at
            $c['tmb_id'],
            $i->itemId,
            $i->order,
            $i->bOffspec,
            $i->bReceived ? $i->dateReceived->format("Y-m-d H:i:s") : 0,
            $i->dateCreated->format("Y-m-d H:i:s"),
            $i->dateUpdated->format("Y-m-d H:i:s")
        ];
        $insertWishlist->execute($wlRow);
        /*
        if ($i->bReceived) {
            $recRow = [ //character_tmb_id,item_id,greed,received_at
                $c['tmb_id'],
                $i->itemId,
                $i->bOffspec,
                $i->dateReceived->format("Y-m-d H:i:s")
            ];
            $insertReceived->execute($recRow);
        }
        */
    }
    foreach ($c['prios'] as $i) {
        $lootItem = $tmb->lootItemById($i->itemId);
        $itemRow = [ //id,name,zone_name,source,guild_tier,url
            $i->itemId,
            $lootItem->name,
            $lootItem->zoneName,
            $lootItem->source,
            $i->tier,
            $lootItem->url
        ];
        $insertItem->execute($itemRow);
        $prioRow = [ //character_tmb_id,item_id,rank,received_at,created_at,updated_at
            $c['tmb_id'],
            $i->itemId,
            $i->order,
            $i->bReceived ? $i->dateReceived->format("Y-m-d H:i:s") : 0,
            $i->dateCreated->format("Y-m-d H:i:s"),
            $i->dateUpdated->format("Y-m-d H:i:s")
        ];
        $insertPrio->execute($prioRow);
    }


}

$db->commit();


//get profiles for those not in guild

$sql = <<<SQL
    SELECT name,realm,class
    FROM character
    WHERE blizzard_id IS NULL
SQL;
$queryCharactersWithNoBlizzInfo = $db->prepare($sql);

$sql = <<<SQL
    UPDATE character
    SET blizzard_id = :blizzard_id,
        race = :race,
        class = :class,
        level = :level,
        guild_name = :guild_name
    WHERE name = :name AND realm = :realm
SQL;
$updateCharacterWithBlizzInfo = $db->prepare($sql);

$db->beginTransaction();

$queryCharactersWithNoBlizzInfo->execute();
while ($row = $queryCharactersWithNoBlizzInfo->fetch()) {
    $foundChar = false;
    if (!empty($row['name']) && !empty($row['realm'])) {
        $foundChar = $blizz->profileSummary($row['name'],$row['realm']);
    } else { //no realm, need to search
        $possibleCharacters = $blizz->findCharacters($row['name'],$faction,$row['class'],$guildRealm);        
        if (count($possibleCharacters) > 1) {
            $numMaxLevel = 0;
            $possFound = false;
            foreach ($possibleCharacters as $p) {
                if ($p->level == 60) {
                    $numMaxLevel++;
                    $possFound = $p;
                }
            }
            if ($numMaxLevel == 1) $foundChar = $p;
        } else if (count($possibleCharacters) == 1) {
            //only 1 match, good
            $foundChar = $possibleCharacters[0];
            
        } else {
            echo "Unable to find just 1 level 60 matching ".$row['name'] .' '. $row['class'] . "\n";
        }
    }
    if (isset($foundChar->id)) {
        $insertRow = [
            'blizzard_id' => $foundChar->id,
            'name' => $foundChar->name,
            'realm' => $foundChar->realm->name,
            'race' => $foundChar->race->name,
            'class' => $foundChar->character_class->name,
            'level' => $foundChar->level,
            'guild_name' => isset($foundChar->guild) ? $foundChar->guild->name : NULL,
            //'avg_ilvl' => $foundChar->average_item_level,
            //'last_login' => $foundChar->last_login_timestamp/1000
        ];
        echo "Updating character " . $row['name'] . " with blizzard info.\n";

        $updateCharacterWithBlizzInfo->execute($insertRow);
        
    } else {
        echo "CHARACTER NOT FOUND ". $row['name'] . "-" . $row['realm'] . "\n";
    }

}

$db->commit();

//fetch WCL data
$sql = <<<SQL
    INSERT OR IGNORE INTO report (id,zone_id,title,start_time,end_time)
    VALUES (?,?,?,?,?)
SQL;
$insertReport = $db->prepare($sql);

$sql = <<<SQL
    INSERT OR IGNORE INTO attendance (report_id,character_wcl_id)
    VALUES (?,?)
SQL;
$insertAttendance = $db->prepare($sql);

$sql = <<<SQL
    SELECT date_first FROM zone_attendance
    WHERE zone_id = ? AND character_wcl_id = ?
SQL;
$queryDateFirstAttendedZone = $db->prepare($sql);

$sql = <<<SQL
    INSERT OR IGNORE INTO zone_attendance(zone_id,character_wcl_id,date_first)
    VALUES (?,?,?)
SQL;
$insertZoneAttendance = $db->prepare($sql);

$sql = <<<SQL
    INSERT OR IGNORE INTO zone(id,name) VALUES (?,?)
SQL;

$insertZone = $db->prepare($sql);

$sql = <<<SQL
    UPDATE character
    SET wcl_id = :wcl_id
    WHERE name = :name AND realm = :realm
SQL;
$updateCharacterFromWCL = $db->prepare($sql);

$sql = <<<SQL
    REPLACE INTO performance (character_wcl_id,zone_id,spec,role,best,median,total)
    VALUES (?,?,?,?,?,?,?)
SQL;

$insertPerformance = $db->prepare($sql);


$reports = $wcl->reports($guildName,$guildRealm);

$raiders = array();
$recentRaiders = array();


$db->beginTransaction();

foreach ($reports as $r) {
    if ($r->zoneId <= 0) continue;
    $row = [
        $r->id,$r->zoneId,$r->title,
        $r->startDateTime->format('Y-m-d H:i:s'),
        $r->endDateTime->format('Y-m-d H:i:s')
    ];
    $insertReport->execute($row);
    $insertZone->execute([$r->zoneId,$wcl->zoneName($r->zoneId)]);

    $bRecentReport = $r->startDateTime > $sinceDate;


    foreach ($r->characters as $c) {
        
        
        $insertAttendance->execute([$r->id,$c->canonicalID]);
        $queryDateFirstAttendedZone->execute([$r->zoneId,$c->canonicalID]);
        
        $dateFirstAttendedZone = $queryDateFirstAttendedZone->fetch();
        if (!$dateFirstAttendedZone) {
            $insertZoneAttendance->execute([$r->zoneId,$c->canonicalID,$r->startDateTime->format('Y-m-d H:i:s')]);
        }

        $raiders[$c->canonicalID] = $c;
        if ($bRecentReport) {
            if (!isset($recentRaiders[$c->canonicalID])) {
                $recentRaiders[$c->canonicalID] = array();
            }
            if (!in_array($r->zoneId,$recentRaiders[$c->canonicalID])) {
                $recentRaiders[$c->canonicalID][] = $r->zoneId;
            }
        }

    }
}

foreach ($raiders as $wclId => $c) {
    //update database
    $class = $wcl->gameClassNameById($c->classID);
    
    $row = [
        'wcl_id' => $c->canonicalID,
        'name' => $c->name,
        'realm' => $c->server->name
    ];
    $updateCharacterFromWCL->execute($row);
}


foreach ($recentRaiders as $wclId => $zoneIds) {
    //get zoneRankings
    
    foreach ($zoneIds as $zoneId) {
        $zoneRankings = $wcl->zoneRankingsById($wclId,$zoneId);
        $row = [
            $wclId,
            $zoneId,
            $zoneRankings['spec'],
            $zoneRankings['role'],
            $zoneRankings['bestPerfAvg'],
            $zoneRankings['medianPerfAvg'],
            $zoneRankings['bestTotalAvg']
        ];
        $insertPerformance->execute($row);
    }
}


$db->commit();

// fetch event data

$sql = <<<SQL
    REPLACE INTO event (id,name,zone_name,start,description,
        created_by,channel_name,channel_id,server_id)
    VALUES (?,?,?,?,?,?,?,?,?)
SQL;
$insertEvent = $db->prepare($sql);

$sql = <<<SQL
    REPLACE INTO event_signup (event_id,discord_id,name,position,
        class,role,bench,late,tentative,absent,signed_up_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
SQL;
$insertEventSignup = $db->prepare($sql);


$db->beginTransaction();
$events = $rh->parseEvents($rhServerId,$curDateTime->getTimestamp());

foreach ($events as $e) {
    $eventRow = [
        $e->id,
        $e->name,
        $e->zoneName(),
        $e->startTime->format("Y-m-d H:i:s"),
        $e->description,
        $e->createdBy,
        $e->channelName,
        $e->channelId,
        $e->serverId
    ];
    $insertEvent->execute($eventRow);
    foreach ($e->signups as $s) {
        $signupRow = [
            $e->id,
            $s->discordId,
            $s->name,
            $s->position,
            $s->characterClass,
            $s->role,
            $s->isBenched,
            $s->isLate,
            $s->isTentative,
            $s->isAbsent,
            $s->dateTime->format("Y-m-d H:i:s")
        ];
        $insertEventSignup->execute($signupRow);
    }
};
$db->commit();




$sql = <<<SQL
    SELECT 	z.id AS 'zone_id', z.name AS 'zone_name', 
		COUNT(r.id) AS 'attended', 
		(   SELECT COUNT(r2.id) 
			FROM report r2 
			JOIN zone z2, zone_attendance za2, character c2
			ON (z2.id=z.id AND z2.id=r2.zone_id AND za2.zone_id = z2.id 
				AND za2.character_wcl_id = c2.wcl_id AND c2.wcl_id = c.wcl_id)
			WHERE r2.start_time >= za2.date_first) AS 'available' 
    FROM character c JOIN report r, zone z, attendance a
    ON (r.id=a.report_id AND z.id = r.zone_id AND c.wcl_id = a.character_wcl_id) 
    WHERE c.id = ? AND r.start_time >= ?
    GROUP BY zone_id;
SQL;
$queryAttendance = $db->prepare($sql);

$sql = <<<SQL
    SELECT
		COUNT(r.id) AS 'attended', 
		(   SELECT COUNT(r2.id) 
			FROM report r2 
			JOIN zone z2, zone_attendance za2, character c2
			ON (z2.id=z.id AND z2.id=r2.zone_id AND za2.zone_id = z2.id 
				AND za2.character_wcl_id = c2.wcl_id AND c2.wcl_id = c.wcl_id)
			WHERE r2.start_time >= za2.date_first AND r2.start_time >= :since_date) AS 'available' 
    FROM character c JOIN report r, zone z, attendance a
    ON (r.id=a.report_id AND z.id = r.zone_id AND c.wcl_id = a.character_wcl_id) 
    WHERE c.discord_id = :discord_id AND z.name = :zone_name AND r.start_time >= :since_date
SQL;
$queryZoneAttendance = $db->prepare($sql);

$sql = <<<SQL
    SELECT p.spec,p.role,p.best,p.median,p.total
    FROM performance p 
    JOIN character c, zone z
    ON (p.character_wcl_id = c.wcl_id AND z.id = p.zone_id)
    WHERE c.id = ? AND z.name = ?
SQL;
$queryPerformance = $db->prepare($sql);

$sql = <<<SQL
    SELECT c.id, c.name || '-' || c.realm AS 'character', c.discord_name, c.discord_id, c.race, c.class, c.guild_rank,
		p.spec,p.role,p.best,p.median,(p.best+p.median)/2 perf,p.total,
		COUNT(r.id) AS 'attended', 
        (   SELECT COUNT(r2.id) 
			FROM report r2 
			JOIN zone_attendance za
			ON (z.id=r2.zone_id AND za.zone_id = z.id 
				AND za.character_wcl_id = c.wcl_id)
			WHERE r2.start_time >= za.date_first AND r2.start_time >= :since_date) AS 'available'
    FROM character c JOIN report r, zone z, attendance a, performance p
    ON (r.id=a.report_id AND z.id = r.zone_id AND c.wcl_id = a.character_wcl_id 
		AND p.zone_id = z.id AND p.character_wcl_id = c.wcl_id) 
    WHERE z.name = :zone_name AND r.start_time >= :since_date AND 'attended' > 0 AND p.best > 0
    GROUP BY c.discord_id, c.id
    ORDER BY c.class,p.role,perf DESC
SQL;
$queryZoneAP = $db->prepare($sql);

$sql = <<<SQL
    SELECT count(r.id) AS 'no-shows'
    FROM event e
    JOIN character c, event_signup es, report r
    ON (c.discord_id = es.discord_id AND e.id = es.event_id AND es.class = c.class
        AND r.start_time BETWEEN datetime(e.start,"-30 minutes") AND datetime(e.start,"+1 hour")
    )
    WHERE es.absent != 1 AND es.tentative != 1 AND es.bench != 1 AND c.discord_id = ? AND zone_name = ?
        AND NOT EXISTS (SELECT * FROM attendance a 
                        WHERE a.report_id = r.id 
                        AND a.character_wcl_id IN (select wcl_id FROM character where discord_id = c.discord_id))
SQL;
$queryNoShows = $db->prepare($sql);

//create attendance/performance output
$apCSVFileName = 'reports/attendance_performance_'.slug($guildName).'_'.slug($performanceZone)
    .'_'.$curDateTime->format("Y-m-d").".csv";
$apCSV = fopen($apCSVFileName,'w');
$apHeaders = ['discord_name','no_shows','character','rank','race','class','spec','role','att','perf','best','median','DPS'];
fputcsv($apCSV,$apHeaders);

//$tbl = new Console_Table();
//$tbl->setHeaders($apHeaders);

$queryZoneAP->execute(['zone_name' => $performanceZone, 'since_date' => $sinceDate->format('Y-m-d H:i:s')]);
$currentClass = 'Druid';
while ($ap = $queryZoneAP->fetch(PDO::FETCH_ASSOC)) {
    $queryNoShows->execute([$ap['discord_id'],$performanceZone]);
    $noShowCount = $queryNoShows->fetch()[0];
    if ($ap['class'] !== $currentClass) {
        $currentClass = $ap['class'];
        //$tbl->addSeparator();
    }
    $r = [
        $ap['discord_name'],
        $noShowCount,
        $ap['character'],
        $ap['guild_rank'],
        $ap['race'],
        $ap['class'],
        $ap['spec'],
        $ap['role'],
        round(($ap['attended']/$ap['available'])*100)."%",
        round($ap['perf'],2),
        round($ap['best'],2),
        round($ap['median'],2),
        $ap['role'] == 'Healer' ? 0 : round($ap['total'],2)
    ];
    //echo $ap['character'] . " attended " . $ap['attended'] . "/" . $ap['available'] . "\n";
    //$tbl->addRow($r);
    fputcsv($apCSV,$r);
}
fclose($apCSV);
//echo $tbl->getTable();

//build rosters from sign-ups

//get signups
$sql = <<<SQL
    SELECT * FROM 
        (SELECT e.id AS 'event_id', e.zone_name, e.start, e.name as 'event_name',
            c.name, c.realm, c.discord_id, c.id AS 'character_id',
            es.name AS 'signup_name', 
            es.position, es.role, es.bench, es.late, es.tentative, es.absent 
        FROM event_signup es 
        JOIN event e, character c 
        ON (c.discord_id=es.discord_id AND es.class = c.class AND e.id = es.event_id) 
        WHERE e.start >= ?
        GROUP BY event_id,character_id ORDER BY event_id,character_id)
    GROUP BY event_id,discord_id ORDER BY event_id,position;
SQL;
$querySignups = $db->prepare($sql);

$querySignups->execute([$curDateTime->format('Y-m-d H:i:s')]);

$currentEventId = 0;
while ($s = $querySignups->fetch(PDO::FETCH_ASSOC)) {
    //fix signup name
    $fullName = $s['name'].'-'.$s['realm'];
    if ($s['signup_name'] != $fullName) {
        echo "Fixing signup name for event ".$s['event_name'].": ". $s['signup_name']. " to $fullName\n";
        $rh->fixEventSignupName($s['event_id'],$s['discord_id'],$fullName);
    }
}


//find top wishlist items grouped by zone name
$sql = <<<SQL
    SELECT i.zone_name, i.name AS 'item', i.id AS 'item_id',
           c.name || '-' || c.realm AS fullname, c.guild_rank, c.tmb_id,
           c.discord_name, c.discord_id, c.id, c.class, c.spec, c.level,
           c.raid_group_name, 
           (SELECT COUNT(p2.item_id)
            FROM prio p2
            JOIN character c2
            ON (c2.tmb_id = p2.character_tmb_id AND p2.received_at = 0)
            WHERE c2.discord_id = c.discord_id) AS 'num_prios',
           p.rank AS 'current_prio_rank',
           julianday() - julianday(w.updated_at) AS 'days_on_list',
           (SELECT COUNT(*) 
            FROM wishlist w2 
            WHERE i.id = w2.item_id AND w2.rank = 1 AND w2.received_at = 0) AS 'num_wishlisted'

    FROM item i
    JOIN wishlist w, character c
    ON (w.character_tmb_id = c.tmb_id 
        AND w.item_id = i.id)
    LEFT JOIN prio p
    ON (p.item_id = i.id AND p.character_tmb_id = c.tmb_id)
    WHERE w.rank = '1' AND w.received_at = '0' AND num_wishlisted > 1 AND i.guild_tier < 4
    GROUP BY i.zone_name,item,fullname
SQL;

$queryTopWishlistItems = $db->prepare($sql);

$sql = <<<SQL
    UPDATE wishlist
    SET ultra = ?
    WHERE character_tmb_id = ? AND item_id = ?
SQL;
$updateWishlistWithUltra = $db->prepare($sql);

$sql = <<<SQL
    SELECT COUNT(p.item_id) AS 'num_prios'
    FROM prio p
    JOIN character c
    ON (c.tmb_id = p.character_tmb_id)
    WHERE c.discord_id = ?
SQL;
$queryNumPrios = $db->prepare($sql);

$sql = <<<SQL
    SELECT i.name, i.guild_tier, r.received_at
    FROM item i
    JOIN received r, character c
    ON (r.item_id = i.id AND r.character_tmb_id = c.tmb_id)
    WHERE i.guild_tier < 4 AND r.greed != 1 AND c.discord_id = ? 
    	AND i.zone_name = ? AND r.received_at > DATE('now','-6 months')
    ORDER BY i.guild_tier
SQL;
$queryBigUpgrades = $db->prepare($sql);

$sql = <<<SQL
    SELECT count(r.id) AS 'num_reports'
    FROM report r
    JOIN zone z
    ON (z.id = r.zone_id)
    WHERE z.name = ? AND r.start_time >= ?
SQL;
$queryNumReports = $db->prepare($sql);



// build prios


$queryTopWishlistItems->execute();
$topWLItems = $queryTopWishlistItems->fetchAll(PDO::FETCH_ASSOC);
$sixMonths = 15778800; //seconds

$db->beginTransaction();
foreach ($topWLItems as $index => $w) {
    
    $queryPerformance->execute([$w['id'],$w['zone_name']]);
    $p = $queryPerformance->fetch(PDO::FETCH_ASSOC);

    $P = 0;
    $w['performance'] = 0;
    if ($p) {
        $P = $p['best']/100;
        $w['performance'] = $p['best'];
    }
    $w['P'] = $P;
    

    $epochStart = 0;
    $dateLastUpgrade = new DateTime("@$epochStart");

    
    //U - num big upgrades
    $U = 1;
    $queryBigUpgrades->execute([$w['discord_id'],$w['zone_name']]);
    $w['bigWins'] = array();
    while ($upgrade = $queryBigUpgrades->fetch(PDO::FETCH_ASSOC)) {
        $lastDate = new DateTime($upgrade['received_at']);
        if ($lastDate > $dateLastUpgrade) {
            $dateLastUpgrade = $lastDate;
        }

        $tier = $upgrade['guild_tier'];
        if (!isset($w['bigWins'][$tier])) {
            $w['bigWins'][$tier] = 0;
        }
        $w['bigWins'][$tier]++;
        $mod = 0;
        if ($tier == 1) {
            $mod = 0.10;
        } else if ($tier == 2) {
            $mod = 0.05;
        } else if ($tier == 3) {
            $mod = 0.025;
        }
        $U -= $mod;
    }
    $w['bigWinsStr'] = str_replace('=',':',http_build_query($w['bigWins'],null,', '));

    $U = max($U,0);
    $w['U'] = $U;
    $w['dateLastUpgrade'] = $dateLastUpgrade->getTimestamp() > 0 
        ? $dateLastUpgrade->format('Y-m-d') : '';
    //L - time on list 
    $L = 0;
    $secsOnList = $w['days_on_list'] * 86400;
    $L = $secsOnList / $sixMonths;
    $L = min($L,1);
    $w['L'] = $L;
    $w['listDays'] = round($w['days_on_list']);

    //T - time since big upgrade 
    $T = 1;
    $timeSinceBigUpgrade = 0;
    if ($dateLastUpgrade->getTimestamp() > 0) {
        $timeSinceBigUpgrade = $curDateTime->getTimestamp() - $dateLastUpgrade->getTimestamp();
        $T = $timeSinceBigUpgrade / $sixMonths;
        $T = min($T,1);
    }
    $w['T'] = $T;

    //R - num raids in zone attended in past 6 months 
    $sixMonthsAgo = clone $curDateTime;
    $sixMonthsAgo->modify('-6 month');
    $R = 0;
    $queryNumReports->execute([$w['zone_name'],$sixMonthsAgo->format('Y-m-d H:i:s')]);
    $numReports = $queryNumReports->fetch(PDO::FETCH_ASSOC);
    $queryZoneAttendance->execute([
        'discord_id' => $w['discord_id'],
        'zone_name' => $w['zone_name'],
        'since_date' => $sixMonthsAgo->format('Y-m-d H:i:s')
    ]);
    $zoneAttendance = $queryZoneAttendance->fetch(PDO::FETCH_ASSOC);

    if ($numReports) {
        $mod = 1 / $numReports['num_reports'];
        if ($zoneAttendance && $zoneAttendance['attended'] > 0) {
            $R = min($zoneAttendance['attended'],$numReports['num_reports']) * $mod;
            $R = min($R,1);
        }
    }
    $w['R'] = $R;
    $w['numRaidsAttended'] = $zoneAttendance['attended'];

    //A - recent attendance ratio 
    $A = 0;
    $queryZoneAttendance->execute([
        'discord_id' => $w['discord_id'],
        'zone_name' => $w['zone_name'],
        'since_date' => $sinceDate->format('Y-m-d H:i:s')
    ]);
    $recentAttendance = $queryZoneAttendance->fetch(PDO::FETCH_ASSOC);
    $w['recentAttendance'] = 0.0;
    if ($recentAttendance) {
        $avail = $recentAttendance['available'];
        $att = $recentAttendance['attended'];
        $w['numRecentRaidsAvailable'] = $avail;
        $w['numRecentRaidsAttended'] = $att;
        if ($att > $avail) {
            $att = $avail;
        }
        $A = $avail > 0 ? $att / $avail : 0.0;
        $w['recentAttendance'] = $A;
    }
    $w['A'] = $A;

    $U *= $weights['U'];
    $L *= $weights['L'];
    $T *= $weights['T'];
    $R *= $weights['R'];
    $A *= $weights['A'];
    $P *= $weights['P'];
    
    $ULTRA = ($U + $L + $T + $R + $A + $P) / 6;
    
    $ULTRA = min(1,$ULTRA);
    $w['ULTRA'] = round($ULTRA,3);

    $w['eligible'] = true;
    
    if ($w['level'] < 60 || $w['recentAttendance'] < 0.5 || $w['numRaidsAttended'] < 8 || $w['P'] == 0) { 
        //not level 60, haven't done 8 raids or < 50% recent attendance, or P == 0  
        
        $w['eligible'] = false;
    } 
    $topWLItems[$index] = $w;
    $updateWishlistWithUltra->execute([$w['ULTRA'],$w['tmb_id'],$w['item_id']]);
    
}
$db->commit();

if (!empty($topWLItems)) {

    usort ($topWLItems,function($a,$b) {
        if ($a['zone_name'] == $b['zone_name']) {
            if ($a['item'] == $b['item']) {
                return -($a['ULTRA'] <=> $b['ULTRA']);
            } else {
                return $a['item'] <=> $b['item'];
            }
        } else {
            return $a['zone_name'] <=> $b['zone_name'];
        }
    });
    $prioHeaders = ['zone','item','character','guild rank','discord name','class','spec','# prios',
    'performance','big wins (tier:#)','list time','last big win','# raids','attend%','ULTRA','eligible','current prio rank'];
    $prioCSVFileName = 'ultra_prios_'.slug($guildName).'_'.$curDateTime->format('Y-m-d').".csv";
    $prioCSV = fopen('prios/'.$prioCSVFileName,'w');
    //$tbl = new Console_Table();
    //$tbl->setHeaders($prioHeaders);
    fputcsv($prioCSV,$prioHeaders);

    $currentItemName = $topWLItems[0]['item'];

    foreach ($topWLItems as $i) {
        if ($i['item'] != $currentItemName) {
            //$tbl->addSeparator();
            $currentItemName = $i['item'];
        }
        $eligible = $i['eligible'] ? 'Y' : 'N';
        $row = [
            $i['zone_name'],
            $i['item'],
            $i['fullname'],
            $i['guild_rank'],
            $i['discord_name'],
            $i['class'],
            $i['spec'],
            $i['num_prios'],
            //$i['avg_ilvl'],
            round($i['performance'],1),
            $i['bigWinsStr'],
            $i['listDays'].'d',
            $i['dateLastUpgrade'],
            $i['numRaidsAttended'],
            round($i['recentAttendance']*100).'%',
            $i['ULTRA'],
            $eligible,
            $i['current_prio_rank']
        ];
        //$tbl->addRow($row);
        fputcsv($prioCSV,$row);
    }
    fclose($prioCSV);
    //echo $tbl->getTable();
}

?>
