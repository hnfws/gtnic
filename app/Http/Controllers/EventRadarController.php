<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;

class EventRadarController extends Controller
{
    public function index()
    {
        // Ambil event beserta data relasi tenants-nya
        $events = Event::with('tenants')
            ->orderByDesc('priority_score')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('EventRadar', [
            'events' => $events,
        ]);
    }
}
