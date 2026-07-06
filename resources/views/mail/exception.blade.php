<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>{!! $css ?? '' !!}</style>
    <style>
        .exception-url-banner {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #1f2937;
            color: #f9fafb;
        }
        .exception-url-banner .inner {
            max-width: 1024px;
            margin: 0 auto;
            padding: 14px 15px;
        }
        .exception-url-banner .method {
            display: inline-block;
            background: #f9fafb;
            color: #1f2937;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            padding: 2px 8px;
            border-radius: 4px;
            margin-right: 10px;
            vertical-align: middle;
        }
        .exception-url-banner a {
            color: #93c5fd;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            word-break: break-all;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    @if (!empty($url))
        <div class="exception-url-banner">
            <div class="inner">
                <span class="method">{{ $method ?? '' }}</span>
                <a href="{{ $url }}">{{ $url }}</a>
            </div>
        </div>
    @endif
    {!! $content ?? '' !!}
</body>
</html>
