// jQuery.fn.extend({
//   live: function(event, callback) {
//     if (this.selector) {
//       jQuery(document).on(event, this.selector, callback);
//     }
//   }
// });
// jQuery(document).ready(function() {
//   //LEAFLET INIT
//   var mymap;
//   var latitude = -2;
//   var longitude = 117;
//   var zoomLevel = 5;
//   var objMap = '';
//   var tmpLat = 0;
//   var tmpLng = 0;
//   var tmpProp = '';
//
//   initMap = function() {
//     var container = L.DomUtil.get('map');
//     if (container != null) {
//       container._leaflet_id = null;
//     }
//   }
//   resetColorInfoWindow = function() {
//     jQuery('.infowindow .col-left').removeClass('bg-info-green');
//     jQuery('.infowindow .col-left').removeClass('bg-info-blue');
//     jQuery('.infowindow .col-left').removeClass('bg-info-yellow');
//     jQuery('.infowindow .col-left').removeClass('bg-info-brown');
//     jQuery('.infowindow .col-left').removeClass('bg-info-gray');
//   }
//   showMap = function() {
//     initMap();
//     if (latitude && longitude && zoomLevel) {
//       var centerMap = [latitude, longitude];
//       mymap = L.map('map').setView(centerMap, zoomLevel);
//
//       L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoiYWRlb2ZpayIsImEiOiJja2F4eGVtY2IwY21xMnZvYWJ5azE0eWRnIn0.buGOeenzITnPaWe1pnQTWw', {
//         maxZoom: 18,
//         minZoom: 4,
//         attribution: 'Map data <a href="https://www.google.co.id/maps">Google Map</a>',
//         id: 'mapbox/streets-v11',
//         tileSize: 512,
//         zoomOffset: -1,
//         //zoomControl: false
//       }).addTo(mymap);
//       if (objMap) {
//         var data = JSON.parse(JSON.stringify(objMap));
//         jQuery.each(data, function(key, val) {
//           var content = '';
//           var title = '';
//           var lat = val.koordinat.lat;
//           var lng = val.koordinat.lng;
//           var iconColor = L.divIcon({
//             iconSize: null,
//             html: '<div class="map-div-icon map-div-icon-beban  map-div-icon-' + val.beban_terbesar.warna + '"><div class="map-div-icon-content map-div-icon-content-beban">' + val.beban_terbesar.nilai_val + '</div></div>'
//           });
//           resetColorInfoWindow();
//           jQuery('.infowindow .col-left').addClass('bg-info-' + val.beban_terbesar.warna);
//
//           var btnGo2Kabkota = '';
//           if (val.showCategories == 1) { //indonesia
//             jQuery('.nama-kabkota').hide();
//             jQuery('.nama-propinsi').show();
//             jQuery('#table-content-wilayah .infowindow .col-right-val-2').html(val.nama);
//             btnGo2Kabkota = '<button class="lihat-sebaran-kabkota" data-lat="' + lat + '" data-lng="' + lng + '" data-prop="' + val.uid + '">TAMPILKAN TITIK SEBARAN</button>';
//           } else { //per propinsi
//             jQuery('.nama-kabkota').show();
//             jQuery('.nama-propinsi').hide();
//             jQuery('#table-content-wilayah .infowindow .col-right-val-1').html(val.nama);
//           }
//           var bebanHTML = '';
//           jQuery.each(val.params, function(k, v) {
//             bebanHTML += '<span class="nama-param">' + v + '</span> : ' + val.beban_val[k] + '<br/>';
//           });
//           bebanHTML += btnGo2Kabkota;
//           jQuery('#table-content-wilayah .infowindow .col-right-val-3').html(bebanHTML);
//           //jQuery('#table-content-wilayah .infowindow .col-right-val-3').html(val.beban_terbesar.nilai_val + '<button class="lihat-sebaran-kabkota" data-lat="' + lat + '" data-lng="' + lng + '" data-prop="' + val.propinsi + '">TAMPILKAN TITIK SEBARAN</button>');
//
//           jQuery('#table-content-wilayah .infowindow .col-right-val-4').html(jQuery('#tahun').val());
//
//           content = jQuery('#table-content-wilayah').html();
//
//           title = val.nama + ' (' + val.beban_terbesar.nilai_val + ' Ton/Tahun)';
//           title = '<div class="map-title map-div-icon-' + val.beban_terbesar.warna + '"><b>' + title + '</b></div>';
//
//           if (lat && lng) {
//             //alert(lat);
//             var latLng = [lat, lng];
//             L.marker(latLng, {
//               icon: iconColor
//             }).bindTooltip(title).bindPopup(content).addTo(mymap);
//           }
//         });
//       }
//     }
//   }
//   resetVar = function() {
//     latitude = -2;
//     longitude = 117;
//     zoomLevel = 5;
//     objMap = '';
//     tmpLat = 0;
//     tmpLng = 0;
//     tmpProp = '';
//   }
//   loadFirst = function(tahun, param, kategori, subkategori) {
//     //alert(tahun);
//     jQuery('.loading-ajax').show();
//     jQuery.get("https://ditppu.menlhk.go.id/apippu/gis/sebaranBeban/tahun/" + tahun + '/param/' + param + '/kategori/' + kategori + '/subkategori/' + subkategori, function(data) {
//       //console.log(data.total);
//       if (data.total) {
//         resetVar();
//         objMap = data.rows;
//         showMap();
//
//         jQuery('#reset-map').hide();
//         jQuery('.loading-ajax').hide();
//         //console.log(objMap);
//       }
//     });
//   }
//
//   showBebanOnProp = function(tahun, prop, param, kategori, subkategori, lat, lng) {
//     jQuery('.loading-ajax').show();
//     jQuery.get("https://ditppu.menlhk.go.id/apippu/gis/sebaranBeban/tahun/" + tahun + "/prop/" + prop + '/param/' + param + '/kategori/' + kategori + '/subkategori/' + subkategori, function(data) {
//       tmpProp = prop;
//       latitude = tmpLat = lat;
//       longitude = tmpLng = lng;
//       zoomLevel = 8;
//       objMap = data.rows;
//       showMap();
//
//       jQuery('#reset-map').show();
//       jQuery('.loading-ajax').hide();
//       //console.log(objMap);
//     });
//     //alert(prop + ' ' + lat + ' ' + lng);
//   }
//
//   loadFirst(jQuery('#tahun').val(), jQuery('#parameter').val(), jQuery('#kategori').val(), jQuery('#subkategori').val());
//
//   jQuery('#reset-map-kabkota, #reset-map').click(function() {
//     var tahun = jQuery('#tahun').val();
//     var parameter = jQuery('#parameter').val();
//     var kategori = jQuery('#kategori').val();
//     var subkategori = jQuery('#subkategori').val();
//     loadFirst(tahun, parameter, kategori, subkategori);
//   });
//   jQuery('#tahun').change(function() {
//     var tahun = jQuery(this).val();
//     var parameter = jQuery('#parameter').val();
//     var kategori = jQuery('#kategori').val();
//     var subkategori = jQuery('#subkategori').val();
//     loadFirst(tahun, parameter, kategori, subkategori);
//   });
//   jQuery('#parameter').change(function() {
//     var parameter = jQuery(this).val();
//     var tahun = jQuery('#tahun').val();
//     var kategori = jQuery('#kategori').val();
//     var subkategori = jQuery('#subkategori').val();
//     loadFirst(tahun, parameter, kategori, subkategori);
//   });
//   jQuery('.lihat-sebaran-kabkota').live('click', function() {
//     var tahun = jQuery('#tahun').val();
//     var parameter = jQuery('#parameter').val();
//     var kategori = jQuery('#kategori').val();
//     var subkategori = jQuery('#subkategori').val();
//     var prop = jQuery(this).attr('data-prop');
//     var latProp = jQuery(this).attr('data-lat');
//     var lngProp = jQuery(this).attr('data-lng');
//     if (tahun && prop && latProp && lngProp) {
//       //alert(prop + ' ' + latProp + ' ' + lngProp);
//       showBebanOnProp(tahun, prop, parameter, kategori, subkategori, latProp, lngProp);
//     }
//   });
//   jQuery.get("https://ditppu.menlhk.go.id/apippu/gis/getCategory", function(data) {
//     var d = JSON.parse(JSON.stringify(data));
//     var opt = "<option value=''>--PILIH KATEGORI PERUSAHAAN--</option>";
//     jQuery.each(d.rows, function(key, va) {
//       opt += "<option value='" + va.uid + "'>" + va.code + ' - ' + va.name + "</option>";
//     });
//     jQuery("#kat").html(opt);
//   });
//   jQuery('#kat').change(function() {
//     var category = jQuery(this).val();
//     jQuery("#subkategori").html("<option value=''>--PILIH SUB KATEGORI PERUSAHAAN--</option>");
//     if (category) {
//       //set init map
//       jQuery.get('https://ditppu.menlhk.go.id/apippu/gis/getCategory/parent/' + category, function(data) {
//         var d = JSON.parse(JSON.stringify(data));
//         var opt = "<option value=''>--PILIH SUB KATEGORI PERUSAHAAN--</option>";
//         jQuery.each(d.rows, function(key, va) {
//           opt += "<option value='" + va.uid + "'>" + va.code + ' - ' + va.name + "</option>";
//         });
//         jQuery("#subkategori").html(opt);
//       });
//     }
//     var tahun = jQuery('#tahun').val();
//     var parameter = jQuery('#parameter').val();
//     var kategori = category;
//     var subkategori = jQuery('#subkategori').val();
//     loadFirst(tahun, parameter, kategori, subkategori);
//   });
//   jQuery("#subkategori").change(function() {
//     var sub = jQuery(this).val();
//     //if(sub){
//     var tahun = jQuery('#tahun').val();
//     var parameter = jQuery('#parameter').val();
//     var kategori = jQuery('#kategori').val();
//     var subkategori = sub;
//     loadFirst(tahun, parameter, kategori, subkategori);
//     //}
//   });
// });
// //