<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tenancy\HotelAccess;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Makes the given hotel the active tenant for this session — used by the
 * sidebar hotel switcher and the super-admin "Enter hotel" button.
 */
class SwitchHotelController extends Controller
{
    public function __invoke(Request $request, Hotel $hotel, HotelAccess $access): RedirectResponse
    {
        abort_unless($access->canEnter($request->user(), $hotel), 403);

        $request->session()->put(HotelAccess::SESSION_KEY, $hotel->id);

        return redirect()->route('dashboard');
    }
}
