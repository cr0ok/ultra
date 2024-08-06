# ULTRA

A loot prio recommendation algorithm developed for The Unyielding-Mankrik which considers:

- **U** - How many "big upgrades" (S-, A-, or B-tiered items) a player has received (on any character associated with their discord ID) in the zone where the item drops within the past 6 months, not counting items won with "off-spec" rolls
- **L** - How long the item has been ranked #1 on the character's wish list
- **T** - How long it's been since the player has received a "big upgrade" from that zone on any character
- **R** - How many raids the player has attended (on any character) in the zone where the item drops over the past 6 months
- **A** - The player's recent attendance ratio (raids attended/raids available) from the past 2 months (on any character)
- **P** - The specific character's best performance in the zone where the item drops (according to warcraftlogs.com)

You can adjust the weight values for any of these inputs according to your guild's preferences in the config file.  If any are not important to your guild at all, just set the weight value to 0.

## Why ULTRA?

Thatsmybis.com allows loot councils to sort out LC decisions before raid so people aren't standing around a corpse for 5 minutes.  But when multiple characters have the same thing ranked #1 on their list, sometimes it's tricky to determine who should have prio without considering some external data.  If only there were a way to combine the fairness of point-based systems like DKP or EPGP with the simplicity of running loot on autopilot with Gargul.  

By combining ULTRA, TMB and Gargul, guilds can run a self-pruning loot sytem that prioritizes raiders who contribute the most to your team by showing up every week, without having to hand-jam spreadsheets or to even think about it while you're killing bosses.  

Like point-based systems, ULTRA favors the regular raider who hasn't won a big upgrade in a while but has been looking for one thing for a while.  Unlike point-based systems, ULTRA doesn't discourage taking upgrades since only S-, A-, and B-tier items won with "main spec" rolls are considered.  

## Features

- Parses the character-json.json file ("Giant JSON blob") downloaded from thatsmybis.com
- Fetches attendance and performance metrics from warcraftlogs.com
- Fetches guild roster and character details from Blizzard
- Fixes raid-helper sign-ups with in-game character names
- Outputs a performance report relevant to the guild's current progression zone, so you know who to keep inviting to raid
- Outputs a customizable ULTRA prio recommendation for top wish listed items, clearly showing which characters should be given prio on which items according to their score
- Everything is maintained in a local SQLite3 database called "roster.db" to support integration with 3rd party applications.

## How to use

- Install PHP, download this package and extract to a directory of your choice.
- Log into thatsmybis.com and go to Guild -> Exports.  Click "Download JSON" under "Giant JSON blob" and save the character-json.json file to the same directory.
- Create API keys for raid-helper, Blizzard, and warcraftlogs.com.
- Open config-example.ini and punch in your guild info and API credentials, maybe save it to config.ini.
- Make sure you upload your raid logs to warcraftlogs.com after every raid.
- Run the script with your config file as the only argument: ./ultra.php config.ini

Check out the generated CSV file in the 'reports' directory to help you invite the best players to raid, and check out the one in 'prios' to help you set up prio lists on thatsmybis.com.

After that, the usual routine of exporting your TMB data into Gargul before raid and exporting loot from Gargul into TMB after raid is all you need.  Once loot is uploaded to TMB after raid, you can run this script again, set your prios again, export into Gargul, raid again, and so on.  

I also recommend outputting the the 'prios' and 'reports' to your guild's discord somewhere for full transparency.  A lot of the "why did so-and-so get higher prio than me!?" questions can be answered by having them look at the ultra prio CSV file.  "Oh, because they have perfect attendance, had the item ranked #1 for 2 months longer than I have, and they haven't taken a big upgrade for 4 months.  Got it."


