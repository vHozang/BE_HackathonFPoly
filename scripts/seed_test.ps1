param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 3306,
    [string]$Database = "HRM_SYSTEM",
    [string]$Username = "root",
    [string]$Password = ""
)

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$sqlFile = Join-Path $scriptDir "seed_test.sql"

if (-not (Test-Path $sqlFile)) {
    Write-Error "Missing seed file: $sqlFile"
    exit 1
}

$mysql = Get-Command mysql -ErrorAction SilentlyContinue
if (-not $mysql) {
    Write-Error "mysql CLI not found in PATH."
    exit 1
}

$args = @(
    "-h", $HostName,
    "-P", $Port,
    "-u", $Username,
    "--default-character-set=utf8mb4"
)

if ($Password -ne "") {
    $env:MYSQL_PWD = $Password
}

$args += @($Database, "-e", "source $sqlFile")

Write-Host "Running seed_test.sql on $Database..."
& mysql @args
$env:MYSQL_PWD = $null
if ($LASTEXITCODE -ne 0) {
    Write-Error "Seed script failed."
    exit $LASTEXITCODE
}

Write-Host "Seed script completed."
