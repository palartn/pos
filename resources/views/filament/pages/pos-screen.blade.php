<x-filament-panels::page>

    <div class="pos"
         x-data
         x-on:keydown.f2.window.prevent="$refs.barcode.focus()"
         x-on:pos-focus.window="$nextTick(() => $refs.barcode.focus())">

        {{-- ================= الكتالوج ================= --}}
        <section class="pos-card">
            <div class="pos-toolbar">
                <input x-ref="barcode" autofocus type="text" class="pos-input is-barcode"
                       wire:model="barcode" wire:keydown.enter="addByBarcode"
                       placeholder="امسح الباركود ثم Enter  (F2)">
                <input type="search" class="pos-input"
                       wire:model.live.debounce.300ms="search"
                       placeholder="ابحث بالاسم أو الباركود...">
            </div>

            <div class="pos-chips">
                <button type="button" wire:click="$set('categoryId', null)"
                        @class(['pos-chip', 'is-active' => $categoryId === null])>الكل</button>
                @foreach ($this->categories as $cat)
                    <button type="button" wire:key="cat-{{ $cat->id }}" wire:click="$set('categoryId', {{ $cat->id }})"
                            @class(['pos-chip', 'is-active' => $categoryId === $cat->id])>{{ $cat->name }}</button>
                @endforeach
            </div>

            <div class="pos-grid">
                @forelse ($this->products as $product)
                    <button type="button" wire:key="p-{{ $product->id }}" wire:click="addProduct({{ $product->id }})" class="pos-product">
                        @if ($product->sold_by_weight)
                            <span class="pos-badge pos-badge--weight">بالوزن</span>
                        @elseif ($product->isLowStock())
                            <span class="pos-badge pos-badge--low">منخفض</span>
                        @endif
                        <span class="pos-product__name">{{ $product->name }}</span>
                        <span class="pos-product__meta">المخزون: {{ rtrim(rtrim(number_format((float) $product->stock, 3, '.', ''), '0'), '.') }} {{ $product->unit }}</span>
                        <span class="pos-product__price">₪{{ number_format((float) $product->retail_price, 2) }}</span>
                    </button>
                @empty
                    <div class="pos-empty">لا توجد أصناف مطابقة</div>
                @endforelse
            </div>
        </section>

        {{-- ================= السلة والدفع ================= --}}
        <aside class="pos-card pos-cart">
            <div class="pos-cart__head">
                <span>🛒 الفاتورة الحالية ({{ count($cart) }})</span>
                @if (count($cart))
                    <button type="button" class="pos-link" wire:click="clearCart" wire:confirm="إفراغ السلة؟">إفراغ</button>
                @endif
            </div>

            <div class="pos-lines">
                @forelse ($cart as $id => $line)
                    <div class="pos-line" wire:key="line-{{ $id }}">
                        <div>
                            <div class="pos-line__name">{{ $line['name'] }}</div>
                            <div class="pos-line__sub">₪{{ number_format((float) $line['price'], 2) }} / {{ $line['unit'] }}</div>
                        </div>
                        <div class="pos-line__total">
                            ₪{{ number_format((float) $line['price'] * (is_numeric($line['qty']) ? (float) $line['qty'] : 0), 2) }}
                        </div>
                        <div class="pos-qty">
                            <button type="button" wire:click="decrement({{ $id }})">−</button>
                            <input type="number" min="0" step="{{ $line['weight'] ? '0.001' : '1' }}"
                                   wire:model.live.debounce.400ms="cart.{{ $id }}.qty">
                            <button type="button" wire:click="increment({{ $id }})">+</button>
                        </div>
                        <div style="text-align:end">
                            <button type="button" class="pos-x" wire:click="remove({{ $id }})" title="حذف">✕</button>
                        </div>
                    </div>
                @empty
                    <div class="pos-empty">امسح باركود أو اختر صنفاً لبدء البيع</div>
                @endforelse
            </div>

            <div class="pos-foot">
                <div class="pos-row"><span>المجموع</span><span>₪{{ number_format($this->subtotal, 2) }}</span></div>
                <div class="pos-row">
                    <span>الخصم (₪)</span>
                    <input type="number" min="0" step="0.01" class="pos-input" wire:model.live.debounce.400ms="discount">
                </div>
                <div class="pos-row pos-row--total"><span>الإجمالي</span><span>₪{{ number_format($this->total, 2) }}</span></div>

                <div class="pos-methods">
                    @foreach ($this->paymentMethods as $m)
                        <button type="button" wire:key="m-{{ $m->value }}" wire:click="$set('paymentMethod', '{{ $m->value }}')"
                                @class(['pos-method', 'is-active' => $paymentMethod === $m->value])>{{ $m->label() }}</button>
                    @endforeach
                </div>

                @if (\App\Enums\PaymentMethod::from($paymentMethod)->needsDetails())
                    <input type="text" class="pos-input" wire:model="paymentDetails" placeholder="تفاصيل الدفع (رقم العملية / المحفظة...)">
                @endif

                <select class="pos-select" wire:model.live="customerId">
                    <option value="">— عميل نقدي —</option>
                    @foreach ($this->customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}@if ((float) $c->balance > 0) (دين: ₪{{ number_format((float) $c->balance, 2) }})@endif</option>
                    @endforeach
                </select>

                <div class="pos-row">
                    <span>المدفوع (₪)</span>
                    <input type="number" min="0" step="0.01" class="pos-input"
                           wire:model.live.debounce.400ms="paidAmount" placeholder="{{ number_format($this->paid, 2, '.', '') }}">
                </div>

                @if ($this->due > 0)
                    <div class="pos-row"><span>المتبقي (دين)</span><span class="pos-due">₪{{ number_format($this->due, 2) }}</span></div>
                @elseif ($this->change > 0)
                    <div class="pos-row"><span>الباقي للعميل</span><span class="pos-change">₪{{ number_format($this->change, 2) }}</span></div>
                @endif

                <button type="button" class="pos-pay" wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout"
                        @disabled(empty($cart))>
                    إتمام البيع — ₪{{ number_format($this->total, 2) }}
                </button>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
