Option Explicit

Dim shell, command, exitCode
Set shell = CreateObject("WScript.Shell")
shell.CurrentDirectory = "C:\xampp\htdocs\garad.ai"

command = "cmd.exe /d /c """"C:\xampp\php\php.exe"" ""C:\xampp\htdocs\garad.ai\artisan"" garad-chat:process-scheduled-tasks >> ""C:\xampp\htdocs\garad.ai\storage\logs\garad-scheduled-task-console.log"" 2>&1"""
exitCode = shell.Run(command, 0, True)
WScript.Quit exitCode
