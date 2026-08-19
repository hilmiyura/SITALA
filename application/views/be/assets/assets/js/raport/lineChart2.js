$(document).ready(function(){
  am4core.ready(function() {

    // console.log(dataRaport.posisiIndeks);

    // Themes begin
    // am4core.useTheme(am4themes_animated);
    // Themes end

    // Create chart instance
    var chart = am4core.create("chartdivLine", am4charts.XYChart);
    chart.fontSize = 8;

    // Add data
    var maxY = 0;
    var idxCapaian = 0;
    var valCapaian = 0;
    // dataChart = [{"id":0,"value":0}];
    // console.log(dataRaport);
    dataChart = [];
    dataRaport.posisiIndeks.forEach((item, i) => {
      if(item['norm_dist'] > maxY){
        maxY = item['norm_dist'];
      }
      dataRaport.posisiIndeks[i]['value'] = parseFloat(dataRaport.posisiIndeks[i]['value']);
      if(dataRaport.posisiIndeks[i]['color']){
        idxCapaian = item.norm_dist;
        valCapaian = dataRaport.posisiIndeks[i]['value'];
        dataRaport.posisiIndeks[i]['color'] = am4core.color("#ff9d00");
        dataRaport.posisiIndeks[i]['aValue'] = 10;
      }else{
        dataRaport.posisiIndeks[i]['color'] = am4core.color("#6771dc");
      }
      dataChart.push(dataRaport.posisiIndeks[i]);
    });
    chart.data = dataChart;

      // Create axes
      // var yAxis = chart.yAxes.push(new am4charts.CategoryAxis());
      // yAxis.renderer.inversed = false;
      // yAxis.dataFields.category = "norm_dist";
      // yAxis.dataFields.valueY = "norm_dist";
      // yAxis.renderer.labels.template.disabled = false;
      // yAxis.min = 0;
      // yAxis.max = 100;

      var yAxis = chart.yAxes.push(new am4charts.ValueAxis());
      yAxis.renderer.minGridDistance = 20;
      // yAxis.min = -0.01;
      yAxis.max = (maxY > 0 ? maxY+0.01 : 10);


      // Create value axis
      var valueAxis = chart.xAxes.push(new am4charts.ValueAxis());
      valueAxis.renderer.minGridDistance = 60;
      // valueAxis.renderer.minGridDistance = 10;
      // valueAxis.interfaceColors.set("grid", am4core.color(0xffffff));
      // valueAxis.renderer.baseGrid.stroke = am4core.color("white");
      valueAxis.renderer.grid.template.stroke = "#ffffff";
      valueAxis.renderer.grid.template.disabled = true;
      valueAxis.min = 0;
      valueAxis.max = 100;

      // Create series
      var series1 = chart.series.push(new am4charts.LineSeries());
      series1.dataFields.valueX = "value";
      series1.dataFields.value = "aValue";
      // series1.dataFields.categoryY = "norm_dist";
      series1.dataFields.valueY = "norm_dist";
      series1.strokeWidth = 1;
      series1.strokeDasharray = 2;
      series1.name = "Distribusi";
      series1.dataFields.fontSize = 8;
      // series1.tooltipText = "{valueX.value}";
      // series1.bullets.push(new am4charts.CircleBullet());
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

      // Add chart cursor
      chart.cursor = new am4charts.XYCursor();
      chart.cursor.behavior = "zoomY";

      // Create ranges
      var range = valueAxis.axisRanges.create();
      range.value = 0;
      range.endValue = 25;
      range.axisFill.fill = am4core.color("#d9d9d9");
      range.axisFill.fillOpacity = 0.2;

      var range2 = valueAxis.axisRanges.create();
      range2.value = 25;
      range2.endValue = 50;
      range2.axisFill.fill = am4core.color("#f17070");
      range2.axisFill.fillOpacity = 0.2;

      var range3 = valueAxis.axisRanges.create();
      range3.value = 50;
      range3.endValue = 60;
      range3.axisFill.fill = am4core.color("#e6e593");
      range3.axisFill.fillOpacity = 0.2;

      var range4 = valueAxis.axisRanges.create();
      range4.value = 60;
      range4.endValue = 75;
      range4.axisFill.fill = am4core.color("#93cfe6");
      range4.axisFill.fillOpacity = 0.2;

      var range4 = valueAxis.axisRanges.create();
      range4.value = 75;
      range4.endValue = 100;
      range4.axisFill.fill = am4core.color("#aae693");
      range4.axisFill.fillOpacity = 0.2;

      var rangeOne = valueAxis.axisRanges.create();
      rangeOne.value = dataRaport.posisiIndeksAVG.daerah.start;
      rangeOne.endValue = dataRaport.posisiIndeksAVG.daerah.end;
      rangeOne.axisFill.fill = am4core.color("red");
      rangeOne.axisFill.fillOpacity = 0.9;

      var rangeTwo = valueAxis.axisRanges.create();
      rangeTwo.value = dataRaport.posisiIndeksAVG.nasional.start;
      rangeTwo.endValue = dataRaport.posisiIndeksAVG.nasional.end;
      rangeTwo.axisFill.fill = am4core.color("green");
      rangeTwo.axisFill.fillOpacity = 0.9;


      function createTrendLine(data,color,name) {
        var trend = chart.series.push(new am4charts.LineSeries());
        trend.dataFields.valueX = "value";
        trend.dataFields.valueY = "id";
        trend.strokeWidth = 0;
        trend.stroke = trend.fill = am4core.color(color);
        trend.data = data;
        trend.name = name;

        var bullet = trend.bullets.push(new am4charts.CircleBullet());
        // bullet.tooltipText = "{date}\n[bold font-size: 17px]value: {valueY}[/]";
        bullet.tooltipText = "";
        bullet.strokeWidth = 1;
        bullet.stroke = trend.stroke
        bullet.circle.fill = trend.stroke;
        trend.heatRules.push({
          target: bullet.circle,
          min: 0,
          max: 0,
          property: "radius",
        });

        var hoverState = bullet.states.create("hover");
        hoverState.properties.scale = 0;

        return trend;
      };

      createTrendLine([
        { "id": idxCapaian, "value": valCapaian },
        { "id": idxCapaian, "value": valCapaian }
      ],'#c00', 'Provinsi');

      createTrendLine([
        { "id": idxCapaian, "value": valCapaian },
        { "id": idxCapaian, "value": valCapaian }
      ],'#60d74e', 'Nasional');

      createTrendLine([
        { "id": idxCapaian, "value": valCapaian }
      ],'#ff9d00', 'Capaian');


      chart.legend = new am4charts.Legend();

    }); // end am4core.ready()
});
