<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Services\Hosting\CpanelWhmService;
use App\Services\Hosting\HostingPanelFactory;
use App\Services\Hosting\IdCloudHostService;
use App\Services\Vps\VpsHostingPanelAdapter;
use App\Services\Vps\VpsProviderFactory;
use Tests\TestCase;

class ServerVpsTypeTest extends TestCase
{
    public function test_server_vps_terdeteksi_lewat_jenis_panel_atau_vps_provider(): void
    {
        $this->assertTrue((new Server(['panel' => 'vps', 'vps_provider' => 'digitalocean']))->isCloud());
        $this->assertTrue((new Server(['panel' => 'cpanel', 'vps_provider' => 'idcloudhost']))->isCloud());
        $this->assertFalse((new Server(['panel' => 'cpanel']))->isCloud());
        $this->assertFalse((new Server(['panel' => 'plesk']))->isCloud());

        $this->assertSame('idcloudhost', (new Server(['panel' => 'vps', 'vps_provider' => 'idcloudhost']))->vpsDriver());
        $this->assertNull((new Server(['panel' => 'cpanel']))->vpsDriver());
        $this->assertSame('IDCloudHost', (new Server(['panel' => 'vps', 'vps_provider' => 'idcloudhost']))->vpsLabel());
    }

    public function test_hosting_panel_factory_memilih_service_sesuai_jenis_server(): void
    {
        $this->assertInstanceOf(CpanelWhmService::class, HostingPanelFactory::make(new Server(['panel' => 'cpanel'])));
        $this->assertInstanceOf(IdCloudHostService::class, HostingPanelFactory::make(new Server(['panel' => 'vps', 'vps_provider' => 'idcloudhost'])));
        $this->assertInstanceOf(VpsHostingPanelAdapter::class, HostingPanelFactory::make(new Server(['panel' => 'vps', 'vps_provider' => 'digitalocean'])));
    }

    public function test_setiap_provider_di_config_punya_adapter_aktif(): void
    {
        $this->assertNotEmpty(VpsProviderFactory::supported());

        foreach (array_keys(VpsProviderFactory::supported()) as $key) {
            $server = new Server(['panel' => 'vps', 'vps_provider' => $key]);

            $this->assertNotNull(VpsProviderFactory::make($server), "Provider [{$key}] ada di config tapi belum punya adapter.");
        }
    }
}
