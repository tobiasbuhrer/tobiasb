/**
 * We are overriding the create_point and fitbounds functions of the Leaflet module.
 */

(function ($) {

    Drupal.Leaflet.prototype.create_point = function (marker) {
        var latLng = new L.LatLng(marker.lat, marker.lon);
        this.bounds.push(latLng);
        var lMarker;

        var tooltip = marker.label ? marker.label.replace(/<[^>]*>/g, '').trim() : '';

        if (marker.icon) {
            var icon = this.create_icon(marker.icon);
            lMarker = new L.Marker(latLng, {icon: icon, title: tooltip, riseOnHover: true});
        }
        else {
            lMarker = new L.Marker(latLng, {title: tooltip, riseOnHover: true});
        }

        if (marker.targetUrl) {
            //var urlStart = marker.label.indexOf('"');
            //var urlEnd = marker.label.substr(urlStart).indexOf('"');
            //lMarker.url = marker.label.slice(urlStart, urlEnd);
            lMarker.on('click', function () {
                window.location = (marker.targetUrl);
            });
        }

        return lMarker;
    };

    Drupal.Leaflet.prototype.fitbounds = function () {
        if (this.bounds.length > 0) {
            this.lMap.fitBounds(new L.LatLngBounds(this.bounds));
        }

        // if we have provided a zoom level, then use it after fitting bounds.
        if (this.settings.zoom) {
            var zoomLevel = this.settings.zoom;
            if (this.settings.middle) {
                //this.lMap.setZoom(zoomLevel);
                //var latLngs = new L.LatLng(this.settings.middle.lat, this.settings.middle.lon);
                //var markerBounds = new L.latLngBounds(latLngs);
                //this.lMap.map.fitBounds(markerBounds);
                this.lMap.setView(new L.LatLng(this.settings.middle.lat, this.settings.middle.lon), zoomLevel);
            }
            else {
                this.lMap.setZoom(zoomLevel);
            }
        }
    };

})(jQuery);