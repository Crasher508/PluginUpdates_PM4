<?php

namespace blackbarbar486\ampelchallenge\commands;

use blackbarbar486\ampelchallenge\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use blackbarbar486\ampelchallenge\tasks\BossbarTask;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use xenialdan\apibossbar\BossBar;

class ChallengeCommand extends Command {

	private Main $plugin;
    public static BossBar $bar;

    public function __construct(Main $plugin) {
        parent::__construct("challenge", "", "/challenge", [""]);
        $this->plugin = $plugin;
    }
    public function execute(CommandSender $player, string $commandLabel, array $args) : void {
        if (!$player->hasPermission("challenge.command")) {
            $player->sendMessage(Main::PREFIX."§cDu kannst die §aAmpel-Challenge §cnicht starten.");
            return;
        }
        if(!$player instanceof Player) return;
        if (!isset($args[0])) {
            $player->sendMessage(Main::PREFIX."§cBenutze bitte: §7[§cstart, finish, win§7]");
            return;
        }
        if (strtolower($args[0]) === "start") {
            if ($this->plugin->status != "offline") {
                $player->sendMessage(Main::PREFIX."§cDie Challenge ist bereits online.");
                return;
            }
            $bar = new Bossbar();
            $bar->setTitle("§8[ §a▇▇▇▇▇ §8] §8[ §7▇▇▇▇▇ §8] §8[ §7▇▇▇▇▇ §8]");
            $bar->setPercentage(1);
            self::$bar = $bar;
            foreach ($player->getWorld()->getPlayers() as $p) {
                $p->sendMessage(Main::PREFIX."§bDie Challenge wurde gestartet.");
                $p->teleport($this->plugin->getServer()->getWorldManager()->getWorld($player->getPosition()->getWorld()->getFolderName())->getSafeSpawn());
                $p->setGamemode(GameMode::SURVIVAL());
                self::$bar->addPlayer($p);
                if ($this->plugin->getConfig()->get("ChallengeTimer") == "true") {
                    $p->chat("/timerstart");
                }
            }
            $random = mt_rand(180, 300);
            $this->plugin->getScheduler()->scheduleDelayedTask(new BossbarTask($this->plugin), $random*20);
            $this->plugin->status = "green";
            $this->plugin->gamelevel = $player->getPosition()->getWorld()->getDisplayName();
        }elseif (strtolower($args[0]) === "finish") {
            if ($this->plugin->status === "offline") {
                $player->sendMessage(Main::PREFIX."§cDie Challenge ist bereits offline.");
                return;
            }
            $this->plugin->challengereset();
        }elseif (strtolower($args[0]) === "win") {
            if ($this->plugin->status === "offline") {
                $player->sendMessage(Main::PREFIX."§cDie Challenge ist offline.");
                return;
            }
            $this->plugin->win($player);
        }
    }
}