<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemoteApiService
{
    private $baseUrl;
    private $apiKey;
    private $timeout;

    public function __construct()
    {
        $this->baseUrl = config('zkteco.remote_api_url');
        $this->apiKey = config('zkteco.remote_api_key');
        $this->timeout = config('zkteco.api_timeout', 30);
    }

    /**
     * Send attendance records to remote server
     *
     * @param array $records
     * @return array
     */
    public function sendAttendanceRecords(array $records): array
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
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/attendance', [
                    'records' => $records,
                    'device_info' => [
                        'ip' => config('zkteco.device_ip'),
                        'synced_at' => now()->toIso8601String(),
                    ],
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
     * Send batch of attendance records (chunks)
     *
     * @param array $records
     * @param int $batchSize
     * @return array
     */
    public function sendAttendanceRecordsInBatches(array $records, int $batchSize = 100): array
    {
        $totalRecords = count($records);
        $batches = array_chunk($records, $batchSize);
        $totalSent = 0;
        $totalFailed = 0;
        $results = [];

        Log::info("Sending {$totalRecords} records in " . count($batches) . " batches");

        foreach ($batches as $index => $batch) {
            Log::info("Sending batch " . ($index + 1) . " of " . count($batches));

            $result = $this->sendAttendanceRecords($batch);
            $results[] = $result;

            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];

            // Optional: Add delay between batches to avoid overwhelming the server
            if ($index < count($batches) - 1) {
                usleep(500000); // 0.5 second delay
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
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/health');

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
     *
     * @return array|null
     */
    public function getSyncStatus(): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/attendance/sync-status', [
                    'device_ip' => config('zkteco.device_ip'),
                ]);

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
