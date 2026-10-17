<x-filament-panels::page>
    <div dir="rtl" style="display:grid; gap:1rem;">

        @unless ($this->hasOpenSession)
            <div style="background:#fef3c7; border:1px solid #f59e0b; color:#92400e; padding:.75rem 1rem; border-radius:.5rem;">
                لا توجد جلسة كاشير مفتوحة، ولا يمكن إتمام البيع قبل فتحها.
                <a href="{{ \App\Filament\Pages\CashSessionScreen::getUrl() }}" style="text-decoration:underline; font-weight:700;">افتح جلسة الآن</a>
            </div>
        @endunless

        @if ($lastSaleId)
            <div style="background:#dcfce7; border:1px solid #16a34a; color:#166534; padding:.6rem 1rem; border-radius:.5rem; display:flex; justify-content:space-between; align-items:center; gap:1rem;">
                <span>آخر فاتورة: <strong>{{ $lastInvoiceNumber }}</strong></span>
                <a href="{{ route('receipts.show', ['sale' => $lastSaleId, 'print' => 1]) }}" target="_blank" style="text-decoration:underline; font-weight:700;">🖨 طباعة الإيصال</a>
            </div>
        @endif

        <div style="display:grid; grid-template-columns:2fr 1fr; gap:1rem; align-items:start;">

            {{-- عمود البحث والمنتجات --}}
            <div style="display:grid; gap:.75rem;">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.200ms="search"
                        wire:keydown.enter="searchProduct"
                        placeholder="امسح الباركود أو اكتب اسم المنتج..."
                        autofocus
                    />
                </x-filament::input.wrapper>

                @error('stock')
                    <p style="color:#dc2626; font-size:.9rem;">{{ $message }}</p>
                @enderror

                @if ($this->searchResults->count())
                    <div style="border:1px solid rgba(128,128,128,.3); border-radius:.5rem; overflow:hidden;">
                        @foreach ($this->searchResults as $product)
                            <div
                                wire:click="addToCart({{ $product->id }})"
                                wire:key="result-{{ $product->id }}"
                                style="padding:.75rem 1rem; cursor:pointer; display:flex; justify-content:space-between; border-bottom:1px solid rgba(128,128,128,.15);"
                            >
                                <span>{{ $product->name }}</span>
                                <span>{{ \App\Support\Money::format($product->sale_price) }}@if ($product->unit) / {{ $product->unit->name }}@endif</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- عمود السلة --}}
            <x-filament::section heading="السلة">
                @php $cu = $this->selectedCustomer; @endphp

                <div style="margin-bottom:.75rem; padding-bottom:.75rem; border-bottom:1px solid rgba(128,128,128,.25);">
                    @if ($cu)
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem;">
                            <div>
                                <div style="font-weight:700;">{{ $cu->name }}</div>
                                @if ($cu->phone)
                                    <div style="font-size:.8rem; opacity:.7;">{{ $cu->phone }}</div>
                                @endif
                                @if ((float) $cu->balance > 0)
                                    <div style="font-size:.85rem; color:#dc2626;">رصيده المستحق: {{ \App\Support\Money::format($cu->balance) }}</div>
                                @endif
                            </div>
                            <button type="button" wire:click="clearCustomer" title="إلغاء اختيار العميل" style="color:#dc2626;">✕</button>
                        </div>
                    @else
                        <label style="display:block; font-size:.85rem; margin-bottom:.25rem;">العميل (بدون اختيار = عميل نقدي)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model.live.debounce.250ms="customerSearch" placeholder="ابحث بالاسم أو رقم الهاتف..." />
                        </x-filament::input.wrapper>

                        @if ($this->customerResults->count())
                            <div style="border:1px solid rgba(128,128,128,.3); border-radius:.5rem; overflow:hidden; margin-top:.35rem;">
                                @foreach ($this->customerResults as $c)
                                    <div
                                        wire:click="selectCustomer({{ $c->id }})"
                                        wire:key="customer-{{ $c->id }}"
                                        style="padding:.5rem .75rem; cursor:pointer; display:flex; justify-content:space-between; border-bottom:1px solid rgba(128,128,128,.15);"
                                    >
                                        <span>{{ $c->name }}</span>
                                        <span style="opacity:.7; font-size:.85rem;">{{ $c->phone }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <button type="button" wire:click="$toggle('showNewCustomer')" style="font-size:.85rem; text-decoration:underline; margin-top:.4rem;">
                            + عميل جديد
                        </button>

                        @if ($showNewCustomer)
                            <div style="display:grid; gap:.5rem; margin-top:.5rem;">
                                <x-filament::input.wrapper :valid="! $errors->has('newCustomerName')">
                                    <x-filament::input type="text" wire:model="newCustomerName" placeholder="اسم العميل" />
                                </x-filament::input.wrapper>
                                @error('newCustomerName')
                                    <p style="color:#dc2626; font-size:.85rem;">{{ $message }}</p>
                                @enderror
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" wire:model="newCustomerPhone" placeholder="رقم الهاتف (اختياري)" />
                                </x-filament::input.wrapper>
                                <x-filament::button wire:click="createCustomer" size="sm" color="gray">حفظ واختيار</x-filament::button>
                            </div>
                        @endif
                    @endif

                    @error('customer')
                        <p style="color:#dc2626; font-size:.9rem; margin-top:.4rem;">{{ $message }}</p>
                    @enderror
                </div>

                @error('cart')
                    <p style="color:#dc2626; font-size:.9rem; margin-bottom:.5rem;">{{ $message }}</p>
                @enderror

                <div style="display:grid; gap:.5rem; max-height:22rem; overflow-y:auto;">
                    @forelse ($cart as $item)
                        <div wire:key="cart-{{ $item['id'] }}" style="display:flex; align-items:center; gap:.5rem;">
                            <span style="flex:1; font-size:.9rem;">{{ $item['name'] }}<small style="display:block; opacity:.6;">{{ \App\Support\Money::format($item['price']) }}@if ($item['unit']) / {{ $item['unit'] }}@endif</small></span>
                            <input
                                type="number"
                                step="{{ $item['decimal'] ? '0.001' : '1' }}"
                                min="{{ $item['decimal'] ? '0.001' : '1' }}"
                                wire:change="updateQty({{ $item['id'] }}, $event.target.value)"
                                value="{{ $item['quantity'] }}"
                                style="width:5rem; text-align:center; border:1px solid rgba(128,128,128,.4); border-radius:.375rem; padding:.2rem; background:transparent;"
                            >
                            <span style="width:4.5rem; text-align:left; font-size:.9rem;">{{ \App\Support\Money::format(round($item['price'] * $item['quantity'], 2)) }}</span>
                            <button type="button" wire:click="removeItem({{ $item['id'] }})" style="color:#dc2626;">✕</button>
                        </div>
                    @empty
                        <p style="opacity:.6; font-size:.9rem;">لا توجد أصناف بعد</p>
                    @endforelse
                </div>

                <hr style="margin:.75rem 0; opacity:.3;">

                <div style="display:grid; gap:.75rem;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>الإجمالي الفرعي</span>
                        <span>{{ \App\Support\Money::format($this->subtotal) }}</span>
                    </div>

                    <div>
                        <label style="display:block; font-size:.85rem; margin-bottom:.25rem;">خصم</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" step="0.01" min="0" wire:model.live="discount" />
                        </x-filament::input.wrapper>
                    </div>

                    <div style="display:flex; justify-content:space-between; font-weight:700; font-size:1.2rem;">
                        <span>الإجمالي</span>
                        <span>{{ \App\Support\Money::format($this->total) }}</span>
                    </div>

                    <div style="display:grid; grid-template-columns:{{ \App\Support\PaymentMethod::needsDetails($paymentMethod) ? '1fr 1fr' : '1fr' }}; gap:.5rem; align-items:start;">
                        <div>
                            <label style="display:block; font-size:.85rem; margin-bottom:.25rem;">طريقة الدفع</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="paymentMethod">
                                    @foreach (\App\Support\PaymentMethod::options() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        @if (\App\Support\PaymentMethod::needsDetails($paymentMethod))
                            <div>
                                <label style="display:block; font-size:.85rem; margin-bottom:.25rem;">{{ \App\Support\PaymentMethod::detailsLabel($paymentMethod) }}</label>
                                <x-filament::input.wrapper :valid="! $errors->has('paymentReference')">
                                    <x-filament::input type="text" maxlength="255" wire:model="paymentReference" />
                                </x-filament::input.wrapper>
                                @error('paymentReference')
                                    <p style="color:#dc2626; font-size:.85rem; margin-top:.25rem;">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <div>
                        <label style="display:block; font-size:.85rem; margin-bottom:.25rem;">
                            المبلغ المدفوع
                            @unless (in_array($paymentMethod, ['cash', 'credit'], true))
                                <span style="opacity:.6; font-size:.8rem;">(اتركه فارغاً = الإجمالي)</span>
                            @endunless
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.live="paid"
                                :placeholder="in_array($paymentMethod, ['cash', 'credit'], true) ? '0.00' : \App\Support\Money::format($this->total)"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    @if ($paymentMethod === 'credit')
                        <div style="display:flex; justify-content:space-between; color:#dc2626; font-weight:700;">
                            <span>المتبقي على العميل (دين)</span>
                            <span>{{ \App\Support\Money::format(max(0, $this->total - $this->effectivePaid)) }}</span>
                        </div>
                    @endif

                    @if ($this->change > 0)
                        <div style="display:flex; justify-content:space-between; color:#16a34a; font-weight:700;">
                            <span>الباقي للزبون</span>
                            <span>{{ \App\Support\Money::format($this->change) }}</span>
                        </div>
                    @endif

                    <div>
                        <label style="display:block; font-size:.85rem; margin-bottom:.25rem;">ملاحظات (اختياري)</label>
                        <textarea
                            wire:model="notes"
                            rows="2"
                            maxlength="1000"
                            placeholder="أي ملاحظة تظهر على الفاتورة"
                            style="width:100%; border:1px solid rgba(128,128,128,.4); border-radius:.5rem; padding:.5rem .75rem; background:transparent; font-size:.9rem;"
                        ></textarea>
                        @error('notes')
                            <p style="color:#dc2626; font-size:.85rem; margin-top:.25rem;">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-filament::button
                        wire:click="checkout"
                        wire:loading.attr="disabled"
                        size="lg"
                        :disabled="! $this->hasOpenSession"
                    >
                        إتمام البيع
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
