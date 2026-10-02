<?php

namespace App\Http\Controllers;

use App\Models\Admin\InstitutePaymentGateway;
use App\Models\Admin\StudentDocument;
use App\Models\Admin\StudentPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Admissions portal: sign out and private files. A student only ever gets their own files.
 */
class PortalController extends Controller
{
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.home');
    }

    public function document(StudentDocument $document)
    {
        abort_unless(optional($document->student)->user_id === Auth::id(), 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function receipt(StudentPayment $payment)
    {
        abort_unless(optional($payment->student)->user_id === Auth::id() && $payment->status === 'success', 404);
        $payment->load(['student.institute', 'student.course', 'due', 'gateway', 'verifier']);

        return view('admin.print.receipt', ['payment' => $payment, 'student' => $payment->student]);
    }

    /**
     * UPI QR image of an institute (its students on the portal; staff with fees.manage in the admin).
     */
    public function paymentQr(InstitutePaymentGateway $setting)
    {
        $user = Auth::user();
        $allowed = $user->hasRole('student')
            ? (int) $user->institute_id === (int) $setting->institute_id
            : $user->can('fees.manage'); // institute scope already limits staff to their institute

        abort_unless($allowed && $setting->qr_code_path && Storage::disk('local')->exists($setting->qr_code_path), 404);

        return Storage::disk('local')->response($setting->qr_code_path, 'upi-qr.png', ['Cache-Control' => 'private, max-age=600']);
    }
}
