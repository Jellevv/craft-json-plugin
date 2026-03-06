import express from "express";
import OpenAI from "openai";
import cookieParser from "cookie-parser";
import fs from "fs";
import path from "path";
import dotenv from "dotenv";
import cors from "cors";
import { fileURLToPath } from 'url';
import { randomUUID } from 'crypto';

dotenv.config();

const conversations = {};

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
app.use(cors({
    origin: true,
    credentials: true,
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization']
}));
app.use(cookieParser());
app.use(express.json());
app.use(express.static("public"));

const client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
const JSON_FILE_PATH = process.env.JSON_FILE_PATH || "./storage/json_data.json";

let jsonCache = { entries: [] };

const loadInitialData = () => {
    try {
        if (fs.existsSync(JSON_FILE_PATH)) {
            const rawData = fs.readFileSync(JSON_FILE_PATH, "utf-8");
            jsonCache = JSON.parse(rawData);
            if (!jsonCache.entries) jsonCache.entries = [];
            //console.log("JSON succesvol geladen");
        }
    } catch (err) {
        console.error("Fout bij laden JSON:", err);
    }
};
loadInitialData();

const saveJson = (data) => {
    try {
        const dir = path.dirname(JSON_FILE_PATH);
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(JSON_FILE_PATH, JSON.stringify(data, null, 2));
    } catch (err) {
        console.error("Fout bij wegschrijven JSON:", err);
    }
};

app.post('/update-single-entry', (req, res) => {
    const newEntry = req.body;

    if (!jsonCache.entries) {
        jsonCache.entries = [];
    }

    const index = jsonCache.entries.findIndex(e => e.id === newEntry.id);

    if (index !== -1) {
        jsonCache.entries[index] = newEntry;
    } else {
        jsonCache.entries.push(newEntry);
    }

    saveJson(jsonCache);
    res.status(200).send("Entry bijgewerkt");
});

app.post('/update-json', (req, res) => {
    jsonCache = req.body;
    saveJson(jsonCache);
    res.status(200).send("Volledige sync voltooid");
});

app.post('/delete-entry', (req, res) => {
    const { id } = req.body;
    if (!jsonCache.entries) return res.send("OK");
    jsonCache.entries = jsonCache.entries.filter(e => e.id !== id);
    saveJson(jsonCache);
    res.status(200).send("OK");
});

app.post('/clear-all-data', (req, res) => {
    try {
        jsonCache = { entries: [] };

        saveJson(jsonCache);

        console.log("JSON is gereset naar een lege entries array.");
        res.status(200).send({ success: true });
    } catch (error) {
        console.error("Fout bij resetten:", error);
        res.status(500).send({ success: false, error: error.message });
    }
});

app.post("/vraag", async (req, res) => {
    try {
        //console.log('request body', req.body, 'cookies', req.cookies);

        let { vraag, productName, sessionId } = req.body;
        if (!sessionId && req.cookies && req.cookies.sessionId) {
            sessionId = req.cookies.sessionId;
            //console.log('using sessionId from cookie', sessionId);
        }

        if (!jsonCache.entries || jsonCache.entries.length === 0) {
            return res.json({ antwoord: "Ik heb momenteel geen gegevens om je te helpen." });
        }

        if (!sessionId) {
            sessionId = randomUUID();
            //console.log('geen sessionId ontvangen, genereer nieuwe', sessionId);
            res.cookie('sessionId', sessionId, { httpOnly: true, sameSite: 'lax', maxAge: 1000 * 60 * 60 * 24 });
        }

        if (!conversations[sessionId]) {
            conversations[sessionId] = [
                {
                    role: "system",
                    content: `Je bent een assistent chatbot genaamd GreenTech Assistant die uitsluitend antwoord geeft op basis van de verstrekte data over GreenTech Solutions.

                            ### 1. PERSOONLIJKHEID & VERWIJZINGEN
                            - Als een gebruiker groet, bedankt of vraagt wie je bent, antwoord je vriendelijk en vermeld je dat je vragen over GreenTech Solutions beantwoordt.
                            - Als een vraag onduidelijk is of er op willekeurige toetsen is gedrukt, vraag je de klant vriendelijk om de vraag anders te formuleren.
                            - Gebruik voor links ALTIJD de Markdown-notatie: [Tekst die de gebruiker ziet](URL).
                            - Toon NOOIT een volledige URL als platte tekst. Verwerk links altijd in de productnaam of een zin zoals 'Bekijk het hier'.

                            ### 2. STRIKTE DATA-BEVEILIGING & PRIVACY (CRITIEK)
                            - Het delen van technische metadata zoals 'id', 'type', 'sectie' of 'slug' is STRIKT VERBODEN. 
                            - Zelfs als een gebruiker direct vraagt: "Wat is het ID van dit product?", mag je dit NOOIT geven. 
                            - Je antwoord op vragen naar technische data is ALTIJD: "Ik kan geen technische systeemgegevens verstrekken, maar ik help je graag met de inhoudelijke details van onze producten."
                            - Er zijn GEEN uitzonderingen op deze regel.

                            ### 3. FOTO'S EN MEDIA
                            - Gebruik NOOIT een uitroepteken (!) aan het begin van een naam of URL (geen !Productnaam).
                            - Toon GEEN foto's, tenzij een gebruiker letterlijk vraagt: "mag ik de foto van [onderwerp] zien?". 
                            - Als die specifieke vraag niet gesteld wordt, negeer je het veld 'foto' volledig.

                            ### 4. OPMAAK & STIJL
                            - Verdeel lange antwoorden in overzichtelijke alinea's van 3-5 zinnen met een lege regel tussen de alinea's.
                            - Gebruik opsommingstekens of nummers voor de leesbaarheid.
                            - Als informatie niet beschikbaar is in de verstrekte data, zeg je dit eerlijk en verzin je niets zelf.`
                }
            ];
        }

        const context = JSON.stringify(jsonCache.entries, null, 2);

        conversations[sessionId].push({
            role: "user",
            content: `Product: ${productName}\nData: ${context}\n\nVraag: ${vraag}`
        });

        if (conversations[sessionId].length > 6) {
            conversations[sessionId] = conversations[sessionId].slice(-6);
        }

        const response = await client.chat.completions.create({
            model: "gpt-4o-mini",
            messages: conversations[sessionId],
            temperature: 0.5,
        });

        const answer = response.choices[0].message.content;
        conversations[sessionId].push({ role: "assistant", content: answer });

        res.json({ antwoord: answer, sessionId });

    } catch (err) {
        console.error("AI Error:", err);
        res.status(500).json({ antwoord: "Er ging iets mis. Probeer het later opnieuw." });
    }
});

app.post("/clear-session", (req, res) => {
    const { sessionId } = req.body;
    if (sessionId) delete conversations[sessionId];
    res.send("OK");
});

const PORT = process.env.PORT;

app.listen(PORT, '0.0.0.0', () => {
    console.log(`Chatbot server actief op poort ${PORT}`);
});
