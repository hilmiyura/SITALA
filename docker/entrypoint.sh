#!/bin/sh
#
# Menyiapkan file & folder yang di-gitignore tapi wajib ada supaya aplikasi
# tidak fatal error sebelum sempat jalan. Idempoten: aman dijalankan berulang,
# dan tidak menimpa apa pun yang sudah ada (termasuk milik host saat bind mount).
set -e

cd /var/www/html

# ---------------------------------------------------------------------------
# Folder runtime
#
# - temp/tpl_compile  : target kompilasi Smarty (COMPILEFOLDER), harus writable
# - uploads/          : target upload file (UPLOADFOLDER)
# - application/views/fe : WAJIB ada meski kosong. Class View selalu men-set
#   folder default "fe" saat instansiasi, sebelum controller sempat override ke
#   "be"; tanpa folder ini semua request mati dengan "Folder fe not found".
# ---------------------------------------------------------------------------
mkdir -p temp/tpl_compile uploads application/views/fe

# index.php me-require file ini tanpa syarat lalu memanggil `new allowupload()`,
# tapi file aslinya tidak ada di repo.
if [ ! -f temp/allowupload.php ]; then
	cat > temp/allowupload.php <<'PHP'
<?php
	// Stub yang dibuat otomatis oleh docker/entrypoint.sh
	class allowupload{
		public function __construct(){
		}
	}
PHP
fi

# kick/Kick.php me-require config/apikeys.php tanpa syarat. Isinya hanya
# membaca getenv(), jadi salinan mentah dari file contoh sudah cukup —
# nilai sebenarnya datang dari environment container atau .env.
if [ ! -f config/apikeys.php ]; then
	cp config/apikeys.example.php config/apikeys.php
fi

# Pada bind mount dari Windows/macOS kepemilikan file tidak bisa diubah;
# abaikan kegagalannya karena di sana filesystem-nya memang sudah permisif.
chown -R www-data:www-data temp uploads 2>/dev/null || true
chmod -R 0775 temp uploads 2>/dev/null || true

exec "$@"
