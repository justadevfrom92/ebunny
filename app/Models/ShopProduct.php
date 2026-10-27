<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;

/** A product in the website Shop (Corral → Products adds and edits them). */
class ShopProduct extends Model
{
    use RecordsHistory;

    public const ICONS = ['thermo', 'sensor', 'ev', 'cable', 'bulb', 'plug', 'meter', 'shield', 'house', 'sun', 'battery', 'tools', 'cap', 'cup', 'bag'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'float', 'was' => 'float', 'rating' => 'float', 'stock' => 'boolean', 'active' => 'boolean'];
    }

    /** The shape the website shop and cart use. */
    public function toShop(): array
    {
        return ['sku' => $this->sku, 'name' => $this->name, 'cat' => $this->cat, 'price' => $this->price, 'was' => $this->was, 'rating' => $this->rating, 'reviews' => $this->reviews,
            'stars' => $this->stars, 'stock' => $this->stock, 'badge' => $this->badge, 'icon' => $this->icon, 'blurb' => $this->blurb, 'order' => $this->position, 'slug' => strtolower($this->sku)];
    }
}
