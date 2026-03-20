import '../css/dashboard.css'
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.min.css'
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'
import 'flatpickr/dist/plugins/monthSelect/style.css'
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

        const title = document.getElementById('settings-stats-period-title')
        if (title && window.statsData.length > 0) {
            const firstDate = new Date(window.statsData[0].date)
            const lastDate = new Date(window.statsData[window.statsData.length - 1].date)

            if (window.statsPeriod === 'day') {
                title.textContent = firstDate.toLocaleDateString('nl-BE', { day: 'numeric', month: 'long', year: 'numeric' })
            } else if (window.statsPeriod === 'month') {
                title.textContent = firstDate.toLocaleDateString('nl-BE', { month: 'long', year: 'numeric' })
            } else {
                title.textContent = firstDate.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short' }) + ' – ' + lastDate.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short', year: 'numeric' })
            }
        }

        const labels = window.statsData.map(row => {
            const date = new Date(row.date)
            return date.getDate()
        })

        const questions = window.statsData.map(row => parseInt(row.total))
        const fallbacks = window.statsData.map(row => parseInt(row.fallbacks))

        const chartType = window.statsPeriod === 'day' ? 'bar' : 'line'

        new Chart(canvas, {
            type: chartType,
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Aantal vragen',
                        data: questions,
                        backgroundColor: chartType === 'bar' ? '#006bc2' : 'rgba(0, 107, 194, 0.1)',
                        borderColor: '#006bc2',
                        borderWidth: 2,
                        borderRadius: chartType === 'bar' ? 6 : 0,
                        borderSkipped: false,
                        maxBarThickness: 40,
                        fill: chartType === 'line',
                        tension: 0.3,
                        pointRadius: chartType === 'line' ? 4 : 0,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Fallback antwoorden',
                        data: fallbacks,
                        backgroundColor: chartType === 'bar' ? 'rgba(229, 76, 60, 0.85)' : 'rgba(229, 76, 60, 0.1)',
                        borderColor: 'rgba(229, 76, 60, 0.85)',
                        borderWidth: 2,
                        borderRadius: chartType === 'bar' ? 6 : 0,
                        borderSkipped: false,
                        maxBarThickness: 40,
                        fill: chartType === 'line',
                        tension: 0.3,
                        pointRadius: chartType === 'line' ? 4 : 0,
                        pointHoverRadius: 6,
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

        const calendarBtn = document.getElementById('settings-stats-calendar-btn')
        const datepickerInput = document.getElementById('settings-stats-datepicker')

        if (calendarBtn && datepickerInput) {
            const pickerOptions = {
                disableMobile: true,
                onChange: (selectedDates) => {
                    const selected = selectedDates[0]
                    const today = new Date()
                    today.setHours(0, 0, 0, 0)

                    let offset = 0

                    if (window.statsPeriod === 'day') {
                        const diffTime = selected - today
                        offset = Math.round(diffTime / (1000 * 60 * 60 * 24))
                    } else if (window.statsPeriod === 'week') {
                        const startOfThisWeek = new Date(today)
                        const dayOfWeek = today.getDay() || 7
                        startOfThisWeek.setDate(today.getDate() - (dayOfWeek - 1))

                        const startOfSelectedWeek = new Date(selected)
                        const selectedDayOfWeek = selected.getDay() || 7
                        startOfSelectedWeek.setDate(selected.getDate() - (selectedDayOfWeek - 1))

                        const diffTime = startOfSelectedWeek - startOfThisWeek
                        offset = Math.round(diffTime / (1000 * 60 * 60 * 24 * 7))
                    } else if (window.statsPeriod === 'month') {
                        const yearDiff = selected.getFullYear() - today.getFullYear()
                        const monthDiff = selected.getMonth() - today.getMonth()
                        offset = yearDiff * 12 + monthDiff
                    }

                    window.location.href = `?tab=statistieken&period=${window.statsPeriod}&offset=${offset}`
                }
            }

            if (window.statsPeriod === 'month') {
                pickerOptions.plugins = [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'F Y' })]
            } else if (window.statsPeriod === 'week') {
                pickerOptions.weekNumbers = true
                pickerOptions.locale = { firstDayOfWeek: 1 }
            }

            const picker = flatpickr(datepickerInput, pickerOptions)
            calendarBtn.addEventListener('click', () => picker.open())
        }
    }
})
