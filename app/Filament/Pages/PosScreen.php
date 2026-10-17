<?php

namespace App\Filament\Pages;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CustomerAccount;
use App\Support\Money;
use App\Support\PaymentMethod;
use App\Support\Qty;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosScreen extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string $view = 'filament.pages.pos-screen';
    protected static ?string $title = 'نقطة البيع';
    protected static ?string $navigationGroup = 'المبيعات';
    protected static ?int $navigationSort = 2;

    public $search = '';
    public array $cart = [];

    public $customerId = null;
    public $customerSearch = '';
    public $showNewCustomer = false;
    public $newCustomerName = '';
    public $newCustomerPhone = '';

    public $paymentMethod = 'cash';
    public $paymentReference = '';
    public $notes = '';
    public $discount = 0;
    public $paid = '';

    // آخر فاتورة أُتمّت (لزر طباعة الإيصال)
    public $lastSaleId = null;
    public $lastInvoiceNumber = null;

    /**
     * هل لدى الكاشير الحالي جلسة مفتوحة؟
     */
    public function getHasOpenSessionProperty(): bool
    {
        return CashSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();
    }

    // ======================= العملاء =======================

    public function getSelectedCustomerProperty(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function getCustomerResultsProperty()
    {
        $term = trim($this->customerSearch);

        if ($term === '' || $this->customerId) {
            return collect();
        }

        return Customer::where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        })
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function selectCustomer($id)
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return;
        }

        $this->customerId = $customer->id;
        $this->customerSearch = '';
        $this->showNewCustomer = false;
        $this->resetErrorBag('customer');
    }

    public function clearCustomer()
    {
        $this->customerId = null;
        $this->customerSearch = '';
    }

    public function createCustomer()
    {
        $this->resetErrorBag(['newCustomerName', 'newCustomerPhone']);

        $name = trim($this->newCustomerName);
        $phone = trim($this->newCustomerPhone);

        if ($name === '') {
            $this->addError('newCustomerName', 'أدخل اسم العميل');
            return;
        }

        if (mb_strlen($name) > 255 || mb_strlen($phone) > 30) {
            $this->addError('newCustomerName', 'الاسم أو الهاتف طويل جداً');
            return;
        }

        $customer = Customer::create([
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
        ]);

        $this->selectCustomer($customer->id);
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';

        Notification::make()->title('تمت إضافة العميل واختياره')->success()->send();
    }

    // ======================= المنتجات والسلة =======================

    /**
     * يبحث بالباركود، ولو تطابق تام يضيف المنتج مباشرة للسلة.
     */
    public function searchProduct()
    {
        $product = Product::where('barcode', $this->search)->first();

        if ($product) {
            $this->addToCart($product->id);
            $this->search = '';
        }
    }

    public function getSearchResultsProperty()
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        return Product::with('unit')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('barcode', 'like', "%{$this->search}%");
            })
            ->limit(10)
            ->get();
    }

    public function addToCart($productId)
    {
        $product = Product::with('unit')->findOrFail($productId);
        $available = (float) $product->quantity;

        if ($available <= 0) {
            $this->addError('stock', "المنتج {$product->name} غير متوفر بالمخزون");
            return;
        }

        if (isset($this->cart[$productId])) {
            $newQty = $this->cart[$productId]['quantity'] + 1;

            if ($newQty > $available) {
                $this->addError('stock', "الكمية المتاحة من {$product->name} غير كافية");
                return;
            }

            $this->cart[$productId]['quantity'] = $newQty;
        } else {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->sale_price,
                'quantity' => min(1, $available),
                'max' => $available,
                'decimal' => (bool) $product->unit?->allow_decimal,
                'unit' => $product->unit?->name,
            ];
        }

        $this->search = '';
    }

    public function updateQty($productId, $qty)
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $qty = $this->cart[$productId]['decimal']
            ? round((float) $qty, 3)
            : (int) $qty;

        if ($qty <= 0) {
            unset($this->cart[$productId]);
            return;
        }

        $this->cart[$productId]['quantity'] = min($qty, $this->cart[$productId]['max']);
    }

    public function removeItem($productId)
    {
        unset($this->cart[$productId]);
    }

    public function getSubtotalProperty()
    {
        return round(
            collect($this->cart)->sum(fn ($i) => round($i['price'] * $i['quantity'], 2)),
            2
        );
    }

    public function getTotalProperty()
    {
        return max(0, round($this->subtotal - (float) $this->discount, 2));
    }

    // ======================= الدفع =======================

    /**
     * عند تغيير طريقة الدفع نمسح حقل التحديد السابق.
     */
    public function updatedPaymentMethod()
    {
        $this->paymentReference = '';
        $this->paid = '';
        $this->resetErrorBag('paymentReference');
    }

    /**
     * المبلغ المدفوع فعلياً: النقدي والآجل يُدخلهما الكاشير،
     * وباقي الطرق (بطاقة، محافظ، بنك...) إن ترك الحقل فارغاً يُعتبر المدفوع هو الإجمالي.
     */
    public function getEffectivePaidProperty()
    {
        if (in_array($this->paymentMethod, ['cash', 'credit'], true)) {
            return round((float) $this->paid, 2);
        }

        $paid = round((float) $this->paid, 2);

        return $paid > 0 ? $paid : $this->total;
    }

    /**
     * الباقي الذي يُعاد للزبون (في الدفع النقدي فقط).
     */
    public function getChangeProperty()
    {
        if ($this->paymentMethod !== 'cash') {
            return 0;
        }

        return max(0, round($this->effectivePaid - $this->total, 2));
    }

    /**
     * وصف الدين: رقم الفاتورة وأصنافها وسعر كل صنف.
     */
    protected function debtDescription(Sale $sale): string
    {
        $items = collect($this->cart)->map(function ($i) {
            return $i['name'] . ' ×' . Qty::format($i['quantity'])
                . ' (' . number_format(round($i['price'] * $i['quantity'], 2), 2) . ')';
        })->implode('، ');

        return 'فاتورة ' . $sale->invoice_number . ': ' . $items
            . ($sale->notes ? ' — ملاحظة: ' . $sale->notes : '');
    }

    public function checkout()
    {
        $this->resetErrorBag(['cart', 'paymentReference', 'customer', 'notes']);

        if (empty($this->cart)) {
            $this->addError('cart', 'السلة فارغة');
            return;
        }

        $session = CashSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();

        if (! $session) {
            $this->addError('cart', 'يجب فتح جلسة كاشير أولاً من صفحة "جلسة الكاشير"');
            return;
        }

        if (! PaymentMethod::exists($this->paymentMethod)) {
            $this->addError('cart', 'طريقة الدفع غير صالحة');
            return;
        }

        $customer = $this->customerId ? Customer::find($this->customerId) : null;

        if ($this->customerId && ! $customer) {
            $this->addError('customer', 'العميل المختار غير موجود');
            return;
        }

        if ($this->paymentMethod === 'credit' && ! $customer) {
            $this->addError('customer', 'اختر العميل أولاً لتسجيل البيع بالآجل');
            return;
        }

        $reference = PaymentMethod::needsDetails($this->paymentMethod)
            ? trim((string) $this->paymentReference)
            : '';

        if (PaymentMethod::detailsRequired($this->paymentMethod) && $reference === '') {
            $this->addError('paymentReference', 'حدّد طريقة الدفع');
            return;
        }

        if (mb_strlen($reference) > 255) {
            $this->addError('paymentReference', 'النص طويل جداً (الحد الأقصى 255 حرفاً)');
            return;
        }

        $notes = trim((string) $this->notes);

        if (mb_strlen($notes) > 1000) {
            $this->addError('notes', 'الملاحظات طويلة جداً (الحد الأقصى 1000 حرف)');
            return;
        }

        if ($this->paymentMethod !== 'credit' && $this->effectivePaid < $this->total) {
            $this->addError('cart', 'المبلغ المدفوع أقل من الإجمالي');
            return;
        }

        // المبلغ المسجَّل هو ما يخص الفاتورة فقط (بدون الباقي المُعاد للزبون)
        $paidAmount = min($this->effectivePaid, $this->total);

        // الجزء غير المدفوع من فاتورة الآجل يُسجَّل ديناً على العميل
        $debt = $this->paymentMethod === 'credit'
            ? round($this->total - $paidAmount, 2)
            : 0.0;

        try {
            $saleInfo = DB::transaction(function () use ($session, $paidAmount, $reference, $customer, $debt, $notes) {
                // قفل صفوف المنتجات والتحقق من المخزون لحظة الإتمام
                // (يمنع بيع كمية غير متوفرة لو باع كاشير آخر نفس المنتج)
                $locked = Product::whereIn('id', array_column($this->cart, 'id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($this->cart as $item) {
                    $product = $locked->get($item['id']);

                    if (! $product || (float) $product->quantity < (float) $item['quantity']) {
                        throw new RuntimeException('الكمية المتاحة من "' . $item['name'] . '" لم تعد كافية');
                    }
                }

                $sale = Sale::create([
                    'invoice_number' => 'INV-' . now()->format('YmdHis') . rand(10, 99),
                    'customer_id' => $customer?->id,
                    'user_id' => Auth::id(),
                    'cash_session_id' => $session->id,
                    'subtotal' => $this->subtotal,
                    'discount' => (float) $this->discount,
                    'tax' => 0,
                    'total' => $this->total,
                    'paid' => $paidAmount,
                    'status' => 'completed',
                    'notes' => $notes !== '' ? $notes : null,
                ]);

                foreach ($this->cart as $item) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'discount' => 0,
                        'total' => round($item['price'] * $item['quantity'], 2),
                    ]);

                    Product::where('id', $item['id'])
                        ->decrement('quantity', $item['quantity']);
                }

                Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $this->paymentMethod,
                    'amount' => $paidAmount,
                    'reference' => $reference !== '' ? $reference : null,
                ]);

                if ($customer && $debt > 0) {
                    app(CustomerAccount::class)->addDebt(
                        $customer->id,
                        $debt,
                        $sale,
                        $this->debtDescription($sale)
                    );
                }

                return [$sale->id, $sale->invoice_number];
            });
        } catch (RuntimeException $e) {
            $this->addError('cart', $e->getMessage());
            return;
        }

        [$this->lastSaleId, $this->lastInvoiceNumber] = $saleInfo;

        $change = $this->change;

        $this->cart = [];
        $this->discount = 0;
        $this->paid = '';
        $this->paymentReference = '';
        $this->notes = '';
        $this->customerId = null;
        $this->customerSearch = '';
        $this->showNewCustomer = false;
        $this->dispatch('sale-completed');

        Notification::make()
            ->title('تمت عملية البيع')
            ->body(
                $debt > 0
                    ? 'سُجّل على العميل دين بقيمة ' . Money::format($debt)
                    : ($change > 0 ? 'الباقي للزبون: ' . Money::format($change) : null)
            )
            ->success()
            ->send();
    }
}
