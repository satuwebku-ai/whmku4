<?php

namespace Tests\Unit;

use App\Models\HostingAccount;
use App\Models\Product;
use App\Models\Server;
use App\Services\Billing\HourlyRateCalculator;
use Tests\TestCase;

/**
 * Harga modal provider -> tarif per jam untuk produk VPS deposit, untuk
 * provider berbasis komponen (IDCloudHost) dan berbasis size (DigitalOcean).
 */
class HourlyRateCostModelTest extends TestCase
{
    private function idcServer(array $extra = []): Server
    {
        return (new Server())->forceFill($extra + [
            'panel' => 'vps',
            'vps_provider' => 'idcloudhost',
            'cost_cache' => ['model' => 'component', 'currency' => 'IDR', 'vcpu' => 10, 'ram' => 5, 'storage' => 1, 'backup' => 0, 'snapshot' => 0, 'windows' => 0],
        ]);
    }

    private function doServer(?float $fx = 16000): Server
    {
        return (new Server())->forceFill([
            'panel' => 'vps',
            'vps_provider' => 'digitalocean',
            'cost_fx_rate' => $fx,
            'cost_cache' => ['model' => 'size', 'currency' => 'USD', 'sizes' => [
                's-1vcpu-1gb' => ['hourly' => 0.01, 'monthly' => 7, 'vcpu' => 1, 'ram' => 1024, 'disk' => 25],
            ]],
        ]);
    }

    private function product(array $attrs): Product
    {
        return (new Product())->forceFill($attrs);
    }

    public function test_provider_komponen_harga_modal_dan_markup(): void
    {
        $spec = ['vcpu' => 2, 'ram' => 2048, 'disk' => 40];
        $server = $this->idcServer();

        $this->assertSame(70.0, HourlyRateCalculator::providerCost($server, $spec)); // 2x10 + 2x5 + 40x1
        $this->assertSame(105.0, HourlyRateCalculator::calculate($server, $spec, $this->product(['pricing_mode' => 'markup', 'markup_percent' => 50])));
    }

    public function test_provider_size_markup_memakai_harga_size_dan_kurs(): void
    {
        $spec = ['provider_size' => 's-1vcpu-1gb', 'vcpu' => 1, 'ram' => 1024, 'disk' => 25];
        $server = $this->doServer(16000);
        $product = $this->product(['pricing_mode' => 'markup', 'markup_percent' => 100]);

        $this->assertSame(160.0, HourlyRateCalculator::providerCost($server, $spec)); // 0.01 USD x 16000
        $this->assertSame(320.0, HourlyRateCalculator::calculate($server, $spec, $product));
    }

    public function test_tanpa_kurs_atau_size_tidak_dikenal_hasilnya_nol_bukan_gratis_diam_diam(): void
    {
        $product = $this->product(['pricing_mode' => 'markup', 'markup_percent' => 100]);
        $spec = ['provider_size' => 's-1vcpu-1gb', 'vcpu' => 1, 'ram' => 1024, 'disk' => 25];

        $this->assertSame(0.0, HourlyRateCalculator::calculate($this->doServer(null), $spec, $product));
        $this->assertSame(0.0, HourlyRateCalculator::providerCost($this->doServer(16000), ['provider_size' => 'tidak-ada']));
    }

    public function test_kartu_harga_manual_kosong_jatuh_ke_kartu_harga_server(): void
    {
        $spec = ['vcpu' => 2, 'ram' => 2048, 'disk' => 40];
        $server = $this->idcServer(['pricing_mode' => 'manual', 'price_per_vcpu_hour' => 7]);
        $kosong = $this->product(['pricing_mode' => 'manual']);

        $this->assertFalse($kosong->hasHourlyRateCard());
        $this->assertSame(14.0, HourlyRateCalculator::calculate($server, $spec, $kosong));
    }

    public function test_spek_akun_membawa_size_provider(): void
    {
        $account = (new HostingAccount())->forceFill(['package' => json_encode(['vcpu' => 1, 'ram' => 1024, 'disk' => 25, 'provider_size' => 's-1vcpu-1gb'])]);

        $this->assertSame('s-1vcpu-1gb', $account->vmSpec()['provider_size']);
    }
}
