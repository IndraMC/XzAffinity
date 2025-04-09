<?php

declare(strict_types=1);

namespace IndraMC\XzAffinity\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use IndraMC\XzAffinity\Main;

class AffinityCommand extends Command implements PluginOwned {
    /** @var Main */
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        parent::__construct("affinity", "Manage your affinities", "/affinity <request|accept|decline|delete|list|types>");
        $this->setPermission("xzaffinity.command.use");
        $this->plugin = $plugin;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
            return false;
        }
        
        if (!isset($args[0])) {
            // Show UI form instead of text usage
            $this->plugin->showMainForm($sender);
            return true;
        }
        
        switch (strtolower($args[0])) {
            case "request":
                $this->plugin->showPlayerListForm($sender);
                break;
                
            case "accept":
                if (!isset($args[1])) {
                    $this->plugin->showRequestsForm($sender);
                    return true;
                }
                
                $requester = $args[1];
                if ($this->plugin->acceptRequest($sender->getName(), $requester)) {
                    $sender->sendMessage($this->plugin->getMessage("request_accepted", ["player" => $requester]));
                    
                    $requesterPlayer = $this->plugin->getServer()->getPlayerByPrefix($requester);
                    if ($requesterPlayer !== null) {
                        $requesterPlayer->sendMessage($this->plugin->getMessage("request_accepted_target", ["player" => $sender->getName()]));
                    }
                } else {
                    $sender->sendMessage($this->plugin->getMessage("request_not_found", ["player" => $requester]));
                }
                break;
                
            case "decline":
                if (!isset($args[1])) {
                    $this->plugin->showRequestsForm($sender);
                    return true;
                }
                
                $requester = $args[1];
                if ($this->plugin->declineRequest($sender->getName(), $requester)) {
                    $sender->sendMessage($this->plugin->getMessage("request_declined", ["player" => $requester]));
                    
                    $requesterPlayer = $this->plugin->getServer()->getPlayerByPrefix($requester);
                    if ($requesterPlayer !== null) {
                        $requesterPlayer->sendMessage($this->plugin->getMessage("request_declined_target", ["player" => $sender->getName()]));
                    }
                } else {
                    $sender->sendMessage($this->plugin->getMessage("request_not_found", ["player" => $requester]));
                }
                break;
                
            case "delete":
                if (!isset($args[1])) {
                    $this->plugin->showDeleteForm($sender);
                    return true;
                }
                
                $player = $args[1];
                if ($this->plugin->deleteAffinity($sender->getName(), $player)) {
                    $sender->sendMessage($this->plugin->getMessage("affinity_deleted", ["player" => $player]));
                    
                    $playerObj = $this->plugin->getServer()->getPlayerByPrefix($player);
                    if ($playerObj !== null) {
                        $playerObj->sendMessage($this->plugin->getMessage("affinity_deleted_target", ["player" => $sender->getName()]));
                    }
                } else {
                    $sender->sendMessage($this->plugin->getMessage("affinity_not_found", ["player" => $player]));
                }
                break;
                
            case "list":
                $this->plugin->showListForm($sender);
                break;
                
            case "types":
                $this->plugin->showTypesForm($sender);
                break;
                
            default:
                $this->plugin->showMainForm($sender);
                return true;
        }
        
        return true;
    }
    
    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
