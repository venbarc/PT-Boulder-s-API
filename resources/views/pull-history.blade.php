@extends('layouts.app')
@section('title', 'Pull History - PT Boulder')

@section('content')
    <style>
        .history-toolbar { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .history-meta { color: #6b7280; font-size: 0.85rem; }
        .history-table-wrap { overflow-x: auto; }
        .hist-status {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .hist-status.success { background: #d1fae5; color: #065f46; }
        .hist-status.partial { background: #fef3c7; color: #92400e; }
        .hist-status.failed { background: #fee2e2; color: #991b1b; }
        .hist-status.running { background: #dbeafe; color: #1e40af; }
    </style>

    <div class="card">
        <h2>Pull History</h2>

        @php($historyCount = $pullHistories instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $pullHistories->total() : $pullHistories->count())

        <div class="history-toolbar">
            <div class="history-meta">Total runs: {{ number_format($historyCount) }}</div>
            @if($pullHistories instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div>
                    <label for="per_page">Rows per page</label>
                    <select id="per_page" onchange="window.location=this.value">
                        <option value="{{ route('pull-history', ['per_page' => 25]) }}" {{ $perPage === 25 ? 'selected' : '' }}>25</option>
                        <option value="{{ route('pull-history', ['per_page' => 50]) }}" {{ $perPage === 50 ? 'selected' : '' }}>50</option>
                        <option value="{{ route('pull-history', ['per_page' => 100]) }}" {{ $perPage === 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
            @endif
        </div>

        @if($pullHistories->count() === 0)
            <div class="empty">No pull history yet. Run any <code>pte:sync-*</code> command.</div>
        @else
            <div class="history-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Command</th>
                            <th>Status</th>
                            <th>Triggered By</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Duration (s)</th>
                            <th>Fetched</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Upserted</th>
                            <th>Failed Chunks</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pullHistories as $history)
                            <tr>
                                <td>{{ $history->command_name }}</td>
                                <td>
                                    @php($status = strtolower((string) $history->status))
                                    <span class="hist-status {{ in_array($status, ['success', 'partial', 'failed', 'running'], true) ? $status : 'running' }}">
                                        {{ $history->status }}
                                    </span>
                                </td>
                                <td>{{ $history->triggered_by ?? '-' }}</td>
                                <td>{{ $history->started_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>{{ $history->completed_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>{{ $history->duration_seconds ?? '-' }}</td>
                                <td>{{ $history->fetched_count }}</td>
                                <td>{{ $history->created_count }}</td>
                                <td>{{ $history->updated_count }}</td>
                                <td>{{ $history->upserted_count }}</td>
                                <td>{{ $history->failed_chunks ?? '-' }}</td>
                                <td>{{ $history->error_message ? \Illuminate\Support\Str::limit($history->error_message, 120) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($pullHistories instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div class="pagination">{{ $pullHistories->links('pagination') }}</div>
            @endif
        @endif
    </div>
@endsection
