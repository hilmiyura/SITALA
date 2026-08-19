$(document).ready(function(){
  //number format

  numberFormat();
  function numberFormat(){
    // $(".form_with_loads").submit(function(event){
    //   event.preventDefault();
    // });
    $("form").submit(function(event){
      // if($(".numberFormat").val()){
      //   var val = $(".numberFormat").val($(".numberFormat").val().replace(/\,+/g, ''));
      // }
      $("input[class^='numberFormat'], input[class*=' numberFormat']").each(function() {
        if($(this).val() && $(this).attr('id')){
          var idFormatNumber = $(this).attr('id');
          $("#"+idFormatNumber).val($("#"+idFormatNumber).val().replace(/\,+/g, ''));
        }
      });
    });

    $(".numberFormat").keyup(function(e){
      $(this).attr("type","currency");
      // $(this).attr("step","any");
      $(this).val(format($(this).val()));
    });
    var format = function(num){
      let checkMin = '';
      if(num.includes('-')){
        checkMin = '-';
      }
      num = num.replace(/[^\d.]/g, "");
      var str = num.toString().replace("", ""), parts = false, output = [], i = 1, formatted = null;
      if(str.indexOf(".") > 0) {
        parts = str.split(".");
        str = parts[0];
      }
      str = str.split("").reverse();
      for(var j = 0, len = str.length; j < len; j++) {
        if(str[j] != ",") {
          output.push(str[j]);
          if(i%3 == 0 && j < (len - 1)) {
            output.push(",");
          }
          i++;
        }
      }
      formatted = output.reverse().join("");

      let dataReturn = ("" + formatted + ((parts) ? "." + parts[1].substr(0, 20) : ""));
      return dataReturn = (checkMin ? checkMin+""+dataReturn : dataReturn);
    };
    setTimeout(()=>{
        $(".numberFormat").trigger("keyup");
    },100);
    $('#tambahData').on('show.bs.modal', function(event) {
      setTimeout(()=>{
          $(".numberFormat").trigger("keyup");
      },500);
    });
  }
  //end
});
