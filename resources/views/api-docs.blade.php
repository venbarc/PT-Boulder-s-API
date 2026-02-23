@extends('layouts.app')
@section('title', 'API Docs - PT Boulder')

@section('content')
    <style>
        .docs-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .docs-card h2 {
            margin: 0 0 0.75rem;
            font-size: 1.1rem;
            color: #374151;
        }
        .docs-meta {
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }
        #swagger-ui {
            border-top: 1px solid #e5e7eb;
            padding-top: 0.75rem;
        }
        .swagger-ui .topbar {
            display: none;
        }
    </style>

    <div class="docs-card">
        <h2>API Swagger</h2>
        <div class="docs-meta">
            Auth endpoint: <code>POST /api/auth/token</code> |
            Protected endpoint: <code>GET /api/report/general-visit</code>
        </div>
        <div id="swagger-ui"></div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: "{{ route('api-docs.openapi') }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                layout: "BaseLayout"
            });
        };
    </script>
@endsection
