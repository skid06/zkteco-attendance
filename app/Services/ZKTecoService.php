<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\ZKTeco;

class ZKTecoService
{
    private ZKTeco $zk;
    private string $ip;
    private int $port;

    public function __construct(string $ip, int $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->zk = new ZKTeco($ip, $port);
    }

    /**
     * Connect to ZKTeco device
     */
    public function connect(): bool
    {
        try {
            $connected = $this->zk->connect();

            if ($connected) {
                Log::info("Connected to ZKTeco device at {$this->ip}:{$this->port}");
                return true;
            }

            Log::error("Failed to connect to ZKTeco device at {$this->ip}:{$this->port}");
            return false;
        } catch (Exception $e) {
            Log::error("ZKTeco connection error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect from device
     */
    public function disconnect(): bool
    {
        try {
            $this->zk->disconnect();
            Log::info("Disconnected from ZKTeco device");
            return true;
        } catch (Exception $e) {
            Log::error("ZKTeco disconnect error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get attendance records from device
     */
    public function getAttendance(): array
    {
        try {
            $this->zk->disableDevice();

            $rawAttendance = $this->zk->getAttendance();

            $this->zk->enableDevice();

            // Transform the raw data to match our expected format
            return $this->transformAttendanceData($rawAttendance);
        } catch (Exception $e) {
            Log::error("Error getting attendance: " . $e->getMessage());
            $this->zk->enableDevice();
            return [];
        }
    }

    /**
     * Clear attendance records from device
     */
    public function clearAttendance(): bool
    {
        try {
            $this->zk->disableDevice();

            $result = $this->zk->clearAttendance();

            $this->zk->enableDevice();

            if ($result) {
                Log::info("Cleared attendance records from device");
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error("Error clearing attendance: " . $e->getMessage());
            $this->zk->enableDevice();
            return false;
        }
    }

    /**
     * Transform attendance data from package format to our application format
     */
    private function transformAttendanceData(array $rawData): array
    {
        $transformed = [];

        foreach ($rawData as $record) {
            $transformed[] = [
                'user_id' => $record['id'] ?? $record['uid'] ?? 'Unknown',
                'timestamp' => $record['timestamp'] ?? date('Y-m-d H:i:s'),
                'verify_type' => $this->getVerifyTypeName($record['type'] ?? 0),
                'status' => $this->getStatusName($record['state'] ?? 0),
                'raw_timestamp' => strtotime($record['timestamp'] ?? 'now'),
            ];
        }

        return $transformed;
    }

    /**
     * Get verify type name
     */
    private function getVerifyTypeName(int $type): string
    {
        $types = [
            0 => 'Password',
            1 => 'Fingerprint',
            2 => 'Card',
            3 => 'Fingerprint and Password',
            4 => 'Fingerprint and Card',
            15 => 'Face',
        ];

        return $types[$type] ?? 'Unknown';
    }

    /**
     * Get status name
     */
    private function getStatusName(int $status): string
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
