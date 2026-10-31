<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()->with('actor:id,name');

        if ($request->filled('event')) {
            $logs->where('event', 'like', '%'.trim($request->string('event')).'%');
        }

        if ($request->filled('actor')) {
            $logs->whereHas('actor', fn ($query) => $query->where('name', 'like', '%'.trim($request->string('actor')).'%'));
        }

        if ($request->filled('date_from')) {
            $logs->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $logs->whereDate('created_at', '<=', $request->date('date_to'));
        }

        return view('admin.audit.index', ['logs' => $logs->latest()->paginate(25)->withQueryString()]);
    }
}