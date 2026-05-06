import '../css/chatbot.css'

document.addEventListener('DOMContentLoaded', () => {

    // ── Elementen ──────────────────────────────────────────────
    const chatWrapper = document.querySelector('.chat-wrapper')
    if (!chatWrapper) return

    const chatToggle = document.querySelector('.chat-header')
    const chatIcon = document.querySelector('.chat-icon')
    const chatHeaderText = document.querySelector('.chat-header-text')
    const chatContainer = document.querySelector('.chat-container')
    const vraagInput = document.querySelector('.vraag')
    const verstuurButton = document.querySelector('.verstuur')
    const resetButton = document.querySelector('.chat-reset')

    // ── Data attributen ────────────────────────────────────────
    const primaryColor = chatWrapper.dataset.color
    const chatWidth = chatWrapper.dataset.width
    const chatHeight = chatWrapper.dataset.height
    const chatbotName = chatWrapper.dataset.chatbotName
    const welcomeMessage = chatWrapper.dataset.welcomeMessage
    const maxVraagLength = parseInt(chatWrapper.dataset.maxVraagLength)
    const csrfToken = chatWrapper.dataset.csrf

    // ── Stel desktop afmetingen in als CSS-variabelen ──────────
    // Media queries in de CSS regelen tablet- en mobielewaarden automatisch.
    chatWrapper.style.setProperty('--chat-width', chatWidth + 'px')
    chatWrapper.style.setProperty('--chat-height', chatHeight + 'px')

    // ── Kleur hulpfuncties ─────────────────────────────────────
    const lightenColor = (hex, amount = 0.8) => {
        const r = parseInt(hex.slice(1, 3), 16)
        const g = parseInt(hex.slice(3, 5), 16)
        const b = parseInt(hex.slice(5, 7), 16)
        return `rgb(${Math.round(r + (255 - r) * amount)}, ${Math.round(g + (255 - g) * amount)}, ${Math.round(b + (255 - b) * amount)})`
    }

    const darkenColor = (hex, amount = 0.6) => {
        const r = parseInt(hex.slice(1, 3), 16)
        const g = parseInt(hex.slice(3, 5), 16)
        const b = parseInt(hex.slice(5, 7), 16)
        return `rgb(${Math.round(r * (1 - amount))}, ${Math.round(g * (1 - amount))}, ${Math.round(b * (1 - amount))})`
    }

    const lightColor = lightenColor(primaryColor)
    const darkColor = darkenColor(primaryColor)

    const MAX_HISTORY = 20

    const sanitize = (html) => {
        const allowed = { a: ['href', 'target'], strong: [], br: [] }
        const div = document.createElement('div')
        div.innerHTML = html
        div.querySelectorAll('*').forEach(el => {
            const tag = el.tagName.toLowerCase()
            if (!allowed[tag]) {
                el.replaceWith(document.createTextNode(el.textContent))
                return
            }
            Array.from(el.attributes).forEach(attr => {
                if (!allowed[tag].includes(attr.name)) el.removeAttribute(attr.name)
            })
            // Block javascript: URLs on links
            if (el.href && el.href.startsWith('javascript:')) el.removeAttribute('href')
        })
        return div.innerHTML
    }

    let sessionId = localStorage.getItem('chatSessionId') || crypto.randomUUID()
    localStorage.setItem('chatSessionId', sessionId)

    // ── Geschiedenis herladen ──────────────────────────────────
    const savedHistory = JSON.parse(localStorage.getItem('chatHistory_' + sessionId) || '[]')
    savedHistory.forEach((msg) => {
        const div = document.createElement('div')
        div.className = 'chat-entry ' + msg.role
        if (msg.role === 'user') {
            div.style.background = lightColor
            div.style.color = darkColor
            div.textContent = msg.content
        } else {
            // Fix #1: sanitize opgeslagen HTML bij herladen (beschermt tegen opgeslagen XSS)
            div.innerHTML = sanitize(msg.content)
        }
        chatContainer.appendChild(div)
    })
    if (savedHistory.length > 0) {
        chatContainer.scrollTop = chatContainer.scrollHeight
    }

    // ── Toggle open/dicht ──────────────────────────────────────
    chatToggle.addEventListener('click', () => {
        const isCollapsed = chatWrapper.classList.toggle('collapsed')
        chatIcon.textContent = isCollapsed ? '+' : '-'
        chatHeaderText.textContent = isCollapsed ? 'Heeft u een vraag?' : chatbotName
        resetButton.style.display = isCollapsed ? 'none' : 'inline'
    })

    // ── Reset gesprek ──────────────────────────────────────────
    resetButton.addEventListener('click', (e) => {
        e.stopPropagation()

        const existing = document.querySelector('.chat-confirm')
        if (existing) { existing.remove(); return }

        const confirmDiv = document.createElement('div')
        confirmDiv.className = 'chat-confirm'
        confirmDiv.innerHTML = `<span>Gesprek resetten?</span><div class="chat-confirm-buttons"><button class="chat-confirm-cancelled">Annuleer</button><button class="chat-confirm-confirmed" style="background: ${primaryColor}">Reset</button></div>`
        chatWrapper.appendChild(confirmDiv)

        confirmDiv.querySelector('.chat-confirm-cancelled').addEventListener('click', (e) => {
            e.stopPropagation()
            confirmDiv.remove()
        })

        confirmDiv.querySelector('.chat-confirm-confirmed').addEventListener('click', (e) => {
            e.stopPropagation()
            localStorage.removeItem('chatHistory_' + sessionId)
            localStorage.removeItem('chatSessionId')
            // Fix #2: gebruik crypto.randomUUID() ook bij reset
            sessionId = crypto.randomUUID()
            localStorage.setItem('chatSessionId', sessionId)
            chatContainer.innerHTML = '<div class="chat-entry assistant">' + welcomeMessage + '</div>'
            confirmDiv.remove()
        })
    })

    // ── Vraag versturen ────────────────────────────────────────
    const stuurVraag = async () => {
        const vraagTekst = vraagInput.value.trim()
        if (!vraagTekst) return

        if (maxVraagLength && vraagTekst.length > maxVraagLength) {
            const errorDiv = document.createElement('div')
            errorDiv.className = 'chat-entry assistant'
            errorDiv.textContent = 'Je vraag is te lang. Maximum is ' + maxVraagLength + ' tekens.'
            chatContainer.appendChild(errorDiv)
            chatContainer.scrollTop = chatContainer.scrollHeight
            return
        }

        const userDiv = document.createElement('div')
        userDiv.className = 'chat-entry user'
        userDiv.style.background = lightColor
        userDiv.style.color = darkColor
        userDiv.textContent = vraagTekst
        chatContainer.appendChild(userDiv)

        const history = JSON.parse(localStorage.getItem('chatHistory_' + sessionId) || '[]')
        history.push({ role: 'user', content: vraagTekst })

        if (history.length > MAX_HISTORY) history.splice(0, history.length - MAX_HISTORY)
        localStorage.setItem('chatHistory_' + sessionId, JSON.stringify(history))

        const assistantDiv = document.createElement('div')
        assistantDiv.className = 'chat-entry assistant'
        assistantDiv.textContent = 'Aan het typen...'
        chatContainer.appendChild(assistantDiv)

        vraagInput.value = ''
        chatContainer.scrollTop = chatContainer.scrollHeight

        try {
            const response = await fetch('/actions/json-plugin/chat/vraag', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    vraag: vraagTekst,
                    sessionId: sessionId,
                    pageUrl: window.location.href
                })
            })

            if (!response.ok) throw new Error('Server error')

            const data = await response.json()

            const parsedAntwoord = (data.antwoord || 'Geen antwoord ontvangen')
                .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, (_, text, url) => `<a href="${url}" target="_blank">${text}</a>`)
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>')

            const safeAntwoord = sanitize(parsedAntwoord)
            assistantDiv.innerHTML = safeAntwoord

            history.push({ role: 'assistant', content: safeAntwoord })

            if (history.length > MAX_HISTORY) history.splice(0, history.length - MAX_HISTORY)
            localStorage.setItem('chatHistory_' + sessionId, JSON.stringify(history))

        } catch (err) {
            console.error('Fout:', err)
            assistantDiv.textContent = 'Excuses, er ging iets mis bij het ophalen van het antwoord.'
        } finally {
            chatContainer.scrollTop = chatContainer.scrollHeight
        }
    }

    verstuurButton.addEventListener('click', stuurVraag)
    vraagInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') stuurVraag()
    })

})
