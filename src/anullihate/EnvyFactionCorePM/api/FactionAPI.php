<?php
/**
 * Created by PhpStorm.
 * User: trd-staff
 * Date: 9/12/19
 * Time: 5:02 PM
 */

namespace anullihate\EnvyFactionCorePM\api;


use anullihate\EnvyFactionCorePM\FactionMain;
use pocketmine\block\Snow;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class FactionAPI {

	private $main;

	public function __construct(FactionMain $main) {
		$this->main = $main;
	}

	public function setEnemies($faction1, $faction2) {
		$stmt = $this->main->db->prepare("INSERT INTO enemies (faction1, faction2) VALUES (:faction1, :faction2);");
		$stmt->bindValue(":faction1", $faction1);
		$stmt->bindValue(":faction2", $faction2);
		$stmt->execute();
	}
	public function unsetEnemies($faction1, $faction2) {
		$stmt = $this->main->db->prepare("DELETE FROM enemies WHERE (faction1 = :faction1 AND faction2 = :faction2) OR (faction1 = :faction2 AND faction2 = :faction1);");
		$stmt->bindValue(":faction1", $faction1);
		$stmt->bindValue(":faction2", $faction2);
		$stmt->execute();
	}
	public function areEnemies($faction1, $faction2) {
		$result = $this->main->db->query("SELECT ID FROM enemies WHERE (faction1 = '$faction1' AND faction2 = '$faction2') OR (faction1 = '$faction2' AND faction2 = '$faction1');");
		$resultArr = $result->fetchArray(SQLITE3_ASSOC);
		if (empty($resultArr) == false) {
			return true;
		}
	}
	public function isInFaction($player) {
		$result = $this->main->db->query("SELECT player FROM master WHERE player='$player';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	public function getFaction($player) {
		$faction = $this->main->db->query("SELECT faction FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["faction"];
	}
	public function setFactionPower($faction, $power) {
		if ($power < 0) {
			$power = 0;
		}
		$stmt = $this->main->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":power", $power);
		$stmt->execute();
	}
	public function setAllies($faction1, $faction2) {
		$stmt = $this->main->db->prepare("INSERT INTO allies (faction1, faction2) VALUES (:faction1, :faction2);");
		$stmt->bindValue(":faction1", $faction1);
		$stmt->bindValue(":faction2", $faction2);
		$stmt->execute();
	}
	public function areAllies($faction1, $faction2) {
		$result = $this->main->db->query("SELECT ID FROM allies WHERE (faction1 = '$faction1' AND faction2 = '$faction2') OR (faction1 = '$faction2' AND faction2 = '$faction1');");
		$resultArr = $result->fetchArray(SQLITE3_ASSOC);
		if (empty($resultArr) == false) {
			return true;
		}
	}
	public function updateAllies($faction) {
		$stmt = $this->main->db->prepare("INSERT OR REPLACE INTO alliescountlimit(faction, count) VALUES (:faction, :count);");
		$stmt->bindValue(":faction", $faction);
		$result = $this->main->db->query("SELECT ID FROM allies WHERE faction1='$faction' OR faction2='$faction';");
		$i = 0;
		while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
			$i = $i + 1;
		}
		$stmt->bindValue(":count", (int)$i);
		$stmt->execute();
	}
	public function getAlliesCount($faction) {
		$result = $this->main->db->query("SELECT count FROM alliescountlimit WHERE faction = '$faction';");
		$resultArr = $result->fetchArray(SQLITE3_ASSOC);
		return (int)$resultArr["count"];
	}
	public function getAlliesLimit() {
		return (int)$this->main->prefs->get("AllyLimitPerFaction");
	}
	public function deleteAllies($faction1, $faction2) {
		$stmt = $this->main->db->prepare("DELETE FROM allies WHERE (faction1 = '$faction1' AND faction2 = '$faction2') OR (faction1 = '$faction2' AND faction2 = '$faction1');");
		$stmt->execute();
	}
	public function getFactionPower($faction) {
		$result = $this->main->db->query("SELECT power FROM strength WHERE faction = '$faction';");
		$resultArr = $result->fetchArray(SQLITE3_ASSOC);
		return (int)$resultArr["power"];
	}
	public function addFactionPower($faction, $power) {
		if ($this->getFactionPower($faction) + $power < 0) {
			$power = $this->getFactionPower($faction);
		}
		$stmt = $this->main->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":power", $this->getFactionPower($faction) + $power);
		$stmt->execute();
	}
	public function subtractFactionPower($faction, $power) {
		if ($this->getFactionPower($faction) - $power < 0) {
			$power = $this->getFactionPower($faction);
		}
		$stmt = $this->main->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":power", $this->getFactionPower($faction) - $power);
		$stmt->execute();
	}
	public function isLeader($player) {
		$faction = $this->main->db->query("SELECT rank FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Leader";
	}
	public function isOfficer($player) {
		$faction = $this->main->db->query("SELECT rank FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Officer";
	}
	public function isMember($player) {
		$faction = $this->main->db->query("SELECT rank FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Member";
	}
	public function getPlayersInFactionByRank($s, $faction, $rank) {
		if ($rank != "Leader") {
			$rankname = $rank . 's';
		} else {
			$rankname = $rank;
		}
		$team = "";
		$result = $this->main->db->query("SELECT player FROM master WHERE faction='$faction' AND rank='$rank';");
		$row = array();
		$i = 0;
		while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
			$row[$i]['player'] = $resultArr['player'];
			if ($this->main->getServer()->getPlayerExact($row[$i]['player']) instanceof Player) {
				$team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::GREEN . "[ON]" . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
			} else {
				$team .= TextFormat::ITALIC . TextFormat::AQUA . $row[$i]['player'] . TextFormat::RED . "[OFF]" . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
			}
			$i = $i + 1;
		}
		$s->sendMessage($this->formatMessage("~ *<$rankname> of |$faction|* ~", true));
		$s->sendMessage($team);
	}
	public function getAllAllies($s, $faction) {
		$team = "";
		$result = $this->main->db->query("SELECT faction1, faction2 FROM allies WHERE faction1='$faction' OR faction2='$faction';");
		$i = 0;
		while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
			$alliedFaction = $resultArr['faction1'] != $faction ? $resultArr['faction1'] : $resultArr['faction2'];
			$team .= TextFormat::ITALIC . TextFormat::RED . $alliedFaction . TextFormat::RESET . TextFormat::WHITE . "||" . TextFormat::RESET;
			$i = $i + 1;
		}
		if ($i > 0) {
			$s->sendMessage($this->formatMessage("~ Allies of *$faction* ~", true));
			$s->sendMessage($team);
		} else {
			$s->sendMessage($this->formatMessage("~ *$faction* has no allies ~", true));
		}
	}
	public function sendListOfTop10FactionsTo($s) {
		$result = $this->main->db->query("SELECT faction FROM strength ORDER BY power DESC LIMIT 10;");
		$i = 0;
		$s->sendMessage($this->formatMessage("~ Top 10 strongest factions ~", true));
		while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
			$j = $i + 1;
			$cf = $resultArr['faction'];
			$pf = $this->getFactionPower($cf);
			$df = $this->getNumberOfPlayers($cf);
			$s->sendMessage(TextFormat::ITALIC . TextFormat::GOLD . "$j -> " . TextFormat::GREEN . "$cf" . TextFormat::GOLD . " with " . TextFormat::RED . "$pf STR" . TextFormat::GOLD . " and " . TextFormat::LIGHT_PURPLE . "$df PLAYERS" . TextFormat::RESET);
			$i = $i + 1;
		}
	}
	public function getNearbyPlots(Player $player) {
		$playerLevel = $player->getLevel()->getName();
		$playerX = $player->getX();
		$playerZ = $player->getZ();
		$maxDistance = $this->prefs->get("MaxMapDistance");
		$result = $this->main->db->query("SELECT faction, x1, z1, x2, z2 FROM plots WHERE ((x1 + (x2 - x1) / 2) - $playerX) * ((x1 + (x2 - x1) / 2) - $playerX) + ((z1 + (z2 - z1) / 2) - $playerZ) * ((z1 + (z2 - z1) / 2) - $playerZ) <= $maxDistance * $maxDistance AND world = '$playerLevel';");
		$factionPlots = array();
		$i = 0;
		while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
			if (!isset($res['faction'])) continue;
			$factionPlots[$i]['faction'] = $res['faction'];
			$factionPlots[$i]['x1'] = $res['x1'];
			$factionPlots[$i]['x2'] = $res['x2'];
			$factionPlots[$i]['z1'] = $res['z1'];
			$factionPlots[$i]['z2'] = $res['z2'];
			$i++;
		}
		return $factionPlots;
	}
	public function getPlayerFaction($player) {
		$faction = $this->main->db->query("SELECT faction FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["faction"];
	}
	public function getLeader($faction) {
		$leader = $this->main->db->query("SELECT player FROM master WHERE faction='$faction' AND rank='Leader';");
		$leaderArray = $leader->fetchArray(SQLITE3_ASSOC);
		return $leaderArray['player'];
	}
	public function factionExists($faction) {
		$lowercasefaction = strtolower($faction);
		$result = $this->main->db->query("SELECT player FROM master WHERE lower(faction)='$lowercasefaction';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	public function sameFaction($player1, $player2) {
		$faction = $this->main->db->query("SELECT faction FROM master WHERE player='$player1';");
		$player1Faction = $faction->fetchArray(SQLITE3_ASSOC);
		$faction = $this->main->db->query("SELECT faction FROM master WHERE player='$player2';");
		$player2Faction = $faction->fetchArray(SQLITE3_ASSOC);
		return $player1Faction["faction"] == $player2Faction["faction"];
	}
	public function getNumberOfPlayers($faction) {
		$query = $this->main->db->query("SELECT COUNT(player) as count FROM master WHERE faction='$faction';");
		$number = $query->fetchArray();
		return $number['count'];
	}
	public function isFactionFull($faction) {
		return $this->getNumberOfPlayers($faction) >= $this->main->prefs->get("MaxPlayersPerFaction");
	}
	public function isNameBanned($name) {
		$bannedNames = file_get_contents($this->getDataFolder() . "BannedNames.txt");
		$isBanned = false;
		if (isset($name) && $this->main->antispam && $this->main->antispam->getProfanityFilter()->hasProfanity($name)) $isBanned = true;
		return (strpos(strtolower($bannedNames), strtolower($name)) > 0 || $isBanned);
	}
	public function newPlot($faction, $x1, $z1, $x2, $z2, string $level) {
		$stmt = $this->main->db->prepare("INSERT OR REPLACE INTO plots (faction, x1, z1, x2, z2, world) VALUES (:faction, :x1, :z1, :x2, :z2, :world);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":x1", $x1);
		$stmt->bindValue(":z1", $z1);
		$stmt->bindValue(":x2", $x2);
		$stmt->bindValue(":z2", $z2);
		$stmt->bindValue(":world", $level);
		$stmt->execute();
	}
	public function drawPlot($sender, $faction, $x, $y, $z, Level $level, $size) {
		$arm = ($size - 1) / 2;
		$block = new Snow();
		if ($this->cornerIsInPlot($x + $arm, $z + $arm, $x - $arm, $z - $arm, $level->getName())) {
			$claimedBy = $this->factionFromPoint($x, $z, $level->getName());
			$power_claimedBy = $this->getFactionPower($claimedBy);
			$power_sender = $this->getFactionPower($faction);
			if ($this->main->prefs->get("EnableOverClaim")) {
				if ($power_sender < $power_claimedBy) {
					$sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy with $power_claimedBy STR. Your faction has $power_sender power. You don't have enough power to overclaim this plot."));
				} else {
					$sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy with $power_claimedBy STR. Your faction has $power_sender power. Type /f overclaim to overclaim this plot if you want."));
				}
				return false;
			} else {
				$sender->sendMessage($this->formatMessage("Overclaiming is disabled."));
				return false;
			}
		}
		$level->setBlock(new Vector3($x + $arm, $y, $z + $arm), $block);
		$level->setBlock(new Vector3($x - $arm, $y, $z - $arm), $block);
		$this->newPlot($faction, $x + $arm, $z + $arm, $x - $arm, $z - $arm, $level->getName());
		return true;
	}
	public function isInPlot(Player $player) {
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		$level = $player->getLevel()->getName();
		$result = $this->main->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2 AND world = '$level';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	public function factionFromPoint($x, $z, string $level) {
		$result = $this->main->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2 AND world = '$level';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return $array["faction"];
	}
	public function inOwnPlot(Player $player) {
		$playerName = $player->getName();
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		$level = $player->getLevel()->getName();
		$faction = $this->getPlayerFaction($playerName);
		return $faction != null && $faction == $this->factionFromPoint($x, $z, $level);
	}
	public function pointIsInPlot($x, $z, string $level) {
		$result = $this->main->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2 AND world = '$level';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return !empty($array);
	}
	public function cornerIsInPlot($x1, $z1, $x2, $z2, string $level) {
		return ($this->pointIsInPlot($x1, $z1, $level) || $this->pointIsInPlot($x1, $z2, $level) || $this->pointIsInPlot($x2, $z1, $level) || $this->pointIsInPlot($x2, $z2, $level));
	}
	public function formatMessage($string, $confirm = false) {
		if ($confirm) {
			return TextFormat::GREEN . "$string";
		} else {
			return TextFormat::YELLOW . "$string";
		}
	}
	public function motdWaiting($player) {
		$stmt = $this->main->db->query("SELECT player FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		return !empty($array);
	}
	public function getMOTDTime($player) {
		$stmt = $this->main->db->query("SELECT timestamp FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		return $array['timestamp'];
	}
	public function setMOTD($faction, $player, $msg) {
		$stmt = $this->main->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":message", $msg);
		$result = $stmt->execute();
		$this->main->db->query("DELETE FROM motdrcv WHERE player='$player';");
	}
	public function updateTag($playername) {
		if (!isset ($this->purechat)){
			$this->purechat = $this->main->getServer()->getPluginManager()->getPlugin("PureChat");
		}
		$p = $this->main->getServer()->getPlayer($playername);
		$f = $this->getPlayerFaction($playername);
		if (!$this->isInFaction($playername)) {
			if (isset($this->purechat)) {
				$levelName = $this->purechat->getConfig()->get("enable-multiworld-chat") ? $p->getLevel()->getName() : null;
				$nameTag = $this->purechat->getNametag($p, $levelName);
				$p->setNameTag($nameTag);
			} else {
				$p->setNameTag(TextFormat::ITALIC . TextFormat::YELLOW . "<$playername>");
			}
		} elseif (isset($this->purechat)) {
			$levelName = $this->purechat->getConfig()->get("enable-multiworld-chat") ? $p->getLevel()->getName() : null;
			$nameTag = $this->purechat->getNametag($p, $levelName);
			$p->setNameTag($nameTag);
		} else {
			$p->setNameTag(TextFormat::ITALIC . TextFormat::GOLD . "<$f> " .
				TextFormat::ITALIC . TextFormat::YELLOW . "<$playername>");
		}
	}

	/****************************************************************/

	/**
	 * @param Player $player
	 * @return string
	 */
	public function getPlayerRank(Player $player) {
		if($this->isInFaction($player->getName()))
		{
			if($this->isOfficer($player->getName())) {
				return '*';
			}
			elseif($this->isLeader($player->getName()))
			{
				return '**';
			}
			else
			{
				return '';
			}
		}
		// TODO
		return '';
	}
}
