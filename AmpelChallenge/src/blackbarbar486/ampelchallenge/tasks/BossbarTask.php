<?php /** @noinspection ALL */

namespace blackbarbar486\ampelchallenge\tasks;

use blackbarbar486\ampelchallenge\Main;
use blackbarbar486\ampelchallenge\commands\ChallengeCommand;
use pocketmine\scheduler\Task;

class BossbarTask extends Task {
    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun() : void {
        $this->plugin->yellowstatus();
    }
}