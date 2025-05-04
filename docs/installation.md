# Installation Guide

## Quick Install

```bash
# Clone the repository
git clone https://github.com/Silberengel/sybil.git
cd sybil

# Run the installation script
chmod +x bin/install.sh
./bin/install.sh
```

The installation script will:
1. Install PHP 8.1 and required extensions
2. Install Composer (if not already installed)
3. Install Sybil and its dependencies
4. Set up the `sybil` command alias
5. Configure your environment
6. Install command completion

After installation, restart your terminal or run:
```bash
source ~/.bashrc  # or source ~/.zshrc
```

## Manual Installation

### Requirements

- PHP 8.1 or higher
- Required PHP extensions:
  - yaml
  - json
  - mbstring
  - xml
  - curl
  - sodium
- Composer (PHP package manager)

### Installation Steps

1. Install PHP and required extensions:

   **Debian/Ubuntu:**
   ```bash
   sudo apt-get update
   sudo apt-get install -y software-properties-common
   sudo add-apt-repository -y ppa:ondrej/php
   sudo apt-get update
   sudo apt-get install -y php8.1 php8.1-cli php8.1-yaml php8.1-json php8.1-mbstring php8.1-xml php8.1-curl php8.1-sodium
   sudo apt-get install -y libyaml-dev libsodium-dev
   ```

   **Fedora:**
   ```bash
   sudo dnf install -y epel-release
   sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
   sudo dnf module enable -y php:remi-8.1
   sudo dnf install -y php php-cli php-yaml php-json php-mbstring php-xml php-curl php-sodium
   sudo dnf install -y libyaml-devel libsodium-devel
   ```

   **macOS:**
   ```bash
   brew install php@8.1
   brew install libyaml libsodium
   pecl install yaml
   ```

2. Install Composer:
   ```bash
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php --install-dir=/usr/local/bin --filename=composer
   php -r "unlink('composer-setup.php');"
   ```

3. Install Sybil:
   ```bash
   composer require silberengel/sybil
   ```

4. Set up environment:
   ```bash
   # Add to your shell configuration file (.bashrc or .zshrc)
   export PATH="$PATH:$HOME/.composer/vendor/bin"
   alias sybil="php $HOME/.composer/vendor/bin/sybil"
   ```

5. Install command completion:
   ```bash
   # Create completion directory
   mkdir -p ~/.local/share/bash-completion/completions
   
   # Copy completion script
   cp bin/completion.sh ~/.local/share/bash-completion/completions/sybil
   chmod +x ~/.local/share/bash-completion/completions/sybil
   
   # Add to your shell configuration
   echo "source ~/.local/share/bash-completion/completions/sybil" >> ~/.bashrc  # or ~/.zshrc
   ```

## Configuration

1. Set your Nostr key:
   ```bash
   export NOSTR_SECRET_KEY=your_private_key_here
   ```

2. (Optional) Add to your shell configuration file to persist:
   ```bash
   echo 'export NOSTR_SECRET_KEY=your_private_key_here' >> ~/.bashrc  # or ~/.zshrc
   ```

## Verification

To verify the installation:
```bash
# Check Sybil version
sybil version  # Should show the current version (e.g., "Sybil version 1.2.0")

# Test command completion
sybil <TAB>  # Should show available commands
```

## Troubleshooting

If you encounter any issues during installation:

1. Check PHP version:
   ```bash
   php -v  # Should show PHP 8.1 or higher
   ```

2. Verify required extensions:
   ```bash
   php -m | grep -E 'yaml|json|mbstring|xml|curl|sodium'
   ```

3. Check Composer installation:
   ```bash
   composer --version
   ```

4. Verify Sybil installation:
   ```bash
   which sybil
   ```

Return to the [Read Me](./../README.md)