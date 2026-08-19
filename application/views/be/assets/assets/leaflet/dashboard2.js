jQuery.fn.extend({
  live: function (event, callback) {
    if (this.selector) {
      jQuery(document).on(event, this.selector, callback);
    }
  }
});
jQuery(document).ready(function () {
    /* Initial Map */
    var map = L.map('mapnew').setView([-2.4058653,117.5021489],5);

    var _attribution = '';

    /* Tile Basemap */
    var basemap = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
      attribution: _attribution
    });
    basemap.addTo(map);

    /* GeoJSON Polygon */

    var dataprovinsi = L.geoJson(null, {
      style: function (feature) {
         if (feature.properties.COLOR == 1) {
           return {
             opacity: 1,
             color: 'gray',
             weight: 1.0,
             fillOpacity: 0.8,
             fillColor: 'rgb(94 216 79)'
           }
         }else if (feature.properties.COLOR == 2) {
           return {
             opacity: 1,
             color: 'gray',
             weight: 1.0,
             fillOpacity: 0.8,
             fillColor: 'rgb(250 98 107)'
           }
         }else if (feature.properties.COLOR == 3) {
           return {
             opacity: 1,
             color: 'gray',
             weight: 1.0,
             fillOpacity: 0.8,
             fillColor: 'rgb(40 175 208)'
           }
         }else if (feature.properties.COLOR == 4) {
           return {
             opacity: 1,
             color: 'gray',
             weight: 1.0,
             fillOpacity: 0.8,
             fillColor: 'rgb(253 185 1)'
           }
         }else if (feature.properties.COLOR == 5) {
           return {
             opacity: 1,
             color: 'gray',
             weight: 1.0,
             fillOpacity: 0.8,
             fillColor: 'rgb(70 72 85)'
           }
         }else{
           return {
             opacity: 1,
             color: 'gray',
             weight: 1.0,
             fillOpacity: 0.8,
             fillColor: 'rgba(255, 0, 0, 0)'
           }
         }
      },
      onEachFeature: function (feature, layer) {
        var content = "";
        layer.on({
          mouseover: function (e) {
            var layer = e.target;
            layer.setStyle({
              weight: 1,
              color: "gray",
              opacity: 1,
              fillColor: "#00FFFF",
              fillOpacity: 0.8,
            });
            dataprovinsi.bindTooltip("Provinsi " + feature.properties.NAME_1, {sticky: true});
          },
          mouseout: function (e) {
            dataprovinsi.resetStyle(e.target);
            map.closePopup();
          },
          click: function (e) {
            dataprovinsi.bindPopup(content);
          }
        });
      }
    });
    $.getJSON(baseUrl+"dashboardData/getGeo", function (data) {
      dataprovinsi.addData(data);
      map.addLayer(dataprovinsi);
      map.fitBounds(dataprovinsi.getBounds());
    });

    /* Legenda */
    // var legend = new L.Control({position: 'bottomleft'});
    // legend.onAdd = function (map) {
    //   this._div = L.DomUtil.create('div', 'info');
    //   this.update();
    //   return this._div;
    // };
    // legend.update = function () {
    //   this._div.innerHTML = '<h5>Legenda</h5><svg width="32" height="20"><rect width="32" height="17" style="fill:rgb(254, 242, 0, 0.9);stroke-width:0.1;stroke:rgb(0,0,0)" /></svg> Kasus 1 - 5<br><svg width="32" height="20"><rect width="32" height="17" style="fill:rgb(195, 187, 34, 0.9);stroke-width:0.1;stroke:rgb(0,0,0)" /></svg> Kasus 6 - 19<br><svg width="32" height="20"><rect width="32" height="17" style="fill:rgb(244, 132, 32, 0.9);stroke-width:0.1;stroke:rgb(0,0,0)" /></svg> Kasus 20 - 50<br><svg width="32" height="20"><rect width="32" height="17" style="fill:rgb(221, 77, 87, 0.9);stroke-width:0.1;stroke:rgb(0,0,0)" /></svg> Kasus >50<br><svg width="32" height="20"><rect width="32" height="17" style="fill:rgb(37, 150, 210, 0.9);stroke-width:0.1;stroke:rgb(0,0,0)" /></svg> Tidak ada kasus<hr><small>Sumber data:<br><a href="https://kawalcorona.com" target="_blank">https://kawalcorona.com</a></small>'
    // };
    // legend.addTo(map);
});
