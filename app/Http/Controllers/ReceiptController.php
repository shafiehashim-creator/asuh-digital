<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BankTransfers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    public function __construct(private BankTransfers $bankTransfers) {}

    public function create(Request $request, Order $order): View|RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if (! $this->bankTransfers->canUploadReceipt($order)) {
            return redirect()->route('preorder.pay', $order);
        }

        return view('preorder.receipt', ['order' => $order]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if (! $this->bankTransfers->canUploadReceipt($order)) {
            return redirect()->route('preorder.pay', $order);
        }

        $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $this->bankTransfers->uploadReceipt($order, $request->file('receipt'), $request->ip());

        return redirect()->route('preorder.pay', $order)->with('receipt_uploaded', true);
    }

    /**
     * Admin-only view of an uploaded receipt (files live outside public/).
     */
    public function show(Request $request, Order $order): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($order->bank_receipt_path && Storage::disk(BankTransfers::DISK)->exists($order->bank_receipt_path), 404);

        return Storage::disk(BankTransfers::DISK)->response($order->bank_receipt_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
