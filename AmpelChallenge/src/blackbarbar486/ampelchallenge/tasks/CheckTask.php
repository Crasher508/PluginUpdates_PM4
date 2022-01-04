<?php

namespace blackbarbar486\ampelchallenge\tasks;

use blackbarbar486\ampelchallenge\commands\ChallengeCommand;
use blackbarbar486\ampelchallenge\Main;
use pocketmine\scheduler\Task;

class CheckTask extends Task {
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

	public function onRun() : void {
        $this->plugin->greenstatus();
    }
}