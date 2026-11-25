<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Builds;
use Tests\TestCase;

class ReceiptAccessTest extends TestCase
{
    use RefreshDatabase, Builds;

    private function saleBy(User $cashier): Sale
    {
        return Sale::create([
            'invoice_number' => 'INV-' . uniqid(),
            'user_id' => $cashier->id,
            'subtotal' => 10, 'discount' => 0, 'total' => 10, 'paid' => 10,
            'payment_method' => 'cash',
        ]);
    }

    public function test_guest_cannot_open_a_receipt(): void
    {
        $this->seedRoles();
        $sale = $this->saleBy($this->makeUser('cashier'));

        $this->get(route('receipts.show', $sale))->assertForbidden();
    }

    public function test_cashier_can_open_own_receipt(): void
    {
        $this->seedRoles();
        $cashier = $this->makeUser('cashier');
        $sale = $this->saleBy($cashier);

        $this->actingAs($cashier)->get(route('receipts.show', $sale))
            ->assertOk()->assertSee($sale->invoice_number);
    }

    public function test_cashier_cannot_open_another_cashiers_receipt(): void
    {
        $this->seedRoles();
        $sale = $this->saleBy($this->makeUser('cashier'));

        $this->actingAs($this->makeUser('cashier'))->get(route('receipts.show', $sale))->assertForbidden();
    }

    public function test_manager_can_open_any_receipt(): void
    {
        $this->seedRoles();
        $sale = $this->saleBy($this->makeUser('cashier'));

        $this->actingAs($this->makeUser('branch_manager'))->get(route('receipts.show', $sale))->assertOk();
    }

    public function test_user_without_sales_permissions_is_forbidden(): void
    {
        $this->seedRoles();
        $sale = $this->saleBy($this->makeUser('cashier'));

        $this->actingAs($this->makeUser('storekeeper'))->get(route('receipts.show', $sale))->assertForbidden();
    }

    public function test_roles_have_the_expected_financial_permissions(): void
    {
        $this->seedRoles();

        $cashier = $this->makeUser('cashier');
        $this->assertTrue($cashier->can('pos.access'));
        $this->assertFalse($cashier->can('sales.return'));
        $this->assertFalse($cashier->can('reports.view'));
        $this->assertFalse($cashier->can('users.manage'));

        $manager = $this->makeUser('branch_manager');
        $this->assertTrue($manager->can('sales.return'));
        $this->assertFalse($manager->can('users.manage'));

        $this->assertTrue($this->makeUser('super_admin')->can('users.manage'));
    }
}
