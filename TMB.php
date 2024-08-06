<?php

class LootItem {
    public $id;
    public $zoneName;
    public $source;
    public $name;
    public $url;
}

class WishListItem {
    public $listNum;
    public $dateCreated;
    public $dateUpdated;
    public $itemId;
    public $order;
    public $tier;
    public $bOffspec;
    public $bReceived;
    public $dateReceived;
}

class PrioItem {
    public $itemId;
    public $order;
    public $tier;
    public $bReceived;
    public $dateReceived;
    public $dateCreated;
    public $dateUpdated;
}

class ReceivedItem {
    public $itemId;
    public $tier;
    public $bOffspec;
    public $dateReceived;
}

class TMB {
    private static $mInstance = NULL;
        
    private $mTmbFilepath = NULL;

    private $mRoster = array();
    
    private $mLootTable = array();


     //PRIVATE
    private function __construct($tmbFilepath) {
        if (empty($tmbFilepath)) {
            die("Error:  TMB filepath not provided.\n");
        }
        $this->mTmbFilepath = $tmbFilepath;
        $this->parseLootTable("classic-loot-table.csv");
        $this->parse();
    }

    //PROTECTED

    protected function parseLootTable($filepath) {
        $lines = file($filepath);
        $header = array_shift($lines);
        foreach ($lines as $line) {
            $csv = str_getcsv($line);
            $zoneName = $csv[0];
            $source = $csv[1];
            $name = $csv[2];
            $id = $csv[4];
            $url = $csv[5];
            $item = new LootItem;
            $item->id = $id;
            $item->zoneName = $zoneName;
            $item->source = $source;
            $item->name = $name;
            $item->url = $url;
            $this->mLootTable[$id] = $item;
        }
    }
    
    protected function sortPrio($itemId,$chars) {
    
        usort($chars, function($a,$b) use ($itemId) {
            return -($a->itemPrioIndex($itemId) <=> $b->itemPrioIndex($itemId));
        });
       
        return $chars;
    }

    //PUBLIC

    public static function getInstance($tmbFilepath = '') {
        if (self::$mInstance == NULL) {
            self::$mInstance = new TMB($tmbFilepath);
        }
        return self::$mInstance;
    }

/*     public function useRoster(Roster $roster) {
        $this->mRoster = $roster;
    }
*/
    public function roster() {
        return $this->mRoster;
    }
    
    public function lootItemById($itemId) {
        $ret = NULL;
        if (array_key_exists($itemId,$this->mLootTable)) {
            $ret = $this->mLootTable[$itemId];
        }
        return $ret;
    }
    
    public function itemNameById($itemId) {
        $ret = NULL;
        if (array_key_exists($itemId,$this->mLootTable)) {
            $ret = $this->mLootTable[$itemId]->name;
        }
        return $ret;
    }
    
    public function itemIdByName($itemName) {
        $ret = NULL;
        foreach ($this->mLootTable as $itemId -> $item) {
            if ($item->name == $itemName) {
                $ret = $itemId;
                break;
            }
        }
        return $ret;
    }
    
 
    public function parse() {

        $tmbJson = file_get_contents($this->mTmbFilepath);
        $tmbData = json_decode($tmbJson,true);
        foreach ($tmbData as $c) {
            $nameParts = explode("-",$c['name']);
            if (count($nameParts) !== 2) {
                echo "INVALID NAME in TMB dump!\n";
                print_r($nameParts);
                continue;
            }
            $name = ucfirst(strtolower($nameParts[0]));
            $realm = ucfirst(strtolower($nameParts[1]));
            
            $received = array();
            $wishlist = array();
            $prios = array();

            //add received items
            
            if (isset($c['received'])) {
                
                foreach ($c['received'] as $item) {
                    $r = new ReceivedItem;
                    $r->itemId = $item['item_id'];
                    $r->tier = $item['guild_tier'];
                    $r->dateReceived = new DateTime($item['pivot']['received_at']);
                    $r->bOffspec = $item['pivot']['is_offspec'];
                    array_push($received,$r);                
                }
            }
            
            //add wish list
            
            if (isset($c['wishlist'])) {
                foreach ($c['wishlist'] as $item) {
                    $w = new WishListItem;
                    $w->listNum = $item['list_number'];
                    $w->order = $item['pivot']['order'];
                    if (!is_null($item['parent_item_id'])) {
                        $w->itemId = $item['parent_item_id']; 
                    } else {
                        $w->itemId = $item['item_id'];
                    }
                    $w->tier = $item['guild_tier'];
                    $w->bOffspec = $item['pivot']['is_offspec'];
                    $w->bReceived = $item['pivot']['is_received'];
                    if (isset($item['pivot']['received_at'])) {
                        $w->dateReceived = new DateTime($item['pivot']['received_at']);
                    }
                    if (isset($item['pivot']['updated_at'])) {
                        $w->dateUpdated = new DateTime($item['pivot']['updated_at']);
                    }
                    $w->dateCreated = new DateTime($item['pivot']['created_at']);
                    array_push($wishlist,$w);
                }
            }
        
            //add prios
            
            if (isset($c['prios'])) {
                foreach ($c['prios'] as $item) {
                    $p = new PrioItem;
                    $p->itemId = $item['item_id'];
                    $p->tier = $item['guild_tier'];
                    $p->order = $item['pivot']['order'];
                    $p->bReceived = $item['pivot']['is_received'];
                    if (isset($item['pivot']['received_at'])) {
                        $p->dateReceived = new DateTime($item['pivot']['received_at']);
                    }
                    if (isset($item['pivot']['updated_at'])) {
                        $p->dateUpdated = new DateTime($item['pivot']['updated_at']);
                    }
                    $p->dateCreated = new DateTime($item['pivot']['created_at']);
                    array_push($prios,$p);
                }
            }
            $row = [
                'tmb_id' => $c['id'],
                'name' => $name,
                'realm' => $realm,
                'race' => $c['race'],
                'class' => $c['class'],
                'level' => $c['level'],
                'discord_name' => $c['username'],
                'discord_id' => $c['discord_id'],
                'raid_group_name' => $c['raid_group_name'],
                'prof_1' => $c['profession_1'],
                'prof_2' => $c['profession_2'],
                'spec' => $c['spec'],
                'archetype' => $c['archetype'],
                'sub_archetype' => $c['sub_archetype'],
                'public_note' => $c['public_note'],
                'officer_note' => $c['officer_note'],
                'received' => $received,
                'wishlist' => $wishlist,
                'prios' => $prios
            ];
            array_push($this->mRoster,$row);
        }
    }
}

?> 
