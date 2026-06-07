Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$WORK_DIR   = "E:\Gear-Gao\trumain.co.th"
$GH_ACTIONS = "https://github.com/semitt2019-cloud/truman-shop/actions"

$C_BG     = [System.Drawing.Color]::FromArgb(15,  15,  15)
$C_BG2    = [System.Drawing.Color]::FromArgb(23,  23,  23)
$C_BG3    = [System.Drawing.Color]::FromArgb(8,   8,   8)
$C_RED    = [System.Drawing.Color]::FromArgb(192, 39,  45)
$C_WHITE  = [System.Drawing.Color]::FromArgb(230, 230, 230)
$C_GRAY   = [System.Drawing.Color]::FromArgb(120, 120, 120)
$C_GREEN  = [System.Drawing.Color]::FromArgb(46,  204, 154)
$C_YELLOW = [System.Drawing.Color]::FromArgb(255, 210, 80)
$C_ERR    = [System.Drawing.Color]::FromArgb(255, 90,  90)
$C_BORDER = [System.Drawing.Color]::FromArgb(40,  40,  40)

function Run-Git([string[]]$GitArgs) {
    Push-Location $WORK_DIR
    $out = & git @GitArgs 2>&1 | Out-String
    $code = $LASTEXITCODE
    Pop-Location
    return @{ Out = $out.Trim(); Code = $code }
}

$form = New-Object System.Windows.Forms.Form
$form.Text            = "2M RACING - Deploy to truman.co.th"
$form.ClientSize      = New-Object System.Drawing.Size(620, 510)
$form.BackColor       = $C_BG
$form.ForeColor       = $C_WHITE
$form.StartPosition   = "CenterScreen"
$form.FormBorderStyle = "FixedSingle"
$form.MaximizeBox     = $false
$form.Font            = New-Object System.Drawing.Font("Segoe UI", 9)

$pnlHead = New-Object System.Windows.Forms.Panel
$pnlHead.Location  = New-Object System.Drawing.Point(0, 0)
$pnlHead.Size      = New-Object System.Drawing.Size(620, 52)
$pnlHead.BackColor = $C_RED
$form.Controls.Add($pnlHead)

$lblTitle = New-Object System.Windows.Forms.Label
$lblTitle.Text      = "  2M RACING  |  Deploy to truman.co.th"
$lblTitle.Dock      = "Fill"
$lblTitle.ForeColor = [System.Drawing.Color]::White
$lblTitle.Font      = New-Object System.Drawing.Font("Segoe UI", 13, [System.Drawing.FontStyle]::Bold)
$lblTitle.TextAlign = "MiddleLeft"
$pnlHead.Controls.Add($lblTitle)

$lblStat = New-Object System.Windows.Forms.Label
$lblStat.Text      = "Checking..."
$lblStat.Location  = New-Object System.Drawing.Point(20, 62)
$lblStat.Size      = New-Object System.Drawing.Size(580, 18)
$lblStat.ForeColor = $C_GRAY
$lblStat.Font      = New-Object System.Drawing.Font("Consolas", 8)
$form.Controls.Add($lblStat)

$lblMsgHint = New-Object System.Windows.Forms.Label
$lblMsgHint.Text      = "Commit Message"
$lblMsgHint.Location  = New-Object System.Drawing.Point(20, 90)
$lblMsgHint.Size      = New-Object System.Drawing.Size(580, 16)
$lblMsgHint.ForeColor = $C_GRAY
$form.Controls.Add($lblMsgHint)

$txtMsg = New-Object System.Windows.Forms.TextBox
$txtMsg.Location    = New-Object System.Drawing.Point(20, 108)
$txtMsg.Size        = New-Object System.Drawing.Size(452, 32)
$txtMsg.Text        = "Update"
$txtMsg.BackColor   = $C_BG2
$txtMsg.ForeColor   = $C_WHITE
$txtMsg.BorderStyle = "FixedSingle"
$txtMsg.Font        = New-Object System.Drawing.Font("Segoe UI", 10)
$form.Controls.Add($txtMsg)

$btnDeploy = New-Object System.Windows.Forms.Button
$btnDeploy.Text     = "DEPLOY  >"
$btnDeploy.Location = New-Object System.Drawing.Point(480, 106)
$btnDeploy.Size     = New-Object System.Drawing.Size(120, 34)
$btnDeploy.BackColor = $C_RED
$btnDeploy.ForeColor = [System.Drawing.Color]::White
$btnDeploy.FlatStyle = "Flat"
$btnDeploy.FlatAppearance.BorderSize = 0
$btnDeploy.Font     = New-Object System.Drawing.Font("Segoe UI", 10, [System.Drawing.FontStyle]::Bold)
$form.Controls.Add($btnDeploy)

$sep = New-Object System.Windows.Forms.Label
$sep.Location  = New-Object System.Drawing.Point(20, 150)
$sep.Size      = New-Object System.Drawing.Size(580, 1)
$sep.BackColor = $C_BORDER
$form.Controls.Add($sep)

$lblLog = New-Object System.Windows.Forms.Label
$lblLog.Text      = "Output"
$lblLog.Location  = New-Object System.Drawing.Point(20, 158)
$lblLog.Size      = New-Object System.Drawing.Size(200, 16)
$lblLog.ForeColor = $C_GRAY
$form.Controls.Add($lblLog)

$txtLog = New-Object System.Windows.Forms.RichTextBox
$txtLog.Location    = New-Object System.Drawing.Point(20, 176)
$txtLog.Size        = New-Object System.Drawing.Size(580, 278)
$txtLog.BackColor   = $C_BG3
$txtLog.ForeColor   = $C_WHITE
$txtLog.ReadOnly    = $true
$txtLog.Font        = New-Object System.Drawing.Font("Consolas", 9)
$txtLog.BorderStyle = "FixedSingle"
$txtLog.ScrollBars  = "Vertical"
$form.Controls.Add($txtLog)

$btnStatus = New-Object System.Windows.Forms.Button
$btnStatus.Text     = "git status"
$btnStatus.Location = New-Object System.Drawing.Point(20, 464)
$btnStatus.Size     = New-Object System.Drawing.Size(120, 28)
$btnStatus.BackColor = $C_BG2
$btnStatus.ForeColor = $C_GRAY
$btnStatus.FlatStyle = "Flat"
$btnStatus.FlatAppearance.BorderColor = $C_BORDER
$form.Controls.Add($btnStatus)

$btnActions = New-Object System.Windows.Forms.Button
$btnActions.Text     = "GitHub Actions"
$btnActions.Location = New-Object System.Drawing.Point(150, 464)
$btnActions.Size     = New-Object System.Drawing.Size(160, 28)
$btnActions.BackColor = $C_BG2
$btnActions.ForeColor = $C_GRAY
$btnActions.FlatStyle = "Flat"
$btnActions.FlatAppearance.BorderColor = $C_BORDER
$form.Controls.Add($btnActions)

$lblVer = New-Object System.Windows.Forms.Label
$lblVer.Text      = "truman.co.th / myerpconnect v1.2"
$lblVer.Location  = New-Object System.Drawing.Point(380, 470)
$lblVer.Size      = New-Object System.Drawing.Size(220, 18)
$lblVer.ForeColor = [System.Drawing.Color]::FromArgb(50, 50, 50)
$lblVer.TextAlign = "MiddleRight"
$form.Controls.Add($lblVer)

function Log([string]$text, [System.Drawing.Color]$color) {
    $txtLog.SelectionStart  = $txtLog.TextLength
    $txtLog.SelectionLength = 0
    $txtLog.SelectionColor  = $color
    $txtLog.AppendText("$text`n")
    $txtLog.ScrollToCaret()
    [System.Windows.Forms.Application]::DoEvents()
}

function Refresh-Status {
    $branch  = (Run-Git @("branch", "--show-current")).Out
    $changed = (Run-Git @("status", "--porcelain")).Out
    $count   = ($changed -split "`n" | Where-Object { $_.Trim() -ne "" }).Count
    if ($count -gt 0) {
        $lblStat.Text      = "branch: $branch   |   $count files changed (not pushed yet)"
        $lblStat.ForeColor = $C_YELLOW
    } else {
        $lblStat.Text      = "branch: $branch   |   No changes"
        $lblStat.ForeColor = $C_GREEN
    }
}

$btnDeploy.Add_Click({
    $msg = $txtMsg.Text.Trim()
    if ([string]::IsNullOrEmpty($msg)) { $msg = "Update" }
    $btnDeploy.Enabled = $false
    $btnDeploy.Text    = "..."
    $txtLog.Clear()
    Log "=== Deploy to truman.co.th ===" $C_RED
    Log (Get-Date -Format "yyyy-MM-dd HH:mm:ss") $C_GRAY
    Log "" $C_WHITE
    Log "> git add ." $C_YELLOW
    $r = Run-Git @("add", ".")
    if ($r.Out) { Log $r.Out $C_WHITE }
    Log "> git commit -m `"$msg`"" $C_YELLOW
    $r = Run-Git @("commit", "-m", $msg)
    if ($r.Out -match "nothing to commit") {
        Log "  (nothing to commit - skipping)" $C_GRAY
    } else {
        $color = if ($r.Code -eq 0) { $C_WHITE } else { $C_ERR }
        Log $r.Out $color
    }
    Log "> git push origin master:main" $C_YELLOW
    $r = Run-Git @("push", "origin", "master:main")
    if ($r.Code -eq 0) {
        Log $r.Out $C_GREEN
        Log "" $C_WHITE
        Log "Push successful! GitHub Actions is deploying..." $C_GREEN
        Log "   $GH_ACTIONS" $C_GRAY
    } else {
        Log $r.Out $C_ERR
        Log "Push failed - see error above" $C_ERR
    }
    Refresh-Status
    $btnDeploy.Enabled = $true
    $btnDeploy.Text    = "DEPLOY  >"
})

$btnStatus.Add_Click({
    $txtLog.Clear()
    Log "> git status" $C_YELLOW
    Log (Run-Git @("status")).Out $C_WHITE
    Log "" $C_WHITE
    Log "> git log --oneline -5" $C_YELLOW
    Log (Run-Git @("log", "--oneline", "-5")).Out $C_GRAY
    Refresh-Status
})

$btnActions.Add_Click({
    Start-Process $GH_ACTIONS
})

Refresh-Status
[void]$form.ShowDialog()
