<?php

namespace App\Support;

use App\Http\Controllers\Backend\Corral\CustomerController;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Find a customer to act on (Order for a Customer, Make a PDF for an Account): the same search as
 * Corral → Customer Search — name, first or last name, email, phone, account, ESIID or address.
 */
class CustomerFinder
{
    public const PARAM = 'find';

    /** Null when nothing was searched; otherwise a page of 10 matches (?find_page=). */
    public static function search(?string $term): ?LengthAwarePaginator
    {
        $term = trim((string) $term);
        if ($term === '') {
            return null;
        }
        $q = Customer::query()->orderBy('name');
        CustomerController::applyFilters($q, ['q' => mb_substr($term, 0, 100)]);

        return $q->paginate(10, ['*'], self::PARAM.'_page')->withQueryString();
    }
}
