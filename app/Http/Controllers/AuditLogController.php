<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Establishment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);
        $filters = $this->validatedFilters($request);
        $logs = $this->queryLogs($filters)->with(['actor:id,name', 'establishment:id,code,name'])
            ->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        return Inertia::render('audit/index', [
            'logs' => $logs,
            'establishments' => Establishment::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'event' => $filters['event'] ?? '',
                'establishment_id' => $filters['establishment_id'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->is_active && $request->user()->is_central_admin, 403);
        $filters = $this->validatedFilters($request);

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Date et heure', 'Action', 'Résultat', 'Utilisateur', 'Identifiant saisi', 'Adresse IP', 'Navigateur', 'Établissement', 'Objet', 'Résumé'], ';', '"', '\\');

            $this->queryLogs($filters)->with(['actor:id,name', 'establishment:id,code,name'])->orderBy('created_at')->orderBy('id')->chunk(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    $metadata = $log->metadata ?? [];
                    fputcsv($output, array_map($this->csvSafe(...), [
                        $log->created_at?->toDateTimeString(),
                        $log->event,
                        $metadata['result'] ?? '',
                        $log->actor?->name ?? '',
                        $metadata['login_identifier'] ?? '',
                        $metadata['ip'] ?? '',
                        $metadata['user_agent'] ?? '',
                        $log->establishment ? $log->establishment->code.' — '.$log->establishment->name : 'Administration centrale',
                        $log->subject_type.($log->subject_id ? ' #'.$log->subject_id : ''),
                        $log->summary,
                    ]), ';', '"', '\\');
                }
            });

            fclose($output);
        }, 'sigec-journal-audit-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'event' => ['nullable', 'string', Rule::in(['auth.login', 'auth.logout', 'auth.failed', 'detainee.admitted', 'detainee.transferred', 'detainee.cell_moved', 'cell.overcrowding', 'cell.overcrowding_resolved', 'detainee.released', 'incident.reported', 'incident.updated', 'visitor.created', 'visit.scheduled', 'visit.arrived', 'visit.completed', 'visit.cancelled', 'stock.movement', 'medical.consultation'])],
            'establishment_id' => ['nullable', 'integer', Rule::exists('establishments', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    private function queryLogs(array $filters)
    {
        return AuditLog::query()
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['establishment_id'] ?? null, fn ($query, $id) => $query->where('establishment_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function csvSafe(mixed $value): string
    {
        $text = (string) ($value ?? '');
        return preg_match('/^\s*[=+\-@]/u', $text) ? "'".$text : $text;
    }
}
