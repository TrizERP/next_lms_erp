<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Blog</title>
    <style>
        body {
            background-color: rgb(255, 255, 255);
            color: #111;
            font-family: Arial, sans-serif;
            padding: 40px;
        }

        h1 {
            color: rgb(99, 16, 233);
        }

        form {
            max-width: 700px;
            margin: auto;
            background-color: rgb(207, 187, 243);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="text"],
        select,
        textarea:not(#editor) {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 5px;
            border: none;
            border-radius: 5px;
            background-color: #F9FAFB;
            color: #111;
            font-size: 16px;
        }

        input[type="file"] {
            margin-top: 5px;
            background-color: #fff;
            color: #000;
            border-radius: 5px;
            padding: 10px;
        }

        button {
            margin-top: 20px;
            padding: 12px 20px;
            background-color: rgb(97, 33, 199);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background-color: rgb(155, 96, 250);
        }

        .error {
            color: #F87171;
        }

        .image-preview {
            margin-top: 10px;
        }

        .image-preview img {
            max-width: 150px;
            border-radius: 8px;
            border: 2px solid #fff;
        }
    </style>
</head>
<body>
    <h1 style="text-align:center;">Edit Blog Post</h1>

    <form action="{{ route('blogs.update', $blog->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <label for="type">Type</label>
        <select name="type" id="type">
            <option value="blog" {{ $blog->type == 'blog' ? 'selected' : ''}}>Blog</option>
            <option value="case study" {{ $blog->type == 'case study' ? 'selected' : ''}}>Case Study</option>
        </select>

        <label for="author">Author</label>
        <input type="text" name="author" id="author" value="{{ $blog->author}}">

        <label for="image">Image</label>
        <div style="display: flex; align-items: center; gap: 12px;">
            <input type="file" name="image" id="image">
            @if ($blog->image)
                <a href="{{ asset($blog->image) }}" target="_blank" style="color: #3333ee; text-decoration: underline; font-weight: bold;">
                    View
                </a>
            @endif
        </div>

        <label for="title">Title</label>
        <input type="text" name="title" id="title" value="{{ $blog->title}}">

        <label for="description">Description</label>
        <textarea name="description" id="editor">{{ $blog->description}}</textarea>

        <label for="slug">Slug</label>
        <input type="text" name="slug" id="slug" value="{{ $blog->slug}}">

        <label for="meta_title">Meta Title</label>
        <input type="text" name="meta_title" id="meta_title" value="{{ $blog->meta_title}}">

        <label for="meta_description">Meta Description</label>
        <input type="text" name="meta_description" id="meta_description" value="{{ $blog->meta_description}}">

        <label for="meta_keyword">Meta Keywords</label>
        <input type="text" name="meta_keyword" id="meta_keyword" value="{{ $blog->meta_keyword}}">

        <label for="status">Status</label>
        <select name="status" id="status">
            <option value="published" {{ $blog->status == 'published' ? 'selected' : ''}}>Published</option>
            <option value="draft" {{ $blog->status == 'draft' ? 'selected' : ''}}>Draft</option>
            <option value="archieved" {{ $blog->status == 'archieved' ? 'selected' : ''}}>Archieved</option>
        </select>

        <button type="submit">Update Blog</button>
    </form>

    <script src="https://cdn.ckeditor.com/4.16.0/standard/ckeditor.js"></script>
    <script>
        CKEDITOR.replace('editor');
    </script>
</body>
</html>