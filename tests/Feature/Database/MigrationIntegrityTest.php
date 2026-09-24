<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_blueprint_billing_tables_and_legacy_links_exist(): void
    {
        foreach ([
            'clients',
            'orders',
            'invoices',
            'invoice_items',
            'payments',
            'hosting_accounts',
            'domains',
            'credits',
            'transactions',
            'coupon_usages',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumn('invoices', 'order_id'));
        $this->assertTrue(Schema::hasColumn('invoice_items', 'order_id'));
        $this->assertTrue(Schema::hasColumn('invoice_items', 'invoice_id'));
        $this->assertTrue(Schema::hasColumn('invoices', 'is_topup'));
        $this->assertTrue(Schema::hasColumn('hosting_accounts', 'renewal_invoice_id'));
        $this->assertTrue(Schema::hasColumn('domains', 'renewal_invoice_id'));
    }

    public function test_financial_idempotency_constraints_exist(): void
    {
        $paymentIndexes = collect(Schema::getIndexes('payments'))->pluck('name');
        $couponIndexes = collect(Schema::getIndexes('coupon_usages'))->pluck('name');
        $creditIndexes = collect(Schema::getIndexes('credits'))->pluck('name');
        $transactionIndexes = collect(Schema::getIndexes('transactions'))->pluck('name');

        $this->assertTrue(Schema::hasColumn('invoices', 'paid_at'));
        $this->assertTrue(Schema::hasColumn('credits', 'idempotency_key'));
        $this->assertTrue(Schema::hasColumn('transactions', 'idempotency_key'));
        $this->assertTrue($paymentIndexes->contains('payments_gateway_external_unique'));
        $this->assertTrue($couponIndexes->contains('coupon_usages_invoice_unique'));
        $this->assertTrue($creditIndexes->contains('credits_idempotency_key_unique'));
        $this->assertTrue($transactionIndexes->contains('transactions_idempotency_key_unique'));
    }

    public function test_admin_reset_password_columns_are_present_after_incremental_migration(): void
    {
        foreach ([
            'reset_code_hash',
            'reset_code_expires_at',
            'reset_attempts',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('admins', $column),
                "Missing admins reset-password column: {$column}"
            );
        }
    }

    public function test_affiliate_wallet_migration_uses_mysql_safe_identifiers(): void
    {
        $ruleIndexes = collect(Schema::getIndexes('affiliate_commission_rules'))->pluck('name');
        $migration = file_get_contents(
            database_path('migrations/2029_03_01_000008_create_affiliate_wallet_transactions_table.php')
        );

        $this->assertTrue($ruleIndexes->contains('aff_comm_rules_type_event_active_idx'));
        $this->assertStringContainsString("'aff_wallet_tx_reversal_fk'", $migration);
    }
}