<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Alert::with(['repository', 'pullRequest', 'alertRule']);
        
        if ($request->filled('repository_id')) {
            $query->where('repository_id', $request->repository_id);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $query->latest();
        
        $alerts = $query->paginate($request->get('per_page', 20));
        
        return AlertResource::collection($alerts);
    }
    
    public function acknowledge(Alert $alert)
    {
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by_user_id' => auth()->id(),
        ]);
        
        return new AlertResource($alert);
    }
    
    public function resolve(Request $request, Alert $alert)
    {
        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_user_id' => auth()->id(),
            'resolution_notes' => $request->get('notes'),
        ]);
        
        return new AlertResource($alert);
    }
}