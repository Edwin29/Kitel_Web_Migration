#Requires -Version 5.1
[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$fixture = Join-Path $env:TEMP ('kitel-sync-test-' + [guid]::NewGuid().ToString('N'))
$repository = Join-Path $fixture 'repo'
$rhymix = Join-Path $fixture 'runtime'
$state = Join-Path $fixture 'state\sync.json'
$backups = Join-Path $fixture 'backups'
$sync = Join-Path $PSScriptRoot 'sync-dev.ps1'

function Assert([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
}

New-Item -ItemType Directory -Path @(
    (Join-Path $repository 'custom_modules\calendar'),
    (Join-Path $repository 'custom_skins\board\demo'),
    (Join-Path $repository 'custom_widgets\hero'),
    (Join-Path $repository 'custom_layouts\kitel_site'),
    $rhymix
) -Force | Out-Null

$source = Join-Path $repository 'custom_modules\calendar\view.html'
$eolSource = Join-Path $repository 'custom_modules\calendar\eol.html'
$eolTarget = Join-Path $rhymix 'modules\calendar\eol.html'
[IO.File]::WriteAllText($source, "one`r`n")
[IO.File]::WriteAllText($eolSource, "same`r`n")
New-Item -ItemType Directory -Path (Split-Path -Parent $eolTarget) -Force | Out-Null
[IO.File]::WriteAllText($eolTarget, "same`n")
[IO.File]::WriteAllText((Join-Path $repository 'custom_skins\board\demo\list.html'), 'skin')
[IO.File]::WriteAllText((Join-Path $repository 'custom_widgets\hero\hero.php'), 'widget')
[IO.File]::WriteAllText((Join-Path $repository 'custom_layouts\kitel_site\layout.html'), 'layout')
$options = @{ RepositoryRoot = $repository; RhymixRoot = $rhymix; StatePath = $state; BackupRoot = $backups }
$target = Join-Path $rhymix 'modules\calendar\view.html'

$null = & $sync @options 6>&1
Assert (-not (Test-Path $target)) 'Dry-run copied a file.'
Assert (-not (Test-Path $state)) 'Dry-run wrote state.'

$null = & $sync @options -Apply 6>&1
Assert (Test-Path $target) 'Module mapping failed.'
Assert (Test-Path (Join-Path $rhymix 'modules\board\skins\demo\list.html')) 'Skin mapping failed.'
Assert (Test-Path (Join-Path $rhymix 'widgets\hero\hero.php')) 'Widget mapping failed.'
Assert (Test-Path (Join-Path $rhymix 'layouts\kitel_site\layout.html')) 'Layout mapping failed.'
Assert (Test-Path $state) 'Sync state was not written.'
Assert (([IO.File]::ReadAllText($eolTarget)) -eq "same`n") 'EOL-only difference caused overwrite.'

[IO.File]::WriteAllText($source, "two`n")
$null = & $sync @options -DryRun 6>&1
Assert (([IO.File]::ReadAllText($target)) -eq "one`r`n") 'Dry-run updated target.'
$null = & $sync @options -Apply 6>&1
Assert (([IO.File]::ReadAllText($target)) -eq "two`n") 'Update failed.'
$backupFiles = @(Get-ChildItem -LiteralPath $backups -Recurse -File)
Assert ($backupFiles.Count -eq 1) 'Expected one backup.'
Assert (([IO.File]::ReadAllText($backupFiles[0].FullName)) -eq "one`r`n") 'Backup content is wrong.'

[IO.File]::WriteAllText($target, "local edit`n")
[IO.File]::WriteAllText($source, "three`n")
$blocked = $false
try { $null = & $sync @options -Apply 6>&1 } catch { $blocked = $true }
Assert $blocked 'Local drift was not blocked.'
Assert (([IO.File]::ReadAllText($target)) -eq "local edit`n") 'Local drift was overwritten.'

$targetOnly = Join-Path $rhymix 'modules\calendar\local-only.txt'
[IO.File]::WriteAllText($targetOnly, 'keep')
$null = & $sync @options -DryRun 6>&1
Assert (Test-Path $targetOnly) 'Target-only file was deleted.'

Write-Host 'PASS: default dry-run, four mappings, EOL normalization, update, backup, conflict, target-only preservation.'
Write-Host "Fixture: $fixture"
