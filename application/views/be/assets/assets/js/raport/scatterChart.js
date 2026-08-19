$(document).ready(function(){
    /* Create chart instance */
  var chart = am4core.create("chartdivScatter", am4charts.XYChart);

  /* Add data */
  // chart.data = [{
  //   "tahun": "2021",
  //   "capaian": 100,
  //   "target": 100,
  // }, {
  //   "tahun": "2022",
  //   "capaian": 150,
  //   "target": 180,
  // }
  // ];
  chart.data = dataRaport.trenIklh;

  /* Create axes */
  var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
  categoryAxis.dataFields.category = "tahun";
  categoryAxis.renderer.minGridDistance = 30;

  /* Create value axis */
  var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
  valueAxis.min = 0;
  valueAxis.max = 100;

  /* Create series */
  // var series1 = chart.series.push(new am4charts.LineSeries());
  // series1.dataFields.valueY = "target";
  // series1.dataFields.categoryX = "tahun";
  // series1.name = "Target";
  // series1.strokeWidth = 2;
  // series1.tensionX = 0.7;
  // series1.tooltipText = "{categoryX}: [bold]{valueY}[/]";
  // series1.bullets.create(am4charts.CircleBullet);
  // series1.fill = am4core.color("#6771dc");
  // series1.stroke = am4core.color("#6771dc");
  // var bullet1 = series1.bullets.push(new am4charts.LabelBullet())
  // bullet1.label.text ="{valueY}";
  // bullet1.interactionsEnabled = false
  // bullet1.dy = 10;
  // bullet1.label.fontSize = 6.5;

  var series1 = chart.series.push(new am4charts.LineSeries());
  series1.name = "Target";
  series1.dataFields.valueY = "target";
  series1.dataFields.categoryX = "tahun";
  series1.fill = am4core.color("#6771dc");
  series1.stroke = am4core.color("#6771dc");
  series1.propertyFields.strokeDasharray = "lineDash";
  series1.tooltip.label.textAlign = "middle";
  series1.strokeWidth = 2;
  series1.tensionX = 0.7;
  series1.bullets.create(am4charts.CircleBullet);
  var bullet1 = series1.bullets.push(new am4charts.Bullet());
  bullet1.fill = am4core.color("#6771dc"); // tooltips grab fill from parent by default
  bullet1.tooltipText = "[#fff font-size: 15px]{name} in {categoryX}:\n[/][#fff font-size: 20px]{valueY}[/] [#fff]{additional}[/]"

  var bullet1text = series1.bullets.push(new am4charts.LabelBullet())
  bullet1text.label.text ="{valueY}";
  bullet1text.interactionsEnabled = false
  bullet1text.dy = 10;
  bullet1text.label.fontSize = 6.5;

  var circle = bullet1.createChild(am4core.Circle);
  circle.radius = 4;
  circle.fill = am4core.color("#6771dc");
  circle.strokeWidth = 2;

  var series2 = chart.series.push(new am4charts.LineSeries());
  series2.dataFields.valueY = "capaian";
  series2.dataFields.categoryX = "tahun";
  series2.name = "Capaian ";
  series2.setFontSize = 5;
  series2.strokeWidth = 2;
  series2.tensionX = 0.7;
  series2.tooltipText = "{categoryX}: [bold]{valueY}[/]";
  series2.bullets.create(am4charts.CircleBullet);
  series2.fill = am4core.color("#ff9d00");
  series2.stroke = am4core.color("#ff9d00");
  var bullet2 = series2.bullets.push(new am4charts.LabelBullet())
  bullet2.label.text ="{valueY}";
  bullet2.interactionsEnabled = false
  bullet2.dy = -10;
  bullet2.label.fontSize = 6.5;

  chart.fontSize = 8

  /* Add legend */
  chart.legend = new am4charts.Legend();

  /* Create a cursor */
  chart.cursor = new am4charts.XYCursor();
});
