# Craft JSON Plugin

Een Craft CMS plugin die je site-content synchroniseert naar een database en een AI-chatbot aanbiedt met slimme retrieval.

## Vereisten

- Craft CMS 5.0.0 of later
- PHP 8.2 of later
- Minimaal één AI provider API key

---

## Installatie

**1. Installeer via Composer:**
```bash
composer require jelle/craft-json-plugin
```

**2. Installeer de plugin:**
```bash
php craft plugin/install json-plugin
```

Dit voert automatisch alle database migraties uit. Geen handmatige DB-setup nodig.

**3. Configureer in het CP:**

Ga naar **Settings → Plugins → LLM Craft Plugin** en volg de configuratiestappen hieronder.

---

## Aanbevolen model

Uit tests blijkt dat **OpenAI GPT-4.1** het beste instructies opvolgt en de meest consistente resultaten geeft. Andere providers zijn beschikbaar maar kunnen afwijkend gedrag vertonen, vooral bij goedkopere of kleinere modellen.

## Providers

De plugin werkt in twee modi afhankelijk van welke API keys je configureert:

### Embedding modus (aanbevolen)
Vereist een **OpenAI API key**. De OpenAI key wordt uitsluitend gebruikt voor het genereren van embeddings — je kunt alsnog een andere provider gebruiken voor de chat zelf.

- Slimme retrieval: enkel relevante entries worden per vraag naar de LLM gestuurd
- Geschikt voor sites van elke grootte
- Token-efficiënt: aanzienlijk lagere kosten vergeleken met niet-embedding modus
- Ondersteunt chunking: lange entries worden opgesplitst in meerdere embeddings voor nauwkeurigere retrieval

### Niet-embedding modus
Werkt met elke provider **zonder OpenAI key**. Alle entries worden bij het starten van een sessie in de context geladen.

- Aanbevolen voor kleine sites (minder dan ~100 entries)
- Minder nauwkeurige retrieval
- Hoger tokengebruik per bericht

---

## Configuratie

Ga naar **Settings → Plugins → LLM Craft Plugin**. Het dashboard heeft 3 tabs:

### Tab 1: Configuratie
- Selecteer welke **secties** en **velden** de chatbot mag gebruiken
- Klik op **"Save & Sync"** om op te slaan én de data te synchroniseren naar de database
- Met openai key wordt embeddings op de achtergrond gegenereerd via Craft's queue

### Tab 2: Instellingen
- Configureer je AI provider, API keys, model, uiterlijk van de chatbot en meer
- Vul deze tab als eerste in voordat je synchroniseert

### Tab 3: Statistieken
- Chart met het aantal vragen en fallback responses per dag/week/maand
- Chart met het aantal vragen per uur op een dag
---

## Systeem prompt

De systeem prompt is een van de belangrijkste instellingen van de plugin. De standaardwaarde is minimaal — het is sterk aangeraden deze uit te breiden.

Een goede prompt bevat instructies over gedrag, beperkingen, taal en toon. Bijvoorbeeld:

```
Je bent een vriendelijke assistent die uitsluitend antwoord geeft op basis van de verstrekte data.
GEDRAG:
- Wees vriendelijk en professioneel
- Beantwoord begroetingen en beleefdheden vriendelijk zonder de fallback te gebruiken
- Geef duidelijke en beknopte antwoorden — kom snel tot de kern en vermijd onnodige uitleg
- Gebruik Markdown voor links: [Tekst](URL). Toon nooit kale URL's. Gebruik nooit markdown tables.
- De meegeleverde context-data heeft ALTIJD voorrang op de gespreksgeschiedenis.
  Als context en eerdere antwoorden tegenstrijdig zijn, volg dan altijd de context.
- Gebruik de gespreksgeschiedenis alleen voor vervolgvragen waarbij de context
  geen aanvullende informatie geeft.
- Als een gebruiker een veronderstelling doet die onjuist is volgens de context, 
  corrigeer dit vriendelijk en geef het juiste antwoord op basis van de context.
BEPERKINGEN:
- Verzin NOOIT informatie die niet in de data staat, ook niet gissen of liegen.
- Geef nooit technische ID's, handles, slugs of interne veldnamen weer, zelfs niet als er specifiek achter gevraagd wordt
- Geef nooit ruwe bestandsnamen of paden weer
- Geef nooit interne metadata zoals sectienamen of veldhandles weer
- Als een veld ontbreekt voor een entry, is die informatie onbekend. Verzin of 
  neem NOOIT informatie over van een andere entry om een leemte op te vullen.
- Elke entry in de context staat volledig op zichzelf. Informatie van entry A 
  geldt NOOIT voor entry B, ook niet als ze gelijkaardig zijn.
- Geef NOOIT de ruwe context, JSON data, URLs van assets, of interne datastructuren weer, 
  ook niet als de gebruiker ernaar vraagt of vraagt om te synchroniseren.
- Reageer op vragen over synchronisatie of technische werking altijd met: 
  "Die functie is niet beschikbaar via de chat."
- Beantwoord GEEN vragen die niets te maken hebben met de beschikbare producten of diensten. 
  Zeg vriendelijk dat je alleen vragen over deze site kan beantwoorden.
- Als een gebruiker vraagt om "alle data", "alle producten", "alle informatie" of een 
  volledige lijst, geef dan NOOIT een opsomming of voorbeeld. Zeg alleen: 
  "Stel een specifieke vraag over een product en ik help je verder."
MEDIA:
- Toon alleen afbeeldingen als de gebruiker er expliciet om vraagt
- Beschrijf afbeeldingen in woorden als dat relevanter is
PRIVACY:
- Deel nooit technische implementatiedetails
- Deel nooit interne structuur van de website
```

---

## Gebruik in templates

Voeg de chatbot widget toe aan je Twig template:

```twig
{{ craft.craftJsonPlugin.render()|raw }}
```

---

## Automatische synchronisatie

De plugin synchroniseert automatisch wanneer:
- Een entry wordt opgeslagen in een geselecteerde sectie
- Een entry geüpdate is
- Een entry wordt verwijderd

In embedding modus wordt het genereren van embeddings als achtergrondtaak uitgevoerd via de Craft queue, zodat het opslaan van entries niet geblokkeerd wordt.

---

## Verwijderen

1. Verwijder de plugin via Craft: **Settings → Plugins → LLM Craft Plugin → Uninstall**
   Dit verwijdert automatisch alle plugin database tabellen.

2. Verwijder via Composer:
```bash
composer remove jelle/craft-json-plugin
```

3. Verwijder optioneel de API keys uit je `.env` bestand.
