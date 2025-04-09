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

class AffinityAdminCommand extends Command implements PluginOwned {
    /** @var Main */
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        parent::__construct("affinityadmin", "Admin commands for XzAffinity", "/affinityadmin");
        $this->setPermission("xzaffinity.command.admin");
        $this->plugin = $plugin;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender->hasPermission("xzaffinity.command.admin")) {
            $sender->sendMessage($this->plugin->getMessage("no_permission"));
            return false;
        }
        
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
            return false;
        }
        
        // Show admin UI form
        $this->plugin->showAdminForm($sender);
        return true;
    }
    
    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}
