$(document).ready(function() {
  initMap = function() {
    var latitude = (dataRaport.mapsset ? (parseFloat(dataRaport.mapsset.latitude)) : -2 );
    var longitude = (dataRaport.mapsset ? (parseFloat(dataRaport.mapsset.longitude)) : 120 );
    var zoomLevel = 5
    if(dataRaport.maps.length > 1000){
      zoomLevel = 5;
    }else if (dataRaport.maps.length > 600) {
      zoomLevel = 5.5;
    }else {
      zoomLevel = 6;
    }
    var centerMap = [latitude, longitude];
    var mymap = L.map('mapsData', {renderer: L.svg()}).setView(centerMap, zoomLevel);
    var greenIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_green.png',
      iconSize: [5, 8]
    });
    var yellowIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_yellow.png',
      iconSize: [5, 8]
    });
    var redIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_red.png',
      iconSize: [5, 8]
    });
    var orangeIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_orange.png',
      iconSize: [5, 8]
    });
    var blueIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_blue.png',
      iconSize: [5, 8]
    });
    var blackIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_black.png',
      iconSize: [5, 8]
    });
    var brownIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_brown.png',
      iconSize: [5, 8]
    });
    var whiteIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_white.png',
      iconSize: [5, 8]
    });
    var purpleIcon = L.icon({
      iconUrl: baseUrl+'application/views/be/assets/app-assets/images/icons/maps/mm_20_purple.png',
      iconSize: [5, 8]
    });

    // L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoiYWRlb2ZpayIsImEiOiJja2F4eGVtY2IwY21xMnZvYWJ5azE0eWRnIn0.buGOeenzITnPaWe1pnQTWw', {
    //   maxZoom: 18,
    //   minZoom: 4,
    //   id: 'mapbox/streets-v11',
    //   tileSize: 512,
    //   zoomOffset: -1,
    //   zoomControl: false
    // }).addTo(mymap);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
			maxZoom: 18,
			minZoom: 4,
		}).addTo(mymap);
    mymap.zoomControl.remove();
    L.control.zoom({
      position: 'topright'
    }).addTo(mymap);

    setTimeout(()=>{
      leafletImage(mymap, downloadMap);
    },100);

    if (dataRaport.maps) {
      var data = JSON.parse(JSON.stringify(dataRaport.maps));
      jQuery.each(data, function(key, val) {
        var lapor = false;
        var color = '';
        var title = '';
        var content = '';
        var lat = 0;
        var lng = 0;
        if (val) {
          title = val.kode_lokasi + ', ' + val.alamat;
          lat = val.latitude;
          lng = val.longitude;

          if (val.uid_rf_component == 1) {
            if(val.peruntukan == 14){ //transportasi
                iconResult = redIcon;
            }else if (val.peruntukan == 15) { //industri
                iconResult = yellowIcon;
            }else if (val.peruntukan == 16) { //perkantoran
                iconResult = greenIcon;
            }else if (val.peruntukan == 17) { //pemukiman
                iconResult = blueIcon;
            }else if (val.peruntukan == 98) { //-
                iconResult = orangeIcon;
            }else{ // tidak digunakan
                iconResult = blackIcon;
            }

          } else if (val.uid_rf_component == 2) {
            iconResult = greenIcon;
          } else if (val.uid_rf_component == 3) {
            iconResult = blueIcon;
          } else if (val.uid_rf_component == 4) {
            iconResult = orangeIcon;
          }
          if (lat && lng) {
            var latLng = [lat, lng];
            L.marker(latLng, {
              icon: iconResult
            }).bindTooltip(title).bindPopup(content).addTo(mymap);
          }
        }
      });
    }
  }
  if(dataRaport.maps){
    initMap();
  }

  function downloadMap(err, canvas) {
      var imgData = canvas.toDataURL("image/svg+xml", 1.0);
      $("#mapsData").html("");
      $("#mapsData").attr("class","");
      var imgData = "<img style='width:100%;height:95%;' src='"+imgData+"'>";
      $("#mapsData").html(imgData);
  };
});
