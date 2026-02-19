@extends('layouts.app')
@section('title', 'Dashboard - PT Boulder')

@section('content')
    <style>
        .dash-toolbar { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .dash-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .dash-source-panels { display: grid; gap: 0.75rem; width: 100%; }
        .dash-source-panel {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem;
            background: #f9fafb;
        }
        .dash-source-panel-title {
            font-size: 0.8rem;
            color: #4b5563;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .dash-source-tabs { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .dash-tab {
            display: inline-block;
            text-decoration: none;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.45rem 0.7rem;
            font-size: 0.85rem;
            color: #374151;
            background: #f9fafb;
        }
        .dash-tab.active {
            border-color: #1e40af;
            color: #1e40af;
            background: #eff6ff;
            font-weight: 600;
        }
        .dash-export-btn {
            display: inline-block;
            text-decoration: none;
            border: 1px solid #1e40af;
            border-radius: 6px;
            padding: 0.45rem 0.7rem;
            font-size: 0.85rem;
            color: #1e40af;
            background: #eff6ff;
        }
        .dash-table-wrap { overflow-x: auto; }
        .dash-meta { color: #6b7280; font-size: 0.85rem; margin-bottom: 0.75rem; }
    </style>

    <div class="card">
        <h2>{{ $sourceLabel }} (PT Boulder API)</h2>

        <div class="dash-toolbar">
            <div class="dash-source-panels">
                @foreach($sourcePanels as $panel)
                    <div class="dash-source-panel">
                        <div class="dash-source-panel-title">{{ $panel['label'] }}</div>
                        <div class="dash-source-tabs">
                            @foreach($panel['sources'] as $panelSource => $panelSourceConfig)
                                <a
                                    href="{{ route('dashboard', ['source' => $panelSource, 'per_page' => $perPage]) }}"
                                    class="dash-tab {{ $source === $panelSource ? 'active' : '' }}"
                                >
                                    {{ $panelSourceConfig['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="dash-actions">
                <label for="per_page">Rows per page</label>
                <select id="per_page" onchange="window.location=this.value">
                    <option value="{{ route('dashboard', ['source' => $source, 'per_page' => 25]) }}" {{ $perPage === 25 ? 'selected' : '' }}>25</option>
                    <option value="{{ route('dashboard', ['source' => $source, 'per_page' => 50]) }}" {{ $perPage === 50 ? 'selected' : '' }}>50</option>
                    <option value="{{ route('dashboard', ['source' => $source, 'per_page' => 100]) }}" {{ $perPage === 100 ? 'selected' : '' }}>100</option>
                </select>
                <a href="{{ route($exportRoute) }}" class="dash-export-btn">Export {{ $sourceLabel }} CSV</a>
            </div>
        </div>

        <div class="dash-meta">Total rows: {{ number_format($totalRows) }}</div>

        @if($rows->count() === 0)
            <div class="empty">No {{ strtolower($sourceLabel) }} data found yet. Run: <code>{{ $syncCommand }}</code></div>
        @else
            <div class="dash-table-wrap">
                <table>
                    <thead>
                        <tr>
                            @foreach($headers as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach($row as $value)
                                    <td>{{ $value !== '' ? $value : '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $rows->links('pagination') }}</div>
        @endif
    </div>
@endsection
