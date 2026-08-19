$(document).ready(function(){


  var chart = am4core.create('chartdivBar', am4charts.XYChart)
  chart.fontSize = 8
  chart.colors.step = 1;

  chart.legend = new am4charts.Legend()
  chart.legend.position = 'bottom'
  chart.legend.paddingBottom = 0
  // chart.legend.height =0;
  chart.legend.fontSize = 8;
  chart.legend.labels.template.maxWidth = 95

  var xAxis = chart.xAxes.push(new am4charts.CategoryAxis())
  xAxis.dataFields.category = 'nama'
  xAxis.renderer.cellStartLocation = 0.1
  xAxis.renderer.cellEndLocation = 1
  xAxis.renderer.grid.template.location = 0;
  xAxis.renderer.line.strokeOpacity = 1;
  xAxis.renderer.minGridDistance = 30;
  
  

  var yAxis = chart.yAxes.push(new am4charts.ValueAxis());
  yAxis.min = 0;

  function createSeries(value, name) {
      var series = chart.series.push(new am4charts.ColumnSeries())
      series.dataFields.valueY = value
      series.dataFields.categoryX = 'nama'
      series.name = name
      series.columns.template.width = am4core.percent(98);
      series.columns.template.strokeWidth = 0;
      // series.label.fontSize = 5

      var bullet = series.bullets.push(new am4charts.LabelBullet())
      bullet.interactionsEnabled = false
      bullet.dy = -5;
      bullet.label.fontSize = 6.5;
      // bullet.label.position = 'top'
      bullet.label.text = '{valueY}'
      // bullet.label.fill = am4core.color('#ffffff')
      return series;
  }
  chart.data = dataRaport.titikPantau;

  createSeries('jumlah_titik_pantau', 'Titik Pantau');
  createSeries('jumlah_data_masuk', 'Data Masuk');
  createSeries('jumlah_data_terverifikasi', 'Data Terverifikasi');

});
