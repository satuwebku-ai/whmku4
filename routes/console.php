<?php

/*
|--------------------------------------------------------------------------
| Tugas Terjadwal
|--------------------------------------------------------------------------
| SEMUA tugas terjadwal (pengingat tagihan, invoice perpanjangan,
| auto-suspend, backup, dll) dijalankan lewat SATU sistem: `lumora:cron`
| (lihat app/Console/Commands/RunCron.php & app/Models/CronJob.php).
| Jadwal tiap tugas diatur dari panel admin (Pengaturan → Cron Jobs),
| BUKAN lagi ditulis di sini sebagai Schedule:: -- supaya mengubah jadwal
| tidak perlu edit kode/akses SSH, dan supaya cuma ada SATU sumber
| kebenaran soal kapan tiap tugas berjalan.
|
| Yang perlu dipasang di cPanel → Cron Jobs, SATU baris saja, Once Per
| Minute (halaman admin Cron Jobs juga punya tombol salin baris ini):
|
|   * * * * * cd /home/user/namafolder && php artisan lumora:cron >> /dev/null 2>&1
|
| PENTING kalau situs ini sebelumnya sempat pakai baris
| `php artisan schedule:run` (versi lama sebelum panel Cron Jobs
| dibuat, dulu jadwalnya ditulis di sini pakai Schedule::command) --
| baris LAMA itu HARUS DICABUT dari cPanel. Membiarkan dua-duanya
| terpasang bersamaan membuat tugas yang sama (mis. pembuatan invoice
| perpanjangan, pengingat tagihan) berjalan lewat dua jadwal yang tidak
| sinkron satu sama lain, dan bisa terlihat seperti "muncul di luar
| jadwal" padahal sebenarnya terpicu dua sistem berbeda.
*/
