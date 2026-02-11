<?php

namespace App\Http\Controllers;

use Botble\Payment\Models\PgLists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PGListController extends Controller
{
    public function store(Request $request)
    {
        $payment = PgLists::create($request->all());
        return response()->json($payment);
    }

    public function show($id)
    {
        $payment = PgLists::findOrFail($id);
        return response()->json($payment);
    }

    public function update(Request $request, $id)
    {
        $payment = PgLists::findOrFail($id);
        $payment->update($request->all());
        return response()->json($payment);
    }

    public function destroy($id)
    {
        $payment = PgLists::findOrFail($id);
        $payment->delete();
        return response()->json('Payment deleted successfully');
    }
}
