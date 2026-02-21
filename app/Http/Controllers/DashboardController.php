<?php

namespace App\Http\Controllers;

use App\Models\PteAvailableBlock;
use App\Models\PteDemographic;
use App\Models\PteGeneralVisit;
use App\Models\PteLocation;
use App\Models\PteMasterPatient;
use App\Models\PteMasterUser;
use App\Models\PtePatientReport;
use App\Models\PteProviderRevenue;
use App\Models\PtePullHistory;
use App\Models\PteService;
use App\Models\PteTherapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $sources = $this->sourceConfigMap();
        $source = (string) $request->query('source', 'provider_revenue');
        if (! array_key_exists($source, $sources)) {
            $source = 'provider_revenue';
        }

        $config = $sources[$source];
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
            'sourcePanels' => $this->buildSourcePanels($sources),
            'headers' => $columns,
            'rows' => $rows,
            'perPage' => $perPage,
            'totalRows' => $rows->total(),
        ]);
    }

    public function pullHistory(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 50;
        }

        $pullHistories = Schema::hasTable('pte_pull_histories')
            ? PtePullHistory::query()->orderByDesc('id')->paginate($perPage)->withQueryString()
            : collect();

        return view('pull-history', [
            'pullHistories' => $pullHistories,
            'perPage' => $perPage,
        ]);
    }

    /**
     * @return array<string, array{
     *     model: class-string,
     *     columns: array<int, string>,
     *     label: string,
     *     export_route: string,
     *     sync_command: string,
     *     order_column: string,
     *     panel: 'report'|'masterdata'
     * }>
     */
    private function sourceConfigMap(): array
    {
        return [
            'provider_revenue' => [
                'model' => PteProviderRevenue::class,
                'columns' => PteProviderRevenue::EXPORT_COLUMNS,
                'label' => 'Provider Revenue',
                'export_route' => 'export.provider_revenue',
                'sync_command' => 'php artisan pte:sync-provider-revenue --from=2024-01-01 --to=2025-12-31',
                'order_column' => 'date_of_service',
                'panel' => 'report',
            ],
            'general_visit' => [
                'model' => PteGeneralVisit::class,
                'columns' => PteGeneralVisit::EXPORT_COLUMNS,
                'label' => 'General Visit',
                'export_route' => 'export.general_visit',
                'sync_command' => 'php artisan pte:sync-general-visit --from=2024-01-01 --to=2025-12-31',
                'order_column' => 'date_of_service',
                'panel' => 'report',
            ],
            'patient_report' => [
                'model' => PtePatientReport::class,
                'columns' => PtePatientReport::EXPORT_COLUMNS,
                'label' => 'Patient Report',
                'export_route' => 'export.patient_report',
                'sync_command' => 'php artisan pte:sync-patient-report --from=2024-01-01 --to=2025-12-31',
                'order_column' => 'registration_date_str',
                'panel' => 'report',
            ],
            'demographics' => [
                'model' => PteDemographic::class,
                'columns' => PteDemographic::EXPORT_COLUMNS,
                'label' => 'Demographics',
                'export_route' => 'export.demographics',
                'sync_command' => 'php artisan pte:sync-demographics',
                'order_column' => 'year_of_birth',
                'panel' => 'report',
            ],
            'available_blocks' => [
                'model' => PteAvailableBlock::class,
                'columns' => PteAvailableBlock::EXPORT_COLUMNS,
                'label' => 'Available Blocks',
                'export_route' => 'export.available_blocks',
                'sync_command' => 'php artisan pte:sync-available-blocks --from=YYYY-MM-DD --to=YYYY-MM-DD --all-therapists=1',
                'order_column' => 'start_datetime',
                'panel' => 'appointment',
            ],
            'therapists' => [
                'model' => PteTherapist::class,
                'columns' => PteTherapist::EXPORT_COLUMNS,
                'label' => 'Therapists',
                'export_route' => 'export.therapists',
                'sync_command' => 'php artisan pte:sync-therapists',
                'order_column' => 'therapist_name',
                'panel' => 'masterdata',
            ],
            'locations' => [
                'model' => PteLocation::class,
                'columns' => PteLocation::EXPORT_COLUMNS,
                'label' => 'Locations',
                'export_route' => 'export.locations',
                'sync_command' => 'php artisan pte:sync-locations',
                'order_column' => 'location_name',
                'panel' => 'masterdata',
            ],
            'services' => [
                'model' => PteService::class,
                'columns' => PteService::EXPORT_COLUMNS,
                'label' => 'Services',
                'export_route' => 'export.services',
                'sync_command' => 'php artisan pte:sync-services',
                'order_column' => 'service_name',
                'panel' => 'masterdata',
            ],
            'master_patients' => [
                'model' => PteMasterPatient::class,
                'columns' => PteMasterPatient::EXPORT_COLUMNS,
                'label' => 'Patients',
                'export_route' => 'export.master_patients',
                'sync_command' => 'php artisan pte:sync-master-patients',
                'order_column' => 'full_name',
                'panel' => 'masterdata',
            ],
            'master_users' => [
                'model' => PteMasterUser::class,
                'columns' => PteMasterUser::EXPORT_COLUMNS,
                'label' => 'Users',
                'export_route' => 'export.master_users',
                'sync_command' => 'php artisan pte:sync-master-users',
                'order_column' => 'full_name',
                'panel' => 'masterdata',
            ],
        ];
    }

    /**
     * @param  array<string, array{
     *     label: string,
     *     panel: 'report'|'masterdata'
     * }>  $sources
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     sources: array<string, array{label: string}>
     * }>
     */
    private function buildSourcePanels(array $sources): array
    {
        $panelLabels = [
            'appointment' => 'Appointment APIs',
            'report' => 'Report APIs',
            'masterdata' => 'Master Data APIs',
        ];

        $panels = [];
        foreach ($panelLabels as $panelKey => $panelLabel) {
            $panelSources = [];
            foreach ($sources as $sourceKey => $config) {
                if (($config['panel'] ?? '') !== $panelKey) {
                    continue;
                }

                $panelSources[$sourceKey] = [
                    'label' => $config['label'],
                ];
            }

            if ($panelSources === []) {
                continue;
            }

            $panels[] = [
                'key' => $panelKey,
                'label' => $panelLabel,
                'sources' => $panelSources,
            ];
        }

        return $panels;
    }
}
