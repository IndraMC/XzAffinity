
## 📋 Overview

**XzAffinity** is a comprehensive relationship system for PocketMine-MP servers, inspired by Mobile Legends' affinity system. It allows players to form special relationships with each other, such as Lovers, Best Friends, Siblings, or Rivals. This plugin enhances player interaction and creates a more engaging social experience on your server.

Developed by **IndraMC**, XzAffinity features an intuitive UI system powered by FormAPI, making it easy for players to manage their relationships.

## ✨ Features

- **Multiple Affinity Types**: Customize different relationship types (Lovers, Best Friends, Siblings, Rivals, etc.)
- **User-friendly UI**: Easy-to-use forms for all player interactions
- **Request System**: Send, accept, or decline affinity requests
- **Relationship Management**: View and delete existing affinities
- **Join/Quit Notifications**: Get notified when your affinity partners join or leave the server
- **Admin Controls**: Easily manage affinity types and their limits
- **Per-Type Limits**: Set different maximum limits for each affinity type
- **Fully Configurable**: Customize all messages and settings

## 🗒️ Example usage

### Player
- Sending an request type `/affinity request` or select `send request` from main menu
- select a player from the list and choose an affinity type to request
- the player will receiver a notification about your request
- accepting/declining requests type `/affinity` or `/affinity accept` to open the requests menu
- Select the request you want to manage
- Choose to accept or decline the request
- Both players will be notified of the decision
- Managing Affinities
- Type `/affinity list` to view your current affinities
- Use `/affinity delete <player>` to remove an affinity

### Admin
- Type `/affinityadmin` to open the admin menu
- Select `Manage Affinity Types` to view, edit, or delete types
- Select `Add New Affinity` Type to create a new type


## 📥 Installation

1. Download the latest version of XzAffinity from [Poggit](https://poggit.pmmp.io/p/XzAffinity)
3. Place both plugins in your server's `plugins` folder
4. Restart your server
5. Configure the plugin settings in `plugin_data/XzAffinity/config.yml` (optional)

## 🔧 Commands

### Player Commands
| Command | Description | Permission |
|---------|-------------|------------|
| `/affinity` | Opens the main affinity menu | xzaffinity.command.use |
| `/affinity request` | Opens the player selection menu to send a request | xzaffinity.command.use |
| `/affinity accept <player>` | Accept an affinity request | xzaffinity.command.use |
| `/affinity decline <player>` | Decline an affinity request | xzaffinity.command.use |
| `/affinity delete <player>` | Delete an existing affinity | xzaffinity.command.use |
| `/affinity list` | View your affinities and pending requests | xzaffinity.command.use |
| `/affinity types` | View available affinity types and their limits | xzaffinity.command.use |

### Admin Commands
| Command | Description | Permission |
|---------|-------------|------------|
| `/affinityadmin` | Opens the admin management menu | xzaffinity.command.admin |

## 🔒 Permissions

| Permission | Description | Default |
|------------|-------------|---------|
| xzaffinity.command.use | Allows using all player commands | true |
| xzaffinity.command.admin | Allows using admin commands | op |

## ⚙️ Configuration

XzAffinity is highly configurable. You can customize all messages in the `config.yml` file, and you can customize affinity types and maximum limits in the `settings.yml` file.

### Default Affinity Types
- Lovers (Default max: 1)
- Best Friends (Default max: 3)
- Siblings (Default max: 5)
- Rivals (Default max: 3)

### Example settings.yml
```yaml
# Affinity types and their maximum limits
affinity_types:
  - "Lovers"
  - "Best Friends"
  - "Siblings"
  - "Rivals"

max_affinities:
  "Lovers": 1
  "Best Friends": 3
  "Siblings": 5
  "Rivals": 3

# You can customize all messages in the messages section
```

## 🙏 Credits
Developer: IndraMC
UI Library: FormAPI by jojoe77777
GitHub: [LordzDraa](https://github.com/IndraMC)
Discord: IndraMC#7345
