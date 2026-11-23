<?php

namespace App\Filament\Pages;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\StockService;
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
        return Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'balance']);
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
                'price' => (float) $product->retail_price,
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

        $method = PaymentMethod::tryFrom($this->paymentMethod) ?? PaymentMethod::Cash;

        try {
            $sale = DB::transaction(function () use ($method, $shift) {
                $warehouse = Warehouse::defaultOrCreate();
                $products = Product::whereIn('id', array_keys($this->cart))->lockForUpdate()->get()->keyBy('id');

                // الأسعار تُقرأ من قاعدة البيانات وليس من المتصفح
                $lines = [];
                $subtotal = 0;

                foreach ($this->cart as $id => $line) {
                    $product = $products->get($id);
                    $qty = round($this->num($line['qty']), 3);

                    if (! $product || $qty <= 0) {
                        continue;
                    }

                    $lineTotal = round($qty * (float) $product->retail_price, 2);
                    $subtotal += $lineTotal;
                    $lines[] = compact('product', 'qty', 'lineTotal');
                }

                if (empty($lines)) {
                    throw new RuntimeException('لا توجد أصناف صالحة في السلة');
                }

                $discount = min(max(0, $this->num($this->discount)), $subtotal);
                $total = round($subtotal - $discount, 2);

                $paid = ($this->paidAmount === '' || $this->paidAmount === null)
                    ? ($method === PaymentMethod::Credit ? 0.0 : $total)
                    : $this->num($this->paidAmount);

                $due = max(0, round($total - $paid, 2));
                $change = max(0, round($paid - $total, 2));

                if ($due > 0 && ! $this->customerId) {
                    throw new RuntimeException('اختر عميلاً لتسجيل المبلغ المتبقي كدين');
                }

                if ($due > 0) {
                    $customer = Customer::whereKey($this->customerId)->lockForUpdate()->firstOrFail();

                    if ((float) $customer->credit_limit > 0
                        && (float) $customer->balance + $due > (float) $customer->credit_limit) {
                        throw new RuntimeException('تجاوز سقف الدين للعميل: ' . $customer->name);
                    }
                }

                $sale = Sale::create([
                    'invoice_number' => 'TMP-' . uniqid(),
                    'user_id' => auth()->id(),
                    'customer_id' => $this->customerId,
                    'warehouse_id' => $warehouse->id,
                    'shift_id' => $shift->id,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'paid' => min($paid, $total),
                    'change_amount' => $change,
                    'due_amount' => $due,
                    'payment_method' => $method->value,
                    'payment_details' => $method->needsDetails() ? ($this->paymentDetails ?: null) : null,
                ]);

                $sale->update(['invoice_number' => 'INV-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

                foreach ($lines as $l) {
                    $sale->items()->create([
                        'product_id' => $l['product']->id,
                        'product_name' => $l['product']->name,
                        'quantity' => $l['qty'],
                        'unit_price' => $l['product']->retail_price,
                        'cost_price' => $l['product']->cost_price,
                        'total' => $l['lineTotal'],
                    ]);

                    app(StockService::class)->adjust(
                        $l['product']->id, $warehouse->id, -$l['qty'], 'sale', $sale->invoice_number
                    );
                }

                if ($due > 0) {
                    Customer::findOrFail($this->customerId)->addTransaction(LedgerType::Debt, $due, [
                        'sale_id' => $sale->id,
                        'description' => 'فاتورة ' . $sale->invoice_number,
                    ]);
                }

                return $sale;
            });
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title('تمت عملية البيع')
            ->body("رقم الفاتورة: {$sale->invoice_number} — الإجمالي: ₪" . number_format((float) $sale->total, 2))
            ->success()
            ->send();

        $this->resetSale();
        unset($this->products);
        $this->dispatch('pos-focus');
    }
}
