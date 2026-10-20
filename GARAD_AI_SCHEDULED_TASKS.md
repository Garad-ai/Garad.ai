# Garad AI scheduled tasks

Garad scheduled workflows are executed by the isolated Artisan command:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\garad.ai\artisan garad-chat:process-scheduled-tasks
```

The command selects only active `Schedule` workflows marked `trigger_config.garad_task`, with a due `next_run_at`. It does not run the Laravel global schedule or legacy AI Agent triggers. Per-workflow cache locks prevent concurrent duplicate runs; run results are recorded in workflow history and delivered once into the owning user's chat.

Missed recurring occurrences are coalesced: a pass runs each overdue task once, then advances it to the next future cron occurrence. It does not replay every missed interval. A failed recurring run is retained as failed and the next regular occurrence remains scheduled. A failed one-time task remains visible as failed with no next occurrence; the user can explicitly retry it from the task details. Failed chat delivery is recorded separately and retried on the next isolated passes with a 1, 5, 15, then 60 minute backoff. With chat notifications disabled, the run result remains available in task history and is not copied to chat.

The only supported delivery destination is the user's Garad chat when enabled. Email, push, and other delivery channels are not implied by this runner.

On this Windows development machine the dedicated task **Garad AI Chat Pro Scheduled Tasks** runs every minute from the Laravel backend directory. Its action invokes `wscript.exe` with `scripts\run-garad-scheduled-tasks-hidden.vbs`; that hidden launcher runs the same PHP Artisan command, waits for completion, and appends console output to `storage\logs\garad-scheduled-task-console.log`. The task continues to use the existing interactive Windows account, so the Garad web page/browser can be closed, but the Windows account must remain signed in. It does not execute while the account is signed out or the machine is off; `StartWhenAvailable` may run a missed pass after the machine and account are available again, but individual missed occurrences are not guaranteed to be replayed.

Inspect the Windows task's last execution with:

```powershell
Get-ScheduledTaskInfo -TaskName 'Garad AI Chat Pro Scheduled Tasks'
```

Execution logs are written to Laravel's `storage/logs/laravel.log`. Provider failures remain failed workflow runs and are surfaced as failure messages; they are not converted into successful results.
Each empty pass is recorded in the Laravel log as `[GaradChat] No Garad scheduled tasks were due.` Console output and errors are also retained in `storage/logs/garad-scheduled-task-console.log`.
