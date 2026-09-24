<?php

namespace App\Exceptions\Billing;

use Exception;

/**
 * Exception dasar untuk seluruh domain Billing (invoice, kupon, saldo).
 *
 * Semua exception billing lain sebaiknya turun dari kelas ini supaya kode
 * pemanggil bisa menangkap satu tipe (`catch (BillingException $e)`) kalau
 * cuma butuh pesan yang aman ditampilkan ke user, tanpa perlu tahu detail
 * subtipe-nya.
 */
class BillingException extends Exception
{
    //
}
