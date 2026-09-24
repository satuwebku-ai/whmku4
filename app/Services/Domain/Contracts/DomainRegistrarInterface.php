<?php

namespace App\Services\Domain\Contracts;

interface DomainRegistrarInterface
{
    /**
     * Cek ketersediaan satu atau lebih domain.
     *
     * @param  string[]  $domains  mis. ['contoh.com', 'contoh.id']
     * @return array{success: bool, message: string, results: array<string, bool>, raw: mixed}
     */
    public function checkAvailability(array $domains): array;

    /**
     * Registrasi domain baru.
     *
     * @param  array{domain: string, years: int, contact: array}  $params
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function registerDomain(array $params): array;

    /**
     * Perpanjang domain yang sudah ada.
     *
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function renewDomain(string $domain, int $years): array;
    /**
     * Ajukan transfer domain dari registrar lain.
     *
     * Provider yang tidak mendukung transfer otomatis harus mengembalikan
     * kegagalan terstruktur; pemanggil tidak boleh menganggap transfer selesai
     * hanya karena request API berhasil dikirim.
     *
     * @param  array{domain: string, years: int, auth_code?: string, contact?: array, whois_privacy?: bool}  $params
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function transferDomain(array $params): array;


    /**
     * Ubah nameserver domain.
     *
     * @param  string[]  $nameservers
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function setNameservers(string $domain, array $nameservers): array;

    /**
     * Uji koneksi & kredensial ke API registrar.
     *
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function testConnection(): array;
}
