#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print status messages
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to detect OS
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command_exists apt-get; then
            echo "debian"
        elif command_exists dnf; then
            echo "fedora"
        else
            echo "linux"
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        echo "macos"
    else
        echo "unknown"
    fi
}

# Function to install PHP and required extensions
install_php() {
    local os=$(detect_os)
    
    case $os in
        "debian")
            print_status "Installing PHP 8.1 and required extensions..."
            sudo apt-get update
            sudo apt-get install -y software-properties-common
            sudo add-apt-repository -y ppa:ondrej/php
            sudo apt-get update
            sudo apt-get install -y php8.1 php8.1-cli php8.1-yaml php8.1-json php8.1-mbstring php8.1-xml php8.1-curl php8.1-sodium
            sudo apt-get install -y libyaml-dev libsodium-dev
            ;;
        "fedora")
            print_status "Installing PHP 8.1 and required extensions..."
            sudo dnf install -y epel-release
            sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
            sudo dnf module enable -y php:remi-8.1
            sudo dnf install -y php php-cli php-yaml php-json php-mbstring php-xml php-curl php-sodium
            sudo dnf install -y libyaml-devel libsodium-devel
            ;;
        "macos")
            print_status "Installing PHP 8.1 and required extensions..."
            if ! command_exists brew; then
                print_error "Homebrew not found. Please install Homebrew first:"
                echo '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"'
                exit 1
            fi
            brew install php@8.1
            brew install libyaml libsodium
            pecl install yaml
            ;;
        *)
            print_error "Unsupported operating system. Please install PHP 8.1 and required extensions manually."
            exit 1
            ;;
    esac

    # Verify PHP version
    php_version=$(php -v | head -n1 | cut -d " " -f2 | cut -d "." -f1,2)
    if [[ $(echo "$php_version < 8.1" | bc) -eq 1 ]]; then
        print_error "PHP version $php_version is not supported. Please install PHP 8.1 or higher."
        exit 1
    fi

    # Verify required extensions
    required_extensions=("yaml" "json" "mbstring" "xml" "curl" "sodium")
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            print_error "PHP extension $ext is not installed or enabled."
            exit 1
        fi
    done
}

# Function to install Composer
install_composer() {
    if ! command_exists composer; then
        print_status "Installing Composer..."
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer
        php -r "unlink('composer-setup.php');"
    else
        print_status "Composer is already installed."
    fi
}

# Function to install Sybil
install_sybil() {
    print_status "Installing Sybil..."
    composer require silberengel/sybil
}

# Function to setup environment variables
setup_env() {
    local shell_rc
    if [[ -f "$HOME/.bashrc" ]]; then
        shell_rc="$HOME/.bashrc"
    elif [[ -f "$HOME/.zshrc" ]]; then
        shell_rc="$HOME/.zshrc"
    else
        print_warning "Could not find .bashrc or .zshrc. Please add the following to your shell configuration file:"
        echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"'
        echo 'alias sybil="php $HOME/.composer/vendor/bin/sybil"'
        return
    fi

    # Add to shell configuration if not already present
    if ! grep -q "alias sybil=" "$shell_rc"; then
        echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> "$shell_rc"
        echo 'alias sybil="php $HOME/.composer/vendor/bin/sybil"' >> "$shell_rc"
        print_status "Added Sybil alias to $shell_rc"
    else
        print_status "Sybil alias already exists in $shell_rc"
    fi
}

# Function to check and setup Nostr key
setup_nostr_key() {
    if [ -z "$NOSTR_SECRET_KEY" ]; then
        print_warning "NOSTR_SECRET_KEY environment variable is not set."
        print_warning "Please set it after installation:"
        echo 'export NOSTR_SECRET_KEY=your_private_key_here'
    fi
}

# Main installation process
main() {
    print_status "Starting Sybil installation..."
    
    # Check if running as root
    if [[ $EUID -eq 0 ]]; then
        print_error "Please do not run this script as root"
        exit 1
    fi
    
    # Install PHP and extensions
    install_php
    
    # Install Composer
    install_composer
    
    # Install Sybil
    install_sybil
    
    # Setup environment
    setup_env
    
    # Check Nostr key
    setup_nostr_key
    
    print_status "Installation complete!"
    print_status "Please restart your terminal or run 'source ~/.bashrc' (or 'source ~/.zshrc') to use Sybil"
}

# Run the installation
main 