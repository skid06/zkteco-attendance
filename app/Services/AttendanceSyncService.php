<?php

namespace App\Services;

use App\Contracts\AttendanceDeviceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttendanceSyncService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('attendance.remote_api.url');
        $this->apiKey = config('attendance.remote_api.key');
        $this->timeout = config('attendance.remote_api.timeout', 30);
    }

    /**
     * Send attendance records to remote server
     */
    public function sendAttendanceRecords(array $records, array $deviceInfo = []): array
    {
        try {
            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'No records to send',
                    'sent' => 0,
                    'failed' => 0,
                ];
            }

            Log::info("Sending " . count($records) . " attendance records to remote server");

            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl, [
                    'records' => $records,
                    'device_info' => array_merge($deviceInfo, [
                        'synced_at' => now()->toIso8601String(),
                    ]),
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info("Successfully sent attendance records", [
                    'total' => count($records),
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Records sent successfully',
                    'sent' => count($records),
                    'failed' => 0,
                    'response' => $data,
                ];
            }

            Log::error("Failed to send attendance records", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send records: ' . $response->status(),
                'sent' => 0,
                'failed' => count($records),
                'error' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::error("Exception while sending attendance records: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'sent' => 0,
                'failed' => count($records),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send attendance records in batches
     */
    public function sendAttendanceRecordsInBatches(array $records, array $deviceInfo = [], ?int $batchSize = null): array
    {
        $batchSize = $batchSize ?? config('attendance.sync.batch_size', 100);
        $totalRecords = count($records);
        $batches = array_chunk($records, $batchSize);
        $totalSent = 0;
        $totalFailed = 0;
        $results = [];

        Log::info("Sending {$totalRecords} records in " . count($batches) . " batches");

        foreach ($batches as $index => $batch) {
            Log::info("Sending batch " . ($index + 1) . " of " . count($batches));

            $result = $this->sendAttendanceRecords($batch, $deviceInfo);
            $results[] = $result;

            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];

            if ($index < count($batches) - 1) {
                usleep(500000); // 0.5 second delay between batches
            }
        }

        return [
            'success' => $totalFailed === 0,
            'message' => "Sent {$totalSent} records, {$totalFailed} failed",
            'total_records' => $totalRecords,
            'sent' => $totalSent,
            'failed' => $totalFailed,
            'batches' => count($batches),
            'batch_results' => $results,
        ];
    }

    /**
     * Test connection to remote API
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(10)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl);

            if ($response->successful()) {
                Log::info("Remote API connection test successful");
                return true;
            }

            Log::warning("Remote API connection test failed: " . $response->status());
            return false;
        } catch (Exception $e) {
            Log::error("Remote API connection test exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get sync status from remote server
     */
    public function getSyncStatus(array $deviceInfo = []): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/attendance/sync-status', $deviceInfo);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error("Error getting sync status: " . $e->getMessage());
            return null;
        }
    }
}
