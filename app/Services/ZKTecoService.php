<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    private $socket;
    private $ip;
    private $port;
    private $sessionId = 0;
    private $replyId = 0;

    // Command constants
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLE_DEVICE = 1002;
    const CMD_DISABLE_DEVICE = 1003;
    const CMD_GET_ATTENDANCE = 13;
    const CMD_CLEAR_ATTENDANCE = 14;
    const CMD_USER_WKM = 88;
    const CMD_USERTEMP_RRQ = 9;
    const CMD_GET_TIME = 201;
    const CMD_SET_TIME = 202;

    public function __construct(string $ip, int $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    /**
     * Connect to ZKTeco device
     */
    public function connect(): bool
    {
        try {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($this->socket === false) {
                throw new Exception("Failed to create socket: " . socket_strerror(socket_last_error()));
            }

            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 10, 'usec' => 0]);

            $command = $this->createCommand(self::CMD_CONNECT, '');
            $this->sendCommand($command);

            $reply = $this->receiveReply();

            if ($reply) {
                $this->sessionId = unpack('v', substr($reply, 4, 2))[1];
                Log::info("Connected to ZKTeco device at {$this->ip}:{$this->port}");
                return true;
            }

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
            if ($this->socket) {
                $command = $this->createCommand(self::CMD_EXIT, '');
                $this->sendCommand($command);
                socket_close($this->socket);
                Log::info("Disconnected from ZKTeco device");
            }
            return true;
        } catch (Exception $e) {
            Log::error("ZKTeco disconnect error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disable device (prevents users from using the device)
     */
    public function disableDevice(): bool
    {
        $command = $this->createCommand(self::CMD_DISABLE_DEVICE, '');
        $this->sendCommand($command);
        return $this->receiveReply() !== false;
    }

    /**
     * Enable device
     */
    public function enableDevice(): bool
    {
        $command = $this->createCommand(self::CMD_ENABLE_DEVICE, '');
        $this->sendCommand($command);
        return $this->receiveReply() !== false;
    }

    /**
     * Get attendance records from device
     */
    public function getAttendance(): array
    {
        try {
            $this->disableDevice();

            $command = $this->createCommand(self::CMD_GET_ATTENDANCE, '');
            $this->sendCommand($command);

            $attendanceData = [];
            $reply = $this->receiveReply();

            if ($reply) {
                $attendanceData = $this->parseAttendanceData($reply);
            }

            $this->enableDevice();

            return $attendanceData;
        } catch (Exception $e) {
            Log::error("Error getting attendance: " . $e->getMessage());
            $this->enableDevice();
            return [];
        }
    }

    /**
     * Clear attendance records from device
     */
    public function clearAttendance(): bool
    {
        try {
            $this->disableDevice();

            $command = $this->createCommand(self::CMD_CLEAR_ATTENDANCE, '');
            $this->sendCommand($command);
            $result = $this->receiveReply();

            $this->enableDevice();

            return $result !== false;
        } catch (Exception $e) {
            Log::error("Error clearing attendance: " . $e->getMessage());
            $this->enableDevice();
            return false;
        }
    }

    /**
     * Create command packet
     */
    private function createCommand(int $command, string $data = ''): string
    {
        $buf = pack('SSSS', $command, 0, $this->sessionId, $this->replyId) . $data;

        $checksum = $this->createChecksum($buf);
        $requestData = pack('SSSS', $command, $checksum, $this->sessionId, $this->replyId) . $data;

        $this->replyId = ($this->replyId + 1) % 0xFFFF;

        return $requestData;
    }

    /**
     * Create checksum for packet
     */
    private function createChecksum(string $buf): int
    {
        $checksum = 0;
        for ($i = 0; $i < strlen($buf); $i += 2) {
            if ($i == strlen($buf) - 1) {
                $checksum += ord($buf[$i]);
            } else {
                $checksum += unpack('v', substr($buf, $i, 2))[1];
            }
        }

        $checksum = $checksum & 0xFFFF;

        while ($checksum > 0xFFFF) {
            $checksum = ($checksum >> 16) + ($checksum & 0xFFFF);
        }

        return ~$checksum & 0xFFFF;
    }

    /**
     * Send command to device
     */
    private function sendCommand(string $command): void
    {
        socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
    }

    /**
     * Receive reply from device
     */
    private function receiveReply()
    {
        $reply = '';
        $from = '';
        $port = 0;

        $bytes = @socket_recvfrom($this->socket, $reply, 1024, 0, $from, $port);

        if ($bytes === false || $bytes === 0) {
            return false;
        }

        return $reply;
    }

    /**
     * Parse attendance data from device response
     */
    private function parseAttendanceData(string $data): array
    {
        $attendanceRecords = [];

        // Skip header (first 8 bytes)
        $data = substr($data, 8);

        // Each attendance record is typically 40 bytes
        $recordSize = 40;
        $totalRecords = floor(strlen($data) / $recordSize);

        for ($i = 0; $i < $totalRecords; $i++) {
            $record = substr($data, $i * $recordSize, $recordSize);

            if (strlen($record) < $recordSize) {
                continue;
            }

            $parsed = $this->parseAttendanceRecord($record);

            if ($parsed) {
                $attendanceRecords[] = $parsed;
            }
        }

        return $attendanceRecords;
    }

    /**
     * Parse individual attendance record
     */
    private function parseAttendanceRecord(string $record): ?array
    {
        try {
            // Extract user ID (first 9 bytes, null-terminated string)
            $userId = rtrim(substr($record, 0, 9), "\0");

            // Extract timestamp (bytes 27-30, 4 bytes)
            $timestamp = unpack('V', substr($record, 27, 4))[1];

            // Extract verify type (byte 26)
            $verifyType = ord($record[26]);

            // Extract status (byte 31)
            $status = ord($record[31]);

            return [
                'user_id' => $userId,
                'timestamp' => date('Y-m-d H:i:s', $timestamp),
                'verify_type' => $this->getVerifyTypeName($verifyType),
                'status' => $this->getStatusName($status),
                'raw_timestamp' => $timestamp,
            ];
        } catch (Exception $e) {
            Log::warning("Error parsing attendance record: " . $e->getMessage());
            return null;
        }
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
