<?php

declare(strict_types=1);

namespace IndraMC\XzAffinity;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use IndraMC\XzAffinity\commands\AffinityCommand;
use IndraMC\XzAffinity\commands\AffinityAdminCommand;
use IndraMC\XzAffinity\libs\jojoe77777\FormAPI\SimpleForm;
use IndraMC\XzAffinity\libs\jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener {
    /** @var Config */
    private Config $affinityData;
    
    /** @var Config */
    private Config $requestsData;
    
    /** @var Config */
    private Config $settings;
    
    /** @var array */
    private array $messages;
    
    /** @var array */
    private array $affinityTypes;
    
    /** @var array */
    private array $maxAffinities;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        // Check for FormAPI dependency
        if (!class_exists(SimpleForm::class)) {
            $this->getLogger()->error("FormAPI not found! Please install FormAPI by jojoe77777.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        
        // Create plugin directory if it doesn't exist
        @mkdir($this->getDataFolder());
        
        // Initialize configs
        $this->saveDefaultConfig();
        $this->affinityData = new Config($this->getDataFolder() . "affinities.yml", Config::YAML);
        $this->requestsData = new Config($this->getDataFolder() . "requests.yml", Config::YAML);
        
        // Initialize settings with default values
        $defaultMaxAffinities = [
            "Lovers" => 1,
            "Best Friends" => 3,
            "Siblings" => 5,
            "Rivals" => 3
        ];
        
        $this->settings = new Config($this->getDataFolder() . "settings.yml", Config::YAML, [
            "affinity_types" => ["Lovers", "Best Friends", "Siblings", "Rivals"],
            "max_affinities" => $defaultMaxAffinities
        ]);
        
        // Load settings
        $this->affinityTypes = $this->settings->get("affinity_types", ["Lovers", "Best Friends", "Siblings", "Rivals"]);
        $this->maxAffinities = $this->settings->get("max_affinities", $defaultMaxAffinities);
        
        // Ensure all affinity types have a max value
        foreach ($this->affinityTypes as $type) {
            if (!isset($this->maxAffinities[$type])) {
                $this->maxAffinities[$type] = 3; // Default value
            }
        }
        
        // Save updated settings
        $this->settings->set("max_affinities", $this->maxAffinities);
        $this->settings->save();
        
        // Load messages
        $this->messages = $this->getConfig()->get("messages", []);
        
        // Register commands
        $this->getServer()->getCommandMap()->register("xzaffinity", new AffinityCommand($this));
        $this->getServer()->getCommandMap()->register("xzaffinityadmin", new AffinityAdminCommand($this));
        
        $this->getLogger()->info(TextFormat::GREEN . "XzAffinity by IndraMC has been enabled!");
    }
    
    public function onDisable(): void {
        // Save all data
        $this->saveAllData();
        $this->getLogger()->info(TextFormat::RED . "XzAffinity by IndraMC has been disabled!");
    }
    
    /**
     * Handle player join event
     * 
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Check if player has any affinities
        $affinities = $this->getPlayerAffinities($playerName);
        if (empty($affinities)) {
            return;
        }
        
        // Notify online affinity partners
        foreach ($affinities as $partner => $data) {
            $partnerPlayer = $this->getServer()->getPlayerByPrefix($partner);
            if ($partnerPlayer !== null) {
                $partnerPlayer->sendMessage($this->getMessage("affinity_partner_joined", [
                    "player" => $playerName,
                    "type" => $data["type"]
                ]));
            }
        }
    }
    
    /**
     * Handle player quit event
     * 
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Check if player has any affinities
        $affinities = $this->getPlayerAffinities($playerName);
        if (empty($affinities)) {
            return;
        }
        
        // Notify online affinity partners
        foreach ($affinities as $partner => $data) {
            $partnerPlayer = $this->getServer()->getPlayerByPrefix($partner);
            if ($partnerPlayer !== null) {
                $partnerPlayer->sendMessage($this->getMessage("affinity_partner_left", [
                    "player" => $playerName,
                    "type" => $data["type"]
                ]));
            }
        }
    }
    
    /**
     * Save all plugin data
     */
    public function saveAllData(): void {
        $this->affinityData->save();
        $this->requestsData->save();
        $this->settings->save();
    }
    
    /**
     * Get player's affinities
     * 
     * @param string $player
     * @return array
     */
    public function getPlayerAffinities(string $player): array {
        return $this->affinityData->get(strtolower($player), []);
    }
    
    /**
     * Get player's pending requests
     * 
     * @param string $player
     * @return array
     */
    public function getPlayerRequests(string $player): array {
        return $this->requestsData->get(strtolower($player), []);
    }
    
    /**
     * Count player's affinities by type
     * 
     * @param string $player
     * @param string $type
     * @return int
     */
    public function countPlayerAffinitiesByType(string $player, string $type): int {
        $affinities = $this->getPlayerAffinities($player);
        $count = 0;
        
        foreach ($affinities as $affinity) {
            if ($affinity["type"] === $type) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Send an affinity request
     * 
     * @param string $sender
     * @param string $target
     * @param string $type
     * @return bool
     */
    public function sendRequest(string $sender, string $target, string $type): bool {
        $sender = strtolower($sender);
        $target = strtolower($target);
        
        // Check if sender already has max affinities of this type
        if ($this->countPlayerAffinitiesByType($sender, $type) >= $this->getMaxAffinityForType($type)) {
            return false;
        }
        
        // Check if target already has max affinities of this type
        if ($this->countPlayerAffinitiesByType($target, $type) >= $this->getMaxAffinityForType($type)) {
            return false;
        }
        
        // Check if they already have an affinity
        $senderAffinities = $this->getPlayerAffinities($sender);
        if (isset($senderAffinities[$target])) {
            return false;
        }
        
        // Check if request already exists
        $targetRequests = $this->getPlayerRequests($target);
        if (isset($targetRequests[$sender])) {
            return false;
        }
        
        // Add request
        $targetRequests[$sender] = [
            "type" => $type,
            "time" => time()
        ];
        
        $this->requestsData->set($target, $targetRequests);
        $this->requestsData->save();
        
        return true;
    }
    
    /**
     * Accept an affinity request
     * 
     * @param string $player
     * @param string $requester
     * @return bool
     */
    public function acceptRequest(string $player, string $requester): bool {
        $player = strtolower($player);
        $requester = strtolower($requester);
        
        // Check if request exists
        $playerRequests = $this->getPlayerRequests($player);
        if (!isset($playerRequests[$requester])) {
            return false;
        }
        
        $type = $playerRequests[$requester]["type"];
        
        // Check if player already has max affinities of this type
        if ($this->countPlayerAffinitiesByType($player, $type) >= $this->getMaxAffinityForType($type)) {
            return false;
        }
        
        // Check if requester already has max affinities of this type
        if ($this->countPlayerAffinitiesByType($requester, $type) >= $this->getMaxAffinityForType($type)) {
            return false;
        }
        
        // Add affinity for both players
        $playerAffinities = $this->getPlayerAffinities($player);
        $requesterAffinities = $this->getPlayerAffinities($requester);
        
        $playerAffinities[$requester] = [
            "type" => $type,
            "since" => time()
        ];
        
        $requesterAffinities[$player] = [
            "type" => $type,
            "since" => time()
        ];
        
        $this->affinityData->set($player, $playerAffinities);
        $this->affinityData->set($requester, $requesterAffinities);
        
        // Remove request
        unset($playerRequests[$requester]);
        $this->requestsData->set($player, $playerRequests);
        
        $this->affinityData->save();
        $this->requestsData->save();
        
        return true;
    }
    
    /**
     * Decline an affinity request
     * 
     * @param string $player
     * @param string $requester
     * @return bool
     */
    public function declineRequest(string $player, string $requester): bool {
        $player = strtolower($player);
        $requester = strtolower($requester);
        
        // Check if request exists
        $playerRequests = $this->getPlayerRequests($player);
        if (!isset($playerRequests[$requester])) {
            return false;
        }
        
        // Remove request
        unset($playerRequests[$requester]);
        $this->requestsData->set($player, $playerRequests);
        $this->requestsData->save();
        
        return true;
    }
    
    /**
     * Delete an affinity
     * 
     * @param string $player1
     * @param string $player2
     * @return bool
     */
    public function deleteAffinity(string $player1, string $player2): bool {
        $player1 = strtolower($player1);
        $player2 = strtolower($player2);
        
        // Check if affinity exists
        $player1Affinities = $this->getPlayerAffinities($player1);
        $player2Affinities = $this->getPlayerAffinities($player2);
        
        if (!isset($player1Affinities[$player2]) || !isset($player2Affinities[$player1])) {
            return false;
        }
        
        // Remove affinity for both players
        unset($player1Affinities[$player2]);
        unset($player2Affinities[$player1]);
        
        $this->affinityData->set($player1, $player1Affinities);
        $this->affinityData->set($player2, $player2Affinities);
        $this->affinityData->save();
        
        return true;
    }
    
    /**
     * Update affinity types
     * 
     * @param array $types
     */
    public function updateAffinityTypes(array $types): void {
        $this->affinityTypes = $types;
        $this->settings->set("affinity_types", $types);
        
        // Update max affinities to include new types
        foreach ($types as $type) {
            if (!isset($this->maxAffinities[$type])) {
                $this->maxAffinities[$type] = 3; // Default value
            }
        }
        
        // Remove types that no longer exist
        foreach (array_keys($this->maxAffinities) as $type) {
            if (!in_array($type, $types)) {
                unset($this->maxAffinities[$type]);
            }
        }
        
        $this->settings->set("max_affinities", $this->maxAffinities);
        $this->settings->save();
    }
    
    /**
     * Add a new affinity type
     * 
     * @param string $type
     * @param int $max
     * @return bool
     */
    public function addAffinityType(string $type, int $max): bool {
        if (in_array($type, $this->affinityTypes)) {
            return false;
        }
        
        $this->affinityTypes[] = $type;
        $this->maxAffinities[$type] = $max;
        
        $this->settings->set("affinity_types", $this->affinityTypes);
        $this->settings->set("max_affinities", $this->maxAffinities);
        $this->settings->save();
        
        return true;
    }
    
    /**
     * Delete an affinity type
     * 
     * @param string $type
     * @return bool
     */
    public function deleteAffinityType(string $type): bool {
        $key = array_search($type, $this->affinityTypes);
        if ($key === false) {
            return false;
        }
        
        unset($this->affinityTypes[$key]);
        $this->affinityTypes = array_values($this->affinityTypes); // Re-index array
        
        if (isset($this->maxAffinities[$type])) {
            unset($this->maxAffinities[$type]);
        }
        
        $this->settings->set("affinity_types", $this->affinityTypes);
        $this->settings->set("max_affinities", $this->maxAffinities);
        $this->settings->save();
        
        return true;
    }
    
    /**
     * Update max affinity for a specific type
     * 
     * @param string $type
     * @param int $max
     */
    public function updateMaxAffinityForType(string $type, int $max): void {
        if (!in_array($type, $this->affinityTypes)) {
            return;
        }
        
        $this->maxAffinities[$type] = $max;
        $this->settings->set("max_affinities", $this->maxAffinities);
        $this->settings->save();
    }
    
    /**
     * Get affinity types
     * 
     * @return array
     */
    public function getAffinityTypes(): array {
        return $this->affinityTypes;
    }
    
    /**
     * Get max affinity for a specific type
     * 
     * @param string $type
     * @return int
     */
    public function getMaxAffinityForType(string $type): int {
        return $this->maxAffinities[$type] ?? 3; // Default to 3 if not set
    }
    
    /**
     * Get all max affinities
     * 
     * @return array
     */
    public function getMaxAffinities(): array {
        return $this->maxAffinities;
    }
    
    /**
     * Get message from config
     * 
     * @param string $key
     * @param array $params
     * @return string
     */
    public function getMessage(string $key, array $params = []): string {
        $message = $this->messages[$key] ?? "Message not found: {$key}";
        
        foreach ($params as $param => $value) {
            $message = str_replace("{" . $param . "}", $value, $message);
        }
        
        return TextFormat::colorize($message);
    }
    
    /**
     * Show main UI form
     * 
     * @param Player $player
     */
    public function showMainForm(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                return;
            }
            
            switch ($data) {
                case 0: // Request
                    $this->showPlayerListForm($player);
                    break;
                case 1: // Accept/Decline
                    $this->showRequestsForm($player);
                    break;
                case 2: // Delete
                    $this->showDeleteForm($player);
                    break;
                case 3: // List
                    $this->showListForm($player);
                    break;
                case 4: // Types
                    $this->showTypesForm($player);
                    break;
            }
        });
        
        $form->setTitle($this->getMessage("ui_main_title"));
        $form->setContent($this->getMessage("ui_main_content"));
        $form->addButton($this->getMessage("ui_main_request"));
        $form->addButton($this->getMessage("ui_main_requests"));
        $form->addButton($this->getMessage("ui_main_delete"));
        $form->addButton($this->getMessage("ui_main_list"));
        $form->addButton($this->getMessage("ui_main_types"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show player list form for sending requests
     * 
     * @param Player $player
     */
    public function showPlayerListForm(Player $player): void {
        $players = [];
        
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->getName() !== $player->getName()) {
                $players[] = $onlinePlayer;
            }
        }
        
        if (empty($players)) {
            $player->sendMessage($this->getMessage("ui_player_list_empty"));
            return;
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($players) {
            if ($data === null) {
                return;
            }
            
            if (!isset($players[$data])) {
                return;
            }
            
            $target = $players[$data];
            $this->showAffinityTypeSelectionForm($player, $target->getName());
        });
        
        $form->setTitle($this->getMessage("ui_player_list_title"));
        $form->setContent($this->getMessage("ui_player_list_content"));
        
        foreach ($players as $onlinePlayer) {
            $form->addButton($this->getMessage("ui_player_list_button", [
                "player" => $onlinePlayer->getName()
            ]));
        }
        
        $player->sendForm($form);
    }
    
    /**
     * Show affinity type selection form
     * 
     * @param Player $player
     * @param string $targetName
     */
    public function showAffinityTypeSelectionForm(Player $player, string $targetName): void {
        $types = $this->getAffinityTypes();
        
        if (empty($types)) {
            $player->sendMessage($this->getMessage("ui_type_selection_empty"));
            return;
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($targetName, $types) {
            if ($data === null) {
                return;
            }
            
            if (!isset($types[$data])) {
                return;
            }
            
            $type = $types[$data];
            $target = $this->getServer()->getPlayerByPrefix($targetName);
            
            if ($target === null) {
                $player->sendMessage($this->getMessage("player_not_found", ["player" => $targetName]));
                return;
            }
            
            if ($this->sendRequest($player->getName(), $target->getName(), $type)) {
                $player->sendMessage($this->getMessage("request_sent", [
                    "player" => $target->getName(),
                    "type" => $type
                ]));
                
                $target->sendMessage($this->getMessage("request_received", [
                    "player" => $player->getName(),
                    "type" => $type
                ]));
            } else {
                $player->sendMessage($this->getMessage("request_failed", ["player" => $target->getName()]));
            }
        });
        
        $form->setTitle($this->getMessage("ui_type_selection_title"));
        $form->setContent($this->getMessage("ui_type_selection_content", ["player" => $targetName]));
        
        foreach ($types as $type) {
            $max = $this->getMaxAffinityForType($type);
            $current = $this->countPlayerAffinitiesByType($player->getName(), $type);
            
            $form->addButton($this->getMessage("ui_type_selection_button", [
                "type" => $type,
                "current" => (string) $current,
                "max" => (string) $max
            ]));
        }
        
        $player->sendForm($form);
    }
    
    /**
     * Show requests form
     * 
     * @param Player $player
     */
    public function showRequestsForm(Player $player): void {
        $requests = $this->getPlayerRequests($player->getName());
        
        if (empty($requests)) {
            $player->sendMessage($this->getMessage("ui_requests_empty"));
            return;
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($requests) {
            if ($data === null) {
                return;
            }
            
            $requesters = array_keys($requests);
            if (!isset($requesters[$data])) {
                return;
            }
            
            $requester = $requesters[$data];
            $this->showRequestActionForm($player, $requester, $requests[$requester]["type"]);
        });
        
        $form->setTitle($this->getMessage("ui_requests_title"));
        $form->setContent($this->getMessage("ui_requests_content"));
        
        foreach ($requests as $requester => $data) {
            $form->addButton($this->getMessage("ui_requests_button", [
                "player" => $requester,
                "type" => $data["type"]
            ]));
        }
        
        $player->sendForm($form);
    }
    
    /**
     * Show request action form
     * 
     * @param Player $player
     * @param string $requester
     * @param string $type
     */
    public function showRequestActionForm(Player $player, string $requester, string $type): void {
        $form = new SimpleForm(function (Player $player, ?int $data) use ($requester, $type) {
            if ($data === null) {
                return;
            }
            
            switch ($data) {
                case 0: // Accept
                    if ($this->acceptRequest($player->getName(), $requester)) {
                        $player->sendMessage($this->getMessage("request_accepted", ["player" => $requester]));
                        
                        $requesterPlayer = $this->getServer()->getPlayerByPrefix($requester);
                        if ($requesterPlayer !== null) {
                            $requesterPlayer->sendMessage($this->getMessage("request_accepted_target", ["player" => $player->getName()]));
                        }
                    } else {
                        $player->sendMessage($this->getMessage("request_accept_failed", ["player" => $requester]));
                    }
                    break;
                    
                case 1: // Decline
                    if ($this->declineRequest($player->getName(), $requester)) {
                        $player->sendMessage($this->getMessage("request_declined", ["player" => $requester]));
                        
                        $requesterPlayer = $this->getServer()->getPlayerByPrefix($requester);
                        if ($requesterPlayer !== null) {
                            $requesterPlayer->sendMessage($this->getMessage("request_declined_target", ["player" => $player->getName()]));
                        }
                    } else {
                        $player->sendMessage($this->getMessage("request_not_found", ["player" => $requester]));
                    }
                    break;
            }
        });
        
        $form->setTitle($this->getMessage("ui_request_action_title"));
        $form->setContent($this->getMessage("ui_request_action_content", [
            "player" => $requester,
            "type" => $type
        ]));
        $form->addButton($this->getMessage("ui_request_action_accept"));
        $form->addButton($this->getMessage("ui_request_action_decline"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show delete form
     * 
     * @param Player $player
     */
    public function showDeleteForm(Player $player): void {
        $affinities = $this->getPlayerAffinities($player->getName());
        
        if (empty($affinities)) {
            $player->sendMessage($this->getMessage("ui_delete_empty"));
            return;
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($affinities) {
            if ($data === null) {
                return;
            }
            
            $partners = array_keys($affinities);
            if (!isset($partners[$data])) {
                return;
            }
            
            $partner = $partners[$data];
            $this->showDeleteConfirmForm($player, $partner, $affinities[$partner]["type"]);
        });
        
        $form->setTitle($this->getMessage("ui_delete_title"));
        $form->setContent($this->getMessage("ui_delete_content"));
        
        foreach ($affinities as $partner => $data) {
            $form->addButton($this->getMessage("ui_delete_button", [
                "player" => $partner,
                "type" => $data["type"]
            ]));
        }
        
        $player->sendForm($form);
    }
    
    /**
     * Show delete confirmation form
     * 
     * @param Player $player
     * @param string $partner
     * @param string $type
     */
    public function showDeleteConfirmForm(Player $player, string $partner, string $type): void {
        $form = new SimpleForm(function (Player $player, ?int $data) use ($partner) {
            if ($data === null) {
                return;
            }
            
            if ($data === 0) { // Confirm
                if ($this->deleteAffinity($player->getName(), $partner)) {
                    $player->sendMessage($this->getMessage("affinity_deleted", ["player" => $partner]));
                    
                    $partnerPlayer = $this->getServer()->getPlayerByPrefix($partner);
                    if ($partnerPlayer !== null) {
                        $partnerPlayer->sendMessage($this->getMessage("affinity_deleted_target", ["player" => $player->getName()]));
                    }
                } else {
                    $player->sendMessage($this->getMessage("affinity_not_found", ["player" => $partner]));
                }
            }
            // If data is 1, it's cancel, so do nothing
        });
        
        $form->setTitle($this->getMessage("ui_delete_confirm_title"));
        $form->setContent($this->getMessage("ui_delete_confirm_content", [
            "player" => $partner,
            "type" => $type
        ]));
        $form->addButton($this->getMessage("ui_delete_confirm_yes"));
        $form->addButton($this->getMessage("ui_delete_confirm_no"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show list form
     * 
     * @param Player $player
     */
    public function showListForm(Player $player): void {
        $affinities = $this->getPlayerAffinities($player->getName());
        $requests = $this->getPlayerRequests($player->getName());
        
        $content = "";
        
        if (empty($affinities) && empty($requests)) {
            $content = $this->getMessage("no_affinities");
        } else {
            if (!empty($affinities)) {
                $content .= $this->getMessage("ui_list_affinities_header") . "\n\n";
                
                foreach ($affinities as $partner => $data) {
                    $content .= $this->getMessage("ui_list_affinities_item", [
                        "player" => $partner,
                        "type" => $data["type"],
                        "since" => date("Y-m-d", $data["since"])
                    ]) . "\n";
                }
                
                $content .= "\n";
            }
            
            if (!empty($requests)) {
                $content .= $this->getMessage("ui_list_requests_header") . "\n\n";
                
                foreach ($requests as $requester => $data) {
                    $content .= $this->getMessage("ui_list_requests_item", [
                        "player" => $requester,
                        "type" => $data["type"],
                        "time" => date("Y-m-d", $data["time"])
                    ]) . "\n";
                }
            }
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) {
            // Just close the form when clicked
        });
        
        $form->setTitle($this->getMessage("ui_list_title"));
        $form->setContent($content);
        $form->addButton($this->getMessage("ui_list_close"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show types form
     * 
     * @param Player $player
     */
    public function showTypesForm(Player $player): void {
        $types = $this->getAffinityTypes();
        $maxAffinities = $this->getMaxAffinities();
        
        $content = $this->getMessage("ui_types_content") . "\n\n";
        
        foreach ($types as $type) {
            $max = $maxAffinities[$type] ?? 3;
            $content .= $this->getMessage("ui_types_item", [
                "type" => $type,
                "max" => (string) $max
            ]) . "\n";
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) {
            // Just close the form when clicked
        });
        
        $form->setTitle($this->getMessage("ui_types_title"));
        $form->setContent($content);
        $form->addButton($this->getMessage("ui_types_close"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show admin main form
     * 
     * @param Player $player
     */
    public function showAdminForm(Player $player): void {
        if (!$player->hasPermission("xzaffinity.command.admin")) {
            $player->sendMessage($this->getMessage("no_permission"));
            return;
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) {
                return;
            }
            
            switch ($data) {
                case 0: // Manage Types
                    $this->showAdminTypesListForm($player);
                    break;
                case 1: // Add New Type
                    $this->showAdminAddTypeForm($player);
                    break;
            }
        });
        
        $form->setTitle($this->getMessage("ui_admin_title"));
        $form->setContent($this->getMessage("ui_admin_content"));
        $form->addButton($this->getMessage("ui_admin_manage_types"));
        $form->addButton($this->getMessage("ui_admin_add_type"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show admin types list form
     * 
     * @param Player $player
     */
    public function showAdminTypesListForm(Player $player): void {
        $types = $this->getAffinityTypes();
        
        if (empty($types)) {
            $player->sendMessage($this->getMessage("ui_admin_types_empty"));
            return;
        }
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($types) {
            if ($data === null) {
                return;
            }
            
            if (!isset($types[$data])) {
                return;
            }
            
            $type = $types[$data];
            $this->showAdminTypeActionForm($player, $type);
        });
        
        $form->setTitle($this->getMessage("ui_admin_types_list_title"));
        $form->setContent($this->getMessage("ui_admin_types_list_content"));
        
        foreach ($types as $type) {
            $max = $this->getMaxAffinityForType($type);
            $form->addButton($this->getMessage("ui_admin_types_list_button", [
                "type" => $type,
                "max" => (string) $max
            ]));
        }
        
        $player->sendForm($form);
    }
    
    /**
     * Show admin type action form
     * 
     * @param Player $player
     * @param string $type
     */
    public function showAdminTypeActionForm(Player $player, string $type): void {
        $form = new SimpleForm(function (Player $player, ?int $data) use ($type) {
            if ($data === null) {
                return;
            }
            
            switch ($data) {
                case 0: // Edit
                    $this->showAdminEditTypeForm($player, $type);
                    break;
                case 1: // Delete
                    $this->showAdminDeleteTypeConfirmForm($player, $type);
                    break;
                case 2: // Back
                    $this->showAdminTypesListForm($player);
                    break;
            }
        });
        
        $form->setTitle($this->getMessage("ui_admin_type_action_title"));
        $form->setContent($this->getMessage("ui_admin_type_action_content", ["type" => $type]));
        $form->addButton($this->getMessage("ui_admin_type_action_edit"));
        $form->addButton($this->getMessage("ui_admin_type_action_delete"));
        $form->addButton($this->getMessage("ui_admin_type_action_back"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show admin edit type form
     * 
     * @param Player $player
     * @param string $type
     */
    public function showAdminEditTypeForm(Player $player, string $type): void {
        $max = $this->getMaxAffinityForType($type);
        
        $form = new CustomForm(function (Player $player, ?array $data) use ($type) {
            if ($data === null) {
                return;
            }
            
            $newType = $data[1] ?? "";
            $newMax = (int) ($data[2] ?? 3);
            
            if (empty($newType)) {
                $player->sendMessage($this->getMessage("ui_admin_edit_type_empty"));
                return;
            }
            
            if ($newMax < 1) {
                $newMax = 1;
            }
            
            // If type name changed, we need to update it
            if ($newType !== $type) {
                // Delete old type
                $this->deleteAffinityType($type);
                
                // Add new type
                $this->addAffinityType($newType, $newMax);
                
                $player->sendMessage($this->getMessage("admin_type_renamed", [
                    "old_type" => $type,
                    "new_type" => $newType
                ]));
            } else {
                // Just update max affinity
                $this->updateMaxAffinityForType($type, $newMax);
                
                $player->sendMessage($this->getMessage("admin_max_updated_type", [
                    "type" => $type,
                    "max" => (string) $newMax
                ]));
            }
            
            // Return to types list
            $this->showAdminTypesListForm($player);
        });
        
        $form->setTitle($this->getMessage("ui_admin_edit_type_title"));
        $form->addLabel($this->getMessage("ui_admin_edit_type_content", ["type" => $type]));
        $form->addInput($this->getMessage("ui_admin_edit_type_input"), "Type name", $type);
        $form->addSlider($this->getMessage("ui_admin_edit_type_slider"), 1, 20, 1, $max);
        
        $player->sendForm($form);
    }
    
    /**
     * Show admin delete type confirmation form
     * 
     * @param Player $player
     * @param string $type
     */
    public function showAdminDeleteTypeConfirmForm(Player $player, string $type): void {
        $form = new SimpleForm(function (Player $player, ?int $data) use ($type) {
            if ($data === null) {
                return;
            }
            
            if ($data === 0) { // Confirm
                if ($this->deleteAffinityType($type)) {
                    $player->sendMessage($this->getMessage("admin_type_deleted", ["type" => $type]));
                } else {
                    $player->sendMessage($this->getMessage("admin_type_delete_failed", ["type" => $type]));
                }
            }
            
            // Return to types list
            $this->showAdminTypesListForm($player);
        });
        
        $form->setTitle($this->getMessage("ui_admin_delete_type_title"));
        $form->setContent($this->getMessage("ui_admin_delete_type_content", ["type" => $type]));
        $form->addButton($this->getMessage("ui_admin_delete_type_confirm"));
        $form->addButton($this->getMessage("ui_admin_delete_type_cancel"));
        
        $player->sendForm($form);
    }
    
    /**
     * Show admin add type form
     * 
     * @param Player $player
     */
    public function showAdminAddTypeForm(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data === null) {
                return;
            }
            
            $type = $data[1] ?? "";
            $max = (int) ($data[2] ?? 3);
            
            if (empty($type)) {
                $player->sendMessage($this->getMessage("ui_admin_add_type_empty"));
                return;
            }
            
            if ($max < 1) {
                $max = 1;
            }
            
            if ($this->addAffinityType($type, $max)) {
                $player->sendMessage($this->getMessage("admin_type_added", [
                    "type" => $type,
                    "max" => (string) $max
                ]));
            } else {
                $player->sendMessage($this->getMessage("admin_type_add_failed", ["type" => $type]));
            }
            
            // Return to admin main form
            $this->showAdminForm($player);
        });
        
        $form->setTitle($this->getMessage("ui_admin_add_type_title"));
        $form->addLabel($this->getMessage("ui_admin_add_type_content"));
        $form->addInput($this->getMessage("ui_admin_add_type_input"), "Type name");
        $form->addSlider($this->getMessage("ui_admin_add_type_slider"), 1, 20, 1, 3);
        
        $player->sendForm($form);
    }
}
