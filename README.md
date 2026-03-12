# JSON Plugin

Een Craft CMS plugin die je site-content synchroniseert naar een JSON-bestand en een AI-chatbot aanbiedt via OpenAI.

## Installatie

**1. Voeg de GitHub-URL toe aan de `composer.json` van je Craft-project:**
```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/Jellevv/craft-json-plugin"
    }
],
```

**2. Installeer de plugin:**
```bash
ddev composer require jelle/craft-json-plugin
```

> Let op: Je hebt een GitHub Personal Access Token (PAT) nodig met `read` rechten als je dit op een server of in een nieuwe omgeving draait.

**3. Activeer in Craft:**

Ga naar Settings → Plugins → JSON Plugin → Install.

---

## Configuratie

Ga naar **Settings → Plugins → JSON Plugin**. Het dashboard heeft twee tabs:

### Tab 1: Configuratie
- Selecteer welke **secties** de chatbot mag gebruiken
- Selecteer welke **velden** meegestuurd worden
- Klik op **"Synchroniseer nu alle content"** om de data te laden

### Tab 2: Instellingen
- **Instellingen** en **Opmaak** van de chatbot

---

## Gebruik in templates

Voeg de chatbot widget toe aan je Twig template:

{{ craft.craftJsonPlugin.render()|raw }}

---

## Automatische synchronisatie tussen craft en de plugin

De plugin synchroniseert automatisch wanneer:
- Een entry wordt opgeslagen in een geselecteerde sectie
- Een entry wordt verwijderd

---

## Synchronisatie met de LLM

- Voor de tab **Instellingen** is op *save* klikken genoeg
- Voor de tab **Configuratie** moet je na *save* ook op *Synchroniseer nu alle content* klikken.

---

## Requirements

- Craft CMS 5.0.0 of later
- PHP 8.0.2 of later
- OpenAI API key
