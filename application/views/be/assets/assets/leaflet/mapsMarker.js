
$(document).ready(function(){
  var listMarker = [];

  var latkabkota = $("#latkabkota").val() ? $("#latkabkota").val() : -2;
  var longkabkota = $("#longkabkota").val() ? $("#longkabkota").val() : 117;

  let map = L.map("map").setView([latkabkota,longkabkota], 13);
  // L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  //   attribution:''
  // }).addTo(map);
  L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
    attribution:''
  }).addTo(map);

  let editableLayers = new L.FeatureGroup();


  // var polylineCoordinates = [
  //     [-1.962981133262721, 113.39546717237683],
  //     [-1.9220147517909358, 113.6027992051095],
  //     [-2.035096347187834, 113.60368232242764],
  //     [-2.136519185871942, 113.54645697399975],
  //     [-2.122105566180486, 113.44270292669538],
  //     [-2.0575747143500998, 113.41594177298249],
  //     [-1.9629875828862462, 113.39546807539593]
  // ];
  // var polyline = L.polyline(polylineCoordinates, {
  //     color: 'blue'
  // }).addTo(map);
  // editableLayers.addLayer(polyline);

  // var polygonCoordinates = [
  //       [
  //         [-1.9667614945030456, 113.6365933623165],
  //         [-1.9969756354155406, 113.65195713937284],
  //         [-1.989406790890959, 113.61909253522757]
  //       ],
  //       [
  //         [1.6901768132182846, 113.00312072038652],
  //         [-1.6879528875477559, 115.57131707668306],
  //         [-1.6036737779320824, 111.22454255819322]
  //       ]
  //     ];
  // polygonCoordinates.forEach((item, i) => {
  //   var polygon = L.polygon(item, {
  //       color: 'blue'
  //   }).addTo(map);
  //   console.log("Layer created with ID:", polygon._leaflet_id);
  //   editableLayers.addLayer(polygon);
  // });

  map.on("click", function(e) {});
  map.on("touchstart", function(e) {});

  editableLayers.on("click", function(e) {});
  editableLayers.on("touchstart", function(e) {});

  map.addLayer(editableLayers);

  let drawControl = new L.Control.Draw({
    position: "topright",
    draw: {
      polyline: true,
      polygon: {
        allowIntersection: false,
        drawError: {
          // color: "#e1e100",
          color: "red",
          message: "<strong>Oh snap!<strong> you can't draw that!"
        },
        shapeOptions: {
          // color: "#bada55",
          color: "red",
          clickable: true
        }
      },
      circle: false,
      circlemarker: false,
      // rectangle: {
      //   shapeOptions: {
      //     clickable: true
      //   }
      // },
      rectangle: false,
      marker: false
    },
    edit: {
      featureGroup: editableLayers,
      remove: true
    }
  });

  map.addControl(drawControl);

  map.on(L.Draw.Event.CREATED, function(e) {
    let layer = e.layer;
    assignUniqueID(layer);
    editableLayers.addLayer(layer);
    getLatLngs(layer, e.layerType);
    // if (type === 'polyline' || type === 'polygon' || type === 'rectangle') {
    //   let coordinates = layer.getLatLngs();
    //   console.log('Coordinates: ', coordinates);
    // }
    //
    editableLayers.addLayer(layer);

    layer.on("click", function(e) {});
    layer.on("touchstart", function(e) {});
    editableLayers.on("click", function(e) {});
    editableLayers.on("touchstart", function(e) {});
  });

  map.on(L.Draw.Event.EDITED, function(e) {
    var layers = e.layers;
    layers.eachLayer(function(layer) {
        getLatLngs(layer, e.layerType);
    });
  });
  map.on(L.Draw.Event.DELETED, function(e) {
    var layers = e.layers;
    layers.eachLayer(function(layer) {
        const { object: data, index: index } = getObjectAndIndexById(layer._leaflet_id);
        listMarker.splice(index, 1);
        cloneHtml();
    });
  });

  function getLatLngs(layer, type = '') {
    let edited = false;
    let idxList= 0;
    if(type == ''){
      const { object: data, index: index } = getObjectAndIndexById(layer._leaflet_id);
      type    = data.type;
      edited  = true;
      idxList = index;
    }
      var luas = 0;
    if (type === 'polygon' || type === 'rectangle') {
        var geojson = layer.toGeoJSON();
        luas = turf.area(geojson); //meter persegi
    } else if (type === 'polyline') {
        var geojson = layer.toGeoJSON();
        luas = turf.length(geojson, {units: 'meters'}); //meter
    }
    var latlngs = layer.getLatLngs();
    pushMarker(layer._leaflet_id, latlngs, type, luas, edited, idxList)
  }

  function assignUniqueID(layer) {
      layer._leaflet_id = L.stamp(layer);
  }

  function deleteLayerByID(layerID) {
    editableLayers.eachLayer(function(layer) {
        if (layer._leaflet_id === layerID) {
            editableLayers.removeLayer(layer);
            cloneHtml();
            // console.log("Layer with ID:", layerID, "deleted.");
        }
    });
  }

  function pushMarker(id, latlong, type, luas, edited ,idxList){
    var pushData = {
      "type":type,
      "id": id,
      "luas": luas,
      "latlong" : latlong
    }

    if(edited){
     listMarker[idxList] = pushData;
   }else{
     listMarker.push(pushData);
   }
   cloneHtml();
  }

  function getObjectAndIndexById(id) {
    let index = -1;
    const obj = listMarker.find((element, i) => {
      if (element.id === id) {
        index = i;
        return true;
      }
      return false;
    });
    return { object: obj, index: index };
  }

  function cloneHtml(){
    let htmls = "";
    listMarker.forEach((item, i) => {
      htmls +="<tr>"
              +"<td>"+item.id+"</td>"
              +"<td>"+item.type+"</td>"
              +"<td>"+item.luas+"</td>"
              +"<td>"+JSON.stringify(item.latlong)+"</td>"
            +"<tr>";
    $("#push-tr").html(htmls);
    });
  }


});
