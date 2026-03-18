# JSON Plugin

Een Craft CMS plugin die je site-content synchroniseert naar een JSON-bestand en een AI-chatbot aanbiedt via OpenAI.

## Installatie

**1. Installeer de plugin via Composer:**
```bash
composer require jelle/craft-json-plugin
```

**2. Activeer in Craft:**

Ga naar Settings → Plugins → JSON Plugin → Install.

---

## Configuratie

Ga naar **Settings → Plugins → JSON Plugin**. Het dashboard heeft twee tabs:

### Tab 1: Configuratie
- Selecteer welke **secties** en **velden** de chatbot mag gebruiken
- Klik op **"Save & Sync"** om op te slaan én de data te synchroniseren

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

- Voor de tab **Instellingen** moet je op **Save** klikken om alle instellingen door te geven aan de LLM
- Voor de tab **Configuratie** synchroniseert de knop **Save & Sync** automatisch alles van deze tab met de chatbot

---

## Requirements

- Craft CMS 5.0.0 of later
- PHP 8.0.2 of later
- AI API key
