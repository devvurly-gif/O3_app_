# Envoie un fichier d'octets bruts vers une file d'impression Windows nommée.
# Appelé par agent.cjs quand l'utilisateur a choisi une imprimante dans la page.
#
# Pourquoi ne pas utiliser Out-Printer : ces cmdlets passent par le pilote, qui
# interpréterait le TSPL comme du texte à mettre en page. Il faut le datatype
# "RAW" du spouleur pour que les octets arrivent intacts à l'imprimante — d'où
# l'appel direct à winspool.drv. Fonctionne avec USB, LAN et Wi-Fi sans exiger
# que l'imprimante soit partagée.

param(
    [Parameter(Mandatory = $true)][string]$PrinterName,
    [Parameter(Mandatory = $true)][string]$Path
)

$ErrorActionPreference = 'Stop'

Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;

public static class O3RawPrinter
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct DocInfo
    {
        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
    }

    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool OpenPrinter(string printerName, out IntPtr handle, IntPtr defaults);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool ClosePrinter(IntPtr handle);

    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool StartDocPrinter(IntPtr handle, int level, ref DocInfo info);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool EndDocPrinter(IntPtr handle);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool StartPagePrinter(IntPtr handle);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool EndPagePrinter(IntPtr handle);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool WritePrinter(IntPtr handle, IntPtr bytes, int count, out int written);

    private static Exception Fail(string call)
    {
        return new Exception(call + " a echoue (code " + Marshal.GetLastWin32Error() + ")");
    }

    public static void Send(string printerName, byte[] payload)
    {
        IntPtr handle;
        if (!OpenPrinter(printerName, out handle, IntPtr.Zero)) throw Fail("OpenPrinter");

        try
        {
            DocInfo info = new DocInfo();
            info.pDocName  = "O3 Etiquettes";
            info.pDataType = "RAW";

            if (!StartDocPrinter(handle, 1, ref info)) throw Fail("StartDocPrinter");
            try
            {
                if (!StartPagePrinter(handle)) throw Fail("StartPagePrinter");

                IntPtr buffer = Marshal.AllocCoTaskMem(payload.Length);
                try
                {
                    Marshal.Copy(payload, 0, buffer, payload.Length);
                    int written;
                    if (!WritePrinter(handle, buffer, payload.Length, out written)) throw Fail("WritePrinter");
                    if (written != payload.Length) throw new Exception("Envoi partiel : " + written + "/" + payload.Length);
                }
                finally
                {
                    Marshal.FreeCoTaskMem(buffer);
                    EndPagePrinter(handle);
                }
            }
            finally
            {
                EndDocPrinter(handle);
            }
        }
        finally
        {
            ClosePrinter(handle);
        }
    }
}
'@

[O3RawPrinter]::Send($PrinterName, [System.IO.File]::ReadAllBytes($Path))
