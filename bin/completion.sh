#!/bin/bash

# Enable bash completion if not already enabled
if [ -n "$BASH_VERSION" ]; then
    if [ -f /etc/bash_completion ] && ! shopt -oq posix; then
        . /etc/bash_completion
    fi
fi

# Enable zsh completion if using zsh
if [ -n "$ZSH_VERSION" ]; then
    autoload -U +X bashcompinit && bashcompinit
fi

_sybil_completion() {
    local cur prev opts
    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"
    
    # Main commands
    opts="note longform wiki publication citation highlight ngit-announce ngit-state ngit-patch ngit-issue ngit-status ngit-feed note-feed longform-feed wiki-feed publication-feed relay-add relay-list relay-info relay-test relay-remove query reply republish broadcast delete completion convert"

    # Command-specific options
    case "${prev}" in
        sybil)
            COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
            return 0
            ;;
        convert)
            # File completion for input files
            COMPREPLY=( $(compgen -f -X "!*.{txt,rtf,html,md,pdf}" -- ${cur}) )
            return 0
            ;;
        --output|-o)
            # File completion for output files
            COMPREPLY=( $(compgen -f -X "!*.adoc" -- ${cur}) )
            return 0
            ;;
        --relay|-r)
            # Could add common relay URLs here
            COMPREPLY=( $(compgen -W "wss://relay.damus.io wss://relay.nostr.band wss://theforest.nostr1.com" -- ${cur}) )
            return 0
            ;;
        --protocol)
            COMPREPLY=( $(compgen -W "ws http" -- ${cur}) )
            return 0
            ;;
        --type|-t)
            if [[ "${COMP_WORDS[COMP_CWORD-2]}" == "publication-feed" ]]; then
                COMPREPLY=( $(compgen -W "index content all" -- ${cur}) )
                return 0
            fi
            ;;
        --status)
            if [[ "${COMP_WORDS[COMP_CWORD-2]}" == "ngit-status" ]]; then
                COMPREPLY=( $(compgen -W "open applied merged resolved closed draft" -- ${cur}) )
                return 0
            fi
            ;;
        --role)
            if [[ "${COMP_WORDS[COMP_CWORD-2]}" == "highlight" ]]; then
                COMPREPLY=( $(compgen -W "author editor" -- ${cur}) )
                return 0
            fi
            ;;
        --key)
            # Could add common environment variable names here
            COMPREPLY=( $(compgen -W "NOSTR_SECRET_KEY" -- ${cur}) )
            return 0
            ;;
    esac

    # File completion for certain commands
    case "${COMP_WORDS[1]}" in
        note|longform|wiki|publication|ngit-patch|ngit-issue)
            if [[ "${prev}" != "--"* ]]; then
                COMPREPLY=( $(compgen -f -- ${cur}) )
                return 0
            fi
            ;;
    esac

    # Common options for all commands
    if [[ "${prev}" == "--"* ]]; then
        COMPREPLY=( $(compgen -W "--relay -r --protocol --key --raw --limit --force" -- ${cur}) )
        return 0
    fi
}

# Register the completion function
complete -F _sybil_completion sybil

# Source the completion script if it's not already sourced
if [ -n "$BASH_VERSION" ] && ! declare -F _sybil_completion >/dev/null; then
    . "$(dirname "${BASH_SOURCE[0]}")/completion.sh"
elif [ -n "$ZSH_VERSION" ] && ! whence -w _sybil_completion >/dev/null; then
    . "$(dirname "${0}")/completion.sh"
fi 