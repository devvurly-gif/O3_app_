# Liste les imprimantes installées et leurs capacités, en JSON sur la sortie
# standard. Appelé par agent.cjs pour la route GET /printers.
#
# On passe par System.Drawing.Printing plutôt que par les cmdlets PrintManagement
# parce que c'est la seule source qui donne d'un coup les formats papier ET les
# résolutions ET la zone imprimable — exactement ce dont l'aperçu a besoin pour
# se caler sur l'imprimante réelle.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

# Sans ça, PowerShell 5.1 écrit dans la codepage OEM de la console et les noms
# d'imprimantes accentués arrivent en charabia côté Node.
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)

# Les dimensions .NET sont en centièmes de pouce.
function ToMm([double]$hundredthsOfInch) {
    return [math]::Round($hundredthsOfInch * 0.254, 2)
}

$defaultName = ''
try {
    $defaultName = (New-Object System.Drawing.Printing.PrinterSettings).PrinterName
} catch {
    $defaultName = ''
}

$result = @()

foreach ($name in [System.Drawing.Printing.PrinterSettings]::InstalledPrinters) {
    try {
        $settings = New-Object System.Drawing.Printing.PrinterSettings
        $settings.PrinterName = $name
        if (-not $settings.IsValid) { continue }

        $papers = @()
        foreach ($paper in $settings.PaperSizes) {
            if ($paper.Width -le 0 -or $paper.Height -le 0) { continue }
            $papers += [pscustomobject]@{
                name     = $paper.PaperName
                widthMm  = ToMm $paper.Width
                heightMm = ToMm $paper.Height
            }
        }

        # Les résolutions nommées (Brouillon, Basse…) sortent avec un X négatif :
        # seules les valeurs réelles en dpi nous intéressent.
        $resolutions = @()
        foreach ($res in $settings.PrinterResolutions) {
            if ($res.X -gt 0) {
                $resolutions += [pscustomobject]@{ x = $res.X; y = $res.Y }
            }
        }

        $defaultPaper = $null
        $printable    = $null
        try {
            $page = $settings.DefaultPageSettings
            $defaultPaper = [pscustomobject]@{
                name     = $page.PaperSize.PaperName
                widthMm  = ToMm $page.PaperSize.Width
                heightMm = ToMm $page.PaperSize.Height
            }
            # Zone réellement atteignable par la tête : tout ce qui déborde est
            # rogné par le pilote, d'où le liseré hachuré dans l'aperçu.
            $area = $page.PrintableArea
            $printable = [pscustomobject]@{
                leftMm   = ToMm $area.X
                topMm    = ToMm $area.Y
                widthMm  = ToMm $area.Width
                heightMm = ToMm $area.Height
            }
        } catch {
            # Imprimante hors ligne ou pilote capricieux : on garde la fiche
            # sans ses réglages par défaut plutôt que de la faire disparaître.
        }

        $result += [pscustomobject]@{
            name        = $name
            isDefault   = ($name -eq $defaultName)
            papers      = $papers
            resolutions = $resolutions
            paper       = $defaultPaper
            printable   = $printable
        }
    } catch {
        continue
    }
}

# -Compress évite les retours à la ligne Windows au milieu du JSON ; la
# profondeur couvre imprimante > papiers > champs.
ConvertTo-Json -InputObject @($result) -Depth 6 -Compress
