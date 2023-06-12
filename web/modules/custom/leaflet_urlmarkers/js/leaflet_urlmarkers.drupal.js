/**
 * We are overriding the create_point function of the Leaflet module.
 * Two things are different: Line 25-27, Line 60-64
 */

(function ($) {
      /**
   * Leaflet Point creator.
   *
   * @param marker
   *   The Marker definition.
   *
   * @returns {*}
   */
  Drupal.Leaflet.prototype.create_point = function(marker) {
    let latLng = new L.LatLng(marker.lat, marker.lon);
    let lMarker;
    let marker_title = marker.title ? marker.title.replace(/<[^>]*>/g, '').trim() : '';
    let options = {
      title: marker_title,
      className: marker.className || '',
      alt: marker_title,
    };
   
    if (marker.zIndexOffset) {
         options.zIndexOffset = marker.zIndexOffset
    }

    lMarker = new L.Marker(latLng, options);

    if (marker.icon) {
      if (marker.icon.iconType && marker.icon.iconType === 'html' && marker.icon.html) {
        let icon = this.create_divicon(marker.icon);
        lMarker.setIcon(icon);
      }
      else if (marker.icon.iconType && marker.icon.iconType === 'circle_marker') {
        try {
          options = marker.icon.options ? JSON.parse(marker.icon.options) : {};
          options.radius = options.radius ? parseInt(options['radius']) : 10;
        }
        catch (e) {
          options = {};
        }
        lMarker = new L.CircleMarker(latLng, options);
      }
      else if (marker.icon.iconUrl) {
        marker.icon.iconSize = marker.icon.iconSize || {};
        marker.icon.iconSize.x = marker.icon.iconSize.x || this.naturalWidth;
        marker.icon.iconSize.y = marker.icon.iconSize.y || this.naturalHeight;
        if (marker.icon.shadowUrl) {
          marker.icon.shadowSize = marker.icon.shadowSize || {};
          marker.icon.shadowSize.x = marker.icon.shadowSize.x || this.naturalWidth;
          marker.icon.shadowSize.y = marker.icon.shadowSize.y || this.naturalHeight;
        }
        let icon = this.create_icon(marker.icon);
        lMarker.setIcon(icon);
      }
    }
          
    if (marker.targetUrl) {
        lMarker.on('click', function () {
            window.location = (marker.targetUrl);
        });
    }
        
    return lMarker;
  };
})(jQuery);