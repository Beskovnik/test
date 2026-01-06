(function() {
    // Configuration
    const REFRESH_INTERVAL = 3 * 60 * 1000; // 3 minutes
    let autoRefreshTimer = null;
    let lastJsonData = null;

    // Elements
    const els = {
        btnGeo: document.getElementById('btn-geo'),
        btnUpdate: document.getElementById('btn-update'),
        inpLat: document.getElementById('inp-lat'),
        inpLon: document.getElementById('inp-lon'),
        presetSelect: document.getElementById('location-preset'),
        chkAuto: document.getElementById('chk-autorefresh'),
        lastUpdated: document.getElementById('last-updated'),
        errorBox: document.getElementById('weather-error'),

        radarContainer: document.getElementById('radar-data'),
        hailContainer: document.getElementById('hail-data'),
        forecastList: document.getElementById('forecast-list'),
        forecastChart: document.getElementById('forecast-chart'),
        metaInfo: document.getElementById('meta-info')
    };

    // State
    let state = {
        lat: localStorage.getItem('weather_lat') || 46.0569,
        lon: localStorage.getItem('weather_lon') || 14.5058,
        loading: false
    };

    // Initialize
    function init() {
        // Restore UI state
        els.inpLat.value = state.lat;
        els.inpLon.value = state.lon;
        els.chkAuto.checked = true;

        // Bind Events
        els.btnGeo.addEventListener('click', useGeolocation);
        els.btnUpdate.addEventListener('click', () => setLocation(els.inpLat.value, els.inpLon.value));
        els.presetSelect.addEventListener('change', (e) => {
            if(!e.target.value) return;
            const [lat, lon] = e.target.value.split(',');
            setLocation(lat, lon);
        });
        els.chkAuto.addEventListener('change', toggleAutoRefresh);

        // Initial Fetch
        fetchWeather();
        toggleAutoRefresh();
    }

    function toggleAutoRefresh() {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        if (els.chkAuto.checked) {
            autoRefreshTimer = setInterval(fetchWeather, REFRESH_INTERVAL);
        }
    }

    function setLocation(lat, lon) {
        // Basic validation
        lat = parseFloat(lat);
        lon = parseFloat(lon);
        if (isNaN(lat) || isNaN(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) {
            showError("Neveljavne koordinate.");
            return;
        }

        state.lat = lat;
        state.lon = lon;
        els.inpLat.value = lat;
        els.inpLon.value = lon;

        localStorage.setItem('weather_lat', lat);
        localStorage.setItem('weather_lon', lon);

        fetchWeather();
    }

    function useGeolocation() {
        if (!navigator.geolocation) {
            showError("Brskalnik ne podpira geolokacije.");
            return;
        }
        els.btnGeo.classList.add('loading'); // Optional CSS class if defined
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                els.btnGeo.classList.remove('loading');
                setLocation(pos.coords.latitude, pos.coords.longitude);
            },
            (err) => {
                els.btnGeo.classList.remove('loading');
                let msg = "Napaka pri pridobivanju lokacije.";
                if (err.code === 1) msg = "Dostop do lokacije zavrnjen.";
                showError(msg);
            },
            { timeout: 10000, maximumAge: 60000 }
        );
    }

    async function fetchWeather() {
        if (state.loading) return;
        state.loading = true;
        hideError();

        // Show subtle loading state (opacity)
        document.getElementById('weather-content').style.opacity = '0.6';

        try {
            const res = await fetch(`/api/weather_report.php?lat=${state.lat}&lon=${state.lon}`);
            const data = await res.json();

            if (data.error) {
                throw new Error(data.error); // Handled by catch
            }

            renderData(data);

            // Update time
            const now = new Date();
            els.lastUpdated.textContent = "Zadnja osvežitev: " + now.toLocaleTimeString();

        } catch (e) {
            showError(e.message || "Napaka pri povezavi.");
        } finally {
            state.loading = false;
            document.getElementById('weather-content').style.opacity = '1';
        }
    }

    function renderData(data) {
        lastJsonData = data;

        // 1. Radar (Current)
        if (data.radar) {
            const mmph = data.radar.rain_intensity_mmph || 0;
            const prob = data.radar.rain_probability || 0;

            let intensityText = "Brez padavin";
            let color = "var(--text)";
            if (mmph > 0 && mmph <= 2) { intensityText = "Rahlo deževanje"; color = "#3498db"; }
            else if (mmph > 2 && mmph <= 10) { intensityText = "Zmerno deževanje"; color = "#2980b9"; }
            else if (mmph > 10) { intensityText = "Močno deževanje"; color = "#e74c3c"; }

            els.radarContainer.innerHTML = `
                <div class="big-stat" style="color: ${color}">${mmph.toFixed(1)} <span style="font-size: 1rem">mm/h</span></div>
                <div class="sub-stat">${intensityText}</div>
                <div style="margin-top: 1rem; font-size: 0.9rem;">
                    <div>Verjetnost dežja: <strong>${prob}%</strong></div>
                    <div>Čas meritve: ${formatTime(data.radar.time)}</div>
                </div>
            `;

            // Local Alert
            if (mmph > 10) showAlert(`Visoka intenziteta padavin: ${mmph} mm/h!`);

        } else {
            els.radarContainer.innerHTML = '<div class="sub-stat">Ni podatka</div>';
        }

        // 2. Hail
        if (data.hailprob) {
            const level = data.hailprob.hail_level || 0; // 0=none, 1=low, 2=med, 3=high
            const prob = data.hailprob.hail_probability || 0;

            let badgeClass = "badge-low";
            let levelText = "Nizka";
            if (level === 2) { badgeClass = "badge-med"; levelText = "Srednja"; }
            if (level >= 3) { badgeClass = "badge-high"; levelText = "Visoka"; }
            if (level === 0) levelText = "Ni nevarnosti";

            els.hailContainer.innerHTML = `
                <div style="margin-bottom: 0.5rem;"><span class="badge ${badgeClass}">${levelText} stopnja</span></div>
                <div class="big-stat">${prob}%</div>
                <div class="sub-stat">Verjetnost toče</div>
                <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--muted);">
                    Čas meritve: ${formatTime(data.hailprob.time)}
                </div>
            `;

            if (level >= 2) showAlert(`Nevarnost toče: ${levelText} stopnja!`, 'warning');

        } else {
            els.hailContainer.innerHTML = '<div class="sub-stat">Ni podatka</div>';
        }

        // 3. Forecast
        if (data.forecast && data.forecast.items) {
            // Render Items
            els.forecastList.innerHTML = data.forecast.items.map(item => {
                const rain = item.rain || 0;
                const clouds = item.clouds || 0;
                const isRainy = rain > 0;
                return `
                    <div class="forecast-item ${isRainy ? 'rainy' : ''}">
                        <div style="font-weight: bold; margin-bottom: 0.3rem;">${formatTime(item.time, true)}</div>
                        <div style="font-size: 1.2rem; margin-bottom: 0.2rem;">${getWeatherIcon(rain, clouds)}</div>
                        <div style="font-size: 0.8rem;">${rain > 0 ? rain + ' mm' : clouds + '%'}</div>
                    </div>
                `;
            }).join('');

            // Render Sparkline
            renderSparkline(data.forecast.items);
        } else {
            els.forecastList.innerHTML = 'Ni napovedi';
        }

        // 4. Meta
        let metaHtml = "";
        if (data.updated) {
            metaHtml += `Posodobljeno (vir): ${formatTime(data.updated)} `;
        }
        if (data.stale) {
            metaHtml += `<span style="color: var(--warning)">⚠️ Stari podatki (povezava neuspešna)</span>`;
            showError("Prikazujem stare podatke, ker povezava z ARSO ni uspela.");
        }
        if (data.copyright) {
            metaHtml += ` | © ${data.copyright}`;
        }
        els.metaInfo.innerHTML = metaHtml;
    }

    // Helper: Sparkline SVG
    function renderSparkline(items) {
        if (!items.length) return;
        const width = els.forecastChart.offsetWidth;
        const height = els.forecastChart.offsetHeight;
        const maxRain = Math.max(...items.map(i => i.rain || 0), 1); // Avoid div/0

        // Points
        const points = items.map((item, i) => {
            const x = (i / (items.length - 1)) * width;
            const y = height - ((item.rain || 0) / maxRain) * height;
            return `${x},${y}`;
        }).join(' ');

        // Area path
        const areaPath = `${points} ${width},${height} 0,${height}`;

        els.forecastChart.innerHTML = `
            <svg width="100%" height="100%" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="rainGrad" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="#4b8bff" stop-opacity="0.5"/>
                        <stop offset="100%" stop-color="#4b8bff" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <path d="M${areaPath}" fill="url(#rainGrad)" />
                <polyline points="${points}" fill="none" stroke="#4b8bff" stroke-width="2" />
            </svg>
        `;
    }

    // Helper: Icons
    function getWeatherIcon(rain, clouds) {
        if (rain > 2) return '🌧️';
        if (rain > 0) return '🌦️';
        if (clouds > 80) return '☁️';
        if (clouds > 20) return '⛅';
        return '☀️';
    }

    // Helper: Format Time
    function formatTime(ts, short = false) {
        // API sends "2023-10-27 10:00" or similar? Or unix?
        // README says "float, unix timestamp" for updated.
        // items have "time". Let's assume input can be string or number.
        if (!ts) return '-';
        // If string looks like ISO, Date parse handles it.
        // If float, might need * 1000? README says "float, unix timestamp" (usually seconds).
        // Let's try parsing.
        let d = new Date(ts);
        // Check if valid
        if (isNaN(d.getTime())) {
             // Maybe unix seconds
             d = new Date(ts * 1000);
        }
        if (isNaN(d.getTime())) return ts;

        if (short) {
            // HH:MM
            return d.toLocaleTimeString('sl-SI', { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleString('sl-SI');
    }

    // Helper: Alert
    function showAlert(msg, type='danger') {
        // We can reuse showToast from global app.js if available, otherwise fallback
        if (window.showToast) {
            window.showToast(msg, type);
        } else {
            console.warn("Alert:", msg);
        }
    }

    // Helper: Error UI
    function showError(msg) {
        els.errorBox.style.display = 'block';
        els.errorBox.textContent = msg;
    }
    function hideError() {
        els.errorBox.style.display = 'none';
    }

    // Expose Copy to clipboard global function for the button
    window.copyJson = function() {
        if (!lastJsonData) return;
        navigator.clipboard.writeText(JSON.stringify(lastJsonData, null, 2)).then(() => {
            if(window.showToast) showToast('Kopirano v odložišče!');
            else alert('Kopirano!');
        });
    };

    // Run
    init();
})();
