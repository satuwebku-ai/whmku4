<?php

namespace App\Services\Billing;

use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Setting;
use App\Notifications\ServiceSuspended;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu pintu untuk siklus layanan akibat tagihan overdue:
 * overdue -> suspend/expire -> paid -> reactivate/renew.
 *
 * Keputusan billing tetap berasal dari invoice renewal yang terkait dengan
 * layanan. Invoice umum milik klien tidak boleh otomatis men-suspend semua
 * layanan klien.
 */
class OverdueServiceLifecycle
{
    public function suspendHosting(HostingAccount $hosting): bool
    {
        return Cache::lock('overdue-suspend:' . $hosting->id, 1800)->get(function () use ($hosting): bool {
            // Ambil snapshot dan validasi dalam transaksi singkat. Jangan
            // menahan row lock saat memanggil API provider.
            $snapshot = DB::transaction(function () use ($hosting) {
                $locked = HostingAccount::query()->lockForUpdate()->find($hosting->id);

                if (! $locked || $locked->status !== 'active' || ! $locked->renewal_invoice_id) {
                    return null;
                }

                $invoice = $locked->renewalInvoice()->lockForUpdate()->first();
                if (! $invoice || ! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
                    return null;
                }

                return [
                    'id' => $locked->id,
                    'domain' => $locked->domain,
                    'client_id' => $locked->client_id,
                    'renewal_invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'username' => $locked->username,
                    'server' => $locked->serverModel,
                ];
            });

            if (! $snapshot) {
                return false;
            }

            $message = 'Disuspend otomatis: invoice ' . $snapshot['invoice_number'] . ' belum dibayar melewati batas toleransi.';
            if ($snapshot['server'] && $snapshot['username']) {
                $result = HostingPanelFactory::make($snapshot['server'])->suspendAccount(
                    $snapshot['username'],
                    'Tagihan belum dibayar: ' . $snapshot['invoice_number']
                );

                if (! $result['success']) {
                    Log::warning('Suspend API gagal; layanan tidak ditandai suspended di database.', [
                        'hosting_account_id' => $snapshot['id'],
                        'message' => $result['message'] ?? 'Unknown provider error',
                    ]);
                    return false;
                }

                $message = $result['message'] ?? $message;
            }

            $updated = DB::transaction(function () use ($snapshot, $message) {
                $locked = HostingAccount::query()->lockForUpdate()->find($snapshot['id']);
                if (! $locked
                    || $locked->status !== 'active'
                    || (int) $locked->renewal_invoice_id !== (int) $snapshot['renewal_invoice_id']) {
                    return null;
                }

                $locked->update([
                    'status' => 'suspended',
                    'provision_message' => $message,
                ]);

                return $locked->fresh(['client']);
            });

            if (! $updated) {
                return false;
            }

            $this->auditHosting($updated, 'suspend', $message);
            $invoice = $updated->renewalInvoice()->first();

            ActivityLog::record(
                'service',
                'Hosting disuspend otomatis: ' . $updated->domain,
                'Invoice ' . ($invoice?->invoice_number ?? $snapshot['invoice_number']) . ' belum dibayar.',
                route('admin.hosting-accounts.details', $updated),
                'danger',
                $updated->client_id,
            );

            if (Setting::get('notify_suspend', '1') === '1' && $updated->client && $invoice) {
                try {
                    $updated->client->notify(new ServiceSuspended($updated->domain, $invoice));
                } catch (Throwable $e) {
                    Log::warning('Notifikasi suspend gagal: ' . $e->getMessage());
                }
            }

            return true;
        }) ?? false;
    }

    public function reactivateHosting(HostingAccount $hosting): bool
    {
        return Cache::lock('overdue-unsuspend:' . $hosting->id, 1800)->get(function () use ($hosting): bool {
            $snapshot = DB::transaction(function () use ($hosting) {
                $locked = HostingAccount::query()->lockForUpdate()->find($hosting->id);

                if (! $locked || $locked->status !== 'suspended') {
                    return null;
                }

                return [
                    'id' => $locked->id,
                    'username' => $locked->username,
                    'server' => $locked->serverModel,
                ];
            });

            if (! $snapshot) {
                return false;
            }

            if ($snapshot['server'] && $snapshot['username']) {
                $result = HostingPanelFactory::make($snapshot['server'])
                    ->unsuspendAccount($snapshot['username']);

                if (! $result['success']) {
                    Log::error('Unsuspend otomatis gagal; layanan tetap suspended.', [
                        'hosting_account_id' => $snapshot['id'],
                        'message' => $result['message'] ?? 'Unknown provider error',
                    ]);
                    return false;
                }
            }

            $updated = DB::transaction(function () use ($snapshot) {
                $locked = HostingAccount::query()->lockForUpdate()->find($snapshot['id']);
                if (! $locked || $locked->status !== 'suspended') {
                    return null;
                }

                $locked->update(['status' => 'active']);

                return $locked;
            });

            if (! $updated) {
                return false;
            }

            $this->auditHosting($updated, 'unsuspend', 'Hosting diaktifkan kembali setelah invoice renewal dibayar.');

            return true;
        }) ?? false;
    }

    public function expireDomain(Domain $domain): bool
    {
        return DB::transaction(function () use ($domain): bool {
            $domain = Domain::query()->lockForUpdate()->find($domain->id);

            if (! $domain || $domain->status !== 'active' || ! $domain->renewal_invoice_id) {
                return false;
            }

            $invoice = $domain->renewalInvoice()->lockForUpdate()->first();
            if (! $invoice || ! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
                return false;
            }

            if (! $domain->expiry_date || $domain->expiry_date->gte(now()->startOfDay())) {
                return false;
            }

            $message = 'Kedaluwarsa otomatis: invoice perpanjangan belum dibayar sampai lewat tanggal expiry.';
            $domain->update([
                'status' => 'expired',
                'provision_message' => $message,
            ]);

            ActivityLog::record(
                'domain',
                'Domain kedaluwarsa: ' . $domain->domain_name,
                'Invoice perpanjangan belum dibayar.',
                route('admin.domains.details', $domain),
                'danger',
                $domain->client_id,
            );

            return true;
        });
    }

    private function auditHosting(HostingAccount $hosting, string $action, string $message): void
    {
        if (class_exists(\App\Models\HostingAccountLog::class)) {
            $hosting->logs()->create([
                'action' => $action,
                'message' => $message,
            ]);
        }
    }
}
