<?php

namespace App\Http\Controllers\Admin;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use Webklex\IMAP\Facades\Client;

class EmailController extends Controller
{
    /** All mails inbox */
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);

        $accounts = EmailAccount::where('is_active', true)->get();

        $query = EmailMessage::with('account')
            ->orderByDesc('received_at');

        if ($accountId = $request->get('account')) {
            $query->where('email_account_id', $accountId);
        }
        if ($folder = $request->get('folder')) {
            $query->where('folder', $folder);
        }
        if ($request->get('unread')) {
            $query->where('is_read', false);
        }
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('from_email', 'like', "%{$search}%")
                  ->orWhere('from_name', 'like', "%{$search}%")
                  ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate(30)->withQueryString();

        $stats = [
            'total'  => EmailMessage::count(),
            'unread' => EmailMessage::where('is_read', false)->count(),
        ];

        return view('admin.emails.index', compact('messages', 'accounts', 'stats'));
    }

    /** API: Poll for new emails in real-time */
    public function pollNewEmails(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);

        $since = $request->get('since');
        if (!$since) {
            return response()->json(['status' => 'error', 'message' => 'Missing since parameter'], 400);
        }

        $query = EmailMessage::with('account')
            ->where('created_at', '>', \Carbon\Carbon::parse($since))
            ->orderByDesc('received_at');

        // Apply filters to match the current inbox view
        if ($accountId = $request->get('account')) {
            $query->where('email_account_id', $accountId);
        }
        if ($folder = $request->get('folder')) {
            $query->where('folder', $folder);
        }
        if ($request->get('unread')) {
            $query->where('is_read', false);
        }
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('from_email', 'like', "%{$search}%")
                  ->orWhere('from_name', 'like', "%{$search}%");
            });
        }

        $newMessages = $query->get();
        $formatted = [];

        foreach ($newMessages as $msg) {
            $formatted[] = [
                'id'      => $msg->id,
                'sender'  => $msg->from_name ?: $msg->from_email,
                'subject' => $msg->subject,
                'html'    => view('admin.emails.partials.email-row', compact('msg'))->render(),
            ];
        }

        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'emails'    => $formatted,
        ]);
    }

    /** View single email */
    public function show(EmailMessage $email): View
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);

        $email->load('account');

        // Mark as read
        if (! $email->is_read) {
            $email->update(['is_read' => true]);
        }

        return view('admin.emails.show', compact('email'));
    }

    /** Toggle read/unread */
    public function toggleRead(EmailMessage $email): JsonResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);

        $email->update(['is_read' => ! $email->is_read]);

        return response()->json([
            'is_read' => $email->is_read,
            'message' => $email->is_read ? 'Marked as read.' : 'Marked as unread.',
        ]);
    }

    /** Toggle star */
    public function toggleStar(EmailMessage $email): JsonResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);

        $email->update(['is_starred' => ! $email->is_starred]);

        return response()->json([
            'is_starred' => $email->is_starred,
            'message'    => $email->is_starred ? 'Starred.' : 'Unstarred.',
        ]);
    }

    // ─── Email Account Management ────────────────────────────────────────────

    /** Delete multiple email messages */
    public function bulkDestroy(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);
        $ids = $request->input('ids', []);
        if (count($ids) > 0) {
            EmailMessage::whereIn('id', $ids)->delete();
        }
        return response()->json(['success' => true]);
    }

    /** Delete an email message */
    public function destroy(EmailMessage $email): \Illuminate\Http\JsonResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);
        $email->delete();
        return response()->json(['success' => true]);
    }

    /** Settings / Accounts view */
    public function accounts(): View
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);

        $accounts = EmailAccount::with('creator')->withCount('messages')->get();
        return view('admin.emails.accounts', compact('accounts'));
    }

    /** Store new email account */
    public function storeAccount(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-crm', 'sales-crm']), 403);

        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email_address'   => ['required', 'email', 'max:255'],
            'provider'        => ['required', 'in:imap,gmail_oauth,google_script'],
            'imap_host'       => ['nullable', 'string', 'max:255'],
            'imap_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', 'in:ssl,tls,none'],
            'smtp_host'       => ['nullable', 'string', 'max:255'],
            'smtp_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:ssl,tls,none'],
            'username'        => ['nullable', 'string', 'max:255'],
            'password'        => ['nullable', 'string'],
        ]);

        // ── IMAP: test connection before saving ──────────────────────────────
        if ($validated['provider'] === 'imap') {
            try {
                $client = Client::make([
                    'host'          => $validated['imap_host'],
                    'port'          => $validated['imap_port'],
                    'encryption'    => $validated['imap_encryption'] === 'none' ? false : $validated['imap_encryption'],
                    'validate_cert' => true,
                    'username'      => $validated['username'] ?: $validated['email_address'],
                    'password'      => $validated['password'],
                    'protocol'      => 'imap',
                ]);
                $client->connect();
            } catch (\Exception $e) {
                return back()->withInput()->withErrors(['password' => 'IMAP connection failed: ' . $e->getMessage()]);
            }
        }

        // ── Google Script: just save the secret (password field) ─────────────
        // No live test needed. The webhook will validate the secret when Google calls it.

        EmailAccount::create([
            ...$validated,
            'created_by' => auth()->id(),
            'is_active'  => true,
        ]);

        $method = $validated['provider'] === 'google_script' ? 'Google Apps Script' : 'IMAP';

        return redirect()->route('admin.emails.accounts')
            ->with('success', "✅ Account \"{$validated['name']}\" connected successfully via {$method}!");
    }

    /** Delete an email account */
    public function destroyAccount(EmailAccount $account): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin']), 403);

        $name = $account->name;
        $account->delete();

        return redirect()->route('admin.emails.accounts')
            ->with('success', "Account \"{$name}\" removed.");
    }
}
