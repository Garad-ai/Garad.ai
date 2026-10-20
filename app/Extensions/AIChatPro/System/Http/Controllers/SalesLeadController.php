<?php

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SalesLeadController extends Controller
{
    private const TABLE = 'ai_chat_pro_sales_leads';

    private const SALES_EMAIL = 'info@garad.ai';

    private const STATUSES = ['new', 'contacted', 'closed'];

    public function submit(Request $request): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'companySize' => 'required|string|max:40',
            'companyName' => 'required|string|max:160',
            'firstName'   => 'required|string|max:100',
            'lastName'    => 'required|string|max:100',
            'email'       => 'required|email:rfc|max:190',
            'phone'       => 'required|string|min:5|max:60',
            'interest'    => 'required|string|max:120',
            'message'     => 'nullable|string|max:3000',
        ]);

        $now = now();

        $id = DB::table(self::TABLE)->insertGetId([
            'user_id'      => $request->user()?->id,
            'company_size' => $data['companySize'],
            'company_name' => $data['companyName'],
            'first_name'   => $data['firstName'],
            'last_name'    => $data['lastName'],
            'email'        => $data['email'],
            'phone'        => $data['phone'],
            'interest'     => $data['interest'],
            'message'      => $data['message'] ?? null,
            'status'       => 'new',
            'ip'           => $request->ip(),
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $this->notify($id, $data);

        $payload = json_encode(['ok' => true, 'id' => $id]);

        // Close the connection first so the SMTP send never delays the visitor.
        return response($payload, 201, [
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen($payload),
            'Connection'     => 'close',
        ]);
    }

    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');

        $leads = DB::table(self::TABLE)
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = DB::table(self::TABLE)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('ai-chat-pro::admin.sales-leads', [
            'leads'  => $leads,
            'counts' => $counts,
            'status' => $status,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:'.implode(',', self::STATUSES)]);

        DB::table(self::TABLE)->where('id', $id)->update([
            'status'     => $data['status'],
            'updated_at' => now(),
        ]);

        return back()->with('success', __('Lead updated.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table(self::TABLE)->where('id', $id)->delete();

        return back()->with('success', __('Lead deleted.'));
    }

    private function notify(int $id, array $data): void
    {
        $to = (string) (setting('ai_chat_pro_sales_email') ?: self::SALES_EMAIL);

        $body = implode("\n", [
            'New Enterprise sales inquiry',
            '',
            'Company: '.$data['companyName'],
            'Company size: '.$data['companySize'],
            'Name: '.$data['firstName'].' '.$data['lastName'],
            'Email: '.$data['email'],
            'Phone: '.$data['phone'],
            'Interested in: '.$data['interest'],
            '',
            'Business needs:',
            $data['message'] ?? '-',
        ]);

        dispatch(function () use ($to, $body, $data, $id) {
            try {
                Mail::raw($body, function ($mail) use ($to, $data) {
                    $mail->to($to)
                        ->replyTo($data['email'], $data['firstName'].' '.$data['lastName'])
                        ->subject('Enterprise inquiry: '.$data['companyName']);
                });

                DB::table(self::TABLE)->where('id', $id)->update(['emailed_at' => now()]);
            } catch (Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }
}
