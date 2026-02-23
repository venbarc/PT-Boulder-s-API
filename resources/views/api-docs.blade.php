@extends('layouts.app')
@section('title', 'API Docs - PT Boulder')

@section('content')
    <style>
        .container {
            max-width: 100% !important;
            padding: 0 1.25rem !important;
        }
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
        .api-docs-root .swagger-ui {
            font-family: Menlo, Consolas, Monaco, "Courier New", monospace;
            color: #111827;
        }
        .api-docs-root .swagger-ui table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .api-docs-root .swagger-ui table th,
        .api-docs-root .swagger-ui table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 0.45rem 0.55rem;
            vertical-align: top;
        }
        .api-docs-root .swagger-ui .model-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .api-docs-root .swagger-ui .opblock .opblock-summary-description {
            font-weight: 600;
        }
        .api-docs-root .swagger-ui .responses-wrapper .response-col_status {
            min-width: 80px;
        }
        .swagger-ui .topbar {
            display: none;
        }
    </style>

    <div class="docs-card api-docs-root">
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
                docExpansion: 'list',
                defaultModelsExpandDepth: -1,
                defaultModelExpandDepth: 1,
                defaultModelRendering: 'example',
                displayRequestDuration: true,
                showExtensions: false,
                showCommonExtensions: false,
                filter: true,
                tryItOutEnabled: true,
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                layout: "BaseLayout"
            });
        };
    </script>
@endsection
