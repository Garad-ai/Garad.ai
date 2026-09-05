<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ __('Call History Export') }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            margin: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .header h1 {
            font-size: 18px;
            margin: 0 0 5px;
        }

        .header p {
            font-size: 10px;
            color: #6b7280;
            margin: 0;
        }

        .call {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .call-meta {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .call-meta strong {
            color: #333;
        }

        .transcript {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f3f4f6;
        }

        .transcript-row {
            margin-bottom: 6px;
            padding: 6px 10px;
            border-radius: 4px;
        }

        .transcript-agent {
            background-color: #f4f4f4;
        }

        .transcript-user {
            background-color: #f3e2fd;
        }

        .transcript-role {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .transcript-message {
            font-size: 11px;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ __('Call History') }}</h1>
        <p>{{ __('Exported') }} {{ $exported_at }} &middot; {{ $calls->count() }} {{ __('calls') }}</p>
    </div>

    @foreach ($calls as $call)
        <div class="call">
            <div class="call-meta">
                <strong>{{ $call->agent?->title ?? __('Unknown Agent') }}</strong>
                &middot;
                {{ $call->caller_number ?? __('Unknown') }} &rarr; {{ $call->called_number ?? __('Unknown') }}
                &middot;
                <strong>{{ strtoupper($call->status?->value ?? $call->status ?? '') }}</strong>
                &middot;
                {{ $call->started_at?->format('Y-m-d H:i:s') }}
                @if ($call->duration)
                    &middot; {{ gmdate('i:s', $call->duration) }}
                @endif
            </div>

            @if ($call->transcripts->isNotEmpty())
                <div class="transcript">
                    @foreach ($call->transcripts as $transcript)
                        <div class="transcript-row transcript-{{ $transcript->role?->value ?? $transcript->role }}">
                            <div class="transcript-role">{{ strtoupper($transcript->role?->value ?? $transcript->role ?? '') }}</div>
                            <div class="transcript-message">{!! nl2br(e($transcript->message)) !!}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</body>

</html>
