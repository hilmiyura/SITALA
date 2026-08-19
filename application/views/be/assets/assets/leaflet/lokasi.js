$(function () {
  'use strict'

$(".openMapsLocationPointer").on("click", function(){
  setTimeout(()=>{
    openMapsLocation();
  },1000);
});

  var mapsPointer = null;
  var resetMap = 0;
  var latitude_active = "";
  var longitude_active = "";
  $(".setLokasi").on("click", function(){
    resetMap = 1;
    if(mapsPointer){
      mapsPointer.remove();
    }
    openMapsLocation();
  });
  $(".closeMapsLocationPointer").on("click", function(){
    resetMap = 0;
    if(mapsPointer){
      mapsPointer.remove();
    }
  });
  function openMapsLocation(){
    setTimeout(() => {
      var tileLayer = new L.TileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
        id: 'mapbox/streets-v11',
      });


      var myIcon = L.icon({
        iconUrl: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
        iconSize: [30, 30],
        // iconAnchor: [22, 94],
        // popupAnchor: [-3, -76],
        // shadowUrl: 'my-icon-shadow.png',
        // shadowSize: [68, 95],
        // shadowAnchor: [22, 94]
    });
      mapsPointer = new L.Map('mapsPointer', {
        'center': [-2, 117],
        'zoom': 5,
        'layers': [tileLayer]
      });

      // Create the geocoder control
      var geocoder = L.Control.geocoder({
          defaultMarkGeocode: false,  // Disable automatic marking of the geocoded location
          collapsed: false  // Keep the search box open by default
      })
      .on('markgeocode', function (e) {
          // Customize the behavior when a result is selected
          var result = e.geocode || e.relatedTarget._geocode;  // Get the geocode result
          getLocationGoogle(result.center.lat,result.center.lng);
          marker.setLatLng(result.center);
          if (!mapsPointer.hasLayer(marker)) {
              marker.addTo(mapsPointer);
              // L.map('mapsPointer').setView(result.center, 2);
          }
          // marker.bindPopup(result.name).openPopup();
      })
      .addTo(mapsPointer);


      var marker;
      function onLocationFound(e) {


        var latitudeSave = $("#latitude").val();
        var longitudeSave = $("#longitude").val();

        if(latitudeSave && longitudeSave && !resetMap){

          getLocationGoogle(latitudeSave,longitudeSave);

          marker = L.marker([latitudeSave,longitudeSave], {
            icon: myIcon,
            draggable: true
          }).addTo(mapsPointer);
        }else{
          getLocationGoogle(e.latlng.lat,e.latlng.lng);
          marker = L.marker(e.latlng, {
            icon: myIcon,
            draggable: true
          }).addTo(mapsPointer);
        }


        marker.on('dragend', function (e) {
          // var markerE = e.target;
          // var latlng = markerE.getLatLng();
          //
          // L.Control.Geocoder.Nominatim().reverse(latlng, mapsPointer.options.crs.scale(mapsPointer.getZoom()), function (results) {
          //     var address = results[0].name;
          //     console.log('Address:', address);
          // });

          getLocationGoogle(marker.getLatLng().lat,marker.getLatLng().lng);
        });
      }

      mapsPointer.on('locationfound', onLocationFound);

      function locate() {
        mapsPointer.locate({ maxZoom: 5 });
      }

      setTimeout(() => {
        locate();
      }, 1000);

      function getLocationGoogle(lat,lng){
        latitude_active = lat;
        longitude_active = lng;
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
      }
    }, 1500);
  }

});
