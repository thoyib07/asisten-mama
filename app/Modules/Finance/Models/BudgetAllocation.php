<?php

namespace App\Modules\Finance\Models;

use App\Support\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Kantong": nominal yang dialokasikan ke satu kategori expense untuk satu periode.
 *
 * Sisa kantong dihitung saat ditampilkan (alokasi + top-up − pengeluaran), bukan disimpan
 * sebagai kolom snapshot — jumlah transaksi satu keluarga per bulan tidak sebanding dengan
 * risiko angka tersimpan yang diam-diam drift dari transaksi aslinya.
 */
class BudgetAllocation extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'category_id', 'period_start', 'amount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_start' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function topups(): HasMany
    {
        return $this->hasMany(BudgetTopup::class);
    }
}
