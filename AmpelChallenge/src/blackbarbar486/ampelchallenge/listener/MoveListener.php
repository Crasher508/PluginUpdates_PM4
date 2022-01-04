<?php

namespace blackbarbar486\ampelchallenge\listener;

use blackbarbar486\ampelchallenge\commands\ChallengeCommand;
use blackbarbar486\ampelchallenge\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;

class MoveListener implements Listener {
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerMove(PlayerMoveEvent $event) : void {
        $player = $event->getPlayer();
        if ($player->getWorld()->getFolderName() != $this->plugin->gamelevel) {
            return;
        }

        if ($this->plugin->status != "red") {
            return;
        }

        $this->plugin->offlinestatus($player);
    }
}