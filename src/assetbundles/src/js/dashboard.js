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

        const title = document.getElementById('stats-period-title')
        if (title) {
            const now = new Date()
            const year = now.getFullYear()
            const month = now.toLocaleDateString('nl-BE', { month: 'long' })

            if (window.statsPeriod === 'day') {
                title.textContent = now.toLocaleDateString('nl-BE', { day: 'numeric', month: 'long', year: 'numeric' })
            } else if (window.statsPeriod === 'month') {
                title.textContent = month + ' ' + year
            } else {
                title.textContent = 'Week van ' + new Date(window.statsData[0].date).toLocaleDateString('nl-BE', { day: 'numeric', month: 'long', year: 'numeric' })
            }
        }

        const labels = window.statsData.map(row => {
            const date = new Date(row.date)
            return date.getDate()
        })

        const totals = window.statsData.map(row => parseInt(row.total))
        const fallbacks = window.statsData.map(row => parseInt(row.fallbacks))

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Aantal vragen',
                        data: totals,
                        backgroundColor: '#006bc2',
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 40,
                    },
                    {
                        label: 'Fallback antwoorden',
                        data: fallbacks,
                        backgroundColor: 'rgba(229, 76, 60, 0.85)',
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 40,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: {
                                family: 'system-ui, -apple-system, sans-serif',
                                size: 13,
                            },
                            color: '#596673',
                        }
                    },
                    tooltip: {
                        backgroundColor: '#fff',
                        titleColor: '#1f2d3d',
                        bodyColor: '#596673',
                        borderColor: '#e3e5e8',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        callbacks: {
                            title: (items) => {
                                const date = new Date(window.statsData[items[0].dataIndex].date)
                                return date.toLocaleDateString('nl-BE', { day: 'numeric', month: 'long', year: 'numeric' })
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: '#f0f1f3',
                        },
                        border: {
                            display: false,
                        },
                        ticks: {
                            color: '#596673',
                            font: {
                                family: 'system-ui, -apple-system, sans-serif',
                                size: 12,
                            },
                            maxRotation: 0,
                        }
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false,
                            dash: [4, 4],
                        },
                        grid: {
                            color: '#e3e5e8',
                        },
                        ticks: {
                            stepSize: 1,
                            color: '#596673',
                            font: {
                                family: 'system-ui, -apple-system, sans-serif',
                                size: 12,
                            },
                            padding: 8,
                        }
                    }
                }
            }
        })
    }
})
