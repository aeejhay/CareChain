/**
 * CareChain — open shifts on a Leaflet map (prototype).
 * Expects window.__CARECHAIN_MAP__ = { center: [lat,lng], zoom, markers: [...] }
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var cfg = window.__CARECHAIN_MAP__;
        var el = document.getElementById('carechainShiftMap');
        if (!el || typeof L === 'undefined') return;
        if (!cfg) return;

        var map = L.map('carechainShiftMap', { scrollWheelZoom: true }).setView(cfg.center, cfg.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        var bounds = [];
        (cfg.markers || []).forEach(function (m) {
            if (typeof m.lat !== 'number' || typeof m.lng !== 'number') return;
            bounds.push([m.lat, m.lng]);

            var wrap = document.createElement('div');
            wrap.className = 'map-popup-facility';

            var title = document.createElement('strong');
            title.textContent = m.facilityName || 'Care home';
            wrap.appendChild(title);

            var sub = document.createElement('div');
            sub.className = 'map-popup-sub';
            sub.textContent = m.city ? m.city + ', Dublin' : '';
            wrap.appendChild(sub);

            var list = document.createElement('ul');
            list.className = 'map-popup-shift-list';
            (m.shifts || []).forEach(function (s) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = s.url;
                a.textContent = s.label;
                li.appendChild(a);
                list.appendChild(li);
            });
            wrap.appendChild(list);

            L.marker([m.lat, m.lng]).addTo(map).bindPopup(wrap, { minWidth: 220, maxHeight: 280 });
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [36, 36], maxZoom: 14 });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        }

        map.on('click', function (ev) {
            L.popup()
                .setLatLng(ev.latlng)
                .setContent(
                    '<p class="map-click-hint">Tap a nursing home pin to see open shifts. ' +
                    '<a href="/carechain/shifts.php">Full shift list</a></p>'
                )
                .openOn(map);
        });
    });
})();
