# JSON Plugin

Een Craft CMS plugin die je site-content synchroniseert naar een JSON-bestand en een AI-chatbot aanbiedt.

## Installatie

**1. Installeer de plugin via Composer:**
```bash
ddev composer require jelle/craft-json-plugin
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
    *Deze tab moet je als eerste invullen als je de plugin voor de eerste keer gebruikt.*

---

## Systeem prompt (veld in Tab 2: instellingen)

De systeem prompt is een van de belangrijkste instellingen van de plugin. De standaardwaarde is minimaal — het is sterk aangeraden deze uit te breiden.

Een goede prompt bevat instructies over gedrag, beperkingen, taal en toon. Bijvoorbeeld:
```
Je bent een vriendelijke en behulpzame assistent die uitsluitend antwoord geeft op basis van de verstrekte data.
GEDRAG:
- Wees vriendelijk en professioneel
- Beantwoord begroetingen en beleefdheden vriendelijk
- Geef duidelijke en beknopte antwoorden
- Gebruik Markdown voor links: [Tekst](URL). Toon nooit kale URL's
BEPERKINGEN:
- Verzin nooit informatie die niet in de data staat
- Geef nooit technische ID's, handles of interne veldnamen weer
PRIVACY:
- Deel nooit technische implementatiedetails
- Deel nooit interne structuur van de website
```

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

## Plugin verwijderen

1. Verwijder de plugin via Craft: Settings → Plugins → JSON Plugin → Uninstall
2. Verwijder via Composer:
```bash
ddev composer remove jelle/craft-json-plugin
```
3. Verwijder eventuele resterende referenties uit `/config/project/project.yaml`
4. Optioneel: verwijder `/storage/json_plugin`
5. Optioneel: API keys verwijderen of in commentaar zetten in .env (niet van toepassing als je de key rechtstreeks in het dashboard hebt geplaatst.)

---

## Requirements

- Craft CMS 5.0.0 of later
- PHP 8.0.2 of later
- AI API key
