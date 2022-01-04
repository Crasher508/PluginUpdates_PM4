<?php

namespace blackbarbar486\ampelchallenge\tasks;

use blackbarbar486\ampelchallenge\Main;
use pocketmine\scheduler\Task;

class OfflineTask extends Task {
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

	public function onRun() : void {
        $this->plugin->challengereset();
    }
}