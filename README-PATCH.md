# Patch kumulatif: rujukan layanan di item invoice (seperti relid WHMCS)

Sudah termasuk patch sebelumnya (guard ID Protection .id & renewal H-30, form order read-only,
perintah lumora:cancel-invalid-invoices). Salin semua ke root proyek, timpa file lama.

## Baru di patch ini
- database/migrations/2026_01_01_000075_add_service_reference_to_invoice_items_table.php
    tambah invoice_items.service_type / service_id / item_type (+ index) lalu backfill otomatis
- app/Models/InvoiceItem.php                       -> serviceRef(), service(), serviceLabel(), supportsServiceRef()
- app/Services/Billing/InvoiceItemServiceBackfill.php (BARU) -> isi rujukan untuk invoice lama
- app/Console/Commands/BackfillInvoiceServices.php (BARU)    -> php artisan lumora:backfill-invoice-services [--dry]
- app/Services/Billing/RenewalInvoiceService.php   -> item perpanjangan hosting/domain menyimpan rujukan layanan
- app/Services/Billing/UpgradeAddonService.php     -> item upgrade & addon menyimpan rujukan layanan
- app/Http/Controllers/Client/ServiceController.php-> item ID Protection menyimpan rujukan layanan
- app/Models/Invoice.php                           -> relatedServices() membaca rujukan item (juga untuk invoice LUNAS)
- app/Services/Payment/PaymentEligibilityService.php -> ruleViolation() memakai rujukan item, bukan menebak teks
- app/Console/Commands/CheckSetup.php              -> cek kolom invoice_items

## Urutan pasang (PENTING)
1. Backup database.
2. Salin file.
3. php artisan migrate            (menambah kolom + backfill otomatis)
4. php artisan lumora:backfill-invoice-services --dry   (lihat hasil), lalu tanpa --dry bila perlu diulang
5. php artisan optimize:clear

Kode aman bila ter-deploy SEBELUM migrate (semua akses kolom baru dijaga supportsServiceRef()),
tetapi rujukan baru baru tersimpan setelah migrate. Konsol Web "Tabel & kolom database lengkap"
akan merah sampai migrate dijalankan; itu disengaja.

## Verifikasi
- Detail INV-2026-0007 (hosting, sudah lunas): menampilkan "Perpanjangan hosting: appdataku.my.id"
- Detail INV-2026-0001: menampilkan ORD-1001 + "Pesanan domain: appdataku.my.id"
- Buat invoice ID Protection baru untuk domain non-.id: item berisi item_type=privacy, service_id=domain

## Batasan
- Item hasil checkout BARU (layanan dibuat setelah bayar) belum punya rujukan sampai backfill
  dijalankan lagi; tampilannya tetap memakai order terkait.
- Order hosting awal tidak bisa dihubungkan (tabel hosting_accounts tidak punya order_id).
- Belum dijalankan di Laravel: hanya lolos pengecekan sintaks PHP dan uji pola teks.
