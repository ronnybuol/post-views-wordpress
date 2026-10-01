# Zona Simple Views 1.2.0

Plugin WordPress ringan untuk menghitung kunjungan artikel dan mengatur angka tampil secara manual. Tidak memerlukan Post Views Counter atau Code Snippets.

## Instalasi

1. WordPress → Plugins → Add New Plugin → Upload Plugin.
2. Pilih `zona-simple-views-1.2.0.zip` → Install Now → Activate.
3. Buka Settings → Zona Simple Views.
4. Pilih posisi tampilan dan kategori yang diizinkan. Tidak memilih kategori berarti semua kategori.
5. Bersihkan cache halaman/CDN setelah mengubah pengaturan tampilan.

Default: tampil setelah isi artikel, label `Dibaca:`, jeda 30 menit, pengguna yang login tidak dihitung. Penghitungan dimulai dari nol bila belum diimpor. Versi 1.1.0 menyediakan impor massal dari Post Views Counter melalui halaman pengaturan.

## Memperbarui versi lama dan menyalin views lama

1. WordPress → Plugins → Add New Plugin → Upload Plugin → unggah ZIP 1.2.0.
2. Pilih **Replace current with uploaded** / **Ganti versi saat ini dengan yang diunggah** bila diminta. Folder plugin tetap `zona-simple-views`, sehingga pengaturan, shortcode, dan angka versi lama dipertahankan.
3. Nonaktifkan **Post Views Counter** lama (Deactivate). Jangan menghapus data atau menjalankan fitur reset plugin lama. Importer menolak berjalan ketika plugin sumber masih aktif.
4. Buka Settings → Zona Simple Views → bagian **Impor dari Post Views Counter** → **Impor views lama**.
5. Biarkan halaman terbuka sampai status selesai. Proses berlangsung 200 artikel per permintaan agar tidak membebani satu permintaan panjang.
6. Jika koneksi terputus atau tab tertutup, buka kembali halaman pengaturan dan klik **Lanjutkan impor**.
7. Bersihkan cache situs/CDN setelah selesai. Periksa beberapa artikel untuk memastikan angka sumber sudah masuk.

Tidak perlu mengaktifkan kembali plugin sumber untuk impor; selama tabel datanya masih ada, angka bisa dibaca langsung. Data sumber dibaca dari `{prefix}post_views`, hanya baris `type=4` dan `period=total`, dicocokkan berdasarkan ID artikel. Statistik harian/bulanan tidak dijumlahkan kembali agar tidak menggandakan total. Semua kategori artikel bertipe `post` disalin, termasuk draft/trash yang masih ada; halaman, produk, dan post type lain belum didukung. Artikel tanpa baris total di sumber tetap menggunakan angka Zona yang sudah ada.

Angka lama ditambahkan ke views otomatis Zona yang sudah tercatat, sementara penyesuaian manual tetap dipertahankan. Contoh: views sumber 5.000 + Zona 3 = otomatis 5.003; bila penyesuaian manual +100, angka tampil menjadi 5.103. Jika Anda pernah mengisi angka lama secara manual sebelum impor, periksa penyesuaian tersebut karena akan tetap ditambahkan; kembalikan penyesuaian ke nol bila angka itu hanya dimaksudkan mengganti data historis.

Impor tidak mengubah atau menghapus tabel sumber. Setiap artikel diberi tanda sudah diimpor; mengulang batch atau seluruh proses tidak menambahkan angka yang sama lagi. Artikel baru yang belum pernah diimpor bisa ditambahkan pada proses berikutnya. Artikel yang sudah diimpor tidak disinkronkan ulang jika counter sumber kemudian berubah.

Jika kedua plugin pernah aktif bersamaan, kunjungan pada masa tersebut mungkin ada pada kedua counter; tidak ada catatan kunjungan individual untuk menghapus tumpang tindih secara pasti. Nonaktifkan plugin lama sebelum proses perpindahan. Progres menampilkan jumlah artikel yang diperiksa, disalin, dan dilewati. Jika respons hilang setelah data tersimpan, angka tetap aman dari penambahan ganda, tetapi batch tersebut dapat tercatat sebagai dilewati saat percobaan ulang.

Pengaturan kategori dan shortcode `[zsv_views]` tetap sama. Warna label serta angka pada versi ini hitam (`#000`).

## Quick Edit pada daftar artikel

Mulai versi 1.2.0, buka **Posts → All Posts → Quick Edit** pada artikel, ubah kolom **Zona Views**, lalu klik **Update/Perbarui**. Tidak perlu membuka editor artikel lengkap.

Quick Edit mengambil angka terbaru dari server ketika dibuka. Tombol Perbarui menunggu pembacaan ini selesai. Jika angka tidak diubah, menyimpan judul, slug, atau kategori melalui Quick Edit tidak mengubah counter. Kunjungan baru yang masuk setelah formulir dibuka tetap dipertahankan saat angka disimpan.

Hak akses sama dengan editor lengkap: administrator dan editor yang memiliki `edit_others_posts` dan `edit_post` pada artikel. Setiap pembacaan dan penyimpanan memeriksa izin serta nonce artikel. Mengubah angka melalui Bulk Edit belum didukung.

Jika kolom Zona Views tidak terlihat, buka **Screen Options/Opsi Layar** pada halaman daftar artikel dan aktifkan kolomnya. Bila sesi kedaluwarsa atau pengambilan angka gagal, muat ulang halaman dan buka Quick Edit kembali.

## Mengubah angka secara manual

Buka editor artikel → kotak **Zona Views** → **Jumlah yang ditampilkan** → isi angka → Perbarui/Simpan artikel. Di Gutenberg, kotak dapat berada di bagian bawah editor. Jika tersembunyi, periksa Preferences → Panels.

Administrator dan editor (memiliki hak `edit_others_posts` serta `edit_post`) dapat menyesuaikan angka. Penulis biasa tidak dapat mengubahnya. Proses penyimpanan memeriksa nonce, izin, autosave, dan revisi.

Plugin menyimpan dua angka terpisah:

- Views otomatis: kunjungan yang tercatat sejak plugin aktif, tidak dapat ditimpa melalui kolom manual.
- Penyesuaian: selisih terhadap angka yang Anda inginkan.

Angka tampil = maksimum 0 dari views otomatis + penyesuaian. Contoh: otomatis 120, angka Anda ubah menjadi 1.000 → penyesuaian +880. Satu kunjungan baru membuat angka tampil 1.001 dan otomatis 121. Angka bisa dinaikkan atau diturunkan, dengan input antara 0 dan 1.000.000.000.

Views yang datang selama formulir editor terbuka dipertahankan. Contoh: saat membuka editor otomatis 120; selama mengedit masuk 3 views; Anda mengisi 1.000 dan menyimpan → angka tampil 1.003. Jika tidak mengubah kolom, menyimpan artikel tidak mengubah penyesuaian. Identitas pengguna dan waktu penyesuaian terakhir ditampilkan pada kotak Zona Views. Riwayat lengkap setiap perubahan belum disediakan.

Jika ingin kembali ke angka otomatis, isi Jumlah yang ditampilkan dengan angka Views otomatis yang tertera, lalu simpan.

## Kategori dan Elementor/Foxiz

Filter kategori hanya membatasi tampilan, bukan penghitungan. Artikel tetap dihitung walaupun views tidak ditampilkan. Artikel dengan salah satu kategori pilihan memenuhi filter. Subkategori tidak otomatis dianggap kategori induk; pilih subkategori secara terpisah.

Untuk Elementor: pilih posisi **Manual melalui shortcode**, lalu sisipkan widget Shortcode berisi:

```
[zsv_views]
```

Untuk menampilkan angka artikel tertentu:

```
[zsv_views id="123"]
```

Shortcode dengan ID lain hanya membaca views artikel tersebut; tidak menambahkan kunjungan ke artikel lain. Shortcode tetap mengikuti kategori pilihan. Tampilan otomatis menggunakan filter isi artikel standar WordPress. Template Foxiz/Elementor yang tidak melewati filter isi standar sebaiknya memakai shortcode.

Jika shortcode diletakkan langsung dalam isi artikel, tampilan otomatis dilewati agar tidak ganda. Jika shortcode ada pada template Elementor, pilih posisi Manual agar tidak muncul dua kali. Kolom Zona Views juga tersedia pada daftar Posts.

## Cara penghitungan

JavaScript pada halaman artikel menghubungi `wp-admin/admin-ajax.php`. Endpoint PHP membaca angka terbaru dan membuat token nonce baru di luar cache, lalu menerima kunjungan dan menaikkan counter dengan operasi SQL atomik. Dengan demikian counter tidak bergantung pada eksekusi PHP halaman artikel yang mungkin dilayani dari cache.

- Menghitung artikel `post` berstatus publish tanpa kata sandi.
- Menunggu tab terlihat sebelum mencatat kunjungan.
- Menghindari hitungan berulang browser yang sama selama jeda, memakai cookie bertanda tangan yang berisi ID artikel dan waktu kunjungan.
- Cookie berlaku satu hari dan menyimpan hingga 30 artikel terakhir; tidak menyimpan IP, identitas pengguna, atau mengirim data ke layanan eksternal.
- Cookie menggunakan HttpOnly, SameSite=Lax, dan Secure pada HTTPS.
- Mengecualikan user agent bot umum dan, secara default, pengguna login.
- Pembaruan manual tidak melewati endpoint publik; hanya melalui editor dengan izin dan nonce.
- Penghapusan permanen artikel turut menghapus baris views artikel tersebut.

## Batasan

Views ini adalah kunjungan browser yang berhasil menjalankan JavaScript, bukan pengguna unik dan bukan ukuran lengkap traffic server. Pemblokir script, cookie yang dihapus/diblokir, bot yang meniru browser, atau dua tab yang dibuka serentak dapat memengaruhi angka. Jika cookie tidak diterima, kunjungan ulang dapat dihitung lagi. Situs dengan pengaturan persetujuan cookie perlu mengintegrasikan script ini dengan pengelola persetujuan sesuai kebijakan situs.

Counter tidak dilengkapi sistem anti-fraud, laporan historis, grafik harian, ekspor, atau penyortiran daftar artikel berdasarkan views. Dua permintaan AJAX diperlukan untuk halaman artikel; untuk traffic sangat tinggi, ukur beban server sebelum penggunaan luas.

Angka yang disesuaikan secara manual tidak merepresentasikan traffic otomatis murni. Untuk laporan traffic, rujuk angka otomatis atau platform analitik Anda.

Plugin lain boleh tetap aktif, tetapi matikan tampilannya agar pembaca tidak melihat dua counter dengan angka berbeda. Snippet filter `pvc_post_views_html` dari percakapan sebelumnya hanya berlaku untuk Post Views Counter, bukan plugin ini; dapat dinonaktifkan jika tidak digunakan lagi.

## Pemecahan masalah

- Angka tidak bertambah: uji sebagai pengunjung logout/incognito. Pastikan tidak sedang preview artikel, artikel bukan password-protected, JavaScript aktif, dan `admin-ajax.php` tidak diblokir firewall/CDN.
- Jangan cache respons POST `admin-ajax.php`. Jika AJAX diblokir, angka statis yang dirender tetap ditampilkan tetapi belum diperbarui.
- Angka muncul dua kali: pilih posisi Manual jika menggunakan shortcode pada template.
- Views tidak tampil: periksa pilihan kategori dan apakah kategori benar-benar terpasang pada artikel.
- Setelah mengubah pengaturan, hapus cache WordPress/CDN; angka di HTML lama dapat muncul sesaat sebelum AJAX selesai.

## Persyaratan dan data

WordPress 6.0+, PHP 7.4+, MySQL/MariaDB. Menggunakan tabel `{prefix}zsv_views`, opsi `zsv_settings` / `zsv_db_version` / `zsv_import_state`, kunci sementara `zsv_import_lock`, dan meta `_zsv_last_adjustment`. Kolom `imported` menandai jumlah historis yang telah disalin. Setiap situs multisite menggunakan tabel dengan prefix masing-masing; tabel dibuat saat aktivasi atau pertama kali situs dipakai.

Menonaktifkan atau menghapus plugin tidak menghapus tabel/angka, sehingga dapat dipasang kembali tanpa kehilangan data. Jangan menghapus tabel secara manual kecuali Anda memang ingin membuang seluruh data plugin ini.

## Validasi rilis

Kode JavaScript diperiksa sintaks dan perilaku permintaan/penayangan. Kode PHP diperiksa dengan parser; logika penyesuaian, filter kategori, validasi izin/nonce, dan endpoint diperiksa dengan lingkungan pengujian. Pengujian tersebut tidak menggantikan uji langsung pada instalasi WordPress, tema Foxiz, plugin cache, dan konfigurasi hosting Anda. Pengujian migrasi mencakup simulasi 2.501 artikel, impor ulang seluruh artikel, batch yang diulang setelah respons terputus, serta kegagalan penyimpanan tanpa memajukan progres. Quick Edit juga diperiksa untuk pembacaan angka terbaru, hak akses, nonce, pengisian formulir, kegagalan jaringan, dan kunjungan yang masuk saat formulir terbuka. Versi 1.1.0 sudah berhasil dipasang dan diimpor pada situs pengguna; fitur Quick Edit 1.2.0 belum diuji langsung pada situs produksi Zonautara.

Lisensi GPL-2.0-or-later.

## Kode sumber dan rilis

Repositori: https://github.com/ronnybuol/post-views-wordpress

Paket instalasi ada di folder `releases/` dan pada halaman GitHub Releases. Gunakan ZIP plugin `zona-simple-views-VERSION.zip`; ZIP **Source code** dari GitHub berisi seluruh repo dan bukan paket instalasi yang disiapkan.

README, CHANGELOG, pengujian, dan skrip build disertakan dalam repo. Jalankan `python3 scripts/build-release.py` untuk mengemas ZIP dari folder `zona-simple-views/`. Pengujian lokal: `php tests/php-behavior.php`, `php tests/import-behavior.php`, `php tests/quick-edit-behavior.php`, dan file `tests/*-test.cjs` dengan Node.js. Runtime pengujian tidak menjadi dependensi plugin.
