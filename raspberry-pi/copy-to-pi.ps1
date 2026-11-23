# Copy kiosk files to the Raspberry Pi and install the desktop / autostart app.
# Run from Windows PowerShell. You will be asked for the Pi password a few times.
# The laptop and the Pi must be on the same Wi-Fi, with any VPN on the laptop turned off.
#
#   .\copy-to-pi.ps1                          find the Pi by name (raspberrypi.local)
#   .\copy-to-pi.ps1 -PiHost 192.168.100.50   use the Pi's IP address (on the Pi run: hostname -I)
#   .\copy-to-pi.ps1 -IPv6                    force IPv6, if the name only works over IPv6

param(
    [string]$PiHost = "raspberrypi.local",
    [string]$PiUser = "rogeliojr",
    [switch]$IPv6
)

$ErrorActionPreference = "Stop"

$piDir = $PSScriptRoot
$project = Split-Path $piDir -Parent
$facesRoot = Join-Path $project "storage\app\known_faces"
$remoteDir = "~/smartdtr-pi"

$files = @(
    (Join-Path $piDir "attendance_client.py"),
    (Join-Path $piDir ".env"),
    (Join-Path $piDir "start-kiosk.sh"),
    (Join-Path $piDir "install-kiosk.sh")
)

foreach ($f in $files) {
    if (-not (Test-Path -LiteralPath $f)) {
        Write-Host "Missing file: $f"
        exit 1
    }
}

$sshOpts = @()
if ($IPv6) { $sshOpts += "-6" }

function Stop-WithHelp([string]$message) {
    Write-Host ""
    Write-Host "STOPPED: $message"
    Write-Host ""
    Write-Host "Check that:"
    Write-Host "  - the Pi is switched on and connected to the same Wi-Fi as this laptop"
    Write-Host "  - any VPN on this laptop is turned off"
    Write-Host "  - SSH is enabled on the Pi (sudo raspi-config > Interface Options > SSH)"
    Write-Host ""
    Write-Host "If the name '$PiHost' does not work, run  hostname -I  on the Pi, then:"
    Write-Host "  .\copy-to-pi.ps1 -PiHost <the Pi's IP address>"
    exit 1
}

function Assert-Ok([string]$step) {
    if ($LASTEXITCODE -ne 0) {
        Stop-WithHelp "$step failed (exit code $LASTEXITCODE). The steps after it were not run."
    }
}

Write-Host "0/5  Looking for the Pi at $PiHost..."
$reachable = $false
try {
    $tcp = New-Object System.Net.Sockets.TcpClient
    $reachable = $tcp.ConnectAsync($PiHost, 22).Wait(5000) -and $tcp.Connected
    $tcp.Close()
} catch {
    $reachable = $false
}
if (-not $reachable) {
    Stop-WithHelp "Cannot reach the Pi at $PiHost. Nothing was copied."
}

Write-Host "1/5  Creating folders on the Pi..."
ssh @sshOpts -l $PiUser $PiHost "mkdir -p $remoteDir/known_faces"
Assert-Ok "Creating folders"

if (Test-Path -LiteralPath $facesRoot) {
    $personDirs = @(Get-ChildItem -LiteralPath $facesRoot -Directory | Where-Object { $_.Name -match '^\d+$' })
    Write-Host "2/5  Copying face photos for $($personDirs.Count) personnel..."
    if ($personDirs.Count -gt 0) {
        # Copy into known_faces/ so each folder lands at known_faces/<id>, not known_faces/<id>/<id>.
        scp.exe @sshOpts -r -o "User=$PiUser" @($personDirs | ForEach-Object { $_.FullName }) "${PiHost}:$remoteDir/known_faces/"
        Assert-Ok "Copying face photos"
    }
} else {
    Write-Host "2/5  No local face folder; Pi will download faces from the server."
}

Write-Host "3/5  Copying kiosk software..."
scp.exe @sshOpts -o "User=$PiUser" @files "${PiHost}:$remoteDir/"
Assert-Ok "Copying kiosk software"

Write-Host "4/5  Installing desktop icon + autostart on the Pi..."
ssh @sshOpts -l $PiUser $PiHost "chmod +x $remoteDir/start-kiosk.sh $remoteDir/install-kiosk.sh && rm -f $remoteDir/.api-endpoint && $remoteDir/install-kiosk.sh"
Assert-Ok "Installing on the Pi"

Write-Host "5/5  Done."
Write-Host ""
Write-Host "On the Pi LCD, double-click:  SMARTDTR Camera"
Write-Host "Or reboot the Pi after enabling Desktop Autologin in raspi-config."
