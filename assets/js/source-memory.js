/* KursOrganizer: Herkunft des Besuchs je Tab merken (nur der Kanal, kein Pfad, keine Klick-Kennung) */
(function () {
    try {
        var query = new URLSearchParams(window.location.search);
        // Login-Link aus einer Mail von KursOrganizer: die Seite davor ist das Mail-Programm, nicht die Herkunft
        if (query.get('internalPath')) return;
        var referrer = document.referrer || '';
        var bare = function (host) {
            return String(host).toLowerCase().replace(/^www\./, '');
        };
        var linkParam = function (names) {
            for (var i = 0; i < names.length; i++) {
                if (query.get(names[i])) return names[i];
            }
            return '';
        };
        var utmSource = function () {
            var value = query.get('utm_source') || '';
            return /^[\w.-]{1,64}$/.test(value) ? value : '';
        };
        var androidApp = function () {
            var app = /^android-app:\/\/([\w.-]+)/.exec(referrer);
            return app ? 'android-app.' + app[1] : '';
        };
        var foreignHost = function () {
            if (!/^https?:/.test(referrer)) return '';
            var host = new URL(referrer).hostname;
            return host && bare(host) !== bare(window.location.hostname) ? host : '';
        };
        var source =
            linkParam(['gclid', 'gbraid', 'wbraid', 'msclkid']) ||
            utmSource() ||
            androidApp() ||
            foreignHost() ||
            linkParam(['fbclid', 'ttclid']);
        if (source) window.sessionStorage.setItem('koReferrer', source);
    } catch (e) {}
})();
