(function(window, undefined) {
  'use strict';
  /*
  NOTE:
  ------
  PLACE HERE YOUR OWN JAVASCRIPT CODE IF NEEDED
  WE WILL RELEASE FUTURE UPDATES SO IN ORDER TO NOT OVERWRITE YOUR JAVASCRIPT CODE PLEASE CONSIDER WRITING YOUR SCRIPT HERE.  */
  //global var
  // $("#modal_loading").modal("show");
  $(".form_with_load").submit(function(event) {
    $("#tambahData").modal("hide");
    $("#tambahDataExcel").modal("hide");
    $("#modal_loading").modal("show");
    // event.preventDefault();
  });

  $(document).ready(function() {

    $(".datepicker").datepicker({
      changeMonth: true,
      changeYear: true,
      // maxDate: add('d').toDate(),
      dateFormat: 'yy-mm-dd',
    });

    //select 2 on modal
    $('.select2').each(function() {
      $(this).select2({ dropdownParent: $(this).parent()});
    });
    // end
  });

  $('.box-content p').expander();

  // $(".readmore-btn").on('click', function() {
  //   $(this).parent().toggleClass("showContent");
  //   var replaceText = $(this).parent().hasClass("showContent") ? "Read Less" : "Read More";
  //   $(this).text(replaceText);
  // });

  $(".regional_select2").on("change", function() {
    var toId = $(this).attr("to");
    var regId = $(this).val();
    var tahun = $('.select-tahun').val();
    $.ajax({
      url: baseUrl + "ajax/provinsiByIdprov/x/" + regId + '/tahun/' + tahun,
      method: "GET",
      success: function(req) {
        var htmls = "<option value=''>--PILIH PROVINSI--</option>";
        if (req.total) {
          // console.log(req);
          jQuery.each(req.data, function(i, val) {
            if (id_provinsi == val.kd_propinsi) {
              htmls += "<option value='" + val.kd_propinsi + "' selected>" + val.nama_propinsi + "</option>";
            } else {
              htmls += "<option value='" + val.kd_propinsi + "'>" + val.nama_propinsi + "</option>";
            }
          });
        }
        $("#" + toId).html(htmls);
      }
    })
  });

  $(".select-tahun").on("change", function() {
    $(".regional_select2").trigger('change');
    $(".provinsi_select2").trigger('change');
  });

  $(".provinsi_select3").on("change", function() {
    var toId = $(this).attr("to");
    var provId = $(this).val();
    $.ajax({
      url: baseUrl + "ajax/kabkotaByIdprov/x/" + provId,
      method: "GET",
      success: function(req) {
        var htmls = "<option value=''>--PILIH KABKOTA--</option>";
        // htmls += "<option value='" + val.kd_kota + "'>" + val.nama_kabkot + "</option>";
        if (req.total) {
          // console.log(req);
          jQuery.each(req.data, function(i, val) {
            if (id_kabkota == val.kd_kota) {
              htmls += "<option value='" + val.kd_kota + "' selected>" + val.nama_kabkot + "</option>";
            } else {
              htmls += "<option value='" + val.kd_kota + "'>" + val.nama_kabkot + "</option>";
            }
          });
        }
        $("#" + toId).html(htmls);
      }
    })
  });

  $(".provinsi_select2").on("change", function() {
    var toId = $(this).attr("to");
    var provId = $(this).val();
    var tahun = $('.select-tahun').val();
    console.log(provId);
    $.ajax({
      url: baseUrl + "ajax/kabkotaByIdprov/x/" + provId + '/tahun/' + tahun,
      method: "GET",
      success: function(req) {
        var htmls = "<option value=''>--PILIH KAB/KOTA--</option>";
        if (req.total) {
          // console.log(req);
          jQuery.each(req.data, function(i, val) {
            if (id_kabkota == val.kd_kota) {
              htmls += "<option value='" + val.kd_kota + "' selected>" + val.nama_kabkot + "</option>";
            } else {
              htmls += "<option value='" + val.kd_kota + "'>" + val.nama_kabkot + "</option>";
            }
          });
        }
        $("#" + toId).html(htmls);
      }
    })
  });

  $(".provinsi_select").on("change", function() {
    var toId = $(this).attr("to");
    var provId = $(this).val();
    $.ajax({
      url: baseUrl + "ajax/kabkotaByIdprov/x/" + provId,
      method: "GET",
      success: function(req) {
        var htmls = "<option value=''>--PILIH KAB/KOTA--</option>";
        if (req.total) {
          // console.log(req);
          jQuery.each(req.data, function(i, val) {
            if (id_kabkota == val.kd_kota) {
              htmls += "<option value='" + val.kd_kota + "' selected>" + val.nama_kabkot + "</option>";
            } else {
              htmls += "<option value='" + val.kd_kota + "'>" + val.nama_kabkot + "</option>";
            }
          });
        }
        $("#" + toId).html(htmls);
      }
    })
  });

  $(".provinsi-pemantauan").on("change", function() {
    var toId = $(this).attr("to");
    var provId = $(this).val();
    $.ajax({
      url: baseUrl + "ajax/pemantauanByIdprov/x/" + provId,
      method: "GET",
      success: function(req) {
        var htmls = "<option value=''>--PILIH LOKASI PEMANTAUAN--</option>";
        if (req.total) {
          console.log(req);
          jQuery.each(req.data, function(i, val) {
            htmls += "<option value='" + val.uid_lokasi_pemantauan + "' selected>" + val.alamat + "</option>";
          });
        }
        $("#" + toId).html(htmls);
      }
    })
  });

  $("#uid_lokasi_pemantauan").on("change", function() {
    var valId = $(this).val();
    if (valId) {
      $.ajax({
        url: baseUrl + "ajax/lokasiById/x/" + valId,
        method: "GET",
        success: function(req) {
          // console.log(req.total);
          if (req.total) {
            jQuery.each(req.data, function(i, val) {
              $("#alamat").val(val.alamat);
              $("#alamat_detail").val(val.alamat_detail);
              $("#latitude").val(val.latitude);
              $("#longitude").val(val.longitude);
              $("#uid_provinsi").val(val.uid_provinsi).trigger("change");
              id_kabkota = val.uid_kabkota;
              $("#uid_kabkota").val(val.uid_kabkota).trigger("change");
            });
          }
        }
      });
    } else {
      $("#alamat").val("");
      $("#latitude").val("");
      $("#longitude").val("");
      $("#uid_provinsi").val("").trigger("change");
      // $("#uid_kabkota").html("--PILIH KAB/KOTA--");
    }
  });

  $('.delete-dialog').on('click', function() {
    var deleteUid = $(this).attr('data-uid');
    var komponenUid = $(this).attr('data-komponen-uid');
    var ajaxAction = $(this).attr('data-ajax');
    swal({
      title: 'Hapus data ini?',
      text: 'Data yang dihapus tidak bisa dikembalikan lagi!',
      type: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal'
    }).then(function(result) {
      if (result.value) {
        setTimeout(function() {
          $.ajax({
            url: baseUrl + ajaxAction,
            type: "POST",
            data: {
              x: deleteUid,
              c: komponenUid
            },
            async: false,
            success: function(response) {
              var result = JSON.parse(response);
              if (result.statusCode == 200) {
                swal({
                  title: 'Berhasil dihapus!',
                  html: '<span class="badge badge-success">' + result.message + '</span>',
                  type: 'success',
                  showConfirmButton: false
                });
                setTimeout(function() {
                  if(result['href']){
                    window.location.href = baseUrl + result['href'];
                  }else{
                    window.location.href = baseUrl + ctrl + '/' + act;
                  }

                }, 2000);
              } else {
                swal({
                  title: 'Gagal dihapus!',
                  html: '<span class="badge badge-danger">' + result.message + '</span>',
                  type: 'error',
                  showConfirmButton: false
                });
              }
            }
          });
        }, 200);
      }
    }).catch(swal.noop);
  });
  $(".verify").on("click", function() {
    var verify = $(this).attr("data-verify");
    var uid = $(this).attr("data-uid");
    verifyAct(uid, verify, 1);
  });
  $(".unverify").on("click", function() {
    var verify = $(this).attr("data-verify");
    var uid = $(this).attr("data-uid");
    verifyAct(uid, verify, 2);
  });

  $(".verifikasi").on("change", function() {
    var verify = $(this).find(':selected').attr('data-verify');
    var uid = $(this).find(':selected').attr('data-uid');
    var verif = $(this).val();
    // console.log(verif + '//' + verify + '//' + uid);
    verifyAct(uid, verify, verif);
  });

  function verifyAct(uid, verify, act) {
    $.ajax({
      url: baseUrl + ctrl + "/verifikasiAct/x/" + uid + "/f/" + verify + "/act/" + act,
      method: "GET",
      success: function(req) {
        if (req == 1) {
          if (ctrl == 'ika' && verify == 'v_pusat') {
            $(".do-max-" + uid).html(8);
          }
          if (act == 1) {
            $("." + verify + "_unverify" + uid).hide();
            $("." + verify + "_verify" + uid).show();
            $("." + verify + "_verifikasi" + uid).removeClass(' bg-danger').addClass('bg-info bg-accent-1');
          } else if (act == 2) {
            $("." + verify + "_unverify" + uid).show();
            $("." + verify + "_verify" + uid).hide();
            $("." + verify + "_verifikasi" + uid).removeClass('bg-info').addClass('bg-danger bg-accent-1');
          } else if (act == 'un') {
            $("." + verify + "_verifikasi" + uid).removeClass('bg-info bg-danger').addClass(' bg-accent-1');

          }
        } else {
          alert("error");
        }
      }
    });
  }
  $(function() {
    $('.freeze-table').freezeTable({
      'freezeColumn': false,
    });
    $('.freeze-table-2').freezeTable({
      'freezeColumn': false,
    });
  });

  window.onscroll = function() {
    myFunction()
  };

  function myFunction() {
    $(".scrollTobtn").val(window.pageYOffset);
    // if (window.pageYOffset >= 400 && window.pageYOffset <= 420) {
    //   $(function() {
    // 		 $(this).find(".freeze-table").freezeTable({
    // 	    'freezeColumn': false,
    // 	  });
    //   });
    // $('.freeze-table').freezeTable({
    //   'freezeColumn': false,
    // });
    // $('.freeze-table-2').freezeTable({
    //   'freezeColumn': false,
    // });
    // }
  }

  $(".catatan-rekomendasi").on('click', function() {
    $("#count-input-rekomendasi").html('');
    var tahun = $(this).attr("tahun");
    var provinsi = $(this).attr("provinsi");
    var kabkota = $(this).attr("kabkota");
    var aspek = $(this).attr("aspek");
    $("#catatan-rekomendasi-txt").val("");
    $("#catatan-rekomendasi-select").val("").trigger("change");

    if (tahun && provinsi && aspek) {
      $.ajax({
        url: baseUrl + "raport/getCatatanRekomendasi/t/" + tahun + "/p/" + provinsi + "/k/" + kabkota + "/a/" + aspek,
        method: "GET",
        success: function(request) {
          var req = JSON.parse(request);
          if (req.statusCode == 200) {
            $(".simpan-catatan-rekomendasi").attr("tahun", tahun);
            $(".simpan-catatan-rekomendasi").attr("provinsi", provinsi);
            $(".simpan-catatan-rekomendasi").attr("kabkota", kabkota);
            $(".simpan-catatan-rekomendasi").attr("aspek", aspek);
            $("#catatan-rekomendasi-txt").val(req.data['rekomendasi_' + aspek]).trigger("keyup");

            var crs = req.data['rekomendasi_' + aspek + '_select'];
            $("#catatan-rekomendasi-select").val((crs ? crs.split('|') : '')).trigger('change');
            // countLengtText("count-input-rekomendasi", req.data['rekomendasi_' + aspek] + ' ' + req.data['rekomendasi_' + aspek + '_select']);
          }
        }
      });
    }
  });

  var maxLengthTextRekomendasi = 700;
  $("#catatan-rekomendasi-txt").on("keyup", function() {
    var val = $(this).val();
    if(val.length > maxLengthTextRekomendasi){
      $("#catatan-rekomendasi-txt").val(val.substring(0,maxLengthTextRekomendasi));
      val = $(this).val();
    }
    countLengtText("count-input-rekomendasi", val);
  });

  function countLengtText(id, text) {
    var val = text;
    $("#" + id).removeClass("text-warning");
    $("#" + id).removeClass("text-danger");
    $("#" + id).removeClass("text-success");
    if (val.length > 0) {
      $("#" + id).html('*Sudah terisi ' + val.length + '/'+maxLengthTextRekomendasi+' karakter');
      if (val.length >= 600) {
        $("#" + id).addClass("text-danger");
        $("#" + id).removeClass("text-warning");
      } else if (val.length >= 450) {
        $("#" + id).addClass("text-warning");
        $("#" + id).removeClass("text-success");
      } else {
        $("#" + id).addClass("text-success");
      }
    } else {
      $("#" + id).html('');
    }
  }


  $(".simpan-catatan-rekomendasi").on("click hide.bs.modal", function(event) {
    var tahun = $(this).attr("tahun");
    var provinsi = $(this).attr("provinsi");
    var kabkota = $(this).attr("kabkota");
    var aspek = $(this).attr("aspek");

    if (tahun && provinsi && aspek) {
      var catatanVerifikatorTxt = $("#catatan-rekomendasi-txt").val();
      var catatanVerifikatorSelect = $("#catatan-rekomendasi-select").val();
      $.ajax({
        url: baseUrl + "raport/catatanVerifikasi/t/" + tahun + "/p/" + provinsi + "/k/" + kabkota + "/a/" + aspek,
        method: "POST",
        data: JSON.stringify({
          catatan: catatanVerifikatorTxt,
          catatanSelect: catatanVerifikatorSelect,
        }),
        success: function(response) {
          var res = JSON.parse(response);
          if (res.statusCode == 200) {
            swal({
              title: 'Berhasil disimpan!',
              html: res.message,
              type: 'success',
              showConfirmButton: false
            });
            var catatanVerifikatorTxtHtml = truncateString(catatanVerifikatorTxt + ' ' + catatanVerifikatorSelect.join(), 40);
            $("#catatan-" + tahun + "-" + provinsi + "-" + kabkota + "-" + aspek + "-short").html(catatanVerifikatorTxtHtml);
          } else {
            swal({
              title: 'Gagal disimpan!',
              html: res.message,
              type: 'error',
              showConfirmButton: false
            });
          }
        }
      });
    }
  });

  function truncateString(str, num) {
    if (str.length > num) {
      var numStart = (num / 2) - 2;
      var numEnd = str.length - 8;
      var strHtml = str.slice(0, numStart) + "..." + str.slice(numEnd, str.length);
      return strHtml;
    } else {
      return str;
    }
  }

  $('.file-maps').change(function() {
    var tahun = $(this).attr("tahun");
    var provinsi = $(this).attr("provinsi");
    var kabkota = $(this).attr("kabkota");
    var aspek = $(this).attr("aspek");

    if (tahun && provinsi && aspek) {
      $(".upload-file-peta-" + tahun + "-" + provinsi + "-" + kabkota + "-" + aspek).show();
      var fd = new FormData();
      var files = $("#file-" + tahun + "-" + provinsi + "-" + kabkota + "-" + aspek)[0].files[0];
      var fileName = files.name;
      var extFile = fileName.split('.').pop();
      if (extFile == 'png' || extFile == 'jpeg' || extFile == 'jpg') {
        fd.append('file', files);
        $.ajax({
          url: baseUrl + "raport/uploadPeta/t/" + tahun + "/p/" + provinsi + "/k/" + kabkota + "/a/" + aspek,
          type: 'POST',
          data: fd,
          contentType: false,
          processData: false,
          success: function(response) {
            var res = (JSON.parse(response));
            if (res.statusCode == 200) {
              var urlFile = '<a href="' + baseUrl + 'uploads/peta_sebaran/' + res.data["peta_sebaran_" + aspek] + '" target="_blank" class="badge badge-warning">Lihat</a>'
              $("#div-url-" + tahun + "-" + provinsi + "-" + kabkota + "-" + aspek).html(urlFile);
              swal("success", "Berhasil Diunggah", "success");
            }
          }
        });
      } else {
        swal("error", "format file tidak sesuai", "error");
      }
    }
  });

})(window);
