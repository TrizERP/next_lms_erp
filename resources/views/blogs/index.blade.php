<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blog List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background-color: rgb(255, 255, 255);
            color: rgb(255, 255, 255);
            font-family: Arial, sans-serif;
            padding: 40px;
        }

        h1 {
            color: rgb(99, 16, 233);
        }

        a {
            background-color: rgb(207, 187, 243);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            background-color:rgb(226, 184, 245);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background-color:rgb(214, 202, 247);
            border-radius: 10px;
            overflow: hidden;
            color: #111;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #444;
        }

        th {
            background-color:rgb(73, 37, 235);
            color: white;
        }

        tr:hover {
            background-color:rgb(117, 83, 156);
            color: white;
        }

        .success {
            color: #10B981;
            margin-top: 20px;
        }
        
        .action-icons {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .action-icons a,
        .action-icons button {
            background: none;
            border: none;
            color: #111;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s;
        }

        .action-icons a:hover,
        .action-icons button:hover {
            color: #ff4081; /* pinkish highlight on hover */
        }

        .action-icons form {
            margin: 0;
            padding: 0;
        }

    </style>
</head>
<body>
    <h1>All Blogs</h1>

    <a href="{{ route('blogs.create') }}">+ Create New Blog</a>

    @if (session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Author</th>
                <th>Status</th>
                <th>Slug</th>
                <th>Image</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($blogs as $blog)
                <tr>
                    <td>{{ $blog->title }}</td>
                    <td>{{ $blog->type }}</td>
                    <td>{{ $blog->author }}</td>
                    <td>{{ $blog->status }}</td>
                    <td>{{ $blog->slug }}</td>
                    <td>
                        @if ($blog->image)
                            <img src="{{$blog->image}}" width="100" height="80">
                        @endif
                    </td>
                    <td>
                        <div class="action-icons">
                            <a href="{{ route('blogs.edit', $blog->id) }}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('blogs.destroy', $blog->id) }}" method="POST" onsubmit="return confirm('Delete this blog?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No blogs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
