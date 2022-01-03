<?php

namespace player4allben\MLGRush;

use pocketmine\plugin\Plugin;
use pocketmine\scheduler\Task;

abstract class MLGRushTask extends Task {

    protected Plugin $owner;

    public function __construct(Plugin $owner) {
     $this->owner = $owner;
    }

    final public function getOwner(): Plugin {
     return $this->owner;
    }
 }
