<?php

namespace App\Services;

use App\Models\PtePullHistory;

class PtePullHistoryLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function start(string $commandName, array $context = []): PtePullHistory
    {
        return PtePullHistory::create([
            'command_name' => $commandName,
            'source_key' => $context['source_key'] ?? null,
            'status' => 'running',
            'triggered_by' => $context['triggered_by'] ?? 'manual',
            'started_at' => now(),
            'from_date' => $context['from_date'] ?? null,
            'to_date' => $context['to_date'] ?? null,
            'from_year' => $context['from_year'] ?? null,
            'to_year' => $context['to_year'] ?? null,
            'options' => $context['options'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function finish(PtePullHistory $history, array $metrics = [], ?\Throwable $error = null): void
    {
        $completedAt = now();
        $startedAt = $history->started_at ?? $completedAt;
        $duration = max(0, $startedAt->diffInSeconds($completedAt));

        $created = (int) ($metrics['created'] ?? 0);
        $updated = (int) ($metrics['updated'] ?? 0);
        $status = $metrics['status'] ?? ($error ? 'failed' : 'success');

        $history->update([
            'status' => $status,
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'fetched_count' => (int) ($metrics['fetched'] ?? 0),
            'created_count' => $created,
            'updated_count' => $updated,
            'upserted_count' => (int) ($metrics['upserted'] ?? ($created + $updated)),
            'failed_chunks' => isset($metrics['failed_chunks']) ? (int) $metrics['failed_chunks'] : null,
            'error_message' => $error?->getMessage(),
        ]);
    }
}
