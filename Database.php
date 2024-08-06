<?php
class Database {
    protected static $instance;
    protected function __construct() {}

    protected static function createTables() {
        $createTableStatements = [
            'DROP TABLE IF EXISTS "character"',
            'DROP TABLE IF EXISTS "zone_attendance"',
            'DROP TABLE IF EXISTS "attendance"',
            'DROP TABLE IF EXISTS "performance"',
            'DROP TABLE IF EXISTS "report"',
            'DROP TABLE IF EXISTS "item"',
            'DROP TABLE IF EXISTS "prio"',
            'DROP TABLE IF EXISTS "wishlist"',
            'DROP TABLE IF EXISTS "received"',
            'CREATE TABLE IF NOT EXISTS "zone" (
                "id"	INTEGER NOT NULL UNIQUE,
                "name"	TEXT NOT NULL,
                PRIMARY KEY("id")
            )',
            'CREATE TABLE IF NOT EXISTS "zone_attendance" (
                "zone_id"	INTEGER NOT NULL,
                "character_wcl_id"	INTEGER NOT NULL,
                "date_first"	DATETIME
            )',
            'CREATE TABLE IF NOT EXISTS "character" (
                "id" INTEGER PRIMARY KEY,
                "blizzard_id"	INTEGER UNIQUE,
                "wcl_id"	INTEGER UNIQUE,
                "tmb_id"	INTEGER UNIQUE,
                "guild_name"  TEXT,
                "discord_name"  TEXT,
                "discord_id"	INTEGER,
                "name"	TEXT NOT NULL,
                "realm"	TEXT,
                "level"	INTEGER,
                "race"	TEXT,
                "class"	TEXT,
                "spec" TEXT,
                "archetype"	TEXT,
                "sub_archetype"	TEXT,
                "prof_1"	TEXT,
                "prof_2"	TEXT,
                "guild_rank"	INTEGER,
                "public_note"	TEXT,
                "officer_note"	TEXT,
                "raid_group_name"	TEXT,
                /*"avg_ilvl"	INTEGER,
                "last_login"	DATETIME,*/
                "updated_at"    DATETIME DEFAULT CURRENT_TIMESTAMP
            )',
            'CREATE TABLE IF NOT EXISTS "attendance" (
                "report_id"	TEXT NOT NULL,
                "character_wcl_id"	INTEGER NOT NULL,
                PRIMARY KEY("report_id","character_wcl_id")
            )',
            'CREATE TABLE IF NOT EXISTS "performance" (
                "character_wcl_id"	INTEGER NOT NULL,
                "zone_id"	INTEGER NOT NULL,
                "spec" TEXT,
                "role" TEXT,
                "best"	REAL,
                "median"	REAL,
                "total"	REAL,
                PRIMARY KEY("character_wcl_id","zone_id")
            )',
            'CREATE TABLE IF NOT EXISTS "report" (
                "id"	TEXT NOT NULL UNIQUE,
                "zone_id"	INTEGER NOT NULL,
                "title"	TEXT,
                "start_time" DATETIME,
                "end_time"	DATETIME,
                PRIMARY KEY("id")
            )',
            'CREATE TABLE IF NOT EXISTS "event" (
                "id"	INTEGER NOT NULL,
                "name"	INTEGER NOT NULL,
                "zone_name" TEXT,
                "start"	DATETIME NOT NULL,
                "description"	TEXT,
                "created_by" TEXT,
                "channel_name" TEXT,
                "channel_id" INTEGER,
                "server_id" INTEGER,
                PRIMARY KEY("id")
            )',
            'CREATE TABLE IF NOT EXISTS "event_signup" (
                "event_id"	INTEGER NOT NULL,
                "discord_id"	INTEGER NOT NULL,
                "name" TEXT,
                "position" INTEGER,
                "class"	TEXT,
                "role"	TEXT,
                "bench" INTEGER DEFAULT 0,
                "late" INTEGER DEFAULT 0,
                "tentative" INTEGER DEFAULT 0,
                "absent" INTEGER DEFAULT 0,
                "signed_up_at"	DATETIME,
                PRIMARY KEY("event_id","discord_id")
            )',
            'CREATE TABLE IF NOT EXISTS "item" (  
                "id"	INTEGER NOT NULL UNIQUE,
                "name"	TEXT NOT NULL,
                "zone_name"     TEXT,
                "source"    TEXT,
                "guild_tier"	INTEGER,
                "url"	TEXT,
                PRIMARY KEY("id")
            )',
            'CREATE TABLE IF NOT EXISTS "prio" (
                "character_tmb_id"	INTEGER NOT NULL,
                "item_id"	INTEGER NOT NULL,
                "rank"	INTEGER NOT NULL DEFAULT 0,
                "received_at"   DATETIME,
                "created_at"    DATETIME,
                "updated_at"	DATETIME,
                PRIMARY KEY("character_tmb_id","item_id")
            )',
            'CREATE TABLE IF NOT EXISTS "wishlist" (
                "character_tmb_id"	INTEGER NOT NULL,
                "item_id"	INTEGER NOT NULL,
                "rank"	INTEGER NOT NULL,
                "greed" INTEGER,
                "ultra" FLOAT,
                "received_at"   DATETIME,
                "created_at"    DATETIME,
                "updated_at"	DATETIME,
                PRIMARY KEY("character_tmb_id","item_id","rank")
            )',
            'CREATE TABLE IF NOT EXISTS "received" (
                "character_tmb_id"	INTEGER NOT NULL,
                "item_id"	INTEGER NOT NULL,
                "greed" INTEGER,
                "received_at"	DATETIME,
                PRIMARY KEY("character_tmb_id","item_id","received_at")
            )',
            ];
            foreach ($createTableStatements as $statement) {
                self::$instance->exec($statement);
            }
    }

    public static function getInstance() {
        if (empty(self::$instance)) {
            if(empty(self::$instance)) {

                try {
                    self::$instance = new PDO('sqlite:roster.db');
                    self::$instance->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
                    self::createTables();
                } catch(PDOException $error) {
                    echo $error->getMessage();
                }
    
            }
    
            return self::$instance;
        }
    }

}
?>
