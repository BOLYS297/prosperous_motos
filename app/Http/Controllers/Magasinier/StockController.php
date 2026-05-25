<?php

namespace App\Http\Controllers\Magasinier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index()
    {
        $boutiqueId = \Illuminate\Support\Facades\Auth::user()->boutique_id;
        
        // We get all products. If they are in stock, we show quantity.
        // We load the stock for the current boutique only.
        $produits = \App\Models\Produit::with(['stocks' => function($query) use ($boutiqueId) {
            $query->where('boutique_id', $boutiqueId);
        }])->orderBy('nom')->get();

        return view('magasinier.stocks.index', compact('produits'));
    }
}
