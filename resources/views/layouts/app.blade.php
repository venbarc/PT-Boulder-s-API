<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PT Boulder - PtEverywhere Dashboard')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; }
        .navbar { background: #1e40af; color: white; padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .navbar h1 { font-size: 1.25rem; }
        .navbar a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.9rem; }
        .navbar a:hover, .navbar a.active { color: white; text-decoration: underline; }
        .nav-spacer { flex: 1; }
        .nav-btn {
            color: white !important;
            text-decoration: none !important;
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 6px;
            padding: 0.4rem 0.7rem;
            font-size: 0.85rem;
            background: rgba(255,255,255,0.08);
        }
        .nav-btn:hover { background: rgba(255,255,255,0.18); }
        .nav-export-select {
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 6px;
            padding: 0.38rem 0.55rem;
            font-size: 0.85rem;
            color: #1f2937;
            background: white;
            min-width: 250px;
        }
        .nav-export-select:focus { outline: 2px solid rgba(255,255,255,0.45); outline-offset: 1px; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 0.85rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: #1e40af; margin-top: 0.25rem; }
        .card { background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .card h2 { font-size: 1.1rem; margin-bottom: 1rem; color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 0.75rem 0.5rem; color: #6b7280; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
        td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #f3f4f6; }
        tr:hover td { background: #f9fafb; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-yellow { background: #fef3c7; color: #92400e; }
        .badge-gray { background: #f3f4f6; color: #4b5563; }
        .pagination { display: flex; gap: 0.25rem; justify-content: center; margin-top: 1rem; }
        .pagination a, .pagination span { padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; text-decoration: none; color: #374151; font-size: 0.85rem; }
        .pagination span.current { background: #1e40af; color: white; border-color: #1e40af; }
        .empty { text-align: center; padding: 2rem; color: #9ca3af; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>PT Boulder</h1>
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('pull-history') }}" class="{{ request()->routeIs('pull-history') ? 'active' : '' }}">Pull History</a>
        <div class="nav-spacer"></div>
        <select class="nav-export-select" onchange="if(this.value){ window.location=this.value; this.selectedIndex=0; }">
            <option value="">Download CSV...</option>
            <optgroup label="Appointment APIs">
                <option value="{{ route('export.available_blocks') }}">Available Blocks CSV</option>
            </optgroup>
            <optgroup label="Report APIs">
                <option value="{{ route('export.provider_revenue') }}">Provider Revenue CSV</option>
                <option value="{{ route('export.general_visit') }}">General Visit CSV</option>
                <option value="{{ route('export.patient_report') }}">Patient Report CSV</option>
                <option value="{{ route('export.demographics') }}">Demographics CSV</option>
            </optgroup>
            <optgroup label="Master Data APIs">
                <option value="{{ route('export.therapists') }}">Therapists CSV</option>
                <option value="{{ route('export.locations') }}">Locations CSV</option>
                <option value="{{ route('export.services') }}">Services CSV</option>
                <option value="{{ route('export.master_patients') }}">Patients CSV</option>
                <option value="{{ route('export.master_users') }}">Users CSV</option>
            </optgroup>
        </select>
    </nav>

    <div class="container">
        @yield('content')
    </div>
</body>
</html>
