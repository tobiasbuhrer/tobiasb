/**
 * We are overriding the create_point and fitbounds functions of the Leaflet module.
 */

(function ($) {
    Drupal.Leaflet.prototype.create_point = function (marker) {
        var self = this;
        var latLng = new L.LatLng(marker.lat, marker.lon);
        this.bounds.push(latLng);
        var lMarker;
        var tooltip = marker.label ? marker.label.replace(/<[^>]*>/g, '').trim() : '';
        var options = {
            title: tooltip
        };

        if (marker.alt !== undefined) {
            options.alt = marker.alt;
        }

        function checkImage(imageSrc, setIcon, logError) {
            var img = new Image();
            img.src = imageSrc;
            img.onload = setIcon;
            img.onerror = logError;
        }

        lMarker = new L.Marker(latLng, options);

        if (marker.icon) {
            checkImage(marker.icon.iconUrl,
                // Success loading image.
                function(){
                    marker.icon.iconSize = marker.icon.iconSize || {};
                    marker.icon.iconSize.x = marker.icon.iconSize.x || this.naturalWidth;
                    marker.icon.iconSize.y = marker.icon.iconSize.y || this.naturalHeight;
                    if (marker.icon.shadowUrl) {
                        marker.icon.shadowSize = marker.icon.shadowSize || {};
                        marker.icon.shadowSize.x = marker.icon.shadowSize.x || this.naturalWidth;
                        marker.icon.shadowSize.y = marker.icon.shadowSize.y || this.naturalHeight;
                    }
                    options.icon = self.create_icon(marker.icon);
                    lMarker.setIcon(options.icon);
                },
                // Error loading image.
                function(err){
                    console.log("Leaflet: The Icon Image doesn't exist at the requested path: " + marker.icon.iconUrl);
                });
        }

        if (marker.targetUrl) {
            lMarker.on('click', function () {
                window.location = (marker.targetUrl);
            });
        }

        return lMarker;
    };

    // Set Map position, fitting Bounds in case of more than one feature
    // @NOTE: This method used by Leaflet Markecluster module (don't remove/rename)
    Drupal.Leaflet.prototype.fitbounds = function () {
        var self = this;

        // Fit Bounds if both them and features exist, and the Map Position in not forced.
        if (!self.settings.map_position_force && self.bounds.length > 0) {
            self.lMap.fitBounds(new L.LatLngBounds(self.bounds));

            // In case of single result use the custom Map Zoom set.
            if (self.bounds.length === 1 && self.settings.zoom) {
                self.lMap.setZoom(self.settings.zoom);
            }
        }
        else if (self.settings.map_position_force) {
            if (self.settings.center) {
                self.lMap.setView(new L.LatLng(self.settings.center.lat, self.settings.center.lng), self.settings.zoom);
            }
            else {
                self.lMap.setZoom(self.settings.zoom);
            }
        }
    };

})(jQuery);