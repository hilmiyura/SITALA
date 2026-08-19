$(document).ready(function(){

  /* Create chart instance */
  var chart = am4core.create("chartdivRadar2", am4charts.RadarChart);

  /* Create and configure series */
  if(dataRaport.aspek_gambut.dataChart == 2){
    /* Add data */
    chart.data = dataRaport.aspek_gambut.chart;
    chart.fontSize = 8

    console.log(dataRaport.aspek_gambut);

    /* Create axes */
    var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
    categoryAxis.dataFields.category = "nama";


    var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
    valueAxis.renderer.axisFills.template.fill = chart.colors.getIndex(2);
    valueAxis.renderer.axisFills.template.fillOpacity = 0.05;
    // valueAxis.min = 0;
    // valueAxis.max = 100;
    valueAxis.renderer.minGridDistance = 10;

    function createSeries(name, value, color, dashed) {
      var series1 = chart.series.push(new am4charts.RadarSeries());
      series1.dataFields.valueY = value;
      series1.dataFields.categoryX = "nama";
      series1.name = "Capaian "+name;
      // series1.bullets.create(am4charts.CircleBullet);
      series1.strokeWidth = 2;
      series1.fontSize = 7
      // series1.smoothing = "monotoneX";
      if(dashed){
        series1.strokeDasharray = "5 3";
      }
      series1.fill = am4core.color(color);
      series1.stroke = am4core.color(color);

    // var bullet = series1.bullets.push(new am4charts.LabelBullet())
    // bullet.label.text ="{valueY}";
    }

    dataRaport.aspek_gambut.group.forEach(element => {
      createSeries(element.forData, element.idx_value, element.color, element.dashed)
    });
  }



  /* Add legend */
  chart.legend = new am4charts.Legend();


});
