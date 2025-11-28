<?php

namespace App\Services\Devices;

use App\Contracts\AttendanceDeviceInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DahuaDevice implements AttendanceDeviceInterface
{
    private string $connection;
    private string $table;
    private int $fetchMinutes;
    private bool $connected = false;

    public function __construct(array $config)
    {
        $this->connection = $config['connection'] ?? 'local_attendance';
        $this->table = $config['table'] ?? 'attendance_records';
        $this->fetchMinutes = $config['fetch_minutes'] ?? 10;
    }

    /**
     * Test database connection
     */
    public function connect(): bool
    {
        try {
            DB::connection($this->connection)->getPdo();
            $this->connected = true;
            Log::info("Connected to Dahua local database: {$this->connection}");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to connect to Dahua database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect (no-op for database connections)
     */
    public function disconnect(): bool
    {
        $this->connected = false;
        Log::info("Disconnected from Dahua database");
        return true;
    }

    /**
     * Get attendance records from local database (last N minutes based on AttendanceDateTime epoch)
     */
    public function getAttendance(): array
    {
        try {
            $epochThreshold = now()->subMinutes($this->fetchMinutes)->timestamp;

            $rawRecords = DB::connection($this->connection)
                ->table($this->table)
                ->where('AttendanceDateTime', '>=', $epochThreshold)
                ->orderBy('AttendanceDateTime', 'desc')
                ->get();

            Log::info("Retrieved {count} records from Dahua database (last {minutes} minutes)", [
                'count' => $rawRecords->count(),
                'minutes' => $this->fetchMinutes,
                'threshold' => $epochThreshold,
            ]);

            return $this->transformAttendanceData($rawRecords->toArray());

        } catch (Exception $e) {
            Log::error("Error getting attendance from Dahua database: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear attendance (not applicable for Dahua - data managed externally)
     */
    public function clearAttendance(): bool
    {
        Log::warning("Clear attendance not supported for Dahua device (database managed externally)");
        return false;
    }

    /**
     * Test connection to database
     */
    public function testConnection(): bool
    {
        return $this->connect();
    }

    /**
     * Get device information
     */
    public function getDeviceInfo(): array
    {
        try {
            $latestRecord = DB::connection($this->connection)
                ->table($this->table)
                ->orderBy('AttendanceDateTime', 'desc')
                ->first();

            return [
                'type' => 'dahua',
                'connection' => $this->connection,
                'table' => $this->table,
                'fetch_minutes' => $this->fetchMinutes,
                'connected' => $this->connected,
                'latest_epoch' => $latestRecord->AttendanceDateTime ?? null,
                'latest_time' => $latestRecord->AttendanceDateTime
                    ? date('Y-m-d H:i:s', $latestRecord->AttendanceDateTime)
                    : null,
            ];
        } catch (Exception $e) {
            Log::error("Error getting Dahua device info: " . $e->getMessage());
            return [
                'type' => 'dahua',
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Transform database records to standardized attendance format
     */
    private function transformAttendanceData(array $rawData): array
    {
        $transformed = [];

        foreach ($rawData as $record) {
            // Convert stdClass to array if needed
            $record = (array) $record;

            $transformed[] = [
                // Standardized fields
                'user_id' => $record['PersonID'] ?? $record['user_id'] ?? 'Unknown',
                'timestamp' => isset($record['AttendanceDateTime']) && $record['AttendanceDateTime'] > 0
                    ? date('Y-m-d H:i:s', $record['AttendanceDateTime'])
                    : ($record['AttendanceTime'] ?? date('Y-m-d H:i:s')),
                'verify_type' => $this->getVerifyTypeName($record['AttendanceMethod'] ?? $record['verify_type'] ?? 0),
                'status' => $this->getStatusName($record['AttendanceState'] ?? $record['status'] ?? 0),
                'raw_timestamp' => $record['AttendanceDateTime'] ?? strtotime($record['AttendanceTime'] ?? 'now'),

                // Additional Dahua-specific fields
                'person_id' => $record['PersonID'] ?? null,
                'person_name' => $record['PersonName'] ?? null,
                'person_card_no' => $record['PersonCardNo'] ?? null,
                'attendance_datetime' => $record['AttendanceDateTime'] ?? null,
                'attendance_state' => $record['AttendanceState'] ?? null,
                'attendance_method' => $record['AttendanceMethod'] ?? null,
                'device_ip_address' => $record['DeviceIPAddress'] ?? null,
                'device_name' => $record['DeviceName'] ?? null,
                'snapshots_path' => $record['SnapshotsPath'] ?? null,
                'handler' => $record['Handler'] ?? null,
                'attendance_utc_time' => $record['AttendanceUtcTime'] ?? null,
                'remarks' => $record['Remarks'] ?? null,
            ];
        }

        return $transformed;
    }

    /**
     * Get verify type name
     */
    private function getVerifyTypeName($type): string
    {
        $types = [
            0 => 'Password',
            1 => 'Fingerprint',
            2 => 'Card',
            3 => 'Face',
            4 => 'Fingerprint and Password',
            5 => 'Card and Password',
            6 => 'Face and Password',
        ];

        return $types[$type] ?? 'Unknown';
    }

    /**
     * Get status name
     */
    private function getStatusName($status): string
    {
        $statuses = [
            0 => 'Check In',
            1 => 'Check Out',
            2 => 'Break Out',
            3 => 'Break In',
            4 => 'Overtime In',
            5 => 'Overtime Out',
        ];

        return $statuses[$status] ?? 'Unknown';
    }
}
