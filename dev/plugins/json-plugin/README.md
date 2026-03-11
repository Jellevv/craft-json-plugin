# JSON Plugin

**Open de `composer.json` van je Craft-project en voeg de GitHub-URL toe aan de `repositories` array:**

"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/Jellevv/craft-json-plugin"

    }
],

**Run het volgende commando in je terminal (of via DDEV):**
ddev composer require jelle/craft-json-plugin

*Let op: Je hebt een GitHub Personal Access Token (PAT) nodig met 'read' rechten als je dit op een server of in een nieuwe omgeving draait.*

**Activeren in Craft**
Ga naar de Craft Control Panel -> Settings -> Plugins.

Zoek de JSON Plugin en klik op Install

**Configuratie**
Na installatie moet de plugin geconfigureerd worden:

Ga naar Settings -> JSON Plugin.

OpenAI API Key: Voer de sleutel in (of gebruik $OPENAI_API_KEY om de waarde uit de .env te laden).

Selecties: Vink de secties en volumes aan die gesynchroniseerd moeten worden.

Sync: Klik op de knop "Synchroniseer nu alle content" om het initiële JSON-bestand aan te maken in storage/json_plugin/.

# Requirements

This plugin requires Craft CMS 5.9.0 or later, and PHP 8.2 or later.

