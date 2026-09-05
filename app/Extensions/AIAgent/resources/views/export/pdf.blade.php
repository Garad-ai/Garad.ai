<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ __('Conversation Export') }}</title>
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

        .message {
            margin-bottom: 12px;
            padding: 10px 14px;
            border-radius: 8px;
        }

        .message-inbound {
            background-color: #f3e2fd;
        }

        .message-outbound {
            background-color: #f4f4f4;
        }

        .message-role {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .message-time {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .message-content {
            font-size: 12px;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $channelName ?? __('AI Agent') }}</h1>
        <p>
            {{ __('Conversation') }} #{{ $conversation->id }}
            @if ($conversation->sender_id)
                | {{ $conversation->sender_id }}
            @endif
            | {{ __('Exported') }} {{ now()->format('Y-m-d H:i:s') }}
        </p>
    </div>

    @foreach ($messages as $message)
        <div class="message message-{{ $message->direction }}">
            <div class="message-role">{{ $message->direction === 'inbound' ? __('User') : __('Agent') }}</div>
            <div class="message-content">{!! nl2br(e($message->content)) !!}</div>
            <div class="message-time">{{ $message->created_at?->format('Y-m-d H:i:s') }}</div>
        </div>
    @endforeach
</body>

</html>
