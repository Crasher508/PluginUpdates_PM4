<?php

namespace player4allben\MLGRush;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\tile\Sign;
use pocketmine\block\utils\SignText;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\item\VanillaItems;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\world\Position;
use pocketmine\world\sound\AnvilUseSound;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\sound\GhastShootSound;
use pocketmine\world\sound\GhastSound;
use pocketmine\world\World;

class MLGRush extends PluginBase implements Listener {
    
	public Config $cfg;
    public string $prefix = '§1M§fL§4G§fRush §8| §7';
    public ?Config $file;
    public int $mode = 0;
    public ?string $player = null;
    public string $map;
    public string $game = "MLGRush";

    public function onEnable() : void {
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . '/games');
        if (!file_exists($this->getDataFolder() . 'config.yml')) {
            $this->initConfig();
        }
        $this->cfg = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->getScheduler()->scheduleRepeatingTask(new MLGTask($this), 20);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getServer()->getWorldManager()->getDefaultWorld()->setTime(0);
        $this->getServer()->getWorldManager()->getDefaultWorld()->stopTime();

        $this->getLogger()->info($this->prefix . TextFormat::WHITE . ' enabled by PrinxIsLeqit! and fixed by MCCreeperYT');
    }

    public function initConfig() {
        $this->cfg = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->cfg->set('text', FALSE);
        $this->cfg->set('x', 0);
        $this->cfg->set('y', 0);
        $this->cfg->set('z', 0);
        $this->cfg->set('world', 'world');
        $this->cfg->save();
    }

    public function onDisable() : void {
        $dir = $this->getDataFolder() . "games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $gamename = pathinfo($g, PATHINFO_FILENAME);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $gamename . '.yml', Config::YAML);
            $blocks = $arenafile->get('blocks');
            foreach ($blocks as $block) {
                $b = explode(':', $block);
                $this->getServer()->getWorldManager()->getWorldByName($gamename)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
            }
            $arenafile->set('blocks', array());
            $arenafile->set('mode', 'waiting');
            $arenafile->set('counter', 0);
            $arenafile->set('playercount', 0);
            $arenafile->set('playerone', NULL);
            $arenafile->set('playertwo', NULL);
            $arenafile->set('winner1', NULL);
            $arenafile->set('winner2', NULL);
            $arenafile->set('winner3', NULL);
            $arenafile->save();

            $chunks = $this->getServer()->getWorldManager()->getDefaultWorld()->getLoadedChunks();
			foreach($chunks as $chunk) {
				$tiles = $chunk->getTiles();
				foreach ($tiles as $tile) {
					if ($tile instanceof Sign) {
						$text = $tile->getText()->getLines();
						if ($text[0] == "§1M§fL§4G§fRush") {
							if (TextFormat::clean($text[1]) == $gamename) {
								$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "0/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
							}
						}
					}
				}
			}
        }
        $this->getLogger()->info($this->prefix . TextFormat::RED . 'Resetted all arenas!');
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $event->setJoinMessage("");
        $player->setImmobile(false);

        foreach ($this->getServer()->getOnlinePlayers() as $p) {
            $p->sendPopup(TextFormat::GRAY . "[" . TextFormat::GREEN . "+" . TextFormat::GRAY . "] " . $event->getPlayer()->getName());
        }

        $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
		
		$player->setGamemode(GameMode::CREATIVE());
		$player->setGamemode(GameMode::SURVIVAL());
    }

    public function onQuit(PlayerQuitEvent $event) {
        $event->setQuitMessage("");
        $player = $event->getPlayer();
        $this->getServer()->dispatchCommand($player, ("leave"));
    }

    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command == "mlgrush") {
            if (!$sender instanceof Player) {
                return FALSE;
            }
            if (empty($args[0])) {
                $sender->sendMessage($this->prefix);
                $sender->sendMessage('/mlgrush create <map>');
                return FALSE;
            }
            if ($args[0] == 'create') {
                $this->getServer()->getWorldManager()->loadWorld($args[1]);
                $this->getServer()->getWorldManager()->getWorldByName($args[1])->loadChunk($this->getServer()->getWorldManager()->getWorldByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getWorldManager()->getWorldByName($args[1])->getSafeSpawn()->getFloorZ());
                    
                $this->mode = 1;
                $this->player = $sender->getName();
                $this->map = $args[1];
                $this->file = new Config($this->getDataFolder() . '/games/' . $args[1] . '.yml', Config::YAML);
                $sender->teleport($this->getServer()->getWorldManager()->getWorldByName($args[1])->getSafeSpawn());
                $sender->setGamemode(GameMode::CREATIVE());
                $sender->sendMessage('Please check console for error messages. Only continue if there are no!');
                $this->file->set('mode', 0);
                $this->file->set('playercount', 0);
                $this->file->set('counter', 0);
                $this->file->set('blocks', array());
                $this->file->set('winner1', NULL);
                $this->file->set('winner2', NULL);
                $this->file->set('winner3', NULL);
                $this->file->save();
                $sender->sendMessage($this->prefix . 'Please touch the spawn of the blue player!');
                return TRUE;
            }
            
        }
        if ($command == 'leave') {
            if (!$sender instanceof Player) {
                return FALSE;
            }
            if (!$this->isPlaying($sender)) {
                $sender->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                return TRUE;
            } else {
                $arenaname = $this->getArena($sender);
                $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
                $mode = $arenafile->get('mode');
                if ($mode == 'waiting') {
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $arenafile->save();
                    $sender->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
					$chunks = $this->getServer()->getWorldManager()->getDefaultWorld()->getLoadedChunks();
					foreach($chunks as $chunk) {
						$tiles = $chunk->getTiles();
						foreach ($tiles as $tile) {
							if ($tile instanceof Sign) {
								$text = $tile->getText()->getLines();
								if ($text[0] == "§1M§fL§4G§fRush") {
									if (TextFormat::clean($text[1]) == $arenaname) {
										$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "0/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
									}
								}
							}
						}
					}
                }
                if ($mode == "ingame1" || $mode == "ingame2" || $mode == "ingame3" || $mode == "starting1" || $mode == "starting2" || $mode == "starting3") {
                    $playersina = $this->getServer()->getWorldManager()->getWorldByName($arenaname)->getPlayers();
                    foreach ($playersina as $p) {
                        $p->getInventory()->clearAll();
                        $p->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                    }
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getServer()->getWorldManager()->getWorldByName($arenaname)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
                    }
                    $arenafile->save();
					$chunks = $this->getServer()->getWorldManager()->getDefaultWorld()->getLoadedChunks();
					foreach($chunks as $chunk) {
						$tiles = $chunk->getTiles();
						foreach ($tiles as $tile) {
							if ($tile instanceof Sign) {
								$text = $tile->getText()->getLines();
								if ($text[0] == "§1M§fL§4G§fRush") {
									if (TextFormat::clean($text[1]) == $arenaname) {
										$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "0/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
									}
								}
							}
						}
					}
                    $sender->setImmobile(false);
                }
                $sender->setImmobile(false);
            }
            return FALSE;
        }
        if ($command == 'hub') {
            if (!$sender instanceof Player) {
                return FALSE;
            }
            if (!$this->isPlaying($sender)) {
                $sender->transfer('atomicmc.tk', 19132);
                return TRUE;
            } else {
                $arenaname = $this->getArena($sender);
                $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
                $mode = $arenafile->get('mode');
                if ($mode == 'waiting') {
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getServer()->getWorldManager()->getWorldByName($arenaname)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
                    }
                    $arenafile->save();
                    $sender->transfer('127.0.0.1', 19133);
					$chunks = $this->getServer()->getWorldManager()->getDefaultWorld()->getLoadedChunks();
					foreach($chunks as $chunk) {
						$tiles = $chunk->getTiles();
						foreach ($tiles as $tile) {
							if ($tile instanceof Sign) {
								$text = $tile->getText()->getLines();
								if ($text[0] == "§1M§fL§4G§fRush") {
									if (TextFormat::clean($text[1]) == $arenaname) {
										$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "0/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
									}
								}
							}
						}
					}
                }
                if ($mode == "ingame1" || $mode == "ingame2" || $mode == "ingame3" || $mode == "starting1" || $mode == "starting2" || $mode == "starting3") {
                    $playersina = $this->getServer()->getWorldManager()->getWorldByName($arenaname)->getPlayers();
                    foreach ($playersina as $p) {
                        $p->getInventory()->clearAll();
                        $p->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                        $sender->transfer('atomicmc.tk', 19132);
                    }
                    $arenafile->set("playercount", 0);
                    $arenafile->set("playerone", "");
                    $arenafile->set("playertwo", "");
                    $arenafile->set("mode", "waiting");
                    $arenafile->save();
					$chunks = $this->getServer()->getWorldManager()->getDefaultWorld()->getLoadedChunks();
					foreach($chunks as $chunk) {
						$tiles = $chunk->getTiles();
						foreach ($tiles as $tile) {
							if ($tile instanceof Sign) {
								$text = $tile->getText()->getLines();
								if ($text[0] == "§1M§fL§4G§fRush") {
									if (TextFormat::clean($text[1]) == $arenaname) {
										$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "0/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
									}
								}
							}
						}
					}
                }
            }
            return false;
        }
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $playername = $player->getName();
		$blockid = $event->getBlock()->getId();
        $block = $event->getBlock()->getPosition();
        $tile = $block->getWorld()->getTile($block);
        if ($playername == $this->player) {
            if ($this->mode == 1) {
                if ($blockid == 0) {
                    return;
                }
                $x = $block->x;
                $y = $block->y + 1;
                $z = $block->z;

                $this->file->set('player1x', $x);
                $this->file->set('player1y', $y);
                $this->file->set('player1z', $z);
                $this->file->save();

                $this->mode = 2;
                $player->sendMessage($this->prefix . 'Please touch the spawn of the red player!');
            } elseif ($this->mode == 2) {
                if ($blockid == 0) {
                    return;
                }
                $x = $block->x;
                $y = $block->y + 1;
                $z = $block->z;

                $this->file->set('player2x', $x);
                $this->file->set('player2y', $y);
                $this->file->set('player2z', $z);
                $this->file->save();

                $this->mode = 3;
                $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                $player->sendMessage($this->prefix . 'Please touch the sign of this arena!');
            } elseif ($this->mode == 3) {
                if ($tile instanceof Sign) {
					$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($this->map), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "0/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
                    $player->sendMessage($this->prefix . TextFormat::GREEN . 'Arena created!');
                    $this->file->set('mode', 'waiting');
                    $this->file->save();
                    $this->mode = 0;
                    $this->player = NULL;
                    $this->file = NULL;
                }
            }
            return;
        }
        if ($tile instanceof Sign) {
            $text = $tile->getText()->getLines();
            if ($text[0] == "§1M§fL§4G§fRush") {
                $arenaname = TextFormat::clean($text[1]);
                $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
                $playercount = $arenafile->get('playercount');
                $mode = $arenafile->get('mode');
                if ($mode == 'waiting') {
                    if ($playercount == 0) {
                        $x = $arenafile->get('player1x');
                        $y = $arenafile->get('player1y');
                        $z = $arenafile->get('player1z');
                        $player->teleport(new Position($x, $y, $z, $this->getServer()->getWorldManager()->getWorldByName($arenaname)));
                        $player->getInventory()->clearAll();
                        $arenafile->set('playercount', 1);
                        $arenafile->set('playerone', $player->getName());
                        $arenafile->save();
						$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "1/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
						$player->setImmobile(true);
                    } elseif ($playercount == 1) {
                        $x = $arenafile->get('player2x');
                        $y = $arenafile->get('player2y');
                        $z = $arenafile->get('player2z');
                        $player->teleport(new Position($x, $y, $z, $this->getServer()->getWorldManager()->getWorldByName($arenaname)));
                        $player->getInventory()->clearAll();
                        $arenafile->set('playercount', 2);
                        $arenafile->set('mode', 'starting1');
                        $arenafile->set('playertwo', $player->getName());
                        $arenafile->set('counter', 5);
                        $arenafile->save();
						$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "2/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::RED . "INGAME"), 'UTF-8')]));
						$player->setImmobile(true);
                    }
                    return;
                } else {
                    $player->sendMessage($this->prefix . TextFormat::RED . 'This arena is ingame!');
                }

                return;
            }
        }
    }


    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $playername = $event->getPlayer()->getName();
        if ($this->isPlaying($player)) {
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            if ($mode !== 'waiting' and $mode !== 'starting1' and $mode !== 'starting2' and $mode !== 'starting3'){
				$py = $player->getPosition()->y;
				if($py < 60){
					$player1 = $arenafile->get('playerone');
					$player2 = $arenafile->get('playertwo');
					if($playername === $player1){
						$x = $arenafile->get('player1x');
						$y = $arenafile->get('player1y');
						$z = $arenafile->get('player1z');

						$player->teleport(new Vector3($x, $y, $z));
					}elseif($playername === $player2){
						$x = $arenafile->get('player2x');
						$y = $arenafile->get('player2y');
						$z = $arenafile->get('player2z');

						$player->teleport(new Vector3($x, $y, $z));
					}
				}
			}
		}
    }

    public function onBlockPlace(BlockPlaceEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock()->getPosition();
        if (!$this->isPlaying($player)) {
            if (!$player->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
                $event->cancel();
            }
        }
        if ($this->isPlaying($player)) {
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            if ($mode === 'ingame1' or $mode === 'ingame2' or $mode === 'ingame3') {
                if($arenafile->get("player1y") - 2 < $block->getY()){
					$event->cancel();
                    return;
                }
                $x = $block->x;
                $y = $block->y;
                $z = $block->z;
                $blocks = $arenafile->get('blocks');
                $blocks[] = $x . ':' . $y . ':' . $z;
                $arenafile->set('blocks', $blocks);
                $arenafile->save();
            } else {
				$event->cancel();
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $blockid = $block->getId();
        $playername = $player->getName();
        if (!$this->isPlaying($player)) {
            if(!$player->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
				$event->cancel();
            }
        }
        if ($this->isPlaying($player)) {
            $arenaname = $this->getArena($player);
            $arenafile = new Config($this->getDataFolder() . '/games/' . $arenaname . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            if ($mode == 'waiting' or $mode == 'starting1' or $mode == 'starting2' or $mode == 'starting3') {
				$event->cancel();
                return;
            }
            if ($blockid == 35) {
                $x1 = $arenafile->get('player1x');
                $y1 = $arenafile->get('player1y');
                $z1 = $arenafile->get('player1z');

                $x2 = $arenafile->get('player2x');
                $y2 = $arenafile->get('player2y');
                $z2 = $arenafile->get('player2z');

                $player1spawn = new Vector3($x1, $y1, $z1);
                $player2spawn = new Vector3($x2, $y2, $z2);
                $distance1 = $player1spawn->distanceSquared($player->getPosition());
                $distance2 = $player2spawn->distanceSquared($player->getPosition());
                if ($distance1 > $distance2) {
                    if ($playername == $arenafile->get('playertwo')) {
                        $player->sendMessage($this->prefix . TextFormat::RED . "You cannot destroy your own woolblock!");
                        $event->cancel();
                    } else {
                        $blocks = $arenafile->get('blocks');
                        $players = $this->getServer()->getWorldManager()->getWorldByName($arenaname)->getPlayers();
                        if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
                        	$one->getPosition()->getWorld()->addSound($one->getPosition(), new GhastShootSound());
						}
						if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
							$two->getPosition()->getWorld()->addSound($two->getPosition(), new GhastShootSound());
						}
                        foreach ($players as $p) {
                            if ($p instanceof Player) {
                                $p->sendMessage($this->prefix . "The woolblock of " . TextFormat::DARK_RED . "Red" . TextFormat::GRAY . " was destroyed!");
                                $p->getPosition()->getWorld()->addSound($p->getPosition(), new GhastSound());
                                $p->setImmobile(true);
                            }
                        }
                        foreach ($blocks as $block) {
                            $b = explode(':', $block);
                            $this->getServer()->getWorldManager()->getWorldByName($arenaname)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
                        }
                        if ($mode == 'ingame1') {
							if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
								$one->teleport($player1spawn);
							}
							if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
								$two->teleport($player2spawn);
							}
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting2');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame2') {
							if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
								$one->teleport($player1spawn);
							}
							if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
								$two->teleport($player2spawn);
							}
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting3');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame3') {
							if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
								$one->teleport($player1spawn);
							}
							if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
								$two->teleport($player2spawn);
							}
                            $arenafile->set('winner3', $player->getName());
                            $arenafile->set('counter', 0);
                            $arenafile->set('blocks', array());
                        }
                        $arenafile->save();
                        $event->cancel();
                    }
                } elseif ($distance1 < $distance2) {
                    if ($playername == $arenafile->get('playerone')) {
                        $player->sendMessage($this->prefix . TextFormat::RED . "You cannot destroy your own woolblock!");
                        $event->cancel();
                    } else {
                        $blocks = $arenafile->get('blocks');
                        $players = $this->getServer()->getWorldManager()->getWorldByName($arenaname)->getPlayers();
						if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
							$one->getPosition()->getWorld()->addSound($one->getPosition(), new GhastShootSound());
						}
						if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
							$two->getPosition()->getWorld()->addSound($two->getPosition(), new GhastShootSound());
						}
                        foreach ($players as $p) {
                            if ($p instanceof Player) {
                                $p->sendMessage($this->prefix . "The woolblock of " . TextFormat::DARK_BLUE . "Blue" . TextFormat::GRAY . " was destroyed!");
								$p->getPosition()->getWorld()->addSound($p->getPosition(), new GhastSound());
							}
                        }
                        foreach ($blocks as $block) {
                            $b = explode(':', $block);
                            $this->getServer()->getWorldManager()->getWorldByName($arenaname)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
                        }
                        if ($mode == 'ingame1') {
							if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
								$one->teleport($player1spawn);
							}
							if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
								$two->teleport($player2spawn);
							}
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting2');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame2') {
							if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
								$one->teleport($player1spawn);
							}
							if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
								$two->teleport($player2spawn);
							}
                            $arenafile->set('winner1', $player->getName());
                            $arenafile->set('counter', 5);
                            $arenafile->set('mode', 'starting3');
                            $arenafile->set('blocks', array());
                        } elseif ($mode == 'ingame3') {
							if (($one = $this->getServer()->getPlayerByPrefix($arenafile->get('playerone'))) !== null) {
								$one->teleport($player1spawn);
							}
							if (($two = $this->getServer()->getPlayerByPrefix($arenafile->get('playertwo'))) !== null) {
								$two->teleport($player2spawn);
							}
                            $arenafile->set('winner3', $player->getName());
                            $arenafile->set('counter', 0);
                            $arenafile->set('blocks', array());
                        }
                        $arenafile->save();
                        $event->cancel();
                    }
                }
            }
            if ($blockid != BlockLegacyIds::SANDSTONE){
				$event->cancel();
			}
		}
    }

    public function onEntityDamage(EntityDamageEvent $event) {
        if ($event->getCause() == EntityDamageEvent::CAUSE_FALL) {
            $event->cancel();
        } elseif ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();
            if ($damager instanceof Player && $entity instanceof Player) {
                if (!$this->isPlaying($damager)) {
                    $event->cancel();
                    return;
                }
                if (!$this->isPlaying($entity)) {
                    $event->cancel();
                    return;
                }
                $damagerinv = $damager->getInventory();
                $iteminhand = $damagerinv->getItemInHand()->getId();
                if ($iteminhand == 280) {
                    $event->setKnockBack(0.5); //0.6
                    $event->setModifier(0, EntityDamageEvent::MODIFIER_PREVIOUS_DAMAGE_COOLDOWN);
                }
            }
        }
        $event->setCancelled(false);
    }

    public function onLogin(PlayerLoginEvent $event) {
        $player = $event->getPlayer();
        $player->getInventory()->clearAll();
        $player->setGamemode(GameMode::SURVIVAL());
        $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
    }

    public function getArena(Player $player) : string {
        $dir = $this->getDataFolder() . "/games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $worldname = pathinfo($g, PATHINFO_FILENAME);
            if ($player->getPosition()->getWorld()->getDisplayName() == $worldname) {
                return $worldname;
            }
        }
        return "";
    }

    public function onDrop(PlayerDropItemEvent $ev){
        $ev->cancel();
    }

    public function isPlaying(Player $player) : bool {
        $dir = $this->getDataFolder() . "/games/";
        $games = array_slice(scandir($dir), 2);
        foreach ($games as $g) {
            $worldname = pathinfo($g, PATHINFO_FILENAME);
            if ($player->getPosition()->getWorld()->getDisplayName() == $worldname) {
                return true;
            }
        }
        return false;
    }
}

class MLGTask extends MLGRushTask {
    public Config $cfg;
    private MLGRush $plugin;

	public function __construct(MLGRush $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
    
    public function onRun() : void {
        foreach ($this->getOwner()->getServer()->getOnlinePlayers() as $player) {
            if (!$player instanceof Player) {
                return;
            }
            if ($player->getPosition()->getWorld() == $this->getOwner()->getServer()->getWorldManager()->getDefaultWorld()) {
                $player->setImmobile(false);
            }

            $player->setHealth(20);
            $player->getHungerManager()->setFood(20);
        }

        $dir = $this->plugin->getDataFolder() . "games/";
        $games = array_slice(scandir($dir), 2);
        $this->cfg = new Config($this->getOwner()->getDataFolder() . 'config.yml', Config::YAML);
        foreach ($games as $g) {
            $gamename = pathinfo($g, PATHINFO_FILENAME);
            if (!$this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename) instanceof World) {
                $this->getOwner()->getServer()->loadLevel($gamename);
                
            }
            $arenafile = new Config($this->getOwner()->getDataFolder() . '/games/' . $gamename . '.yml', Config::YAML);
            $mode = $arenafile->get('mode');
            //ROUND1:
            if ($mode === 'waiting') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter + 1);
                $arenafile->save();
                if ($counter == 30) {
                    $arenafile->set('counter', 0);
                    $arenafile->save();
                    $players = $this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->getPlayers();
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::RED . 'Waiting for 2 players!');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                }
            } elseif ($mode === 'starting1') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                foreach ($this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->getPlayers() as $p) {
                    if ($p instanceof Player) {
                        $p->setImmobile(true);
                        if ($counter == 5) {
                            $p->sendPopup(TextFormat::GREEN . '5');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 4) {
                            $p->sendPopup(TextFormat::DARK_GREEN . '4');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 3) {
                            $p->sendPopup(TextFormat::YELLOW . '3');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 2) {
                            $p->sendPopup(TextFormat::RED . '2');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 1) {
                            $p->sendPopup(TextFormat::DARK_RED . '1');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 0) {
                            $p->getInventory()->clearAll();
                            $p->getInventory()->setItem(0, VanillaItems::STICK()->setCustomName(TextFormat::GOLD . 'Stick'));
                            $p->getInventory()->setItem(1, VanillaBlocks::SANDSTONE()->asItem()->setCount(64));
                            $p->getInventory()->setItem(2, VanillaItems::WOODEN_PICKAXE());

                            $p->sendPopup(TextFormat::GREEN . 'Go!');
                            $p->sendTitle(TextFormat::GOLD . TextFormat::ITALIC . 'Round 1', '', 5, 15, 5);
                            $p->sendMessage($this->plugin->prefix . TextFormat::WHITE . ' You have 3 minutes time to rush your opponents base and destroy the wool!');

                            $p->setImmobile(false);

                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                            $arenafile->set('mode', 'ingame1');
                            $arenafile->set('counter', 180);
                            $arenafile->save();
                        }
                    }
                }
            } elseif ($mode === 'ingame1') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                $players = $this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->getPlayers();
                if ($counter == 120) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " minutes left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 60) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "1" . TextFormat::GRAY . " minute left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 30) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "30" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 15) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "15" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 10) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "10" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 5) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "5" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 4) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "4" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 3) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "3" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 2) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 1) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "One" . TextFormat::GRAY . " second left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 0) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . 'The game has end!');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                            $p->setImmobile(true);
                        }
                    }

                    $x1 = $arenafile->get('player1x');
                    $y1 = $arenafile->get('player1y');
                    $z1 = $arenafile->get('player1z');

                    $x2 = $arenafile->get('player2x');
                    $y2 = $arenafile->get('player2y');
                    $z2 = $arenafile->get('player2z');

                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playerone'))->teleport(new Vector3($x1, $y1, $z1));
                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playertwo'))->teleport(new Vector3($x2, $y2, $z2));

                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
                    }

                    $arenafile->set('mode', 'starting2');
                    $arenafile->set('counter', 5);
                    $arenafile->set('blocks', array());
                    $arenafile->set('winner1', NULL);
                    $arenafile->save();
                }
                //ROUND2:
            } elseif ($mode === 'starting2') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                foreach ($this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->getPlayers() as $p) {
                    if ($p instanceof Player) {
                        $p->setImmobile(true);
                        if ($counter == 5) {
                            $p->sendPopup(TextFormat::GREEN . '5');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 4) {
                            $p->sendPopup(TextFormat::DARK_GREEN . '4');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 3) {
                            $p->sendPopup(TextFormat::YELLOW . '3');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 2) {
                            $p->sendPopup(TextFormat::RED . '2');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 1) {
                            $p->sendPopup(TextFormat::DARK_RED . '1');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 0) {
                            $p->getInventory()->clearAll();
							$p->getInventory()->setItem(0, VanillaItems::STICK()->setCustomName(TextFormat::GOLD . 'Stick'));
							$p->getInventory()->setItem(1, VanillaBlocks::SANDSTONE()->asItem()->setCount(64));
							$p->getInventory()->setItem(2, VanillaItems::WOODEN_PICKAXE());
                            $p->sendPopup(TextFormat::GREEN . 'Go!');
                            $p->sendTitle(TextFormat::GOLD . TextFormat::ITALIC . 'Round 2', '', 5, 15, 5);
                            $p->sendMessage($this->plugin->prefix . TextFormat::WHITE . ' You have 3 minutes time to rush your opponents base and destroy the wool!');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                            $p->setImmobile(false);
                            $arenafile->set('mode', 'ingame2');
                            $arenafile->set('counter', 180);
                            $arenafile->save();
                        }
                    }
                }
            } elseif ($mode === 'ingame2') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                $players = $this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->getPlayers();
                if ($counter == 120) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " minutes left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 60) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "1" . TextFormat::GRAY . " minute left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 30) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "30" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 15) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "15" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 10) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "10" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 5) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "5" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 4) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "4" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 3) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "3" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 0) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::RED . 'Nobody wins this round!');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                            $p->setImmobile(true);
                        }
                    }

                    $x1 = $arenafile->get('player1x');
                    $y1 = $arenafile->get('player1y');
                    $z1 = $arenafile->get('player1z');

                    $x2 = $arenafile->get('player2x');
                    $y2 = $arenafile->get('player2y');
                    $z2 = $arenafile->get('player2z');

                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playerone'))->teleport(new Vector3($x1, $y1, $z1));
                    $this->getOwner()->getServer()->getPlayer($arenafile->get('playertwo'))->teleport(new Vector3($x2, $y2, $z2));

                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
                    }

                    $arenafile->set('mode', 'starting3');
                    $arenafile->set('counter', 5);
                    $arenafile->set('blocks', array());
                    $arenafile->set('winner2', NULL);
                    $arenafile->save();
                }
                //ROUND3:
            } elseif ($mode === 'starting3') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                foreach ($this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->getPlayers() as $p) {
                    if ($p instanceof Player) {
                        $p->setImmobile(true);
                        if ($counter == 5) {
                            $p->sendPopup(TextFormat::GREEN . '5');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 4) {
                            $p->sendPopup(TextFormat::DARK_GREEN . '4');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 3) {
                            $p->sendPopup(TextFormat::YELLOW . '3');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 2) {
                            $p->sendPopup(TextFormat::RED . '2');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 1) {
                            $p->sendPopup(TextFormat::DARK_RED . '1');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        } elseif ($counter == 0) {
                            $p->getInventory()->clearAll();
							$p->getInventory()->setItem(0, VanillaItems::STICK()->setCustomName(TextFormat::GOLD . 'Stick'));
							$p->getInventory()->setItem(1, VanillaBlocks::SANDSTONE()->asItem()->setCount(64));
							$p->getInventory()->setItem(2, VanillaItems::WOODEN_PICKAXE());
                            $p->sendPopup(TextFormat::GREEN . 'Go!');
                            $p->sendTitle(TextFormat::GOLD . TextFormat::ITALIC . 'Round 3', '', 5, 15, 5);
                            $p->sendMessage($this->plugin->prefix . TextFormat::WHITE . ' You have 3 minutes time to rush your opponents base and destroy the wool!');
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                            $p->setImmobile(false);
                            $arenafile->set('mode', 'ingame3');
                            $arenafile->set('counter', 180);
                            $arenafile->save();
                        }
                    }
                }
            } elseif ($mode === 'ingame3') {
                $counter = $arenafile->get('counter');
                $arenafile->set('counter', $counter - 1);
                $arenafile->save();

                $players = $this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->getPlayers();
                if ($counter == 120) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "2" . TextFormat::GRAY . " minutes left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 60) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "1" . TextFormat::GRAY . " minute left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 30) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "30" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 15) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "15" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 10) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "10" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 5) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "5" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 4) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "4" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 3) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->sendMessage($this->plugin->prefix . TextFormat::BLUE . "3" . TextFormat::GRAY . " seconds left to the end!");
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new ClickSound());
                        }
                    }
                    return;
                } elseif ($counter == 0) {
                    foreach ($players as $p) {
                        if ($p instanceof Player) {
                            $p->teleport($this->getOwner()->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                            $p->getPosition()->getWorld()->addSound($p->getPosition(), new AnvilUseSound());
                            $p->setImmobile(false);
                        }
                    }

                    $blocks = $arenafile->get('blocks');
                    foreach ($blocks as $block) {
                        $b = explode(':', $block);
                        $this->getOwner()->getServer()->getWorldManager()->getWorldByName($gamename)->setBlock(new Vector3($b[0], $b[1], $b[2]), VanillaBlocks::AIR());
                    }


                    $arenafile->set('blocks', array());
                    $arenafile->set('winner3', NULL);
                    $arenafile->save();

                    $winner1 = $arenafile->get('winner1');
                    $winner2 = $arenafile->get('winner2');
                    $winner3 = $arenafile->get('winner3');
                    //END:
                    $player1 = $this->getOwner()->getServer()->getPlayer($arenafile->get('playerone'));
                    $player2 = $this->getOwner()->getServer()->getPlayer($arenafile->get('playertwo'));
                    $points1 = 0;
                    $points2 = 0;
                    //P1
                    if ($player1->getName() == $winner1) {
                        $points1 = $points1 + 1;
                    }
                    if ($player1->getName() == $winner2) {
                        $points1 = $points1 + 1;
                    }
                    if ($player1->getName() == $winner3) {
                        $points1 = $points1 + 1;
                    }
                    //P2
                    if ($player2->getName() == $winner1) {
                        $points2 = $points2 + 1;
                    }
                    if ($player2->getName() == $winner2) {
                        $points2 = $points2 + 1;
                    }
                    if ($player2->getName() == $winner3) {
                        $points2 = $points2 + 1;
                    }

                    foreach ($players as $p) {
                        $p->getInventory()->clearAll();
                        if ($p instanceof Player) {
                            $pname = $p->getName();
                            if ($points1 == $points2) {
                                $p->sendTitle(TextFormat::GRAY . 'Undecited!');
                            } elseif ($points1 < $points2) {
                                if ($player2->getName() == $pname) {
                                    $p->sendTitle(TextFormat::GOLD . 'You have won!!');
                                } else {
                                    $p->sendTitle(TextFormat::RED . 'You have lost!');
                                }
                            } elseif ($points1 > $points2) {
                                if ($player1->getName() == $pname) {
                                    $p->sendTitle(TextFormat::GOLD . 'You have won!');
                                } else {
                                    $p->sendTitle(TextFormat::RED . 'You have lost!');
                                }
                            }
                        }
                    }
                    $arenafile->set('winner1', NULL);
                    $arenafile->set('winner2', NULL);
                    $arenafile->set('winner3', NULL);
                    $arenafile->set('mode', 'waiting');
                    $arenafile->set('counter', 0);
                    $arenafile->set('playercount', 0);
                    $arenafile->set('playerone', NULL);
                    $arenafile->set('playertwo', NULL);
                    $arenafile->save();

					$chunks = $this->getOwner()->getServer()->getWorldManager()->getDefaultWorld()->getLoadedChunks();
					foreach($chunks as $chunk) {
						$tiles = $chunk->getTiles();
						foreach ($tiles as $tile) {
							if ($tile instanceof Sign) {
								$text = $tile->getText()->getLines();
								if ($text[0] == "§1M§fL§4G§fRush") {
									if (TextFormat::clean($text[1]) == $gamename) {
										$tile->setText(new SignText([0 => mb_scrub(("§1M§fL§4G§fRush"), 'UTF-8'), 1 => mb_scrub(($text[1]), 'UTF-8'), 2 => mb_scrub((TextFormat::YELLOW . "0/2"), 'UTF-8'), 3 => mb_scrub((TextFormat::GREEN . "JOIN"), 'UTF-8')]));
									}
								}
							}
						}
					}
                }
            }
        }
    }
}
