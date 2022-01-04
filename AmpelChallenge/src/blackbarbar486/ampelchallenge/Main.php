<?php /** @noinspection ALL */

namespace blackbarbar486\ampelchallenge;

use blackbarbar486\ampelchallenge\tasks\RedTask;
use blackbarbar486\ampelchallenge\tasks\CheckTask;
use blackbarbar486\ampelchallenge\tasks\BossbarTask;
use blackbarbar486\ampelchallenge\tasks\OfflineTask;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use blackbarbar486\ampelchallenge\commands\ChallengeCommand;
use blackbarbar486\ampelchallenge\listener\MoveListener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {

    public const PREFIX = "§cA§em§ap§ce§el§a» ";
    public string $status = "offline";
    public string $gamelevel = "";

    public function onEnable() : void {
        $this->getServer()->getLogger()->info(Main::PREFIX.TextFormat::GREEN." was enabled.");
        //Create Config
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->saveResource("config.yml");
        //Register Command
        $this->getServer()->getCommandMap()->register("challenge", new ChallengeCommand($this));
        //Register Listener
        $this->getServer()->getPluginManager()->registerEvents(new MoveListener($this), $this);
    }

    public function onDisable() : void {
        $this->getServer()->getLogger()->info(TextFormat::GRAY."[".TextFormat::RED."A".TextFormat::YELLOW."m".TextFormat::GREEN."p".TextFormat::RED."e".TextFormat::YELLOW."l".TextFormat::AQUA."Challenge".TextFormat::GRAY."]".TextFormat::RED." was disabled.");
    }

    public function yellowstatus() {
        ChallengeCommand::$bar->setTitle("§8[ §7▇▇▇▇▇ §8] §8[ §e▇▇▇▇▇ §8] §8[ §7▇▇▇▇▇ §8]");
        $this->status = "yellow";
        $this->getScheduler()->scheduleDelayedTask(new RedTask($this), 2*20); //2 seconds
    }

    public function redstatus() {
        ChallengeCommand::$bar->setTitle("§8[ §7▇▇▇▇▇ §8] §8[ §7▇▇▇▇▇ §8] §8[ §c▇▇▇▇▇ §8]");
        $this->status = "red";
        $this->getScheduler()->scheduleDelayedTask(new CheckTask($this), 5*20); //4 seconds
    }

    public function greenstatus() {
        if ($this->status === "offline") {
            return true;
        }
        ChallengeCommand::$bar->setTitle("§8[ §a▇▇▇▇▇ §8] §8[ §7▇▇▇▇▇ §8] §8[ §7▇▇▇▇▇ §8]");
        $this->status = "green";
        $random = mt_rand(180, 300);
        $this->getScheduler()->scheduleDelayedTask(new BossbarTask($this), $random*20); //Random seconds
    }

    public function offlinestatus(Player $player) {
        $this->status = "ending";
        foreach ($player->getLevel()->getPlayers() as $p) {
            if ($this->getConfig()->get("ChallengeTimer") == "true") {
                $p->chat("/timerfinish");
            }
            $p->teleport($player->getLevel()->getSafeSpawn());
            $p->setGamemode(3);
            $p->sendMessage(Main::PREFIX."§bDer Spieler " . $player->getName() . " hat sich bewegt.");
            $p->sendMessage(Main::PREFIX."§bDie Challenge ist somit beendet.");
            $p->sendMessage(Main::PREFIX."§bDu wirst in 10s teleportiert und die Challenge resettet.");
        }
        ChallengeCommand::$bar->setTitle("§l§c Challenge beendet");
        $this->getScheduler()->scheduleDelayedTask(new OfflineTask($this), 10*20); //10 seconds
    }

    public function challengereset() {
        $this->status = "offline";
        foreach ($this->getServer()->getLevelByName($this->gamelevel)->getPlayers() as $p) {
            if ($this->getConfig()->get("ChallengeTimer") == "true") {
                $p->chat("/timerfinish");
            }
            ChallengeCommand::$bar->removePlayer($p);
            $p->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
            if (!$p->isOp()) {
                $p->setGamemode(0);
            }else {
                $p->setGamemode(1);
            }
            $p->sendMessage(self::PREFIX."§bDie Challenge wurde beendet.");
        }
        $this->gamelevel = "";
    }

    public function win(Player $player) {
        foreach ($player->getLevel()->getPlayers() as $p) {
            if ($this->getConfig()->get("ChallengeTimer") == "true") {
                $p->chat("/timerfinish");
            }
            $p->teleport($player->getLevel()->getSafeSpawn());
            $p->setGamemode(3);
            $p->sendMessage(Main::PREFIX."§bDie Challenge wurde gewonnen.");
            $p->sendMessage(Main::PREFIX."§bDu wirst in 10s teleportiert und die Challenge resettet.");
        }
        ChallengeCommand::$bar->setTitle("§l§a Challenge gewonnen");
        $this->status = "offline";
        $this->getScheduler()->scheduleDelayedTask(new OfflineTask($this), 10*20); //10 seconds
    }
}