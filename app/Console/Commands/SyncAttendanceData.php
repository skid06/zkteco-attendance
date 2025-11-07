<?php

namespace App\Console\Commands;

use App\Services\RemoteApiService;
use App\Services\ZKTecoService;
use Illuminate\Console\Command;
use Exception;

class SyncAttendanceData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync
                            {--clear : Clear attendance records from device after sync}
                            {--batch-size=100 : Number of records to send per batch}
                            {--test : Test connection without syncing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync attendance data from ZKTeco device to remote server';

    private ZKTecoService $zktecoService;
    private RemoteApiService $apiService;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('╔═══════════════════════════════════════════════════════════╗');
        $this->info('║       ZKTeco Attendance Data Sync                         ║');
        $this->info('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();

        try {
            // Initialize services
            $this->initializeServices();

            // Test connection mode
            if ($this->option('test')) {
                return $this->testConnections();
            }

            // Connect to device
            $this->info('🔌 Connecting to ZKTeco device...');
            if (!$this->zktecoService->connect()) {
                $this->error('❌ Failed to connect to ZKTeco device');
                return Command::FAILURE;
            }
            $this->info('✅ Connected successfully');
            $this->newLine();

            // Get attendance records
            $this->info('📊 Fetching attendance records from device...');
            $records = $this->zktecoService->getAttendance();

            if (empty($records)) {
                $this->warn('⚠️  No attendance records found on device');
                $this->zktecoService->disconnect();
                return Command::SUCCESS;
            }

            $this->info("✅ Retrieved " . count($records) . " attendance records");
            $this->newLine();

            // Display sample records
            $this->displaySampleRecords($records);

            // Confirm before sending
            if (!$this->confirm('Do you want to send these records to the remote server?', true)) {
                $this->warn('Sync cancelled by user');
                $this->zktecoService->disconnect();
                return Command::SUCCESS;
            }

            // Send to remote server
            $this->info('📤 Sending attendance records to remote server...');
            $batchSize = (int) $this->option('batch-size');

            $result = $this->apiService->sendAttendanceRecordsInBatches($records, $batchSize);

            $this->newLine();
            $this->displaySyncResults($result);

            // Clear device records if requested and sync was successful
            if ($this->option('clear') && $result['success']) {
                if ($this->confirm('Clear attendance records from device?', true)) {
                    $this->info('🗑️  Clearing attendance records from device...');
                    if ($this->zktecoService->clearAttendance()) {
                        $this->info('✅ Attendance records cleared successfully');
                    } else {
                        $this->error('❌ Failed to clear attendance records');
                    }
                }
            }

            // Disconnect from device
            $this->zktecoService->disconnect();

            $this->newLine();
            $this->info('✅ Sync process completed');

            return $result['success'] ? Command::SUCCESS : Command::FAILURE;

        } catch (Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            if (isset($this->zktecoService)) {
                $this->zktecoService->disconnect();
            }

            return Command::FAILURE;
        }
    }

    /**
     * Initialize services
     */
    private function initializeServices(): void
    {
        $deviceIp = config('zkteco.device_ip');
        $devicePort = config('zkteco.device_port');

        if (empty($deviceIp)) {
            throw new Exception('ZKTeco device IP not configured. Please set ZKTECO_DEVICE_IP in .env');
        }

        $this->zktecoService = new ZKTecoService($deviceIp, $devicePort);
        $this->apiService = new RemoteApiService();
    }

    /**
     * Test connections to device and remote API
     */
    private function testConnections(): int
    {
        $this->info('🧪 Testing connections...');
        $this->newLine();

        // Test ZKTeco device connection
        $this->info('Testing ZKTeco device connection...');
        if ($this->zktecoService->connect()) {
            $this->info('✅ ZKTeco device connection successful');
            $this->zktecoService->disconnect();
        } else {
            $this->error('❌ ZKTeco device connection failed');
        }

        $this->newLine();

        // Test remote API connection
        $this->info('Testing remote API connection...');
        if ($this->apiService->testConnection()) {
            $this->info('✅ Remote API connection successful');
        } else {
            $this->error('❌ Remote API connection failed');
        }

        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Display sample attendance records
     */
    private function displaySampleRecords(array $records): void
    {
        $this->info('Sample records (showing first 5):');

        $headers = ['User ID', 'Timestamp', 'Verify Type', 'Status'];
        $sampleData = array_slice($records, 0, 5);

        $rows = array_map(function ($record) {
            return [
                $record['user_id'],
                $record['timestamp'],
                $record['verify_type'],
                $record['status'],
            ];
        }, $sampleData);

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Display sync results
     */
    private function displaySyncResults(array $result): void
    {
        if ($result['success']) {
            $this->info('✅ Sync completed successfully!');
        } else {
            $this->error('❌ Sync completed with errors');
        }

        $this->newLine();
        $this->info("📊 Sync Summary:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Records', $result['total_records'] ?? 0],
                ['Successfully Sent', $result['sent']],
                ['Failed', $result['failed']],
                ['Batches', $result['batches'] ?? 1],
            ]
        );
    }
}
