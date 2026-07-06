<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship;
use App\Models\SponsorshipContact;
use App\Models\SponsorshipDeliverable;
use App\Models\SponsorshipDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SponsorshipController extends Controller
{
    // ── Sponsorships ─────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $sponsorships = Sponsorship::with(['contacts', 'deliverables', 'documents'])
            ->orderByDesc('created_at')
            ->get()
            ->map
            ->toApiArray()
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Sponsorships retrieved successfully.',
            'data'    => ['sponsorships' => $sponsorships],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeInput($request);
        $validated = $this->validateSponsorship($request);
        unset($validated['logo']);

        if ($request->hasFile('logo')) {
            $validated['logoUrl'] = $request->file('logo')->store('sponsorship_logos', 'public');
        }

        $validated['currency']  = $validated['currency'] ?? 'NGN';
        $validated['createdBy'] = Auth::id();

        $sponsorship = Sponsorship::create($validated);
        $sponsorship->load(['contacts', 'deliverables', 'documents']);

        return response()->json([
            'success' => true,
            'message' => 'Record created successfully.',
            'data'    => $sponsorship->toApiArray(),
        ], 201);
    }

    public function update(Request $request, int $sponsorshipId): JsonResponse
    {
        $sponsorship = Sponsorship::findOrFail($sponsorshipId);

        $this->normalizeInput($request);
        $validated = $this->validateSponsorship($request);
        unset($validated['logo']);

        if ($request->hasFile('logo')) {
            if ($sponsorship->logoUrl) {
                Storage::disk('public')->delete($sponsorship->logoUrl);
            }
            $validated['logoUrl'] = $request->file('logo')->store('sponsorship_logos', 'public');
        }

        $sponsorship->update($validated);
        $sponsorship->load(['contacts', 'deliverables', 'documents']);

        return response()->json([
            'success' => true,
            'message' => 'Record updated successfully.',
            'data'    => $sponsorship->toApiArray(),
        ]);
    }

    public function destroy(int $sponsorshipId): JsonResponse
    {
        $sponsorship = Sponsorship::with('documents')->findOrFail($sponsorshipId);

        if ($sponsorship->logoUrl) {
            Storage::disk('public')->delete($sponsorship->logoUrl);
        }
        foreach ($sponsorship->documents as $document) {
            if ($document->fileUrl) {
                Storage::disk('public')->delete($document->fileUrl);
            }
        }

        $sponsorship->delete(); // children cascade via FKs

        return response()->json([
            'success' => true,
            'message' => 'Record deleted successfully.',
        ]);
    }

    public function updateStatus(Request $request, int $sponsorshipId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('sponsorship.statuses')))],
        ]);

        $sponsorship = Sponsorship::findOrFail($sponsorshipId);
        $sponsorship->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Stage updated.',
        ]);
    }

    // ── Contacts ─────────────────────────────────────────────────────────

    public function storeContact(Request $request, int $sponsorshipId): JsonResponse
    {
        Sponsorship::findOrFail($sponsorshipId);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'role'  => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['sponsorshipId'] = $sponsorshipId;
        SponsorshipContact::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact added.',
        ], 201);
    }

    public function destroyContact(int $sponsorshipId, int $contactId): JsonResponse
    {
        $contact = SponsorshipContact::where('sponsorshipId', $sponsorshipId)
            ->where('contactId', $contactId)
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact removed.',
        ]);
    }

    // ── Deliverables ─────────────────────────────────────────────────────

    public function storeDeliverable(Request $request, int $sponsorshipId): JsonResponse
    {
        Sponsorship::findOrFail($sponsorshipId);

        $validated = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'dueDate' => ['nullable', 'date'],
        ]);

        SponsorshipDeliverable::create([
            'sponsorshipId' => $sponsorshipId,
            'title'         => $validated['title'],
            'status'        => 'pending',
            'dueDate'       => $validated['dueDate'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deliverable added.',
        ], 201);
    }

    public function updateDeliverable(Request $request, int $sponsorshipId, int $deliverableId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(config('sponsorship.deliverable_statuses'))],
        ]);

        $deliverable = SponsorshipDeliverable::where('sponsorshipId', $sponsorshipId)
            ->where('deliverableId', $deliverableId)
            ->firstOrFail();

        $deliverable->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Deliverable updated.',
        ]);
    }

    public function destroyDeliverable(int $sponsorshipId, int $deliverableId): JsonResponse
    {
        $deliverable = SponsorshipDeliverable::where('sponsorshipId', $sponsorshipId)
            ->where('deliverableId', $deliverableId)
            ->firstOrFail();

        $deliverable->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deliverable removed.',
        ]);
    }

    /**
     * Seed the selected tier's default benefits as deliverables, skipping any
     * that already exist (case-insensitive title match).
     */
    public function seedDeliverables(Request $request, int $sponsorshipId): JsonResponse
    {
        Sponsorship::findOrFail($sponsorshipId);

        $validated = $request->validate([
            'tier' => ['required', Rule::in(array_keys(config('sponsorship.tiers')))],
        ]);

        $benefits = config("sponsorship.tiers.{$validated['tier']}.benefits", []);

        $existing = SponsorshipDeliverable::where('sponsorshipId', $sponsorshipId)
            ->pluck('title')
            ->map(fn ($t) => strtolower($t))
            ->all();

        $created = 0;
        foreach ($benefits as $benefit) {
            if (in_array(strtolower($benefit), $existing, true)) {
                continue;
            }
            SponsorshipDeliverable::create([
                'sponsorshipId' => $sponsorshipId,
                'title'         => $benefit,
                'status'        => 'pending',
            ]);
            $created++;
        }

        return response()->json([
            'success' => true,
            'message' => "Added {$created} deliverable(s) from tier benefits.",
        ], 201);
    }

    // ── Documents ────────────────────────────────────────────────────────

    public function storeDocument(Request $request, int $sponsorshipId): JsonResponse
    {
        Sponsorship::findOrFail($sponsorshipId);

        $validated = $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(config('sponsorship.document_categories'))],
            'file'     => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
        ]);

        $path = $request->file('file')->store('sponsorship_documents', 'public');

        SponsorshipDocument::create([
            'sponsorshipId' => $sponsorshipId,
            'title'         => $validated['title'],
            'category'      => $validated['category'],
            'fileUrl'       => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded.',
        ], 201);
    }

    public function destroyDocument(int $sponsorshipId, int $documentId): JsonResponse
    {
        $document = SponsorshipDocument::where('sponsorshipId', $sponsorshipId)
            ->where('documentId', $documentId)
            ->firstOrFail();

        if ($document->fileUrl) {
            Storage::disk('public')->delete($document->fileUrl);
        }
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document removed.',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Empty multipart strings come through as "" — convert optional ones to
     * null so enum validation and nullable columns behave.
     */
    private function normalizeInput(Request $request): void
    {
        $request->merge([
            'tier'          => $request->filled('tier') ? $request->input('tier') : null,
            'website'       => $request->input('website') !== '' ? $request->input('website') : null,
            'description'   => $request->input('description') !== '' ? $request->input('description') : null,
            'invoiceNumber' => $request->input('invoiceNumber') !== '' ? $request->input('invoiceNumber') : null,
            'invoiceDate'   => $request->input('invoiceDate') !== '' ? $request->input('invoiceDate') : null,
            'currency'      => $request->filled('currency') ? $request->input('currency') : 'NGN',
        ]);
    }

    private function validateSponsorship(Request $request): array
    {
        return $request->validate([
            'eventId'          => ['nullable', 'integer'],
            'type'             => ['required', Rule::in(config('sponsorship.types'))],
            'organizationName' => ['required', 'string', 'max:255'],
            'website'          => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'tier'             => ['nullable', Rule::in(array_keys(config('sponsorship.tiers')))],
            'status'           => ['required', Rule::in(array_keys(config('sponsorship.statuses')))],
            'currency'         => ['nullable', 'string', 'max:8'],
            'agreedAmount'     => ['nullable', 'numeric', 'min:0'],
            'amountPaid'       => ['nullable', 'numeric', 'min:0'],
            'paymentStatus'    => ['required', Rule::in(config('sponsorship.payment_statuses'))],
            'invoiceNumber'    => ['nullable', 'string', 'max:255'],
            'invoiceDate'      => ['nullable', 'date'],
            'logo'             => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
        ]);
    }
}