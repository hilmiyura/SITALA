<?php
/**
 * desc : controller utilitas lintas modul, dipanggil lewat AJAX dari form pelaporan
 */
class utilsController extends Front
{
    public function init()
    {
        ($this -> session -> get('memberIKLH') ?: $this -> redirect("login"));

        //LOAD MODELS
        $this -> loadModel("utils");

        //GLOBAL VAR
        $this -> me = $this -> session -> get('memberIKLH');
    }

    //Memeriksa pergeseran koordinat yang dilaporkan terhadap koordinat master
    //lokasi_pemantauan. Seluruh logika dan validasinya ada di model utils supaya
    //controller lain (iku/ika/ikal) bisa memanggilnya langsung tanpa lewat HTTP.
    //
    //Contoh:
    //  POST /utils/validateLocationPelaporan
    //  {"uid_lokasi_pemantauan": 36421, "latitude": -6.16039, "longitude": 106.64251}
    //
    //  {"statusCode":200,"message":"...",
    //   "data":{"status":"ok","shift_m":12.47,"shift_ok_m":50,"shift_warn_m":100,
    //           "lokasi_input":{"latitude":-6.16039,"longitude":106.64251},
    //           "lokasi_sumber":{"uid_lokasi_pemantauan":36421,"kode_lokasi":"...",
    //                            "alamat":"...","alamat_detail":"...",
    //                            "latitude":-6.16028,"longitude":106.64252}}}
    //
    //status bernilai "ok" / "warn" / "invalid", mengikuti ambang di tabel
    //config_parameters (LOCATION_SHIFT_OK_M dan LOCATION_SHIFT_WARN_M).
    //
    //lokasi_input dan lokasi_sumber adalah dua titik yang dibandingkan, disertakan
    //supaya hasilnya bisa langsung ditampilkan tanpa query susulan ke lokasi_pemantauan.
    public function validateLocationPelaporan()
    {
        header("Content-Type: application/json; charset=UTF-8");

        $in = $this -> readInput();

        echo json_encode($this -> utils -> validateLocationPelaporan(
            isset($in['uid_lokasi_pemantauan']) ? $in['uid_lokasi_pemantauan'] : null,
            isset($in['latitude']) ? $in['latitude'] : null,
            isset($in['longitude']) ? $in['longitude'] : null
        ));
    }

    //Menggabungkan tiga sumber input, dari prioritas terendah ke tertinggi:
    //
    //  1. segmen URL   -- /utils/validateLocationPelaporan/latitude/-6.16/longitude/106.64
    //                     mengikuti konvensi routing Kick, praktis untuk uji cepat di browser
    //  2. body JSON    -- Front::post() hanya membaca $_POST, sedangkan Apidog dan
    //                     fetch() lazimnya mengirim Content-Type: application/json yang
    //                     TIDAK diisikan PHP ke $_POST. Tanpa langkah ini payload JSON
    //                     akan terbaca kosong dan endpointnya selalu menolak.
    //  3. form-encoded -- $_POST, yaitu bentuk kiriman jQuery $.post di form pelaporan
    private function readInput()
    {
        $in = $this -> params();
        if (!is_array($in)) {
            $in = array();
        }

        $raw = file_get_contents("php://input");
        if ($raw) {
            $json = json_decode($raw, TRUE);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $in = array_merge($in, $json);
            }
        }

        return array_merge($in, $this -> post());
    }
}
