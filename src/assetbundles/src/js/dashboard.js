import '../css/dashboard.css'
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)
document.addEventListener('DOMContentLoaded', function () {

    const toggle = document.querySelector('#settings-useFallbackMessage-field .lightswitch')
    const textarea = document.querySelector('#settings-fallbackMessage')

    if (toggle && textarea) {
        function updateState() {
            const isOn = toggle.classList.contains('on')
            textarea.disabled = !isOn
            textarea.style.opacity = isOn ? '1' : '0.4'
            textarea.required = isOn
        }
        updateState()
        toggle.addEventListener('click', updateState)
    }

    const providerSelect = document.getElementById('settings-aiProvider')

    if (providerSelect) {
        const openaiFields = document.querySelector('#settings-openaiApiKey-field')
        const openaiModelField = document.querySelector('#settings-openaiModel-field')
        const groqFields = document.querySelector('#settings-groqApiKey-field')
        const groqModelField = document.querySelector('#settings-groqModel-field')
        const claudeFields = document.querySelector('#settings-claudeApiKey-field')
        const claudeModelField = document.querySelector('#settings-claudeModel-field')
        const geminiFields = document.querySelector('#settings-geminiApiKey-field')
        const geminiModelField = document.querySelector('#settings-geminiModel-field')

        function updateProviderFields() {
            const provider = providerSelect.value
            openaiFields.style.display = provider === 'openai' ? '' : 'none'
            openaiModelField.style.display = provider === 'openai' ? '' : 'none'
            groqFields.style.display = provider === 'groq' ? '' : 'none'
            groqModelField.style.display = provider === 'groq' ? '' : 'none'
            claudeFields.style.display = provider === 'claude' ? '' : 'none'
            claudeModelField.style.display = provider === 'claude' ? '' : 'none'
            geminiFields.style.display = provider === 'gemini' ? '' : 'none'
            geminiModelField.style.display = provider === 'gemini' ? '' : 'none'
        }

        updateProviderFields()
        providerSelect.addEventListener('change', updateProviderFields)
    }

    const saveBtn = document.querySelector('button.btn.submit')
    const selectedTab = document.querySelector('.plugin-tabs')?.dataset.selectedTab || 'configuratie'

    if (selectedTab === 'configuratie' && saveBtn) {
        saveBtn.textContent = 'Save & Sync'
        const doSync = document.querySelector('#settings-doSync')
        if (doSync) doSync.value = '1'
    }

    const canvas = document.getElementById('settings-stats-chart')
    if (canvas && window.statsData && selectedTab === 'statistieken') {
        const labels = window.statsData.map(row => row.date)
        const totals = window.statsData.map(row => parseInt(row.total))
        const fallbacks = window.statsData.map(row => parseInt(row.fallbacks))

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Totaal vragen',
                        data: totals,
                        backgroundColor: 'rgba(0, 107, 194, 0.7)',
                    },
                    {
                        label: 'Fallback antwoorden',
                        data: fallbacks,
                        backgroundColor: 'rgba(231, 76, 60, 0.7)',
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        })
    }
     console.log('selectedTab:', selectedTab)
    console.log('canvas:', document.getElementById('stats-chart'))
    console.log('statsData:', window.statsData)
})
