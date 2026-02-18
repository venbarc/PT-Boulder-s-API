<?php

namespace App\Http\Controllers;

use App\Models\PteDemographic;
use App\Models\PteGeneralVisit;
use App\Models\PtePatientReport;
use App\Models\PteProviderRevenue;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function exportProviderRevenueCsv(): StreamedResponse
    {
        return $this->streamModelCsv(
            PteProviderRevenue::class,
            PteProviderRevenue::EXPORT_COLUMNS,
            'pt_boulder_provider_revenue_'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportGeneralVisitCsv(): StreamedResponse
    {
        return $this->streamModelCsv(
            PteGeneralVisit::class,
            PteGeneralVisit::EXPORT_COLUMNS,
            'pt_boulder_general_visit_'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportDemographicsCsv(): StreamedResponse
    {
        return $this->streamModelCsv(
            PteDemographic::class,
            PteDemographic::EXPORT_COLUMNS,
            'pt_boulder_demographics_'.now()->format('Ymd_His').'.csv'
        );
    }

    public function exportPatientReportCsv(): StreamedResponse
    {
        return $this->streamModelCsv(
            PtePatientReport::class,
            PtePatientReport::EXPORT_COLUMNS,
            'pt_boulder_patient_report_'.now()->format('Ymd_His').'.csv'
        );
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<int, string>  $columns
     */
    private function streamModelCsv(string $modelClass, array $columns, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($modelClass, $columns): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $columns);

            $modelClass::query()
                ->select($columns)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($handle, $columns): void {
                    foreach ($rows as $row) {
                        $line = [];
                        foreach ($columns as $column) {
                            $value = $row->{$column} ?? null;
                            if ($value instanceof \DateTimeInterface) {
                                $line[] = $column === 'date_of_service'
                                    ? $value->format('Y-m-d')
                                    : $value->format('Y-m-d H:i:s');

                                continue;
                            }

                            if (is_array($value)) {
                                $line[] = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';

                                continue;
                            }

                            $line[] = $value === null ? '' : (string) $value;
                        }

                        fputcsv($handle, $line);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
