<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessScrapedEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ScraperController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location_name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'price' => 'nullable|numeric',
            'instagram_post_url' => 'required|url',
            'registration_link' => 'nullable|url',
            'account_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._]+$/'],
            'posted_at' => 'nullable|date',
            'deadline_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        ProcessScrapedEvent::dispatchSync($validator->validated());

        return response()->json([
            'status' => 'processed',
            'message' => 'Event selesai diproses.',
        ], 200);
    }

    public function knownPosts(Request $request)
    {
        $validated = $request->validate([
            'account' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._]+$/'],
        ]);

        $accountName = strtolower($validated['account']);
        $allowedAccounts = config('services.scraper.accounts', []);
        abort_unless(in_array($accountName, $allowedAccounts, true), 422, 'Akun scraper tidak dikenal.');

        $shortcodes = DB::table('scraper_posts')
            ->where('account_name', $accountName)
            ->where(function ($query) {
                $query->whereNull('posted_at')
                    ->orWhere('posted_at', '>=', now()->subMonths(3));
            })
            ->pluck('shortcode');

        return response()->json(['shortcodes' => $shortcodes]);
    }

    public function markPostsSeen(Request $request)
    {
        $validated = $request->validate([
            'account' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._]+$/'],
            'posts' => 'required|array|max:500',
            'posts.*.shortcode' => 'required|string|max:100',
            'posts.*.posted_at' => 'nullable|date',
        ]);

        $accountName = strtolower($validated['account']);
        $allowedAccounts = config('services.scraper.accounts', []);
        abort_unless(in_array($accountName, $allowedAccounts, true), 422, 'Akun scraper tidak dikenal.');

        $now = now();
        $rows = collect($validated['posts'])
            ->unique('shortcode')
            ->map(fn (array $post) => [
                'account_name' => $accountName,
                'shortcode' => $post['shortcode'],
                'posted_at' => $post['posted_at'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('scraper_posts')->upsert(
                $rows,
                ['account_name', 'shortcode'],
                ['posted_at', 'updated_at']
            );
        }

        DB::table('scraper_posts')
            ->where('account_name', $accountName)
            ->whereNotNull('posted_at')
            ->where('posted_at', '<', now()->subMonths(3))
            ->delete();

        return response()->json(['status' => 'saved', 'count' => count($rows)]);
    }
}
