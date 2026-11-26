<?php

namespace App\Filament\Pages;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use RuntimeException;

class PosScreen extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'شاشة البيع';
    protected static ?string $slug = 'pos';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.pos-screen';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('pos.access') ?? false;
    }

    // حالة الشاشة (Livewire state)
    public string $search = '';
    public ?int $categoryId = null;
    public string $barcode = '';
    public array $cart = [];
    public $discount = 0;
    public ?int $customerId = null;
    public string $paymentMethod = 'cash';
    public $paidAmount = '';
    public string $paymentDetails = '';

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    /* ---------------------------- بيانات العرض ---------------------------- */

    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function customers(): Collection
    {
        return Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'balance', 'price_level']);
    }

    #[Computed]
    public function priceLevel(): string
    {
        return $this->customers->firstWhere('id', $this->customerId)?->price_level === 'wholesale'
            ? 'wholesale'
            : 'retail';
    }

    /** عند تغيير العميل يُعاد تسعير السلة بحسب مستواه (الخادم يعيد الحساب عند الحفظ على أي حال) */
    public function updatedCustomerId(): void
    {
        $level = $this->priceLevel;

        foreach (Product::whereIn('id', array_keys($this->cart))->get() as $product) {
            $this->cart[$product->id]['price'] = $product->priceFor($level);
        }
    }

    #[Computed]
    public function shift(): ?CashierShift
    {
        return CashierShift::openFor(auth()->id());
    }

    #[Computed]
    public function defaultWarehouseId(): int
    {
        return Warehouse::defaultOrCreate()->id;
    }

    #[Computed]
    public function products(): Collection
    {
        $warehouseId = $this->defaultWarehouseId;

        return Product::query()
            ->with(['stocks' => fn ($q) => $q->where('warehouse_id', $warehouseId)])
            ->where('is_active', true)
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->search !== '', function ($q) {
                $s = '%' . $this->search . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('barcode', 'like', $s));
            })
            ->orderBy('name')
            ->limit(60)
            ->get();
    }

    #[Computed]
    public function paymentMethods(): array
    {
        return PaymentMethod::cases();
    }

    /* ------------------------------- الحسابات ------------------------------ */

    private function num($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    #[Computed]
    public function subtotal(): float
    {
        return round(collect($this->cart)->sum(
            fn ($l) => $this->num($l['price']) * $this->num($l['qty'])
        ), 2);
    }

    #[Computed]
    public function total(): float
    {
        return max(0, round($this->subtotal - $this->num($this->discount), 2));
    }

    #[Computed]
    public function paid(): float
    {
        if ($this->paidAmount === '' || $this->paidAmount === null) {
            return $this->paymentMethod === 'credit' ? 0.0 : $this->total;
        }

        return $this->num($this->paidAmount);
    }

    #[Computed]
    public function change(): float
    {
        return max(0, round($this->paid - $this->total, 2));
    }

    #[Computed]
    public function due(): float
    {
        return max(0, round($this->total - $this->paid, 2));
    }

    /* ------------------------------ إدارة السلة ----------------------------- */

    public function addProduct(int $id): void
    {
        $product = Product::where('is_active', true)->find($id);

        if (! $product) {
            return;
        }

        if (isset($this->cart[$id])) {
            $this->cart[$id]['qty'] = $this->num($this->cart[$id]['qty']) + ($product->sold_by_weight ? 0.5 : 1);
        } else {
            $this->cart[$id] = [
                'name' => $product->name,
                'price' => $product->priceFor($this->priceLevel),
                'qty' => $product->sold_by_weight ? 1 : 1,
                'unit' => $product->unit,
                'weight' => (bool) $product->sold_by_weight,
            ];
        }
    }

    public function addByBarcode(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            return;
        }

        $product = Product::where('is_active', true)->where('barcode', $code)->first();

        if (! $product) {
            Notification::make()->title('الباركود غير موجود')->body($code)->warning()->send();

            return;
        }

        $this->addProduct($product->id);
    }

    public function increment(int $id): void
    {
        if (isset($this->cart[$id])) {
            $step = $this->cart[$id]['weight'] ? 0.5 : 1;
            $this->cart[$id]['qty'] = $this->num($this->cart[$id]['qty']) + $step;
        }
    }

    public function decrement(int $id): void
    {
        if (! isset($this->cart[$id])) {
            return;
        }

        $step = $this->cart[$id]['weight'] ? 0.5 : 1;
        $qty = $this->num($this->cart[$id]['qty']) - $step;

        $qty > 0 ? $this->cart[$id]['qty'] = $qty : $this->remove($id);
    }

    public function remove(int $id): void
    {
        unset($this->cart[$id]);
    }

    public function clearCart(): void
    {
        $this->resetSale();
    }

    private function resetSale(): void
    {
        $this->cart = [];
        $this->discount = 0;
        $this->customerId = null;
        $this->paymentMethod = 'cash';
        $this->paidAmount = '';
        $this->paymentDetails = '';
        $this->barcode = '';
    }

    /* ------------------------------ إتمام البيع ----------------------------- */

    public function checkout(): void
    {
        if (empty($this->cart)) {
            Notification::make()->title('السلة فارغة')->warning()->send();

            return;
        }

        $shift = $this->shift;

        if (! $shift) {
            Notification::make()->title('افتح وردية أولاً من قائمة «ورديات الكاشير»')->danger()->send();

            return;
        }

        try {
            $sale = app(CheckoutService::class)->checkout(
                collect($this->cart)->map(fn ($line) => $line['qty'])->all(),
                [
                    'discount' => $this->discount,
                    'customer_id' => $this->customerId,
                    'payment_method' => $this->paymentMethod,
                    'paid_amount' => $this->paidAmount,
                    'payment_details' => $this->paymentDetails,
                ],
                $shift,
                auth()->id(),
            );
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title('تمت عملية البيع')
            ->body("رقم الفاتورة: {$sale->invoice_number} — الإجمالي: ₪" . number_format((float) $sale->total, 2))
            ->actions([
                NotificationAction::make('print')
                    ->label('طباعة الفاتورة')
                    ->url(route('receipts.show', $sale) . '?autoprint=1', shouldOpenInNewTab: true),
            ])
            ->success()
            ->send();

        if (Setting::bool('auto_print_receipt')) {
            $this->dispatch('print-receipt', url: route('receipts.show', $sale));
        }

        $this->resetSale();
        unset($this->products);
        $this->dispatch('pos-focus');
    }
}
