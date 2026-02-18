<?php

namespace App\Http\Controllers;

use App\Models\PteDemographic;
use App\Models\PteGeneralVisit;
use App\Models\PtePatientReport;
use App\Models\PteProviderRevenue;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $source = (string) $request->query('source', 'provider_revenue');
        if (! in_array($source, ['provider_revenue', 'general_visit', 'demographics', 'patient_report'], true)) {
            $source = 'provider_revenue';
        }

        $config = $this->sourceConfig($source);
        $modelClass = $config['model'];
        $columns = $config['columns'];

        $rowsQuery = $modelClass::query()->select($columns);
        $orderColumn = $config['order_column'] ?? null;
        if (is_string($orderColumn) && in_array($orderColumn, $columns, true)) {
            $rowsQuery->orderByDesc($orderColumn);
        }

        $rows = $rowsQuery
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map(function ($row) use ($columns): array {
                $line = [];
                foreach ($columns as $column) {
                    $value = $row->{$column} ?? null;
                    if ($value instanceof \DateTimeInterface) {
                        $line[] = $column === 'date_of_service'
                            ? $value->format('Y-m-d')
                            : $value->format('Y-m-d H:i:s');
                    } elseif (is_array($value)) {
                        $line[] = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
                    } else {
                        $line[] = $value === null ? '' : (string) $value;
                    }
                }

                return $line;
            })->values()
        );

        return view('dashboard', [
            'source' => $source,
            'sourceLabel' => $config['label'],
            'exportRoute' => $config['export_route'],
            'syncCommand' => $config['sync_command'],
            'headers' => $columns,
            'rows' => $rows,
            'perPage' => $perPage,
            'totalRows' => $rows->total(),
        ]);
    }

    /**
     * @return array{model: class-string, columns: array<int, string>, label: string, export_route: string, sync_command: string, order_column: string}
     */
    private function sourceConfig(string $source): array
    {
        if ($source === 'demographics') {
            return [
                'model' => PteDemographic::class,
                'columns' => PteDemographic::EXPORT_COLUMNS,
                'label' => 'Demographics',
                'export_route' => 'export.demographics',
                'sync_command' => 'php artisan pte:sync-demographics --from-year=2024 --to-year=2025',
                'order_column' => 'year_of_birth',
            ];
        }

        if ($source === 'patient_report') {
            return [
                'model' => PtePatientReport::class,
                'columns' => PtePatientReport::EXPORT_COLUMNS,
                'label' => 'Patient Report',
                'export_route' => 'export.patient_report',
                'sync_command' => 'php artisan pte:sync-patient-report --from=2024-01-01 --to=2025-12-31',
                'order_column' => 'registration_date_str',
            ];
        }

        if ($source === 'general_visit') {
            return [
                'model' => PteGeneralVisit::class,
                'columns' => PteGeneralVisit::EXPORT_COLUMNS,
                'label' => 'General Visit',
                'export_route' => 'export.general_visit',
                'sync_command' => 'php artisan pte:sync-general-visit --from=2024-01-01 --to=2025-12-31',
                'order_column' => 'date_of_service',
            ];
        }

        return [
            'model' => PteProviderRevenue::class,
            'columns' => PteProviderRevenue::EXPORT_COLUMNS,
            'label' => 'Provider Revenue',
            'export_route' => 'export.provider_revenue',
            'sync_command' => 'php artisan pte:sync-provider-revenue --from=2024-01-01 --to=2025-12-31',
            'order_column' => 'date_of_service',
        ];
    }
}
