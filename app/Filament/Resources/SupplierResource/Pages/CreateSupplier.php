<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $opening = round((float) Arr::pull($data, 'opening_balance', 0), 2);

        return DB::transaction(function () use ($data, $opening) {
            $supplier = Supplier::create($data);

            if ($opening != 0.0) {
                app(SupplierAccount::class)->addOpening($supplier->id, $opening, 'رصيد سابق عند إضافة المورد');
            }

            return $supplier;
        });
    }
}
