<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Litstack - {{ $scopeLabel }}</title>
    <style>
        body {
            font-family: sans-serif;
            color: #1f1a15;
            font-size: 12px;
        }

        h1 {
            font-family: serif;
            font-size: 22px;
            font-weight: normal;
            color: #1f1a15;
            margin: 0 0 4px;
        }

        .meta {
            color: #8a7f72;
            font-size: 11px;
            margin: 0 0 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #8a7f72;
            border-bottom: 1px solid #dccfb5;
            padding: 0 0 6px;
        }

        td {
            padding: 7px 0;
            border-bottom: 1px solid #e8ddc9;
        }

        .author {
            color: #8a7f72;
            width: 45%;
        }

        .title {
            font-weight: bold;
        }

        .empty {
            color: #8a7f72;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>{{ $scopeLabel }}</h1>
    <p class="meta">
        Exported {{ $generatedAt->format('F j, Y') }}
        &middot; {{ $userBooks->count() }} {{ $userBooks->count() === 1 ? 'book' : 'books' }}
    </p>

    @if ($userBooks->isEmpty())
        <p class="empty">No books in this list yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th class="author">Author</th>
                    <th>Title</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($userBooks as $userBook)
                    <tr>
                        <td class="author">{{ $userBook->book->author ?: '—' }}</td>
                        <td class="title">{{ $userBook->book->title }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
