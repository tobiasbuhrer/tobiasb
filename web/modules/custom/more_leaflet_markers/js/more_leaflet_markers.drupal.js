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
            lMarker.on('click', function () {
                window.location = (marker.targetUrl);
            });
        }

        return lMarker;
    };



    Drupal.Leaflet.prototype.setMapPosition = function (features) {
        // Fit Bounds if both them and features exist, and the Map Position in not forced.
        if (features.length > 0 && !this.settings.map_position_force && this.bounds.length > 0) {
            this.lMap.fitBounds(new L.LatLngBounds(this.bounds));

            // In case of single result use the custom Map Zoom set.
            if (features.length === 1 && this.settings.zoom) {
                this.lMap.setZoom(this.settings.zoom);
            }

        }
        else if (this.settings.map_position_force) {
            var zoomLevel = this.settings.zoom;
            if (this.settings.center) {
                //this.lMap.setZoom(zoomLevel);
                //var latLngs = new L.LatLng(this.settings.middle.lat, this.settings.middle.lon);
                //var markerBounds = new L.latLngBounds(latLngs);
                //this.lMap.map.fitBounds(markerBounds);
                this.lMap.setView(new L.LatLng(this.settings.center.lat, this.settings.center.lng), zoomLevel);
            }
            else {
                this.lMap.setZoom(zoomLevel);
            }
        }
    };

})(jQuery);