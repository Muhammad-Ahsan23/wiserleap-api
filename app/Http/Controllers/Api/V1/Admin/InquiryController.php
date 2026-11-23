<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InquiryIndexRequest;
use App\Http\Requests\Admin\InquiryReplyRequest;
use App\Http\Requests\Admin\InquiryStatusRequest;
use App\Http\Resources\InquiryResource;
use App\Mail\InquiryReplyMail;
use App\Models\Inquiry;
use App\Models\InquiryReply;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class InquiryController extends Controller
{
    /** Paginated inbox; `meta.counts` feeds the status tabs and the menu badge. */
    public function index(InquiryIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $base = Inquiry::query()->when($filters['search'] ?? null, function (Builder $q, string $search) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $q->where(fn (Builder $q) => $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('message', 'like', $like));
        });

        $counts = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $inquiries = (clone $base)
            ->select(['id', 'name', 'email', 'subject', 'budget', 'status', 'notified_at', 'read_at', 'replied_at', 'created_at', 'updated_at', DB::raw('SUBSTR(message, 1, 400) as message')])
            ->withCount('replies')
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->paginate($filters['perPage'] ?? 15)
            ->withQueryString();

        $statusCounts = collect(InquiryStatus::cases())->mapWithKeys(fn (InquiryStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]);

        return InquiryResource::collection($inquiries)->additional(['meta' => ['counts' => ['all' => (int) $counts->sum()] + $statusCounts->all()]]);
    }

    /** Opening a new inquiry marks it read. */
    public function show(Inquiry $inquiry): InquiryResource
    {
        $inquiry->markRead();

        return InquiryResource::make($inquiry->load('replies.user:id,name'))->detailed();
    }

    public function status(InquiryStatusRequest $request, Inquiry $inquiry): InquiryResource
    {
        $status = InquiryStatus::from($request->validated('status'));

        $inquiry->update([
            'status' => $status,
            'read_at' => $status === InquiryStatus::New ? null : ($inquiry->read_at ?? now()),
        ]);

        return InquiryResource::make($inquiry->load('replies.user:id,name'))->detailed();
    }

    /**
     * Email the visitor, then record the reply. If the mail server refuses,
     * nothing is recorded and the admin sees the error (the text stays in the form).
     */
    public function reply(InquiryReplyRequest $request, Inquiry $inquiry): JsonResponse
    {
        $data = $request->validated();
        $subject = Str::limit($data['subject'] ?? 'Re: '.($inquiry->subject ?: 'Your message to WiserLeap'), 200, '');

        try {
            Mail::to($inquiry->email, $inquiry->name)->send(new InquiryReplyMail($inquiry, $subject, $data['message']));
        } catch (Throwable $e) {
            Log::error('Inquiry reply email failed', ['inquiry_id' => $inquiry->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'The email could not be sent. Check the mail settings and try again.'], 502);
        }

        DB::transaction(function () use ($inquiry, $request, $subject, $data) {
            InquiryReply::create([
                'inquiry_id' => $inquiry->id,
                'user_id' => $request->user()->id,
                'subject' => $subject,
                'body' => $data['message'],
                'created_at' => now(),
            ]);
            $inquiry->update([
                'status' => InquiryStatus::Replied,
                'read_at' => $inquiry->read_at ?? now(),
                'replied_at' => now(),
            ]);
        });

        return InquiryResource::make($inquiry->load('replies.user:id,name'))->detailed()->response()->setStatusCode(201);
    }

    public function destroy(Inquiry $inquiry): JsonResponse
    {
        $inquiry->delete();

        return response()->json(['message' => 'Inquiry deleted.']);
    }
}
