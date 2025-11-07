# ZKTeco Attendance Sync Application

A Laravel console application that retrieves attendance data from ZKTeco biometric devices and sends it to a remote server via API.

## Features

- Connect to ZKTeco devices via TCP/IP
- Retrieve attendance records (check-in/check-out data)
- Send data to remote server in batches
- Clear device records after successful sync
- Connection testing mode
- Detailed logging and error handling
- Configurable batch sizes and retry logic

## Requirements

- PHP 8.1 or higher
- Composer
- Network access to ZKTeco device
- ZKTeco device must be configured for TCP/IP communication

## Installation

The application is already set up in `/Users/melchorvalencia/Documents/zkteco-attendance`

## Configuration

### 1. Configure ZKTeco Device

Update the `.env` file with your ZKTeco device details:

```env
# ZKTeco Device Configuration
ZKTECO_DEVICE_IP=192.168.1.201    # Your device IP address
ZKTECO_DEVICE_PORT=4370           # Default ZKTeco port
```

### 2. Configure Remote API

Set up your remote server API endpoint:

```env
# Remote API Configuration
REMOTE_API_URL=https://api.example.com/api/v1
REMOTE_API_KEY=your-api-key-here
REMOTE_API_TIMEOUT=30
```

### 3. Sync Settings (Optional)

```env
# Sync Settings
SYNC_BATCH_SIZE=100              # Records per batch
AUTO_CLEAR_DEVICE=false          # Auto-clear after sync
RETRY_FAILED_RECORDS=true
MAX_RETRIES=3
```

## Usage

Navigate to the project directory:

```bash
cd /Users/melchorvalencia/Documents/zkteco-attendance
```

### Basic Sync Command

Sync attendance data from device to remote server:

```bash
php artisan attendance:sync
```

### Test Connections

Test connectivity to both the ZKTeco device and remote API:

```bash
php artisan attendance:sync --test
```

### Sync with Auto-Clear

Sync data and clear records from device after successful sync:

```bash
php artisan attendance:sync --clear
```

### Custom Batch Size

Specify a custom batch size for sending records:

```bash
php artisan attendance:sync --batch-size=50
```

### Combined Options

```bash
php artisan attendance:sync --clear --batch-size=200
```

## Command Options

| Option | Description |
|--------|-------------|
| `--test` | Test connection without syncing data |
| `--clear` | Clear attendance records from device after successful sync |
| `--batch-size=N` | Number of records to send per batch (default: 100) |

## Scheduling (Optional)

To run the sync automatically, add it to Laravel's scheduler in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync every hour
    $schedule->command('attendance:sync --clear')
             ->hourly()
             ->withoutOverlapping();
}
```

Then set up a cron job:

```bash
* * * * * cd /Users/melchorvalencia/Documents/zkteco-attendance && php artisan schedule:run >> /dev/null 2>&1
```

## API Endpoint Requirements

Your remote server should have the following endpoints:

### 1. Health Check (Optional)

```
GET /health
Authorization: Bearer {API_KEY}
```

Response:
```json
{
    "status": "ok"
}
```

### 2. Sync Attendance Records

```
POST /attendance
Authorization: Bearer {API_KEY}
Content-Type: application/json
```

Request body:
```json
{
    "records": [
        {
            "user_id": "12345",
            "timestamp": "2025-11-06 09:30:00",
            "verify_type": "Fingerprint",
            "status": "Check In",
            "raw_timestamp": 1730880600
        }
    ],
    "device_info": {
        "ip": "192.168.1.201",
        "synced_at": "2025-11-06T09:35:00Z"
    }
}
```

Response:
```json
{
    "success": true,
    "message": "Records saved successfully",
    "records_received": 100
}
```

### 3. Get Sync Status (Optional)

```
GET /attendance/sync-status?device_ip=192.168.1.201
Authorization: Bearer {API_KEY}
```

## Data Structure

### Attendance Record Fields

Each attendance record contains:

- `user_id`: Employee/user ID from the device
- `timestamp`: Human-readable timestamp (Y-m-d H:i:s)
- `verify_type`: Authentication method (Fingerprint, Card, Face, Password, etc.)
- `status`: Attendance type (Check In, Check Out, Break Out, Break In, etc.)
- `raw_timestamp`: Unix timestamp

### Verify Types

- Password (0)
- Fingerprint (1)
- Card (2)
- Fingerprint and Password (3)
- Fingerprint and Card (4)
- Face (15)

### Status Types

- Check In (0)
- Check Out (1)
- Break Out (2)
- Break In (3)
- Overtime In (4)
- Overtime Out (5)

## Troubleshooting

### Cannot Connect to Device

1. Verify the device IP address is correct
2. Ensure the device is on the same network or accessible
3. Check if port 4370 is open on your firewall
4. Verify the device has TCP/IP communication enabled

Test connection:
```bash
php artisan attendance:sync --test
```

### API Connection Failed

1. Verify the API URL is correct
2. Check if the API key is valid
3. Ensure your server has internet access
4. Check API server logs for errors

### No Records Retrieved

- The device may have no attendance records stored
- Try creating a test attendance record on the device
- Check device logs for any errors

### Socket Errors

If you see socket-related errors, ensure PHP sockets extension is enabled:

```bash
php -m | grep sockets
```

If not installed:
```bash
brew install php --with-sockets
```

## Logs

Application logs are stored in `storage/logs/laravel.log`

View recent logs:
```bash
tail -f storage/logs/laravel.log
```

Enable debug logging in `.env`:
```env
ZKTECO_DEBUG_LOGGING=true
```

## File Structure

```
app/
├── Console/Commands/
│   └── SyncAttendanceData.php    # Main console command
├── Services/
│   ├── ZKTecoService.php         # ZKTeco device communication
│   └── RemoteApiService.php      # Remote API integration
config/
└── zkteco.php                     # Configuration file
```

## Security Considerations

1. Keep your API key secure in the `.env` file
2. Never commit `.env` to version control
3. Use HTTPS for remote API communication
4. Implement proper authentication on your remote API
5. Consider encrypting sensitive data in transit

## Support

For issues or questions:

1. Check the logs: `storage/logs/laravel.log`
2. Run connection test: `php artisan attendance:sync --test`
3. Verify device and API configurations in `.env`

## License

This application is provided as-is for attendance management purposes.
