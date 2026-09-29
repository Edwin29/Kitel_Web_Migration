#Requires -Version 5.1
<#
.SYNOPSIS
Preview or copy repository-owned KITEL extensions into a local Rhymix install.
.EXAMPLE
./scripts/sync-dev.ps1
.EXAMPLE
./scripts/sync-dev.ps1 -Apply
.DESCRIPTION
Dry-run is the default. Nothing is deleted. Existing local changes which differ
from the last successful sync are conflicts and block the entire apply.
#>
[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [switch]$Apply,
    [switch]$DryRun,
    [string]$RepositoryRoot,
    [string]$RhymixRoot = 'D:\rhymix_dev\www\rhymix',
    [string]$StatePath,
    [string]$BackupRoot
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ($Apply -and $DryRun) { throw 'Choose either -Apply or -DryRun.' }
if (-not $RepositoryRoot) { $RepositoryRoot = Split-Path -Parent $PSScriptRoot }
$repository = [IO.Path]::GetFullPath($RepositoryRoot).TrimEnd('\', '/')
$rhymix = [IO.Path]::GetFullPath($RhymixRoot).TrimEnd('\', '/')
if (-not (Test-Path -LiteralPath $repository -PathType Container)) { throw "Repository root does not exist: $repository" }
if (-not (Test-Path -LiteralPath $rhymix -PathType Container)) { throw "Rhymix root does not exist: $rhymix" }

$devRoot = Split-Path -Parent (Split-Path -Parent $rhymix)
if (-not $StatePath) { $StatePath = Join-Path $devRoot 'sync-state\kitel-sync-dev.json' }
if (-not $BackupRoot) { $BackupRoot = Join-Path $devRoot 'sync-backups' }
$StatePath = [IO.Path]::GetFullPath($StatePath)
$BackupRoot = [IO.Path]::GetFullPath($BackupRoot).TrimEnd('\', '/')

function Test-Within([string]$Path, [string]$Root) {
    return $Path.Equals($Root, [StringComparison]::OrdinalIgnoreCase) -or
        $Path.StartsWith($Root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)
}

if ((Test-Within $StatePath $rhymix) -or (Test-Within $BackupRoot $rhymix)) {
    throw 'State and backup paths must stay outside the Rhymix web root.'
}
if ((Test-Within $StatePath $repository) -or (Test-Within $BackupRoot $repository)) {
    throw 'State and backup paths must stay outside the repository.'
}

function Assert-NoReparse([string]$Path, [string]$Boundary) {
    $current = [IO.Path]::GetFullPath($Path)
    while (Test-Within $current $Boundary) {
        if (Test-Path -LiteralPath $current) {
            $item = Get-Item -LiteralPath $current -Force
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw "Refusing reparse point: $current"
            }
        }
        if ($current.Equals($Boundary, [StringComparison]::OrdinalIgnoreCase)) { break }
        $current = Split-Path -Parent $current
    }
}

Assert-NoReparse $repository $repository
Assert-NoReparse $rhymix $rhymix

function Get-ContentHash([string]$Path) {
    $bytes = [IO.File]::ReadAllBytes($Path)
    $extension = [IO.Path]::GetExtension($Path).ToLowerInvariant()
    $textExtensions = @('.php', '.html', '.css', '.js', '.xml', '.json', '.md', '.txt', '.svg', '.ps1')
    if ($textExtensions -contains $extension) {
        # Hash UTF-8 text with CRLF normalized, without altering either file.
        $content = [Text.Encoding]::UTF8.GetString($bytes)
        $content = $content.Replace("`r`n", "`n")
        $bytes = [Text.Encoding]::UTF8.GetBytes($content)
    }
    $sha = [Security.Cryptography.SHA256]::Create()
    try { return [BitConverter]::ToString($sha.ComputeHash($bytes)).Replace('-', '') }
    finally { $sha.Dispose() }
}

$mappings = New-Object System.Collections.Generic.List[object]
function Add-Mapping([string]$Source, [string]$Destination) {
    if (-not (Test-Path -LiteralPath $Source -PathType Container)) { return }
    $sourceFull = [IO.Path]::GetFullPath($Source).TrimEnd('\', '/')
    $destinationFull = [IO.Path]::GetFullPath($Destination).TrimEnd('\', '/')
    if (-not (Test-Within $sourceFull $repository) -or -not (Test-Within $destinationFull $rhymix)) {
        throw "Mapping escapes an allowed root: $Source -> $Destination"
    }
    Assert-NoReparse $sourceFull $repository
    Assert-NoReparse $destinationFull $rhymix
    $mappings.Add([pscustomobject]@{ Source = $sourceFull; Destination = $destinationFull })
}

foreach ($module in @(Get-ChildItem -LiteralPath (Join-Path $repository 'custom_modules') -Directory -ErrorAction SilentlyContinue)) {
    Add-Mapping $module.FullName (Join-Path $rhymix "modules\$($module.Name)")
}
foreach ($module in @(Get-ChildItem -LiteralPath (Join-Path $repository 'custom_skins') -Directory -ErrorAction SilentlyContinue)) {
    foreach ($skin in @(Get-ChildItem -LiteralPath $module.FullName -Directory)) {
        Add-Mapping $skin.FullName (Join-Path $rhymix "modules\$($module.Name)\skins\$($skin.Name)")
    }
}
foreach ($widget in @(Get-ChildItem -LiteralPath (Join-Path $repository 'custom_widgets') -Directory -ErrorAction SilentlyContinue)) {
    Add-Mapping $widget.FullName (Join-Path $rhymix "widgets\$($widget.Name)")
}
foreach ($layout in @(Get-ChildItem -LiteralPath (Join-Path $repository 'custom_layouts') -Directory -ErrorAction SilentlyContinue)) {
    Add-Mapping $layout.FullName (Join-Path $rhymix "layouts\$($layout.Name)")
}
if ($mappings.Count -eq 0) { throw 'No custom component directories found.' }

$oldHashes = @{}
if (Test-Path -LiteralPath $StatePath) {
    $state = Get-Content -LiteralPath $StatePath -Raw -Encoding UTF8 | ConvertFrom-Json
    if ($state.version -ne 1 -or $state.repository -ne $repository -or $state.rhymix -ne $rhymix) {
        throw "Sync state belongs to a different repository or Rhymix root: $StatePath"
    }
    foreach ($entry in $state.files.PSObject.Properties) { $oldHashes[$entry.Name] = [string]$entry.Value }
}

$plan = New-Object System.Collections.Generic.List[object]
$targetOnly = New-Object System.Collections.Generic.List[string]
foreach ($mapping in $mappings) {
    Write-Host "MAP $($mapping.Source) -> $($mapping.Destination)"
    $sourceNames = @{}
    foreach ($sourceFile in @(Get-ChildItem -LiteralPath $mapping.Source -Recurse -File -Force)) {
        Assert-NoReparse $sourceFile.FullName $repository
        $relative = $sourceFile.FullName.Substring($mapping.Source.Length + 1)
        $sourceNames[$relative] = $true
        $target = [IO.Path]::GetFullPath((Join-Path $mapping.Destination $relative))
        if (-not (Test-Within $target $mapping.Destination)) { throw "Unsafe target path: $target" }
        Assert-NoReparse $target $rhymix
        $sourceHash = Get-ContentHash $sourceFile.FullName
        $key = $target.Substring($rhymix.Length + 1).Replace('\', '/')
        $targetExists = Test-Path -LiteralPath $target -PathType Leaf
        $targetHash = if ($targetExists) { Get-ContentHash $target } else { $null }
        $action = if (-not $targetExists) { 'New' }
            elseif ($sourceHash -eq $targetHash) { 'Same' }
            elseif ($oldHashes.ContainsKey($key) -and $oldHashes[$key] -eq $targetHash) { 'Update' }
            else { 'Conflict' }
        $plan.Add([pscustomobject]@{ Key = $key; Source = $sourceFile.FullName; Target = $target; SourceHash = $sourceHash; Action = $action })
    }
    if (Test-Path -LiteralPath $mapping.Destination -PathType Container) {
        foreach ($targetFile in @(Get-ChildItem -LiteralPath $mapping.Destination -Recurse -File -Force)) {
            $relative = $targetFile.FullName.Substring($mapping.Destination.Length + 1)
            if (-not $sourceNames.ContainsKey($relative)) { $targetOnly.Add($targetFile.FullName) }
        }
    }
}

$plan | Sort-Object Key | Format-Table Action, Key -AutoSize | Out-Host
foreach ($item in $targetOnly) { Write-Host "TARGET-ONLY (kept): $item" }
$counts = $plan | Group-Object Action | Sort-Object Name
Write-Host ('SUMMARY ' + (($counts | ForEach-Object { "$($_.Name)=$($_.Count)" }) -join ' ') + " TargetOnly=$($targetOnly.Count)")

$execute = $Apply -and -not $WhatIfPreference
if (-not $execute) { Write-Host 'DRY-RUN: no files, backups, or state changed.'; return }
if (@($plan | Where-Object Action -eq 'Conflict').Count -gt 0) {
    throw 'Conflicts detected. Reconcile local changes before applying; no files were copied.'
}

$backupSet = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$backupSet = "$backupSet-$PID"
$newHashes = @{}
foreach ($entry in $plan) {
    if ($entry.Action -eq 'Same') {
        $newHashes[$entry.Key] = $entry.SourceHash
        continue
    }
    if (-not $PSCmdlet.ShouldProcess($entry.Target, "Copy $($entry.Source)")) { continue }
    $targetDir = Split-Path -Parent $entry.Target
    New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
    Assert-NoReparse $entry.Target $rhymix
    $currentHash = if (Test-Path -LiteralPath $entry.Target -PathType Leaf) { Get-ContentHash $entry.Target } else { $null }
    if ($entry.Action -eq 'New' -and $currentHash) { throw "Target appeared after preflight: $($entry.Key)" }
    if ($entry.Action -eq 'Update' -and $currentHash -ne $oldHashes[$entry.Key]) {
        throw "Target changed after preflight: $($entry.Key)"
    }
    $backup = $null
    if ($entry.Action -eq 'Update') {
        $backup = Join-Path (Join-Path $BackupRoot $backupSet) ($entry.Key.Replace('/', '\'))
        New-Item -ItemType Directory -Path (Split-Path -Parent $backup) -Force | Out-Null
        Copy-Item -LiteralPath $entry.Target -Destination $backup -ErrorAction Stop
    }
    try {
        Copy-Item -LiteralPath $entry.Source -Destination $entry.Target -Force -ErrorAction Stop
        $sourceRaw = (Get-FileHash -LiteralPath $entry.Source -Algorithm SHA256).Hash
        $targetRaw = (Get-FileHash -LiteralPath $entry.Target -Algorithm SHA256).Hash
        if ($sourceRaw -ne $targetRaw) { throw "Copy verification failed: $($entry.Key)" }
        $newHashes[$entry.Key] = $entry.SourceHash
    }
    catch {
        if ($backup) { Copy-Item -LiteralPath $backup -Destination $entry.Target -Force }
        else { Remove-Item -LiteralPath $entry.Target -Force -ErrorAction SilentlyContinue }
        throw
    }
}

$stateDir = Split-Path -Parent $StatePath
New-Item -ItemType Directory -Path $stateDir -Force | Out-Null
$stateObject = [ordered]@{ version = 1; repository = $repository; rhymix = $rhymix; files = $newHashes }
$tempState = "$StatePath.$PID.tmp"
try {
    [IO.File]::WriteAllText($tempState, ($stateObject | ConvertTo-Json -Depth 5), (New-Object Text.UTF8Encoding($false)))
    Move-Item -LiteralPath $tempState -Destination $StatePath -Force
}
finally { if (Test-Path -LiteralPath $tempState) { Remove-Item -LiteralPath $tempState -Force } }
Write-Host "APPLIED: state=$StatePath backupSet=$(Join-Path $BackupRoot $backupSet)"
