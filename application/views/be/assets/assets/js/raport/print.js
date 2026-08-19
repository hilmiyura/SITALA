$(document).ready(function(){
  $(".printPdf").click(function(){
    var keyQr = $(this).attr("keyQr");
    $("#info-print-raport").html("Sedang mencetak raport...");
    $("#info-print-raport").removeClass("text-success");
    $("#info-print-raport").addClass("text-warning");
    printDiv(keyQr);
  });
  function printDiv(keyQr = "") {
    var divContents = document.getElementById("pdfTemplate").innerHTML;
    // document.getElementById("pdfTemplateCreate").style.marginTop = "0px";
  	var element = '<html>'+
                    +'<header></header>'
  									+'<body>'
  										+divContents
  									+'</body>'
  								+'</html>';

    element = element.replace("NaN", "");

  	var opt = {
  			margin: 0.1,
  			filename: "raport.pdf",
  			enableLinks: !1,
  			pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
  			image: {
  					type: "jpeg",
  					quality: 0.40
  			},
  			html2canvas: {
  					scale: 3,
            dpi: 192,
            letterRendering: true
  			},
  			jsPDF: {
  					unit: "in",
  					format: "a4",
  					orientation: "portrait"
  			}
  	};
  	var htmlPdfData = html2pdf().from(element).set(opt).toPdf().get("pdf").then(function(pdf) {
  			var totalPages = pdf.internal.getNumberOfPages();
  			// console.log("getHeight:" + pdf.internal.pageSize.getHeight());
  			// console.log("getWidth:" + pdf.internal.pageSize.getWidth());
  			for (var i = 1; i <= totalPages; i++) {
  					pdf.setPage(i);
  					pdf.setFontSize(10);
  					pdf.setTextColor(150);
  					// pdf.text("Page " + i + " of " + totalPages, pdf.internal.pageSize.getWidth() / 2, pdf.internal.pageSize.getHeight() / 2)
  			}
        $("#info-print-raport").removeClass("text-warning").addClass("text-success");
        $("#info-print-raport").html("berhasil mencetak raport...");
        setTimeout(()=>{
            $("#info-print-raport").html("");
        },5500)


        let perBlob = pdf.output('blob');
        var formData = new FormData();
        formData.append('file', perBlob, opt.filename);
        formData.append('keyQr',keyQr);
        // savePdf(formData,keyQr);
        $.ajax({
          url: baseUrl + "raport/uploadRaportPdf/"+keyQr,
          type: 'POST',
          data: formData,
          contentType: false,
          processData: false,
          success: function(response) {
            var res = (JSON.parse(response));
            if(res.statusCode != 200){
              return swal("error","Gagal Mencetak raport","error");
            }
          }
        });
  	});
    htmlPdfData.save();
  }
  function savePdf(formData,keyQr){

  }

  const getBase64FromUrl = async (url) => {
    const data = await fetch(url);
    const blob = await data.blob();
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.readAsDataURL(blob);
      reader.onloadend = () => {
        const base64data = reader.result;
        resolve(base64data);
      }
    });
  }
  getBase64FromUrl(dataRaport.urlQr).then(response=>{
    if(response){
      $(".push-qr").attr("src",response);
    }
  })
})
