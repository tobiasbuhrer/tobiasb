/**
 * @file
 * Visitors behaviors.
 */

(function visitorsTrack(Drupal, drupalSettings, once) {
  /**
   * Attach visitors tracking behaviors.
   */
  Drupal.behaviors.visitorsTracker = {
    attach() {
      once('visitorsTracker', 'html').forEach(function visitors() {
        window._paq = window._paq || [];

        const { _paq } = window;
        const u = drupalSettings.path.baseUrl;
        const path = `/${drupalSettings.path.currentPath}`;
        const { uid } = drupalSettings.user;
        const { route, server, module, counter, debug } =
          drupalSettings.visitors;

        if (debug) {
          const push = _paq.push.bind(_paq);
          _paq.push = function visitorsDebugPush(...args) {
            // Log each command before the tracker replaces _paq.
            // eslint-disable-next-line no-console
            console.log('Visitors tracker:', ...args);
            return push(...args);
          };
        }

        function visitorsTracker() {
          _paq.push(['setSiteId', 1]);
          _paq.push(['setTrackerUrl', `${u}visitors/_track`]);
          _paq.push(['setUserId', uid]);
          _paq.push(['setCustomVariable', 1, 'route', route, 'page']);
          _paq.push(['setCustomVariable', 2, 'path', path, 'page']);
          _paq.push(['setCustomVariable', 3, 'server', server, 'page']);
          if (counter) {
            _paq.push(['setCustomVariable', 4, 'viewed', counter, 'page']);
          }

          _paq.push(['trackPageView']);

          const d = document;
          const g = d.createElement('script');
          const s = d.getElementsByTagName('script')[0];

          g.type = 'text/javascript';
          g.defer = true;
          g.async = true;
          g.src = `${module}/js/tracker.min.js`;
          g.onload = function visitorsTrackerLoaded() {
            if (
              debug &&
              window.Matomo &&
              typeof window.Matomo.setLogger === 'function'
            ) {
              window.Matomo.setLogger(console);
            }
          };

          s.parentNode.insertBefore(g, s);
        }

        window.addEventListener('load', function tracker() {
          visitorsTracker();
        });
      });
    },
  };
})(Drupal, drupalSettings, once);
