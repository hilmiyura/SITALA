jQuery.fn.extend({
  live: function (event, callback) {
    if (this.selector) {
      jQuery(document).on(event, this.selector, callback);
    }
  }
});
jQuery(document).ready(function () {
  //LEAFLET INIT
  var mymap;
  var tahun_aktif = $(".tahun_aktif").val();
  var latitude = -2;
  var longitude = 117;
  var zoomLevel = 5;
  var objMap = '';
  var tmpLatProv = 0;
  var tmpLngProv = 0;
  var tmpProv = '';
  var tmpLatKab = 0;
  var tmpLngKab = 0;
  var tmpKab = '';

  initMap = function () {
    var container = L.DomUtil.get('map-dashboard');
    if (container != null) {
      container._leaflet_id = null;
    }
  }
  resetColorInfoWindow = function () {
    $('.infowindow .col-left').removeClass('bg-info-green');
    $('.infowindow .col-left').removeClass('bg-info-blue');
    $('.infowindow .col-left').removeClass('bg-info-yellow');
    $('.infowindow .col-left').removeClass('bg-info-brown');
    $('.infowindow .col-left').removeClass('bg-info-gray');
  }
  showMap = function () {
    initMap();
    if (latitude && longitude && zoomLevel) {
      var centerMap = [latitude, longitude];
      mymap = L.map('map-dashboard').setView(centerMap, zoomLevel);

      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
			    maxZoom : 18,
				minZoom : 4,
			}).addTo(mymap);
      // L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
      //   attribution:'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
      //   maxZoom : 18,
			// 	minZoom : 4,
      // }).addTo(mymap);

      // var blueIcon = L.icon({
      //   iconUrl: 'assets/images/maps.png',
      //   iconSize: [30, 30],
      // });

      var greenIcon = L.icon({
				iconUrl: 'https://labs.google.com/ridefinder/images/mm_20_green.png',
				iconSize: [15, 20]
			});
			var redIcon = L.icon({
				iconUrl: 'https://labs.google.com/ridefinder/images/mm_20_red.png',
				iconSize: [15, 20]
			});
			var orangeIcon = L.icon({
				iconUrl: 'https://labs.google.com/ridefinder/images/mm_20_orange.png',
				iconSize: [15, 20]
			});
			var blueIcon = L.icon({
				iconUrl: 'https://labs.google.com/ridefinder/images/mm_20_blue.png',
				iconSize: [15, 20]
			});

      if (objMap) {
        var data = JSON.parse(JSON.stringify(objMap));
        jQuery.each(data, function (key, val) {

          var content = '';
          var title = '';
          var lat = 0;
          var lng = 0;
          if (!val['uid_lokasi_pemantauan']) {
            lat = val.latitude;
            lng = val.longitude;
            if(!val['uid_kabkota']){
              val['color'] = 'green';
            }else if (val['uid_kabkota']) {
              val['color'] = 'yellow';
            }

            var iconColor = L.divIcon({
              iconSize: null,
              html: '<div class="map-div-icon map-div-icon-' + val.color + '"><div class="map-div-icon-content">' + val.total + '</div></div>'
            });

            resetColorInfoWindow();

            if (!val['uid_kabkota']) {
              title = val.nama + ' (' + val.total + ' Titik)';
              $('.nama-kabkota').hide();
              $('#table-content-wilayah .infowindow .col-right-val-2').html(val.nama);
              var htmlval3 = '';
                  htmlval3 += '<span>'+val.total + ' Titik</span>';
              $('#table-content-wilayah .infowindow .col-right-val-3').html(htmlval3);

              var htmlval4 = '<button class="btn btn-sm btn-success lihat-sebaran-kabkota" data-lat="' + lat + '" data-lng="' + lng + '" data-prov="' + val.uid_provinsi + '">TAMPILKAN SEBARAN PER KAB/KOTA</button>';
              $('#table-content-wilayah .infowindow .col-right-val-4').html(htmlval4);

              content = $('#table-content-wilayah').html();

            } else if (val['uid_kabkota']) {
              title = val.nama + ' (' + val.total + ' Titik)';

              $('.nama-kabkota').show();
              $('#table-content-wilayah .infowindow .col-right-val-1').html(val.nama);
              $('#table-content-wilayah .infowindow .col-right-val-2').html(val.nama_provinsi);
              var htmlval3 = '';
                  htmlval3 += '<span>'+val.total + ' Titik</span>';
              $('#table-content-wilayah .infowindow .col-right-val-3').html(htmlval3);

              var htmlval4 = '<button class="btn btn-sm btn-success lihat-sebaran-titik" data-lat="' + lat + '" data-lng="' + lng + '" data-kabkota="' + val.uid_kabkota + '">TAMPILKAN TITIK SEBARAN</button>';
              $('#table-content-wilayah .infowindow .col-right-val-4').html(htmlval4);

              content = $('#table-content-wilayah').html();

            }
            title = '<div class="map-title map-div-icon-' + val.color + '"><b>' + title + '</b></div>';
            if (lat && lng) {
              //alert(lat);
              var latLng = [lat, lng];
              if (!val['uid_kabkota']) {
                L.marker(latLng, { icon: iconColor }).bindTooltip(title).bindPopup(content).addTo(mymap);
              } else if (val['uid_kabkota']) {
                L.marker(latLng, { icon: iconColor }).bindTooltip(title).bindPopup(content).addTo(mymap);
              }
            }
          } else {

            if (val.uid_rf_pelaksana == 1) {
              iconResult = redIcon;
            } else if (val.uid_rf_pelaksana == 2) {
              iconResult = greenIcon;
            } else if (val.uid_rf_pelaksana == 3) {
              iconResult = blueIcon;
            } else if (val.uid_rf_pelaksana == 4) {
              iconResult = orangeIcon;
            }

              title = val.kode_lokasi+" | "+val.name_pelaksana+" | "+val.nama;


              $('#table-content-titik .infowindow .col-right-val-1').html(val.kode_lokasi);
              $('#table-content-titik .infowindow .col-right-val-2').html(val.nama);
              $('#table-content-titik .infowindow .col-right-val-3').html(val.alamat_detail);
              $('#table-content-titik .infowindow .col-right-val-4').html(val.jenis);
              $('#table-content-titik .infowindow .col-right-val-5').html(val.name_pelaksana);
              content = $('#table-content-titik').html();

              lat = val.latitude;
              lng = val.longitude;
              var latLng = [lat, lng];
              if (lat && lng) {
                L.marker(latLng, { icon: iconResult }).bindTooltip(title).bindPopup(content).addTo(mymap);
              }
          }
        });
      }

    }
  }

  resetVar = function () {
    latitude = -2;
    longitude = 117;
    zoomLevel = 5;
    objMap = '';
    tmpLatProv = 0;
    tmpLngProv = 0;
    tmpProv = '';
    tmpLatKab = 0;
    tmpLngKab = 0;
    tmpKab = ''
  }


  loadFirst = function (kategori, subkategori) {
    jQuery.get(baseUrl+ctrl+"/lokasiByProvinsi/y/"+tahun_aktif, function (data) {
      var data = JSON.parse(data)
      if (data.total) {
        resetVar();
        objMap = data.data;
        showMap();

        $('#reset-map-kabkota').hide();
        $('#reset-map-titik').hide();
        $('#reset-map').hide();
        $("#legenda-titik").hide();

        $('#tbl-titik').show();
        $('#tbl-cerobong').hide();
        $('.loading-ajax').hide();
        //console.log(objMap);
      }
    });
  }

  showOnProv = function (prov, lat, lng) {

    $('.loading-ajax').show();
    jQuery.get(baseUrl+ctrl+"/lokasiByKabkota/x/"+prov+"/y/"+tahun_aktif, function (data) {
      var data = JSON.parse(data);
      tmpProv = prov;
      latitude = tmpLatProv = lat;
      longitude = tmpLngProv = lng;
      zoomLevel = 8;
      objMap = data.data;
      showMap();

      $('#reset-map-kabkota').show();
      $('#reset-map-titik').hide();
      $('#reset-map').hide();
      $("#legenda-titik").hide();

      $('#tbl-titik').show();
    });
  }

  jQuery('.lihat-sebaran-kabkota').live('click', function () {
    var prov = $(this).attr('data-prov');
    var latProv = $(this).attr('data-lat');
    var lngProv = $(this).attr('data-lng');
    if (prov && latProv && lngProv) {
      showOnProv(prov, latProv, lngProv);
    }
  });

  showOnKabKota = function (kabkota, lat, lng) {
    $('.loading-ajax').show();
    jQuery.get(baseUrl+ctrl+"/sebaranTitik/x/"+kabkota+"/y/"+tahun_aktif, function (data) {
      var data = JSON.parse(data);
      tmpKab = kabkota;
      latitude = tmpLatKab = lat;
      longitude = tmpLngKab = lng;
      zoomLevel = 10;
      objMap = data.data;
      showMap();

      $('#reset-map-kabkota').hide();
      $('#reset-map-titik').show();
      $('#reset-map').show();
      $("#legenda-titik").show();
      $('#tbl-titik').hide();
    });
  }
  jQuery('.lihat-sebaran-titik').live('click', function () {
    var kabkota = $(this).attr('data-kabkota');
    var latKab = $(this).attr('data-lat');
    var lngKab = $(this).attr('data-lng');
    if (kabkota && latKab && lngKab) {
      showOnKabKota(kabkota, latKab, lngKab);
    }
  });

  $('#reset-map-kabkota, #reset-map').click(function () {
    loadFirst('', '');
    $('#kategori').val('');
    $('#kategori').trigger('change');
  });

  $('#reset-map-titik').click(function () {
    showOnProv(tmpProv, tmpLatProv, tmpLngProv);
  });

  // jQuery('.lihat-titik').live('click', function () {
  //   var prov = $(this).attr('data-prov');
  //   var kabkota = $(this).attr('data-kabkota');
  //
  //   jQuery.get(baseUrl+ctrl+"/sebaranTitik/t/1/x/"+kabkota+"/y/"+prov, function (data) {
  //     var data = JSON.parse(data);
  //     if (data.total) {
  //       $("#push-content-xl").html(data.html);
  //       $('#modal-form-xl').modal('show');
  //     }else{
  //       swal("","Tidak dapat menampilkan data, karena data kosong","warning");
  //     }
  //   });
  // });
  // jQuery('.lihat-titik').live('click', function () {
  //   var titik = $(this).attr('data-titik');
  //   var prov = $(this).attr('data-prov');
  //   var kabkota = $(this).attr('data-kabkota');
  //
  //   jQuery.get(baseUrl+ctrl+"/daftarTitik/x/"+titik+"/p/"+prov+"/k/"+kabkota, function (data) {
  //     var data = JSON.parse(data);
  //     if (data.total) {
  //       $("#push-content-xl").html(data.html);
  //       $('#modal-form-xl').modal('show');
  //     }else{
  //       swal("","Tidak dapat menampilkan data, karena data kosong","warning");
  //     }
  //   });
  // });

  loadFirst('', '');
});
