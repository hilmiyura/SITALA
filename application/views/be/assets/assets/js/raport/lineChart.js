$(document).ready(function(){

  // Create chart instance
  var chart = am4core.create("chartdivLine", am4charts.XYChart);
  chart.plotContainer.stroke = am4core.color("#aaa");

  chart.plotContainer.strokeOpacity = 1;
  chart.paddingRight = 40;


  // Add data
  dataRaport.posisiIndeks.forEach((item, i) => {
    dataRaport.posisiIndeks[i]['value'] = parseFloat(dataRaport.posisiIndeks[i]['value']);
    if(dataRaport.posisiIndeks[i]['color']){
      dataRaport.posisiIndeks[i]['color'] = am4core.color("#ff9d00");
      dataRaport.posisiIndeks[i]['aValue'] = 10;
    }else{
      dataRaport.posisiIndeks[i]['color'] = am4core.color("#6771dc");
    }
  });
  chart.data = dataRaport.posisiIndeks;

  // Create category axis
  var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
  categoryAxis.dataFields.category = "id";
  categoryAxis.renderer.opposite = true;

  // Create value axis
  var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
  valueAxis.renderer.inversed = false;
  valueAxis.renderer.minLabelPosition = 0.01;
  valueAxis.min = 0;
  valueAxis.max = 100;
  valueAxis.renderer.minGridDistance = 35;
  // valueAxis.renderer.grid.template.disabled = true;
  // valueAxis.renderer.labels.template.disabled = true;
  // valueAxis.strictMinMax = true;

  // Create series
  var series1 = chart.series.push(new am4charts.LineSeries());
  series1.dataFields.valueY = "value";
  series1.dataFields.categoryX = "id";
  series1.dataFields.value = "aValue";
  series1.name = "Distribusi";
  // series1.bullets.push(new am4charts.CircleBullet());
  // series1.tooltipText = "Place taken by {id} in {categoryX}: {valueY}";
  // series1.legendSettings.valueText = "{valueY}";

  // Bullets
  var bullet = series1.bullets.push(new am4charts.CircleBullet());
  bullet.circle.stroke = am4core.color("#fff");
  bullet.circle.strokeWidth = 0.1;
  bullet.circle.propertyFields.fill = "color";
  bullet.circle.fill = am4core.color("#6771dc");
  series1.heatRules.push({
    target: bullet.circle,
    min: 5,
    max: 20,
    property: "radius",
  });
  // bullet.circle.text = "aa";
  // bullet.circle.textAlign = "middle";

  /* Create ranges */
  function createRange(axis, from, to, color) {
    var range = axis.axisRanges.create();
    range.value = from;
    range.endValue = to;
    range.axisFill.fill = color;
    range.axisFill.fillOpacity = 0.8;
    range.label.disabled = true;
  }

  // createRange(valueAxis, 0, 20, am4core.color("#d9d9d9"));
  // createRange(valueAxis, 20, 40, am4core.color("#f17070"));
  // createRange(valueAxis, 40, 60, am4core.color("#e6e593"));
  // createRange(valueAxis, 60, 80, am4core.color("#93cfe6"));
  // createRange(valueAxis, 80, 100, am4core.color("#aae693"));

  // Add chart cursor
  // chart.cursor = new am4charts.XYCursor();
  // chart.cursor.behavior = "zoomY";

  chart.fontSize = 8;


function createTrendLine(data,color,name) {
  var trend = chart.series.push(new am4charts.LineSeries());
  trend.dataFields.valueY = "value";
  trend.dataFields.categoryX = "id";
  trend.strokeWidth = 2
  trend.stroke = trend.fill = am4core.color(color);
  trend.data = data;
  trend.name = name;

  var bullet = trend.bullets.push(new am4charts.CircleBullet());
  // bullet.tooltipText = "{date}\n[bold font-size: 17px]value: {valueY}[/]";
  bullet.tooltipText = "";
  bullet.strokeWidth = 2;
  bullet.stroke = am4core.color("#fff")
  bullet.circle.fill = trend.stroke;

  var hoverState = bullet.states.create("hover");
  hoverState.properties.scale = 1.7;

  return trend;
};

createTrendLine([
  { "id": "16", "value": 0 },
  { "id": "16", "value": 100 }
],'#c00', 'Provinsi');

createTrendLine([
  { "id": "19", "value": 0 },
  { "id": "19", "value": 100 }
],'#60d74e', 'Nasional');

  // Add legend
  chart.legend = new am4charts.Legend();
});
