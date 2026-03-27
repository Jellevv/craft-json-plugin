import '../css/dashboard.css'
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.min.css'
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect'
import 'flatpickr/dist/plugins/monthSelect/style.css'

document.addEventListener('DOMContentLoaded', function () {
    const selectedTab = document.querySelector('.plugin-tabs')?.dataset.selectedTab || 'configuratie';
    
    const providerDropdown = document.getElementById('settings-aiProvider');
    if (providerDropdown) {
        const aiProviders = ['openai', 'groq', 'claude', 'gemini'];
        const updateVisibility = () => {
            const currentProvider = providerDropdown.value;
            aiProviders.forEach(provider => {
                const apiKeyWrapper = document.getElementById(`settings-${provider}ApiKey-field`);
                const modelWrapper = document.getElementById(`settings-${provider}Model-field`);
                if (apiKeyWrapper) apiKeyWrapper.style.display = (currentProvider === provider) ? '' : 'none';
                if (modelWrapper) modelWrapper.style.display = (currentProvider === provider) ? '' : 'none';
            });
        };
        updateVisibility();
        providerDropdown.addEventListener('change', updateVisibility);
    }

    if (selectedTab === 'configuratie') {
        const craftSubmitButton = document.querySelector('button.btn.submit');
        if (craftSubmitButton) {
            craftSubmitButton.textContent = 'Save & Sync';
            const syncHiddenInput = document.querySelector('#settings-doSync');
            if (syncHiddenInput) syncHiddenInput.value = '1';
        }

        const fallbackLightswitch = document.querySelector('#settings-useFallbackMessage-field .lightswitch');
        const fallbackTextarea = document.querySelector('#settings-fallbackMessage');
        if (fallbackLightswitch && fallbackTextarea) {
            const updateTextareaState = () => {
                const isEnabled = fallbackLightswitch.classList.contains('on');
                fallbackTextarea.disabled = !isEnabled;
                fallbackTextarea.style.opacity = isEnabled ? '1' : '0.4';
            };
            updateTextareaState();
            fallbackLightswitch.addEventListener('click', () => setTimeout(updateTextareaState, 50));
        }
    }

    if (selectedTab !== 'statistieken') return;

    const periodTitleElement = document.getElementById('settings-stats-period-title');
    if (periodTitleElement && window.statsData?.length > 0) {
        const firstEntryDate = new Date(window.statsData[0].date);
        const lastEntryDate = new Date(window.statsData[window.statsData.length - 1].date);
        
        if (window.statsPeriod === 'day') {
            periodTitleElement.textContent = firstEntryDate.toLocaleDateString('nl-BE', { day: 'numeric', month: 'long', year: 'numeric' });
        } else if (window.statsPeriod === 'month') {
            periodTitleElement.textContent = firstEntryDate.toLocaleDateString('nl-BE', { month: 'long', year: 'numeric' });
        } else {
            const startStr = firstEntryDate.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short' });
            const endStr = lastEntryDate.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short', year: 'numeric' });
            periodTitleElement.textContent = `${startStr} – ${endStr}`;
        }
    }

    const statsChartCanvas = document.getElementById('settings-stats-chart');
    if (statsChartCanvas && window.statsData) {
        const chartStyle = window.statsPeriod === 'day' ? 'bar' : 'line';
        new Chart(statsChartCanvas, {
            type: chartStyle,
            data: {
                labels: window.statsData.map(row => new Date(row.date).getDate()),
                datasets: [
                    {
                        label: 'Aantal vragen',
                        data: window.statsData.map(row => parseInt(row.total)),
                        backgroundColor: chartStyle === 'bar' ? '#006bc2' : 'rgba(0, 107, 194, 0.1)',
                        borderColor: '#006bc2',
                        fill: chartStyle === 'line',
                        tension: 0.3,
                        borderWidth: 2
                    },
                    {
                        label: 'Fallback antwoorden',
                        data: window.statsData.map(row => parseInt(row.fallbacks)),
                        backgroundColor: chartStyle === 'bar' ? 'rgba(229, 76, 60, 0.85)' : 'rgba(229, 76, 60, 0.1)',
                        borderColor: 'rgba(229, 76, 60, 0.85)',
                        fill: chartStyle === 'line',
                        tension: 0.3,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    const periodCalendarButton = document.getElementById('settings-stats-calendar-btn');
    const periodCalendarInput = document.getElementById('settings-stats-datepicker');
    if (periodCalendarButton && periodCalendarInput) {
        const pickerOptions = {
            disableMobile: true,
            locale: 'nl',
            onChange: (selectedDates) => {
                const pickedDate = selectedDates[0];
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                let dayOffset = 0;
                if (window.statsPeriod === 'day') {
                    dayOffset = Math.round((pickedDate - today) / 86400000);
                } else if (window.statsPeriod === 'month') {
                    dayOffset = (pickedDate.getFullYear() - today.getFullYear()) * 12 + (pickedDate.getMonth() - today.getMonth());
                } else if (window.statsPeriod === 'week') {
                    dayOffset = Math.round((pickedDate - today) / (86400000 * 7));
                }
                window.location.href = `?tab=statistieken&period=${window.statsPeriod}&offset=${dayOffset}`;
            }
        };
        if (window.statsPeriod === 'month') {
            pickerOptions.plugins = [new monthSelectPlugin({ shorthand: true, dateFormat: "Y-m", altFormat: "F Y" })];
        }
        const periodDatePicker = flatpickr(periodCalendarInput, pickerOptions);
        periodCalendarButton.addEventListener('click', () => periodDatePicker.open());
    }

    const hourlyChartCanvas = document.getElementById('settings-hourly-stats-chart');
    if (hourlyChartCanvas && window.hourlyStatsData) {
        const hourlyDataBuckets = new Array(24).fill(0);
        window.hourlyStatsData.forEach(row => { hourlyDataBuckets[parseInt(row.hour)] = parseInt(row.total); });
        
        new Chart(hourlyChartCanvas, {
            type: 'bar',
            data: {
                labels: Array.from({ length: 24 }, (_, i) => `${i}:00`),
                datasets: [{ label: 'Vragen per uur', data: hourlyDataBuckets, backgroundColor: '#006bc2', borderRadius: 4 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        const hourlyCalendarButton = document.getElementById('settings-hourly-stats-calendar-btn');
        const hourlyCalendarInput = document.getElementById('settings-hourly-stats-datepicker');
        if (hourlyCalendarButton && hourlyCalendarInput) {
            const hourlyDatePicker = flatpickr(hourlyCalendarInput, {
                disableMobile: true,
                dateFormat: "Y-m-d",
                onChange: (selectedDates) => {
                    const d = selectedDates[0];
                    const dateString = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                    window.location.href = `?tab=statistieken&period=${window.statsPeriod}&offset=${window.statsOffset}&hourlyDate=${dateString}`;
                }
            });
            hourlyCalendarButton.addEventListener('click', () => hourlyDatePicker.open());
        }
    }
});
